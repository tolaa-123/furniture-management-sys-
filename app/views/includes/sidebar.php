<?php
// Sidebar navigation for authenticated users
$userRole = $_SESSION['user_role'] ?? null;
$userId = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['user_name'] ?? 'User';
?>

<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <?php if ($userRole === 'manager' || $userRole === 'admin'): ?>
                <!-- Manager/Admin Navigation -->
                <li class="nav-item">
                    <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/dashboard') !== false) ? 'active' : ''; ?>" 
                       href="/admin/dashboard">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/admin/review-orders">
                        <i class="fas fa-clipboard-check"></i>
                        Review Custom Orders
                        <span class="badge bg-warning text-dark float-end">
                            <?php
                            try {
                                if (isset($db)) {
                                    $stmt = $db->prepare("SELECT COUNT(*) FROM furn_orders WHERE status = 'pending_cost_approval'");
                                    $stmt->execute();
                                    echo $stmt->fetchColumn();
                                } else {
                                    echo '0';
                                }
                            } catch (Exception $e) {
                                echo '0';
                            }
                            ?>
                        </span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/admin/assign-orders">
                        <i class="fas fa-tasks"></i>
                        Assign Orders
                        <span class="badge bg-info text-white float-end">
                            <?php
                            try {
                                if (isset($db)) {
                                    $stmt = $db->prepare("SELECT COUNT(*) FROM furn_orders WHERE status = 'deposit_paid'");
                                    $stmt->execute();
                                    echo $stmt->fetchColumn();
                                } else {
                                    echo '0';
                                }
                            } catch (Exception $e) {
                                echo '0';
                            }
                            ?>
                        </span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/admin/approve-deposits">
                        <i class="fas fa-money-check-alt"></i>
                        Approve Deposits
                        <span class="badge bg-success text-white float-end">
                            <?php
                            try {
                                if (isset($db)) {
                                    $stmt = $db->prepare("SELECT COUNT(*) FROM furn_orders WHERE status = 'waiting_for_deposit'");
                                    $stmt->execute();
                                    echo $stmt->fetchColumn();
                                } else {
                                    echo '0';
                                }
                            } catch (Exception $e) {
                                echo '0';
                            }
                            ?>
                        </span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/admin/manage-products">
                        <i class="fas fa-box"></i>
                        Manage Products
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/admin/manage-materials">
                        <i class="fas fa-cubes"></i>
                        Manage Materials
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/admin/payroll">
                        <i class="fas fa-file-invoice-dollar"></i>
                        Payroll
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/admin/reports">
                        <i class="fas fa-chart-line"></i>
                        Analytics
                    </a>
                </li>
                
                <?php if ($userRole === 'admin'): ?>
                    <li class="nav-item mt-4">
                        <h6 class="sidebar-heading px-3 mt-4 mb-1 text-muted">
                            <span>Administration</span>
                        </h6>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/users">
                            <i class="fas fa-users"></i>
                            User Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/products">
                            <i class="fas fa-box"></i>
                            Product Management
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/categories">
                            <i class="fas fa-tags"></i>
                            Categories
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/audit-logs">
                            <i class="fas fa-clipboard-list"></i>
                            Audit Logs
                        </a>
                    </li>
                <?php endif; ?>
                
            <?php elseif ($userRole === 'customer'): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/customer/dashboard') !== false) ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/public/customer/dashboard">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/orders/my-orders') !== false) ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/public/orders/my-orders">
                        <i class="fas fa-shopping-cart"></i> My Orders
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/orders/create') !== false) ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/public/orders/create">
                        <i class="fas fa-plus-circle"></i> Create Order
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo BASE_URL; ?>/public/settings">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-danger" href="<?php echo BASE_URL; ?>/public/logout">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/payments') !== false) ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/public/payments">
                        <i class="fas fa-credit-card"></i> Payments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/notifications') !== false) ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/public/notifications">
                        <i class="fas fa-bell"></i> Notifications <span class="badge bg-danger" id="notifCount" style="display:none;">0</span>
                    </a>
                </li>

                
            <?php elseif ($userRole === 'employee'): ?>
                <!-- Employee Navigation -->
                <li class="nav-item">
                    <a class="nav-link" href="/employee/dashboard">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/employee/production">
                        <i class="fas fa-hammer"></i>
                        Production Queue
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/employee/delivery">
                        <i class="fas fa-truck"></i>
                        Delivery Schedule
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/employee/timesheet">
                        <i class="fas fa-clock"></i>
                        Timesheet
                    </a>
                </li>
            <?php endif; ?>
        </ul>
        
        <!-- User Info Panel -->
        <div class="user-info-panel">
            
        </div>
        
        <!-- Bottom Navigation -->
        <ul class="nav flex-column mb-3">
        </ul>
    </div>
</nav>
