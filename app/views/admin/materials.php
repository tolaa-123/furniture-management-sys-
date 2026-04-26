<?php
// Admin authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}

// Get database connection
require_once __DIR__ . '/../../../config/db_config.php';

$adminName = $_SESSION['user_name'] ?? 'Admin User';

// Fetch material categories for the add material form
$materialCategories = [];
try {
    $catStmt = $pdo->query("SELECT id, name FROM furn_material_categories WHERE is_active = 1 ORDER BY name ASC");
    $materialCategories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error fetching material categories: ' . $e->getMessage());
}

// Handle material actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    // CSRF token check for all POST actions
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = 'Invalid CSRF token.';
        $messageType = 'danger';
        return;
    }

    if ($action === 'add' && isset($_POST['material_name'])) {
        try {
            // Use global default threshold from settings, fallback to 20
            $threshold = 20;
            try {
                $sv = $pdo->query("SELECT setting_value FROM furn_settings WHERE setting_key='low_stock_threshold' LIMIT 1")->fetchColumn();
                if ($sv !== false && floatval($sv) > 0) $threshold = floatval($sv);
            } catch (PDOException $e2) {}

            $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;

            $pdo->prepare("INSERT INTO furn_materials (name, current_stock, unit, cost_per_unit, minimum_stock, category_id) VALUES (?, ?, ?, ?, ?, ?)")
                ->execute([
                    $_POST['material_name'],
                    $_POST['quantity'],
                    $_POST['unit'],
                    $_POST['unit_price'],
                    $threshold,
                    $category_id
                ]);
            $message = 'Material added successfully';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error adding material: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }

    if ($action === 'update' && isset($_POST['material_id'])) {
        try {
            $stmt = $pdo->prepare("UPDATE furn_materials SET name = ?, current_stock = ?, unit = ?, cost_per_unit = ?, minimum_stock = ?, supplier = ? WHERE id = ?");
            $stmt->execute([
                $_POST['material_name'],
                $_POST['quantity'],
                $_POST['unit'],
                $_POST['unit_price'],
                $_POST['threshold'],
                $_POST['supplier'],
                $_POST['material_id']
            ]);
            $message = 'Material updated successfully';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error updating material: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }

    if ($action === 'restock' && isset($_POST['material_id']) && isset($_POST['restock_quantity'])) {
        try {
            $qty          = floatval($_POST['restock_quantity']);
            $unitPrice    = floatval($_POST['unit_price_paid'] ?? 0);
            $invoiceNum   = trim($_POST['invoice_number'] ?? '');
            $supplierName = trim($_POST['supplier_name'] ?? '');
            $purchaseDate = trim($_POST['purchase_date'] ?? date('Y-m-d'));

            $pdo->prepare("UPDATE furn_materials SET current_stock = current_stock + ?,
                cost_per_unit = IF(? > 0, ?, cost_per_unit),
                supplier = IF(? != '', ?, supplier),
                updated_at = NOW() WHERE id = ?")
                ->execute([$qty, $unitPrice, $unitPrice, $supplierName, $supplierName, $_POST['material_id']]);

            // Log purchase
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS furn_material_purchases (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    material_id INT NOT NULL, manager_id INT NOT NULL,
                    action ENUM('restock','adjustment') NOT NULL DEFAULT 'restock',
                    quantity DECIMAL(10,2) NOT NULL, unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
                    total_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
                    invoice_number VARCHAR(100) DEFAULT NULL, supplier VARCHAR(255) DEFAULT NULL,
                    purchase_date DATE NOT NULL, notes TEXT DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(material_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                $pdo->prepare("INSERT INTO furn_material_purchases
                    (material_id, manager_id, action, quantity, unit_price, total_cost, invoice_number, supplier, purchase_date)
                    VALUES (?, ?, 'restock', ?, ?, ?, ?, ?, ?)")
                    ->execute([$_POST['material_id'], $_SESSION['user_id'], $qty,
                               $unitPrice, $qty * $unitPrice,
                               $invoiceNum ?: null, $supplierName ?: null, $purchaseDate]);
            } catch (PDOException $eLog) { error_log("Purchase log error: " . $eLog->getMessage()); }

            $message = 'Material restocked successfully';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error restocking material: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }

    if ($action === 'delete' && isset($_POST['material_id'])) {
        try {
            // Soft delete — preserve all history (transactions, usage, reservations)
            $stmt = $pdo->prepare("UPDATE furn_materials SET is_active = 0, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$_POST['material_id']]);
            $message = 'Material deactivated successfully';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error deactivating material: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }

    // Admin approve/reject material requests
    if ($action === 'approve_request' && isset($_POST['request_id'])) {
        $request_id = intval($_POST['request_id']);
        try {
            $stmt = $pdo->prepare("SELECT * FROM furn_material_requests WHERE id = ? AND status = 'pending'");
            $stmt->execute([$request_id]);
            $req = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$req) { $message = 'Request not found or already processed.'; $messageType = 'danger'; }
            else {
                $stockStmt = $pdo->prepare("SELECT current_stock, COALESCE(reserved_stock,0) as reserved FROM furn_materials WHERE id = ?");
                $stockStmt->execute([$req['material_id']]);
                $mat = $stockStmt->fetch(PDO::FETCH_ASSOC);
                $available = floatval($mat['current_stock']) - floatval($mat['reserved']);
                if ($available < $req['quantity_requested']) {
                    $message = "Insufficient stock (available: {$available})."; $messageType = 'danger';
                } else {
                    try { $pdo->exec("ALTER TABLE furn_materials ADD COLUMN IF NOT EXISTS reserved_stock DECIMAL(10,2) NOT NULL DEFAULT 0"); } catch (PDOException $e2) {}
                    $pdo->beginTransaction();
                    $pdo->prepare("UPDATE furn_materials SET reserved_stock = COALESCE(reserved_stock,0) + ?, updated_at = NOW() WHERE id = ?")->execute([$req['quantity_requested'], $req['material_id']]);
                    $pdo->prepare("UPDATE furn_material_requests SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ?")->execute([$_SESSION['user_id'], $request_id]);
                    $pdo->commit();
                    require_once __DIR__ . '/../../../app/includes/notification_helper.php';
                    $mn = $pdo->prepare("SELECT name FROM furn_materials WHERE id=?"); $mn->execute([$req['material_id']]); $matName = $mn->fetchColumn() ?: 'material';
                    insertNotification($pdo, $req['employee_id'], 'material', 'Material Request Approved', 'Your request for ' . $req['quantity_requested'] . ' ' . $matName . ' has been approved by Admin.', $request_id, '/employee/materials', 'normal');
                    $message = 'Request approved.'; $messageType = 'success';
                }
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $message = 'Error: ' . $e->getMessage(); $messageType = 'danger';
        }
    }

    if ($action === 'reject_request' && isset($_POST['request_id'])) {
        $request_id = intval($_POST['request_id']);
        $reason = trim($_POST['rejection_reason'] ?? '');
        try {
            $stmt = $pdo->prepare("SELECT employee_id FROM furn_material_requests WHERE id = ? AND status = 'pending'");
            $stmt->execute([$request_id]);
            $req = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($req) {
                $pdo->prepare("UPDATE furn_material_requests SET status = 'rejected', approved_by = ?, approved_at = NOW(), rejection_reason = ? WHERE id = ?")->execute([$_SESSION['user_id'], $reason, $request_id]);
                require_once __DIR__ . '/../../../app/includes/notification_helper.php';
                insertNotification($pdo, $req['employee_id'], 'material', 'Material Request Rejected', 'Your material request was rejected by Admin' . ($reason ? ': ' . $reason : '.'), $request_id, '/employee/materials', 'normal');
                $message = 'Request rejected.'; $messageType = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage(); $messageType = 'danger';
        }
    }

    // Resolve low stock alert
    if ($action === 'resolve_alert' && isset($_POST['alert_id'])) {
        try {
            $pdo->prepare("UPDATE furn_low_stock_alerts SET is_resolved = 1, resolved_at = NOW(), resolved_by = ? WHERE id = ?")->execute([$_SESSION['user_id'], intval($_POST['alert_id'])]);
            $message = 'Alert resolved.'; $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage(); $messageType = 'danger';
        }
    }
}

// Fetch statistics
$stats = [
    'total_materials' => 0,
    'low_stock_count' => 0,
    'pending_orders' => 0,
    'total_value'    => 0,
    'total_reserved' => 0,
    'avg_cost'       => 0,
    'total_suppliers'=> 0,
];

try {
    $stats['total_materials'] = $pdo->query("SELECT COUNT(*) FROM furn_materials WHERE is_active = 1")->fetchColumn();
    $stats['low_stock_count'] = $pdo->query("SELECT COUNT(*) FROM furn_materials WHERE is_active = 1 AND (current_stock - reserved_stock) < minimum_stock")->fetchColumn();
    $stats['pending_orders']  = $pdo->query("SELECT COUNT(*) FROM furn_orders WHERE status = 'pending_cost_approval'")->fetchColumn();
    $stats['total_value']     = $pdo->query("SELECT COALESCE(SUM(current_stock * cost_per_unit), 0) FROM furn_materials WHERE is_active = 1")->fetchColumn();
    $stats['total_reserved']  = $pdo->query("SELECT COALESCE(SUM(reserved_stock), 0) FROM furn_materials WHERE is_active = 1")->fetchColumn();
    $stats['avg_cost']        = $pdo->query("SELECT COALESCE(AVG(cost_per_unit), 0) FROM furn_materials WHERE is_active = 1")->fetchColumn();
    $stats['total_suppliers'] = $pdo->query("SELECT COUNT(DISTINCT supplier) FROM furn_materials WHERE is_active = 1 AND supplier IS NOT NULL AND supplier != ''")->fetchColumn();
} catch (PDOException $e) {
    error_log("Stats error: " . $e->getMessage());
}

// Fetch all materials
$materials = [];
try {
    $stmt = $pdo->query("SELECT * FROM furn_materials WHERE is_active = 1 ORDER BY name ASC");
    $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Materials fetch error: " . $e->getMessage());
    $message = 'Error loading materials: ' . $e->getMessage();
    $messageType = 'danger';
}

// Fetch pending material requests
$pendingRequests = [];
$allRequests = [];
try {
    $stmt = $pdo->query("
        SELECT mr.*, m.name as material_name, m.unit, m.current_stock,
               (m.current_stock - COALESCE(m.reserved_stock,0)) as available_stock,
               COALESCE(m.reserved_stock,0) as reserved_stock,
               CONCAT(u.first_name,' ',u.last_name) as employee_name,
               o.order_number
        FROM furn_material_requests mr
        LEFT JOIN furn_materials m ON mr.material_id = m.id
        LEFT JOIN furn_users u ON mr.employee_id = u.id
        LEFT JOIN furn_orders o ON mr.order_id = o.id
        WHERE mr.status = 'pending'
        ORDER BY mr.created_at ASC
    ");
    $pendingRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt2 = $pdo->query("
        SELECT mr.*, m.name as material_name, m.unit,
               CONCAT(u.first_name,' ',u.last_name) as employee_name,
               o.order_number,
               CONCAT(a.first_name,' ',a.last_name) as approved_by_name
        FROM furn_material_requests mr
        LEFT JOIN furn_materials m ON mr.material_id = m.id
        LEFT JOIN furn_users u ON mr.employee_id = u.id
        LEFT JOIN furn_orders o ON mr.order_id = o.id
        LEFT JOIN furn_users a ON mr.approved_by = a.id
        WHERE mr.status != 'pending'
        ORDER BY mr.updated_at DESC LIMIT 50
    ");
    $allRequests = $stmt2->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("Requests error: " . $e->getMessage()); }

// Fetch purchase history
$purchaseHistory = [];
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS furn_material_purchases (
        id INT AUTO_INCREMENT PRIMARY KEY, material_id INT NOT NULL, manager_id INT NOT NULL,
        action ENUM('restock','adjustment') NOT NULL DEFAULT 'restock',
        quantity DECIMAL(10,2) NOT NULL, unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
        total_cost DECIMAL(10,2) NOT NULL DEFAULT 0, invoice_number VARCHAR(100) DEFAULT NULL,
        supplier VARCHAR(255) DEFAULT NULL, purchase_date DATE NOT NULL, notes TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(material_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $stmt = $pdo->query("
        SELECT fp.*, m.name as material_name, m.unit,
               CONCAT(u.first_name,' ',u.last_name) as recorded_by
        FROM furn_material_purchases fp
        LEFT JOIN furn_materials m ON fp.material_id = m.id
        LEFT JOIN furn_users u ON fp.manager_id = u.id
        ORDER BY fp.purchase_date DESC, fp.created_at DESC LIMIT 100
    ");
    $purchaseHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("Purchase history error: " . $e->getMessage()); }

// Fetch usage reports
$usageReports = [];
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS furn_material_usage (
        id INT AUTO_INCREMENT PRIMARY KEY, employee_id INT NOT NULL, task_id INT DEFAULT NULL,
        material_id INT NOT NULL, quantity_used DECIMAL(10,2) NOT NULL,
        waste_amount DECIMAL(10,2) DEFAULT 0, notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(employee_id), INDEX(material_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $stmt = $pdo->query("
        SELECT mu.*, m.name as material_name, m.unit,
               CONCAT(u.first_name,' ',u.last_name) as employee_name,
               o.order_number
        FROM furn_material_usage mu
        LEFT JOIN furn_materials m ON mu.material_id = m.id
        LEFT JOIN furn_users u ON mu.employee_id = u.id
        LEFT JOIN furn_production_tasks t ON mu.task_id = t.id
        LEFT JOIN furn_orders o ON t.order_id = o.id
        ORDER BY mu.created_at DESC LIMIT 100
    ");
    $usageReports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("Usage reports error: " . $e->getMessage()); }

// Fetch low stock alerts
$activeAlerts = [];
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS furn_low_stock_alerts (
        id INT AUTO_INCREMENT PRIMARY KEY, material_id INT NOT NULL,
        current_stock DECIMAL(10,2) NOT NULL, minimum_stock DECIMAL(10,2) NOT NULL,
        alert_level ENUM('low','critical') NOT NULL, is_resolved TINYINT(1) NOT NULL DEFAULT 0,
        resolved_at TIMESTAMP NULL DEFAULT NULL, resolved_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY(material_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $stmt = $pdo->query("
        SELECT lsa.*, m.name as material_name, m.unit,
               CONCAT(u.first_name,' ',u.last_name) as resolved_by_name
        FROM furn_low_stock_alerts lsa
        JOIN furn_materials m ON lsa.material_id = m.id
        LEFT JOIN furn_users u ON lsa.resolved_by = u.id
        WHERE lsa.is_resolved = 0
        ORDER BY CASE WHEN lsa.alert_level='critical' THEN 1 ELSE 2 END, lsa.created_at DESC
    ");
    $activeAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("Alerts error: " . $e->getMessage()); }

// Fetch materials by furniture type
$furnitureMaterials = [
    'Sofa' => [],
    'Chair' => [],
    'Bed' => [],
    'Table' => [],
    'Desk' => [],
    'Shelf' => []
];

// Create a lookup array for material costs
$materialCosts = [];
try {
    $costStmt = $pdo->query("SELECT id, name, cost_per_unit FROM furn_materials WHERE is_active = 1");
    while ($row = $costStmt->fetch(PDO::FETCH_ASSOC)) {
        $materialCosts[$row['name']] = floatval($row['cost_per_unit']);
    }
} catch (PDOException $e) {
    error_log("Material costs error: " . $e->getMessage());
}

try {
    // Get all products with their categories
    $stmt = $pdo->query("
        SELECT p.id, p.name as product_name, c.name as category_name,
               m.name as material_name, m.unit, pm.quantity_required, m.cost_per_unit
        FROM furn_products p
        LEFT JOIN furn_categories c ON p.category_id = c.id
        LEFT JOIN furn_product_materials pm ON p.id = pm.product_id
        LEFT JOIN furn_materials m ON pm.material_id = m.id
        WHERE p.is_active = 1
        ORDER BY c.name, p.name, m.name
    ");
    $productMaterials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group materials by furniture category
    foreach ($productMaterials as $pm) {
        $category = ucfirst($pm['category_name']);
        if (isset($furnitureMaterials[$category]) && $pm['material_name']) {
            $costPerUnit = floatval($pm['cost_per_unit']) ?: ($materialCosts[$pm['material_name']] ?? 0);
            $furnitureMaterials[$category][] = [
                'product_name' => $pm['product_name'],
                'material_name' => $pm['material_name'],
                'unit' => $pm['unit'],
                'quantity_required' => $pm['quantity_required'],
                'cost_per_unit' => $costPerUnit,
                'total_cost' => $costPerUnit * floatval($pm['quantity_required'])
            ];
        }
    }
} catch (PDOException $e) { 
    error_log("Furniture materials error: " . $e->getMessage());
}

// Auto-generate low stock alerts for materials below threshold
try {
    $lowMats = $pdo->query("SELECT id, current_stock, COALESCE(reserved_stock,0) as reserved, minimum_stock FROM furn_materials WHERE is_active=1 AND (current_stock - COALESCE(reserved_stock,0)) <= minimum_stock")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($lowMats as $lm) {
        $avail = $lm['current_stock'] - $lm['reserved'];
        $existing = $pdo->prepare("SELECT id FROM furn_low_stock_alerts WHERE material_id=? AND is_resolved=0 LIMIT 1");
        $existing->execute([$lm['id']]);
        if (!$existing->fetch()) {
            $level = ($avail <= $lm['minimum_stock'] * 0.5) ? 'critical' : 'low';
            $pdo->prepare("INSERT INTO furn_low_stock_alerts (material_id, current_stock, minimum_stock, alert_level) VALUES (?,?,?,?)")->execute([$lm['id'], $avail, $lm['minimum_stock'], $level]);
        }
    }
    // Refresh alerts after auto-generate
    $stmt = $pdo->query("SELECT lsa.*, m.name as material_name, m.unit FROM furn_low_stock_alerts lsa JOIN furn_materials m ON lsa.material_id = m.id WHERE lsa.is_resolved = 0 ORDER BY CASE WHEN lsa.alert_level='critical' THEN 1 ELSE 2 END, lsa.created_at DESC");
    $activeAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("Auto-alert error: " . $e->getMessage()); }

$stats['pending_requests'] = count($pendingRequests);
$stats['active_alerts'] = count($activeAlerts);

$pageTitle = 'Raw Materials Management';
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
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" aria-label="Toggle Menu">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay (for mobile) -->
    <div class="sidebar-overlay"></div>

    <!-- Sidebar -->
    <?php include_once __DIR__ . '/../../includes/admin_sidebar.php'; ?>

    <!-- Header -->
    <!-- Top Header -->
    <?php 
    $pageTitle = 'Materials';
    include_once __DIR__ . '/../../includes/admin_header.php'; 
    ?>
        <div class="system-status">
            <span style="width: 10px; height: 10px; background: white; border-radius: 50%; display: inline-block;"></span>
            <span>Operational</span>
        </div>
        <div style="display: flex; align-items: center; gap: 20px;">
            <div class="notification-bell">
                <i class="fas fa-bell"></i>
                <?php if($stats['low_stock_count'] > 0): ?>
                <span class="notification-badge"><?php echo $stats['low_stock_count']; ?></span>
                <?php endif; ?>
            </div>
            <div class="admin-profile">
                <div class="admin-avatar"><?php echo strtoupper(substr($adminName, 0, 1)); ?></div>
                <div>
                    <div style="font-size: 14px; font-weight: 600;"><?php echo htmlspecialchars($adminName); ?></div>
                    <div class="admin-role-badge">Administrator</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <h2 style="margin-bottom: 30px; color: #2c3e50;">Raw Materials Management</h2>
        
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
            <?php if ($messageType === 'danger' && strpos($message, 'supplier column') !== false): ?>
            <br><br>
            <strong>Fix Instructions:</strong>
            <ol style="margin-top: 10px;">
                <li>Open in browser: <a href="<?php echo BASE_URL; ?>/public/fix_materials_table.php" style="color: #3498DB; text-decoration: underline;"><?php echo BASE_URL; ?>/public/fix_materials_table.php</a></li>
                <li>This will add the missing supplier column to the materials table</li>
                <li>Refresh this page after the fix is complete</li>
            </ol>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['total_materials']); ?></div>
                <div class="stat-label">Total Materials</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color:#E74C3C;"><?php echo number_format($stats['low_stock_count']); ?></div>
                <div class="stat-label">Low Stock Alerts</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">ETB <?php echo number_format($stats['total_value'], 0); ?></div>
                <div class="stat-label">Inventory Value</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['total_reserved'], 2); ?></div>
                <div class="stat-label">Total Reserved</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">ETB <?php echo number_format($stats['avg_cost'], 2); ?></div>
                <div class="stat-label">Avg Cost/Unit</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['total_suppliers']); ?></div>
                <div class="stat-label">Suppliers</div>
            </div>
        </div>

        <!-- Materials Table -->
        <div class="section-card">
            <div class="section-header">
                <div class="section-title"><i class="fas fa-boxes me-2"></i>All Materials</div>
                <button class="btn-action btn-success-custom" onclick="showAddModal()">
                    <i class="fas fa-plus me-1"></i>Add New Material
                </button>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px;">
                <input type="text" id="matSearch" placeholder="Search by name or supplier..."
                    style="flex:1; min-width:200px; padding:9px 14px; border:1px solid #ddd; border-radius:8px; font-size:13px; outline:none;">
                <select id="stockFilter" style="padding:9px 14px; border:1px solid #ddd; border-radius:8px; font-size:13px; outline:none;">
                    <option value="">All Stock</option>
                    <option value="low">Low Stock</option>
                    <option value="ok">In Stock</option>
                </select>
                <button onclick="document.getElementById('matSearch').value=''; document.getElementById('stockFilter').value=''; filterMaterials();"
                    style="padding:9px 16px; background:#f0f0f0; border:1px solid #ddd; border-radius:8px; font-size:13px; cursor:pointer;">
                    <i class="fas fa-times"></i> Clear
                </button>
                <span id="matCount" style="padding:9px 0; font-size:13px; color:#7f8c8d; align-self:center;"></span>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Material Name</th>
                            <th>Quantity</th>
                            <th>Unit</th>
                            <th>Unit Price</th>
                            <th>Threshold</th>
                            <th>Supplier</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="matBody">
                        <?php foreach ($materials as $material): 
                            // Map database columns to expected format
                            $material['material_name'] = $material['name'];
                            $material['quantity'] = $material['current_stock'];
                            $material['unit_price'] = $material['cost_per_unit'];
                            $material['threshold'] = $material['minimum_stock'];
                            $availableStock = ($material['current_stock'] ?? 0) - ($material['reserved_stock'] ?? 0);
                            $isLowStock = $availableStock < ($material['minimum_stock'] ?? 0);
                        ?>
                        <tr style="<?php echo $isLowStock ? 'background: #fff3cd;' : ''; ?>">
                            <td><?php echo $material['id'] ?? 'N/A'; ?></td>
                            <td><strong><?php echo htmlspecialchars($material['name'] ?? 'N/A'); ?></strong></td>
                            <td class="<?php echo $isLowStock ? 'stock-low' : 'stock-ok'; ?>">
                                <?php echo number_format($material['current_stock'] ?? 0, 2); ?>
                                <?php if ($material['reserved_stock'] > 0): ?>
                                <small style="color: #666;">(<?php echo number_format($availableStock, 2); ?> avail)</small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($material['unit'] ?? 'pieces'); ?></td>
                            <td>ETB <?php echo number_format($material['cost_per_unit'] ?? 0, 2); ?></td>
                            <td><?php echo number_format($material['minimum_stock'] ?? 0, 2); ?></td>
                            <td><?php echo htmlspecialchars($material['supplier'] ?? 'N/A'); ?></td>
                            <td>
                                <?php if ($isLowStock): ?>
                                <span style="color: #E74C3C; font-weight: 600;"><i class="fas fa-exclamation-triangle"></i> Low Stock</span>
                                <?php else: ?>
                                <span style="color: #27AE60; font-weight: 600;"><i class="fas fa-check-circle"></i> In Stock</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($isLowStock): ?>
                                <button class="btn-action btn-warning-custom" onclick="showRestockModal(<?php echo $material['id'] ?? ''; ?>, '<?php echo htmlspecialchars($material['name']); ?>')">
                                    <i class="fas fa-plus-circle"></i> Restock
                                </button>
                                <?php endif; ?>
                                <button class="btn-action btn-primary-custom" onclick='editMaterial(<?php echo json_encode($material); ?>)'>
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Deactivate this material? It will be hidden but history is preserved.');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="material_id" value="<?php echo $material['id'] ?? ''; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? (($_SESSION['csrf_token'] = bin2hex(random_bytes(32))) ? $_SESSION['csrf_token'] : bin2hex(random_bytes(32)))); ?>">
                                    <button type="submit" class="btn-action btn-danger-custom">
                                        <i class="fas fa-ban"></i> Deactivate
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>


        <!-- ===== PENDING MATERIAL REQUESTS ===== -->
        <?php if (!empty($pendingRequests)): ?>
        <div class="section-card" style="border-left:4px solid #E74C3C;">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-bell" style="color:#E74C3C;"></i> Pending Material Requests
                    <span style="background:#E74C3C;color:white;border-radius:50%;padding:2px 8px;font-size:13px;margin-left:8px;"><?php echo count($pendingRequests); ?></span>
                </h2>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Employee</th><th>Material</th><th>Qty Requested</th><th>Available</th><th>Order</th><th>Date</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($pendingRequests as $req): ?>
                        <tr>
                            <td>#<?php echo str_pad($req['id'],4,'0',STR_PAD_LEFT); ?></td>
                            <td><strong><?php echo htmlspecialchars($req['employee_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($req['material_name']); ?></td>
                            <td><strong><?php echo $req['quantity_requested']; ?> <?php echo $req['unit']; ?></strong></td>
                            <td>
                                <?php $avail = floatval($req['available_stock'] ?? ($req['current_stock'] - $req['reserved_stock'])); ?>
                                <?php if ($avail < $req['quantity_requested']): ?>
                                    <span style="color:#E74C3C;font-weight:600;"><?php echo number_format($avail,2); ?> ⚠ Low</span>
                                <?php else: ?>
                                    <span style="color:#27AE60;"><?php echo number_format($avail,2); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $req['order_number'] ? htmlspecialchars($req['order_number']) : 'N/A'; ?></td>
                            <td><?php echo date('M d, Y', strtotime($req['created_at'])); ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="approve_request">
                                    <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <button type="submit" class="btn-action btn-success-custom" style="padding:5px 10px;font-size:12px;"><i class="fas fa-check"></i> Approve</button>
                                </form>
                                <button class="btn-action btn-danger-custom" style="padding:5px 10px;font-size:12px;margin-top:4px;" onclick="adminRejectRequest(<?php echo $req['id']; ?>)"><i class="fas fa-times"></i> Reject</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- ===== ALL REQUESTS HISTORY ===== -->
        <?php if (!empty($allRequests)): ?>
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-history"></i> Material Request History</h2>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Employee</th><th>Material</th><th>Qty</th><th>Order</th><th>Status</th><th>Processed By</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach ($allRequests as $req): ?>
                        <tr>
                            <td>#<?php echo str_pad($req['id'],4,'0',STR_PAD_LEFT); ?></td>
                            <td><?php echo htmlspecialchars($req['employee_name']); ?></td>
                            <td><?php echo htmlspecialchars($req['material_name']); ?></td>
                            <td><?php echo $req['quantity_requested']; ?> <?php echo $req['unit']; ?></td>
                            <td><?php echo $req['order_number'] ? htmlspecialchars($req['order_number']) : 'N/A'; ?></td>
                            <td>
                                <?php $sc = ['approved'=>'success','rejected'=>'danger','pending'=>'warning']; ?>
                                <span class="badge badge-<?php echo $sc[$req['status']] ?? 'secondary'; ?>"><?php echo ucfirst($req['status']); ?></span>
                                <?php if ($req['status']==='rejected' && !empty($req['rejection_reason'])): ?>
                                    <br><small style="color:#E74C3C;"><?php echo htmlspecialchars($req['rejection_reason']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo !empty($req['approved_by_name']) ? htmlspecialchars($req['approved_by_name']) : '<span style="color:#aaa;">—</span>'; ?></td>
                            <td><?php echo date('M d, Y', strtotime($req['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- ===== LOW STOCK ALERTS ===== -->
        <?php if (!empty($activeAlerts)): ?>
        <div class="section-card" style="border-left:4px solid #E74C3C;">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-exclamation-triangle" style="color:#E74C3C;"></i> Active Low Stock Alerts
                    <span style="background:#E74C3C;color:white;border-radius:50%;padding:2px 8px;font-size:13px;margin-left:8px;"><?php echo count($activeAlerts); ?></span>
                </h2>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Material</th><th>Current Stock</th><th>Minimum</th><th>Level</th><th>Created</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach ($activeAlerts as $alert): ?>
                        <tr style="<?php echo $alert['alert_level']==='critical' ? 'background:#fff0f0;' : 'background:#fffbf0;'; ?>">
                            <td><strong><?php echo htmlspecialchars($alert['material_name']); ?></strong></td>
                            <td style="color:#E74C3C;font-weight:600;"><?php echo number_format($alert['current_stock'],2); ?> <?php echo $alert['unit']; ?></td>
                            <td><?php echo number_format($alert['minimum_stock'],2); ?></td>
                            <td>
                                <?php if ($alert['alert_level']==='critical'): ?>
                                    <span style="background:#E74C3C;color:white;padding:3px 8px;border-radius:4px;font-size:12px;font-weight:600;">CRITICAL</span>
                                <?php else: ?>
                                    <span style="background:#F39C12;color:white;padding:3px 8px;border-radius:4px;font-size:12px;font-weight:600;">LOW</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($alert['created_at'])); ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="resolve_alert">
                                    <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                    <button type="submit" class="btn-action btn-success-custom" style="padding:5px 10px;font-size:12px;"><i class="fas fa-check"></i> Resolve</button>
                                </form>
                                <button class="btn-action btn-warning-custom" style="padding:5px 10px;font-size:12px;" onclick="showRestockModal(<?php echo $alert['material_id']; ?>, '<?php echo htmlspecialchars($alert['material_name']); ?>')"><i class="fas fa-plus"></i> Restock</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- ===== PURCHASE HISTORY ===== -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-receipt"></i> Purchase History (Restock Log)</h2>
            </div>
            <?php if (empty($purchaseHistory)): ?>
                <p style="text-align:center;padding:40px;color:#aaa;">No purchase records yet.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Date</th><th>Material</th><th>Qty</th><th>Unit Price</th><th>Total Cost</th><th>Invoice #</th><th>Supplier</th><th>Recorded By</th></tr></thead>
                    <tbody>
                        <?php $totalPurchase = 0; foreach ($purchaseHistory as $p): $totalPurchase += floatval($p['total_cost']); ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($p['purchase_date'])); ?></td>
                            <td><strong><?php echo htmlspecialchars($p['material_name']); ?></strong> <small style="color:#aaa;">(<?php echo $p['unit']; ?>)</small></td>
                            <td><?php echo number_format($p['quantity'],2); ?></td>
                            <td>ETB <?php echo number_format($p['unit_price'],2); ?></td>
                            <td><strong>ETB <?php echo number_format($p['total_cost'],2); ?></strong></td>
                            <td><?php echo $p['invoice_number'] ? htmlspecialchars($p['invoice_number']) : '<span style="color:#aaa;">—</span>'; ?></td>
                            <td><?php echo $p['supplier'] ? htmlspecialchars($p['supplier']) : '<span style="color:#aaa;">—</span>'; ?></td>
                            <td><?php echo htmlspecialchars($p['recorded_by'] ?? 'N/A'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr style="background:#f8f9fa;font-weight:700;">
                            <td colspan="4" style="text-align:right;">Total:</td>
                            <td>ETB <?php echo number_format($totalPurchase,2); ?></td>
                            <td colspan="3"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- ===== USAGE REPORTS ===== -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-chart-bar"></i> Employee Material Usage Reports</h2>
            </div>
            <?php if (empty($usageReports)): ?>
                <p style="text-align:center;padding:40px;color:#aaa;">No usage reports yet.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Date</th><th>Employee</th><th>Material</th><th>Used</th><th>Waste</th><th>Total Consumed</th><th>Order</th><th>Notes</th></tr></thead>
                    <tbody>
                        <?php foreach ($usageReports as $u): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                            <td><strong><?php echo htmlspecialchars($u['employee_name'] ?? 'N/A'); ?></strong></td>
                            <td><?php echo htmlspecialchars($u['material_name'] ?? 'N/A'); ?> <small style="color:#aaa;">(<?php echo $u['unit']; ?>)</small></td>
                            <td><?php echo number_format($u['quantity_used'],2); ?></td>
                            <td style="color:<?php echo floatval($u['waste_amount'])>0?'#E74C3C':'#27AE60'; ?>">
                                <?php echo number_format($u['waste_amount'],2); ?>
                            </td>
                            <td><strong><?php echo number_format($u['quantity_used'] + $u['waste_amount'],2); ?></strong></td>
                            <td><?php echo !empty($u['order_number']) ? htmlspecialchars($u['order_number']) : '<span style="color:#aaa;">N/A</span>'; ?></td>
                            <td style="font-size:12px;color:#666;"><?php echo htmlspecialchars($u['notes'] ?? ''); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Admin Reject Request Modal -->
    <div id="adminRejectModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
        <div style="background:white;border-radius:12px;padding:0;width:90%;max-width:460px;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,.2);">
            <div style="background:linear-gradient(135deg,#e74c3c,#c0392b);padding:16px 22px;display:flex;justify-content:space-between;align-items:center;">
                <h3 style="margin:0;color:white;font-size:16px;"><i class="fas fa-times-circle"></i> Reject Material Request</h3>
                <button onclick="document.getElementById('adminRejectModal').style.display='none'" style="background:none;border:none;color:white;font-size:22px;cursor:pointer;">&times;</button>
            </div>
            <div style="padding:22px;">
                <form method="POST">
                    <input type="hidden" name="action" value="reject_request">
                    <input type="hidden" name="request_id" id="admin_reject_request_id">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div style="margin-bottom:14px;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:5px;">Rejection Reason</label>
                        <textarea name="rejection_reason" rows="3" placeholder="Explain why this request is rejected..."
                            style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:8px;font-size:14px;box-sizing:border-box;resize:vertical;"></textarea>
                    </div>
                    <div style="display:flex;gap:10px;justify-content:flex-end;">
                        <button type="button" onclick="document.getElementById('adminRejectModal').style.display='none'" style="padding:10px 20px;background:#95a5a6;color:white;border:none;border-radius:8px;cursor:pointer;">Cancel</button>
                        <button type="submit" style="padding:10px 20px;background:#e74c3c;color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;"><i class="fas fa-times"></i> Reject</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div id="addModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;overflow-y:auto;">
        <div style="background:white;border-radius:14px;padding:0;width:90%;max-width:520px;margin:20px auto;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,.2);">
            <div style="background:linear-gradient(135deg,#27ae60,#1e8449);padding:16px 22px;display:flex;justify-content:space-between;align-items:center;">
                <h3 style="margin:0;color:white;font-size:16px;"><i class="fas fa-plus-circle me-2"></i>Add New Material</h3>
                <button onclick="closeAddModal()" style="background:none;border:none;color:white;font-size:22px;cursor:pointer;">&times;</button>
            </div>
            <div style="padding:22px;">
                <form method="POST">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? (($_SESSION['csrf_token'] = bin2hex(random_bytes(32))) ? $_SESSION['csrf_token'] : bin2hex(random_bytes(32)))); ?>">
                    <div style="margin-bottom:14px;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:5px;">Material Name <span style="color:#e74c3c;">*</span></label>
                        <input type="text" name="material_name" required placeholder="e.g., Oak Wood, Premium Leather"
                            style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:8px;font-size:14px;box-sizing:border-box;">
                    </div>
                    <div style="margin-bottom:14px;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:5px;">Category <span style="color:#e74c3c;">*</span></label>
                        <select name="category_id" required style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:8px;font-size:14px;">
                            <option value="">Select Category</option>
                            <?php foreach ($materialCategories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
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
                    <div style="display:flex;gap:10px;justify-content:flex-end;">
                        <button type="button" onclick="closeAddModal()"
                            style="padding:10px 20px;background:#95a5a6;color:white;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;">Cancel</button>
                        <button type="submit" class="btn-action btn-success-custom" style="padding:10px 24px;">
                            <i class="fas fa-plus me-1"></i>Add Material
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Material Modal -->
    <div id="editModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; overflow-y: auto;">
        <div style="background: white; padding: 30px; border-radius: 12px; width: 90%; max-width: 600px; margin: 20px;">
            <h3 style="margin-bottom: 20px;">Edit Material</h3>
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="material_id" id="edit_material_id">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? (($_SESSION['csrf_token'] = bin2hex(random_bytes(32))) ? $_SESSION['csrf_token'] : bin2hex(random_bytes(32)))); ?>">
                <div class="form-group">
                    <label>Material Name *</label>
                    <input type="text" name="material_name" id="edit_material_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Quantity *</label>
                    <input type="number" name="quantity" id="edit_quantity" class="form-control" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label>Unit *</label>
                    <select name="unit" id="edit_unit" class="form-control" required>
                        <option value="">Select Unit</option>
                        <option value="kg">Kilograms (kg)</option>
                        <option value="m">Meters (m)</option>
                        <option value="m2">Square Meters (m²)</option>
                        <option value="m3">Cubic Meters (m³)</option>
                        <option value="pcs">Pieces (pcs)</option>
                        <option value="liters">Liters</option>
                        <option value="boxes">Boxes</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Unit Price (ETB) *</label>
                    <input type="number" name="unit_price" id="edit_unit_price" class="form-control" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label>Threshold (Low Stock Alert) *</label>
                    <input type="number" name="threshold" id="edit_threshold" class="form-control" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label>Supplier</label>
                    <input type="text" name="supplier" id="edit_supplier" class="form-control">
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" onclick="closeEditModal()" style="padding: 10px 20px; border: 1px solid #ddd; background: white; border-radius: 6px; cursor: pointer;">Cancel</button>
                    <button type="submit" class="btn-action btn-success-custom" style="padding: 10px 20px;">Update Material</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Restock Modal -->
    <div id="restockModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
        <div style="background:white;border-radius:14px;padding:0;width:90%;max-width:540px;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,.2);">
            <div style="background:linear-gradient(135deg,#27ae60,#1e8449);padding:16px 22px;display:flex;justify-content:space-between;align-items:center;">
                <h3 style="margin:0;color:white;font-size:16px;"><i class="fas fa-box me-2"></i>Restock Material</h3>
                <button onclick="closeRestockModal()" style="background:none;border:none;color:white;font-size:22px;cursor:pointer;">&times;</button>
            </div>
            <div style="padding:22px;">
                <form method="POST">
                    <input type="hidden" name="action" value="restock">
                    <input type="hidden" name="material_id" id="restock_material_id">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? (($_SESSION['csrf_token'] = bin2hex(random_bytes(32))) ? $_SESSION['csrf_token'] : bin2hex(random_bytes(32)))); ?>">

                    <div style="background:#f8f4e9;border-radius:8px;padding:12px;margin-bottom:16px;border-left:4px solid #27ae60;font-size:13px;">
                        <strong id="restock_material_name"></strong>
                        <div id="restock_stock_info" style="color:#555;margin-top:4px;"></div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                        <div>
                            <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:5px;">Quantity to Add *</label>
                            <input type="number" name="restock_quantity" id="admin_restock_qty" step="0.01" min="0.01" required
                                style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:8px;font-size:14px;box-sizing:border-box;"
                                oninput="calcAdminTotal()">
                        </div>
                        <div>
                            <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:5px;">Unit Price Paid (ETB)</label>
                            <input type="number" name="unit_price_paid" id="admin_unit_price" step="0.01" min="0"
                                style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:8px;font-size:14px;box-sizing:border-box;"
                                placeholder="Price per unit" oninput="calcAdminTotal()">
                        </div>
                    </div>

                    <div id="admin_total_display" style="background:#e8f5e9;border-radius:8px;padding:10px 14px;margin-bottom:14px;font-size:13px;display:none;">
                        <i class="fas fa-calculator me-1" style="color:#27ae60;"></i>
                        Total Purchase Cost: <strong id="admin_total_val" style="color:#27ae60;">ETB 0.00</strong>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                        <div>
                            <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:5px;">Purchase Date</label>
                            <input type="date" name="purchase_date" value="<?php echo date('Y-m-d'); ?>"
                                style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:8px;font-size:14px;box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:5px;">Invoice / Receipt #</label>
                            <input type="text" name="invoice_number" placeholder="e.g., INV-2026-001"
                                style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:8px;font-size:14px;box-sizing:border-box;">
                        </div>
                    </div>
                    <div style="margin-bottom:18px;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:5px;">Supplier Name</label>
                        <input type="text" name="supplier_name" id="admin_restock_supplier"
                            style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:8px;font-size:14px;box-sizing:border-box;"
                            placeholder="Leave blank to keep existing supplier">
                    </div>

                    <div style="display:flex;gap:10px;justify-content:flex-end;">
                        <button type="button" onclick="closeRestockModal()"
                            style="padding:10px 20px;background:#95a5a6;color:white;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;">Cancel</button>
                        <button type="submit" class="btn-action btn-success-custom" style="padding:10px 24px;">
                            <i class="fas fa-save me-1"></i> Restock
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Materials search + stock filter
        function filterMaterials() {
            const q      = document.getElementById('matSearch').value.toLowerCase().trim();
            const stock  = document.getElementById('stockFilter').value;
            const rows   = document.getElementById('matBody').querySelectorAll('tr');
            let visible  = 0;
            rows.forEach(row => {
                const text     = row.textContent.toLowerCase();
                const isLow    = row.style.background === 'rgb(255, 243, 205)' || row.querySelector('.stock-low') !== null;
                const matchQ   = !q || text.includes(q);
                const matchS   = !stock || (stock === 'low' && isLow) || (stock === 'ok' && !isLow);
                const match    = matchQ && matchS;
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            document.getElementById('matCount').textContent = visible + ' of ' + rows.length + ' materials';
        }
        document.getElementById('matSearch').addEventListener('input', filterMaterials);
        document.getElementById('stockFilter').addEventListener('change', filterMaterials);
        filterMaterials();

        function showAddModal() {
            document.getElementById('addModal').style.display = 'flex';
        }
        
        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }
        
        function editMaterial(material) {
            document.getElementById('edit_material_id').value = material.id;
            document.getElementById('edit_material_name').value = material.material_name;
            document.getElementById('edit_quantity').value = material.quantity;
            document.getElementById('edit_unit').value = material.unit;
            document.getElementById('edit_unit_price').value = material.unit_price;
            document.getElementById('edit_threshold').value = material.threshold;
            document.getElementById('edit_supplier').value = material.supplier;
            document.getElementById('editModal').style.display = 'flex';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        function showRestockModal(id, name) {
            document.getElementById('restock_material_id').value = id;
            document.getElementById('restock_material_name').textContent = name;
            document.getElementById('restock_stock_info').textContent = '';
            document.getElementById('admin_restock_supplier').value = '';
            document.getElementById('admin_restock_qty').value = '';
            document.getElementById('admin_unit_price').value = '';
            document.getElementById('admin_total_display').style.display = 'none';
            document.getElementById('restockModal').style.display = 'flex';
        }

        function closeRestockModal() {
            document.getElementById('restockModal').style.display = 'none';
        }

        function calcAdminTotal() {
            const qty   = parseFloat(document.getElementById('admin_restock_qty').value || 0);
            const price = parseFloat(document.getElementById('admin_unit_price').value || 0);
            const total = qty * price;
            const disp  = document.getElementById('admin_total_display');
            if (qty > 0 && price > 0) {
                document.getElementById('admin_total_val').textContent = 'ETB ' + total.toLocaleString('en', {minimumFractionDigits:2});
                disp.style.display = 'block';
            } else {
                disp.style.display = 'none';
            }
        }

        function adminRejectRequest(requestId) {
            document.getElementById('admin_reject_request_id').value = requestId;
            document.getElementById('adminRejectModal').style.display = 'flex';
        }
    </script>
    <!-- Mobile Menu Script -->
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
