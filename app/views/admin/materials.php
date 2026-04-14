<?php
// Admin authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}

// Get database connection
require_once __DIR__ . '/../../../config/db_config.php';

$adminName = $_SESSION['user_name'] ?? 'Admin User';

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

            $pdo->prepare("INSERT INTO furn_materials (name, current_stock, unit, cost_per_unit, minimum_stock) VALUES (?, ?, ?, ?, ?)")
                ->execute([
                    $_POST['material_name'],
                    $_POST['quantity'],
                    $_POST['unit'],
                    $_POST['unit_price'],
                    $threshold,
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
            $stmt = $pdo->prepare("DELETE FROM furn_materials WHERE id = ?");
            $stmt->execute([$_POST['material_id']]);
            $message = 'Material deleted successfully';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error deleting material: ' . $e->getMessage();
            $messageType = 'danger';
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
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this material?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="material_id" value="<?php echo $material['id'] ?? ''; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? (($_SESSION['csrf_token'] = bin2hex(random_bytes(32))) ? $_SESSION['csrf_token'] : bin2hex(random_bytes(32)))); ?>">
                                    <button type="submit" class="btn-action btn-danger-custom">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Material Modal -->
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
    </script>
    <!-- Mobile Menu Script -->
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
