<?php
// Manager authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}

require_once __DIR__ . '/../../../config/db_config.php';


$csrf_token = $_SESSION['csrf_token'] ?? null;
if (!$csrf_token) {
    $csrf_token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrf_token;
}

$managerName = $_SESSION['user_name'] ?? 'Manager';
$managerId = $_SESSION['user_id'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token check for all POST actions
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Invalid CSRF token.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'add_product') {
            try {
                require_once __DIR__ . '/../../../core/SecurityUtil.php';
                $imageName = null;
                if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = __DIR__ . '/../../../public/assets/images/products/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                    $uploadResult = SecurityUtil::validateUpload($_FILES['product_image'], ['jpg','jpeg','png','gif','webp'], 5*1024*1024);
                    if ($uploadResult && $uploadResult['valid']) {
                        $imageName = $uploadResult['secure_name'];
                        move_uploaded_file($_FILES['product_image']['tmp_name'], $uploadDir . $imageName);
                    }
                }
                $categoryStmt = $pdo->prepare("SELECT id FROM furn_categories WHERE name = ?");
                $categoryStmt->execute([$_POST['category']]);
                $categoryRow = $categoryStmt->fetch(PDO::FETCH_ASSOC);
                $categoryId = $categoryRow ? $categoryRow['id'] : 1;
                try { $pdo->exec("ALTER TABLE furn_products ADD COLUMN IF NOT EXISTS dimensions VARCHAR(100) DEFAULT NULL"); } catch (PDOException $e2) {}
                $pdo->prepare("INSERT INTO furn_products (name, category_id, materials_used, dimensions, description, base_price, estimated_production_time, image_main, is_active) VALUES (?,?,?,?,?,?,?,?,1)")
                    ->execute([$_POST['product_name'], $categoryId, $_POST['material'], $_POST['dimensions'] ?? null, $_POST['description'], $_POST['estimated_price'], 14, $imageName]);
                $success = "Product added successfully!";
            } catch (PDOException $e) {
                $error = "Error: " . $e->getMessage();
            }

        } elseif ($action === 'edit_product') {
            try {
                require_once __DIR__ . '/../../../core/SecurityUtil.php';
                $imageName = $_POST['existing_image'] ?? null;
                if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = __DIR__ . '/../../../public/assets/images/products/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                    $uploadResult = SecurityUtil::validateUpload($_FILES['product_image'], ['jpg','jpeg','png','gif','webp'], 5*1024*1024);
                    if ($uploadResult && $uploadResult['valid']) {
                        if ($imageName && file_exists($uploadDir . $imageName)) unlink($uploadDir . $imageName);
                        $imageName = $uploadResult['secure_name'];
                        move_uploaded_file($_FILES['product_image']['tmp_name'], $uploadDir . $imageName);
                    }
                }
                $categoryStmt = $pdo->prepare("SELECT id FROM furn_categories WHERE name = ?");
                $categoryStmt->execute([$_POST['category']]);
                $categoryRow = $categoryStmt->fetch(PDO::FETCH_ASSOC);
                $categoryId = $categoryRow ? $categoryRow['id'] : 1;
                $pdo->prepare("UPDATE furn_products SET name=?, category_id=?, materials_used=?, dimensions=?, description=?, base_price=?, image_main=?, updated_at=NOW() WHERE id=?")
                    ->execute([$_POST['product_name'], $categoryId, $_POST['material'], $_POST['dimensions'] ?? null, $_POST['description'], $_POST['estimated_price'], $imageName, $_POST['product_id']]);
                $success = "Product updated successfully!";
            } catch (PDOException $e) {
                $error = "Error: " . $e->getMessage();
            }

        } elseif ($action === 'delete_product') {
            try {
                $productId = intval($_POST['product_id']);
                $pdo->prepare("DELETE FROM furn_products WHERE id = ?")
                    ->execute([$productId]);
                $success = "Product deleted successfully!";
            } catch (PDOException $e) {
                $error = "Error: " . $e->getMessage();
            }
        } // end action
} // end else CSRF
} // end if POST

// Get filter
$categoryFilter = $_GET['category'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Fetch products with category names
$query = "SELECT p.*, c.name as category_name 
          FROM furn_products p 
          LEFT JOIN furn_categories c ON p.category_id = c.id 
          WHERE p.is_active = 1";
$params = [];

if ($categoryFilter) {
    $query .= " AND c.name = ?";
    $params[] = $categoryFilter;
}

if ($searchQuery) {
    $query .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $searchTerm = "%$searchQuery%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$query .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Manage Products';
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
    <button class="mobile-menu-toggle" aria-label="Toggle Menu">
        <i class="fas fa-bars"></i>
    </button>
    <div class="sidebar-overlay"></div>
    
    <?php include_once __DIR__ . '/../../includes/manager_sidebar.php'; ?>

    <!-- Top Header -->
    <?php 
    $pageTitle = 'Manage Products';
    include_once __DIR__ . '/../../includes/manager_header.php'; 
    ?>
        <div class="header-left">
            <div class="system-status">
                <i class="fas fa-box-open"></i> Manage Products
            </div>
        </div>
        <div class="header-right">
            <div class="admin-profile">
                <div class="admin-avatar"><?php echo strtoupper(substr($managerName, 0, 1)); ?></div>
                <div>
                    <div style="font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($managerName); ?></div>
                    <div class="admin-role-badge">MANAGER</div>
                </div>
            </div>
        </div>
    </div>

    <div class="main-content">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Header Section -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-box-open"></i> Gallery Products</h2>
                <button class="btn-action btn-primary-custom" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add New Product
                </button>
            </div>
            
            <!-- Filters -->
            <div class="filter-section" style="margin-top: 20px;">
                <form method="GET" action="" style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <div class="form-group" style="margin: 0; flex: 1; min-width: 200px;">
                        <input type="text" name="search" class="form-control" placeholder="Search products..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                    </div>
                    
                    <div class="form-group" style="margin: 0;">
                        <select name="category" class="form-control">
                            <option value="">All Categories</option>
                            <option value="sofa" <?php echo $categoryFilter === 'sofa' ? 'selected' : ''; ?>>Sofas</option>
                            <option value="chair" <?php echo $categoryFilter === 'chair' ? 'selected' : ''; ?>>Chairs</option>
                            <option value="bed" <?php echo $categoryFilter === 'bed' ? 'selected' : ''; ?>>Beds</option>
                            <option value="table" <?php echo $categoryFilter === 'table' ? 'selected' : ''; ?>>Tables</option>
                            <option value="shelf" <?php echo $categoryFilter === 'shelf' ? 'selected' : ''; ?>>Shelves</option>
                            <option value="office" <?php echo $categoryFilter === 'office' ? 'selected' : ''; ?>>Office</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn-action btn-primary-custom">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    
                    <a href="?" class="btn-action btn-secondary-custom">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </form>
            </div>
        </div>

        <!-- Products Table -->
        <div class="section-card">
            <div class="table-responsive">
                <table class="data-table mobile-cards">
                    <thead style="background: #4A2C2A; color: white;">
                        <tr>
                            <th>ID</th>
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
                        <?php foreach ($products as $product): 
                            // Map database columns to expected format for JavaScript
                            $product['product_id'] = $product['id'];
                            $product['product_name'] = $product['name'];
                            $product['category'] = $product['category_name'] ?? 'Uncategorized';
                            $product['material'] = $product['materials_used'] ?? 'N/A';
                            $product['dimensions'] = $product['dimensions'] ?? 'N/A';
                            $product['estimated_price'] = $product['base_price'];
                            $product['status'] = $product['is_active'] ? 'active' : 'inactive';
                            $product['image_main'] = $product['image_main'] ?? null;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['id']); ?></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
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
                                                 style="width:50px;height:50px;object-fit:cover;border-radius:5px;"
                                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                            <div style="width:50px;height:50px;background:#f0f0f0;border-radius:5px;display:none;align-items:center;justify-content:center;">
                                                <i class="fas fa-image" style="color:#ccc;"></i>
                                            </div>
                                        <?php else: ?>
                                            <div style="width:50px;height:50px;background:#f0f0f0;border-radius:5px;display:flex;align-items:center;justify-content:center;">
                                                <i class="fas fa-image" style="color:#ccc;"></i>
                                            </div>
                                        <?php endif; ?>
                                        <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                    </div>
                                </td>
                                <td><span class="badge badge-info"><?php echo ucfirst($product['category_name'] ?? 'Uncategorized'); ?></span></td>
                                <td><?php echo htmlspecialchars($product['materials_used'] ?? '—'); ?></td>
                                <td><?php echo !empty($product['dimensions']) ? htmlspecialchars($product['dimensions']) : '<span style="color:#ccc;">—</span>'; ?></td>
                                <td><strong>ETB <?php echo number_format($product['base_price'], 2); ?></strong></td>
                                <td>
                                    <?php if ($product['is_active']): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn-action btn-warning-custom btn-edit"
                                        data-id="<?php echo (int)$product['id']; ?>">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn-action btn-danger-custom btn-delete"
                                        data-id="<?php echo (int)$product['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($product['name'], ENT_QUOTES); ?>">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h3><i class="fas fa-plus"></i> Add New Product</h3>
                <span class="close" onclick="closeAddModal()">&times;</span>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_product">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? $csrf_token); ?>">
                
                <div class="form-group">
                    <label>Product Image</label>
                    <input type="file" name="product_image" class="form-control" accept="image/*" onchange="previewAddImage(event)">
                    <small style="color: #666;">Recommended: 800x800px, Max 5MB (JPG, PNG, GIF, WEBP)</small>
                    <div id="add_image_preview" style="margin-top: 10px;"></div>
                </div>
                
                <div class="form-group">
                    <label>Product Name *</label>
                    <input type="text" name="product_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Category *</label>
                    <select name="category" class="form-control" required>
                        <option value="">Select Category</option>
                        <option value="sofa">Sofa</option>
                        <option value="chair">Chair</option>
                        <option value="bed">Bed</option>
                        <option value="table">Table</option>
                        <option value="shelf">Shelf</option>
                        <option value="office">Office Furniture</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Material *</label>
                    <input type="text" name="material" class="form-control" placeholder="e.g., Solid Wood, Leather" required>
                </div>
                
                <div class="form-group">
                    <label>Dimensions *</label>
                    <input type="text" name="dimensions" class="form-control" placeholder="e.g., 1.2m x 0.6m x 0.75m" required>
                </div>
                
                <div class="form-group">
                    <label>Description *</label>
                    <textarea name="description" class="form-control" rows="3" required></textarea>
                </div>
                
                <div class="form-group">
                    <label>Estimated Price (ETB) *</label>
                    <input type="number" name="estimated_price" class="form-control" step="0.01" min="0" required>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-action btn-secondary-custom" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn-action btn-primary-custom">Add Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Product</h3>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_product">
                <input type="hidden" name="product_id" id="edit_product_id">
                <input type="hidden" name="existing_image" id="edit_existing_image">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? $csrf_token); ?>">
                
                <div class="form-group">
                    <label>Product Image</label>
                    <div id="edit_current_image" style="margin-bottom: 10px;"></div>
                    <input type="file" name="product_image" class="form-control" accept="image/*" onchange="previewEditImage(event)">
                    <small style="color: #666;">Leave empty to keep current image. Max 5MB (JPG, PNG, GIF, WEBP)</small>
                    <div id="edit_image_preview" style="margin-top: 10px;"></div>
                </div>
                
                <div class="form-group">
                    <label>Product Name *</label>
                    <input type="text" name="product_name" id="edit_product_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Category *</label>
                    <select name="category" id="edit_category" class="form-control" required>
                        <option value="sofa">Sofa</option>
                        <option value="chair">Chair</option>
                        <option value="bed">Bed</option>
                        <option value="table">Table</option>
                        <option value="shelf">Shelf</option>
                        <option value="office">Office Furniture</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Material *</label>
                    <input type="text" name="material" id="edit_material" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Dimensions *</label>
                    <input type="text" name="dimensions" id="edit_dimensions" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Description *</label>
                    <textarea name="description" id="edit_description" class="form-control" rows="3" required></textarea>
                </div>
                
                <div class="form-group">
                    <label>Estimated Price (ETB) *</label>
                    <input type="number" name="estimated_price" id="edit_estimated_price" class="form-control" step="0.01" min="0" required>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-action btn-secondary-custom" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn-action btn-primary-custom">Update Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Form -->
    <form id="deleteForm" method="POST" action="<?php echo BASE_URL; ?>/public/manager/manage-products" style="display: none;">
        <input type="hidden" name="action" value="delete_product">
        <input type="hidden" name="product_id" id="delete_product_id">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? $csrf_token); ?>">
    </form>

    <script>
        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
            document.getElementById('add_image_preview').innerHTML = '';
        }

        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }

        function previewAddImage(event) {
            const file = event.target.files[0];
            const preview = document.getElementById('add_image_preview');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = '<img src="' + e.target.result + '" style="max-width: 200px; max-height: 200px; border-radius: 5px; border: 2px solid #4A2C2A;">';
                };
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = '';
            }
        }

        function previewEditImage(event) {
            const file = event.target.files[0];
            const preview = document.getElementById('edit_image_preview');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = '<img src="' + e.target.result + '" style="max-width: 200px; max-height: 200px; border-radius: 5px; border: 2px solid #4A2C2A;">';
                };
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = '';
            }
        }

        function editProduct(product) {
            document.getElementById('edit_product_id').value = product.id || product.product_id;
            document.getElementById('edit_product_name').value = product.name || product.product_name;
            // category_name may be "Sofa" but select values are lowercase
            const cat = (product.category || product.category_name || 'sofa').toLowerCase();
            document.getElementById('edit_category').value = cat;
            document.getElementById('edit_material').value = product.materials_used || product.material || '';
            document.getElementById('edit_dimensions').value = product.dimensions || '';
            document.getElementById('edit_description').value = product.description || '';
            document.getElementById('edit_estimated_price').value = product.base_price || product.estimated_price || 0;
            document.getElementById('edit_existing_image').value = product.image_main || '';
            
            // Show current image — handle both paths: assets/images/products/ and uploads/finished_products/
            const currentImageDiv = document.getElementById('edit_current_image');
            if (product.image_main) {
                let imgUrl = '';
                const img = product.image_main;
                if (img.startsWith('http')) {
                    imgUrl = img;
                } else if (img.startsWith('uploads/') || img.startsWith('assets/')) {
                    imgUrl = '<?php echo BASE_URL; ?>/public/' + img;
                } else {
                    imgUrl = '<?php echo BASE_URL; ?>/public/assets/images/products/' + img;
                }
                currentImageDiv.innerHTML = '<strong>Current Image:</strong><br><img src="' + imgUrl + '" style="max-width:150px;max-height:150px;border-radius:5px;margin-top:5px;" onerror="this.style.display=\'none\'">';
            } else {
                currentImageDiv.innerHTML = '<span style="color:#aaa;">No image uploaded</span>';
            }
            
            document.getElementById('edit_image_preview').innerHTML = '';
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function deleteProduct(id, name) {
            if (confirm('Are you sure you want to delete "' + name + '"?')) {
                document.getElementById('delete_product_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        window.onclick = function(event) {
            const modals = ['addModal', 'editModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }

        // Product data map — safe JS object, no HTML attribute escaping issues
        const PRODUCTS = <?php
            $map = [];
            foreach ($products as $p) {
                $map[(int)$p['id']] = [
                    'id'           => (int)$p['id'],
                    'name'         => $p['name'],
                    'category_name'=> $p['category_name'] ?? '',
                    'materials_used'=> $p['materials_used'] ?? '',
                    'dimensions'   => $p['dimensions'] ?? '',
                    'description'  => $p['description'] ?? '',
                    'base_price'   => (float)$p['base_price'],
                    'image_main'   => $p['image_main'] ?? '',
                ];
            }
            echo json_encode($map);
        ?>;

        // Wire up edit and delete buttons via event delegation
        document.addEventListener('click', function(e) {
            const editBtn = e.target.closest('.btn-edit');
            if (editBtn) {
                const product = PRODUCTS[parseInt(editBtn.dataset.id)];
                if (product) editProduct(product);
                return;
            }
            const delBtn = e.target.closest('.btn-delete');
            if (delBtn) {
                const id   = parseInt(delBtn.dataset.id);
                const name = delBtn.dataset.name;
                if (confirm('Are you sure you want to delete "' + name + '"?\nThis cannot be undone.')) {
                    document.getElementById('delete_product_id').value = id;
                    document.getElementById('deleteForm').submit();
                }
            }
        });
    </script>

    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
