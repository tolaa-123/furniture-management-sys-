<?php
// Sidebar navigation for authenticated users
// Note: This is a simplified version that works with the dashboard context

// Get user role from session (fallback to customer if not set)
$userRole = $_SESSION['user_role'] ?? 'customer';
$userId = $_SESSION['user_id'] ?? 0;
$userName = $_SESSION['user_name'] ?? 'User';
?>

<nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <?php if ($userRole === 'manager' || $userRole === 'admin'): ?>
                <!-- Manager/Admin Navigation -->
                <li class="nav-item">
                    <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/manager/dashboard') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>/public/manager/dashboard">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>/public/orders/pending">
                        <i class="fas fa-clock"></i>
                        Pending Orders
                        <span class="badge bg-warning text-dark float-end">
                            <?php
                            // This would need database connection - showing 0 for now
                            echo '0';
                            ?>
                        </span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>/public/orders/production">
                        <i class="fas fa-cogs"></i>
                        In Production
                        <span class="badge bg-info text-white float-end">
                            <?php
                            // This would need database connection - showing 0 for now
                            echo '0';
                            ?>
                        </span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>/public/orders/completed">
                        <i class="fas fa-check-circle"></i>
                        Completed Orders
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>/public/reports">
                        <i class="fas fa-chart-bar"></i>
                        Reports
                    </a>
                </li>
                
                <?php if ($userRole === 'admin'): ?>
                    <li class="nav-item mt-4">
                        <h6 class="sidebar-heading px-3 mt-4 mb-1 text-muted">
                            <span>Administration</span>
                        </h6>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>/public/admin/users">
                            <i class="fas fa-users"></i>
                            User Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>/public/admin/products">
                            <i class="fas fa-box"></i>
                            Product Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>/public/admin/categories">
                            <i class="fas fa-tags"></i>
                            Categories
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>/public/admin/audit-logs">
                            <i class="fas fa-clipboard-list"></i>
                            Audit Logs
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo BASE_URL; ?>/public/admin/bank-accounts">
                            <i class="fas fa-university"></i>
                            Bank Accounts
                        </a>
                    </li>
                <?php endif; ?>
                
            <?php elseif ($userRole === 'customer'): ?>
                <!-- Customer Navigation -->
                <li class="nav-item">
                    <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/customer/dashboard') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>/public/customer/dashboard">
                        <i class="fas fa-home"></i>
                        My Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>/public/orders/my-orders">
                        <i class="fas fa-shopping-cart"></i>
                        My Orders
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>/public/orders/create">
                        <i class="fas fa-plus-circle"></i>
                        Create New Order
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>/public/settings">
                        <i class="fas fa-cog"></i>
                        Settings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-danger" href="<?php echo BASE_URL; ?>/public/logout">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </li>
                
                
            <?php elseif ($userRole === 'employee'): ?>
                <!-- Employee Navigation -->
                <li class="nav-item">
                    <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/employee/dashboard') !== false) ? 'active' : ''; ?>" 
                       href="<?php echo BASE_URL; ?>/public/employee/dashboard">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>/public/employee/production">
                        <i class="fas fa-hammer"></i>
                        Production Queue
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>/public/employee/delivery">
                        <i class="fas fa-truck"></i>
                        Delivery Schedule
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>/public/employee/timesheet">
                        <i class="fas fa-clock"></i>
                        Timesheet
                    </a>
                </li>
            <?php endif; ?>
        </ul>
        
        <hr class="my-3">
        
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link text-danger" href="<?php echo BASE_URL; ?>/public/logout">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </li>
        </ul>
        
        <div class="px-3 py-2 text-center text-muted small">
            
        </div>
    </div>
</nav>