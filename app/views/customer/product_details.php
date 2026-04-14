<?php
// Customer authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}

require_once __DIR__ . '/../../../config/db_config.php';

$customerName = $_SESSION['user_name'] ?? 'Customer';
$customerId = $_SESSION['user_id'];

// Get product ID
$productId = $_GET['id'] ?? 0;

// Fetch product details
try {
    $stmt = $pdo->prepare("SELECT * FROM furn_products WHERE product_id = ? AND status = 'active'");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        header('Location: ' . BASE_URL . '/public/customer/gallery/sofas');
        exit();
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Get related products (same category)
$relatedStmt = $pdo->prepare("SELECT * FROM furn_products WHERE category = ? AND product_id != ? AND status = 'active' LIMIT 4");
$relatedStmt->execute([$product['category'], $productId]);
$relatedProducts = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = $product['product_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Product Details</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/css/admin-responsive.css">
    <style>
        .product-detail-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }
        
        .product-image-section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .main-product-image {
            width: 100%;
            height: 500px;
            background: #f5f5f5;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .main-product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .main-product-image .placeholder {
            font-size: 120px;
            color: #ddd;
        }
        
        .product-info-section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .product-category-badge {
            display: inline-block;
            background: #D4A574;
            color: white;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 15px;
        }
        
        .product-title {
            font-size: 32px;
            font-weight: 700;
            color: #333;
            margin: 0 0 20px 0;
        }
        
        .product-price-large {
            font-size: 42px;
            font-weight: 700;
            color: #4A2C2A;
            margin-bottom: 30px;
        }
        
        .product-specs {
            border-top: 2px solid #f0f0f0;
            border-bottom: 2px solid #f0f0f0;
            padding: 25px 0;
            margin-bottom: 30px;
        }
        
        .spec-item {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .spec-item:last-child {
            margin-bottom: 0;
        }
        
        .spec-icon {
            width: 40px;
            height: 40px;
            background: #4A2C2A;
            color: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        
        .spec-content {
            flex: 1;
        }
        
        .spec-label {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .spec-value {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }
        
        .product-description-full {
            font-size: 16px;
            line-height: 1.8;
            color: #666;
            margin-bottom: 30px;
        }
        
        .btn-order-large {
            width: 100%;
            padding: 18px;
            background: #4A2C2A;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-order-large:hover {
            background: #3D1F14;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.2);
        }
        
        .related-products-section {
            margin-top: 60px;
        }
        
        .section-title {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .related-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .related-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }
        
        .related-image {
            width: 100%;
            height: 200px;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .related-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .related-info {
            padding: 15px;
        }
        
        .related-name {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .related-price {
            font-size: 18px;
            font-weight: 700;
            color: #4A2C2A;
        }
        
        @media (max-width: 968px) {
            .product-detail-container {
                grid-template-columns: 1fr;
            }
            
            .main-product-image {
                height: 400px;
            }
            
            .product-title {
                font-size: 24px;
            }
            
            .product-price-large {
                font-size: 32px;
            }
        }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle" aria-label="Toggle Menu">
        <i class="fas fa-bars"></i>
    </button>
    <div class="sidebar-overlay"></div>
    
    <?php include_once __DIR__ . '/../../includes/customer_sidebar.php'; ?>

    <div class="top-header">
        <div class="header-left">
            <div class="system-status">
                <i class="fas fa-box-open"></i> Product Details
            </div>
        </div>
        <div class="header-right">
            <div class="admin-profile">
                <div class="admin-avatar"><?php echo strtoupper(substr($customerName, 0, 1)); ?></div>
                <div>
                    <div style="font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($customerName); ?></div>
                    <div class="admin-role-badge" style="background: #27AE60;">CUSTOMER</div>
                </div>
            </div>
        </div>
    </div>

    <div class="main-content">
        <!-- Breadcrumb -->
        <div style="margin-bottom: 20px;">
            <a href="<?php echo BASE_URL; ?>/public/customer/gallery/<?php echo $product['category']; ?>" style="color: #4A2C2A; text-decoration: none;">
                <i class="fas fa-arrow-left"></i> Back to <?php echo ucfirst($product['category']); ?> Gallery
            </a>
        </div>

        <!-- Product Details -->
        <div class="product-detail-container">
            <!-- Image Section -->
            <div class="product-image-section">
                <div class="main-product-image">
                    <?php if ($product['image_main']): ?>
                        <img src="<?php echo BASE_URL; ?>/public/assets/images/products/<?php echo htmlspecialchars($product['image_main']); ?>" 
                             alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                    <?php else: ?>
                        <div class="placeholder">
                            <i class="fas fa-couch"></i>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Info Section -->
            <div class="product-info-section">
                <span class="product-category-badge">
                    <i class="fas fa-tag"></i> <?php echo ucfirst($product['category']); ?>
                </span>
                
                <h1 class="product-title"><?php echo htmlspecialchars($product['product_name']); ?></h1>
                
                <div class="product-price-large">
                    ETB <?php echo number_format($product['estimated_price'], 2); ?>
                </div>
                
                <div class="product-specs">
                    <div class="spec-item">
                        <div class="spec-icon">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <div class="spec-content">
                            <div class="spec-label">Material</div>
                            <div class="spec-value"><?php echo htmlspecialchars($product['material']); ?></div>
                        </div>
                    </div>
                    
                    <div class="spec-item">
                        <div class="spec-icon">
                            <i class="fas fa-ruler-combined"></i>
                        </div>
                        <div class="spec-content">
                            <div class="spec-label">Dimensions</div>
                            <div class="spec-value"><?php echo htmlspecialchars($product['dimensions']); ?></div>
                        </div>
                    </div>
                    
                    <div class="spec-item">
                        <div class="spec-icon">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <div class="spec-content">
                            <div class="spec-label">Category</div>
                            <div class="spec-value"><?php echo ucfirst($product['category']); ?> Furniture</div>
                        </div>
                    </div>
                </div>
                
                <div class="product-description-full">
                    <h3 style="margin-bottom: 15px; color: #333;">Description</h3>
                    <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                </div>
                
                <button class="btn-order-large" onclick="orderProduct(<?php echo $product['product_id']; ?>)">
                    <i class="fas fa-shopping-cart"></i>
                    Order This Design
                </button>
                
                <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; font-size: 14px; color: #666;">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Note:</strong> This is an estimated price. Final cost will be calculated by our manager based on your specific requirements.
                </div>
            </div>
        </div>

        <!-- Related Products -->
        <?php if (!empty($relatedProducts)): ?>
            <div class="related-products-section">
                <h2 class="section-title">
                    <i class="fas fa-th-large"></i>
                    Related Products
                </h2>
                
                <div class="related-grid">
                    <?php foreach ($relatedProducts as $related): ?>
                        <div class="related-card" onclick="viewProduct(<?php echo $related['product_id']; ?>)">
                            <div class="related-image">
                                <?php if ($related['image_main']): ?>
                                    <img src="<?php echo BASE_URL; ?>/public/assets/images/products/<?php echo htmlspecialchars($related['image_main']); ?>" 
                                         alt="<?php echo htmlspecialchars($related['product_name']); ?>"
                                         loading="lazy">
                                <?php else: ?>
                                    <i class="fas fa-couch" style="font-size: 48px; color: #ddd;"></i>
                                <?php endif; ?>
                            </div>
                            <div class="related-info">
                                <div class="related-name"><?php echo htmlspecialchars($related['product_name']); ?></div>
                                <div class="related-price">ETB <?php echo number_format($related['estimated_price'], 2); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function viewProduct(productId) {
            window.location.href = '<?php echo BASE_URL; ?>/public/customer/product_details?id=' + productId;
        }
        
        function orderProduct(productId) {
            window.location.href = '<?php echo BASE_URL; ?>/public/customer/create-order?product_id=' + productId;
        }
    </script>

    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
