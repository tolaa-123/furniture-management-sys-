<?php
// Session and authentication already handled by index.php
$customerId = $_SESSION['user_id'] ?? 0;
$customerName = $_SESSION['first_name'] ?? $_SESSION['user_name'] ?? 'Customer';

// Get database connection
require_once __DIR__ . '/../../../config/db_config.php';

// Get order ID from URL
$orderId = $_GET['id'] ?? 0;

// Fetch order details
$order = null;
if ($orderId > 0 && $customerId > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                o.*,
                CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                u.email as customer_email
            FROM furn_orders o
            LEFT JOIN furn_users u ON o.customer_id = u.id
            WHERE o.id = ? AND o.customer_id = ?
        ");
        $stmt->execute([$orderId, $customerId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching order: " . $e->getMessage());
    }
}

// Redirect if order not found
if (!$order) {
    header('Location: ' . BASE_URL . '/public/customer/my-orders');
    exit();
}

$pageTitle = 'Track Order';
$currentStatus = $order['status'] ?? 'pending_review';

// Define order workflow stages (must match actual DB status values)
$stages = [
    'pending_cost_approval' => ['label' => 'Order Placed', 'icon' => 'fa-shopping-cart', 'color' => '#3498db'],
    'cost_estimated'        => ['label' => 'Cost Estimated', 'icon' => 'fa-calculator', 'color' => '#9b59b6'],
    'deposit_paid'          => ['label' => 'Deposit Paid', 'icon' => 'fa-credit-card', 'color' => '#f39c12'],
    'payment_verified'      => ['label' => 'Payment Verified', 'icon' => 'fa-check-circle', 'color' => '#27ae60'],
    'in_production'         => ['label' => 'In Production', 'icon' => 'fa-hammer', 'color' => '#e67e22'],
    'ready_for_delivery'    => ['label' => 'Ready for Delivery', 'icon' => 'fa-truck', 'color' => '#16a085'],
    'final_payment_paid'    => ['label' => 'Final Payment Submitted', 'icon' => 'fa-money-bill-wave', 'color' => '#2980b9'],
    'completed'             => ['label' => 'Completed', 'icon' => 'fa-flag-checkered', 'color' => '#2ecc71'],
];

// Normalise legacy/alias statuses to their canonical stage key
$statusAliasMap = [
    'pending'        => 'pending_cost_approval',
    'pending_review' => 'pending_cost_approval',
];
$canonicalStatus = $statusAliasMap[$currentStatus] ?? $currentStatus;

// Determine which stages are completed
$stageOrder = array_keys($stages);
$currentIndex = array_search($canonicalStatus, $stageOrder);
if ($currentIndex === false) $currentIndex = 0;
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
        .tracking-header { background: linear-gradient(135deg, #8B4513, #5D4037); color: #fff; border-radius: 12px; padding: 25px; margin-bottom: 30px; box-shadow: 0 8px 25px rgba(139,69,19,0.2); }
        .tracking-card { background: white; border-radius: 12px; padding: 30px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .timeline { position: relative; padding: 20px 0; }
        .timeline-item { position: relative; padding: 30px 0 30px 80px; }
        .timeline-item:not(:last-child)::before { content: ''; position: absolute; left: 30px; top: 60px; bottom: -30px; width: 3px; background: #e0e0e0; }
        .timeline-icon { position: absolute; left: 0; top: 30px; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 24px; color: white; z-index: 1; }
        .timeline-content { background: #f8f9fa; padding: 20px; border-radius: 10px; border-left: 4px solid #e0e0e0; }
        .timeline-item.completed .timeline-icon { box-shadow: 0 0 0 4px rgba(46,204,113,0.2); }
        .timeline-item.completed .timeline-content { border-left-color: #27ae60; background: #e8f8f5; }
        .timeline-item.active .timeline-icon { box-shadow: 0 0 0 4px rgba(52,152,219,0.3); animation: tpulse 2s infinite; }
        .timeline-item.active .timeline-content { border-left-color: #3498db; background: #ebf5fb; }
        .timeline-item.pending .timeline-icon { background: #bdc3c7; }
        .timeline-item.pending .timeline-content { opacity: 0.6; }
        @keyframes tpulse { 0%,100%{box-shadow:0 0 0 4px rgba(52,152,219,0.3);}50%{box-shadow:0 0 0 8px rgba(52,152,219,0.1);} }
        .order-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .summary-item { background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center; }
        .summary-label { font-size: 12px; color: #7f8c8d; text-transform: uppercase; margin-bottom: 5px; }
        .summary-value { font-size: 18px; font-weight: 700; color: #2c3e50; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; border-radius: 8px; font-weight: 600; font-size: 14px; text-decoration: none; border: none; cursor: pointer; font-family: inherit; }
        .btn-primary { background: #3498db; color: white; }
        .btn-success { background: #27ae60; color: white; }
        .btn-info    { background: #17a2b8; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-light   { background: white; color: #4a2c2a; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 10px; font-size: 11px; font-weight: 700; }
        .bg-primary { background: #3498db; color: white; }
        .text-success { color: #27ae60; }
        .d-flex { display: flex; }
        .justify-content-between { justify-content: space-between; }
        .justify-content-center { justify-content: center; }
        .align-items-center { align-items: center; }
        .flex-wrap { flex-wrap: wrap; }
        .gap-3 { gap: 12px; }
        .mb-1 { margin-bottom: 4px; }
        .mb-3 { margin-bottom: 16px; }
        .mb-4 { margin-bottom: 20px; }
        .ms-2 { margin-left: 8px; }
        .opacity-75 { opacity: .75; }
        .h2 { font-size: 1.5rem; font-weight: 700; margin: 0; }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>
    <div class="sidebar-overlay"></div>

    <?php include_once __DIR__ . '/../../includes/customer_sidebar.php'; ?>
    <?php $pageTitle = 'Track Order'; include_once __DIR__ . '/../../includes/customer_header.php'; ?>

    <div class="main-content">
            <div class="tracking-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h2 mb-1"><i class="fas fa-map-marker-alt me-2"></i>Track Your Order</h1>
                        <div class="opacity-75">Order #<?php echo htmlspecialchars($order['order_number']); ?></div>
                    </div>
                    <a href="<?php echo BASE_URL; ?>/public/customer/my-orders" class="btn btn-light">
                        <i class="fas fa-arrow-left me-2"></i>Back to Orders
                    </a>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="tracking-card">
                <h3 class="mb-3"><i class="fas fa-info-circle me-2"></i>Order Summary</h3>
                <div class="order-summary">
                    <div class="summary-item">
                        <div class="summary-label">Furniture</div>
                        <div class="summary-value"><?php echo htmlspecialchars($order['furniture_name']); ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Order Date</div>
                        <div class="summary-value"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Total Cost</div>
                        <div class="summary-value">ETB <?php echo number_format($order['estimated_cost'], 2); ?></div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-label">Current Status</div>
                        <div class="summary-value" style="color: <?php echo $stages[$currentStatus]['color'] ?? '#95a5a6'; ?>;">
                            <?php echo ucwords(str_replace('_', ' ', $currentStatus)); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Timeline -->
            <div class="tracking-card">
                <h3 class="mb-4"><i class="fas fa-route me-2"></i>Order Progress</h3>
                <div class="timeline">
                    <?php foreach ($stages as $stageKey => $stage): 
                        $stageIndex = array_search($stageKey, $stageOrder);
                        $isCompleted = $stageIndex < $currentIndex;
                        $isActive = $stageIndex === $currentIndex;
                        $isPending = $stageIndex > $currentIndex;
                        $statusClass = $isCompleted ? 'completed' : ($isActive ? 'active' : 'pending');
                        $iconBg = $isCompleted || $isActive ? $stage['color'] : '#bdc3c7';
                    ?>
                    <div class="timeline-item <?php echo $statusClass; ?>">
                        <div class="timeline-icon" style="background: <?php echo $iconBg; ?>;">
                            <i class="fas <?php echo $stage['icon']; ?>"></i>
                        </div>
                        <div class="timeline-content">
                            <h5 style="margin: 0 0 10px 0; color: #2c3e50;">
                                <?php echo $stage['label']; ?>
                                <?php if ($isCompleted): ?>
                                    <i class="fas fa-check-circle text-success ms-2"></i>
                                <?php elseif ($isActive): ?>
                                    <span class="badge bg-primary ms-2">In Progress</span>
                                <?php endif; ?>
                            </h5>
                            <p style="margin: 0; color: #7f8c8d; font-size: 14px;">
                                <?php
                                if ($stageKey === 'pending_cost_approval') {
                                    echo $isCompleted || $isActive ? 'Your order has been received and is being reviewed.' : 'Order will be reviewed by our team.';
                                } elseif ($stageKey === 'cost_estimated') {
                                    echo $isCompleted || $isActive ? 'Cost estimation completed. Please proceed with deposit payment.' : 'Manager will estimate the cost for your custom furniture.';
                                } elseif ($stageKey === 'deposit_paid') {
                                    echo $isCompleted || $isActive ? 'Deposit payment received. Awaiting verification.' : 'You will need to pay 40% deposit to proceed.';
                                } elseif ($stageKey === 'payment_verified') {
                                    echo $isCompleted || $isActive ? 'Payment verified successfully. Order will be assigned to production.' : 'Manager will verify your payment.';
                                } elseif ($stageKey === 'in_production') {
                                    echo $isCompleted || $isActive ? 'Your furniture is being crafted by our skilled artisans.' : 'Production will begin after payment verification.';
                                } elseif ($stageKey === 'ready_for_delivery') {
                                    echo $isCompleted || $isActive ? 'Your furniture is ready! Please pay remaining balance for delivery.' : 'You will be notified when ready for delivery.';
                                } elseif ($stageKey === 'final_payment_paid') {
                                    echo $isCompleted || $isActive ? 'Final payment received. Awaiting manager verification.' : 'Pay the remaining balance to complete your order.';
                                } elseif ($stageKey === 'completed') {
                                    echo $isCompleted || $isActive ? 'Order completed! Thank you for choosing us.' : 'Final stage after delivery.';
                                }
                                ?>
                            </p>
                            <?php if ($isCompleted && $stageKey === 'cost_estimated'): ?>
                                <div style="margin-top: 10px;">
                                    <strong>Estimated Cost:</strong> ETB <?php echo number_format($order['estimated_cost'], 2); ?><br>
                                    <strong>Deposit Required:</strong> ETB <?php echo number_format($order['deposit_amount'], 2); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="tracking-card">
                <div class="d-flex gap-3 justify-content-center flex-wrap">
                    <a href="<?php echo BASE_URL; ?>/public/customer/order-details?id=<?php echo $orderId; ?>" class="btn btn-primary">
                        <i class="fas fa-eye me-2"></i>View Full Details
                    </a>
                    <?php if ($currentStatus === 'cost_estimated'): ?>
                        <a href="<?php echo BASE_URL; ?>/public/customer/pay-deposit?order_id=<?php echo $orderId; ?>" class="btn btn-success">
                            <i class="fas fa-credit-card me-2"></i>Pay Deposit
                        </a>
                    <?php elseif ($currentStatus === 'ready_for_delivery'): ?>
                        <a href="<?php echo BASE_URL; ?>/public/customer/pay-remaining?order_id=<?php echo $orderId; ?>" class="btn btn-success">
                            <i class="fas fa-money-bill-wave me-2"></i>Pay Final Balance
                        </a>
                    <?php elseif ($currentStatus === 'payment_verified'): ?>
                        <a href="<?php echo BASE_URL; ?>/public/customer/payments" class="btn btn-info text-white">
                            <i class="fas fa-clock me-2"></i>Awaiting Production Assignment
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo BASE_URL; ?>/public/customer/my-orders" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Orders
                    </a>
                </div>
            </div>
    </div>
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
