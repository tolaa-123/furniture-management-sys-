<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}
require_once __DIR__ . '/../../../config/db_config.php';
$managerName = $_SESSION['user_name'] ?? 'Manager User';

$csrf_token = $_SESSION['csrf_token'] ?? null;
if (!$csrf_token) {
    $csrf_token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrf_token;
}

$message = '';
$messageType = '';

// Handle employee assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign') {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = 'Invalid CSRF token.';
        $messageType = 'danger';
    } else {
        $orderId = $_POST['order_id'] ?? 0;
        $employeeId = $_POST['employee_id'] ?? 0;
        if ($orderId && $employeeId) {
            try {
                $pdo->beginTransaction();

                // Enforce: if order was created by an employee, only that employee can be assigned
                $orderCheck = $pdo->prepare("SELECT created_by_employee_id FROM furn_orders WHERE id = ?");
                $orderCheck->execute([$orderId]);
                $orderRow = $orderCheck->fetch(PDO::FETCH_ASSOC);
                $creatorId = intval($orderRow['created_by_employee_id'] ?? 0);
                if ($creatorId > 0 && intval($employeeId) !== $creatorId) {
                    throw new Exception('This order was created by a specific employee and must be assigned to them only.');
                }

                // Check if column exists before using it
                $stmt = $pdo->query("DESCRIBE furn_orders");
                $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Use OrderModel to start production (this will automatically deduct reserved materials)
                require_once __DIR__ . '/../../../app/models/OrderModel.php';
                $orderModel = new OrderModel();
                
                try {
                    // Start production - this will deduct reserved stock automatically
                    $orderModel->startProduction($orderId);
                    
                    // Update assigned employee if column exists
                    if (in_array('assigned_employee_id', $columns)) {
                        $stmt = $pdo->prepare("UPDATE furn_orders SET assigned_employee_id = ? WHERE id = ?");
                        $stmt->execute([$employeeId, $orderId]);
                    }
                } catch (Exception $e) {
                    throw new Exception('Failed to start production: ' . $e->getMessage());
                }
                
                // Create production task for employee
                // First check if furn_production_tasks table exists
                $stmt = $pdo->query("SHOW TABLES LIKE 'furn_production_tasks'");
                $taskTableExists = $stmt->rowCount() > 0;
                
                if ($taskTableExists) {
                    // Get order details for the task
                    $stmt = $pdo->prepare("SELECT * FROM furn_orders WHERE id = ?");
                    $stmt->execute([$orderId]);
                    $order = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Check if task already exists for this order
                    $stmt = $pdo->prepare("SELECT id FROM furn_production_tasks WHERE order_id = ? AND employee_id = ?");
                    $stmt->execute([$orderId, $employeeId]);
                    $existingTask = $stmt->fetch();
                    
                    if (!$existingTask) {
                        // Create new production task
                        $deadline = $order['preferred_delivery_date'] ?? date('Y-m-d', strtotime('+30 days'));
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO furn_production_tasks (
                                order_id, employee_id, product_id, 
                                status, progress, deadline, 
                                created_at, updated_at
                            ) VALUES (?, ?, ?, 'pending', 0, ?, NOW(), NOW())
                        ");
                        $stmt->execute([
                            $orderId, 
                            $employeeId, 
                            $order['product_id'] ?? null,
                            $deadline
                        ]);
                    }
                }
                
                $pdo->commit();
                // Notify assigned employee
                require_once __DIR__ . '/../../../app/includes/notification_helper.php';
                $orderNum = $order['order_number'] ?? '#'.$orderId;
                $furnitureName = $order['furniture_name'] ?? $order['furniture_type'] ?? 'Custom Furniture';
                insertNotification($pdo, $employeeId, 'production', 'New Task Assigned',
                    'You have been assigned to produce: ' . $furnitureName . ' (Order ' . $orderNum . ')',
                    $orderId, '/employee/tasks', 'high');
                $message = 'Order assigned successfully to employee!';
                $messageType = 'success';
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'danger';
                error_log("Assignment error: " . $e->getMessage());
            }
        }
    }
}

// Fetch unassigned orders (payment_verified OR deposit_paid with estimated_cost)
$unassignedOrders = [];
try {
    // Ensure required columns exist
    $pdo->exec("ALTER TABLE furn_orders ADD COLUMN IF NOT EXISTS assigned_employee_id INT DEFAULT NULL");
    $pdo->exec("ALTER TABLE furn_orders ADD COLUMN IF NOT EXISTS estimated_cost DECIMAL(12,2) DEFAULT NULL");
    $pdo->exec("ALTER TABLE furn_orders ADD COLUMN IF NOT EXISTS created_by_employee_id INT DEFAULT NULL");
    
    $stmt = $pdo->query("
        SELECT o.*, u.first_name, u.last_name,
               COALESCE(o.estimated_cost, o.total_amount, 0) as estimated_cost
        FROM furn_orders o
        LEFT JOIN furn_users u ON o.customer_id = u.id
        WHERE (
            o.status IN ('payment_verified', 'deposit_paid')
            OR (o.status = 'cost_estimated' AND o.deposit_paid > 0)
            OR (o.deposit_paid > 0 AND (o.status IS NULL OR o.status = ''))
        )
        AND (
            -- Not yet assigned at all
            (o.assigned_employee_id IS NULL OR o.assigned_employee_id = 0)
            OR
            -- Pre-assigned (employee-created) but no production task created yet
            (o.assigned_employee_id IS NOT NULL AND o.assigned_employee_id > 0
             AND NOT EXISTS (
                 SELECT 1 FROM furn_production_tasks pt WHERE pt.order_id = o.id
             ))
        )
        ORDER BY o.created_at DESC
    ");
    $unassignedOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Orders error: " . $e->getMessage());
}

// Fetch employees with workload
$employees = [];
try {
    // First get all employees
    $stmt = $pdo->query("
        SELECT u.id, u.first_name, u.last_name, u.email, u.role
        FROM furn_users u
        WHERE u.role = 'employee'
        ORDER BY u.first_name ASC
    ");
    
    if (!$stmt) {
        throw new Exception("Query failed: " . implode(", ", $pdo->errorInfo()));
    }
    
    $allEmployees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Found " . count($allEmployees) . " employees");
    
    // Then count active orders for each employee
    foreach ($allEmployees as $emp) {
        $countStmt = $pdo->prepare("
            SELECT COUNT(*) as active_orders
            FROM furn_orders o
            WHERE o.assigned_employee_id = ? AND o.status IN ('in_production', 'production_started')
        ");
        
        if (!$countStmt) {
            error_log("Count query prepare failed: " . implode(", ", $pdo->errorInfo()));
            $emp['active_orders'] = 0;
        } else {
            $countStmt->execute([$emp['id']]);
            $result = $countStmt->fetch(PDO::FETCH_ASSOC);
            $emp['active_orders'] = (int)($result['active_orders'] ?? 0);
        }

        // Average rating
        try {
            $r = $pdo->prepare("SELECT ROUND(AVG(rating),1) as avg_rating, COUNT(*) as total_ratings FROM furn_ratings WHERE employee_id=?");
            $r->execute([$emp['id']]);
            $rRow = $r->fetch(PDO::FETCH_ASSOC);
            $emp['avg_rating']    = floatval($rRow['avg_rating'] ?? 0);
            $emp['total_ratings'] = intval($rRow['total_ratings'] ?? 0);
        } catch(PDOException $e2) { $emp['avg_rating'] = 0; $emp['total_ratings'] = 0; }

        // Attendance rate this month
        try {
            $today = date('Y-m-d');
            $monthStart = date('Y-m-01');
            $attCols = $pdo->query("SHOW COLUMNS FROM furn_attendance")->fetchAll(PDO::FETCH_COLUMN);
            $dateExpr = in_array('date', $attCols) ? 'date' : 'DATE(check_in_time)';
            $a = $pdo->prepare("SELECT COUNT(*) as present FROM furn_attendance WHERE employee_id=? AND $dateExpr BETWEEN ? AND ? AND status IN ('present','late')");
            $a->execute([$emp['id'], $monthStart, $today]);
            $presentDays = intval($a->fetchColumn());
            $workingDays = max(1, (int)date('j')); // days elapsed this month
            $emp['attendance_rate'] = round(($presentDays / $workingDays) * 100);
            $emp['present_today']   = false;
            $pt = $pdo->prepare("SELECT COUNT(*) FROM furn_attendance WHERE employee_id=? AND $dateExpr=?");
            $pt->execute([$emp['id'], $today]);
            $emp['present_today'] = intval($pt->fetchColumn()) > 0;
        } catch(PDOException $e2) { $emp['attendance_rate'] = 0; $emp['present_today'] = false; }

        // Completed tasks count
        try {
            $ct = $pdo->prepare("SELECT COUNT(*) FROM furn_production_tasks WHERE employee_id=? AND status='completed'");
            $ct->execute([$emp['id']]);
            $emp['completed_tasks'] = intval($ct->fetchColumn());
        } catch(PDOException $e2) { $emp['completed_tasks'] = 0; }

        $employees[] = $emp;
    }
    
    // Ensure employees array is properly formatted
    if (empty($employees)) {
        error_log("No employees found in system");
    }
    
    // Sort by active orders
    usort($employees, function($a, $b) {
        return $a['active_orders'] - $b['active_orders'];
    });
    
    error_log("Employees array after processing: " . json_encode($employees));
    
} catch (PDOException $e) {
    error_log("Employees error: " . $e->getMessage());
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
}

$pageTitle = 'Assign Orders to Employees';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - FurnitureCraft</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/css/admin-responsive.css">
    <style>
        .assign-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow-y: auto;
        }
        .assign-modal.active {
            display: flex;
        }
        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 30px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            animation: slideIn 0.3s ease;
            max-height: 90vh;
            overflow-y: auto;
            margin: auto;
        }
        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            flex-wrap: wrap;
            gap: 10px;
        }
        .modal-title {
            font-size: 22px;
            font-weight: 700;
            color: #2c3e50;
            flex: 1;
            min-width: 200px;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 28px;
            color: #7f8c8d;
            cursor: pointer;
            transition: color 0.3s;
            padding: 0;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-close:hover {
            color: #2c3e50;
        }
        .order-info {
            background: linear-gradient(135deg, #f8f9fa 0%, #f0f2f5 100%);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            border-left: 5px solid #3498DB;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .info-label {
            color: #7f8c8d;
            font-weight: 600;
            min-width: 100px;
        }
        .info-value {
            color: #2c3e50;
            font-weight: 700;
            flex: 1;
            text-align: right;
        }
        .employee-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
            max-height: 350px;
            overflow-y: auto;
            margin-bottom: 20px;
            padding-right: 5px;
        }
        .employee-grid::-webkit-scrollbar {
            width: 6px;
        }
        .employee-grid::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        .employee-grid::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }
        .employee-grid::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        .employee-option {
            padding: 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .employee-option:hover {
            border-color: #3498DB;
            background: #f8f9fa;
        }
        .employee-option.selected {
            border-color: #27AE60;
            background: #d4edda;
        }
        .employee-info {
            flex: 1;
            min-width: 150px;
        }
        .employee-name {
            font-weight: 700;
            color: #2c3e50;
            font-size: 15px;
            word-break: break-word;
        }
        .employee-email {
            font-size: 12px;
            color: #7f8c8d;
            margin-top: 3px;
            word-break: break-word;
        }
        .employee-workload {
            background: #e9ecef;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: #2c3e50;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            flex-wrap: wrap;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        .btn-assign {
            background: linear-gradient(135deg, #27AE60 0%, #229954 100%);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
            min-width: 120px;
        }
        .btn-assign:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }
        .btn-assign:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            transform: none;
        }
        .btn-cancel {
            background: #e9ecef;
            color: #2c3e50;
            padding: 12px 30px;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
            min-width: 120px;
        }
        .btn-cancel:hover {
            background: #d4a574;
            color: white;
        }
        .assign-btn {
            background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 13px;
        }
        .assign-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }
        .no-employees {
            text-align: center;
            padding: 40px 20px;
            color: #7f8c8d;
        }
        .no-employees i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.3;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .assign-modal {
                padding: 10px;
            }
            .modal-content {
                padding: 20px;
                max-height: 95vh;
            }
            .modal-title {
                font-size: 18px;
            }
            .info-row {
                flex-direction: column;
                gap: 5px;
            }
            .info-label {
                min-width: auto;
            }
            .info-value {
                text-align: left;
            }
            .employee-option {
                flex-direction: column;
                align-items: flex-start;
            }
            .employee-workload {
                align-self: flex-start;
            }
            .modal-actions {
                flex-direction: column;
            }
            .btn-assign,
            .btn-cancel {
                width: 100%;
            }
        }
        
        @media (max-width: 480px) {
            .modal-content {
                padding: 15px;
                border-radius: 12px;
            }
            .modal-title {
                font-size: 16px;
            }
            .order-info {
                padding: 15px;
                margin-bottom: 20px;
            }
            .info-row {
                font-size: 13px;
            }
            .employee-grid {
                max-height: 300px;
            }
            .employee-option {
                padding: 12px;
            }
            .employee-name {
                font-size: 14px;
            }
            .employee-email {
                font-size: 11px;
            }
            .employee-workload {
                font-size: 11px;
                padding: 4px 10px;
            }
        }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>
    <div class="sidebar-overlay"></div>
    
    <?php include_once __DIR__ . '/../../includes/manager_sidebar.php'; ?>
    
    <!-- Top Header -->
    <?php 
    $pageTitle = 'Assign Employees';
    include_once __DIR__ . '/../../includes/manager_header.php'; 
    ?>
        <div class="header-left"><div class="system-status"><i class="fas fa-circle"></i> Workshop Manager</div></div>
        <div class="header-right">
            <div class="admin-profile">
                <div class="admin-avatar"><?php echo strtoupper(substr($managerName, 0, 1)); ?></div>
                <div><div style="font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($managerName); ?></div><div class="admin-role-badge">MANAGER</div></div>
            </div>
        </div>
    </div>
    
    <div class="main-content">
        <h2 style="margin-bottom: 30px; color: #2c3e50;"><i class="fas fa-tasks me-2"></i><?php echo $pageTitle; ?></h2>

        <!-- Employee Stats -->
        <?php
        $eStats = [];
        try {
            $eStats['total']    = (int)$pdo->query("SELECT COUNT(*) FROM furn_users WHERE role='employee'")->fetchColumn();
            $attCols2 = $pdo->query("SHOW COLUMNS FROM furn_attendance")->fetchAll(PDO::FETCH_COLUMN);
            $dExpr2 = in_array('date',$attCols2)?'date':'DATE(check_in_time)';
            $eStats['present']  = (int)$pdo->query("SELECT COUNT(DISTINCT employee_id) FROM furn_attendance WHERE $dExpr2=CURDATE() AND status IN ('present','late')")->fetchColumn();
            $eStats['active']   = (int)$pdo->query("SELECT COUNT(*) FROM furn_production_tasks WHERE status IN ('pending','in_progress')")->fetchColumn();
            $eStats['done_month']=(int)$pdo->query("SELECT COUNT(*) FROM furn_production_tasks WHERE status='completed' AND MONTH(completed_at)=MONTH(CURDATE()) AND YEAR(completed_at)=YEAR(CURDATE())")->fetchColumn();
            $eStats['uncompleted']=(int)$pdo->query("SELECT COUNT(*) FROM furn_production_tasks WHERE status IN ('pending','in_progress')")->fetchColumn();
        } catch(PDOException $e){}
        $eCards = [
            [$eStats['total']??0,     'Total Employees',       '#3498DB','fa-user-tie'],
            [$eStats['present']??0,   'Present Today',         '#27AE60','fa-user-check'],
            [$eStats['active']??0,    'Active Tasks',          '#9B59B6','fa-tasks'],
            [$eStats['done_month']??0,'Tasks Done This Month', '#1ABC9C','fa-check-double'],
            [$eStats['uncompleted']??0,'Uncompleted Tasks',    '#E74C3C','fa-exclamation-circle'],
        ];
        $eUnassigned = 0;
        $eAvgWorkload = 0;
        try {
            $eUnassigned = (int)$pdo->query("
                SELECT COUNT(*) FROM furn_orders o
                WHERE o.status IN ('payment_verified','deposit_paid')
                AND (
                    (o.assigned_employee_id IS NULL OR o.assigned_employee_id = 0)
                    OR (o.assigned_employee_id > 0 AND NOT EXISTS (
                        SELECT 1 FROM furn_production_tasks pt WHERE pt.order_id = o.id
                    ))
                )
            ")->fetchColumn();
            $totalActive = (int)$pdo->query("SELECT COUNT(*) FROM furn_production_tasks WHERE status IN ('pending','in_progress')")->fetchColumn();
            $totalEmps   = (int)$pdo->query("SELECT COUNT(*) FROM furn_users WHERE role='employee'")->fetchColumn();
            $eAvgWorkload = $totalEmps > 0 ? round($totalActive / $totalEmps, 1) : 0;
        } catch(PDOException $e){}
        $eCards[] = [$eUnassigned, 'Unassigned Orders', '#E74C3C', 'fa-clipboard-list'];
        $eCards[] = [$eAvgWorkload, 'Avg Workload',      '#F39C12', 'fa-balance-scale'];
        ?>
        <div class="stats-grid" style="margin-bottom:24px;">
            <?php foreach($eCards as [$v,$l,$c,$i]): ?>
            <div class="stat-card" style="border-left:4px solid <?php echo $c; ?>;">
                <div style="display:flex;justify-content:space-between;align-items:start;">
                    <div><div class="stat-value"><?php echo $v; ?></div><div class="stat-label"><?php echo $l; ?></div></div>
                    <div style="font-size:28px;color:<?php echo $c; ?>;opacity:.25;"><i class="fas <?php echo $i; ?>"></i></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($message): ?>
            <div style="padding: 15px 20px; background: <?php echo $messageType === 'success' ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo $messageType === 'success' ? '#155724' : '#721c24'; ?>; border-radius: 10px; margin-bottom: 25px; border-left: 5px solid <?php echo $messageType === 'success' ? '#27AE60' : '#E74C3C'; ?>;">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i><?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Employee Workload -->
        <div class="section-card">
            <div class="section-header"><h2 class="section-title"><i class="fas fa-users me-2"></i>Employee Workload</h2></div>
            <?php if (empty($employees)): ?>
                <div class="no-employees">
                    <i class="fas fa-user-slash"></i>
                    <h4>No Employees Found</h4>
                    <p>There are no active employees in the system. Please add employees first.</p>
                </div>
            <?php else: ?>
                <div class="stats-grid">
                    <?php foreach ($employees as $emp): ?>
                        <div class="stat-card" style="border-left: 5px solid <?php echo $emp['active_orders'] > 3 ? '#E74C3C' : '#27AE60'; ?>;">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div>
                                    <div style="font-weight: 700; font-size: 16px; color: #2c3e50;"><?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></div>
                                    <div style="font-size: 13px; color: #7f8c8d; margin-top: 5px;"><?php echo htmlspecialchars($emp['email']); ?></div>
                                    <div style="margin-top: 12px;">
                                        <span style="background: <?php echo $emp['active_orders'] > 3 ? '#E74C3C' : '#27AE60'; ?>; color: white; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 700;">
                                            <i class="fas fa-tasks me-1"></i><?php echo $emp['active_orders']; ?> Active Tasks
                                        </span>
                                    </div>
                                </div>
                                <div style="font-size: 40px; color: <?php echo $emp['active_orders'] > 3 ? '#E74C3C' : '#27AE60'; ?>; opacity: 0.2;">
                                    <i class="fas fa-user-check"></i>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Orders to Assign -->
        <div class="section-card">
            <div class="section-header"><h2 class="section-title"><i class="fas fa-clipboard-list me-2"></i>Orders Ready for Assignment</h2></div>
            <?php if (empty($unassignedOrders)): ?>
                <div style="text-align: center; padding: 60px 20px; color: #7f8c8d;">
                    <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3; display: block;"></i>
                    <h4>No Orders Ready for Assignment</h4>
                    <p>All orders have been assigned or are waiting for payment.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table mobile-cards">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Furniture Type</th>
                                <th>Cost</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($unassignedOrders as $order): ?>
                                <tr>
                                    <td><strong>#<?php echo $order['id']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($order['furniture_type'] ?? 'Custom'); ?></td>
                                    <td><strong>ETB <?php echo number_format($order['estimated_cost'] ?? 0, 2); ?></strong></td>
                                    <td><span class="badge" style="background: #ffc107; color: #000; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;"><?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?></span></td>
                                    <td>
                                        <button class="assign-btn" onclick="openAssignModal(<?php echo htmlspecialchars(json_encode($order)); ?>)">
                                            <i class="fas fa-user-plus me-1"></i>Assign
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Assignment Modal -->
    <div class="assign-modal" id="assignModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-user-plus me-2"></i>Assign Order to Employee</h3>
                <button class="modal-close" onclick="closeAssignModal()" aria-label="Close modal"><i class="fas fa-times"></i></button>
            </div>

            <div class="order-info" id="orderInfo"></div>

            <label style="display: block; font-weight: 700; color: #2c3e50; margin-bottom: 10px; font-size: 15px;">
                <i class="fas fa-users me-2"></i>Select Employee
            </label>

            <!-- Filter dropdown -->
            <div style="display:flex;gap:10px;align-items:center;margin-bottom:14px;flex-wrap:wrap;">
                <label style="font-size:12px;font-weight:600;color:#555;">Sort by:</label>
                <select id="empFilterSelect" onchange="applyEmpFilter()" style="padding:7px 12px;border:1.5px solid #E0E0E0;border-radius:7px;font-size:13px;font-family:inherit;outline:none;cursor:pointer;">
                    <option value="workload">Least Workload (Recommended)</option>
                    <option value="rating">Highest Rating</option>
                    <option value="attendance">Best Attendance</option>
                    <option value="experience">Most Completed Tasks</option>
                    <option value="present">Present Today First</option>
                </select>
                <span id="empFilterInfo" style="font-size:12px;color:#888;"></span>
            </div>

            <div class="employee-grid" id="employeeList"></div>

            <div class="modal-actions">
                <button class="btn-cancel" onclick="closeAssignModal()">Cancel</button>
                <button class="btn-assign" id="assignBtn" onclick="submitAssignment()" disabled>
                    <i class="fas fa-check me-1"></i>Assign Order
                </button>
            </div>
        </div>
    </div>

    <script>
        let selectedOrder = null;
        let selectedEmployee = null;
        const employees = <?php echo json_encode($employees); ?>;

        function openAssignModal(order) {
            selectedOrder = order;
            selectedEmployee = null;

            // Display order info
            const orderInfo = `
                <div class="info-row">
                    <span class="info-label">Order ID:</span>
                    <span class="info-value">#${order.id}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Customer:</span>
                    <span class="info-value">${order.first_name} ${order.last_name}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Furniture:</span>
                    <span class="info-value">${order.furniture_type || 'Custom'}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Estimated Cost:</span>
                    <span class="info-value">ETB ${parseFloat(order.estimated_cost).toFixed(2)}</span>
                </div>
            `;
            document.getElementById('orderInfo').innerHTML = orderInfo;

            const lockedEmployeeId = order.created_by_employee_id ? parseInt(order.created_by_employee_id) : null;

            // Display employees
            const employeeList = document.getElementById('employeeList');
            if (employees.length === 0) {
                employeeList.innerHTML = '<div class="no-employees"><i class="fas fa-user-slash"></i><p>No employees available</p></div>';
            } else {
                if (lockedEmployeeId) {
                    // Order was created by an employee — show locked notice and auto-select
                    const lockedEmp = employees.find(e => parseInt(e.id) === lockedEmployeeId);
                    const lockedName = lockedEmp ? lockedEmp.first_name + ' ' + lockedEmp.last_name : 'Employee #' + lockedEmployeeId;
                    employeeList.innerHTML = `
                        <div style="background:#fff3cd; border:1px solid #ffc107; border-radius:8px; padding:15px; margin-bottom:10px;">
                            <i class="fas fa-lock me-2" style="color:#856404;"></i>
                            <strong style="color:#856404;">Locked:</strong> This order was created by <strong>${lockedName}</strong> and must be assigned to them.
                        </div>
                    ` + employees.map(emp => {
                        const isLocked = parseInt(emp.id) === lockedEmployeeId;
                        return `
                            <div class="employee-option ${isLocked ? 'selected' : ''}" 
                                 style="${!isLocked ? 'opacity:0.4; pointer-events:none;' : 'border:2px solid #27AE60;'}"
                                 onclick="${isLocked ? 'selectEmployee(' + emp.id + ', this)' : ''}">
                                <div class="employee-info">
                                    <div class="employee-name"><i class="fas fa-user-circle me-2"></i>${emp.first_name} ${emp.last_name} ${isLocked ? '<span style="background:#27AE60;color:white;padding:2px 8px;border-radius:10px;font-size:11px;margin-left:5px;">Creator</span>' : ''}</div>
                                    <div class="employee-email">${emp.email}</div>
                                </div>
                                <div class="employee-workload">
                                    <i class="fas fa-tasks me-1"></i>${emp.active_orders} Tasks
                                </div>
                            </div>
                        `;
                    }).join('');
                    // Auto-select the locked employee
                    selectedEmployee = lockedEmployeeId;
                    document.getElementById('assignBtn').disabled = false;
                } else {
                    document.getElementById('empFilterSelect').value = 'workload';
                    document.getElementById('empFilterInfo').textContent = 'Showing employees with fewest active tasks first';
                    renderEmployeeList(employees);
                }
            }

            const modal = document.getElementById('assignModal');
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function renderEmployeeList(list) {
            const employeeList = document.getElementById('employeeList');
            if (!list || list.length === 0) {
                employeeList.innerHTML = '<div class="no-employees"><i class="fas fa-user-slash"></i><p>No employees found</p></div>';
                return;
            }
            employeeList.innerHTML = list.map(emp => {
                const stars = emp.avg_rating > 0
                    ? '★'.repeat(Math.round(emp.avg_rating)) + '☆'.repeat(5 - Math.round(emp.avg_rating))
                    : '—';
                const presentBadge = emp.present_today
                    ? '<span style="background:#27AE60;color:white;padding:1px 7px;border-radius:8px;font-size:10px;margin-left:6px;">Present</span>'
                    : '<span style="background:#E74C3C;color:white;padding:1px 7px;border-radius:8px;font-size:10px;margin-left:6px;">Absent</span>';
                const workloadColor = emp.active_orders === 0 ? '#27AE60' : emp.active_orders <= 2 ? '#F39C12' : '#E74C3C';
                return `
                    <div class="employee-option" onclick="selectEmployee(${emp.id}, this)">
                        <div class="employee-info" style="flex:1;">
                            <div class="employee-name">
                                <i class="fas fa-user-circle me-2"></i>${emp.first_name} ${emp.last_name}
                                ${presentBadge}
                            </div>
                            <div class="employee-email" style="margin-top:4px;">${emp.email}</div>
                            <div style="display:flex;gap:14px;margin-top:6px;flex-wrap:wrap;font-size:12px;color:#555;">
                                <span title="Active tasks"><i class="fas fa-tasks me-1" style="color:${workloadColor};"></i><strong style="color:${workloadColor};">${emp.active_orders}</strong> active</span>
                                <span title="Completed tasks"><i class="fas fa-check-circle me-1" style="color:#3498DB;"></i>${emp.completed_tasks} done</span>
                                <span title="Average rating"><i class="fas fa-star me-1" style="color:#F39C12;"></i>${emp.avg_rating > 0 ? emp.avg_rating + '/5' : 'No ratings'}</span>
                                <span title="Attendance this month"><i class="fas fa-calendar-check me-1" style="color:#9B59B6;"></i>${emp.attendance_rate}% attendance</span>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function applyEmpFilter() {
            const filter = document.getElementById('empFilterSelect').value;
            const info   = document.getElementById('empFilterInfo');
            let sorted = [...employees];

            if (filter === 'workload') {
                sorted.sort((a,b) => a.active_orders - b.active_orders);
                info.textContent = 'Showing employees with fewest active tasks first';
            } else if (filter === 'rating') {
                sorted.sort((a,b) => b.avg_rating - a.avg_rating);
                info.textContent = 'Showing highest rated employees first';
            } else if (filter === 'attendance') {
                sorted.sort((a,b) => b.attendance_rate - a.attendance_rate);
                info.textContent = 'Showing employees with best attendance this month first';
            } else if (filter === 'experience') {
                sorted.sort((a,b) => b.completed_tasks - a.completed_tasks);
                info.textContent = 'Showing most experienced employees first';
            } else if (filter === 'present') {
                sorted.sort((a,b) => (b.present_today ? 1 : 0) - (a.present_today ? 1 : 0));
                info.textContent = 'Showing employees present today first';
            }

            renderEmployeeList(sorted);
        }

        function closeAssignModal() {
            document.getElementById('assignModal').classList.remove('active');
            selectedOrder = null;
            selectedEmployee = null;
            document.getElementById('assignBtn').disabled = true;
            
            // Restore body scroll
            document.body.style.overflow = 'auto';
        }

        function selectEmployee(employeeId, element) {
            // Remove previous selection
            document.querySelectorAll('.employee-option').forEach(el => el.classList.remove('selected'));
            
            // Add selection to clicked element
            element.classList.add('selected');
            selectedEmployee = employeeId;
            
            // Enable assign button
            document.getElementById('assignBtn').disabled = false;
        }

        function submitAssignment() {
            if (!selectedOrder || !selectedEmployee) {
                alert('Please select an employee');
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="assign">
                <input type="hidden" name="order_id" value="${selectedOrder.id}">
                <input type="hidden" name="employee_id" value="${selectedEmployee}">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        // Close modal when clicking outside
        document.getElementById('assignModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAssignModal();
            }
        });
        
        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('assignModal');
                if (modal.classList.contains('active')) {
                    closeAssignModal();
                }
            }
        });
    </script>

    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
