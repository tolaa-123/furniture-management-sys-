<?php
/**
 * Analytics Controller
 * Handles dashboard analytics and Chart.js visualizations
 */
require_once dirname(__DIR__) . '/../core/BaseController.php';
require_once dirname(__DIR__) . '/models/AnalyticsModel.php';

class AnalyticsController extends BaseController {
    private $analyticsModel;
    
    public function __construct() {
        parent::__construct();
        $this->analyticsModel = new AnalyticsModel();
    }
    
    /**
     * Main dashboard
     */
    public function dashboard() {
        // Auth check using session directly (isManager not in BaseController)
        if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['manager', 'admin'])) {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        
        try {
            // Get dashboard statistics
            $stats = $this->analyticsModel->getDashboardStats();
            
            // Get chart data
            $chartData = [
                'monthlyRevenue'       => $this->analyticsModel->getMonthlyRevenueData(12),
                'ordersByStatus'       => $this->analyticsModel->getOrdersByStatusData(),
                'employeeHours'        => $this->analyticsModel->getEmployeeHoursData(8),
                'lowStockAlerts'       => $this->analyticsModel->getLowStockAlerts(),
                'topProducts'          => $this->analyticsModel->getTopSellingProducts(10),
                'monthlyProfit'        => $this->analyticsModel->getMonthlyProfitData(12),
                'topCustomers'         => $this->analyticsModel->getTopCustomers(10, 12),
                'materialUsage'        => $this->analyticsModel->getMaterialUsageTrends(12, 6),
                'employeeProductivity' => $this->analyticsModel->getEmployeeProductivityData(8, 6),
                'weeklyOrders'         => $this->analyticsModel->getWeeklyOrdersData(12),
            ];
        } catch (Exception $e) {
            error_log("Analytics Dashboard Error: " . $e->getMessage());
            // Provide default empty data
            $stats = [
                'total_orders' => 0,
                'pending_orders' => 0,
                'this_month_revenue' => 0,
                'this_month_profit' => 0,
                'active_employees' => 0,
                'low_stock_items' => 0
            ];
            $chartData = [
                'monthlyRevenue' => ['labels' => [], 'datasets' => []],
                'ordersByStatus' => ['labels' => [], 'datasets' => []],
                'employeeHours' => ['labels' => [], 'datasets' => []],
                'lowStockAlerts' => ['labels' => [], 'datasets' => []],
                'topProducts' => ['labels' => [], 'datasets' => []],
                'monthlyProfit' => ['labels' => [], 'datasets' => []],
                'topCustomers' => ['labels' => [], 'datasets' => []],
                'materialUsage' => ['labels' => [], 'datasets' => []],
                'employeeProductivity' => ['labels' => [], 'datasets' => []],
                'weeklyOrders' => ['labels' => [], 'datasets' => []]
            ];
        }
        
        include_once dirname(__DIR__) . '/views/analytics/dashboard.php';
    }
    
    /**
     * AJAX: get updated chart data
     */
    public function getChartData() {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['manager', 'admin'])) {
            echo json_encode(['error' => 'Access denied']);
            return;
        }
        
        $chartType = $_GET['chart'] ?? '';
        $months    = intval($_GET['months'] ?? 12);
        $limit     = intval($_GET['limit']  ?? 10);
        
        try {
            switch ($chartType) {
                case 'monthly_revenue':
                    $data = $this->analyticsModel->getMonthlyRevenueData($months);
                    break;
                case 'orders_by_status':
                    $data = $this->analyticsModel->getOrdersByStatusData();
                    break;
                case 'employee_hours':
                    $data = $this->analyticsModel->getEmployeeHoursData($limit);
                    break;
                case 'low_stock':
                    $data = $this->analyticsModel->getLowStockAlerts();
                    break;
                case 'top_products':
                    $data = $this->analyticsModel->getTopSellingProducts($limit);
                    break;
                case 'monthly_profit':
                    $data = $this->analyticsModel->getMonthlyProfitData($months);
                    break;
                case 'top_customers':
                    $data = $this->analyticsModel->getTopCustomers($limit, $months);
                    break;
                case 'material_usage':
                    $data = $this->analyticsModel->getMaterialUsageTrends($months, $limit);
                    break;
                case 'employee_productivity':
                    $data = $this->analyticsModel->getEmployeeProductivityData($limit, $months);
                    break;
                case 'weekly_orders':
                    $data = $this->analyticsModel->getWeeklyOrdersData($limit);
                    break;
                default:
                    throw new Exception('Invalid chart type');
            }
            echo json_encode(['success' => true, 'data' => $data]);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Refresh cache
     */
    public function refreshCache() {
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Admin access required']);
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $this->analyticsModel->clearAllCache();
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Cache refreshed']);
            } catch (Exception $e) {
                header('Content-Type: application/json');
                echo json_encode(['error' => $e->getMessage()]);
            }
            exit();
        }
    }
    
    /**
     * Export dashboard data
     */
    public function export() {
        if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['manager', 'admin'])) {
            header('Location: ' . BASE_URL . '/public/login');
            exit();
        }
        
        $exportType = $_GET['type'] ?? 'summary';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="dashboard_report_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        if ($exportType === 'summary') {
            fputcsv($output, ['Metric', 'Value']);
            $stats = $this->analyticsModel->getDashboardStats();
            foreach ($stats as $key => $value) {
                fputcsv($output, [ucwords(str_replace('_', ' ', $key)), $value]);
            }
        } else {
            $dataType = $_GET['data'] ?? 'revenue';
            switch ($dataType) {
                case 'revenue':
                    fputcsv($output, ['Month', 'Revenue (ETB)', 'Orders Count']);
                    $data = $this->analyticsModel->getMonthlyRevenueData(12);
                    for ($i = 0; $i < count($data['labels']); $i++) {
                        fputcsv($output, [
                            $data['labels'][$i],
                            $data['datasets'][0]['data'][$i],
                            $data['datasets'][1]['data'][$i] ?? 0
                        ]);
                    }
                    break;
                case 'products':
                    fputcsv($output, ['Product', 'Category', 'Orders', 'Quantity', 'Revenue (ETB)']);
                    $data = $this->analyticsModel->getTopSellingProducts(20);
                    foreach ($data['detailed_data'] ?? [] as $product) {
                        fputcsv($output, [
                            $product['product_name'],
                            $product['category'],
                            $product['orders_count'],
                            $product['total_quantity'],
                            $product['total_revenue']
                        ]);
                    }
                    break;
            }
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Get real-time dashboard updates
     */
    public function getUpdates() {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['manager', 'admin'])) {
            echo json_encode(['error' => 'Access denied']);
            return;
        }
        
        $stats  = $this->analyticsModel->getDashboardStats();
        $alerts = $this->getRecentAlerts();
        
        echo json_encode([
            'success'   => true,
            'stats'     => $stats,
            'alerts'    => $alerts,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Get recent alerts for dashboard
     */
    private function getRecentAlerts() {
        $alerts = [];
        
        $lowStock     = $this->analyticsModel->getLowStockAlerts();
        $detailedStock = $lowStock['detailed_data'] ?? [];
        
        foreach ($detailedStock as $item) {
            if ($item['stock_status'] === 'critical') {
                $alerts[] = ['type' => 'danger',  'message' => "Critical stock: {$item['name']} ({$item['current_stock']} {$item['unit']} left)", 'timestamp' => date('Y-m-d H:i:s')];
            } elseif ($item['stock_status'] === 'warning') {
                $alerts[] = ['type' => 'warning', 'message' => "Low stock: {$item['name']} ({$item['current_stock']} {$item['unit']} left)",      'timestamp' => date('Y-m-d H:i:s')];
            }
        }
        
        // Pending orders — use PDO directly
        try {
            require_once dirname(__DIR__) . '/../config/db_config.php';
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM furn_orders WHERE status IN ('pending_review','pending_cost_approval','pending')");
            $pendingCount = $stmt->fetch()['total'] ?? 0;
            if ($pendingCount > 5) {
                $alerts[] = ['type' => 'info', 'message' => "High pending orders: {$pendingCount} require attention", 'timestamp' => date('Y-m-d H:i:s')];
            }
        } catch (Exception $e) { /* non-fatal */ }
        
        return array_slice($alerts, 0, 5);
    }
}