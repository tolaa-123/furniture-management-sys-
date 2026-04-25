<?php
/**
 * Public-facing entry point for the Custom Furniture ERP & E-Commerce Platform
 * This file serves as the main router for public-facing pages
 */

// Harden session cookie settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
ini_set('session.cookie_samesite', 'Strict');
session_start();

// Load configuration
require_once '../config/config.php';

// Load Phase 1 Utilities
require_once '../app/utils/ErrorLogger.php';
require_once '../app/utils/Validator.php';
require_once '../app/utils/QueryOptimizer.php';
require_once '../app/utils/QueryCache.php';
require_once '../app/utils/DatabaseErrorHandler.php';

// Set content type
header('Content-Type: text/html; charset=utf-8');

// Basic routing
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

// Debug: Let's see what we're getting
// error_log("Request URI: " . $request_uri);
// error_log("Path: " . $path);

// Remove base path to get the actual route
$route = str_replace('/NEWkoder/public/', '', $path);
$route = str_replace('/public/', '', $route);
$route = trim($route, '/');

// Backward-compatibility map for legacy underscore/mixed routes.
// Use canonical hyphen-style routes internally while preserving old links.
$legacyRouteMap = [
    'customer/create_order'       => 'customer/create-order',
    'customer/my_orders'          => 'customer/my-orders',
    'customer/edit_order'         => 'customer/edit-order',
    'customer/order_details'      => 'customer/order-details',
    'customer/order_tracking'     => 'customer/order-tracking',
    'manager/manage_products'     => 'manager/manage-products',
    'manager/cost_estimation'     => 'manager/cost-estimation',
    'manager/assign_employees'    => 'manager/assign-employees',
    'manager/completed_tasks'     => 'manager/completed-tasks',
    'manager/material_report'     => 'manager/material-report',
    'manager/profit_report'       => 'manager/profit-report',
    'manager/payroll_details'     => 'manager/payroll-details',
    'manager/create_payroll'      => 'manager/create-payroll',
    'manager/submit_report'       => 'manager/submit-report',
    'employee/submit_report'      => 'employee/submit-report',
    'employee/feedback_detail'    => 'employee/feedback-detail',
    'admin/profit_report'         => 'admin/profit-report',
    'admin/submit_report'         => 'admin/submit-report',
    'admin/material_report'       => 'admin/material-report',
    'customer/pay_deposit'        => 'customer/pay-deposit',
    'customer/pay_remaining'      => 'customer/pay-remaining',
    'customer/orders/create'      => 'customer/create-order',
    'customer/orders'             => 'customer/my-orders',
    'customer/orders/details'     => 'customer/order-details',
    'customer/orders/tracking'    => 'customer/order-tracking',
];

if (isset($legacyRouteMap[$route])) {
    $canonical = $legacyRouteMap[$route];
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Location: ' . BASE_URL . '/public/' . $canonical);
        exit();
    }
    $route = $canonical;
}

// Debug: Let's see the final route
// error_log("Final route: " . $route);

// Default route
if (empty($route) || $route === 'index.php') {
    $route = 'home';
}

// Route handling 
switch ($route) {
    case 'home':
    case '':
        // Load home page
        // Prepare CSRF token and roles for auth modals on home
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        $csrf_token = $_SESSION[CSRF_TOKEN_NAME];
        // Load roles for registration modal
        require_once __DIR__ . '/../core/Database.php';
        require_once __DIR__ . '/../app/models/User.php';
        $userModelForHome = new User();
        $roles = $userModelForHome->getAllRoles();
        include_once '../app/views/home.php';
        break;
    case 'about':
        include_once '../app/views/about.php';
        break;
    case 'furniture':
        include_once '../app/views/furniture.php';
        break;
    case 'how-it-works':
        header('Location: ' . BASE_URL . '/public/#how-it-works');
        exit();
    case 'contact':
        include_once '../app/views/contact.php';
        break;
    case 'login':
        // Redirect to homepage with login modal
        header('Location: ' . BASE_URL . '/public/?modal=login');
        exit();
    
    case 'register':
        // Redirect to homepage with register modal
        header('Location: ' . BASE_URL . '/public/?modal=register');
        exit();
    
    case 'forgot-password':
        // Redirect to homepage with forgot password modal
        header('Location: ' . BASE_URL . '/public/?modal=forgot');
        exit();
    
    case 'reset-password':
        // Handle reset password
        require_once __DIR__ . '/../core/BaseController.php';
        require_once __DIR__ . '/../app/models/User.php';
        require_once __DIR__ . '/../app/controllers/AuthController.php';
        $auth = new AuthController();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $auth->resetPassword();
        } else {
            $auth->showResetPassword();
        }
        break;
    
    case 'logout':
        // Handle logout
        require_once __DIR__ . '/../core/BaseController.php';
        require_once __DIR__ . '/../app/models/User.php';
        require_once __DIR__ . '/../app/controllers/AuthController.php';
        $auth = new AuthController();
        $auth->logout();
        break;
        
    // Customer routes
    case 'customer/dashboard':
        // Check if user is logged in
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        
        // Load customer dashboard
        require_once '../core/Database.php';
        require_once '../core/SecurityUtil.php';
        require_once '../app/models/UserModel.php';
        require_once '../app/models/OrderModel.php';
        $db = Database::getInstance()->getConnection();
        $auth = new UserModel(); // Simplified for demo
        $security = new SecurityUtil();
        $orderModel = new OrderModel();
        $orders = $orderModel->getOrdersByCustomer($_SESSION['user_id']);
        include_once '../app/views/customer/dashboard.php';
        break;
    
    case 'customer/create-order':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/customer/create_order.php';
        break;
        
    case 'customer/payments':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/customer/payments.php';
        break;

    case 'customer/pay-deposit':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/customer/pay_deposit.php';
        break;

    case 'customer/pay-remaining':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/customer/pay_remaining.php';
        break;
    
    case 'customer/profile':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/customer/profile.php';
        break;
    
    case 'customer/settings':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/customer/settings.php';
        break;
    
    case 'customer/my-orders':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/customer/my_orders.php';
        break;
    
    case 'customer/edit-order':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/customer/edit_order.php';
        break;

    case 'cancel-order':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '/public/customer/my-orders');
            exit();
        }
        // CSRF check
        $csrfToken = $_POST['csrf_token'] ?? '';
        $sessionToken = $_SESSION[CSRF_TOKEN_NAME] ?? '';
        if (!$csrfToken || !$sessionToken || !hash_equals($sessionToken, $csrfToken)) {
            $_SESSION['flash_message'] = 'Invalid request. Please try again.';
            $_SESSION['flash_type'] = 'danger';
            header('Location: ' . BASE_URL . '/public/customer/my-orders');
            exit();
        }
        $cancelOrderId = (int) ($_POST['order_id'] ?? 0);
        if ($cancelOrderId > 0) {
            require_once '../config/db_config.php';
            try {
                $stmt = $pdo->prepare("
                    UPDATE furn_orders
                    SET status = 'cancelled', updated_at = NOW()
                    WHERE id = ? AND customer_id = ? AND status IN ('pending_review', 'pending_cost_approval')
                ");
                $stmt->execute([$cancelOrderId, $_SESSION['user_id']]);
                if ($stmt->rowCount() > 0) {
                    $_SESSION['flash_message'] = 'Order cancelled successfully.';
                    $_SESSION['flash_type'] = 'success';
                } else {
                    $_SESSION['flash_message'] = 'Order could not be cancelled. It may have already been processed.';
                    $_SESSION['flash_type'] = 'danger';
                }
            } catch (PDOException $e) {
                error_log("Cancel order error: " . $e->getMessage());
                $_SESSION['flash_message'] = 'An error occurred. Please try again.';
                $_SESSION['flash_type'] = 'danger';
            }
        }
        header('Location: ' . BASE_URL . '/public/customer/my-orders');
        exit();

    case 'customer/invoices':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        // invoices.php does not exist — redirect to my-orders
        header('Location: ' . BASE_URL . '/public/customer/my-orders');
        exit();
    
    case 'customer/gallery':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        $_GET['category'] = $_GET['category'] ?? 'sofa';
        include_once '../app/views/customer/gallery.php';
        break;

    case 'customer/wishlist':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/customer/wishlist.php';
        break;

    case 'customer/order-details':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/customer/order_details.php';
        break;

    case 'customer/order-tracking':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/customer/order_tracking.php';
        break;
        
    case 'notifications':
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . '/public/login'); exit();
        }
        $role = $_SESSION['user_role'];
        header('Location: ' . BASE_URL . '/public/' . $role . '/notifications');
        exit();
        
    case 'customer/notifications':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
            header('Location: ' . BASE_URL . '/public/login'); exit();
        }
        include_once '../app/views/customer/notifications.php';
        break;

    case 'customer/messages':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/customer/messages.php';
        break;
        
    case 'order-details':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/customer/order_details.php';
        break;
        
    case 'order-tracking':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/customer/order_tracking.php';
        break;
        
    case 'invoices':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        header('Location: ' . BASE_URL . '/public/customer/my-orders');
        exit();
        
    // Employee routes
    case 'employee/dashboard':
        // Check if user is logged in
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        
        // Load employee dashboard
        require_once '../core/Database.php';
        require_once '../core/SecurityUtil.php';
        require_once '../app/models/UserModel.php';
        require_once '../app/models/AttendanceModel.php';
        require_once '../app/models/ProductionModel.php';
        $db = Database::getInstance()->getConnection();
        $auth = new UserModel(); // Simplified for demo
        $security = new SecurityUtil();
        $attendanceModel = new AttendanceModel();
        $productionModel = new ProductionModel();
        
        // Get employee data
        $userId = $_SESSION['user_id'];
        $todayAttendance = $attendanceModel->getTodayAttendance($userId);
        $monthlyAttendanceCount = $attendanceModel->getMonthlyAttendanceCount($userId, date('Y-m'), date('Y'));
        $totalHours = $attendanceModel->getMonthlyHours($userId, date('Y-m'), date('Y'));
        $assignedOrders = $productionModel->getAssignedOrdersByEmployee($userId);
        $recentActivities = $productionModel->getEmployeeRecentActivities($userId, 10);
        $canCheckIn = !$todayAttendance && (date('H') >= 7 && date('H') <= 9);
        
        include_once '../app/views/employee/dashboard.php';
        break;

    case 'employee/notifications':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
            header('Location: ' . BASE_URL . '/public/login'); exit();
        }
        include_once '../app/views/employee/notifications.php';
        break;

    case 'employee/tasks':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/employee/tasks.php';
        break;

    case 'employee/orders':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/employee/orders.php';
        break;

    case 'employee/customers':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/employee/customers.php';
        break;

    case 'employee/products':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/employee/products.php';
        break;

    case 'employee/materials':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/employee/materials.php';
        break;

    case 'employee/attendance':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/employee/attendance.php';
        break;

    case 'employee/payroll':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/employee/payroll.php';
        break;

    case 'employee/messages':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/employee/messages.php';
        break;

    case 'employee/reports':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/employee/reports.php';
        break;

    case 'employee/submit-report':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/employee/submit_report.php';
        break;

    case 'employee/feedback-detail':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/employee/feedback_detail.php';
        break;

    case 'employee/profile':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/employee/profile.php';
        break;
        
    // Admin routes
    case 'admin/dashboard':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/admin/dashboard.php';
        break;
        
    case 'admin/users':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/admin/users.php';
        break;
        
    case 'admin/orders':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/admin/orders.php';
        break;
        
    case 'admin/products':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/admin/products.php';
        break;
        
    case 'admin/materials':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/admin/materials.php';
        break;
        
    case 'admin/employees':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/admin/employees.php';
        break;
        
    case 'admin/payroll':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/admin/payroll.php';
        break;
        
    case 'admin/reports':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/admin/reports.php';
        break;

    case 'admin/notifications':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
            header('Location: ' . BASE_URL . '/public/login'); exit();
        }
        include_once '../app/views/admin/notifications.php';
        break;

    case 'admin/profit-report':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/manager/profit_report.php';
        break;

    case 'admin/submit-report':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/admin/submit_report.php';
        break;
        
    case 'admin/attendance':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/admin/attendance.php';
        break;

    case 'admin/settings':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/admin/settings.php';
        break;

    case 'admin/backup':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/admin/backup.php';
        break;
        
    case 'admin/profile':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/admin/profile.php';
        break;

    case 'admin/messages':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/admin/messages.php';
        break;
        
    // Manager routes
    case 'manager/dashboard':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/manager/dashboard.php';
        break;
        
    case 'manager/orders':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/manager/orders.php';
        break;
    
    case 'manager/edit-order':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/manager/edit_order.php';
        break;
        
    case 'manager/cost-estimation':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/manager/cost_estimation.php';
        break;
        
    case 'manager/assign-employees':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/manager/assign_employees.php';
        break;
        
    case 'manager/production':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/manager/production.php';
        break;
        
    case 'manager/completed-tasks':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/manager/completed_tasks.php';
        break;
        
    case 'manager/employee-performance':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/manager/employee_performance.php';
        break;
        
    case 'manager/complaints':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/manager/complaints.php';
        break;
        
    case 'manager/inventory':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/manager/inventory.php';
        break;

    case 'manager/material-report':
        if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['manager','admin'])) {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/manager/material_report.php';
        break;

    case 'admin/material-report':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        header('Location: ' . BASE_URL . '/public/admin/reports?report=materials');
        exit();

    // Analytics dashboard (manager + admin)
    case 'analytics/dashboard':
        if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['manager', 'admin'])) {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        require_once '../app/controllers/AnalyticsController.php';
        $analyticsCtrl = new AnalyticsController();
        $analyticsCtrl->dashboard();
        break;

    // AJAX: get chart data
    case 'analytics/get-chart-data':
        if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['manager', 'admin'])) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unauthorized']);
            exit();
        }
        require_once '../app/controllers/AnalyticsController.php';
        $analyticsCtrl = new AnalyticsController();
        $analyticsCtrl->getChartData();
        break;

    // AJAX: get real-time updates
    case 'analytics/get-updates':
        if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['manager', 'admin'])) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unauthorized']);
            exit();
        }
        require_once '../app/controllers/AnalyticsController.php';
        $analyticsCtrl = new AnalyticsController();
        $analyticsCtrl->getUpdates();
        break;

    // Analytics export
    case 'analytics/export':
        if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['manager', 'admin'])) {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        require_once '../app/controllers/AnalyticsController.php';
        $analyticsCtrl = new AnalyticsController();
        $analyticsCtrl->export();
        break;

    // Analytics refresh cache
    case 'analytics/refresh-cache':
        if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['manager', 'admin'])) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unauthorized']);
            exit();
        }
        require_once '../app/controllers/AnalyticsController.php';
        $analyticsCtrl = new AnalyticsController();
        $analyticsCtrl->refreshCache();
        break;
        
    case 'manager/payments':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/manager/payments.php';
        break;
        
    case 'manager/reports':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/manager/reports.php';
        break;

    case 'manager/submit-report':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/manager/submit_report.php';
        break;
        
    case 'manager/messages':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/manager/messages.php';
        break;
        
    case 'manager/products':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/manager/products.php';
        break;
        
    case 'manager/attendance':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/manager/attendance.php';
        break;

    case 'manager/payroll':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/manager/payroll.php';
        break;
        
    case 'manager/profile':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/manager/profile.php';
        break;

    case 'manager/payroll-details':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/manager/payroll_details.php';
        break;

    case 'manager/create-payroll':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/manager/create_payroll.php';
        break;

    case 'manager/manage-products':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/manager/manage_products.php';
        break;

    case 'manager/notifications':
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
            header('Location: ' . BASE_URL . '/public/login'); exit();
        }
        include_once '../app/views/manager/notifications.php';
        break;

    case 'manager/profit-report':
        if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['manager', 'admin'])) {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        include_once '../app/views/manager/profit_report.php';
        break;
        
    // Order routes
    case 'orders/create':
        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . '/public/?modal=login');
            exit();
        }
        
        // Load customer order creation page
        include_once '../app/views/customer/create_order.php';
        break;
        
    case 'orders/my-orders':
        // Check if user is logged in
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
            header('Location: ' . BASE_URL . '/public/?modal=login');
            exit();
        }
        
        // Load customer orders page with full data
        require_once '../core/Database.php';
        require_once '../core/SecurityUtil.php';
        require_once '../app/models/UserModel.php';
        require_once '../app/models/OrderModel.php';
        $db = Database::getInstance()->getConnection();
        
        // Fetch orders with items summary (without payments table for now)
        $stmt = $db->prepare("
            SELECT 
                o.id,
                o.order_number,
                o.created_at,
                o.status,
                o.total_amount,
                COALESCE(o.deposit_paid, 0) as prepaid_amount,
                GROUP_CONCAT(
                    CONCAT(pr.name, ' (', COALESCE(oc.quantity, 1), ')')
                    SEPARATOR ', '
                ) as items_summary,
                COUNT(DISTINCT oc.id) as item_count
            FROM furn_orders o
            LEFT JOIN furn_order_customizations oc ON o.id = oc.order_id
            LEFT JOIN furn_products pr ON oc.product_id = pr.id
            WHERE o.customer_id = ?
            GROUP BY o.id, o.order_number, o.created_at, o.status, o.total_amount, o.deposit_paid
            ORDER BY o.created_at DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        include_once '../app/views/orders/my_orders.php';
        break;
    
    case 'orders/success':
        // Check if user is logged in
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
            header('Location: ' . BASE_URL . '/public/?modal=login');
            exit();
        }
        
        // Load order success page
        include_once '../app/views/orders/success.php';
        break;
        
    default:
        // Handle dynamic routes
        if (preg_match('#^customer/gallery/([a-z0-9\-]+)$#', $route, $matches)) {
            if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
                header('Location: ' . BASE_URL . '/public/login');
                exit();
            }
            $_GET['category'] = $matches[1];
            include_once '../app/views/customer/gallery.php';
        } elseif (preg_match('#^customer/orders/details/([0-9]+)$#', $route, $matches)) {
            if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
                header('Location: ' . BASE_URL . '/public/login');
                exit();
            }
            $_GET['id'] = $matches[1];
            include_once '../app/views/customer/order_details.php';
        } elseif (preg_match('#^customer/orders/tracking/([0-9]+)$#', $route, $matches)) {
            if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
                header('Location: ' . BASE_URL . '/public/login');
                exit();
            }
            $_GET['id'] = $matches[1];
            include_once '../app/views/customer/order_tracking.php';
        } elseif (preg_match('#^orders/view/([0-9]+)$#', $route, $matches)) {
            // Load order view page
            require_once '../core/Database.php';
            require_once '../core/SecurityUtil.php';
            require_once '../app/models/UserModel.php';
            require_once '../app/models/OrderModel.php';
            require_once '../app/models/CustomizationModel.php';
            $db = Database::getInstance()->getConnection();
            $auth = new UserModel(); // Simplified for demo
            $security = new SecurityUtil();
            $orderModel = new OrderModel();
            $customizationModel = new CustomizationModel();
            $orderId = $matches[1];
            // Mock data for demo
            $order = ['id' => $orderId, 'order_number' => 'ORD202602220001', 'status' => 'pending_cost_approval', 'created_at' => date('Y-m-d H:i:s')];
            $customizations = [];
            include_once '../app/views/orders/view.php';
        } elseif (preg_match('#^payments/upload-deposit/([0-9]+)$#', $route, $matches)) {
            // Load deposit upload page
            require_once '../core/Database.php';
            require_once '../core/SecurityUtil.php';
            require_once '../app/models/UserModel.php';
            require_once '../app/models/OrderModel.php';
            $db = Database::getInstance()->getConnection();
            $auth = new UserModel(); // Simplified for demo
            $security = new SecurityUtil();
            $orderModel = new OrderModel();
            $orderId = $matches[1];
            // Mock data for demo
            $order = ['id' => $orderId, 'order_number' => 'ORD202602220001', 'status' => 'waiting_for_deposit', 'total_amount' => 15000.00, 'deposit_amount' => 7500.00, 'created_at' => date('Y-m-d H:i:s')];
            include_once '../app/views/payments/upload_deposit.php';
        } elseif (preg_match('#^payments/upload-final/([0-9]+)$#', $route, $matches)) {
            // Load final payment upload page
            require_once '../core/Database.php';
            require_once '../core/SecurityUtil.php';
            require_once '../app/models/UserModel.php';
            require_once '../app/models/OrderModel.php';
            $db = Database::getInstance()->getConnection();
            $auth = new UserModel(); // Simplified for demo
            $security = new SecurityUtil();
            $orderModel = new OrderModel();
            $orderId = $matches[1];
            // Mock data for demo
            $order = ['id' => $orderId, 'order_number' => 'ORD202602220001', 'status' => 'ready_for_delivery', 'total_amount' => 15000.00, 'deposit_paid' => 7500.00, 'remaining_balance' => 7500.00, 'created_at' => date('Y-m-d H:i:s')];
            include_once '../app/views/payments/upload_final.php';
        } elseif (preg_match('#^payments/verify/([0-9]+)$#', $route, $matches)) {
            // Load payment verification page
            require_once '../core/Database.php';
            require_once '../core/SecurityUtil.php';
            require_once '../app/models/UserModel.php';
            require_once '../app/models/PaymentModel.php';
            require_once '../app/models/OrderModel.php';
            $db = Database::getInstance()->getConnection();
            $auth = new UserModel(); // Simplified for demo
            $security = new SecurityUtil();
            $paymentModel = new PaymentModel();
            $orderModel = new OrderModel();
            $receiptId = $matches[1];
            // Mock data for demo
            $receipt = ['id' => $receiptId, 'order_number' => 'ORD202602220001', 'payment_type' => 'deposit', 'amount' => 7500.00, 'status' => 'pending', 'receipt_image_path' => '/uploads/receipts/demo.jpg', 'first_name' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.com', 'order_status' => 'waiting_for_deposit', 'total_amount' => 15000.00, 'deposit_amount' => 7500.00];
            include_once '../app/views/payments/verify_payment.php';
        } elseif (preg_match('#^production/assign/([0-9]+)$#', $route, $matches)) {
            // Load order assignment page
            require_once '../core/Database.php';
            require_once '../core/SecurityUtil.php';
            require_once '../app/models/UserModel.php';
            require_once '../app/models/OrderModel.php';
            require_once '../app/models/MaterialModel.php';
            $db = Database::getInstance()->getConnection();
            $auth = new UserModel(); // Simplified for demo
            $security = new SecurityUtil();
            $orderModel = new OrderModel();
            $materialModel = new MaterialModel();
            $orderId = $matches[1];
            // Mock data for demo
            $order = ['id' => $orderId, 'order_number' => 'ORD202602220001', 'status' => 'deposit_paid', 'total_amount' => 15000.00, 'deposit_paid' => 7500.00, 'first_name' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.com'];
            $employees = [['id' => 1, 'first_name' => 'Employee', 'last_name' => 'One']];
            $requiredMaterials = [];
            include_once '../app/views/production/assign_order.php';
        } elseif (preg_match('#^production/start-work/([0-9]+)$#', $route, $matches)) {
            // Load start work page
            require_once '../core/Database.php';
            require_once '../core/SecurityUtil.php';
            require_once '../app/models/UserModel.php';
            require_once '../app/models/OrderModel.php';
            $db = Database::getInstance()->getConnection();
            $auth = new UserModel(); // Simplified for demo
            $security = new SecurityUtil();
            $orderModel = new OrderModel();
            $assignmentId = $matches[1];
            // Mock data for demo
            $assignment = ['id' => $assignmentId, 'order_number' => 'ORD202602220001', 'estimated_hours' => 10, 'assigned_at' => date('Y-m-d H:i:s'), 'assigned_by_first_name' => 'Manager', 'assigned_by_last_name' => 'One', 'first_name' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.com'];
            include_once '../app/views/production/start_work.php';
        } elseif (preg_match('#^production/complete-work/([0-9]+)$#', $route, $matches)) {
            // Load complete work page
            require_once '../core/Database.php';
            require_once '../core/SecurityUtil.php';
            require_once '../app/models/UserModel.php';
            require_once '../app/models/OrderModel.php';
            $db = Database::getInstance()->getConnection();
            $auth = new UserModel(); // Simplified for demo
            $security = new SecurityUtil();
            $orderModel = new OrderModel();
            $assignmentId = $matches[1];
            // Mock data for demo
            $assignment = ['id' => $assignmentId, 'order_number' => 'ORD202602220001', 'estimated_hours' => 10, 'started_at' => date('Y-m-d H:i:s', strtotime('-2 hours')), 'first_name' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.com'];
            include_once '../app/views/production/complete_work.php';
        } elseif (preg_match('#^production/tracking/([0-9]+)$#', $route, $matches)) {
            // Load production tracking page
            require_once '../core/Database.php';
            require_once '../core/SecurityUtil.php';
            require_once '../app/models/UserModel.php';
            require_once '../app/models/OrderModel.php';
            require_once '../app/models/ProductionModel.php';
            require_once '../app/models/MaterialReservationModel.php';
            $db = Database::getInstance()->getConnection();
            $auth = new UserModel(); // Simplified for demo
            $security = new SecurityUtil();
            $orderModel = new OrderModel();
            $productionModel = new ProductionModel();
            $reservationModel = new MaterialReservationModel();
            $orderId = $matches[1];
            // Mock data for demo
            $order = ['id' => $orderId, 'order_number' => 'ORD202602220001', 'status' => 'in_production', 'total_amount' => 15000.00, 'deposit_paid' => 7500.00];
            $assignments = [];
            $reservations = [];
            $timeline = ['production_started_at' => date('Y-m-d H:i:s', strtotime('-1 day')), 'estimated_completion_date' => date('Y-m-d', strtotime('+2 days'))];
            include_once '../app/view
            s/production/view_tracking.php';
        } elseif (preg_match('#^collection/([a-z]+)$#', $route, $matches)) {
            // Load collection page
            $collectionType = $matches[1];
            include_once '../app/views/collection.php';
        } else {
            header('Location: ' . BASE_URL . '/public/');
            exit();
        }
        break;
}
