<?php
// Session and authentication already handled by index.php
$customerId = $_SESSION['user_id'] ?? 0;
$customerName = $_SESSION['first_name'] ?? $_SESSION['user_name'] ?? 'Customer';

// Get database connection
require_once __DIR__ . '/../../../config/db_config.php';

// Get order ID from URL
$orderId = $_GET['id'] ?? 0;

// Fetch order details with finished product image from task
$order = null;
if ($orderId > 0 && $customerId > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                o.*,
                CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                u.email as customer_email,
                t.finished_image,
                t.completion_notes,
                t.materials_used,
                t.actual_hours,
                CONCAT(e.first_name, ' ', e.last_name) as employee_name
            FROM furn_orders o
            LEFT JOIN furn_users u ON o.customer_id = u.id
            LEFT JOIN furn_production_tasks t ON t.order_id = o.id AND t.status = 'completed'
            LEFT JOIN furn_users e ON t.employee_id = e.id
            WHERE o.id = ? AND o.customer_id = ?
        ");
        $stmt->execute([$orderId, $customerId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching order details: " . $e->getMessage());
    }
}

// Redirect if order not found
if (!$order) {
    header('Location: ' . BASE_URL . '/public/customer/my-orders');
    exit();
}

// Check if customer already rated this order
$existingRating = null;
try {
    $stmtR = $pdo->prepare("SELECT * FROM furn_ratings WHERE order_id = ? AND customer_id = ?");
    $stmtR->execute([$orderId, $customerId]);
    $existingRating = $stmtR->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* table may not exist yet */ }

// --- Payment calculation (single source of truth) ---
$totalCost = floatval($order['estimated_cost'] ?? $order['total_amount'] ?? 0);

// Deposit paid: use the order column (set by manager approval) as primary source
// Fall back to MAX single approved deposit payment to avoid double-counting duplicates
$depositPaid = floatval($order['deposit_paid'] ?? 0);
if ($depositPaid <= 0) {
    $stmtDep = $pdo->prepare("
        SELECT COALESCE(MAX(amount), 0) as paid
        FROM furn_payments
        WHERE order_id = ? AND payment_type IN ('deposit','prepayment') AND status IN ('approved','verified')
    ");
    $stmtDep->execute([$orderId]);
    $depositPaid = floatval($stmtDep->fetch(PDO::FETCH_ASSOC)['paid']);
}
$depositPaid = min($depositPaid, $totalCost);

// Final payment: single approved final payment row
$stmtFin = $pdo->prepare("
    SELECT COALESCE(MAX(amount), 0) as paid
    FROM furn_payments
    WHERE order_id = ? AND payment_type IN ('final','postpayment','remaining','final_payment') AND status IN ('approved','verified')
");
$stmtFin->execute([$orderId]);
$finalPaid = floatval($stmtFin->fetch(PDO::FETCH_ASSOC)['paid']);
$finalPaid = min($finalPaid, $totalCost - $depositPaid);

$amountPaid = min($depositPaid + $finalPaid, $totalCost);
$remainingBalance = $totalCost - $amountPaid;

$pageTitle = 'Order Details';
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
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; margin: 0; padding: 0; overflow: hidden; }
        .dashboard-container { display: flex; height: 100vh; }
        .main-content { flex: 1; margin-left: 250px; padding: 30px; overflow-y: auto; height: 100vh; }
        .order-header { background: linear-gradient(135deg, #8B4513, #5D4037); color: #fff; border-radius: 12px; padding: 20px; margin-bottom: 25px; box-shadow: 0 8px 25px rgba(139, 69, 19, 0.2); }
        .detail-card { background: white; border-radius: 12px; padding: 25px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .detail-row { display: flex; padding: 12px 0; border-bottom: 1px solid #f0f0f0; }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { font-weight: 600; color: #6c757d; width: 200px; }
        .detail-value { color: #495057; flex: 1; }
        .section-title { font-size: 1.25rem; font-weight: 600; color: #4a2c2a; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #8B4513; }
        .btn-back { background: #6c757d; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; display: inline-block; }
        .btn-back:hover { background: #5a6268; color: white; }

        /* ── PRINT STYLES ── */
        @media print {
            /* Hide everything that is not order content */
            .sidebar, .sidebar-overlay, .mobile-menu-toggle,
            .top-header, .order-header .btn,
            .detail-card:has(.star-rating),
            #ratingForm, .star-rating,
            .btn-back, .btn-success, .btn-warning, .btn-primary,
            .d-flex.gap-3,
            script { display: none !important; }

            /* Reset layout — no sidebar offset */
            body { background: #fff !important; overflow: visible !important; }
            .dashboard-container { display: block !important; }
            .main-content {
                margin-left: 0 !important;
                padding: 10px 20px !important;
                height: auto !important;
                overflow: visible !important;
            }

            /* Order header — keep but simplify */
            .order-header {
                background: #4a2c2a !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                border-radius: 0 !important;
                margin-bottom: 15px !important;
                padding: 14px 20px !important;
            }

            /* Cards */
            .detail-card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
                margin-bottom: 12px !important;
                page-break-inside: avoid;
            }

            /* Hide rating card entirely */
            .detail-card:has(#ratingForm),
            .detail-card:has(.star-btn) { display: none !important; }

            /* Hide action-required warning banners */
            div[style*="fff3cd"] { display: none !important; }

            /* Completed / status banners — keep */
            .detail-card:has(.fa-flag-checkered),
            .detail-card:has(.fa-hourglass-half) { display: block !important; }

            /* Print header branding */
            .print-header { display: block !important; }

            /* Page setup */
            @page { margin: 15mm; size: A4; }
        }

        /* Hidden on screen, shown only when printing */
        .print-header { display: none; }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" aria-label="Toggle Menu">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay"></div>

    <?php include_once __DIR__ . '/../../includes/customer_sidebar.php'; ?>

    <!-- Top Header -->
    <div class="top-header">
        <div class="header-left">
            <div class="system-status"><i class="fas fa-circle"></i> Customer Portal</div>
        </div>
        <div class="header-right">
            <div class="admin-profile">
                <div class="admin-avatar"><?php echo strtoupper(substr($customerName, 0, 1)); ?></div>
                <div>
                    <div style="font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($customerName); ?></div>
                    <div class="admin-role-badge">CUSTOMER</div>
                </div>
            </div>
        </div>
    </div>

        <!-- Main Content -->
        <div class="main-content" style="margin-left:250px;padding:30px;overflow-y:auto;height:100vh;">

            <!-- Print-only header (hidden on screen) -->
            <div class="print-header" style="text-align:center;margin-bottom:20px;padding-bottom:15px;border-bottom:3px solid #8B4513;">
                <h2 style="color:#4a2c2a;margin:0 0 4px;">FurnitureCraft — SmartWorkshop</h2>
                <div style="color:#666;font-size:13px;">Order Receipt &nbsp;|&nbsp; Printed: <?php echo date('F j, Y, g:i a'); ?></div>
            </div>
            <div class="order-header d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h2 mb-1"><i class="fas fa-file-alt me-2"></i>Order Details</h1>
                    <div class="opacity-75">Order #<?php echo htmlspecialchars($order['order_number']); ?></div>
                </div>
                <a href="<?php echo BASE_URL; ?>/public/customer/my-orders" class="btn btn-light">
                    <i class="fas fa-arrow-left me-2"></i>Back to Orders
                </a>
            </div>

            <!-- Order Completed Banner -->
            <?php if (($order['status'] ?? '') === 'completed'): ?>
            <div class="detail-card" style="border: 2px solid #27ae60; background: linear-gradient(135deg, #e8f5e9, #f1f8e9);">
                <div style="text-align: center; padding: 20px;">
                    <i class="fas fa-flag-checkered" style="font-size: 48px; color: #27ae60; margin-bottom: 15px; display: block;"></i>
                    <h3 style="color: #27ae60; margin-bottom: 10px;">Order Completed!</h3>
                    <p style="color: #555; margin-bottom: 5px;">Your custom furniture has been fully paid and is ready for delivery.</p>
                    <p style="color: #555;">Thank you for choosing FurnitureCraft. We hope you love your new furniture!</p>
                </div>
            </div>
            <?php elseif (($order['status'] ?? '') === 'final_payment_paid'): ?>
            <div class="detail-card" style="border: 2px solid #17a2b8; background: linear-gradient(135deg, #e3f2fd, #f0f8ff);">
                <div style="text-align: center; padding: 20px;">
                    <i class="fas fa-hourglass-half" style="font-size: 48px; color: #17a2b8; margin-bottom: 15px; display: block;"></i>
                    <h3 style="color: #17a2b8; margin-bottom: 10px;">Final Payment Submitted</h3>
                    <p style="color: #555; margin-bottom: 5px;">Your final payment is being reviewed by our team.</p>
                    <p style="color: #555;">Once approved, your order will be marked as <strong>Completed</strong> and your furniture will be ready for delivery.</p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Rating Section (completed orders only) -->
            <?php if (($order['status'] ?? '') === 'completed'): ?>
            <div class="detail-card" style="border: 2px solid #f39c12;">
                <div class="section-title" style="color: #e67e22;"><i class="fas fa-star me-2"></i>Rate Your Order</div>
                <?php if ($existingRating): ?>
                    <div style="text-align:center; padding: 20px;">
                        <div style="font-size: 28px; margin-bottom: 10px;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star" style="color: <?php echo $i <= $existingRating['rating'] ? '#f39c12' : '#ddd'; ?>;"></i>
                            <?php endfor; ?>
                        </div>
                        <p style="color: #555; margin-bottom: 5px;">You rated this order <strong><?php echo $existingRating['rating']; ?>/5 stars</strong></p>
                        <?php if (!empty($existingRating['review_text'])): ?>
                            <p style="color: #777; font-style: italic;">"<?php echo htmlspecialchars($existingRating['review_text']); ?>"</p>
                        <?php endif; ?>
                        <small style="color: #aaa;">Submitted on <?php echo date('M j, Y', strtotime($existingRating['created_at'])); ?></small>
                    </div>
                <?php else: ?>
                    <form id="ratingForm">
                        <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                        <div style="text-align: center; margin-bottom: 20px;">
                            <p style="color: #555; margin-bottom: 15px;">How satisfied are you with your finished furniture?</p>
                            <div class="star-rating" style="font-size: 40px; cursor: pointer; display: inline-block;">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star star-btn" data-value="<?php echo $i; ?>" style="color: #ddd; transition: color 0.2s;" title="<?php echo $i; ?> star<?php echo $i > 1 ? 's' : ''; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <input type="hidden" name="rating" id="ratingValue" value="0">
                            <div id="ratingLabel" style="color: #e67e22; font-weight: 600; margin-top: 8px; min-height: 24px;"></div>
                        </div>
                        <div style="margin-bottom: 15px;">
                            <textarea name="review_text" class="form-control" rows="3" placeholder="Suggestions / Review (optional) — Tell us what you liked or how we can improve..." style="border-radius: 8px;"></textarea>
                        </div>
                        <div style="text-align: center;">
                            <button type="submit" class="btn btn-warning" style="padding: 10px 30px; font-weight: 600;" id="submitRatingBtn" disabled>
                                <i class="fas fa-paper-plane me-2"></i>Submit Rating
                            </button>
                        </div>
                        <div id="ratingMsg" style="margin-top: 15px; text-align: center; display: none;"></div>
                    </form>
                    <script>
                    (function() {
                        const stars = document.querySelectorAll('.star-btn');
                        const ratingInput = document.getElementById('ratingValue');
                        const ratingLabel = document.getElementById('ratingLabel');
                        const submitBtn = document.getElementById('submitRatingBtn');
                        const labels = ['', 'Poor', 'Fair', 'Good', 'Very Good', 'Excellent'];
                        let selected = 0;

                        stars.forEach(star => {
                            star.addEventListener('mouseover', function() {
                                const val = parseInt(this.dataset.value);
                                stars.forEach((s, i) => s.style.color = i < val ? '#f39c12' : '#ddd');
                            });
                            star.addEventListener('mouseout', function() {
                                stars.forEach((s, i) => s.style.color = i < selected ? '#f39c12' : '#ddd');
                            });
                            star.addEventListener('click', function() {
                                selected = parseInt(this.dataset.value);
                                ratingInput.value = selected;
                                ratingLabel.textContent = labels[selected];
                                submitBtn.disabled = false;
                                stars.forEach((s, i) => s.style.color = i < selected ? '#f39c12' : '#ddd');
                            });
                        });

                        document.getElementById('ratingForm').addEventListener('submit', function(e) {
                            e.preventDefault();
                            if (!ratingInput.value || ratingInput.value == 0) return;
                            const formData = new FormData(this);
                            submitBtn.disabled = true;
                            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
                            fetch('<?php echo BASE_URL; ?>/public/api/submit_rating.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(r => r.json())
                            .then(data => {
                                const msg = document.getElementById('ratingMsg');
                                msg.style.display = 'block';
                                if (data.success) {
                                    msg.style.background = '#d4edda';
                                    msg.style.color = '#155724';
                                    msg.style.padding = '12px';
                                    msg.style.borderRadius = '8px';
                                    msg.innerHTML = '<i class="fas fa-check-circle me-2"></i>' + data.message;
                                    setTimeout(() => location.reload(), 1500);
                                } else {
                                    msg.style.background = '#f8d7da';
                                    msg.style.color = '#721c24';
                                    msg.style.padding = '12px';
                                    msg.style.borderRadius = '8px';
                                    msg.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>' + data.message;
                                    submitBtn.disabled = false;
                                    submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Submit Rating';
                                }
                            });
                        });
                    })();
                    </script>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Order Status -->
            <div class="detail-card">
                <div class="section-title">Order Status</div>
                <div class="text-center py-3">
                    <?php
                    $status = $order['status'] ?? '';
                    $statusClass = 'status-' . $status;
                    $statusDisplay = ucwords(str_replace('_', ' ', $status));
                    ?>
                    <span class="status-badge <?php echo $statusClass; ?>" style="font-size: 1.1rem; padding: 12px 24px;">
                        <?php echo $statusDisplay; ?>
                    </span>
                </div>
            </div>

            <!-- Order Information -->
            <div class="detail-card">
                <div class="section-title">Order Information</div>
                <div class="detail-row">
                    <div class="detail-label">Order Number:</div>
                    <div class="detail-value"><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Order Date:</div>
                    <div class="detail-value"><?php echo date('F j, Y, g:i a', strtotime($order['created_at'])); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Last Updated:</div>
                    <div class="detail-value"><?php echo date('F j, Y, g:i a', strtotime($order['updated_at'])); ?></div>
                </div>
            </div>

            <!-- Furniture Details -->
            <div class="detail-card">
                <div class="section-title">Furniture Details</div>
                <div class="detail-row">
                    <div class="detail-label">Furniture Type:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($order['furniture_type']); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Furniture Name:</div>
                    <div class="detail-value"><strong><?php echo htmlspecialchars($order['furniture_name']); ?></strong></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Dimensions (L × W × H):</div>
                    <div class="detail-value">
                        <?php echo number_format($order['length'], 2); ?> m × 
                        <?php echo number_format($order['width'], 2); ?> m × 
                        <?php echo number_format($order['height'], 2); ?> m
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Material:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($order['material']); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Color:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($order['color']); ?></div>
                </div>
                <?php if (!empty($order['design_description'])): ?>
                <div class="detail-row">
                    <div class="detail-label">Design Description:</div>
                    <div class="detail-value"><?php echo nl2br(htmlspecialchars($order['design_description'])); ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($order['design_image'])): ?>
                <div class="detail-row">
                    <div class="detail-label">Design Image:</div>
                    <div class="detail-value">
                        <a href="<?php echo BASE_URL; ?>/public/<?php echo htmlspecialchars($order['design_image']); ?>" target="_blank" style="display: inline-block;">
                            <img src="<?php echo BASE_URL; ?>/public/<?php echo htmlspecialchars($order['design_image']); ?>" 
                                 alt="Design Image" 
                                 style="max-width: 400px; max-height: 300px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); cursor: pointer; transition: transform 0.3s;"
                                 onmouseover="this.style.transform='scale(1.05)'"
                                 onmouseout="this.style.transform='scale(1)'"
                                 onerror="this.parentElement.innerHTML='<span style=color:#dc3545;><i class=fas fa-exclamation-triangle></i> Image not found</span>'">
                        </a>
                        <div style="margin-top: 8px; color: #6c757d; font-size: 0.9rem;">
                            <i class="fas fa-info-circle"></i> Click image to view full size
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if (!empty($order['special_notes'])): ?>
                <div class="detail-row">
                    <div class="detail-label">Special Notes:</div>
                    <div class="detail-value"><?php echo nl2br(htmlspecialchars($order['special_notes'])); ?></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Finished Product (shown when ready_for_delivery, final_payment_paid or completed) -->
            <?php if (!empty($order['finished_image']) && in_array($order['status'] ?? '', ['ready_for_delivery', 'final_payment_paid', 'completed'])): ?>
            <div class="detail-card" style="border: 2px solid #27ae60;">
                <div class="section-title" style="color: #27ae60;"><i class="fas fa-check-circle me-2"></i>Your Finished Product is Ready!</div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; align-items: start;">
                    <div>
                        <a href="<?php echo BASE_URL; ?>/public/<?php echo htmlspecialchars($order['finished_image']); ?>" target="_blank">
                            <img src="<?php echo BASE_URL; ?>/public/<?php echo htmlspecialchars($order['finished_image']); ?>"
                                 alt="Finished Product"
                                 style="width: 100%; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); cursor: pointer;"
                                 onerror="this.parentElement.innerHTML='<p style=color:#dc3545;>Image not available</p>'">
                        </a>
                        <p style="text-align:center; color:#6c757d; font-size:13px; margin-top:8px;"><i class="fas fa-search-plus"></i> Click to view full size</p>
                    </div>
                    <div>
                        <?php if (!empty($order['employee_name'])): ?>
                        <div class="detail-row">
                            <div class="detail-label">Crafted by:</div>
                            <div class="detail-value"><strong><?php echo htmlspecialchars($order['employee_name']); ?></strong></div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($order['actual_hours'])): ?>
                        <div class="detail-row">
                            <div class="detail-label">Production Hours:</div>
                            <div class="detail-value"><?php echo number_format($order['actual_hours'], 1); ?> hours</div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($order['materials_used'])): ?>
                        <div class="detail-row" style="flex-direction: column;">
                            <div class="detail-label" style="width:100%; margin-bottom:5px;">Materials Used:</div>
                            <div class="detail-value" style="background:#f8f9fa; padding:10px; border-radius:6px; white-space:pre-wrap;"><?php echo htmlspecialchars($order['materials_used']); ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($order['completion_notes'])): ?>
                        <div class="detail-row" style="flex-direction: column; margin-top:10px;">
                            <div class="detail-label" style="width:100%; margin-bottom:5px;">Completion Notes:</div>
                            <div class="detail-value" style="background:#e8f5e9; padding:10px; border-radius:6px;"><?php echo htmlspecialchars($order['completion_notes']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($order['status'] === 'ready_for_delivery' && $remainingBalance > 0): ?>
                <div style="background:#fff3cd; padding:15px; border-radius:8px; margin-top:20px; border-left:4px solid #ffc107;">
                    <strong><i class="fas fa-exclamation-triangle me-2" style="color:#856404;"></i>Action Required:</strong>
                    Your furniture is ready. Please pay the remaining balance of <strong style="color:#dc3545;">ETB <?php echo number_format($remainingBalance, 2); ?></strong> to arrange delivery.
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Payment Information -->
            <div class="detail-card">
                <div class="section-title">Payment Information</div>
                <?php if ($totalCost > 0): ?>
                <div class="detail-row">
                    <div class="detail-label">Total Cost:</div>
                    <div class="detail-value"><strong style="font-size: 1.2rem; color: #8B4513;">ETB <?php echo number_format($totalCost, 2); ?></strong></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Deposit (40%):</div>
                    <div class="detail-value">ETB <?php echo number_format($order['deposit_amount'] ?? ($totalCost * 0.4), 2); ?></div>
                </div>
                <?php if ($depositPaid > 0): ?>
                <div class="detail-row">
                    <div class="detail-label">Deposit Paid:</div>
                    <div class="detail-value"><strong style="color: #28a745;">ETB <?php echo number_format($depositPaid, 2); ?></strong></div>
                </div>
                <?php endif; ?>
                <?php if ($finalPaid > 0): ?>
                <div class="detail-row">
                    <div class="detail-label">Final Payment:</div>
                    <div class="detail-value"><strong style="color: #28a745;">ETB <?php echo number_format($finalPaid, 2); ?></strong></div>
                </div>
                <?php endif; ?>
                <?php if ($depositPaid > 0): ?>
                <div class="detail-row">
                    <div class="detail-label">Remaining Balance:</div>
                    <div class="detail-value">
                        <?php if ($remainingBalance <= 0 || $order['status'] === 'completed'): ?>
                            <strong style="color: #28a745;"><i class="fas fa-check-circle me-1"></i>Fully Paid</strong>
                        <?php elseif ($order['status'] === 'final_payment_paid'): ?>
                            <strong style="color: #17a2b8;"><i class="fas fa-clock me-1"></i>Payment Under Review</strong>
                        <?php else: ?>
                            <strong style="color: #dc3545;">ETB <?php echo number_format($remainingBalance, 2); ?></strong>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="detail-row">
                    <div class="detail-label">Payment Status:</div>
                    <div class="detail-value">
                        <span style="color: #856404; background: #fff3cd; padding: 5px 12px; border-radius: 6px; font-size: 13px;">
                            <i class="fas fa-hourglass-half me-1"></i>Awaiting deposit payment
                        </span>
                    </div>
                </div>
                <?php endif; ?>
                <?php else: ?>
                <div class="detail-row">
                    <div class="detail-label">Payment Status:</div>
                    <div class="detail-value">
                        <span style="color: #856404; background: #fff3cd; padding: 5px 12px; border-radius: 6px; font-size: 13px;">
                            <i class="fas fa-hourglass-half me-1"></i>Awaiting cost estimation
                        </span>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Customer Information -->
            <div class="detail-card">
                <div class="section-title">Customer Information</div>
                <div class="detail-row">
                    <div class="detail-label">Name:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($order['customer_name'] ?: 'N/A'); ?></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Email:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($order['customer_email']); ?></div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="detail-card">
                <div class="d-flex gap-3 justify-content-center">
                    <a href="<?php echo BASE_URL; ?>/public/customer/my-orders" class="btn btn-back">
                        <i class="fas fa-arrow-left me-2"></i>Back to Orders
                    </a>
                    <?php if ($status === 'cost_estimated' && isset($order['deposit_amount']) && $order['deposit_amount'] > 0): ?>
                        <a href="<?php echo BASE_URL; ?>/public/customer/pay-deposit?order_id=<?php echo $orderId; ?>" class="btn btn-success">
                            <i class="fas fa-credit-card me-2"></i>Pay Deposit (ETB <?php echo number_format($order['deposit_amount'], 2); ?>)
                        </a>
                    <?php elseif ($remainingBalance > 0 && in_array($status, ['ready_for_delivery'])): ?>
                        <a href="<?php echo BASE_URL; ?>/public/customer/pay-remaining?order_id=<?php echo $orderId; ?>" class="btn btn-danger" style="font-size:16px; padding:12px 30px;">
                            <i class="fas fa-credit-card me-2"></i>Pay Final Balance — ETB <?php echo number_format($remainingBalance, 2); ?>
                        </a>
                    <?php elseif ($status === 'final_payment_paid'): ?>
                        <span class="btn btn-secondary" style="cursor:default;"><i class="fas fa-clock me-2"></i>Payment Under Review</span>
                    <?php elseif ($status === 'completed'): ?>
                        <span class="btn btn-success" style="cursor:default;"><i class="fas fa-check-circle me-2"></i>Order Completed</span>
                    <?php endif; ?>
                    <a href="#" class="btn btn-primary" onclick="printOrder(); return false;">
                        <i class="fas fa-print me-2"></i>Print Order
                    </a>
                </div>
            </div>
        </div>

    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
    <script>
    function printOrder() {
        // Hide rating section temporarily if present
        const ratingCard = document.querySelector('#ratingForm');
        if (ratingCard) {
            const card = ratingCard.closest('.detail-card');
            if (card) card.style.display = 'none';
        }
        window.print();
        // Restore after print dialog closes
        setTimeout(function() {
            const ratingCard2 = document.querySelector('#ratingForm');
            if (ratingCard2) {
                const card = ratingCard2.closest('.detail-card');
                if (card) card.style.display = '';
            }
        }, 1000);
    }
    </script>
</body>
</html>
