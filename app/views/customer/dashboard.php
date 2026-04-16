<?php
// Customer authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}

// Get database connection
require_once __DIR__ . '/../../../config/db_config.php';

$customerId = $_SESSION['user_id'];
$customerName = $_SESSION['first_name'] ?? $_SESSION['user_name'] ?? 'Customer';

// Fetch statistics
$stats = [
    'total_orders'     => 0,
    'pending_orders'   => 0,
    'in_production'    => 0,
    'completed_orders' => 0,
    'total_paid'       => 0,
    'pending_payment'  => 0,
    'wishlist_count'   => 0,
    'open_complaints'  => 0,
];

$customerId = (int) $customerId;

try {
    // Total orders
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM furn_orders WHERE customer_id = ?");
    $stmt->execute([$customerId]);
    $stats['total_orders'] = (int)$stmt->fetchColumn();

    // Pending / awaiting action
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM furn_orders WHERE customer_id = ? AND status IN ('pending_review','pending_cost_approval','cost_estimated')");
    $stmt->execute([$customerId]);
    $stats['pending_orders'] = (int)$stmt->fetchColumn();

    // In production
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM furn_orders WHERE customer_id = ? AND status IN ('in_production','payment_verified','deposit_paid')");
    $stmt->execute([$customerId]);
    $stats['in_production'] = (int)$stmt->fetchColumn();

    // Completed
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM furn_orders WHERE customer_id = ? AND status = 'completed'");
    $stmt->execute([$customerId]);
    $stats['completed_orders'] = (int)$stmt->fetchColumn();

    // Total amount paid (approved/verified payments)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(p.amount),0) FROM furn_payments p JOIN furn_orders o ON p.order_id=o.id WHERE o.customer_id=? AND p.status IN ('approved','verified')");
    $stmt->execute([$customerId]);
    $stats['total_paid'] = floatval($stmt->fetchColumn());

    // Orders awaiting payment (ready_for_delivery)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM furn_orders WHERE customer_id = ? AND status IN ('ready_for_delivery','cost_estimated')");
    $stmt->execute([$customerId]);
    $stats['pending_payment'] = (int)$stmt->fetchColumn();

    // Wishlist count
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM furn_wishlist WHERE customer_id = ?");
        $stmt->execute([$customerId]);
        $stats['wishlist_count'] = (int)$stmt->fetchColumn();
    } catch(PDOException $e2) {}

    // Open complaints
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM furn_complaints WHERE customer_id = ? AND status = 'open'");
        $stmt->execute([$customerId]);
        $stats['open_complaints'] = (int)$stmt->fetchColumn();
    } catch(PDOException $e2) {}

} catch (PDOException $e) {
    error_log("Customer dashboard stats error: " . $e->getMessage());
}

// Fetch recent orders
$recentOrders = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM furn_orders WHERE customer_id = ? ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$customerId]);
    $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Recent orders error: " . $e->getMessage());
}

$pageTitle = 'Customer Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Customer Dashboard'; ?> - FurnitureCraft</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/css/admin-responsive.css">
    <style>
        .top-header { position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; width: 100% !important; z-index: 1998 !important; }
        @media (min-width: 1024px) { .top-header { left: 260px !important; width: calc(100% - 260px) !important; } }
        /* Page-specific styles */
        .kpi-grid { display: grid; grid-template-columns: 1fr; gap: 15px; margin-bottom: 25px; }
        @media (min-width: 768px)  { .kpi-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (min-width: 1024px) { .kpi-grid { grid-template-columns: repeat(4, 1fr); gap: 20px; } }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" aria-label="Toggle Menu">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay (for mobile) -->
    <div class="sidebar-overlay"></div>

    <!-- Sidebar -->
    <?php include_once __DIR__ . '/../../includes/customer_sidebar.php'; ?>

    <!-- Top Header -->
    <?php 
    $pageTitle = 'Customer Dashboard';
    include_once __DIR__ . '/../../includes/customer_header.php'; 
    ?>

    <!-- Main Content -->
    <div class="main-content">
        <h2 style="margin-bottom: 20px; color: #2c3e50;">Welcome, <?php echo htmlspecialchars($customerName); ?>!</h2>

        <?php
        // Check for orders awaiting payment (cost_estimated)
        try {
            $stmtPay = $pdo->prepare("SELECT id, order_number, furniture_name, furniture_type, estimated_cost, deposit_amount FROM furn_orders WHERE customer_id = ? AND status = 'cost_estimated' ORDER BY created_at DESC");
            $stmtPay->execute([$customerId]);
            $paymentPendingOrders = $stmtPay->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $paymentPendingOrders = [];
        }
        ?>

        
        <!-- KPI Cards -->
        <div class="kpi-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));">
            <?php
            $custCards = [
                [$stats['total_orders'],   'Total Orders',      BASE_URL.'/public/customer/my-orders',  'kpi-blue',   'fa-shopping-cart'],
                [$stats['pending_orders'], 'Pending / Awaiting',BASE_URL.'/public/customer/my-orders',  'kpi-orange', 'fa-clock'],
                [$stats['in_production'],  'In Production',     BASE_URL.'/public/customer/my-orders',  'kpi-brown',  'fa-cogs'],
                [$stats['completed_orders'],'Completed Orders', BASE_URL.'/public/customer/my-orders',  'kpi-green',  'fa-check-circle'],
                ['ETB '.number_format($stats['total_paid'],0), 'Total Paid', BASE_URL.'/public/customer/payments', 'kpi-purple', 'fa-money-bill-wave'],
            ];
            foreach ($custCards as [$v,$l,$href,$cls,$i]): ?>
            <a href="<?php echo $href; ?>" style="text-decoration:none;">
                <div class="kpi-card <?php echo $cls; ?>" style="cursor:pointer;transition:transform .15s,box-shadow .15s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,0.12)';" onmouseout="this.style.transform='';this.style.boxShadow='';">
                    <div class="kpi-icon"><i class="fas <?php echo $i; ?>"></i></div>
                    <div class="kpi-value"><?php echo $v; ?></div>
                    <div class="kpi-label"><?php echo $l; ?></div>
                </div>
            </a>
            <?php endforeach; ?>
            <?php if ($stats['pending_payment'] > 0): ?>
            <a href="<?php echo BASE_URL; ?>/public/customer/payments" style="text-decoration:none;">
                <div class="kpi-card kpi-red" style="cursor:pointer;transition:transform .15s,box-shadow .15s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,0.12)';" onmouseout="this.style.transform='';this.style.boxShadow='';">
                    <div class="kpi-icon"><i class="fas fa-exclamation-circle"></i></div>
                    <div class="kpi-value"><?php echo $stats['pending_payment']; ?></div>
                    <div class="kpi-label">Payment Due</div>
                </div>
            </a>
            <?php endif; ?>
            <?php if ($stats['wishlist_count'] > 0): ?>
            <a href="<?php echo BASE_URL; ?>/public/customer/wishlist" style="text-decoration:none;">
                <div class="kpi-card kpi-orange" style="cursor:pointer;transition:transform .15s,box-shadow .15s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,0.12)';" onmouseout="this.style.transform='';this.style.boxShadow='';">
                    <div class="kpi-icon"><i class="fas fa-heart"></i></div>
                    <div class="kpi-value"><?php echo $stats['wishlist_count']; ?></div>
                    <div class="kpi-label">Wishlist Items</div>
                </div>
            </a>
            <?php endif; ?>
            <?php if ($stats['open_complaints'] > 0): ?>
            <a href="<?php echo BASE_URL; ?>/public/customer/my-orders" style="text-decoration:none;">
                <div class="kpi-card kpi-red" style="cursor:pointer;transition:transform .15s,box-shadow .15s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,0.12)';" onmouseout="this.style.transform='';this.style.boxShadow='';">
                    <div class="kpi-icon"><i class="fas fa-exclamation-triangle"></i></div>
                    <div class="kpi-value"><?php echo $stats['open_complaints']; ?></div>
                    <div class="kpi-label">Open Complaints</div>
                </div>
            </a>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="section-card">
            <div class="section-title"><i class="fas fa-bolt me-2"></i>Quick Actions</div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 10px;">
                <a href="<?php echo BASE_URL; ?>/public/customer/create-order" style="padding: 10px 8px; background: #3498DB; color: white; border-radius: 8px; text-align: center; text-decoration: none; font-weight: 600; font-size: 12px; transition: opacity 0.2s;" onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                    <i class="fas fa-plus-circle" style="font-size:18px;display:block;margin-bottom:5px;"></i>Create Order
                </a>
                <a href="<?php echo BASE_URL; ?>/public/customer/my-orders" style="padding: 10px 8px; background: #27AE60; color: white; border-radius: 8px; text-align: center; text-decoration: none; font-weight: 600; font-size: 12px; transition: opacity 0.2s;" onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                    <i class="fas fa-list" style="font-size:18px;display:block;margin-bottom:5px;"></i>My Orders
                </a>
                <a href="<?php echo BASE_URL; ?>/public/customer/payments" style="padding: 10px 8px; background: #F39C12; color: white; border-radius: 8px; text-align: center; text-decoration: none; font-weight: 600; font-size: 12px; transition: opacity 0.2s;" onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                    <i class="fas fa-credit-card" style="font-size:18px;display:block;margin-bottom:5px;"></i>Make Payment
                </a>
                <a href="<?php echo BASE_URL; ?>/public/customer/invoices" style="padding: 10px 8px; background: #9B59B6; color: white; border-radius: 8px; text-align: center; text-decoration: none; font-weight: 600; font-size: 12px; transition: opacity 0.2s;" onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                    <i class="fas fa-file-invoice" style="font-size:18px;display:block;margin-bottom:5px;"></i>View Invoices
                </a>
                <a href="<?php echo BASE_URL; ?>/public/customer/gallery" style="padding: 10px 8px; background: #8B4513; color: white; border-radius: 8px; text-align: center; text-decoration: none; font-weight: 600; font-size: 12px; transition: opacity 0.2s;" onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                    <i class="fas fa-images" style="font-size:18px;display:block;margin-bottom:5px;"></i>Browse Designs
                </a>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="section-card">
            <div class="section-title"><i class="fas fa-box me-2"></i>Recent Orders</div>
            <?php if (empty($recentOrders)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No orders yet. <a href="<?php echo BASE_URL; ?>/public/customer/create_order" style="color: #3498DB; text-decoration: none;">Create your first order</a></p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Order Number</th>
                                <th>Furniture</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($order['furniture_name']); ?></td>
                                <td>ETB <?php echo number_format($order['estimated_cost'] ?? $order['total_amount'] ?? 0, 2); ?></td>
                                <td><span class="status-badge status-<?php echo htmlspecialchars($order['status']); ?>"><?php echo ucwords(str_replace('_', ' ', $order['status'])); ?></span></td>
                                <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                                <td>
                                    <?php if ($order['status'] === 'cost_estimated'): ?>
                                        <a href="<?php echo BASE_URL; ?>/public/customer/payments" style="background: #27AE60; color: white; padding: 6px 14px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 13px;">
                                            <i class="fas fa-credit-card me-1"></i>Pay Now
                                        </a>
                                    <?php else: ?>
                                        <a href="<?php echo BASE_URL; ?>/public/customer/order_details?id=<?php echo $order['id']; ?>" class="btn-action btn-primary-custom">View</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Mobile Menu Script -->
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
