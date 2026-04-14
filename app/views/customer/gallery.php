<?php
// Customer authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}

require_once __DIR__ . '/../../../config/db_config.php';

$customerName = $_SESSION['first_name'] ?? $_SESSION['user_name'] ?? 'Customer';
$customerId = $_SESSION['user_id'];

// Get category from URL — sidebar links use plural (sofas, chairs…), normalise to singular
$categoryRaw = $_GET['category'] ?? 'sofa';
$pluralMap = [
    'sofas'     => 'sofa',
    'chairs'    => 'chair',
    'beds'      => 'bed',
    'tables'    => 'table',
    'cabinets'  => 'cabinet',
    'wardrobes' => 'wardrobe',
    'shelves'   => 'shelf',
    'office'    => 'office',
    'customs'   => 'custom',
];
$category = $pluralMap[$categoryRaw] ?? $categoryRaw;

$validCategories = ['sofa', 'chair', 'bed', 'table', 'cabinet', 'wardrobe', 'shelf', 'office', 'custom'];
if (!in_array($category, $validCategories)) {
    $category = 'sofa';
}

// Category display names
$categoryNames = [
    'sofa' => 'Sofas',
    'chair' => 'Chairs',
    'bed' => 'Beds',
    'table' => 'Tables',
    'cabinet' => 'Cabinets',
    'wardrobe' => 'Wardrobes',
    'shelf' => 'Shelves',
    'office' => 'Office Furniture',
    'custom' => 'Custom Designs'
];

$pageTitle = $categoryNames[$category] ?? 'Gallery';

// Get filter parameters
$searchQuery = $_GET['search'] ?? '';
$materialFilter = $_GET['material'] ?? '';
$priceMin = $_GET['price_min'] ?? '';
$priceMax = $_GET['price_max'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 12;
$offset = ($page - 1) * $perPage;

// Build query - map category name to category_id via furn_categories
$catStmt = $pdo->prepare("SELECT id FROM furn_categories WHERE LOWER(name) = ? LIMIT 1");
$catStmt->execute([strtolower($category)]);
$catRow = $catStmt->fetch(PDO::FETCH_ASSOC);
// If not found, try with first letter capitalised (e.g. "Sofa")
if (!$catRow) {
    $catStmt->execute([ucfirst($category)]);
    $catRow = $catStmt->fetch(PDO::FETCH_ASSOC);
}
$categoryId = $catRow ? $catRow['id'] : 0;

$query = "SELECT * FROM furn_products WHERE category_id = ? AND is_active = 1";
$params = [$categoryId];

if ($searchQuery) {
    $query .= " AND (name LIKE ? OR description LIKE ?)";
    $searchTerm = "%$searchQuery%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($materialFilter) {
    $query .= " AND materials_used LIKE ?";
    $params[] = "%$materialFilter%";
}

if ($priceMin !== '') {
    $query .= " AND base_price >= ?";
    $params[] = $priceMin;
}

if ($priceMax !== '') {
    $query .= " AND base_price <= ?";
    $params[] = $priceMax;
}

// Get total count
$countQuery = str_replace('SELECT *', 'SELECT COUNT(*)', $query);
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalProducts = $countStmt->fetchColumn();
$totalPages = ceil($totalProducts / $perPage);

// Get products
$query .= " ORDER BY created_at DESC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available materials for filter
$materialsStmt = $pdo->prepare("SELECT DISTINCT materials_used FROM furn_products WHERE category_id = ? AND is_active = 1 ORDER BY materials_used");
$materialsStmt->execute([$categoryId]);
$materials = $materialsStmt->fetchAll(PDO::FETCH_COLUMN);

// Ensure wishlist table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS furn_wishlist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_wishlist (customer_id, product_id),
    KEY idx_customer (customer_id),
    KEY idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Get wishlist items for current customer
$wishlistStmt = $pdo->prepare("SELECT product_id FROM furn_wishlist WHERE customer_id = ?");
$wishlistStmt->execute([$customerId]);
$wishlistProducts = $wishlistStmt->fetchAll(PDO::FETCH_COLUMN);
$wishlistIds = array_flip($wishlistProducts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Inspiration Gallery</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/css/admin-responsive.css">
    <style>
        /* Gallery Specific Styles */
        .gallery-header {
            background: linear-gradient(135deg, #4A2C2A 0%, #3D1F14 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .gallery-header h1 {
            margin: 0 0 10px 0;
            font-size: 32px;
        }
        
        .gallery-header p {
            margin: 0;
            opacity: 0.9;
        }
        
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #4A2C2A;
        }
        
        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-family: 'Poppins', sans-serif;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #4A2C2A;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .product-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.2);
        }
        
        .product-image {
            width: 100%;
            height: 250px;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .product-card:hover .product-image img {
            transform: scale(1.1);
        }
        
        .product-image .placeholder {
            font-size: 64px;
            color: #ddd;
        }
        
        .product-info {
            padding: 20px;
        }
        
        .product-category {
            display: inline-block;
            background: #D4A574;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 10px;
        }
        
        .product-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin: 0 0 10px 0;
        }
        
        .product-details {
            font-size: 13px;
            color: #666;
            margin-bottom: 15px;
        }
        
        .product-details div {
            margin-bottom: 5px;
        }
        
        .product-details i {
            width: 20px;
            color: #4A2C2A;
        }
        
        .product-price {
            font-size: 24px;
            font-weight: 700;
            color: #4A2C2A;
            margin-bottom: 15px;
        }
        
        .product-description {
            font-size: 14px;
            color: #666;
            margin-bottom: 15px;
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .btn-order {
            width: 100%;
            padding: 12px;
            background: #4A2C2A;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
        }
        
        .btn-order:hover {
            background: #3D1F14;
            transform: translateY(-2px);
        }
        
        .btn-wishlist {
            width: 100%;
            padding: 12px;
            background: #D4A574;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
            margin-top: 10px;
        }
        
        .btn-wishlist:hover {
            background: #C49564;
            transform: translateY(-2px);
        }
        
        .btn-wishlist.in-wishlist {
            background: #E74C3C;
        }
        
        .btn-wishlist.in-wishlist:hover {
            background: #C0392B;
        }
        
        .no-products {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
        }
        
        .no-products i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }
        
        .pagination a,
        .pagination span {
            padding: 10px 15px;
            background: white;
            border: 2px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
        }
        
        .pagination a:hover {
            background: #4A2C2A;
            color: white;
            border-color: #4A2C2A;
        }
        
        .pagination .active {
            background: #4A2C2A;
            color: white;
            border-color: #4A2C2A;
        }
        
        @media (max-width: 768px) {
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
                gap: 15px;
            }
            
            .filter-row {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Include original customer sidebar -->
    <?php include_once __DIR__ . '/../../includes/customer_sidebar.php'; ?>

    <!-- Top Header -->
    <?php 
    $pageTitle = 'Inspiration Gallery';
    include_once __DIR__ . '/../../includes/customer_header.php'; 
    ?>

    <!-- Main Content -->
    <div class="main-content" style="padding: 30px;">
        <!-- Gallery Header -->
        <div class="gallery-header">
            <h1><i class="fas fa-images me-2"></i><?php echo $pageTitle; ?></h1>
            <p>Browse our collection of beautiful furniture designs and get inspired</p>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="">
                <input type="hidden" name="category" value="<?php echo $category; ?>">
                <div class="filter-row">
                    <div class="filter-group">
                        <label><i class="fas fa-search"></i> Search</label>
                        <input type="text" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-layer-group"></i> Material</label>
                        <select name="material">
                            <option value="">All Materials</option>
                            <?php foreach ($materials as $mat): ?>
                                <option value="<?php echo htmlspecialchars($mat); ?>" <?php echo $materialFilter === $mat ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($mat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-dollar-sign"></i> Min Price (ETB)</label>
                        <input type="number" name="price_min" placeholder="Min" value="<?php echo htmlspecialchars($priceMin); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-dollar-sign"></i> Max Price (ETB)</label>
                        <input type="number" name="price_max" placeholder="Max" value="<?php echo htmlspecialchars($priceMax); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <button type="submit" class="btn-order" style="margin: 0;">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                    </div>
                    
                    <div class="filter-group">
                        <a href="?category=<?php echo $category; ?>" class="btn-order" style="margin: 0; text-align: center; display: block; padding: 12px; text-decoration: none;">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Products Grid -->
        <?php if (empty($products)): ?>
            <div class="no-products">
                <i class="fas fa-box-open"></i>
                <h3>No Products Found</h3>
                <p>Try adjusting your filters or check back later for new designs.</p>
            </div>
        <?php else: ?>
            <div class="products-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <?php 
                            $imgSrc = '';
                            if (!empty($product['image_main'])) {
                                $img = $product['image_main'];
                                if (strpos($img, 'http') === 0) {
                                    $imgSrc = $img;
                                } elseif (strpos($img, 'uploads/') === 0 || strpos($img, 'assets/') === 0) {
                                    $imgSrc = BASE_URL . '/public/' . $img;
                                } else {
                                    $imgSrc = BASE_URL . '/public/assets/images/products/' . $img;
                                }
                            }
                            ?>
                            <?php if ($imgSrc): ?>
                                <img src="<?php echo htmlspecialchars($imgSrc); ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                                     loading="lazy"
                                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                <div class="placeholder" style="display:none;"><i class="fas fa-couch"></i></div>
                            <?php else: ?>
                                <div class="placeholder">
                                    <i class="fas fa-couch"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="product-info">
                            <span class="product-category"><?php echo ucfirst($category); ?></span>
                            <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <div class="product-details">
                                <div><i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($product['materials_used'] ?? ''); ?></div>
                            </div>
                            <div class="product-price">ETB <?php echo number_format($product['base_price'], 2); ?></div>
                            <p class="product-description"><?php echo htmlspecialchars($product['description'] ?? ''); ?></p>
                            <button class="btn-order" onclick="orderProduct(<?php echo htmlspecialchars(json_encode([
                                'product_id'      => $product['id'],
                                'product_name'    => $product['name'],
                                'category'        => $category,
                                'material'        => $product['materials_used'] ?? '',
                                'dimensions'      => '',
                                'description'     => $product['description'] ?? '',
                                'color'           => '',
                                'estimated_price' => $product['base_price'],
                                'image_url'       => '',
                            ])); ?>)">
                                <i class="fas fa-shopping-cart"></i> Order This Design
                            </button>
                            <button class="btn-wishlist" onclick="toggleWishlist(<?php echo $product['id']; ?>, this)" title="Add to Wishlist">
                                <i class="fas fa-heart"></i> Add to Wishlist
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?category=<?php echo $category; ?>&page=<?php echo $page - 1; ?>&search=<?php echo urlencode($searchQuery); ?>&material=<?php echo urlencode($materialFilter); ?>&price_min=<?php echo $priceMin; ?>&price_max=<?php echo $priceMax; ?>">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?category=<?php echo $category; ?>&page=<?php echo $i; ?>&search=<?php echo urlencode($searchQuery); ?>&material=<?php echo urlencode($materialFilter); ?>&price_min=<?php echo $priceMin; ?>&price_max=<?php echo $priceMax; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?category=<?php echo $category; ?>&page=<?php echo $page + 1; ?>&search=<?php echo urlencode($searchQuery); ?>&material=<?php echo urlencode($materialFilter); ?>&price_min=<?php echo $priceMin; ?>&price_max=<?php echo $priceMax; ?>">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        function orderProduct(product) {
            const params = new URLSearchParams({
                product_id:      product.product_id,
                product_name:    product.product_name,
                category:        product.category,
                material:        product.material,
                dimensions:      product.dimensions,
                description:     product.description,
                color:           product.color || '',
                estimated_price: product.estimated_price,
                image_url:       product.image_url || ''
            });
            window.location.href = '<?php echo BASE_URL; ?>/public/customer/create-order?' + params.toString();
        }
        
        function toggleWishlist(productId, button) {
            const formData = new FormData();
            formData.append('product_id', productId);
            
            fetch('<?php echo BASE_URL; ?>/public/api/add_to_wishlist.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    button.classList.toggle('in-wishlist');
                    if (data.action === 'added') {
                        button.style.background = '#E74C3C';
                        showToast('✓ Added to wishlist!', 'success');
                    } else {
                        button.style.background = '#D4A574';
                        showToast('✓ Removed from wishlist!', 'info');
                    }
                } else {
                    showToast(data.message || 'Error updating wishlist', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error updating wishlist', 'error');
            });
        }
        
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                background: ${type === 'success' ? '#27AE60' : type === 'error' ? '#E74C3C' : '#3498DB'};
                color: white;
                border-radius: 8px;
                z-index: 9999;
                animation: slideIn 0.3s ease;
                font-weight: 500;
            `;
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
    </script>
    
    <style>
        @keyframes slideIn {
            from { transform: translateX(400px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(400px); opacity: 0; }
        }
    </style>
</body>
</html>
