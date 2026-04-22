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
    <title><?php echo $pageTitle; ?> - FurnitureCraft</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/css/admin-responsive.css">
    <style>
        .top-header { position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; width: 100% !important; z-index: 1998 !important; }
        @media (min-width: 1024px) { .top-header { left: 260px !important; width: calc(100% - 260px) !important; } }
        .form-section { background: white; padding: 25px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); }
        .section-title { font-size: 16px; font-weight: 600; color: #4a2c2a; margin-bottom: 18px; padding-bottom: 10px; border-bottom: 2px solid #d4a574; }
        .form-label { font-weight: 600; color: #2c3e50; margin-bottom: 6px; display: block; font-size: 13px; }
        .form-control, .form-select {
            width: 100%; padding: 9px 12px; border: 1.5px solid #ddd; border-radius: 8px;
            font-size: 14px; font-family: inherit; outline: none; box-sizing: border-box;
            transition: border-color .2s;
        }
        .form-control:focus, .form-select:focus { border-color: #8B4513; }
        .required { color: #dc3545; }
        .row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .row.row-3 { grid-template-columns: 1fr 1fr 1fr; }
        @media(max-width:768px) { .row, .row.row-3 { grid-template-columns: 1fr; } }
        .col { }
        .file-upload-area { border: 2px dashed #d4a574; border-radius: 10px; padding: 30px; text-align: center; background: #fafafa; cursor: pointer; transition: all .2s; }
        .file-upload-area:hover { background: #f0f0f0; border-color: #8B4513; }
        .file-name { margin-top: 10px; color: #28a745; font-weight: 600; font-size: 13px; }
        .btn-submit { background: linear-gradient(135deg,#4a2c2a,#3d1f1d); color: white; padding: 12px 30px; border: none; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; width: 100%; margin-bottom: 10px; font-family: inherit; }
        .btn-submit:hover { opacity: .9; }
        .btn-reset { background: #6c757d; color: white; padding: 12px 30px; border: none; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; width: 100%; margin-bottom: 10px; font-family: inherit; }
        .btn-cancel { background: white; color: #4a2c2a; padding: 12px 30px; border: 2px solid #4a2c2a; border-radius: 8px; font-weight: 600; font-size: 14px; text-decoration: none; display: block; text-align: center; font-family: inherit; }
        .alert-info { background: #e3f2fd; border: 1px solid #90caf9; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; font-size: 13px; color: #1565c0; }
        .alert-steps { background: #e8f5e9; border: 1px solid #a5d6a7; border-radius: 8px; padding: 14px 18px; font-size: 13px; color: #2e7d32; }
        .alert-steps ol { margin: 8px 0 0 16px; line-height: 2; }
        small.hint { color: #7f8c8d; font-size: 12px; display: block; margin-top: 3px; }
        .spinner { display: inline-block; width: 14px; height: 14px; border: 2px solid #fff; border-top-color: transparent; border-radius: 50%; animation: spin .6s linear infinite; vertical-align: middle; margin-right: 6px; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>
    <div class="sidebar-overlay"></div>

    <?php
    $pageTitle = 'Create Order';
    include_once __DIR__ . '/../../includes/customer_sidebar.php';
    include_once __DIR__ . '/../../includes/customer_header.php';
    ?>

    <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title"><i class="fas fa-plus-circle me-2"></i>Create Custom Furniture Order</h1>
                <p class="page-description">Fill the form below to request custom furniture from our workshop. Our manager will review your order and provide a cost estimation.</p>
            </div>

            <form id="customOrderForm" enctype="multipart/form-data">
                <!-- CSRF Token for Security -->
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div>
                    <div>
                        <!-- Section 1: Furniture Information -->
                        <div class="form-section">
                            <?php if ($prefilledProduct): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> 
                                    <strong>Ordering from Gallery:</strong> <?php echo htmlspecialchars($prefilledProduct['product_name']); ?>
                                    <br><small>You can modify any details below to customize your order.</small>
                                </div>
                            <?php endif; ?>
                            <h3 class="section-title"><i class="fas fa-couch" style="margin-right:8px;"></i>Furniture Information</h3>
                            <div class="row">
                                <div class="col">
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
                                <div class="col">
                                    <label class="form-label">Furniture Name</label>
                                    <input type="text" class="form-control" name="furniture_name"
                                           placeholder="e.g. Living Room Sofa, Office Desk"
                                           value="<?php echo htmlspecialchars($prefilledProduct['product_name'] ?? ''); ?>">
                                    <small class="text-muted">Optional — give your piece a name</small>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col">
                                    <label class="form-label">Primary Material</label>
                                    <select class="form-select" name="material">
                                        <option value="">Select Material...</option>
                                        <?php
                                        $materials = ['Oak Wood','Teak Wood','Pine Wood','Mahogany','Plywood','MDF','Metal Frame','Stainless Steel','Premium Leather','Fabric Upholstery','Glass','Bamboo','Other'];
                                        $prefilledMat = $prefilledProduct ? ($prefilledProduct['material'] ?? '') : '';
                                        foreach ($materials as $m) {
                                            $selected = (strcasecmp($m, $prefilledMat) === 0) ? 'selected' : '';
                                            echo "<option value=\"$m\" $selected>$m</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col">
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
                            <h3 class="section-title"><i class="fas fa-ruler-combined" style="margin-right:8px;"></i>Dimensions</h3>
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
                            <div class="row row-3">
                                <div class="col">
                                    <label class="form-label">Length m <span class="required">*</span></label>
                                    <input type="number" class="form-control" name="length" id="length" 
                                           placeholder="1.2" min="0.01" step="0.01" 
                                           value="<?php echo $length; ?>" required>
                                </div>
                                <div class="col">
                                    <label class="form-label">Width m <span class="required">*</span></label>
                                    <input type="number" class="form-control" name="width" id="width" 
                                           placeholder="0.6" min="0.01" step="0.01" 
                                           value="<?php echo $width; ?>" required>
                                </div>
                                <div class="col">
                                    <label class="form-label">Height m <span class="required">*</span></label>
                                    <input type="number" class="form-control" name="height" id="height" 
                                           placeholder="0.75" min="0.01" step="0.01" 
                                           value="<?php echo $height; ?>" required>
                                </div>
                            </div>
                            
                            <div class="row row-3" style="margin-top:16px;">
                                <div class="col">
                                    <label class="form-label">Quantity <span class="required">*</span></label>
                                    <input type="number" class="form-control" name="quantity" id="quantity" min="1" value="1" required>
                                    <small class="hint">Number of items</small>
                                </div>
                                <div class="col">
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
                                <div class="col">
                                    <label class="form-label">Preferred Delivery Date</label>
                                    <input type="date" class="form-control" name="preferred_delivery_date" id="preferred_delivery_date" min="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                                    <small class="hint">Minimum 7 days from today</small>
                                </div>
                            </div>
                        </div>

                        <!-- Section 3: Design Details -->
                        <div class="form-section">
                            <h3 class="section-title"><i class="fas fa-pencil-ruler" style="margin-right:8px;"></i>Design Details</h3>
                            <div>
                                <label class="form-label">Design Description</label>
                                <textarea class="form-control" name="design_description" rows="5" placeholder="Describe your furniture design in detail. Example: I want a modern desk with two drawers and cable holes for computer wires."><?php echo $prefilledProduct ? htmlspecialchars($prefilledProduct['description'] ?? '') : ''; ?></textarea>
                                <small class="text-muted">Be as detailed as possible to help our craftsmen understand your vision.</small>
                            </div>
                        </div>

                        <!-- Section 5: Upload Design -->
                        <div class="form-section">
                            <h3 class="section-title"><i class="fas fa-image" style="margin-right:8px;"></i>Upload Design Image</h3>
                            <?php
                            $galleryImageUrl = '';
                            if ($prefilledProduct && !empty($prefilledProduct['image_url'])) {
                                $galleryImageUrl = $prefilledProduct['image_url'];
                            }
                            ?>
                            <?php if ($galleryImageUrl): ?>
                            <div id="galleryImagePreview" style="margin-bottom:15px; padding:15px; background:#f8f4e9; border-radius:10px; border:2px solid #d4a574;">
                                <p style="margin:0 0 10px; font-weight:600; color:#4a2c2a;"><i class="fas fa-images" style="margin-right:8px;"></i>Inspiration Image (from Gallery)</p>
                                <img src="<?php echo htmlspecialchars($galleryImageUrl); ?>"
                                     alt="Gallery inspiration"
                                     style="max-width:100%; max-height:200px; border-radius:8px; object-fit:cover; display:block;">
                                <small class="text-muted d-block mt-2">This gallery image will be used as your design reference. Upload your own image below to replace it.</small>
                            </div>
                            <input type="hidden" name="gallery_image_url" id="galleryImageUrl" value="<?php echo htmlspecialchars($galleryImageUrl); ?>">
                            <?php endif; ?>
                            <div class="file-upload-area" onclick="document.getElementById('designImage').click()" <?php echo $galleryImageUrl ? 'style="display:none;"' : ''; ?>>
                                <i class="fas fa-cloud-upload-alt file-upload-icon"></i>
                                <h5>Click to Upload Design Image</h5>
                                <p class="text-muted mb-0">Supported: JPG, PNG, PDF (Max 5MB)</p>
                                <p class="file-name" id="fileName"></p>
                            </div>
                            <input type="file" id="designImage" name="design_image" accept=".jpg,.jpeg,.png,.pdf" style="display: none;" onchange="showFileName()">
                        </div>

                        <!-- Section 6: Special Instructions removed -->

                        <!-- Hidden fields -->
                        <input type="hidden" name="order_number" value="<?php echo $orderNumber; ?>">
                        <input type="hidden" name="customer_id" value="<?php echo $customerId; ?>">

                        <div class="form-section" style="margin-top:10px;">
                            <button type="submit" class="btn-submit">
                                <i class="fas fa-paper-plane" style="margin-right:8px;"></i>Submit Order
                            </button>
                            <button type="reset" class="btn-reset">
                                <i class="fas fa-redo" style="margin-right:8px;"></i>Reset Form
                            </button>
                            <a href="<?php echo BASE_URL; ?>/public/customer/dashboard" class="btn-cancel">
                                <i class="fas fa-times" style="margin-right:8px;"></i>Cancel
                            </a>
                        </div>
                        </div>

                        <div class="alert-steps">
                            <h6 style="font-weight:700;margin-bottom:10px;"><i class="fas fa-info-circle" style="margin-right:8px;"></i>What Happens Next?</h6>
                            <ol style="font-size:13px;line-height:2;margin:0 0 0 16px;padding:0;">
                                <li>Manager reviews your order</li>
                                <li>Cost estimation provided</li>
                                <li>You pay 40% deposit</li>
                                <li>Production begins</li>
                                <li>You pay remaining 60%</li>
                                <li>Delivery arranged</li>
                            </ol>
                        </div>

                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="<?php echo BASE_URL; ?>/public/assets/js/jquery-3.6.0.min.js"></script>
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
                
                submitBtn.prop('disabled', true).html('<span class="spinner"></span>Submitting...');
                
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
                            submitBtn.prop('disabled', false).html('<i class="fas fa-paper-plane" style="margin-right:8px;"></i>Submit Order');
                        }
                    },
                    error: function() {
                        alert('Error submitting order. Please try again.');
                        submitBtn.prop('disabled', false).html('<i class="fas fa-paper-plane" style="margin-right:8px;"></i>Submit Order');
                    }
                });
            });
        });
    </script>
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
