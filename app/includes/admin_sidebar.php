<?php
/**
 * Admin Sidebar Component
 * Reusable sidebar for all admin pages
 */

$currentPage = $_SERVER['REQUEST_URI'];
$adminName = $_SESSION['user_name'] ?? 'Admin User';

// Get notification counts
$notificationCounts = [
    'pending_orders' => 0,
    'low_stock' => 0,
    'pending_payments' => 0,
    'unread_messages' => 0,
    'pending_reviews' => 0
];

try {
    // Pending orders awaiting approval
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM furn_orders WHERE status IN ('pending_review', 'pending_cost_approval')");
    $stmt->execute();
    $notificationCounts['pending_orders'] = $stmt->fetchColumn();

    // Low stock materials
    $stmt = $pdo->query("SELECT COUNT(*) FROM furn_materials WHERE current_stock < minimum_stock");
    $notificationCounts['low_stock'] = $stmt->fetchColumn();

    // Pending payments
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM furn_payments WHERE status = 'pending'");
    $stmt->execute(['pending']);
    $notificationCounts['pending_payments'] = $stmt->fetchColumn();

    // Check if messages table exists
    $tables = $pdo->query("SHOW TABLES LIKE 'furn_messages'")->fetchAll();
    if (!empty($tables)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM furn_messages WHERE receiver_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $notificationCounts['unread_messages'] = $stmt->fetchColumn();
    }

    // Pending reviews/ratings
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM furn_ratings WHERE rating IS NOT NULL");
        $notificationCounts['pending_reviews'] = $stmt->fetchColumn();
    } catch (PDOException $e) { /* ratings table may not exist */ }
} catch (PDOException $e) {
    error_log("Admin notification counts error: " . $e->getMessage());
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
                <div style="font-size: 11px; opacity: 0.8; margin-top: 2px;">Admin Panel</div>
            </div>
        </div>
    </div>

    <!-- Navigation Menu -->
    <ul class="sidebar-menu">
        <!-- Dashboard -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/admin/dashboard" class="<?php echo isActive('/admin/dashboard'); ?>">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <!-- Orders -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/admin/orders" class="<?php echo isActive('/admin/orders'); ?>">
                <i class="fas fa-box"></i>
                <span>Orders</span>
                <?php if($notificationCounts['pending_orders'] > 0): ?>
                    <span class="menu-badge"><?php echo $notificationCounts['pending_orders']; ?></span>
                <?php endif; ?>
            </a>
        </li>

        <!-- Users -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/admin/users" class="<?php echo isActive('/admin/users'); ?>">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
        </li>

        <!-- Products -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/admin/products" class="<?php echo isActive('/admin/products'); ?>">
                <i class="fas fa-couch"></i>
                <span>Products</span>
            </a>
        </li>

        <!-- Raw Materials -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/admin/materials" class="<?php echo isActive('/admin/materials'); ?>">
                <i class="fas fa-boxes"></i>
                <span>Raw Materials</span>
                <?php if($notificationCounts['low_stock'] > 0): ?>
                    <span class="menu-badge badge-danger"><?php echo $notificationCounts['low_stock']; ?></span>
                <?php endif; ?>
            </a>
        </li>

        <!-- Employees -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/admin/employees" class="<?php echo isActive('/admin/employees'); ?>">
                <i class="fas fa-user-tie"></i>
                <span>Employees</span>
            </a>
        </li>

        <!-- Attendance -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/admin/attendance" class="<?php echo isActive('/admin/attendance'); ?>">
                <i class="fas fa-clipboard-user"></i>
                <span>Attendance</span>
            </a>
        </li>

        <!-- Payroll -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/admin/payroll" class="<?php echo isActive('/admin/payroll'); ?>">
                <i class="fas fa-money-bill-wave"></i>
                <span>Payroll</span>
            </a>
        </li>

        <!-- Reports -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/admin/reports" class="<?php echo isActive('/admin/reports'); ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
        </li>

        <!-- Messages -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/admin/messages" class="<?php echo isActive('/admin/messages'); ?>">
                <i class="fas fa-envelope"></i>
                <span>Messages</span>
                <?php if($notificationCounts['unread_messages'] > 0): ?>
                    <span class="menu-badge badge-info"><?php echo $notificationCounts['unread_messages']; ?></span>
                <?php endif; ?>
            </a>
        </li>

        <!-- Settings -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/admin/settings" class="<?php echo isActive('/admin/settings'); ?>">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
        </li>

        <!-- My Profile -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/admin/profile" class="<?php echo isActive('/admin/profile'); ?>">
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
