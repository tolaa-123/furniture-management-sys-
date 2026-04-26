<?php
// Admin authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}

// Get database connection
require_once __DIR__ . '/../../../config/db_config.php';

$adminName = $_SESSION['user_name'] ?? 'Admin User';

// Handle product actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' && isset($_POST['product_name'])) {
        try {
            $stmt = $pdo->prepare("INSERT INTO furn_products (name, category, description, base_price, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([
                $_POST['product_name'],
                $_POST['category'],
                $_POST['description'],
                $_POST['base_price']
            ]);
            $message = 'Product added successfully';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error adding product: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
    
    if ($action === 'update' && isset($_POST['product_id'])) {
        try {
            $stmt = $pdo->prepare("UPDATE furn_products SET name = ?, category = ?, description = ?, base_price = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([
                $_POST['product_name'],
                $_POST['category'],
                $_POST['description'],
                $_POST['base_price'],
                $_POST['product_id']
            ]);
            $message = 'Product updated successfully';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error updating product: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
    
    if ($action === 'delete' && isset($_POST['product_id'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM furn_products WHERE id = ?");
            $stmt->execute([$_POST['product_id']]);
            $message = 'Product deleted successfully';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error deleting product: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Fetch statistics
$stats = [
    'total_products'   => 0,
    'active_products'  => 0,
    'categories'       => 0,
    'avg_price'        => 0,
    'pending_orders'   => 0,
    'low_stock_materials' => 0,
];

try {
    $tableExists = $pdo->query("SHOW TABLES LIKE 'furn_products'")->fetch();
    if ($tableExists) {
        $stats['total_products']  = $pdo->query("SELECT COUNT(*) FROM furn_products")->fetchColumn();
        $stats['active_products'] = $pdo->query("SELECT COUNT(*) FROM furn_products WHERE is_active = 1")->fetchColumn();
        $stats['categories']      = $pdo->query("SELECT COUNT(DISTINCT category) FROM furn_products WHERE category IS NOT NULL AND category != ''")->fetchColumn();
        $stats['avg_price']       = $pdo->query("SELECT COALESCE(AVG(base_price), 0) FROM furn_products WHERE is_active = 1")->fetchColumn();
    }
    $stats['pending_orders']      = $pdo->query("SELECT COUNT(*) FROM furn_orders WHERE status = 'pending_cost_approval'")->fetchColumn();
    $stats['low_stock_materials'] = $pdo->query("SELECT COUNT(*) FROM furn_materials WHERE current_stock < minimum_stock")->fetchColumn();
} catch (PDOException $e) {
    error_log("Stats error: " . $e->getMessage());
}

// Fetch all products
$products = [];
try {
    // Check if table exists first
    $tableExists = $pdo->query("SHOW TABLES LIKE 'furn_products'")->fetch();
    if ($tableExists) {
        $stmt = $pdo->query("SELECT * FROM furn_products ORDER BY created_at DESC");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Auto-fix NULL categories based on product name keywords
        $categoryMap = [
            'sofa'      => 'Sofa',   'couch'     => 'Sofa',
            'chair'     => 'Chair',  'seat'      => 'Chair',
            'table'     => 'Table',  'dining'    => 'Table',
            'bed'       => 'Bed',    'mattress'  => 'Bed',
            'cabinet'   => 'Cabinet','kitchen'   => 'Cabinet',
            'desk'      => 'Desk',   'office'    => 'Desk',
            'wardrobe'  => 'Wardrobe','closet'   => 'Wardrobe',
            'bookshelf' => 'Bookshelf','shelf'   => 'Bookshelf','bookcase' => 'Bookshelf',
        ];
        foreach ($products as &$product) {
            if (empty($product['category'])) {
                $nameLower = strtolower($product['name'] ?? '');
                $detected  = 'Other';
                foreach ($categoryMap as $keyword => $cat) {
                    if (strpos($nameLower, $keyword) !== false) {
                        $detected = $cat;
                        break;
                    }
                }
                // Update DB
                $pdo->prepare("UPDATE furn_products SET category = ? WHERE id = ?")
                    ->execute([$detected, $product['id']]);
                $product['category'] = $detected;
            }
        }
        unset($product);
    } else {
        $message = 'Products table does not exist. Please run setup_admin_complete.php first.';
        $messageType = 'danger';
    }
} catch (PDOException $e) {
    error_log("Products fetch error: " . $e->getMessage());
    $message = 'Error loading products. Please run setup_admin_complete.php to create the table.';
    $messageType = 'danger';
}

$pageTitle = 'Products Management';
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
    $pageTitle = 'Products';
    include_once __DIR__ . '/../../includes/admin_header.php'; 
    ?>
        <div class="system-status">
            <span style="width: 10px; height: 10px; background: white; border-radius: 50%; display: inline-block;"></span>
            <span>Operational</span>
        </div>
        <div style="display: flex; align-items: center; gap: 20px;">
            <div class="notification-bell">
                <i class="fas fa-bell"></i>
                <?php if($stats['pending_orders'] > 0): ?>
                <span class="notification-badge"><?php echo $stats['pending_orders']; ?></span>
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
        <h2 style="margin-bottom: 30px; color: #2c3e50;">Products Management</h2>
        
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
            <?php if ($messageType === 'danger' && strpos($message, 'setup') !== false): ?>
            <br><br>
            <strong>Setup Instructions:</strong>
            <ol style="margin-top: 10px;">
                <li>Open in browser: <a href="<?php echo BASE_URL; ?>/public/setup_admin_complete.php" style="color: #3498DB; text-decoration: underline;"><?php echo BASE_URL; ?>/public/setup_admin_complete.php</a></li>
                <li>This will create the products table and insert sample data</li>
                <li>Refresh this page after setup is complete</li>
            </ol>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['total_products']); ?></div>
                <div class="stat-label">Total Products</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['active_products']); ?></div>
                <div class="stat-label">Active Products</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['categories']); ?></div>
                <div class="stat-label">Categories</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">ETB <?php echo number_format($stats['avg_price'], 0); ?></div>
                <div class="stat-label">Avg Price</div>
            </div>
        </div>

        <!-- Products Table -->
        <div class="section-card">
            <div class="section-header">
                <div class="section-title"><i class="fas fa-couch me-2"></i>All Products</div>
                <button class="btn-action btn-success-custom" onclick="showAddModal()">
                    <i class="fas fa-plus me-1"></i>Add New Product
                </button>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px;">
                <input type="text" id="prodSearch" placeholder="Search by name or description..."
                    style="flex:1; min-width:200px; padding:9px 14px; border:1px solid #ddd; border-radius:8px; font-size:13px; outline:none;">
                <select id="catFilter" style="padding:9px 14px; border:1px solid #ddd; border-radius:8px; font-size:13px; outline:none;">
                    <option value="">All Categories</option>
                    <option value="Table">Table</option>
                    <option value="Chair">Chair</option>
                    <option value="Bed">Bed</option>
                    <option value="Sofa">Sofa</option>
                    <option value="Desk">Desk</option>
                    <option value="Shelf">Shelf</option>
                    <option value="Other">Other</option>
                </select>
                <button onclick="document.getElementById('prodSearch').value=''; document.getElementById('catFilter').value=''; filterProducts();"
                    style="padding:9px 16px; background:#f0f0f0; border:1px solid #ddd; border-radius:8px; font-size:13px; cursor:pointer;">
                    <i class="fas fa-times"></i> Clear
                </button>
                <span id="prodCount" style="padding:9px 0; font-size:13px; color:#7f8c8d; align-self:center;"></span>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Base Price</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="prodBody">
                        <?php if (empty($products)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: #7f8c8d;">
                                No products found. Click "Add New Product" to create one.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?php echo $product['id'] ?? 'N/A'; ?></td>
                            <td><strong><?php echo htmlspecialchars($product['name'] ?? 'N/A'); ?></strong></td>
                            <td><span class="category-badge" data-category="<?php echo htmlspecialchars($product['category'] ?? ''); ?>"><?php echo htmlspecialchars($product['category'] ?: '—'); ?></span></td>
                            <td><?php echo htmlspecialchars(substr($product['description'] ?? '', 0, 50)) . (strlen($product['description'] ?? '') > 50 ? '...' : ''); ?></td>
                            <td><strong>ETB <?php echo number_format($product['base_price'] ?? 0, 2); ?></strong></td>
                            <td><?php echo date('M j, Y', strtotime($product['created_at'])); ?></td>
                            <td>
                                <button class="btn-action btn-primary-custom" onclick='editProduct(<?php echo json_encode($product); ?>)'>
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this product?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id'] ?? ''; ?>">
                                    <button type="submit" class="btn-action btn-danger-custom">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div id="addModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; overflow-y: auto;">
        <div style="background: white; padding: 30px; border-radius: 12px; width: 90%; max-width: 600px; margin: 20px;">
            <h3 style="margin-bottom: 20px;">Add New Product</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>Product Name *</label>
                    <input type="text" name="product_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Category *</label>
                    <select name="category" class="form-control" required>
                        <option value="">Select Category</option>
                        <option value="Table">Table</option>
                        <option value="Chair">Chair</option>
                        <option value="Bed">Bed</option>
                        <option value="Sofa">Sofa</option>
                        <option value="Desk">Desk</option>
                        <option value="Shelf">Shelf</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Base Price (ETB) *</label>
                    <input type="number" name="base_price" class="form-control" step="0.01" min="0" required>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" onclick="closeAddModal()" style="padding: 10px 20px; border: 1px solid #ddd; background: white; border-radius: 6px; cursor: pointer;">Cancel</button>
                    <button type="submit" class="btn-action btn-success-custom" style="padding: 10px 20px;">Add Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div id="editModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; overflow-y: auto;">
        <div style="background: white; padding: 30px; border-radius: 12px; width: 90%; max-width: 600px; margin: 20px;">
            <h3 style="margin-bottom: 20px;">Edit Product</h3>
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? (($_SESSION['csrf_token'] = bin2hex(random_bytes(32))) ? $_SESSION['csrf_token'] : bin2hex(random_bytes(32)))); ?>">
                <input type="hidden" name="product_id" id="edit_product_id">
                <div class="form-group">
                    <label>Product Name *</label>
                    <input type="text" name="product_name" id="edit_product_name" class="form-control" required>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? (($_SESSION['csrf_token'] = bin2hex(random_bytes(32))) ? $_SESSION['csrf_token'] : bin2hex(random_bytes(32)))); ?>">
                </div>
                <div class="form-group">
                    <label>Category *</label>
                    <select name="category" id="edit_category" class="form-control" required>
                        <option value="">Select Category</option>
                        <option value="Table">Table</option>
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? (($_SESSION['csrf_token'] = bin2hex(random_bytes(32))) ? $_SESSION['csrf_token'] : bin2hex(random_bytes(32)))); ?>">
                        <option value="Chair">Chair</option>
                        <option value="Bed">Bed</option>
                        <option value="Sofa">Sofa</option>
                        <option value="Desk">Desk</option>
                        <option value="Shelf">Shelf</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Base Price (ETB) *</label>
                    <input type="number" name="base_price" id="edit_base_price" class="form-control" step="0.01" min="0" required>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" onclick="closeEditModal()" style="padding: 10px 20px; border: 1px solid #ddd; background: white; border-radius: 6px; cursor: pointer;">Cancel</button>
                    <button type="submit" class="btn-action btn-success-custom" style="padding: 10px 20px;">Update Product</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Products search + category filter
        function filterProducts() {
            const q      = document.getElementById('prodSearch').value.toLowerCase().trim();
            const cat    = document.getElementById('catFilter').value;   // exact case e.g. "Sofa"
            const rows   = document.getElementById('prodBody').querySelectorAll('tr');
            let visible  = 0;
            rows.forEach(row => {
                const text    = row.textContent.toLowerCase();
                const badge   = row.querySelector('[data-category]');
                const rowCat  = badge ? badge.getAttribute('data-category') : '';
                const match   = (!q || text.includes(q)) && (!cat || rowCat === cat);
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            document.getElementById('prodCount').textContent = visible + ' of ' + rows.length + ' products';
        }
        document.getElementById('prodSearch').addEventListener('input', filterProducts);
        document.getElementById('catFilter').addEventListener('change', filterProducts);
        filterProducts();

        function showAddModal() {
            document.getElementById('addModal').style.display = 'flex';
        }
        
        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }
        
        function editProduct(product) {
            document.getElementById('edit_product_id').value = product.id;
            document.getElementById('edit_product_name').value = product.name;
            document.getElementById('edit_category').value = product.category;
            document.getElementById('edit_description').value = product.description;
            document.getElementById('edit_base_price').value = product.base_price;
            document.getElementById('editModal').style.display = 'flex';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
    </script>
    <!-- Mobile Menu Script -->
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
