<?php
/**
 * Manager Sidebar Component
 * Reusable sidebar for all manager pages
 */

$currentPage = $_SERVER['REQUEST_URI'];
$managerName = $_SESSION['user_name'] ?? 'Manager User';

// Get notification counts
$notificationCounts = [
    'pending_orders' => 0,
    'low_stock' => 0,
    'pending_payments' => 0,
    'unread_messages' => 0,
    'pending_material_requests' => 0
];

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM furn_orders WHERE status IN ('pending_cost_approval', 'pending_review', 'pending')");
    $stmt->execute();
    $notificationCounts['pending_orders'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM furn_materials WHERE current_stock < minimum_stock");
    $notificationCounts['low_stock'] = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM furn_material_requests WHERE status = 'pending'");
    $notificationCounts['pending_material_requests'] = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM furn_payments WHERE status = ?");
    $stmt->execute(['pending']);
    $notificationCounts['pending_payments'] = $stmt->fetchColumn();

    // Check if messages table exists
    $tables = $pdo->query("SHOW TABLES LIKE 'furn_messages'")->fetchAll();
    if (!empty($tables)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM furn_messages WHERE receiver_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $notificationCounts['unread_messages'] = $stmt->fetchColumn();
    }
} catch (PDOException $e) {
    error_log("Notification counts error: " . $e->getMessage());
}

function isActive($page) {
    global $currentPage;
    return strpos($currentPage, $page) !== false ? 'active' : '';
}
?>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <i class="fas fa-chair"></i>
            <div>
                <div style="font-size: 16px; font-weight: 700;">FURNITURECRAFT</div>
                <div style="font-size: 11px; opacity: 0.8; margin-top: 2px;">Workshop Manager</div>
            </div>
        </div>
    </div>

    <!-- Navigation Menu -->
    <ul class="sidebar-menu">
        <!-- Dashboard -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/manager/dashboard" class="<?php echo isActive('/manager/dashboard'); ?>">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <!-- Orders -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/manager/orders" class="<?php echo isActive('/manager/orders'); ?>">
                <i class="fas fa-box"></i>
                <span>Orders</span>
                <?php if($notificationCounts['pending_orders'] > 0): ?>
                    <span class="menu-badge"><?php echo $notificationCounts['pending_orders']; ?></span>
                <?php endif; ?>
            </a>
        </li>

        <!-- Cost Estimation -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/manager/cost-estimation" class="<?php echo isActive('/manager/cost-estimation'); ?>">
                <i class="fas fa-calculator"></i>
                <span>Cost Estimation</span>
            </a>
        </li>

        <!-- Assign Employee -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/manager/assign-employees" class="<?php echo isActive('/manager/assign-employees'); ?>">
                <i class="fas fa-user-plus"></i>
                <span>Assign Employee</span>
            </a>
        </li>

        <!-- Production -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/manager/production" class="<?php echo isActive('/manager/production'); ?>">
                <i class="fas fa-industry"></i>
                <span>Production</span>
            </a>
        </li>

        <!-- Completed Tasks -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/manager/completed-tasks" class="<?php echo isActive('/manager/completed-tasks'); ?>">
                <i class="fas fa-check-circle"></i>
                <span>Completed Tasks</span>
            </a>
        </li>

        <!-- Inventory (Raw Materials) -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/manager/inventory" class="<?php echo isActive('/manager/inventory'); ?>">
                <i class="fas fa-warehouse"></i>
                <span>Raw Materials</span>
                <?php if($notificationCounts['low_stock'] > 0): ?>
                    <span class="menu-badge badge-danger"><?php echo $notificationCounts['low_stock']; ?></span>
                <?php elseif($notificationCounts['pending_material_requests'] > 0): ?>
                    <span class="menu-badge badge-warning"><?php echo $notificationCounts['pending_material_requests']; ?></span>
                <?php endif; ?>
            </a>
        </li>

        <!-- Gallery Products -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/manager/manage-products" class="<?php echo isActive('/manager/manage-products'); ?>">
                <i class="fas fa-images"></i>
                <span>Gallery Products</span>
            </a>
        </li>

        <!-- Attendance -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/manager/attendance" class="<?php echo isActive('/manager/attendance'); ?>">
                <i class="fas fa-clock"></i>
                <span>Attendance</span>
            </a>
        </li>

        <!-- Payroll -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/manager/payroll" class="<?php echo isActive('/manager/payroll'); ?>">
                <i class="fas fa-money-bill-wave"></i>
                <span>Payroll</span>
            </a>
        </li>

        <!-- Payment Verification -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/manager/payments" class="<?php echo isActive('/manager/payments'); ?>">
                <i class="fas fa-credit-card"></i>
                <span>Payment Verification</span>
                <?php if($notificationCounts['pending_payments'] > 0): ?>
                    <span class="menu-badge badge-warning"><?php echo $notificationCounts['pending_payments']; ?></span>
                <?php endif; ?>
            </a>
        </li>

        <!-- Reports -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/manager/reports" class="<?php echo isActive('/manager/reports'); ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
        </li>

        <!-- Messages -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/manager/messages" class="<?php echo isActive('/manager/messages'); ?>">
                <i class="fas fa-comments"></i>
                <span>Messages</span>
                <?php if($notificationCounts['unread_messages'] > 0): ?>
                    <span class="menu-badge badge-info"><?php echo $notificationCounts['unread_messages']; ?></span>
                <?php endif; ?>
            </a>
        </li>

        <!-- My Profile -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/manager/profile" class="<?php echo isActive('/manager/profile'); ?>">
                <i class="fas fa-user-circle"></i>
                <span>My Profile</span>
            </a>
        </li>

        <!-- Dark Mode Toggle -->
        <li style="padding: 10px 15px;">
            <button id="darkModeToggle" class="dark-mode-toggle w-100">
                <i class="fas fa-moon"></i> Dark Mode
            </button>
        </li>

        <!-- Logout -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</div>
<?php
// Flash toast support - render hidden element if session flash exists
if (!empty($_SESSION['flash_message'])): ?>
<div id="flash-toast" data-message="<?php echo htmlspecialchars($_SESSION['flash_message']); ?>" data-type="<?php echo htmlspecialchars($_SESSION['flash_type'] ?? 'success'); ?>" style="display:none;"></div>
<?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); endif; ?>
