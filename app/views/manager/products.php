<?php
// Start output buffering to allow redirects after HTML output
ob_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    header('Location: ' . BASE_URL . '/public/login'); exit();
}
require_once __DIR__ . '/../../../config/db_config.php';

$csrf_token = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;
$managerName = $_SESSION['user_name'] ?? 'Manager User';

// ── Upload helper ──
function handleImageUpload($fileKey) {
    if (empty($_FILES[$fileKey]['name'])) return null;
    $file = $_FILES[$fileKey];
    $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
    if (!in_array($file['type'], $allowed)) return false;
    if ($file['size'] > 3 * 1024 * 1024) return false; // 3MB max
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $name = 'prod_' . uniqid() . '.' . $ext;
    $dir = __DIR__ . '/../../../public/uploads/products/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    return move_uploaded_file($file['tmp_name'], $dir . $name) ? 'uploads/products/' . $name : false;
}

// ── POST handlers ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Invalid CSRF token.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'add_product') {
            try {
                $imgPath = handleImageUpload('image_file');
                $stmt = $pdo->prepare("
                    INSERT INTO furn_products (name, product_name, category, material, dimensions, description, base_price, estimated_price, image_main, status, is_active, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', 1, NOW())
                ");
                $stmt->execute([
                    $_POST['product_name'], $_POST['product_name'],
                    $_POST['category'], $_POST['material'] ?? '',
                    $_POST['dimensions'] ?? '', $_POST['description'] ?? '',
                    $_POST['estimated_price'], $_POST['estimated_price'],
                    $imgPath ?: null,
                ]);
                $success = "Product added successfully!";
            } catch (PDOException $e) { $error = "Error: " . $e->getMessage(); }
        } elseif ($action === 'update_product') {
            try {
                $imgPath = handleImageUpload('image_file');
                $pid = intval($_POST['product_id']);
                if ($imgPath) {
                    $stmt = $pdo->prepare("UPDATE furn_products SET name=?,product_name=?,category=?,material=?,dimensions=?,description=?,base_price=?,estimated_price=?,status=?,image_main=? WHERE id=?");
                    $stmt->execute([$_POST['product_name'],$_POST['product_name'],$_POST['category'],$_POST['material']??'',$_POST['dimensions']??'',$_POST['description']??'',$_POST['estimated_price'],$_POST['estimated_price'],$_POST['status'],$imgPath,$pid]);
                } else {
                    $stmt = $pdo->prepare("UPDATE furn_products SET name=?,product_name=?,category=?,material=?,dimensions=?,description=?,base_price=?,estimated_price=?,status=? WHERE id=?");
                    $stmt->execute([$_POST['product_name'],$_POST['product_name'],$_POST['category'],$_POST['material']??'',$_POST['dimensions']??'',$_POST['description']??'',$_POST['estimated_price'],$_POST['estimated_price'],$_POST['status'],$pid]);
                }
                $success = "Product updated successfully!";
            } catch (PDOException $e) { $error = "Error: " . $e->getMessage(); }
        } elseif ($action === 'delete_product') {
            try {
                $pdo->prepare("DELETE FROM furn_products WHERE id=?")->execute([intval($_POST['product_id'])]);
                $success = "Product deleted.";
            } catch (PDOException $e) { $error = "Error: " . $e->getMessage(); }
        }
    }
}

// ── Fetch products ──
$products = [];
$filterCat = $_GET['cat'] ?? '';
try {
    // Ensure legacy columns exist so old INSERT/UPDATE still work
    try {
        $pdo->exec("ALTER TABLE furn_products
            ADD COLUMN IF NOT EXISTS product_name VARCHAR(255) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS category VARCHAR(100) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS material VARCHAR(255) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS dimensions VARCHAR(100) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS estimated_price DECIMAL(12,2) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'active',
            ADD COLUMN IF NOT EXISTS product_id INT DEFAULT NULL
        ");
    } catch (PDOException $e2) {}

    if ($filterCat) {
        $stmt = $pdo->prepare("
            SELECT p.*,
                COALESCE(p.product_name, p.name) as product_name,
                COALESCE(p.category, c.name) as category,
                COALESCE(p.estimated_price, p.base_price) as estimated_price,
                COALESCE(p.product_id, p.id) as product_id
            FROM furn_products p
            LEFT JOIN furn_categories c ON p.category_id = c.id
            WHERE LOWER(COALESCE(p.category, c.name)) = LOWER(?)
            ORDER BY COALESCE(p.product_name, p.name)
        ");
        $stmt->execute([$filterCat]);
    } else {
        $stmt = $pdo->query("
            SELECT p.*,
                COALESCE(p.product_name, p.name) as product_name,
                COALESCE(p.category, c.name) as category,
                COALESCE(p.estimated_price, p.base_price) as estimated_price,
                COALESCE(p.product_id, p.id) as product_id
            FROM furn_products p
            LEFT JOIN furn_categories c ON p.category_id = c.id
            ORDER BY COALESCE(p.product_name, p.name)
        ");
    }
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $error = "Error fetching products: " . $e->getMessage(); }

$categories = ['sofa','chair','bed','table','cabinet','wardrobe','shelf','office','custom'];
$pageTitle = 'Finished Furniture';
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
        .prod-thumb { width:52px;height:52px;object-fit:cover;border-radius:8px;border:1px solid #E0E0E0; }
        .prod-no-img { width:52px;height:52px;border-radius:8px;background:#F5F5F5;display:flex;align-items:center;justify-content:center;color:#BDC3C7;font-size:20px;border:1px solid #E0E0E0; }
        .img-preview { width:100%;max-height:180px;object-fit:cover;border-radius:8px;margin-top:8px;display:none; }
        .cat-filter { display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px; }
        .cat-btn { padding:6px 14px;border-radius:20px;border:1.5px solid #E0E0E0;background:#fff;font-size:12px;font-weight:600;cursor:pointer;color:#555;transition:all .2s; }
        .cat-btn.active,.cat-btn:hover { background:#3D1F14;color:#fff;border-color:#3D1F14; }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>
    <div class="sidebar-overlay"></div>
    <?php include_once __DIR__ . '/../../includes/manager_sidebar.php'; ?>
    <!-- Top Header -->
    <?php 
    $pageTitle = 'Products';
    include_once __DIR__ . '/../../includes/manager_header.php'; 
    ?>

    <div class="main-content">
        <?php if (isset($success)): ?>
        <div style="background:#d4edda;color:#155724;border:1px solid #c3e6cb;border-radius:8px;padding:12px 16px;margin-bottom:16px;"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
        <div style="background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;border-radius:8px;padding:12px 16px;margin-bottom:16px;"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="section-card">
            <div class="section-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:16px;">
                <h2 class="section-title"><i class="fas fa-couch"></i> Finished Furniture
                    <span style="background:#3D1F1422;color:#3D1F14;border-radius:12px;padding:2px 10px;font-size:12px;font-weight:600;margin-left:8px;"><?php echo count($products); ?></span>
                </h2>
                <button onclick="openAddModal()" style="display:inline-flex;align-items:center;gap:8px;padding:10px 18px;background:#3D1F14;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
                    <i class="fas fa-plus"></i> Add Product
                </button>
            </div>

            <!-- Category filter -->
            <div class="cat-filter">
                <a href="?" class="cat-btn <?php echo !$filterCat?'active':''; ?>">All</a>
                <?php foreach ($categories as $c): ?>
                <a href="?cat=<?php echo $c; ?>" class="cat-btn <?php echo $filterCat===$c?'active':''; ?>"><?php echo ucfirst($c); ?></a>
                <?php endforeach; ?>
            </div>

            <?php if (empty($products)): ?>
                <p style="text-align:center;color:#95A5A6;padding:40px 0;">No products found. Add one to get started.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Material</th>
                            <th>Dimensions</th>
                            <th>Price (ETB)</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($products as $p):
                        $sc = ($p['is_active'] ? 'active' : 'inactive') === 'active' ? '#27AE60' : '#95A5A6';
                    ?>
                        <tr>
                            <td>
                                <?php if (!empty($p['image_main'] ?? '')): ?>
                                    <img src="<?php echo BASE_URL.'/public/'.htmlspecialchars($p['image_main'] ?? ''); ?>"
                                         alt="<?php echo htmlspecialchars($p['name']); ?>"
                                         class="prod-thumb"
                                         onclick="openImageModal('<?php echo BASE_URL.'/public/'.htmlspecialchars($p['image_main'] ?? ''); ?>','<?php echo htmlspecialchars($p['name']); ?>')"
                                         style="cursor:pointer;">
                                <?php else: ?>
                                    <div class="prod-no-img"><i class="fas fa-couch"></i></div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td>
                            <td><span style="background:#3D1F1418;color:#3D1F14;border-radius:12px;padding:2px 10px;font-size:12px;font-weight:600;"><?php echo ucfirst($p['category']??''); ?></span></td>
                            <td style="font-size:13px;"><?php echo htmlspecialchars($p['materials_used']??'—'); ?></td>
                            <td style="font-size:13px;color:#7F8C8D;"><?php echo htmlspecialchars($p['dimensions']??'—'); ?></td>
                            <td><strong><?php echo number_format($p['base_price']??0,2); ?></strong></td>
                            <td><span style="background:<?php echo $sc; ?>22;color:<?php echo $sc; ?>;border:1px solid <?php echo $sc; ?>55;border-radius:12px;padding:2px 10px;font-size:12px;font-weight:600;"><?php echo ucfirst($p['is_active'] ? 'active' : 'inactive'??'active'); ?></span></td>
                            <td style="display:flex;gap:6px;flex-wrap:wrap;">
                                <button onclick='openEditModal(<?php echo json_encode($p,JSON_HEX_APOS|JSON_HEX_QUOT); ?>)'
                                    style="background:#EBF5FB;color:#3498DB;border:none;border-radius:6px;padding:7px 12px;cursor:pointer;font-size:13px;font-weight:600;">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button onclick="deleteProduct(<?php echo $p['id']; ?>,'<?php echo htmlspecialchars($p['name'],ENT_QUOTES); ?>')"
                                    style="background:#FDEDEC;color:#E74C3C;border:none;border-radius:6px;padding:7px 12px;cursor:pointer;font-size:13px;font-weight:600;">
                                    <i class="fas fa-trash"></i>
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

    <!-- Image lightbox -->
    <div id="imgOverlay" onclick="closeImageModal()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.8);z-index:9999;align-items:center;justify-content:center;">
        <div style="position:relative;max-width:90vw;max-height:90vh;">
            <img id="imgLightbox" src="" alt="" style="max-width:100%;max-height:85vh;border-radius:10px;box-shadow:0 8px 40px rgba(0,0,0,0.5);">
            <div id="imgLightboxCaption" style="text-align:center;color:#fff;margin-top:10px;font-size:15px;font-weight:600;"></div>
        </div>
    </div>

    <!-- Add Modal -->
    <div id="addModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9000;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:14px;width:560px;max-width:95vw;max-height:90vh;overflow-y:auto;box-shadow:0 8px 40px rgba(0,0,0,0.2);">
            <div style="background:linear-gradient(135deg,#2C3E50,#3D1F14);color:#fff;padding:20px 24px;border-radius:14px 14px 0 0;display:flex;justify-content:space-between;align-items:center;">
                <div style="font-size:17px;font-weight:700;"><i class="fas fa-plus"></i> Add New Product</div>
                <button onclick="closeAddModal()" style="background:rgba(255,255,255,0.15);border:none;color:#fff;width:32px;height:32px;border-radius:50%;cursor:pointer;font-size:16px;">✕</button>
            </div>
            <form method="POST" enctype="multipart/form-data" style="padding:24px;">
                <input type="hidden" name="action" value="add_product">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <div style="margin-bottom:14px;">
                    <label style="display:block;font-weight:600;margin-bottom:6px;font-size:13px;">Product Name *</label>
                    <input type="text" name="product_name" required style="width:100%;padding:10px 12px;border:2px solid #E0E0E0;border-radius:8px;font-family:inherit;font-size:14px;outline:none;box-sizing:border-box;" onfocus="this.style.borderColor='#3D1F14'" onblur="this.style.borderColor='#E0E0E0'">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:6px;font-size:13px;">Category *</label>
                        <select name="category" required style="width:100%;padding:10px 12px;border:2px solid #E0E0E0;border-radius:8px;font-family:inherit;font-size:14px;outline:none;box-sizing:border-box;">
                            <?php foreach ($categories as $c): ?><option value="<?php echo $c; ?>"><?php echo ucfirst($c); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:6px;font-size:13px;">Material</label>
                        <input type="text" name="material" placeholder="e.g. Oak Wood" style="width:100%;padding:10px 12px;border:2px solid #E0E0E0;border-radius:8px;font-family:inherit;font-size:14px;outline:none;box-sizing:border-box;" onfocus="this.style.borderColor='#3D1F14'" onblur="this.style.borderColor='#E0E0E0'">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:6px;font-size:13px;">Dimensions</label>
                        <input type="text" name="dimensions" placeholder="e.g. 1.2×0.8×0.75 m" style="width:100%;padding:10px 12px;border:2px solid #E0E0E0;border-radius:8px;font-family:inherit;font-size:14px;outline:none;box-sizing:border-box;" onfocus="this.style.borderColor='#3D1F14'" onblur="this.style.borderColor='#E0E0E0'">
                    </div>
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:6px;font-size:13px;">Price (ETB) *</label>
                        <input type="number" name="estimated_price" step="0.01" required style="width:100%;padding:10px 12px;border:2px solid #E0E0E0;border-radius:8px;font-family:inherit;font-size:14px;outline:none;box-sizing:border-box;" onfocus="this.style.borderColor='#3D1F14'" onblur="this.style.borderColor='#E0E0E0'">
                    </div>
                </div>
                <div style="margin-bottom:14px;">
                    <label style="display:block;font-weight:600;margin-bottom:6px;font-size:13px;">Description</label>
                    <textarea name="description" rows="3" style="width:100%;padding:10px 12px;border:2px solid #E0E0E0;border-radius:8px;font-family:inherit;font-size:14px;outline:none;resize:vertical;box-sizing:border-box;" onfocus="this.style.borderColor='#3D1F14'" onblur="this.style.borderColor='#E0E0E0'"></textarea>
                </div>
                <div style="margin-bottom:20px;">
                    <label style="display:block;font-weight:600;margin-bottom:6px;font-size:13px;">Product Image</label>
                    <input type="file" name="image_file" accept="image/*" onchange="previewImg(this,'addPreview')"
                        style="width:100%;padding:10px 12px;border:2px dashed #E0E0E0;border-radius:8px;font-size:13px;cursor:pointer;box-sizing:border-box;">
                    <img id="addPreview" class="img-preview" alt="Preview">
                    <div style="font-size:11px;color:#95A5A6;margin-top:4px;">JPG, PNG, WebP — max 3MB</div>
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end;">
                    <button type="button" onclick="closeAddModal()" style="padding:10px 20px;background:#F5F5F5;color:#555;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;">Cancel</button>
                    <button type="submit" style="padding:10px 24px;background:#3D1F14;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;"><i class="fas fa-plus"></i> Add Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9000;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:14px;width:560px;max-width:95vw;max-height:90vh;overflow-y:auto;box-shadow:0 8px 40px rgba(0,0,0,0.2);">
            <div style="background:linear-gradient(135deg,#2C3E50,#3498DB);color:#fff;padding:20px 24px;border-radius:14px 14px 0 0;display:flex;justify-content:space-between;align-items:center;">
                <div style="font-size:17px;font-weight:700;"><i class="fas fa-edit"></i> Edit Product</div>
                <button onclick="closeEditModal()" style="background:rgba(255,255,255,0.15);border:none;color:#fff;width:32px;height:32px;border-radius:50%;cursor:pointer;font-size:16px;">✕</button>
            </div>
            <form method="POST" enctype="multipart/form-data" style="padding:24px;">
                <input type="hidden" name="action" value="update_product">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="product_id" id="edit_id">
                <div style="margin-bottom:14px;">
                    <label style="display:block;font-weight:600;margin-bottom:6px;font-size:13px;">Product Name *</label>
                    <input type="text" name="product_name" id="edit_name" required style="width:100%;padding:10px 12px;border:2px solid #E0E0E0;border-radius:8px;font-family:inherit;font-size:14px;outline:none;box-sizing:border-box;" onfocus="this.style.borderColor='#3498DB'" onblur="this.style.borderColor='#E0E0E0'">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:6px;font-size:13px;">Category *</label>
                        <select name="category" id="edit_cat" required style="width:100%;padding:10px 12px;border:2px solid #E0E0E0;border-radius:8px;font-family:inherit;font-size:14px;outline:none;box-sizing:border-box;">
                            <?php foreach ($categories as $c): ?><option value="<?php echo $c; ?>"><?php echo ucfirst($c); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:6px;font-size:13px;">Material</label>
                        <input type="text" name="material" id="edit_mat" style="width:100%;padding:10px 12px;border:2px solid #E0E0E0;border-radius:8px;font-family:inherit;font-size:14px;outline:none;box-sizing:border-box;" onfocus="this.style.borderColor='#3498DB'" onblur="this.style.borderColor='#E0E0E0'">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:6px;font-size:13px;">Dimensions</label>
                        <input type="text" name="dimensions" id="edit_dim" style="width:100%;padding:10px 12px;border:2px solid #E0E0E0;border-radius:8px;font-family:inherit;font-size:14px;outline:none;box-sizing:border-box;" onfocus="this.style.borderColor='#3498DB'" onblur="this.style.borderColor='#E0E0E0'">
                    </div>
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:6px;font-size:13px;">Price (ETB) *</label>
                        <input type="number" name="estimated_price" id="edit_price" step="0.01" required style="width:100%;padding:10px 12px;border:2px solid #E0E0E0;border-radius:8px;font-family:inherit;font-size:14px;outline:none;box-sizing:border-box;" onfocus="this.style.borderColor='#3498DB'" onblur="this.style.borderColor='#E0E0E0'">
                    </div>
                </div>
                <div style="margin-bottom:14px;">
                    <label style="display:block;font-weight:600;margin-bottom:6px;font-size:13px;">Description</label>
                    <textarea name="description" id="edit_desc" rows="3" style="width:100%;padding:10px 12px;border:2px solid #E0E0E0;border-radius:8px;font-family:inherit;font-size:14px;outline:none;resize:vertical;box-sizing:border-box;" onfocus="this.style.borderColor='#3498DB'" onblur="this.style.borderColor='#E0E0E0'"></textarea>
                </div>
                <div style="margin-bottom:14px;">
                    <label style="display:block;font-weight:600;margin-bottom:6px;font-size:13px;">Status</label>
                    <select name="status" id="edit_status" style="width:100%;padding:10px 12px;border:2px solid #E0E0E0;border-radius:8px;font-family:inherit;font-size:14px;outline:none;box-sizing:border-box;">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div style="margin-bottom:20px;">
                    <label style="display:block;font-weight:600;margin-bottom:6px;font-size:13px;">Replace Image</label>
                    <div id="edit_current_img" style="margin-bottom:8px;"></div>
                    <input type="file" name="image_file" accept="image/*" onchange="previewImg(this,'editPreview')"
                        style="width:100%;padding:10px 12px;border:2px dashed #E0E0E0;border-radius:8px;font-size:13px;cursor:pointer;box-sizing:border-box;">
                    <img id="editPreview" class="img-preview" alt="Preview">
                    <div style="font-size:11px;color:#95A5A6;margin-top:4px;">Leave empty to keep current image</div>
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end;">
                    <button type="button" onclick="closeEditModal()" style="padding:10px 20px;background:#F5F5F5;color:#555;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;">Cancel</button>
                    <button type="submit" style="padding:10px 24px;background:#3498DB;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete form -->
    <form id="delForm" method="POST" style="display:none;">
        <input type="hidden" name="action" value="delete_product">
        <input type="hidden" name="product_id" id="del_id">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
    </form>

    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
    <script>
    const BASE = '<?php echo BASE_URL; ?>';

    function openAddModal()  { document.getElementById('addModal').style.display='flex'; }
    function closeAddModal() { document.getElementById('addModal').style.display='none'; }
    function openEditModal(p) {
        document.getElementById('edit_id').value    = p.product_id;
        document.getElementById('edit_name').value  = p.product_name;
        document.getElementById('edit_cat').value   = p.category || 'custom';
        document.getElementById('edit_mat').value   = p.material || '';
        document.getElementById('edit_dim').value   = p.dimensions || '';
        document.getElementById('edit_desc').value  = p.description || '';
        document.getElementById('edit_price').value = p.estimated_price;
        document.getElementById('edit_status').value= p.status || 'active';
        const ci = document.getElementById('edit_current_img');
        if (p.image_main) {
            ci.innerHTML = '<img src="'+BASE+'/public/'+p.image_main+'" style="width:80px;height:80px;object-fit:cover;border-radius:8px;border:1px solid #E0E0E0;">';
        } else {
            ci.innerHTML = '<span style="font-size:12px;color:#95A5A6;">No current image</span>';
        }
        document.getElementById('editPreview').style.display='none';
        document.getElementById('editModal').style.display='flex';
    }
    function closeEditModal() { document.getElementById('editModal').style.display='none'; }
    function deleteProduct(id, name) {
        if (!confirm('Delete "'+name+'"? This cannot be undone.')) return;
        document.getElementById('del_id').value = id;
        document.getElementById('delForm').submit();
    }
    function previewImg(input, previewId) {
        const prev = document.getElementById(previewId);
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = e => { prev.src=e.target.result; prev.style.display='block'; };
            reader.readAsDataURL(input.files[0]);
        }
    }
    function openImageModal(src, name) {
        document.getElementById('imgLightbox').src = src;
        document.getElementById('imgLightboxCaption').textContent = name;
        document.getElementById('imgOverlay').style.display='flex';
    }
    function closeImageModal() { document.getElementById('imgOverlay').style.display='none'; }
    document.addEventListener('keydown', e => { if(e.key==='Escape'){closeAddModal();closeEditModal();closeImageModal();} });
    </script>
</body>
</html>
<?php
// Flush output buffer
ob_end_flush();
?>

