<?php
// Manager authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}

require_once __DIR__ . '/../../../config/db_config.php';

// Get order ID from URL
$orderId = $_GET['order_id'] ?? 0;

// Fetch order details
$order = null;
if ($orderId > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT o.*, CONCAT(u.first_name, ' ', u.last_name) as customer_name, u.email, u.phone
            FROM furn_orders o
            LEFT JOIN furn_users u ON o.customer_id = u.id
            WHERE o.id = ?
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching order: " . $e->getMessage());
    }
}

// Redirect if order not found
if (!$order) {
    $_SESSION['error_message'] = 'Order not found.';
    header('Location: ' . BASE_URL . '/public/manager/orders');
    exit();
}

// Check if order can be edited by manager
$editableStatuses = ['pending_review', 'pending_cost_approval', 'cost_estimated', 'waiting_for_deposit'];
if (!in_array($order['status'], $editableStatuses)) {
    $_SESSION['error_message'] = 'This order cannot be edited. It has already progressed beyond editing stage.';
    header('Location: ' . BASE_URL . '/public/manager/orders');
    exit();
}

$managerName = $_SESSION['user_name'] ?? 'Manager';
$pageTitle = 'Edit Order - Manager';
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
        .btn-cancel { background: white; color: #4a2c2a; padding: 12px 30px; border: 2px solid #4a2c2a; border-radius: 8px; font-weight: 600; font-size: 14px; text-decoration: none; display: block; text-align: center; font-family: inherit; }
        .alert-info { background: #e3f2fd; border: 1px solid #90caf9; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; font-size: 13px; color: #1565c0; }
        .alert-warning { background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; font-size: 13px; color: #856404; }
        small.hint { color: #7f8c8d; font-size: 12px; display: block; margin-top: 3px; }
        .spinner { display: inline-block; width: 14px; height: 14px; border: 2px solid #fff; border-top-color: transparent; border-radius: 50%; animation: spin .6s linear infinite; vertical-align: middle; margin-right: 6px; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .current-image-preview { margin-bottom: 15px; padding: 15px; background: #f8f4e9; border-radius: 10px; border: 2px solid #d4a574; }
        .current-image-preview img { max-width: 100%; max-height: 200px; border-radius: 8px; object-fit: cover; }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>
    <div class="sidebar-overlay"></div>

    <?php include_once __DIR__ . '/../../includes/manager_header.php'; ?>
    <?php include_once __DIR__ . '/../../includes/manager_sidebar.php'; ?>

    <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title"><i class="fas fa-edit me-2"></i>Edit Order (Manager)</h1>
                <p class="page-description">Order #<?php echo htmlspecialchars($order['order_number']); ?> — Customer: <?php echo htmlspecialchars($order['customer_name']); ?></p>
            </div>

            <div class="alert-warning">
                <i class="fas fa-exclamation-triangle"></i> 
                <strong>Manager Edit:</strong> You are editing this order on behalf of the customer. If cost estimation was already done, it will need to be recalculated.
            </div>

            <div class="alert-info">
                <strong>Customer Contact:</strong> <?php echo htmlspecialchars($order['email']); ?> | <?php echo htmlspecialchars($order['phone'] ?? 'N/A'); ?>
            </div>

            <form id="managerEditOrderForm" enctype="multipart/form-data">
                <!-- CSRF Token for Security -->
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                
                <div>
                    <div>
                        <!-- Section 1: Furniture Information -->
                        <div class="form-section">
                            <h3 class="section-title"><i class="fas fa-couch" style="margin-right:8px;"></i>Furniture Information</h3>
                            <div class="row">
                                <div class="col">
                                    <label class="form-label">Furniture Type <span class="required">*</span></label>
                                    <select class="form-select" name="furniture_type" required>
                                        <option value="">Select Type...</option>
                                        <option value="Table" <?php echo ($order['furniture_type'] == 'Table') ? 'selected' : ''; ?>>Table</option>
                                        <option value="Chair" <?php echo ($order['furniture_type'] == 'Chair') ? 'selected' : ''; ?>>Chair</option>
                                        <option value="Bed" <?php echo ($order['furniture_type'] == 'Bed') ? 'selected' : ''; ?>>Bed</option>
                                        <option value="Sofa" <?php echo ($order['furniture_type'] == 'Sofa') ? 'selected' : ''; ?>>Sofa</option>
                                        <option value="Desk" <?php echo ($order['furniture_type'] == 'Desk') ? 'selected' : ''; ?>>Desk</option>
                                        <option value="Shelf" <?php echo ($order['furniture_type'] == 'Shelf') ? 'selected' : ''; ?>>Shelf</option>
                                        <option value="Other" <?php echo ($order['furniture_type'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col">
                                    <label class="form-label">Color/Finish <span class="required">*</span></label>
                                    <select class="form-select" name="color" required>
                                        <option value="">Select Color...</option>
                                        <?php
                                        $colorOptions = ['Natural Wood','Brown','Dark Brown','Black','White','Gray','Custom Color'];
                                        foreach ($colorOptions as $c) {
                                            $selected = ($order['color'] == $c) ? 'selected' : '';
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
                            <div class="row row-3">
                                <div class="col">
                                    <label class="form-label">Length m <span class="required">*</span></label>
                                    <input type="number" class="form-control" name="length" id="length" 
                                           placeholder="1.2" min="0.01" step="0.01" 
                                           value="<?php echo htmlspecialchars($order['length'] ?? ''); ?>" required>
                                </div>
                                <div class="col">
                                    <label class="form-label">Width m <span class="required">*</span></label>
                                    <input type="number" class="form-control" name="width" id="width" 
                                           placeholder="0.6" min="0.01" step="0.01" 
                                           value="<?php echo htmlspecialchars($order['width'] ?? ''); ?>" required>
                                </div>
                                <div class="col">
                                    <label class="form-label">Height m <span class="required">*</span></label>
                                    <input type="number" class="form-control" name="height" id="height" 
                                           placeholder="0.75" min="0.01" step="0.01" 
                                           value="<?php echo htmlspecialchars($order['height'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row row-3" style="margin-top:16px;">
                                <div class="col">
                                    <label class="form-label">Quantity <span class="required">*</span></label>
                                    <input type="number" class="form-control" name="quantity" id="quantity" min="1" 
                                           value="<?php echo intval($order['quantity'] ?? 1); ?>" required>
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
                                        foreach ($budgetRanges as $range) {
                                            $selected = ($order['budget_range'] == $range) ? 'selected' : '';
                                            echo "<option value=\"$range\" $selected>$range</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col">
                                    <label class="form-label">Preferred Delivery Date</label>
                                    <input type="date" class="form-control" name="preferred_delivery_date" id="preferred_delivery_date" 
                                           min="<?php echo date('Y-m-d', strtotime('+7 days')); ?>"
                                           value="<?php echo htmlspecialchars($order['preferred_delivery_date'] ?? ''); ?>">
                                    <small class="hint">Minimum 7 days from today</small>
                                </div>
                            </div>
                        </div>

                        <!-- Section 3: Design Details -->
                        <div class="form-section">
                            <h3 class="section-title"><i class="fas fa-pencil-ruler" style="margin-right:8px;"></i>Design Details</h3>
                            <div>
                                <label class="form-label">Design Description <span class="required">*</span></label>
                                <textarea class="form-control" name="design_description" rows="5" placeholder="Describe your furniture design in detail..." required><?php echo htmlspecialchars($order['design_description'] ?? ''); ?></textarea>
                                <small class="text-muted">Be as detailed as possible to help our craftsmen understand your vision.</small>
                            </div>
                        </div>

                        <!-- Section 4: Upload Design Image -->
                        <div class="form-section">
                            <h3 class="section-title"><i class="fas fa-image" style="margin-right:8px;"></i>Upload Design Image</h3>
                            
                            <?php if (!empty($order['design_image'])): ?>
                            <div class="current-image-preview">
                                <p style="margin:0 0 10px; font-weight:600; color:#4a2c2a;"><i class="fas fa-images" style="margin-right:8px;"></i>Current Design Image</p>
                                <img src="<?php echo BASE_URL; ?>/public/<?php echo htmlspecialchars($order['design_image']); ?>" 
                                     alt="Current design"
                                     onerror="this.parentElement.innerHTML='<p style=color:#dc3545;>Image not available</p>'">
                                <small class="text-muted d-block mt-2">Upload a new image below to replace this one.</small>
                            </div>
                            <?php endif; ?>
                            
                            <div class="file-upload-area" onclick="document.getElementById('designImage').click()">
                                <i class="fas fa-cloud-upload-alt file-upload-icon"></i>
                                <h5>Click to Upload New Design Image</h5>
                                <p class="text-muted mb-0">Supported: JPG, PNG, PDF (Max 5MB)</p>
                                <p class="file-name" id="fileName"></p>
                            </div>
                            <input type="file" id="designImage" name="design_image" accept=".jpg,.jpeg,.png,.pdf" style="display: none;" onchange="showFileName()">
                        </div>

                        <div class="form-section" style="margin-top:10px;">
                            <button type="submit" class="btn-submit">
                                <i class="fas fa-save" style="margin-right:8px;"></i>Update Order
                            </button>
                            <a href="<?php echo BASE_URL; ?>/public/manager/orders" class="btn-cancel">
                                <i class="fas fa-times" style="margin-right:8px;"></i>Cancel
                            </a>
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
            if (input.files.length > 0) {
                fileName.textContent = '✓ ' + input.files[0].name;
            }
        }

        $(document).ready(function() {
            $('#managerEditOrderForm').on('submit', function(e) {
                e.preventDefault();
                
                // Validate fields
                let isValid = true;
                let errors = [];
                
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
                
                const quantity = parseInt($('#quantity').val());
                if (quantity < 1) {
                    errors.push('Quantity must be at least 1');
                    isValid = false;
                }
                
                const budgetRange = $('#budget_range').val();
                if (!budgetRange) {
                    errors.push('Please select a budget range');
                    isValid = false;
                }
                
                if (!isValid) {
                    alert('Please fix the following errors:\n\n' + errors.join('\n'));
                    return false;
                }
                
                const formData = new FormData(this);
                const submitBtn = $(this).find('button[type="submit"]');
                
                submitBtn.prop('disabled', true).html('<span class="spinner"></span>Updating...');
                
                $.ajax({
                    url: '<?php echo BASE_URL; ?>/public/api/manager_update_order.php',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            alert('✓ Order updated successfully!\n\nOrder Number: ' + response.order_number);
                            window.location.href = '<?php echo BASE_URL; ?>/public/manager/orders';
                        } else {
                            alert('Error: ' + response.message);
                            submitBtn.prop('disabled', false).html('<i class="fas fa-save" style="margin-right:8px;"></i>Update Order');
                        }
                    },
                    error: function() {
                        alert('Error updating order. Please try again.');
                        submitBtn.prop('disabled', false).html('<i class="fas fa-save" style="margin-right:8px;"></i>Update Order');
                    }
                });
            });
        });
    </script>
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
