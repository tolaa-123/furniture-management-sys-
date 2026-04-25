<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}
require_once __DIR__ . '/../../../config/db_config.php';

$employeeName = $_SESSION['user_name'] ?? 'Employee User';
$employeeId   = $_SESSION['user_id'];

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'request_material') {
        try {
            $orderId  = !empty($_POST['order_id']) ? intval($_POST['order_id']) : null;
            $purpose  = 'Required for assigned order production';
            $matIds   = $_POST['req_mat_id']  ?? [];
            $matQtys  = $_POST['req_mat_qty'] ?? [];

            if (empty($matIds)) throw new Exception("Please add at least one material.");

            $stmt = $pdo->prepare("
                INSERT INTO furn_material_requests
                    (employee_id, material_id, quantity_requested, purpose, order_id, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ");

            $count = 0;
            $lastInsertId = null;
            foreach ($matIds as $i => $matId) {
                $matId = intval($matId);
                $qty   = floatval($matQtys[$i] ?? 0);
                if (!$matId || $qty <= 0) continue;

                // Server-side: validate qty <= available stock
                $stockStmt = $pdo->prepare("SELECT (current_stock - COALESCE(reserved_stock,0)) as available, name FROM furn_materials WHERE id = ?");
                $stockStmt->execute([$matId]);
                $mat = $stockStmt->fetch(PDO::FETCH_ASSOC);
                if (!$mat) continue;
                $available = floatval($mat['available']);
                if ($qty > $available) {
                    throw new Exception("Requested quantity ($qty) for \"{$mat['name']}\" exceeds available stock ($available). Please request $available or less.");
                }

                $stmt->execute([$employeeId, $matId, $qty, $purpose, $orderId]);
                $lastInsertId = $pdo->lastInsertId();
                $count++;
            }
            if ($count === 0) throw new Exception("No valid materials entered.");

            // Notify all managers
            require_once __DIR__ . '/../../../app/includes/notification_helper.php';
            $orderLabel = '';
            if ($orderId) {
                $oStmt = $pdo->prepare("SELECT order_number FROM furn_orders WHERE id = ?");
                $oStmt->execute([$orderId]);
                $oNum = $oStmt->fetchColumn();
                if ($oNum) $orderLabel = " for order $oNum";
            }
            notifyRole(
                $pdo, 'manager', 'material',
                'New Material Request',
                htmlspecialchars($_SESSION['user_name'] ?? 'An employee') . " requested $count material(s)$orderLabel. Please review and approve.",
                $lastInsertId,
                '/manager/inventory',
                'high'
            );

            $success = "$count material request(s) submitted! Manager has been notified.";
        } catch (Exception $e) {
            $error = "Error submitting request: " . $e->getMessage();
        }

    } elseif ($action === 'report_usage') {
        try {
            $taskId    = intval($_POST['task_id']);
            $notes     = trim($_POST['notes'] ?? '');
            $matIds    = $_POST['mat_id']  ?? [];
            $matQtys   = $_POST['mat_qty'] ?? [];
            $matWastes = $_POST['mat_waste'] ?? [];

            if (empty($matIds)) throw new Exception("Please add at least one material.");

            $pdo->beginTransaction();
            
            // Get order_id from task_id for furn_order_materials
            $orderStmt = $pdo->prepare("SELECT order_id FROM furn_production_tasks WHERE id = ?");
            $orderStmt->execute([$taskId]);
            $orderId = $orderStmt->fetchColumn();

            $stmtIns = $pdo->prepare("
                INSERT INTO furn_material_usage (employee_id, task_id, material_id, quantity_used, waste_amount, notes, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            // Also save to furn_order_materials for profit calculation (CONSOLIDATION)
            $stmtOrderMat = $pdo->prepare("
                INSERT INTO furn_order_materials (order_id, task_id, material_id, material_name, quantity_used, unit, unit_price, total_cost)
                SELECT ?, ?, m.id, m.name, ?, m.unit, m.cost_per_unit, (? * m.cost_per_unit)
                FROM furn_materials m WHERE m.id = ?
            ");
            
            // Reduce approved request qty
            $stmtReduceReq = $pdo->prepare("
                UPDATE furn_material_requests
                SET quantity_requested = GREATEST(0, quantity_requested - ?)
                WHERE employee_id = ? AND material_id = ? AND status = 'approved'
                ORDER BY approved_at DESC LIMIT 1
            ");
            // Deduct from actual stock AND release from reserved
            $stmtDeductStock = $pdo->prepare("
                UPDATE furn_materials
                SET current_stock   = GREATEST(0, current_stock - ?),
                    reserved_stock  = GREATEST(0, COALESCE(reserved_stock,0) - ?),
                    updated_at      = NOW()
                WHERE id = ?
            ");
            // Validate approved qty before committing
            $stmtCheckApproved = $pdo->prepare("
                SELECT COALESCE(SUM(quantity_requested),0) as approved_qty
                FROM furn_material_requests
                WHERE employee_id = ? AND material_id = ? AND status = 'approved'
            ");

            $count = 0;
            foreach ($matIds as $i => $matId) {
                $matId = intval($matId);
                $qty   = floatval($matQtys[$i] ?? 0);
                $waste = floatval($matWastes[$i] ?? 0);
                if (!$matId || $qty <= 0) continue;

                // Validate: must have approved request for this material
                $stmtCheckApproved->execute([$employeeId, $matId]);
                $approvedQty = floatval($stmtCheckApproved->fetchColumn());
                if ($approvedQty <= 0) {
                    throw new Exception("No approved request found for material ID $matId. Request and get approval first.");
                }
                if ($qty > $approvedQty) {
                    throw new Exception("Quantity ($qty) exceeds approved amount ($approvedQty) for material ID $matId.");
                }

                $totalConsumed = $qty + $waste;
                $stmtIns->execute([$employeeId, $taskId ?: null, $matId, $qty, $waste, $notes]);
                
                // CONSOLIDATION: Also log to furn_order_materials for profit tracking
                if ($orderId) {
                    $stmtOrderMat->execute([$orderId, $taskId, $qty, $qty, $matId]);
                }
                
                $stmtReduceReq->execute([$qty, $employeeId, $matId]);
                $stmtDeductStock->execute([$totalConsumed, $totalConsumed, $matId]);
                $count++;
            }
            if ($count === 0) throw new Exception("No valid materials entered.");
            $pdo->commit();
            
            // LOW STOCK ALERT AUTOMATION: Check stock levels after usage
            try {
                require_once __DIR__ . '/../../../app/includes/notification_helper.php';
                
                foreach ($matIds as $i => $matId) {
                    $matId = intval($matId);
                    $qty = floatval($matQtys[$i] ?? 0);
                    $waste = floatval($matWastes[$i] ?? 0);
                    if (!$matId || $qty <= 0) continue;
                    
                    // Check current stock level
                    $checkStmt = $pdo->prepare("
                        SELECT m.name, m.current_stock, m.minimum_stock,
                               (m.current_stock - COALESCE(m.reserved_stock, 0)) as available
                        FROM furn_materials m WHERE m.id = ?
                    ");
                    $checkStmt->execute([$matId]);
                    $mat = $checkStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($mat && floatval($mat['available']) < floatval($mat['minimum_stock'])) {
                        // Create low stock alert
                        $alertStmt = $pdo->prepare("
                            INSERT INTO furn_low_stock_alerts (material_id, current_stock, minimum_stock, alert_level, created_at)
                            VALUES (?, ?, ?, 'low', NOW())
                        ");
                        $alertStmt->execute([$matId, $mat['available'], $mat['minimum_stock']]);
                        
                        // Notify managers
                        notifyRole($pdo, 'manager', 'inventory', 'Low Stock Alert: ' . $mat['name'],
                            'Material "' . $mat['name'] . '" is running low after usage. Available: ' . number_format(floatval($mat['available']), 2),
                            $matId, '/manager/inventory', 'high');
                    }
                }
            } catch (Exception $e) {
                error_log("Low stock alert check error: " . $e->getMessage());
            }
            
            $success = "$count material(s) usage reported and stock updated successfully!";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Auto-create tables if missing
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS furn_material_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        material_id INT NOT NULL,
        quantity_requested DECIMAL(10,2) NOT NULL,
        purpose TEXT,
        order_id INT DEFAULT NULL,
        status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        approved_by INT DEFAULT NULL,
        approved_at TIMESTAMP NULL DEFAULT NULL,
        rejection_reason TEXT DEFAULT NULL,
        manager_note TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(employee_id), INDEX(material_id), INDEX(status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Add rejection_reason column if missing (for older installs)
    try { $pdo->exec("ALTER TABLE furn_material_requests ADD COLUMN IF NOT EXISTS rejection_reason TEXT DEFAULT NULL"); } catch(PDOException $e2){}
    try { $pdo->exec("ALTER TABLE furn_material_requests ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP NULL DEFAULT NULL"); } catch(PDOException $e2){}
    try { $pdo->exec("ALTER TABLE furn_material_requests ADD COLUMN IF NOT EXISTS approved_by INT DEFAULT NULL"); } catch(PDOException $e2){}
} catch (PDOException $e) { error_log("Create requests table: " . $e->getMessage()); }

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS furn_material_usage (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        task_id INT DEFAULT NULL,
        material_id INT NOT NULL,
        quantity_used DECIMAL(10,2) NOT NULL,
        waste_amount DECIMAL(10,2) DEFAULT 0,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(employee_id), INDEX(material_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) { error_log("Create usage table: " . $e->getMessage()); }

// Fetch this employee's material requests
$requests = [];
try {
    $stmt = $pdo->prepare("
        SELECT mr.*,
               m.name as material_name, m.unit,
               o.order_number,
               CONCAT(a.first_name,' ',a.last_name) as approved_by_name
        FROM furn_material_requests mr
        LEFT JOIN furn_materials m ON mr.material_id = m.id
        LEFT JOIN furn_orders o ON mr.order_id = o.id
        LEFT JOIN furn_users a ON mr.approved_by = a.id
        WHERE mr.employee_id = ?
        ORDER BY mr.created_at DESC
    ");
    $stmt->execute([$employeeId]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("Requests error: " . $e->getMessage()); }

// Fetch usage history
$usageHistory = [];
try {
    $stmt = $pdo->prepare("
        SELECT mu.*, m.material_name, m.unit, t.id as task_number, o.order_number
        FROM furn_material_usage mu
        LEFT JOIN furn_materials m ON mu.material_id = m.id
        LEFT JOIN furn_production_tasks t ON mu.task_id = t.id
        LEFT JOIN furn_orders o ON t.order_id = o.id
        WHERE mu.employee_id = ?
        ORDER BY mu.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$employeeId]);
    $usageHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("Usage history error: " . $e->getMessage()); }

// Materials for dropdowns
$materials = [];
try {
    $stmt = $pdo->query("SELECT id, name as material_name, unit,
        current_stock, COALESCE(reserved_stock,0) as reserved_stock,
        (current_stock - COALESCE(reserved_stock,0)) as quantity
        FROM furn_materials WHERE is_active = 1 ORDER BY name ASC");
    $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("Materials error: " . $e->getMessage()); }
// Tasks assigned to this employee (pending or in_progress)
$tasks = [];
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT t.id, o.order_number, o.id as order_id,
               COALESCE(o.furniture_name, o.furniture_type, 'Custom Order') as product_name,
               MIN(p.id) as product_id
        FROM furn_production_tasks t
        LEFT JOIN furn_orders o ON t.order_id = o.id
        LEFT JOIN furn_products p ON (
            LOWER(p.name) = LOWER(o.furniture_name)
            OR LOWER(p.name) = LOWER(o.furniture_type)
        )
        WHERE t.employee_id = ? AND t.status IN ('pending','in_progress','assigned')
        GROUP BY t.id, o.order_number, o.id, o.furniture_name, o.furniture_type
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([$employeeId]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("Tasks error: " . $e->getMessage()); }

// Product materials map: product_id => [{material_id, material_name, unit, quantity_required}]
$productMaterialsMap = [];
try {
    if (!empty($tasks)) {
        $productIds = array_filter(array_unique(array_column($tasks, 'product_id')));
        if (!empty($productIds)) {
            $ph = implode(',', array_fill(0, count($productIds), '?'));
            $stmt = $pdo->prepare("
                SELECT pm.product_id, pm.material_id, pm.quantity_required,
                       m.name as material_name, m.unit,
                       (m.current_stock - COALESCE(m.reserved_stock,0)) as available
                FROM furn_product_materials pm
                JOIN furn_materials m ON pm.material_id = m.id
                WHERE pm.product_id IN ($ph)
            ");
            $stmt->execute(array_values($productIds));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $productMaterialsMap[$row['product_id']][] = $row;
            }
        }
    }
} catch (PDOException $e) { error_log("Product materials error: " . $e->getMessage()); }

// Approved materials for this employee (for usage report dropdown)
$approvedMaterials = [];
try {
    $stmt = $pdo->prepare("
        SELECT mr.material_id, m.name as material_name, m.unit,
               SUM(mr.quantity_requested) as approved_qty
        FROM furn_material_requests mr
        JOIN furn_materials m ON mr.material_id = m.id
        WHERE mr.employee_id = ? AND mr.status = 'approved'
        GROUP BY mr.material_id, m.name, m.unit
        HAVING approved_qty > 0
    ");
    $stmt->execute([$employeeId]);
    $approvedMaterials = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("Approved materials error: " . $e->getMessage()); }

// Task material status: for each task, check if materials are requested/approved
$taskMaterialStatus = [];
try {
    foreach ($tasks as $task) {
        $tid = $task['id'];
        $oid = $task['order_id'];
        // Check if any request exists for this order
        $s = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM furn_material_requests WHERE employee_id=? AND order_id=? GROUP BY status");
        $s->execute([$employeeId, $oid]);
        $rows = $s->fetchAll(PDO::FETCH_ASSOC);
        $statusMap = array_column($rows, 'cnt', 'status');
        if (empty($rows)) {
            $taskMaterialStatus[$tid] = 'not_requested';
        } elseif (!empty($statusMap['approved'])) {
            $taskMaterialStatus[$tid] = 'approved';
        } elseif (!empty($statusMap['pending'])) {
            $taskMaterialStatus[$tid] = 'pending';
        } else {
            $taskMaterialStatus[$tid] = 'rejected';
        }
    }
} catch (PDOException $e) { error_log("Task status error: " . $e->getMessage()); }

$pendingCount  = count(array_filter($requests, fn($r) => $r['status'] === 'pending'));
$approvedCount = count(array_filter($requests, fn($r) => $r['status'] === 'approved'));
// Orders for request form (from tasks)
$orders = array_map(fn($t) => ['id' => $t['order_id'], 'order_number' => $t['order_number'], 'product_name' => $t['product_name'], 'task_id' => $t['id'], 'product_id' => $t['product_id']], $tasks);
$pageTitle = 'Materials';
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
</head>
<body>
    <button class="mobile-menu-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>
    <div class="sidebar-overlay"></div>
    <?php include_once __DIR__ . '/../../includes/employee_sidebar.php'; ?>

    <!-- Top Header -->
    <?php 
    $pageTitle = 'Materials';
    include_once __DIR__ . '/../../includes/employee_header.php'; 
    ?>
        <div class="header-left"><div class="system-status"><i class="fas fa-circle"></i> Materials Management</div></div>
        <div class="header-right">
            <div class="admin-profile">
                <div class="admin-avatar"><?php echo strtoupper(substr($employeeName, 0, 1)); ?></div>
                <div>
                    <div style="font-weight:600;font-size:14px;"><?php echo htmlspecialchars($employeeName); ?></div>
                    <div class="admin-role-badge" style="background:#27AE60;">EMPLOYEE</div>
                </div>
            </div>
        </div>
    </div>

    <div class="main-content">
        <?php if (isset($success)): ?>
            <div style="background:#d4edda;color:#155724;padding:15px;border-radius:8px;margin-bottom:20px;border:1px solid #c3e6cb;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div style="background:#f8d7da;color:#721c24;padding:15px;border-radius:8px;margin-bottom:20px;border:1px solid #f5c6cb;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card" style="border-left:4px solid #3498DB;">
                <div style="display:flex;justify-content:space-between;align-items:start;">
                    <div><div class="stat-value"><?php echo count($requests); ?></div><div class="stat-label">Total Requests</div></div>
                    <div style="font-size:32px;color:#3498DB;"><i class="fas fa-clipboard-list"></i></div>
                </div>
            </div>
            <div class="stat-card" style="border-left:4px solid #F39C12;">
                <div style="display:flex;justify-content:space-between;align-items:start;">
                    <div><div class="stat-value"><?php echo $pendingCount; ?></div><div class="stat-label">Pending Approval</div></div>
                    <div style="font-size:32px;color:#F39C12;"><i class="fas fa-hourglass-half"></i></div>
                </div>
            </div>
            <div class="stat-card" style="border-left:4px solid #27AE60;">
                <div style="display:flex;justify-content:space-between;align-items:start;">
                    <div><div class="stat-value"><?php echo $approvedCount; ?></div><div class="stat-label">Approved</div></div>
                    <div style="font-size:32px;color:#27AE60;"><i class="fas fa-check-circle"></i></div>
                </div>
            </div>
            <div class="stat-card" style="border-left:4px solid #9B59B6;">
                <div style="display:flex;justify-content:space-between;align-items:start;">
                    <div><div class="stat-value"><?php echo count($usageHistory); ?></div><div class="stat-label">Usage Reports</div></div>
                    <div style="font-size:32px;color:#9B59B6;"><i class="fas fa-chart-bar"></i></div>
                </div>
            </div>
        </div>

        <!-- Task Material Status Notifications -->
        <?php
        $warningTasks = [];
        $seen = [];
        foreach ($tasks as $task) {
            $tid = $task['id'];
            if (in_array($tid, $seen)) continue;
            $seen[] = $tid;
            $tStatus = $taskMaterialStatus[$tid] ?? 'not_requested';
            if ($tStatus !== 'approved') $warningTasks[] = ['task' => $task, 'status' => $tStatus];
        }
        if (!empty($warningTasks)):
        ?>
        <div style="position:relative;display:inline-block;margin-bottom:20px;" id="matNotifWrap">
            <button onclick="toggleMatNotif()" style="background:#F39C12;color:white;border:none;border-radius:8px;padding:10px 18px;font-size:14px;cursor:pointer;display:flex;align-items:center;gap:8px;font-family:inherit;">
                <i class="fas fa-bell"></i>
                Material Alerts
                <span style="background:white;color:#F39C12;border-radius:50%;width:20px;height:20px;font-size:12px;font-weight:700;display:flex;align-items:center;justify-content:center;">
                    <?php echo count($warningTasks); ?>
                </span>
            </button>
            <div id="matNotifDropdown" style="display:none;position:absolute;top:calc(100% + 6px);left:0;background:white;border:1px solid #FFE69C;border-radius:10px;box-shadow:0 6px 20px rgba(0,0,0,0.12);min-width:380px;max-width:480px;z-index:999;">
                <div style="background:#FFF3CD;padding:10px 16px;border-radius:10px 10px 0 0;font-weight:600;font-size:13px;color:#856404;border-bottom:1px solid #FFE69C;">
                    <i class="fas fa-bell"></i> Tasks needing material requests
                </div>
                <?php foreach ($warningTasks as $w):
                    $tStatus = $w['status'];
                    $task    = $w['task'];
                    $color   = $tStatus === 'pending' ? '#F39C12' : '#E74C3C';
                    $icon    = $tStatus === 'pending' ? 'fa-hourglass-half' : ($tStatus === 'rejected' ? 'fa-times-circle' : 'fa-exclamation-triangle');
                    $msg     = ['not_requested'=>'Not yet requested','pending'=>'Pending approval','rejected'=>'Request rejected'][$tStatus] ?? '';
                ?>
                <div style="padding:10px 16px;border-bottom:1px solid #f5f5f5;font-size:13px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <div>
                            <strong>Task #<?php echo str_pad($task['id'],4,'0',STR_PAD_LEFT); ?></strong>
                            — <?php echo htmlspecialchars($task['order_number'] ?? 'N/A'); ?><br>
                            <span style="color:#666;"><?php echo htmlspecialchars($task['product_name']); ?></span>
                        </div>
                        <span style="color:<?php echo $color; ?>;font-size:12px;font-weight:600;white-space:nowrap;margin-left:10px;">
                            <i class="fas <?php echo $icon; ?>"></i> <?php echo $msg; ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
                <div style="padding:10px 16px;text-align:center;">
                    <button onclick="toggleRequestForm();toggleMatNotif();" style="background:#3498DB;color:white;border:none;border-radius:6px;padding:7px 16px;font-size:13px;cursor:pointer;font-family:inherit;">
                        <i class="fas fa-plus"></i> Request Materials Now
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Request Material -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-plus-circle"></i> Request Materials</h2>
                <button class="btn-action btn-primary-custom" onclick="toggleRequestForm()"><i class="fas fa-plus"></i> New Request</button>
            </div>
            <div id="requestForm" style="display:none;margin-top:20px;">
                <form method="POST" id="materialRequestForm">
                    <input type="hidden" name="action" value="request_material">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? $_SESSION['csrf_token'] ?? ''); ?>">

                    <!-- Task selector -->
                    <div class="form-group" style="margin-bottom:18px;">
                        <label style="font-weight:600;">Related Task / Order <span style="color:#E74C3C;">*</span></label>
                        <select name="order_id" id="req_task_select" class="form-control" onchange="prefillMaterials(this)">
                            <option value="">-- Select Task --</option>
                            <?php foreach ($tasks as $t): ?>
                                <option value="<?php echo $t['order_id']; ?>"
                                        data-product-id="<?php echo $t['product_id']; ?>">
                                    Task #<?php echo str_pad($t['id'],4,'0',STR_PAD_LEFT); ?>
                                    — <?php echo htmlspecialchars($t['order_number'] ?? 'N/A'); ?>
                                    (<?php echo htmlspecialchars($t['product_name']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Product material hint -->
                    <div id="productMaterialHint" style="display:none;background:#EBF5FB;border:1px solid #AED6F1;border-radius:8px;padding:12px 16px;margin-bottom:14px;">
                        <strong style="color:#1A5276;font-size:13px;"><i class="fas fa-info-circle"></i> Required materials for this product:</strong>
                        <ul id="productMaterialList" style="margin:8px 0 0 16px;font-size:13px;color:#1A5276;"></ul>
                    </div>

                    <!-- Dynamic materials rows -->
                    <label style="font-weight:600;display:block;margin-bottom:8px;">Materials to Request <span style="color:#E74C3C;">*</span></label>
                    <div style="overflow-x:auto;">
                        <table style="width:100%;border-collapse:collapse;margin-bottom:10px;" id="reqMatTable">
                            <thead>
                                <tr style="background:#f8f9fa;font-size:13px;">
                                    <th style="padding:8px 10px;text-align:left;border:1px solid #dee2e6;">#</th>
                                    <th style="padding:8px 10px;text-align:left;border:1px solid #dee2e6;">Material</th>
                                    <th style="padding:8px 10px;text-align:left;border:1px solid #dee2e6;">Quantity</th>
                                    <th style="padding:8px 10px;border:1px solid #dee2e6;"></th>
                                </tr>
                            </thead>
                            <tbody id="reqMatRows"></tbody>
                        </table>
                    </div>
                    <button type="button" onclick="addReqRow()" style="background:#3498db;color:white;border:none;padding:8px 16px;border-radius:6px;font-size:13px;cursor:pointer;margin-bottom:16px;">
                        <i class="fas fa-plus"></i> Add Material
                    </button>

                    <div style="background:#FFF3CD;border:1px solid #FFE69C;border-radius:8px;padding:12px;margin-top:10px;">
                        <p style="margin:0;color:#856404;font-size:13px;"><i class="fas fa-info-circle"></i> All materials in this form will be submitted as one batch request for manager approval.</p>
                    </div>
                    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
                        <button type="button" class="btn-action btn-secondary-custom" onclick="toggleRequestForm()"><i class="fas fa-times"></i> Cancel</button>
                        <button type="submit" class="btn-action btn-success-custom"><i class="fas fa-paper-plane"></i> Submit Request</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- My Requests List -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-clipboard-list"></i> My Material Requests</h2>
            </div>
            <?php if (empty($requests)): ?>
                <p style="text-align:center;color:#7f8c8d;padding:40px;">No material requests yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table mobile-cards">
                        <thead>
                            <tr><th>ID</th><th>Material</th><th>Qty</th><th>Order</th><th>Status</th><th>Date</th><th>Approved By</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $req): ?>
                            <tr>
                                <td>#<?php echo str_pad($req['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                <td><strong><?php echo htmlspecialchars($req['material_name']); ?></strong></td>
                                <td><?php echo $req['quantity_requested']; ?> <?php echo $req['unit']; ?></td>
                                <td><?php echo $req['order_number'] ? htmlspecialchars($req['order_number']) : 'N/A'; ?></td>
                                <td>
                                    <?php $sc = ['pending'=>'warning','approved'=>'success','rejected'=>'danger','released'=>'info']; ?>
                                    <span class="badge badge-<?php echo $sc[$req['status']] ?? 'secondary'; ?>"><?php echo ucfirst($req['status']); ?></span>
                                    <?php if ($req['status'] === 'rejected' && $req['rejection_reason']): ?>
                                        <br><small style="color:#E74C3C;"><?php echo htmlspecialchars($req['rejection_reason']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($req['created_at'])); ?></td>
                                <td>
                                    <?php if (!empty($req['approved_by_name']) && trim($req['approved_by_name']) !== ''): ?>
                                        <?php echo htmlspecialchars($req['approved_by_name']); ?>
                                    <?php else: ?>
                                        <span style="color:#95A5A6;">Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Report Usage -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-chart-line"></i> Report Material Usage</h2>
                <button class="btn-action btn-primary-custom" onclick="toggleUsageForm()"><i class="fas fa-plus"></i> New Report</button>
            </div>
            <div id="usageForm" style="display:none;margin-top:20px;">
                <form method="POST" id="usageReportForm">
                    <input type="hidden" name="action" value="report_usage">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? $_SESSION['csrf_token'] ?? ''); ?>">

                    <!-- Task selector -->
                    <div class="form-group" style="margin-bottom:18px;">
                        <label style="font-weight:600;">Production Task <span style="color:#E74C3C;">*</span></label>
                        <select name="task_id" class="form-control" required>
                            <option value="">-- Select Task --</option>
                            <?php foreach ($tasks as $t): ?>
                                <option value="<?php echo $t['id']; ?>">
                                    Task #<?php echo str_pad($t['id'],4,'0',STR_PAD_LEFT); ?>
                                    — <?php echo htmlspecialchars($t['order_number'] ?? 'N/A'); ?>
                                    — <?php echo htmlspecialchars($t['product_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Dynamic materials table -->
                    <label style="font-weight:600;display:block;margin-bottom:8px;">Materials Used <span style="color:#E74C3C;">*</span></label>
                    <div style="overflow-x:auto;">
                        <table style="width:100%;border-collapse:collapse;margin-bottom:10px;" id="matTable">
                            <thead>
                                <tr style="background:#f8f9fa;font-size:13px;">
                                    <th style="padding:8px 10px;text-align:left;border:1px solid #dee2e6;">#</th>
                                    <th style="padding:8px 10px;text-align:left;border:1px solid #dee2e6;">Material</th>
                                    <th style="padding:8px 10px;text-align:left;border:1px solid #dee2e6;">Qty Used</th>
                                    <th style="padding:8px 10px;text-align:left;border:1px solid #dee2e6;">Waste</th>
                                    <th style="padding:8px 10px;border:1px solid #dee2e6;"></th>
                                </tr>
                            </thead>
                            <tbody id="matRows">
                                <!-- rows added by JS -->
                            </tbody>
                        </table>
                    </div>
                    <button type="button" onclick="addMatRow()" style="background:#3498db;color:white;border:none;padding:8px 16px;border-radius:6px;font-size:13px;cursor:pointer;margin-bottom:16px;">
                        <i class="fas fa-plus"></i> Add Material
                    </button>

                    <div class="form-group">
                        <label>General Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Any additional notes about this usage report..."></textarea>
                    </div>

                    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;">
                        <button type="button" class="btn-action btn-secondary-custom" onclick="toggleUsageForm()"><i class="fas fa-times"></i> Cancel</button>
                        <button type="submit" class="btn-action btn-success-custom"><i class="fas fa-check"></i> Submit Report</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Usage History -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-history"></i> Material Usage History</h2>
            </div>
            <?php if (empty($usageHistory)): ?>
                <p style="text-align:center;color:#7f8c8d;padding:40px;">No usage reports yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table mobile-cards">
                        <thead>
                            <tr><th>ID</th><th>Task</th><th>Order</th><th>Material</th><th>Qty Used</th><th>Waste</th><th>Date</th><th>Notes</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usageHistory as $u): ?>
                            <tr>
                                <td>#<?php echo str_pad($u['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                <td>#<?php echo str_pad($u['task_number'] ?? 0, 4, '0', STR_PAD_LEFT); ?></td>
                                <td><?php echo htmlspecialchars($u['order_number'] ?? 'N/A'); ?></td>
                                <td><strong><?php echo htmlspecialchars($u['material_name']); ?></strong></td>
                                <td><?php echo $u['quantity_used']; ?> <?php echo $u['unit']; ?></td>
                                <td>
                                    <?php if ($u['waste_amount'] > 0): ?>
                                        <span style="color:#E74C3C;"><?php echo $u['waste_amount']; ?> <?php echo $u['unit']; ?></span>
                                    <?php else: ?>
                                        <span style="color:#27AE60;">0</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($u['notes'] ?: '—'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="background:#EBF5FB;border:1px solid #AED6F1;border-radius:8px;padding:12px 16px;margin-top:12px;">
                    <i class="fas fa-info-circle" style="color:#3498DB;"></i>
                    <span style="color:#1A5276;font-size:13px;"> Manager feedback on your usage reports is visible in your <a href="<?php echo BASE_URL; ?>/public/employee/reports" style="color:#2980B9;font-weight:600;">Reports page</a>.</span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function toggleMatNotif() {
        const d = document.getElementById('matNotifDropdown');
        if (d) d.style.display = d.style.display === 'none' ? 'block' : 'none';
    }
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        const wrap = document.getElementById('matNotifWrap');
        if (wrap && !wrap.contains(e.target)) {
            const d = document.getElementById('matNotifDropdown');
            if (d) d.style.display = 'none';
        }
    });

    function toggleRequestForm() {
        const f = document.getElementById('requestForm');
        const isHidden = f.style.display === 'none' || f.style.display === '';
        f.style.display = isHidden ? 'block' : 'none';
        if (isHidden && document.getElementById('reqMatRows').children.length === 0) {
            addReqRow();
        }
    }
    function toggleUsageForm() {
        const f = document.getElementById('usageForm');
        const isHidden = f.style.display === 'none' || f.style.display === '';
        f.style.display = isHidden ? 'block' : 'none';
        if (isHidden && document.getElementById('matRows').children.length === 0) {
            addMatRow(); // start with one empty row
        }
    }

    // Materials data for dropdowns — only approved materials for this employee
    const MATERIALS = <?php echo json_encode(array_map(fn($m) => [
        'id'          => $m['material_id'],
        'name'        => $m['material_name'],
        'unit'        => $m['unit'],
        'approved_qty'=> floatval($m['approved_qty']),
    ], $approvedMaterials)); ?>;

    // Product materials map for request form pre-fill
    const PRODUCT_MATERIALS = <?php echo json_encode($productMaterialsMap); ?>;

    let rowCount = 0;

    function addMatRow() {
        rowCount++;
        const tbody = document.getElementById('matRows');
        const tr = document.createElement('tr');
        tr.id = 'matRow_' + rowCount;

        if (MATERIALS.length === 0) {
            alert('No approved materials found. You must have an approved material request before reporting usage.');
            return;
        }

        let opts = '<option value="">-- Select --</option>';
        MATERIALS.forEach(m => {
            const label = `${m.name} (${m.unit}) — Approved: ${m.approved_qty.toFixed(2)}`;
            opts += `<option value="${m.id}" data-unit="${m.unit}" data-approved="${m.approved_qty}">${label}</option>`;
        });

        tr.innerHTML = `
            <td style="padding:8px 10px;border:1px solid #dee2e6;color:#aaa;font-size:13px;">${rowCount}</td>
            <td style="padding:6px 8px;border:1px solid #dee2e6;">
                <select name="mat_id[]" class="form-control mat-select" style="min-width:200px;" required onchange="updateUnit(this, ${rowCount})">
                    ${opts}
                </select>
            </td>
            <td style="padding:6px 8px;border:1px solid #dee2e6;">
                <div style="display:flex;align-items:center;gap:6px;">
                    <input type="number" name="mat_qty[]" id="qty_${rowCount}" class="form-control" style="width:100px;" min="0.01" step="0.01" placeholder="0.00" required>
                    <span id="unit_${rowCount}" style="color:#7f8c8d;font-size:12px;white-space:nowrap;"></span>
                </div>
            </td>
            <td style="padding:6px 8px;border:1px solid #dee2e6;">
                <div style="display:flex;align-items:center;gap:6px;">
                    <input type="number" name="mat_waste[]" class="form-control" style="width:100px;" min="0" step="0.01" placeholder="0.00" value="0">
                    <span style="color:#e74c3c;font-size:12px;">waste</span>
                </div>
            </td>
            <td style="padding:6px 8px;border:1px solid #dee2e6;text-align:center;">
                <button type="button" onclick="removeMatRow(${rowCount})" style="background:#e74c3c;color:white;border:none;border-radius:4px;padding:4px 10px;cursor:pointer;font-size:13px;">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    }

    function updateUnit(sel, rowId) {
        const opt = sel.options[sel.selectedIndex];
        document.getElementById('unit_' + rowId).textContent = opt.dataset.unit || '';
        // Set max on qty input
        const qtyInput = document.getElementById('qty_' + rowId);
        if (qtyInput && opt.dataset.approved) {
            qtyInput.max = opt.dataset.approved;
            qtyInput.title = `Max approved: ${opt.dataset.approved}`;
        }
    }

    function removeMatRow(rowId) {
        const row = document.getElementById('matRow_' + rowId);
        if (row) row.remove();
    }

    // Request form: pre-fill materials from product spec
    function prefillMaterials(sel) {
        const opt = sel.options[sel.selectedIndex];
        const productId = opt ? opt.dataset.productId : null;
        const hint = document.getElementById('productMaterialHint');
        const list = document.getElementById('productMaterialList');
        list.innerHTML = '';
        document.getElementById('reqMatRows').innerHTML = '';
        reqRowCount = 0;

        if (productId && PRODUCT_MATERIALS[productId]) {
            const mats = PRODUCT_MATERIALS[productId];
            mats.forEach(m => {
                const avail = parseFloat(m.available);
                const color = avail < m.quantity_required ? '#E74C3C' : '#27AE60';
                const li = document.createElement('li');
                li.innerHTML = `<strong>${m.material_name}</strong>: ${m.quantity_required} ${m.unit}
                    <span style="color:${color};">(Available: ${avail.toFixed(2)}${avail < m.quantity_required ? ' ⚠ Insufficient' : ''})</span>`;
                list.appendChild(li);
                // Auto-add a row pre-filled for this material
                addReqRow(m.material_id, m.quantity_required);
            });
            hint.style.display = 'block';
        } else {
            hint.style.display = 'none';
            addReqRow(); // start with one empty row
        }
    }

    let reqRowCount = 0;
    const ALL_MATERIALS = <?php echo json_encode(array_map(fn($m) => [
        'id'    => $m['id'],
        'name'  => $m['material_name'],
        'unit'  => $m['unit'],
        'avail' => floatval($m['quantity']),
    ], $materials)); ?>;

    function addReqRow(preselect = null, preqty = null) {
        reqRowCount++;
        const tbody = document.getElementById('reqMatRows');
        const tr = document.createElement('tr');
        tr.id = 'reqRow_' + reqRowCount;

        let opts = '<option value="">-- Select Material --</option>';
        ALL_MATERIALS.forEach(m => {
            const disabled = m.avail <= 0 ? 'disabled' : '';
            const sel = (preselect && m.id == preselect) ? 'selected' : '';
            opts += `<option value="${m.id}" data-unit="${m.unit}" data-avail="${m.avail}" ${disabled} ${sel}>
                ${m.name} (${m.unit}) — Available: ${m.avail.toFixed(2)}${m.avail <= 0 ? ' [OUT OF STOCK]' : ''}
            </option>`;
        });

        const unitLabel = preselect ? (ALL_MATERIALS.find(m => m.id == preselect)?.unit || '') : '';
        const preAvail  = preselect ? (ALL_MATERIALS.find(m => m.id == preselect)?.avail ?? '') : '';

        tr.innerHTML = `
            <td style="padding:8px 10px;border:1px solid #dee2e6;color:#aaa;font-size:13px;">${reqRowCount}</td>
            <td style="padding:6px 8px;border:1px solid #dee2e6;">
                <select name="req_mat_id[]" class="form-control" style="min-width:220px;" required onchange="updateReqRowUnit(this, ${reqRowCount})">
                    ${opts}
                </select>
            </td>
            <td style="padding:6px 8px;border:1px solid #dee2e6;">
                <div style="display:flex;align-items:center;gap:6px;">
                    <input type="number" name="req_mat_qty[]" id="reqQty_${reqRowCount}" class="form-control"
                           style="width:110px;" min="0.01" step="0.01" placeholder="0.00"
                           ${preAvail !== '' ? `max="${preAvail}" title="Max available: ${preAvail}"` : ''}
                           value="${preqty !== null ? preqty : ''}" required>
                    <span id="reqUnit_${reqRowCount}" style="color:#7f8c8d;font-size:12px;white-space:nowrap;">${unitLabel}</span>
                    <span id="reqAvail_${reqRowCount}" style="color:#27AE60;font-size:11px;white-space:nowrap;">${preAvail !== '' ? 'max: '+preAvail : ''}</span>
                </div>
            </td>
            <td style="padding:6px 8px;border:1px solid #dee2e6;text-align:center;">
                <button type="button" onclick="removeReqRow(${reqRowCount})"
                        style="background:#e74c3c;color:white;border:none;border-radius:4px;padding:4px 10px;cursor:pointer;font-size:13px;">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    }

    function updateReqRowUnit(sel, rowId) {
        const opt = sel.options[sel.selectedIndex];
        const unit  = opt.dataset.unit  || '';
        const avail = parseFloat(opt.dataset.avail || 0);
        const span  = document.getElementById('reqUnit_'  + rowId);
        const availSpan = document.getElementById('reqAvail_' + rowId);
        const qtyInput  = document.getElementById('reqQty_'  + rowId);
        if (span)  span.textContent  = unit;
        if (availSpan) availSpan.textContent = avail > 0 ? 'max: ' + avail : '';
        if (qtyInput && avail > 0) {
            qtyInput.max   = avail;
            qtyInput.title = 'Max available: ' + avail;
        } else if (qtyInput) {
            qtyInput.removeAttribute('max');
        }
    }

    function removeReqRow(rowId) {
        const row = document.getElementById('reqRow_' + rowId);
        if (row) row.remove();
    }

    // Validate at least one row on submit
    document.getElementById('materialRequestForm').addEventListener('submit', function(e) {
        const rows = document.querySelectorAll('#reqMatRows tr');
        if (rows.length === 0) {
            e.preventDefault();
            alert('Please add at least one material to request.');
        }
    });

    document.getElementById('usageReportForm').addEventListener('submit', function(e) {
        const rows = document.querySelectorAll('#matRows tr');
        if (rows.length === 0) {
            e.preventDefault();
            alert('Please add at least one material.');
        }
    });
    </script>
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
