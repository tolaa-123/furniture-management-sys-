<?php
// Session and authentication already handled by index.php
$customerId = $_SESSION['user_id'] ?? 0;
$customerName = $_SESSION['first_name'] ?? $_SESSION['user_name'] ?? 'Customer';

// Get database connection
require_once __DIR__ . '/../../../config/db_config.php';

// Get wishlist items from database
try {
    $stmt = $pdo->prepare("
        SELECT 
            w.id as wishlist_id,
            p.id as product_id,
            p.name as product_name,
            p.description,
            p.base_price as estimated_price,
            p.materials_used as material,
            p.image_main,
            w.created_at as added_at
        FROM furn_wishlist w
        JOIN furn_products p ON w.product_id = p.id
        WHERE w.customer_id = ? AND p.is_active = 1
        ORDER BY w.created_at DESC
    ");
    $stmt->execute([$customerId]);
    $wishlistItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Wishlist query error: " . $e->getMessage());
    $wishlistItems = [];
}

$pageTitle = 'My Wishlist';
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .wishlist-header { background: linear-gradient(135deg, #8B4513, #5D4037); color: #fff; border-radius: 12px; padding: 20px; margin-bottom: 25px; box-shadow: 0 8px 25px rgba(139, 69, 19, 0.2); }
        .wishlist-item { background: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); transition: all 0.3s; }
        .wishlist-item:hover { transform: translateY(-5px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .item-image { width: 120px; height: 120px; object-fit: cover; border-radius: 8px; background: #f8f4f0; padding: 10px; }
        .item-name { font-size: 1.25rem; font-weight: 600; color: #4a2c2a; margin-bottom: 5px; }
        .item-type { color: #6c757d; font-size: 0.9rem; margin-bottom: 10px; }
        .item-description { color: #495057; margin-bottom: 10px; }
        .item-price { font-size: 1.5rem; font-weight: 700; color: #8B4513; }
        .item-actions { display: flex; gap: 10px; align-items: center; }
        .btn-order { background: #8B4513; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; transition: all 0.3s; }
        .btn-order:hover { background: #5D4037; color: white; transform: translateY(-2px); }
        .btn-remove { background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 8px; transition: all 0.3s; }
        .btn-remove:hover { background: #c82333; }
        .empty-state { text-align: center; padding: 60px 20px; color: #6c757d; background: white; border-radius: 12px; }
        .empty-state i { font-size: 4rem; color: #dee2e6; margin-bottom: 20px; }
        .stats-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); transition: transform 0.3s; }
        .stats-card:hover { transform: translateY(-5px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle" aria-label="Toggle Menu">
        <i class="fas fa-bars"></i>
    </button>
    <div class="sidebar-overlay"></div>

    <?php include_once __DIR__ . '/../../includes/customer_sidebar.php'; ?>

    <!-- Top Header -->
    <?php 
    $pageTitle = 'My Wishlist';
    include_once __DIR__ . '/../../includes/customer_header.php'; 
    ?>
        <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
            <span style="font-size: 20px;">🔨</span>
            <span style="font-weight: 700; font-size: 16px; color: white;"><span style="color: #e67e22;">Smart</span>Workshop</span>
            <span style="color: rgba(255,255,255,0.4); margin: 0 5px;">|</span>
            <span style="font-size: 14px; color: rgba(255,255,255,0.85);">Furniture ERP &nbsp;<strong style="color:white;">My Wishlist</strong></span>
        </div>
        <div class="header-right" style="display: flex; align-items: center; gap: 15px;">
            <div class="system-status" style="background: #27AE60; color: white; padding: 5px 12px; border-radius: 20px; font-size: 12px; display: flex; align-items: center; gap: 6px;">
                <span style="width: 8px; height: 8px; background: white; border-radius: 50%; display: inline-block;"></span> Operational
            </div>
            <div style="position: relative; cursor: pointer;">
                <i class="fas fa-bell" style="font-size: 18px; color: rgba(255,255,255,0.85);"></i>
            </div>
            <div class="admin-profile" style="display: flex; align-items: center; gap: 10px;">
                <div class="admin-avatar" style="background: #9B59B6;"><?php echo strtoupper(substr($customerName, 0, 1)); ?></div>
                <div>
                    <div style="font-size: 13px; font-weight: 600; color: white;"><?php echo htmlspecialchars($customerName); ?></div>
                    <div class="admin-role-badge" style="background: #9B59B6;">CUSTOMER</div>
                </div>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="wishlist-header d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h2 mb-1"><i class="fas fa-heart me-2"></i>My Wishlist</h1>
                <div class="opacity-75">Save your favorite furniture designs for later</div>
            </div>
            <a href="<?php echo BASE_URL; ?>/public/furniture" class="btn btn-light">
                <i class="fas fa-search me-2"></i>Browse Furniture
            </a>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-danger bg-opacity-10 p-3">
                                <i class="fas fa-heart text-danger fa-2x"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="text-muted small">Saved Items</div>
                            <div class="h3 mb-0"><?php echo count($wishlistItems); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-success bg-opacity-10 p-3">
                                <i class="fas fa-tag text-success fa-2x"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="text-muted small">Total Value</div>
                            <div class="h3 mb-0">ETB <?php echo number_format(array_sum(array_column($wishlistItems, 'estimated_price')), 0); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-info bg-opacity-10 p-3">
                                <i class="fas fa-clock text-info fa-2x"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <div class="text-muted small">Recently Added</div>
                            <div class="h3 mb-0"><?php echo !empty($wishlistItems) ? date('M j', strtotime($wishlistItems[0]['added_at'])) : 'N/A'; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Wishlist Items -->
        <?php if (empty($wishlistItems)): ?>
            <div class="empty-state">
                <i class="fas fa-heart"></i>
                <h4>Your Wishlist is Empty</h4>
                <p class="text-muted">Browse our furniture collection and save your favorites here!</p>
                <a href="<?php echo BASE_URL; ?>/public/customer/gallery" class="btn btn-primary btn-lg mt-3">
                    <i class="fas fa-search me-2"></i>Browse Gallery
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($wishlistItems as $item): ?>
                <div class="wishlist-item" id="wishlist-item-<?php echo $item['wishlist_id']; ?>">
                    <div class="row align-items-center">
                        <div class="col-md-2">
                            <?php
                            $wImg = $item['image_main'] ?? '';
                            if (!empty($wImg)) {
                                if (strpos($wImg, 'http') === 0) {
                                    $wImgSrc = $wImg;
                                } elseif (strpos($wImg, 'uploads/') === 0) {
                                    $wImgSrc = BASE_URL . '/public/' . $wImg;
                                } else {
                                    $wImgSrc = BASE_URL . '/public/assets/images/products/' . $wImg;
                                }
                            } else {
                                $wImgSrc = '';
                            }
                            ?>
                            <?php if ($wImgSrc): ?>
                                <img src="<?php echo htmlspecialchars($wImgSrc); ?>"
                                     alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                     class="item-image"
                                     style="width:120px;height:120px;object-fit:cover;border-radius:8px;"
                                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                <div class="item-image" style="display:none;align-items:center;justify-content:center;background:#f8f4f0;">
                                    <i class="fas fa-couch" style="font-size:2rem;color:#ccc;"></i>
                                </div>
                            <?php else: ?>
                                <div class="item-image" style="display:flex;align-items:center;justify-content:center;background:#f8f4f0;">
                                    <i class="fas fa-couch" style="font-size:2rem;color:#ccc;"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <div class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                            <div class="item-type"><i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($item['material'] ?? ''); ?></div>
                            <div class="item-description"><?php echo htmlspecialchars($item['description']); ?></div>
                            <small class="text-muted"><i class="fas fa-calendar me-1"></i>Added on <?php echo date('M j, Y', strtotime($item['added_at'])); ?></small>
                        </div>
                        <div class="col-md-2 text-center">
                            <div class="item-price">ETB <?php echo number_format($item['estimated_price'], 0); ?></div>
                            <small class="text-muted">Estimated Price</small>
                        </div>
                        <div class="col-md-2">
                            <div class="item-actions flex-column">
                                <a href="<?php echo BASE_URL; ?>/public/customer/create-order?product_id=<?php echo $item['product_id']; ?>&product_name=<?php echo urlencode($item['product_name']); ?>&material=<?php echo urlencode($item['material'] ?? ''); ?>&description=<?php echo urlencode($item['description']); ?>&estimated_price=<?php echo $item['estimated_price']; ?>"
                                   class="btn btn-order w-100 mb-2">
                                    <i class="fas fa-shopping-cart me-1"></i>Order Now
                                </a>
                                <button class="btn btn-remove w-100" onclick="removeFromWishlist(<?php echo $item['wishlist_id']; ?>, <?php echo $item['product_id']; ?>)">
                                    <i class="fas fa-trash me-1"></i>Remove
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
        function removeFromWishlist(wishlistId, productId) {
            if (!confirm('Are you sure you want to remove this item from your wishlist?')) return;
            const fd = new FormData();
            fd.append('product_id', productId);
            fetch('<?php echo BASE_URL; ?>/public/api/add_to_wishlist.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const el = document.getElementById('wishlist-item-' + wishlistId);
                        if (el) el.remove();
                    } else {
                        alert(data.message || 'Error removing item');
                    }
                })
                .catch(() => alert('Error removing item. Please try again.'));
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
