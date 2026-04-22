<?php
/**
 * Employee Sidebar Component
 * Reusable sidebar for all employee pages
 */

$currentPage = $_SERVER['REQUEST_URI'];
$employeeName = $_SESSION['user_name'] ?? 'Employee User';

// Get notification counts
$notificationCounts = [
    'pending_tasks' => 0,
    'unread_messages' => 0,
    'pending_requests' => 0,
    'unread_feedback' => 0
];

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM furn_production_tasks WHERE employee_id = ? AND status = 'pending'");
    $stmt->execute([$_SESSION['user_id']]);
    $notificationCounts['pending_tasks'] = $stmt->fetchColumn();

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM furn_messages WHERE receiver_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $notificationCounts['unread_messages'] = $stmt->fetchColumn();
    } catch (PDOException $e) { /* messages table may not exist */ }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM furn_material_requests WHERE employee_id = ? AND status = 'pending'");
    $stmt->execute([$_SESSION['user_id']]);
    $notificationCounts['pending_requests'] = $stmt->fetchColumn();

    // Unread manager feedback on usage reports
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM furn_report_feedback WHERE employee_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $notificationCounts['unread_feedback'] = $stmt->fetchColumn();
    } catch (PDOException $e) { /* table may not exist yet */ }
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
            <i class="fas fa-tools"></i>
            <div>
                <div style="font-size: 16px; font-weight: 700;">FURNITURECRAFT</div>
                <div style="font-size: 11px; opacity: 0.8; margin-top: 2px;">Production Employee</div>
            </div>
        </div>
    </div>

    <!-- Navigation Menu -->
    <ul class="sidebar-menu">
        <!-- Dashboard -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/employee/dashboard" class="<?php echo isActive('/employee/dashboard'); ?>">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <!-- My Tasks -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/employee/tasks" class="<?php echo isActive('/employee/tasks'); ?>">
                <i class="fas fa-tasks"></i>
                <span>My Tasks</span>
                <?php if($notificationCounts['pending_tasks'] > 0): ?>
                    <span class="menu-badge"><?php echo $notificationCounts['pending_tasks']; ?></span>
                <?php endif; ?>
            </a>
        </li>

        <!-- Manage Orders -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/employee/orders" class="<?php echo isActive('/employee/orders'); ?>">
                <i class="fas fa-shopping-cart"></i>
                <span>Manage Orders</span>
            </a>
        </li>

        <!-- Customers -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/employee/customers" class="<?php echo isActive('/employee/customers'); ?>">
                <i class="fas fa-users"></i>
                <span>Customers</span>
            </a>
        </li>

        <!-- Products -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/employee/products" class="<?php echo isActive('/employee/products'); ?>">
                <i class="fas fa-couch"></i>
                <span>Products</span>
            </a>
        </li>

        <!-- Materials -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/employee/materials" class="<?php echo isActive('/employee/materials'); ?>">
                <i class="fas fa-boxes"></i>
                <span>Materials</span>
                <?php if($notificationCounts['pending_requests'] > 0): ?>
                    <span class="menu-badge badge-warning"><?php echo $notificationCounts['pending_requests']; ?></span>
                <?php endif; ?>
            </a>
        </li>

        <!-- Attendance -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/employee/attendance" class="<?php echo isActive('/employee/attendance'); ?>">
                <i class="fas fa-clock"></i>
                <span>Attendance</span>
            </a>
        </li>

        <!-- Payroll -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/employee/payroll" class="<?php echo isActive('/employee/payroll'); ?>">
                <i class="fas fa-file-invoice-dollar"></i>
                <span>My Payslips</span>
            </a>
        </li>

        <!-- Messages -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/employee/messages" class="<?php echo isActive('/employee/messages'); ?>">
                <i class="fas fa-envelope"></i>
                <span>Messages</span>
                <?php if($notificationCounts['unread_messages'] > 0): ?>
                    <span class="menu-badge badge-info"><?php echo $notificationCounts['unread_messages']; ?></span>
                <?php endif; ?>
            </a>
        </li>

        <!-- Notifications -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/employee/notifications" class="<?php echo isActive('/employee/notifications'); ?>">
                <i class="fas fa-bell"></i>
                <span>Notifications</span>
                <?php
                try {
                    $stmtN = $pdo->prepare("SELECT COUNT(*) FROM furn_notifications WHERE user_id = ? AND is_read = 0");
                    $stmtN->execute([$_SESSION['user_id']]);
                    $unreadNotifCount = (int)$stmtN->fetchColumn();
                    if ($unreadNotifCount > 0) echo '<span class="menu-badge badge-danger">' . ($unreadNotifCount > 9 ? '9+' : $unreadNotifCount) . '</span>';
                } catch (PDOException $e) {}
                ?>
            </a>
        </li>

        <!-- Reports -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/employee/reports" class="<?php echo isActive('/employee/reports'); ?>">
                <i class="fas fa-chart-line"></i>
                <span>My Performance</span>
                <?php if($notificationCounts['unread_feedback'] > 0): ?>
                    <span class="menu-badge badge-danger"><?php echo $notificationCounts['unread_feedback']; ?></span>
                <?php endif; ?>
            </a>
        </li>

        <!-- My Profile -->
        <li>
            <a href="<?php echo BASE_URL; ?>/public/employee/profile" class="<?php echo isActive('/employee/profile'); ?>">
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
<?php if (!empty($_SESSION['flash_message'])): ?>
<div id="flash-toast" data-message="<?php echo htmlspecialchars($_SESSION['flash_message']); ?>" data-type="<?php echo htmlspecialchars($_SESSION['flash_type'] ?? 'success'); ?>" style="display:none;"></div>
<?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); endif; ?>
