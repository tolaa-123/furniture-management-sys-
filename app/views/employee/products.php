<?php
// Employee authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}

// Get database connection
require_once __DIR__ . '/../../../config/db_config.php';

$employeeName = $_SESSION['user_name'] ?? 'Employee User';
$employeeId = $_SESSION['user_id'];

// Get filter parameters
$filterCategory = $_GET['category'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Fetch products
$products = [];
try {
    $query = "
        SELECT p.*, c.name as category_name
        FROM furn_products p
        LEFT JOIN furn_categories c ON c.id = p.category_id
        WHERE p.is_active = 1
    ";
    
    $params = [];
    
    if ($filterCategory) {
        $query .= " AND p.category_id = (SELECT id FROM furn_categories WHERE LOWER(name) = ? LIMIT 1)";
        $params[] = strtolower($filterCategory);
    }
    
    if ($searchQuery) {
        $query .= " AND (p.name LIKE ? OR p.description LIKE ?)";
        $searchParam = "%$searchQuery%";
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $query .= " ORDER BY p.name";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Products query error: " . $e->getMessage());
    $products = [];
}

// Get categories for filter
$categories = [];
try {
    $stmt = $pdo->query("
        SELECT DISTINCT c.name FROM furn_categories c
        INNER JOIN furn_products p ON p.category_id = c.id AND p.is_active = 1
        ORDER BY c.name
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Error fetching categories: " . $e->getMessage());
}

$pageTitle = 'Products Catalog';
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

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay"></div>

    <?php include_once __DIR__ . '/../../includes/employee_sidebar.php'; ?>

    <!-- Top Header -->
    <?php 
    $pageTitle = 'Products';
    include_once __DIR__ . '/../../includes/employee_header.php'; 
    ?>
        <div class="header-left">
            <div class="system-status">
                <i class="fas fa-circle"></i> Products Catalog
            </div>
        </div>
        <div class="header-right">
            <div class="admin-profile">
                <div class="admin-avatar"><?php echo strtoupper(substr($employeeName, 0, 1)); ?></div>
                <div>
                    <div style="font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($employeeName); ?></div>
                    <div class="admin-role-badge" style="background: #27AE60;">EMPLOYEE</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));">
            <div class="stat-card" style="border-left: 4px solid #3498DB;">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <div>
                        <div class="stat-value"><?php echo count($products); ?></div>
                        <div class="stat-label">Total Products</div>
                    </div>
                    <div style="font-size: 32px; color: #3498DB;"><i class="fas fa-couch"></i></div>
                </div>
            </div>

            <div class="stat-card" style="border-left: 4px solid #27AE60;">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <div>
                        <div class="stat-value"><?php echo count($categories); ?></div>
                        <div class="stat-label">Categories</div>
                    </div>
                    <div style="font-size: 32px; color: #27AE60;"><i class="fas fa-th-large"></i></div>
                </div>
            </div>

            <div class="stat-card" style="border-left: 4px solid #F39C12;">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <div>
                        <div class="stat-value">
                            <?php 
                            $totalOrders = array_sum(array_column($products, 'order_count'));
                            echo $totalOrders;
                            ?>
                        </div>
                        <div class="stat-label">Total Orders</div>
                    </div>
                    <div style="font-size: 32px; color: #F39C12;"><i class="fas fa-shopping-cart"></i></div>
                </div>
            </div>
        </div>

        <!-- Products Section -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-couch"></i> Furniture Catalog</h2>
                <div style="background: #E8F5E9; padding: 8px 15px; border-radius: 6px; font-size: 13px; color: #2E7D32;">
                    <i class="fas fa-info-circle"></i> View Only
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filter-section" style="margin-bottom: 20px;">
                <form method="GET" action="" style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <div class="form-group" style="margin: 0; flex: 1; min-width: 200px;">
                        <label>Search Products</label>
                        <input type="text" name="search" class="form-control" placeholder="Product name, description..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                    </div>
                    
                    <div class="form-group" style="margin: 0; min-width: 200px;">
                        <label>Category</label>
                        <select name="category" class="form-control">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $filterCategory === $cat ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div style="display: flex; align-items: flex-end; gap: 10px;">
                        <button type="submit" class="btn-action btn-primary-custom">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <?php if ($filterCategory || $searchQuery): ?>
                        <a href="<?php echo BASE_URL; ?>/public/employee/products" class="btn-action btn-secondary-custom">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <?php if (empty($products)): ?>
                <p style="text-align: center; color: #7f8c8d; padding: 40px;">No products found.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table mobile-cards">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Material</th>
                                <th>Standard Size</th>
                                <th>Price (ETB)</th>
                                <th>Orders</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td>
                                        <?php 
                                        $imgSrc = '';
                                        if (!empty($product['image_main'])) {
                                            // Paths starting with uploads/ are finished product images
                                            if (strpos($product['image_main'], 'uploads/') === 0) {
                                                $imgSrc = BASE_URL . '/public/' . $product['image_main'];
                                            } else {
                                                $imgSrc = BASE_URL . '/public/assets/images/products/' . $product['image_main'];
                                            }
                                        }
                                        ?>
                                        <?php if ($imgSrc): ?>
                                            <img src="<?php echo htmlspecialchars($imgSrc); ?>"
                                                 alt="<?php echo htmlspecialchars($product['product_name'] ?? ''); ?>"
                                                 style="width:48px; height:48px; object-fit:cover; border-radius:6px; border:1px solid #e0e0e0;"
                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                            <div style="display:none; width:48px; height:48px; background:#f0f0f0; border-radius:6px; align-items:center; justify-content:center; color:#bbb;">
                                                <i class="fas fa-couch"></i>
                                            </div>
                                        <?php else: ?>
                                            <div style="width:48px; height:48px; background:#f0f0f0; border-radius:6px; display:flex; align-items:center; justify-content:center; color:#bbb;">
                                                <i class="fas fa-couch"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($product['name'] ?? 'Product'); ?></strong></td>
                                    <td>
                                        <span class="badge badge-info">
                                            <?php echo htmlspecialchars($product['category_name'] ?? $product['category'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['materials_used'] ?? 'Various'); ?></td>
                                    <td><?php echo htmlspecialchars($product['dimensions'] ?? 'Custom'); ?></td>
                                    <td><strong>ETB <?php echo number_format($product['base_price'] ?? 0, 2); ?></strong></td>
                                    <td><?php echo $product['order_count'] ?? 0; ?></td>
                                    <td>
                                        <button class="btn-action btn-primary-custom" onclick="viewProductDetails(<?php echo htmlspecialchars(json_encode($product), ENT_QUOTES); ?>)" title="View Details">
                                            <i class="fas fa-eye"></i>
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

    <!-- Product Details Modal -->
    <div id="productDetailsModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h3><i class="fas fa-couch"></i> Product Details</h3>
                <span class="close" onclick="closeProductDetailsModal()">&times;</span>
            </div>
            <div id="product_details_content"></div>
            <div class="modal-footer">
                <button type="button" class="btn-action btn-secondary-custom" onclick="closeProductDetailsModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        function viewProductDetails(product) {
            const name = product.name || 'Product';
            const category = product.category_name || product.category || 'N/A';
            const material = product.materials_used || 'Various';
            const dimensions = product.dimensions || 'Custom';
            const price = parseFloat(product.base_price || 0).toFixed(2);
            const imgPath = product.image_main
                ? (product.image_main.startsWith('uploads/') ? '<?php echo BASE_URL; ?>/public/' + product.image_main : '<?php echo BASE_URL; ?>/public/assets/images/products/' + product.image_main)
                : '';
            const content = `
                <div style="padding: 20px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <div><strong>Product Name:</strong><br>${name}</div>
                        <div><strong>Category:</strong><br><span class="badge badge-info">${category}</span></div>
                        <div><strong>Material:</strong><br>${material}</div>
                        <div><strong>Dimensions:</strong><br>${dimensions}</div>
                        <div><strong>Price:</strong><br><strong style="color:#27AE60;font-size:18px;">ETB ${price}</strong></div>
                    </div>
                    ${product.description ? `<div style="margin-top:20px;padding:15px;background:#F8F9FA;border-radius:8px;"><strong>Description:</strong><br><p style="margin:10px 0 0;color:#495057;">${product.description}</p></div>` : ''}
                    ${imgPath ? `<div style="margin-top:20px;"><strong>Product Image:</strong><br><img src="${imgPath}" alt="${name}" style="max-width:100%;max-height:250px;border-radius:8px;margin-top:10px;object-fit:cover;" onerror="this.style.display='none'"></div>` : ''}
                    <div style="margin-top:20px;padding:15px;background:#FFF3CD;border-radius:8px;border:1px solid #FFE69C;">
                        <p style="margin:0;color:#856404;font-size:14px;"><i class="fas fa-info-circle"></i> This is a view-only catalog.</p>
                    </div>
                </div>
            `;
            document.getElementById('product_details_content').innerHTML = content;
            document.getElementById('productDetailsModal').style.display = 'block';
        }

        function closeProductDetailsModal() {
            document.getElementById('productDetailsModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('productDetailsModal');
            if (event.target === modal) {
                closeProductDetailsModal();
            }
        }
    </script>

    <!-- Mobile Menu Script -->
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
