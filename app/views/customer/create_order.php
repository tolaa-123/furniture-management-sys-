<?php
// Session and authentication already handled by index.php
$customerId = $_SESSION['user_id'] ?? 0;
$customerName = $_SESSION['first_name'] ?? $_SESSION['user_name'] ?? 'Customer';

// Generate CSRF token for security
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Generate order number
$orderNumber = 'ORD-' . date('Y') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
$orderDate = date('F j, Y');

// Check if ordering from gallery (product pre-fill)
$productId = $_GET['product_id'] ?? null;
$prefilledProduct = null;

if ($productId) {
    if (isset($_GET['product_name'])) {
        // Data passed directly via URL params from gallery
        $prefilledProduct = [
            'product_id'      => $productId,
            'product_name'    => $_GET['product_name'] ?? '',
            'category'        => $_GET['category'] ?? '',
            'material'        => $_GET['material'] ?? '',
            'dimensions'      => $_GET['dimensions'] ?? '',
            'description'     => $_GET['description'] ?? '',
            'color'           => $_GET['color'] ?? '',
            'estimated_price' => floatval($_GET['estimated_price'] ?? 0),
            'image_url'       => $_GET['image_url'] ?? '',
        ];
    } else {
        // Fallback: fetch from DB
        require_once __DIR__ . '/../../../config/db_config.php';
        try {
            $stmt = $pdo->prepare("SELECT * FROM furn_products WHERE id = ? AND is_active = 1");
            $stmt->execute([$productId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                // try old product_id column
                $stmt2 = $pdo->prepare("SELECT * FROM furn_products WHERE product_id = ? AND status = 'active'");
                $stmt2->execute([$productId]);
                $row = $stmt2->fetch(PDO::FETCH_ASSOC);
            }
            if ($row) {
                $prefilledProduct = [
                    'product_id'      => $row['id'] ?? $row['product_id'],
                    'product_name'    => $row['name'] ?? $row['product_name'] ?? '',
                    'category'        => $row['category'] ?? '',
                    'material'        => $row['materials_used'] ?? $row['material'] ?? '',
                    'dimensions'      => $row['dimensions'] ?? '',
                    'description'     => $row['description'] ?? '',
                    'color'           => $row['color'] ?? '',
                    'estimated_price' => floatval($row['base_price'] ?? $row['estimated_price'] ?? 0),
                    'image_url'       => $row['image_main'] ?? '',
                ];
            }
        } catch (PDOException $e) {}
    }
}

$pageTitle = 'Create Custom Furniture Order';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - SmartWorkshop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/css/admin-responsive.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        
        /* Header */
        .top-header {
            background: linear-gradient(135deg, #4a2c2a 0%, #3d1f1d 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .logo-section { display: flex; align-items: center; gap: 15px; }
        .logo-icon { font-size: 28px; color: #d4a574; }
        .brand-name { font-size: 20px; font-weight: 600; }
        .header-title { font-size: 18px; color: #d4a574; }
        .header-right { display: flex; align-items: center; gap: 20px; }
        .status-badge { background: #28a745; padding: 6px 15px; border-radius: 20px; font-size: 13px; display: flex; align-items: center; gap: 5px; }
        .notification-icon { position: relative; font-size: 20px; cursor: pointer; }
        .notification-badge { position: absolute; top: -8px; right: -8px; background: #dc3545; color: white; border-radius: 50%; width: 18px; height: 18px; font-size: 11px; display: flex; align-items: center; justify-content: center; }
        .user-profile { display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.1); padding: 8px 15px; border-radius: 25px; cursor: pointer; }
        .user-avatar { width: 35px; height: 35px; border-radius: 50%; background: #d4a574; display: flex; align-items: center; justify-content: center; font-weight: 600; color: #4a2c2a; }
        .user-role { background: #ffc107; color: #000; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
        
        /* Layout */
        .dashboard-container { display: flex; min-height: calc(100vh - 70px); }
        
        /* Sidebar */
        .sidebar { width: 250px; background: linear-gradient(180deg, #4a2c2a 0%, #3d1f1d 100%); color: white; padding: 20px 0; box-shadow: 2px 0 10px rgba(0,0,0,0.1); }
        .sidebar-menu { list-style: none; padding: 0; }
        .sidebar-menu li { margin: 5px 15px; }
        .sidebar-menu a { display: flex; align-items: center; gap: 12px; padding: 12px 15px; color: rgba(255,255,255,0.8); text-decoration: none; border-radius: 8px; transition: all 0.3s; }
        .sidebar-menu a:hover { background: rgba(255,255,255,0.1); color: white; }
        .sidebar-menu a.active { background: white; color: #4a2c2a; font-weight: 600; }
        .sidebar-menu a i { width: 20px; text-align: center; }
        .create-order-btn { background: white !important; color: #4a2c2a !important; font-weight: 600; margin: 10px 15px 20px; text-align: center; justify-content: center; }
        .sidebar-menu a[href*="logout"] { margin-top: 10px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px; }
        .sidebar-menu a[href*="logout"]:hover { background: rgba(220, 53, 69, 0.2); color: #ff6b6b; }
        
        /* Main Content */
        .main-content { flex: 1; padding: 30px; overflow-y: auto; background: #f8f9fa; }
        .page-header { background: white; padding: 25px; border-radius: 15px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .page-title { font-size: 28px; font-weight: 700; color: #2c3e50; margin-bottom: 10px; }
        .page-description { color: #7f8c8d; font-size: 15px; }
        
        /* Form Sections */
        .form-section { background: white; padding: 25px; border-radius: 15px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .section-title { font-size: 18px; font-weight: 600; color: #4a2c2a; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #d4a574; }
        .form-label { font-weight: 600; color: #2c3e50; margin-bottom: 8px; }
        .form-control, .form-select { border: 2px solid #e9ecef; border-radius: 8px; padding: 10px 15px; transition: all 0.3s; }
        .form-control:focus, .form-select:focus { border-color: #4a2c2a; box-shadow: 0 0 0 0.2rem rgba(74, 44, 42, 0.15); }
        .required { color: #dc3545; }
        
        /* Order Summary */
        .summary-card { background: linear-gradient(135deg, #4a2c2a 0%, #3d1f1d 100%); color: white; padding: 20px; border-radius: 15px; }
        .summary-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.2); }
        .summary-item:last-child { border-bottom: none; }
        .summary-label { opacity: 0.8; }
        .summary-value { font-weight: 600; }
        
        /* Buttons */
        .btn-submit { background: linear-gradient(135deg, #4a2c2a 0%, #3d1f1d 100%); color: white; padding: 12px 30px; border: none; border-radius: 8px; font-weight: 600; transition: all 0.3s; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(74, 44, 42, 0.3); color: white; }
        .btn-reset { background: #6c757d; color: white; padding: 12px 30px; border: none; border-radius: 8px; font-weight: 600; }
        .btn-cancel { background: white; color: #4a2c2a; padding: 12px 30px; border: 2px solid #4a2c2a; border-radius: 8px; font-weight: 600; }
        
        /* File Upload */
        .file-upload-area { border: 2px dashed #d4a574; border-radius: 10px; padding: 30px; text-align: center; background: #fafafa; transition: all 0.3s; cursor: pointer; }
        .file-upload-area:hover { background: #f0f0f0; border-color: #4a2c2a; }
        .file-upload-icon { font-size: 48px; color: #d4a574; margin-bottom: 15px; }
        .file-name { margin-top: 10px; color: #28a745; font-weight: 600; }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" aria-label="Toggle Menu">
        <i class="fas fa-bars"></i>
    </button>
    <div class="sidebar-overlay"></div>

    <?php
    $pageTitle = 'Create Order';
    include_once __DIR__ . '/../../includes/customer_header.php';
    ?>

    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include_once __DIR__ . '/../../includes/customer_sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title"><i class="fas fa-plus-circle me-2"></i>Create Custom Furniture Order</h1>
                <p class="page-description">Fill the form below to request custom furniture from our workshop. Our manager will review your order and provide a cost estimation.</p>
            </div>

            <form id="customOrderForm" enctype="multipart/form-data">
                <!-- CSRF Token for Security -->
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="row justify-content-center">
                    <div class="col-12">
                        <!-- Section 1: Furniture Information -->
                        <div class="form-section">
                            <?php if ($prefilledProduct): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> 
                                    <strong>Ordering from Gallery:</strong> <?php echo htmlspecialchars($prefilledProduct['product_name']); ?>
                                    <br><small>You can modify any details below to customize your order.</small>
                                </div>
                            <?php endif; ?>
                            <h3 class="section-title"><i class="fas fa-couch me-2"></i>Furniture Information</h3>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Furniture Type <span class="required">*</span></label>
                                    <select class="form-select" name="furniture_type" required>
                                        <option value="">Select Type...</option>
                                        <option value="Table"       <?php echo ($prefilledProduct && strtolower($prefilledProduct['category']) == 'table')    ? 'selected' : ''; ?>>Table</option>
                                        <option value="Chair"       <?php echo ($prefilledProduct && strtolower($prefilledProduct['category']) == 'chair')    ? 'selected' : ''; ?>>Chair</option>
                                        <option value="Bed"         <?php echo ($prefilledProduct && strtolower($prefilledProduct['category']) == 'bed')      ? 'selected' : ''; ?>>Bed</option>
                                        <option value="Sofa"        <?php echo ($prefilledProduct && strtolower($prefilledProduct['category']) == 'sofa')     ? 'selected' : ''; ?>>Sofa</option>
                                        <option value="Wardrobe"    <?php echo ($prefilledProduct && strtolower($prefilledProduct['category']) == 'wardrobe') ? 'selected' : ''; ?>>Wardrobe</option>
                                        <option value="Cabinet"     <?php echo ($prefilledProduct && strtolower($prefilledProduct['category']) == 'cabinet')  ? 'selected' : ''; ?>>Cabinet</option>
                                        <option value="Desk"        <?php echo ($prefilledProduct && strtolower($prefilledProduct['category']) == 'office')   ? 'selected' : ''; ?>>Desk</option>
                                        <option value="Shelf"       <?php echo ($prefilledProduct && strtolower($prefilledProduct['category']) == 'shelf')    ? 'selected' : ''; ?>>Shelf</option>
                                        <option value="Custom"      <?php echo ($prefilledProduct && strtolower($prefilledProduct['category']) == 'custom')   ? 'selected' : ''; ?>>Custom</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Color/Finish <span class="required">*</span></label>
                                    <select class="form-select" name="color" required>
                                        <option value="">Select Color...</option>
                                        <?php
                                        $colorOptions = ['Natural Wood','Brown','Dark Brown','Black','White','Gray','Custom Color'];
                                        $prefilledColor = $prefilledProduct ? ($prefilledProduct['color'] ?? '') : '';
                                        foreach ($colorOptions as $c) {
                                            $selected = (strcasecmp($c, $prefilledColor) === 0) ? 'selected' : '';
                                            echo "<option value=\"$c\" $selected>$c</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Section 2: Dimensions -->
                        <div class="form-section">
                            <h3 class="section-title"><i class="fas fa-ruler-combined me-2"></i>Dimensions</h3>
                            <?php 
                            // Parse dimensions if prefilled (format: "1.2m x 0.6m x 0.75m")
                            $length = $width = $height = '';
                            if ($prefilledProduct && $prefilledProduct['dimensions']) {
                                $dims = preg_match('/(\d+(?:\.\d+)?)\s*m\s*x\s*(\d+(?:\.\d+)?)\s*m\s*x\s*(\d+(?:\.\d+)?)\s*m/i', 
                                                   $prefilledProduct['dimensions'], $matches);
                                if ($dims) {
                                    $length = $matches[1];
                                    $width = $matches[2];
                                    $height = $matches[3];
                                }
                            }
                            ?>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Length m <span class="required">*</span></label>
                                    <input type="number" class="form-control" name="length" id="length" 
                                           placeholder="1.2" min="0.01" step="0.01" 
                                           value="<?php echo $length; ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Width m <span class="required">*</span></label>
                                    <input type="number" class="form-control" name="width" id="width" 
                                           placeholder="0.6" min="0.01" step="0.01" 
                                           value="<?php echo $width; ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Height m <span class="required">*</span></label>
                                    <input type="number" class="form-control" name="height" id="height" 
                                           placeholder="0.75" min="0.01" step="0.01" 
                                           value="<?php echo $height; ?>" required>
                                </div>
                            </div>
                            
                            <!-- NEW ERP FIELDS -->
                            <div class="row mt-3">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Quantity <span class="required">*</span></label>
                                    <input type="number" class="form-control" name="quantity" id="quantity" min="1" value="1" required>
                                    <small class="text-muted">Number of items</small>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Budget Range <span class="required">*</span></label>
                                    <select class="form-select" name="budget_range" id="budget_range" required>
                                        <option value="">Select Budget...</option>
                                        <?php
                                        $budgetRanges = [
                                            'Under ETB 5,000',
                                            'ETB 5,000 - ETB 10,000',
                                            'ETB 10,000 - ETB 20,000',
                                            'Above ETB 20,000'
                                        ];
                                        $estimatedPrice = $prefilledProduct ? $prefilledProduct['estimated_price'] : 0;
                                        $selectedBudget = '';
                                        if ($estimatedPrice > 0) {
                                            if ($estimatedPrice < 5000) $selectedBudget = 'Under ETB 5,000';
                                            elseif ($estimatedPrice <= 10000) $selectedBudget = 'ETB 5,000 - ETB 10,000';
                                            elseif ($estimatedPrice <= 20000) $selectedBudget = 'ETB 10,000 - ETB 20,000';
                                            else $selectedBudget = 'Above ETB 20,000';
                                        }
                                        foreach ($budgetRanges as $range) {
                                            $selected = ($range == $selectedBudget) ? 'selected' : '';
                                            echo "<option value=\"$range\" $selected>$range</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Preferred Delivery Date</label>
                                    <input type="date" class="form-control" name="preferred_delivery_date" id="preferred_delivery_date" min="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                                    <small class="text-muted">Minimum 7 days</small>
                                </div>
                            </div>
                        </div>

                        <!-- Section 3: Design Details -->
                        <div class="form-section">
                            <h3 class="section-title"><i class="fas fa-pencil-ruler me-2"></i>Design Details</h3>
                            <div class="mb-3">
                                <label class="form-label">Design Description</label>
                                <textarea class="form-control" name="design_description" rows="5" placeholder="Describe your furniture design in detail. Example: I want a modern desk with two drawers and cable holes for computer wires."><?php echo $prefilledProduct ? htmlspecialchars($prefilledProduct['description'] ?? '') : ''; ?></textarea>
                                <small class="text-muted">Be as detailed as possible to help our craftsmen understand your vision.</small>
                            </div>
                        </div>

                        <!-- Section 5: Upload Design -->
                        <div class="form-section">
                            <h3 class="section-title"><i class="fas fa-image me-2"></i>Upload Design Image</h3>
                            <?php
                            $galleryImageUrl = '';
                            if ($prefilledProduct && !empty($prefilledProduct['image_url'])) {
                                $galleryImageUrl = $prefilledProduct['image_url'];
                            }
                            ?>
                            <?php if ($galleryImageUrl): ?>
                            <div id="galleryImagePreview" style="margin-bottom:15px; padding:15px; background:#f8f4e9; border-radius:10px; border:2px solid #d4a574;">
                                <p style="margin:0 0 10px; font-weight:600; color:#4a2c2a;"><i class="fas fa-images me-2"></i>Inspiration Image (from Gallery)</p>
                                <img src="<?php echo htmlspecialchars($galleryImageUrl); ?>"
                                     alt="Gallery inspiration"
                                     style="max-width:100%; max-height:200px; border-radius:8px; object-fit:cover; display:block;">
                                <small class="text-muted d-block mt-2">This gallery image will be used as your design reference. Upload your own image below to replace it.</small>
                            </div>
                            <input type="hidden" name="gallery_image_url" id="galleryImageUrl" value="<?php echo htmlspecialchars($galleryImageUrl); ?>">
                            <?php endif; ?>
                            <div class="file-upload-area" onclick="document.getElementById('designImage').click()">
                                <i class="fas fa-cloud-upload-alt file-upload-icon"></i>
                                <h5><?php echo $galleryImageUrl ? 'Upload Your Own Image (Optional — replaces gallery image)' : 'Click to Upload Design Image'; ?></h5>
                                <p class="text-muted mb-0">Supported: JPG, PNG, PDF (Max 5MB)</p>
                                <p class="file-name" id="fileName"></p>
                            </div>
                            <input type="file" id="designImage" name="design_image" accept=".jpg,.jpeg,.png,.pdf" style="display: none;" onchange="showFileName()">
                        </div>

                        <!-- Section 6: Special Instructions removed -->

                        <!-- Hidden fields -->
                        <input type="hidden" name="order_number" value="<?php echo $orderNumber; ?>">
                        <input type="hidden" name="customer_id" value="<?php echo $customerId; ?>">

                        <!-- Submit Buttons -->
                        <div class="form-section" style="margin-top:10px;">
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-submit">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Order
                                </button>
                                <button type="reset" class="btn btn-reset">
                                    <i class="fas fa-redo me-2"></i>Reset Form
                                </button>
                                <a href="<?php echo BASE_URL; ?>/public/customer/dashboard" class="btn btn-cancel">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                            </div>
                        </div>

                        <!-- What Happens Next -->
                        <div class="alert alert-info" style="border-radius:12px;">
                            <h6 style="font-weight:700;margin-bottom:10px;"><i class="fas fa-info-circle me-2"></i>What Happens Next?</h6>
                            <ol class="mb-0 ps-3" style="font-size:13px;line-height:2;">
                                <li>Manager reviews your order</li>
                                <li>Cost estimation provided</li>
                                <li>You pay 40% deposit</li>
                                <li>Production begins</li>
                                <li>You pay remaining 60%</li>
                                <li>Delivery arranged</li>
                            </ol>
                        </div>

                    </div>

                    <div class="col-lg-4" style="display:none;">
                        <!-- sidebar removed -->
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function showFileName() {
            const input = document.getElementById('designImage');
            const fileName = document.getElementById('fileName');
            const galleryPreview = document.getElementById('galleryImagePreview');
            const galleryUrl = document.getElementById('galleryImageUrl');
            if (input.files.length > 0) {
                fileName.textContent = '✓ ' + input.files[0].name;
                // User's own file takes priority — hide gallery reference
                if (galleryPreview) galleryPreview.style.display = 'none';
                if (galleryUrl) galleryUrl.value = '';
            }
        }

        $(document).ready(function() {
            $('#customOrderForm').on('submit', function(e) {
                e.preventDefault();
                
                // Validate new ERP fields
                let isValid = true;
                let errors = [];
                
                // Validate dimensions
                const length = parseFloat($('#length').val());
                const width = parseFloat($('#width').val());
                const height = parseFloat($('#height').val());
                
                if (length <= 0) {
                    errors.push('Length must be greater than 0');
                    isValid = false;
                }
                if (width <= 0) {
                    errors.push('Width must be greater than 0');
                    isValid = false;
                }
                if (height <= 0) {
                    errors.push('Height must be greater than 0');
                    isValid = false;
                }
                
                // Validate quantity
                const quantity = parseInt($('#quantity').val());
                if (quantity < 1) {
                    errors.push('Quantity must be at least 1');
                    isValid = false;
                }
                
                // Validate budget range
                const budgetRange = $('#budget_range').val();
                if (!budgetRange) {
                    errors.push('Please select a budget range');
                    isValid = false;
                }
                
                // Show errors if any
                if (!isValid) {
                    alert('Please fix the following errors:\n\n' + errors.join('\n'));
                    return false;
                }
                
                const formData = new FormData(this);
                const submitBtn = $(this).find('button[type="submit"]');
                
                submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Submitting...');
                
                $.ajax({
                    url: '<?php echo BASE_URL; ?>/public/api/submit_custom_order.php',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            alert('✓ Order submitted successfully!\n\nOrder Number: ' + response.order_number + '\n\nThe workshop manager will review your order soon.');
                            window.location.href = '<?php echo BASE_URL; ?>/public/customer/my-orders';
                        } else {
                            alert('Error: ' + response.message);
                            submitBtn.prop('disabled', false).html('<i class="fas fa-paper-plane me-2"></i>Submit Order');
                        }
                    },
                    error: function() {
                        alert('Error submitting order. Please try again.');
                        submitBtn.prop('disabled', false).html('<i class="fas fa-paper-plane me-2"></i>Submit Order');
                    }
                });
            });
        });
    </script>
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
