<?php
// Start output buffering to allow redirects after HTML output
ob_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}
require_once __DIR__ . '/../../../config/db_config.php';
$csrf_token = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;
$managerName = $_SESSION['user_name'] ?? 'Manager User';
$managerId = $_SESSION['user_id'];

function verifyCsrf() {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error_message'] = 'Invalid CSRF token.';
        header('Location: ' . BASE_URL . '/public/manager/inventory');
        exit();
    }
}

// Handle Add Material
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_material'])) {
    verifyCsrf();
    $name       = trim($_POST['material_name']);
    $quantity   = floatval($_POST['quantity']);
    $unit       = trim($_POST['unit']);
    $unit_price = floatval($_POST['unit_price']);

    // Use global default threshold from settings, fallback to 20
    $threshold = 20;
    try {
        $s = $pdo->query("SELECT setting_value FROM furn_settings WHERE setting_key='low_stock_threshold' LIMIT 1");
        $sv = $s->fetchColumn();
        if ($sv !== false && floatval($sv) > 0) $threshold = floatval($sv);
    } catch (PDOException $e2) {}

    try {
        $pdo->prepare("INSERT INTO furn_materials (name, current_stock, unit, cost_per_unit, minimum_stock, created_at) VALUES (?, ?, ?, ?, ?, NOW())")
            ->execute([$name, $quantity, $unit, $unit_price, $threshold]);
        $_SESSION['success_message'] = "Material added successfully!";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error adding material: " . $e->getMessage();
    }
    header('Location: ' . BASE_URL . '/public/manager/inventory');
    exit();
}

// Handle Update Stock (Restock)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stock'])) {
    verifyCsrf();
    $material_id     = intval($_POST['material_id']);
    $quantity_change = floatval($_POST['quantity_change']);
    $action          = $_POST['action'];
    $purchase_price  = floatval($_POST['purchase_price'] ?? 0);
    $invoice_number  = trim($_POST['invoice_number'] ?? '');
    $purchase_date   = trim($_POST['purchase_date'] ?? date('Y-m-d'));
    $supplier_name   = trim($_POST['supplier_name'] ?? '');

    try {
        // Ensure purchase log table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS furn_material_purchases (
            id INT AUTO_INCREMENT PRIMARY KEY,
            material_id INT NOT NULL,
            manager_id INT NOT NULL,
            action ENUM('restock','adjustment') NOT NULL DEFAULT 'restock',
            quantity DECIMAL(10,2) NOT NULL,
            unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            total_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
            invoice_number VARCHAR(100) DEFAULT NULL,
            supplier VARCHAR(255) DEFAULT NULL,
            purchase_date DATE NOT NULL,
            notes TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(material_id), INDEX(manager_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        if ($action === 'add') {
            $pdo->prepare("UPDATE furn_materials SET current_stock = current_stock + ?,
                cost_per_unit = IF(? > 0, ?, cost_per_unit),
                supplier = IF(? != '', ?, supplier),
                updated_at = NOW() WHERE id = ?")
                ->execute([$quantity_change, $purchase_price, $purchase_price,
                           $supplier_name, $supplier_name, $material_id]);

            // Log the purchase
            $pdo->prepare("INSERT INTO furn_material_purchases
                (material_id, manager_id, action, quantity, unit_price, total_cost, invoice_number, supplier, purchase_date)
                VALUES (?, ?, 'restock', ?, ?, ?, ?, ?, ?)")
                ->execute([$material_id, $managerId, $quantity_change,
                           $purchase_price, $quantity_change * $purchase_price,
                           $invoice_number ?: null, $supplier_name ?: null, $purchase_date]);
        } else {
            $pdo->prepare("UPDATE furn_materials SET current_stock = GREATEST(0, current_stock - ?), updated_at = NOW() WHERE id = ?")
                ->execute([$quantity_change, $material_id]);
        }
        $_SESSION['success_message'] = "Stock updated successfully!";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Error updating stock: " . $e->getMessage();
    }
    header('Location: ' . BASE_URL . '/public/manager/inventory');
    exit();
}

// Handle Approve/Reject Material Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['handle_request'])) {
    verifyCsrf();
    $request_id = intval($_POST['request_id']);
    $decision   = $_POST['decision']; // 'approved' or 'rejected'
    $reason     = trim($_POST['rejection_reason'] ?? '');

    try {
        // Fetch request details
        $stmt = $pdo->prepare("SELECT * FROM furn_material_requests WHERE id = ? AND status = 'pending'");
        $stmt->execute([$request_id]);
        $req = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$req) {
            $_SESSION['error_message'] = "Request not found or already processed.";
            header('Location: ' . BASE_URL . '/public/manager/inventory');
            exit();
        }

        if ($decision === 'approved') {
            // Check available stock (current_stock minus already reserved)
            $stockStmt = $pdo->prepare("SELECT current_stock, COALESCE(reserved_stock,0) as reserved FROM furn_materials WHERE id = ?");
            $stockStmt->execute([$req['material_id']]);
            $mat = $stockStmt->fetch(PDO::FETCH_ASSOC);
            $available = floatval($mat['current_stock']) - floatval($mat['reserved']);

            if ($available < $req['quantity_requested']) {
                $_SESSION['error_message'] = "Insufficient available stock (available: {$available}, reserved: {$mat['reserved']}).";
                header('Location: ' . BASE_URL . '/public/manager/inventory');
                exit();
            }

            $pdo->beginTransaction();
            // Reserve stock (don't deduct yet — deduct when employee reports actual usage)
            try {
                $pdo->exec("ALTER TABLE furn_materials ADD COLUMN IF NOT EXISTS reserved_stock DECIMAL(10,2) NOT NULL DEFAULT 0");
            } catch (PDOException $e2) {}
            $pdo->prepare("UPDATE furn_materials SET reserved_stock = COALESCE(reserved_stock,0) + ?, updated_at = NOW() WHERE id = ?")
                ->execute([$req['quantity_requested'], $req['material_id']]);
            // Update request status
            $pdo->prepare("UPDATE furn_material_requests SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?")
                ->execute([$managerId, $request_id]);
            $pdo->commit();
            // Notify employee
            require_once __DIR__ . '/../../../app/includes/notification_helper.php';
            $matName = $pdo->prepare("SELECT name FROM furn_materials WHERE id=?");
            $matName->execute([$req['material_id']]); $mn = $matName->fetchColumn() ?: 'material';
            insertNotification($pdo, $req['employee_id'], 'material', 'Material Request Approved',
                'Your request for ' . $req['quantity_requested'] . ' ' . $mn . ' has been approved.',
                $request_id, '/employee/materials', 'normal');
            $_SESSION['success_message'] = "Request approved. Stock reserved for employee.";
        } else {
            $pdo->prepare("UPDATE furn_material_requests SET status = 'rejected', approved_by = ?, approved_at = NOW(), rejection_reason = ? WHERE id = ?")
                ->execute([$managerId, $reason, $request_id]);
            // Notify employee
            require_once __DIR__ . '/../../../app/includes/notification_helper.php';
            insertNotification($pdo, $req['employee_id'], 'material', 'Material Request Rejected',
                'Your material request was rejected' . ($reason ? ': ' . $reason : '.'),
                $request_id, '/employee/materials', 'normal');
            $_SESSION['success_message'] = "Request rejected.";
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error_message'] = "Error processing request: " . $e->getMessage();
    }
    header('Location: ' . BASE_URL . '/public/manager/inventory');
    exit();
}

// Fetch materials
$materials = [];
try {
    // Ensure reserved_stock column exists
    try { $pdo->exec("ALTER TABLE furn_materials ADD COLUMN IF NOT EXISTS reserved_stock DECIMAL(10,2) NOT NULL DEFAULT 0"); } catch (PDOException $e2) {}
    $stmt = $pdo->query("SELECT *, name as material_name, current_stock as quantity,
        COALESCE(reserved_stock,0) as reserved,
        (current_stock - COALESCE(reserved_stock,0)) as available_stock,
        cost_per_unit as unit_price, minimum_stock as threshold
        FROM furn_materials ORDER BY name ASC");
    $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Materials error: " . $e->getMessage());
}

// Fetch purchase history
$purchaseHistory = [];
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS furn_material_purchases (
        id INT AUTO_INCREMENT PRIMARY KEY,
        material_id INT NOT NULL,
        manager_id INT NOT NULL,
        action ENUM('restock','adjustment') NOT NULL DEFAULT 'restock',
        quantity DECIMAL(10,2) NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
        total_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
        invoice_number VARCHAR(100) DEFAULT NULL,
        supplier VARCHAR(255) DEFAULT NULL,
        purchase_date DATE NOT NULL,
        notes TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(material_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $stmt = $pdo->query("
        SELECT fp.*, m.name as material_name, m.unit,
               CONCAT(u.first_name,' ',u.last_name) as manager_name
        FROM furn_material_purchases fp
        LEFT JOIN furn_materials m ON fp.material_id = m.id
        LEFT JOIN furn_users u ON fp.manager_id = u.id
        ORDER BY fp.purchase_date DESC, fp.created_at DESC
        LIMIT 50
    ");
    $purchaseHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Purchase history error: " . $e->getMessage());
}

// Fetch pending material requests
$pendingRequests = [];
try {
    $stmt = $pdo->query("
        SELECT mr.*, 
               m.name as material_name, m.unit, m.current_stock,
               (m.current_stock - COALESCE(m.reserved_stock,0)) as available_stock,
               COALESCE(m.reserved_stock,0) as reserved_stock,
               CONCAT(u.first_name, ' ', u.last_name) as employee_name,
               o.order_number
        FROM furn_material_requests mr
        LEFT JOIN furn_materials m ON mr.material_id = m.id
        LEFT JOIN furn_users u ON mr.employee_id = u.id
        LEFT JOIN furn_orders o ON mr.order_id = o.id
        WHERE mr.status = 'pending'
        ORDER BY mr.created_at ASC
    ");
    $pendingRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Pending requests error: " . $e->getMessage());
}

// Fetch all requests (recent history)
$allRequests = [];
try {
    $stmt = $pdo->query("
        SELECT mr.*, 
               m.name as material_name, m.unit,
               CONCAT(u.first_name, ' ', u.last_name) as employee_name,
               o.order_number,
               CONCAT(a.first_name, ' ', a.last_name) as approved_by_name
        FROM furn_material_requests mr
        LEFT JOIN furn_materials m ON mr.material_id = m.id
        LEFT JOIN furn_users u ON mr.employee_id = u.id
        LEFT JOIN furn_orders o ON mr.order_id = o.id
        LEFT JOIN furn_users a ON mr.approved_by = a.id
        WHERE mr.status != 'pending'
        ORDER BY mr.updated_at DESC
        LIMIT 30
    ");
    $allRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("All requests error: " . $e->getMessage());
}

$pageTitle = 'Inventory Management';
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
    <?php include_once __DIR__ . '/../../includes/manager_sidebar.php'; ?>

    <!-- Top Header -->
    <?php 
    $pageTitle = 'Inventory';
    include_once __DIR__ . '/../../includes/manager_header.php'; 
    ?>

    <div class="main-content">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div style="background:#d4edda;color:#155724;padding:15px;border-radius:8px;margin-bottom:20px;border:1px solid #c3e6cb;">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
            <div style="background:#f8d7da;color:#721c24;padding:15px;border-radius:8px;margin-bottom:20px;border:1px solid #f5c6cb;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>

        <!-- ===== MATERIAL REQUESTS SECTION ===== -->
        <?php if (!empty($pendingRequests)): ?>
        <div class="section-card" style="border-left: 4px solid #E74C3C;">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-bell" style="color:#E74C3C;"></i> Pending Material Requests
                    <span style="background:#E74C3C;color:white;border-radius:50%;padding:2px 8px;font-size:13px;margin-left:8px;"><?php echo count($pendingRequests); ?></span>
                </h2>
            </div>
            <div class="table-responsive">
                <table class="data-table mobile-cards">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Employee</th>
                            <th>Material</th>
                            <th>Qty Requested</th>
                            <th>Available Stock</th>
                            <th>Reserved</th>
                            <th>Order</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingRequests as $req): ?>
                        <tr>
                            <td>#<?php echo str_pad($req['id'], 4, '0', STR_PAD_LEFT); ?></td>
                            <td><strong><?php echo htmlspecialchars($req['employee_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($req['material_name']); ?></td>
                            <td><strong><?php echo $req['quantity_requested']; ?> <?php echo $req['unit']; ?></strong></td>
                            <td>
                                <?php $avail = floatval($req['available_stock'] ?? ($req['current_stock'] - $req['reserved_stock'])); ?>
                                <?php if ($avail < $req['quantity_requested']): ?>
                                    <span style="color:#E74C3C;font-weight:600;"><?php echo number_format($avail,2); ?> <?php echo $req['unit']; ?> ⚠ Insufficient</span>
                                <?php else: ?>
                                    <span style="color:#27AE60;"><?php echo number_format($avail,2); ?> <?php echo $req['unit']; ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="color:#e67e22;"><?php echo number_format(floatval($req['reserved_stock'] ?? 0),2); ?> <?php echo $req['unit']; ?></td>
                            <td><?php echo $req['order_number'] ? htmlspecialchars($req['order_number']) : 'N/A'; ?></td>
                            <td><?php echo date('M d, Y', strtotime($req['created_at'])); ?></td>
                            <td>
                                <button class="btn-action btn-success-custom" style="padding:5px 10px;font-size:12px;" onclick="approveRequest(<?php echo $req['id']; ?>)">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button class="btn-action btn-danger-custom" style="padding:5px 10px;font-size:12px;margin-top:4px;" onclick="rejectRequest(<?php echo $req['id']; ?>)">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- ===== INVENTORY TABLE ===== -->
        <?php
        $invStats = [];
        try {
            $invStats['types']    = (int)$pdo->query("SELECT COUNT(*) FROM furn_materials")->fetchColumn();
            $invStats['low']      = (int)$pdo->query("SELECT COUNT(*) FROM furn_materials WHERE current_stock < minimum_stock")->fetchColumn();
            $invStats['value']    = floatval($pdo->query("SELECT COALESCE(SUM(current_stock*cost_per_unit),0) FROM furn_materials")->fetchColumn());
            $invStats['used_month']=floatval($pdo->query("SELECT COALESCE(SUM(total_cost),0) FROM furn_order_materials WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())")->fetchColumn());
        } catch(PDOException $e){}
        $invCards = [
            [$invStats['types']??0,                                    'Raw Material Types',    '#F39C12','fa-boxes',            BASE_URL.'/public/manager/inventory'],
            [$invStats['low']??0,                                      'Low Stock Alerts',      '#E74C3C','fa-exclamation-triangle', BASE_URL.'/public/manager/inventory'],
            ['ETB '.number_format($invStats['value']??0,0),            'Inventory Value',       '#27AE60','fa-warehouse',        '#'],
            ['ETB '.number_format($invStats['used_month']??0,0),       'Materials Used (Month)','#9B59B6','fa-tools',            BASE_URL.'/public/manager/material-report'],
        ];
        $invPendingReq = 0;
        try {
            $invPendingReq = (int)$pdo->query("SELECT COUNT(*) FROM furn_material_requests WHERE status='pending'")->fetchColumn();
        } catch(PDOException $e){}
        $invTotalUsed = 0;
        try {
            $invTotalUsed = floatval($pdo->query("SELECT COALESCE(SUM(total_cost),0) FROM furn_order_materials")->fetchColumn());
        } catch(PDOException $e){}
        $invCards[] = [$invPendingReq,                                 'Pending Material Requests', '#E74C3C', 'fa-bell',    BASE_URL.'/public/manager/inventory'];
        $invCards[] = ['ETB '.number_format($invTotalUsed,0),          'Total Materials Used (All Time)', '#3498DB', 'fa-history', BASE_URL.'/public/manager/material-report'];
        ?>
        <div class="stats-grid" style="margin-bottom:20px;">
            <?php foreach($invCards as [$v,$l,$c,$i,$href]): ?>
            <a href="<?php echo $href; ?>" style="text-decoration:none;">
                <div class="stat-card" style="border-left:4px solid <?php echo $c; ?>;cursor:pointer;transition:transform .15s,box-shadow .15s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,0.12)';" onmouseout="this.style.transform='';this.style.boxShadow='';">
                    <div style="display:flex;justify-content:space-between;align-items:start;">
                        <div><div class="stat-value" style="font-size:<?php echo strpos((string)$v,'ETB')!==false?'16px':'28px';?>;color:<?php echo $c; ?>"><?php echo $v; ?></div><div class="stat-label"><?php echo $l; ?></div></div>
                        <div style="font-size:28px;color:<?php echo $c; ?>;opacity:.25;"><i class="fas <?php echo $i; ?>"></i></div>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-warehouse"></i> Raw Materials Inventory</h2>
                <button class="btn-action btn-success-custom" onclick="openAddMaterialModal()"><i class="fas fa-plus"></i> Add Material</button>
            </div>
            <?php if (empty($materials)): ?>
                <p style="text-align:center;padding:60px 20px;color:#7f8c8d;">
                    <i class="fas fa-box-open" style="font-size:48px;margin-bottom:20px;display:block;"></i>
                    No materials in inventory.
                </p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table mobile-cards">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Material Name</th>
                                <th>Total Stock</th>
                                <th>Reserved</th>
                                <th>Available</th>
                                <th>Unit</th>
                                <th>Unit Price (ETB)</th>
                                <th>Supplier</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($materials as $material): ?>
                            <tr>
                                <td>#<?php echo $material['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($material['material_name']); ?></strong></td>
                                <td><strong><?php echo number_format($material['quantity'],2); ?></strong></td>
                                <td style="color:#e67e22;"><?php echo number_format($material['reserved'],2); ?></td>
                                <td>
                                    <?php $avail = floatval($material['available_stock']); $threshold = floatval($material['threshold'] ?? 20); ?>
                                    <strong style="color:<?php echo $avail <= $threshold ? '#e74c3c' : '#27ae60'; ?>">
                                        <?php echo number_format($avail,2); ?>
                                    </strong>
                                </td>
                                <td><?php echo htmlspecialchars($material['unit']); ?></td>
                                <td><?php echo number_format($material['unit_price'], 2); ?></td>
                                <td><?php echo htmlspecialchars($material['supplier'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if ($avail <= $threshold): ?>
                                        <span class="badge badge-danger">Low Stock</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">In Stock</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn-action btn-warning-custom" onclick="openRestockModal(<?php echo htmlspecialchars(json_encode($material)); ?>)">
                                        <i class="fas fa-plus"></i> Restock
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- ===== PURCHASE HISTORY ===== -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-receipt"></i> Purchase History (Restock Log)</h2>
            </div>
            <?php if (empty($purchaseHistory)): ?>
                <p style="text-align:center;padding:40px;color:#aaa;">No purchase records yet. Records are created when you restock materials.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="data-table mobile-cards">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Material</th>
                            <th>Qty Purchased</th>
                            <th>Unit Price (ETB)</th>
                            <th>Total Cost (ETB)</th>
                            <th>Invoice #</th>
                            <th>Supplier</th>
                            <th>Recorded By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $totalPurchaseCost = 0;
                        foreach ($purchaseHistory as $p):
                            $totalPurchaseCost += floatval($p['total_cost']);
                        ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($p['purchase_date'])); ?></td>
                            <td><strong><?php echo htmlspecialchars($p['material_name']); ?></strong> <small style="color:#aaa;">(<?php echo $p['unit']; ?>)</small></td>
                            <td><?php echo number_format($p['quantity'],2); ?></td>
                            <td><?php echo number_format($p['unit_price'],2); ?></td>
                            <td><strong>ETB <?php echo number_format($p['total_cost'],2); ?></strong></td>
                            <td><?php echo $p['invoice_number'] ? htmlspecialchars($p['invoice_number']) : '<span style="color:#aaa;">—</span>'; ?></td>
                            <td><?php echo $p['supplier'] ? htmlspecialchars($p['supplier']) : '<span style="color:#aaa;">—</span>'; ?></td>
                            <td><?php echo htmlspecialchars($p['manager_name'] ?? 'N/A'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr style="background:#f8f9fa;font-weight:700;">
                            <td colspan="4" style="text-align:right;">Total Purchased:</td>
                            <td>ETB <?php echo number_format($totalPurchaseCost,2); ?></td>
                            <td colspan="3"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Add Material Modal -->
    <div id="addMaterialModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;overflow-y:auto;padding:20px;">
        <div style="background:white;border-radius:14px;padding:0;max-width:520px;width:100%;margin:auto;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,.2);">
            <div style="background:linear-gradient(135deg,#27ae60,#1e8449);padding:16px 22px;display:flex;justify-content:space-between;align-items:center;">
                <h3 style="margin:0;color:white;font-size:16px;"><i class="fas fa-plus-circle me-2"></i>Add New Material</h3>
                <button onclick="closeAddMaterialModal()" style="background:none;border:none;color:white;font-size:22px;cursor:pointer;">&times;</button>
            </div>
            <div style="padding:22px;">
                <form method="POST">
                    <input type="hidden" name="add_material" value="1">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <div style="margin-bottom:14px;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:5px;">Material Name <span style="color:#e74c3c;">*</span></label>
                        <input type="text" name="material_name" required placeholder="e.g., Oak Wood, Premium Leather"
                            style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:8px;font-size:14px;box-sizing:border-box;">
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                        <div>
                            <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:5px;">Initial Quantity <span style="color:#e74c3c;">*</span></label>
                            <input type="number" name="quantity" step="0.01" min="0" required
                                style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:8px;font-size:14px;box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:5px;">Unit <span style="color:#e74c3c;">*</span></label>
                            <select name="unit" required style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:8px;font-size:14px;">
                                <option value="">Select Unit</option>
                                <option value="pcs">Pieces (pcs)</option>
                                <option value="kg">Kilograms (kg)</option>
                                <option value="m">Meters (m)</option>
                                <option value="m2">Square Meters (m²)</option>
                                <option value="m3">Cubic Meters (m³)</option>
                                <option value="L">Liters (L)</option>
                                <option value="board_feet">Board Feet</option>
                                <option value="square_feet">Square Feet</option>
                                <option value="yards">Yards</option>
                                <option value="sheets">Sheets</option>
                                <option value="box">Box</option>
                            </select>
                        </div>
                    </div>
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:5px;">Unit Price (ETB) <span style="color:#e74c3c;">*</span></label>
                        <input type="number" name="unit_price" step="0.01" min="0" required
                            style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:8px;font-size:14px;box-sizing:border-box;"
                            placeholder="Cost per unit">
                    </div>
                    <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:12px;color:#856404;">
                        <i class="fas fa-info-circle me-1"></i>
                        Low stock threshold uses the global default from Settings. Supplier can be set after adding via Edit.
                    </div>
                    <div style="display:flex;gap:10px;justify-content:flex-end;">
                        <button type="button" onclick="closeAddMaterialModal()"
                            style="padding:10px 20px;background:#95a5a6;color:white;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;">Cancel</button>
                        <button type="submit"
                            style="padding:10px 24px;background:linear-gradient(135deg,#27ae60,#1e8449);color:white;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;">
                            <i class="fas fa-plus me-1"></i>Add Material
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Restock Modal -->
    <div id="restockModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
        <div style="background:white;border-radius:14px;padding:0;max-width:560px;width:90%;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,.2);">
            <div style="background:linear-gradient(135deg,#e67e22,#d35400);padding:16px 22px;display:flex;justify-content:space-between;align-items:center;">
                <h3 style="margin:0;color:white;font-size:16px;"><i class="fas fa-box me-2"></i>Update Stock</h3>
                <button onclick="closeRestockModal()" style="background:none;border:none;color:white;font-size:22px;cursor:pointer;">&times;</button>
            </div>
            <div style="padding:22px;">
                <form method="POST">
                    <input type="hidden" name="update_stock" value="1">
                    <input type="hidden" name="material_id" id="restock_material_id">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                    <div id="restock_material_info" style="background:#f8f4e9;border-radius:8px;padding:12px;margin-bottom:16px;font-size:13px;border-left:4px solid #e67e22;"></div>

                    <div style="margin-bottom:14px;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:5px;">Action</label>
                        <select name="action" id="restock_action" onchange="togglePurchaseFields()"
                            style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:8px;font-size:14px;">
                            <option value="add">➕ Add Stock (Restock)</option>
                            <option value="subtract">➖ Remove Stock (Adjustment)</option>
                        </select>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                        <div>
                            <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:5px;">Quantity *</label>
                            <input type="number" name="quantity_change" step="0.01" min="0.01" required
                                style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:8px;font-size:14px;box-sizing:border-box;">
                        </div>
                        <div id="purchase_price_field">
                            <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:5px;">Unit Price Paid (ETB)</label>
                            <input type="number" name="purchase_price" id="purchase_price_input" step="0.01" min="0"
                                style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:8px;font-size:14px;box-sizing:border-box;"
                                placeholder="Price per unit" oninput="calcTotal()">
                        </div>
                    </div>

                    <div id="purchase_total_display" style="background:#e8f5e9;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:13px;display:none;">
                        <i class="fas fa-calculator me-1" style="color:#27ae60;"></i>
                        Total Purchase Cost: <strong id="purchase_total_val" style="color:#27ae60;">ETB 0.00</strong>
                        <small style="color:#aaa;display:block;margin-top:2px;">This updates the unit price for future profit calculations</small>
                    </div>

                    <div id="purchase_fields">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                            <div>
                                <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:5px;">Purchase Date *</label>
                                <input type="date" name="purchase_date" value="<?php echo date('Y-m-d'); ?>"
                                    style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:8px;font-size:14px;box-sizing:border-box;">
                            </div>
                            <div>
                                <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:5px;">Invoice / Receipt #</label>
                                <input type="text" name="invoice_number" placeholder="e.g., INV-2026-001"
                                    style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:8px;font-size:14px;box-sizing:border-box;">
                            </div>
                        </div>
                        <div style="margin-bottom:16px;">
                            <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:5px;">Supplier Name</label>
                            <input type="text" name="supplier_name" id="restock_supplier"
                                style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:8px;font-size:14px;box-sizing:border-box;"
                                placeholder="Leave blank to keep existing supplier">
                        </div>
                    </div>

                    <div style="display:flex;gap:10px;justify-content:flex-end;">
                        <button type="button" onclick="closeRestockModal()"
                            style="padding:10px 20px;background:#95a5a6;color:white;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;">Cancel</button>
                        <button type="submit"
                            style="padding:10px 24px;background:linear-gradient(135deg,#e67e22,#d35400);color:white;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;">
                            <i class="fas fa-save me-1"></i> Update Stock
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Approve Request Form (hidden) -->
    <form id="approveForm" method="POST" style="display:none;">
        <input type="hidden" name="handle_request" value="1">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <input type="hidden" name="request_id" id="approve_request_id">
        <input type="hidden" name="decision" value="approved">
    </form>

    <!-- Reject Request Modal -->
    <div id="rejectModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
        <div style="background:white;border-radius:12px;padding:30px;max-width:450px;width:90%;">
            <h3 style="margin:0 0 20px;color:#E74C3C;"><i class="fas fa-times-circle"></i> Reject Request</h3>
            <form method="POST">
                <input type="hidden" name="handle_request" value="1">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="request_id" id="reject_request_id">
                <input type="hidden" name="decision" value="rejected">
                <div style="margin-bottom:15px;">
                    <label style="display:block;margin-bottom:5px;font-weight:500;">Reason for Rejection</label>
                    <textarea name="rejection_reason" class="form-control" rows="3" placeholder="Explain why this request is rejected..."></textarea>
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end;">
                    <button type="button" class="btn-action" style="background:#95a5a6;color:white;" onclick="closeRejectModal()">Cancel</button>
                    <button type="submit" class="btn-action btn-danger-custom">Reject Request</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openAddMaterialModal() { document.getElementById('addMaterialModal').style.display = 'flex'; }
    function closeAddMaterialModal() { document.getElementById('addMaterialModal').style.display = 'none'; }
    function openRestockModal(material) {
        document.getElementById('restock_material_id').value = material.id;
        document.getElementById('restock_material_info').innerHTML =
            '<strong>' + material.material_name + '</strong><br>' +
            'Total Stock: <strong>' + parseFloat(material.quantity).toFixed(2) + ' ' + material.unit + '</strong> &nbsp;|&nbsp; ' +
            'Reserved: <span style="color:#e67e22;">' + parseFloat(material.reserved||0).toFixed(2) + '</span> &nbsp;|&nbsp; ' +
            'Available: <strong style="color:#27ae60;">' + parseFloat(material.available_stock||material.quantity).toFixed(2) + '</strong><br>' +
            'Current Unit Price: <strong>ETB ' + parseFloat(material.unit_price).toFixed(2) + '</strong>';
        document.getElementById('restock_supplier').value = material.supplier || '';
        document.getElementById('purchase_price_input').value = material.unit_price || '';
        document.getElementById('restock_action').value = 'add';
        togglePurchaseFields();
        calcTotal();
        document.getElementById('restockModal').style.display = 'flex';
    }
    function closeRestockModal() { document.getElementById('restockModal').style.display = 'none'; }

    function togglePurchaseFields() {
        const action = document.getElementById('restock_action').value;
        const show = action === 'add';
        document.getElementById('purchase_fields').style.display = show ? 'block' : 'none';
        document.getElementById('purchase_price_field').style.display = show ? 'block' : 'none';
        document.getElementById('purchase_total_display').style.display = show ? 'block' : 'none';
    }

    function calcTotal() {
        const qty   = parseFloat(document.querySelector('[name="quantity_change"]')?.value || 0);
        const price = parseFloat(document.getElementById('purchase_price_input')?.value || 0);
        const total = qty * price;
        const el = document.getElementById('purchase_total_val');
        if (el) el.textContent = 'ETB ' + total.toLocaleString('en', {minimumFractionDigits:2});
        document.getElementById('purchase_total_display').style.display = (price > 0 && qty > 0) ? 'block' : 'none';
    }

    // Wire qty input to recalc total
    document.addEventListener('input', function(e) {
        if (e.target.name === 'quantity_change') calcTotal();
    });

    function approveRequest(id) {
        if (!confirm('Approve this material request?\nStock will be reserved for the employee.')) return;
        document.getElementById('approve_request_id').value = id;
        document.getElementById('approveForm').submit();
    }
    function rejectRequest(id) {
        document.getElementById('reject_request_id').value = id;
        document.getElementById('rejectModal').style.display = 'flex';
    }
    function closeRejectModal() { document.getElementById('rejectModal').style.display = 'none'; }
    document.getElementById('addMaterialModal').addEventListener('click', function(e) { if (e.target === this) closeAddMaterialModal(); });
    document.getElementById('restockModal').addEventListener('click', function(e) { if (e.target === this) closeRestockModal(); });
    document.getElementById('rejectModal').addEventListener('click', function(e) { if (e.target === this) closeRejectModal(); });
    </script>
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
<?php
// Flush output buffer
ob_end_flush();
?>
