<?php
// Session and authentication already handled by index.php
$customerId = $_SESSION['user_id'] ?? 0;
$customerName = $_SESSION['first_name'] ?? $_SESSION['user_name'] ?? 'Customer';

// Get database connection
require_once __DIR__ . '/../../../config/db_config.php';

// Get wishlist items — LEFT JOIN so orphaned items (deleted products) still show and can be removed
try {
    $stmt = $pdo->prepare("
        SELECT 
            w.id as wishlist_id,
            w.product_id,
            w.created_at as added_at,
            p.name as product_name,
            p.description,
            p.base_price as estimated_price,
            p.materials_used as material,
            p.image_main,
            p.is_active
        FROM furn_wishlist w
        LEFT JOIN furn_products p ON w.product_id = p.id
        WHERE w.customer_id = ?
        ORDER BY w.created_at DESC
    ");
    $stmt->execute([$customerId]);
    $wishlistItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Wishlist query error: " . $e->getMessage());
    $wishlistItems = [];
}

// Count only active/available items for display stats
$activeItems = array_filter($wishlistItems, fn($i) => !empty($i['product_name']) && $i['is_active']);

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
    <style>
        .top-header { position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; width: 100% !important; z-index: 1998 !important; }
        @media (min-width: 1024px) { .top-header { left: 260px !important; width: calc(100% - 260px) !important; } }
        .wishlist-header { background: linear-gradient(135deg, #8B4513, #5D4037); color: #fff; border-radius: 12px; padding: 20px; margin-bottom: 25px; box-shadow: 0 8px 25px rgba(139,69,19,0.2); display:flex; justify-content:space-between; align-items:center; }
        .wishlist-item { background: white; border-radius: 12px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); transition: all 0.3s; display:flex; gap:20px; align-items:center; }
        .wishlist-item:hover { transform: translateY(-3px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .wishlist-item.orphaned { opacity: 0.6; border: 1px dashed #ccc; }
        .item-image { width: 110px; height: 110px; object-fit: cover; border-radius: 8px; flex-shrink:0; background:#f8f4f0; display:flex; align-items:center; justify-content:center; }
        .item-body { flex: 1; }
        .item-name { font-size: 1.1rem; font-weight: 600; color: #4a2c2a; margin-bottom: 4px; }
        .item-meta { color: #6c757d; font-size: 0.85rem; margin-bottom: 6px; }
        .item-price { font-size: 1.3rem; font-weight: 700; color: #8B4513; }
        .item-actions { display: flex; flex-direction:column; gap: 8px; min-width:130px; }
        .btn-order { background: #8B4513; color: white; border: none; padding: 9px 16px; border-radius: 8px; font-weight: 600; font-size:13px; cursor:pointer; text-decoration:none; text-align:center; display:block; font-family:inherit; }
        .btn-order:hover { background: #5D4037; color:white; }
        .btn-remove { background: #dc3545; color: white; border: none; padding: 9px 16px; border-radius: 8px; font-size:13px; cursor:pointer; font-family:inherit; }
        .btn-remove:hover { background: #c82333; }
        .empty-state { text-align: center; padding: 60px 20px; color: #6c757d; background: white; border-radius: 12px; }
        .wl-stats { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:24px; }
        .wl-stat-card { background:white; border-radius:12px; padding:18px 20px; box-shadow:0 2px 8px rgba(0,0,0,0.07); display:flex; align-items:center; gap:14px; }
        .wl-stat-icon { width:48px; height:48px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0; }
        .wl-stat-val { font-size:22px; font-weight:700; color:#2c3e50; }
        .wl-stat-lbl { font-size:12px; color:#888; }
        @media(max-width:768px){ .wl-stats{grid-template-columns:1fr;} .wishlist-item{flex-wrap:wrap;} }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>
    <div class="sidebar-overlay"></div>

    <?php
    $pageTitle = 'My Wishlist';
    include_once __DIR__ . '/../../includes/customer_sidebar.php';
    include_once __DIR__ . '/../../includes/customer_header.php';
    ?>

    <div class="main-content">
        <div class="wishlist-header">
            <div>
                <h1 style="margin:0 0 4px;font-size:22px;"><i class="fas fa-heart" style="margin-right:8px;"></i>My Wishlist</h1>
                <div style="opacity:.8;font-size:13px;">Save your favorite furniture designs for later</div>
            </div>
            <a href="<?php echo BASE_URL; ?>/public/customer/gallery" style="background:white;color:#8B4513;padding:9px 18px;border-radius:8px;font-weight:600;font-size:13px;text-decoration:none;">
                <i class="fas fa-search" style="margin-right:6px;"></i>Browse Gallery
            </a>
        </div>

        <!-- Stats -->
        <div class="wl-stats">
            <div class="wl-stat-card">
                <div class="wl-stat-icon" style="background:#fde8e8;color:#dc3545;"><i class="fas fa-heart"></i></div>
                <div><div class="wl-stat-val" id="wl-count"><?php echo count($wishlistItems); ?></div><div class="wl-stat-lbl">Saved Items</div></div>
            </div>
            <div class="wl-stat-card">
                <div class="wl-stat-icon" style="background:#e8f5e9;color:#27ae60;"><i class="fas fa-tag"></i></div>
                <div><div class="wl-stat-val">ETB <?php echo number_format(array_sum(array_column(array_filter($wishlistItems, fn($i) => !empty($i['estimated_price'])), 'estimated_price')), 0); ?></div><div class="wl-stat-lbl">Total Value</div></div>
            </div>
            <div class="wl-stat-card">
                <div class="wl-stat-icon" style="background:#e3f2fd;color:#3498db;"><i class="fas fa-clock"></i></div>
                <div><div class="wl-stat-val"><?php echo !empty($wishlistItems) ? date('M j', strtotime($wishlistItems[0]['added_at'])) : 'N/A'; ?></div><div class="wl-stat-lbl">Recently Added</div></div>
            </div>
        </div>

        <!-- Items -->
        <?php if (empty($wishlistItems)): ?>
            <div class="empty-state">
                <i class="fas fa-heart" style="font-size:4rem;color:#dee2e6;display:block;margin-bottom:20px;"></i>
                <h4>Your Wishlist is Empty</h4>
                <p style="color:#6c757d;">Browse our furniture collection and save your favorites here!</p>
                <a href="<?php echo BASE_URL; ?>/public/customer/gallery" class="btn-order" style="display:inline-block;margin-top:16px;padding:12px 28px;">
                    <i class="fas fa-search" style="margin-right:8px;"></i>Browse Gallery
                </a>
            </div>
        <?php else: ?>
            <?php foreach ($wishlistItems as $item):
                $isOrphaned = empty($item['product_name']) || !$item['is_active'];
            ?>
            <div class="wishlist-item <?php echo $isOrphaned ? 'orphaned' : ''; ?>" id="wishlist-item-<?php echo $item['wishlist_id']; ?>">
                <?php if (!$isOrphaned): ?>
                    <?php
                    $wImg = $item['image_main'] ?? '';
                    if (!empty($wImg)) {
                        $wImgSrc = strpos($wImg,'http')===0 ? $wImg : (strpos($wImg,'uploads/')===0 ? BASE_URL.'/public/'.$wImg : BASE_URL.'/public/assets/images/products/'.$wImg);
                    } else { $wImgSrc = ''; }
                    ?>
                    <?php if ($wImgSrc): ?>
                        <img src="<?php echo htmlspecialchars($wImgSrc); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                             class="item-image" style="width:110px;height:110px;object-fit:cover;border-radius:8px;flex-shrink:0;"
                             onerror="this.outerHTML='<div class=\'item-image\' style=\'width:110px;height:110px;border-radius:8px;background:#f8f4f0;display:flex;align-items:center;justify-content:center;flex-shrink:0;\'><i class=\'fas fa-couch\' style=\'font-size:2rem;color:#ccc;\'></i></div>'">
                    <?php else: ?>
                        <div class="item-image" style="width:110px;height:110px;border-radius:8px;background:#f8f4f0;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fas fa-couch" style="font-size:2rem;color:#ccc;"></i>
                        </div>
                    <?php endif; ?>
                    <div class="item-body">
                        <div class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                        <div class="item-meta"><i class="fas fa-tag" style="margin-right:4px;"></i><?php echo htmlspecialchars($item['material'] ?? ''); ?></div>
                        <div style="color:#495057;font-size:13px;margin-bottom:8px;"><?php echo htmlspecialchars(substr($item['description'] ?? '', 0, 100)); ?></div>
                        <small style="color:#aaa;"><i class="fas fa-calendar" style="margin-right:4px;"></i>Added <?php echo date('M j, Y', strtotime($item['added_at'])); ?></small>
                    </div>
                    <div style="text-align:center;min-width:100px;">
                        <div class="item-price">ETB <?php echo number_format($item['estimated_price'], 0); ?></div>
                        <small style="color:#888;">Est. Price</small>
                    </div>
                    <div class="item-actions">
                        <a href="<?php echo BASE_URL; ?>/public/customer/create-order?product_id=<?php echo $item['product_id']; ?>&product_name=<?php echo urlencode($item['product_name']); ?>&material=<?php echo urlencode($item['material'] ?? ''); ?>&description=<?php echo urlencode($item['description'] ?? ''); ?>&estimated_price=<?php echo $item['estimated_price']; ?>" class="btn-order">
                            <i class="fas fa-shopping-cart" style="margin-right:6px;"></i>Order Now
                        </a>
                        <button class="btn-remove" onclick="removeFromWishlist(<?php echo $item['wishlist_id']; ?>, <?php echo $item['product_id']; ?>)">
                            <i class="fas fa-trash" style="margin-right:6px;"></i>Remove
                        </button>
                    </div>
                <?php else: ?>
                    <div class="item-image" style="width:110px;height:110px;border-radius:8px;background:#f0f0f0;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fas fa-ban" style="font-size:2rem;color:#ccc;"></i>
                    </div>
                    <div class="item-body">
                        <div class="item-name" style="color:#999;">Product No Longer Available</div>
                        <div class="item-meta">This product has been removed from our catalog</div>
                        <small style="color:#aaa;"><i class="fas fa-calendar" style="margin-right:4px;"></i>Added <?php echo date('M j, Y', strtotime($item['added_at'])); ?></small>
                    </div>
                    <div class="item-actions">
                        <button class="btn-remove" onclick="removeFromWishlist(<?php echo $item['wishlist_id']; ?>, <?php echo $item['product_id']; ?>)">
                            <i class="fas fa-trash" style="margin-right:6px;"></i>Remove
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script>
    function removeFromWishlist(wishlistId, productId) {
        if (!confirm('Remove this item from your wishlist?')) return;
        const fd = new FormData();
        fd.append('product_id', productId);
        fetch('<?php echo BASE_URL; ?>/public/api/add_to_wishlist.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const el = document.getElementById('wishlist-item-' + wishlistId);
                    if (el) el.remove();
                    // Update count badge on this page
                    const countEl = document.getElementById('wl-count');
                    if (countEl) countEl.textContent = Math.max(0, parseInt(countEl.textContent) - 1);
                } else {
                    alert(data.message || 'Error removing item');
                }
            })
            .catch(() => alert('Error removing item. Please try again.'));
    }
    </script>
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
