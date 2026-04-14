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
        if (!$this->isManager()) {
            $this->setFlashMessage('error', 'Access denied. Manager access required.');
            $this->redirect('/login');
            return;
        }
        
        // Get dashboard statistics
        $stats = $this->analyticsModel->getDashboardStats();
        
        // Get chart data
        $chartData = [
            'monthlyRevenue' => $this->analyticsModel->getMonthlyRevenueData(12),
            'ordersByStatus' => $this->analyticsModel->getOrdersByStatusData(),
            'employeeHours' => $this->analyticsModel->getEmployeeHoursData(8),
            'lowStockAlerts' => $this->analyticsModel->getLowStockAlerts(),
            'topProducts' => $this->analyticsModel->getTopSellingProducts(10),
            'monthlyProfit' => $this->analyticsModel->getMonthlyProfitData(12),
            'topCustomers' => $this->analyticsModel->getTopCustomers(10, 12),
            'materialUsage' => $this->analyticsModel->getMaterialUsageTrends(12, 6),
            'employeeProductivity' => $this->analyticsModel->getEmployeeProductivityData(8, 6)
        ];
        
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
                default:
                    $data = null;
            }
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
                default:
                    throw new Exception('Invalid chart type');
            }
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => $data]);
            
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    /**
     * Refresh cache
     */
    public function refreshCache() {
        if (!$this->isAdmin()) {
            $this->setFlashMessage('error', 'Access denied. Admin access required.');
            $this->redirect('/analytics/dashboard');
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $this->analyticsModel->clearExpiredCache();
                $this->logAudit('analytics_cache_refreshed', 'furn_analytics_cache', null, ['action' => 'manual_refresh']);
                $this->setFlashMessage('success', 'Analytics cache refreshed successfully');
            } catch (Exception $e) {
                $this->setFlashMessage('error', 'Failed to refresh cache: ' . $e->getMessage());
            }
            
            $this->redirect('/analytics/dashboard');
        }
    }
    
    /**
     * Export dashboard data
     */
    public function export() {
        if (!$this->isManager()) {
            $this->setFlashMessage('error', 'Access denied. Manager access required.');
            $this->redirect('/login');
            return;
        }
        
        $exportType = $_GET['type'] ?? 'summary';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="dashboard_report_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        if ($exportType === 'summary') {
            // Export summary statistics
            fputcsv($output, ['Metric', 'Value']);
            
            $stats = $this->analyticsModel->getDashboardStats();
            foreach ($stats as $key => $value) {
                fputcsv($output, [ucwords(str_replace('_', ' ', $key)), $value]);
            }
        } else {
            // Export detailed data based on type
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
                    $detailed = $data['detailed_data'] ?? [];
                    foreach ($detailed as $product) {
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
        if (!$this->isManager()) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Access denied']);
            return;
        }
        
        // Get latest statistics
        $stats = $this->analyticsModel->getDashboardStats();
        
        // Get recent alerts
        $alerts = $this->getRecentAlerts();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'stats' => $stats,
            'alerts' => $alerts,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Get recent alerts for dashboard
     */
    private function getRecentAlerts() {
        $alerts = [];
        
        // Low stock alerts
        $lowStock = $this->analyticsModel->getLowStockAlerts();
        $detailedStock = $lowStock['detailed_data'] ?? [];
        
        foreach ($detailedStock as $item) {
            if ($item['stock_status'] === 'critical') {
                $alerts[] = [
                    'type' => 'danger',
                    'message' => "Critical stock alert: {$item['name']} ({$item['current_stock']} {$item['unit']} remaining)",
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            } elseif ($item['stock_status'] === 'warning') {
                $alerts[] = [
                    'type' => 'warning',
                    'message' => "Low stock warning: {$item['name']} ({$item['current_stock']} {$item['unit']} remaining)",
                    'timestamp' => date('Y-m-d H:i:s')
                ];
            }
        }
        
        // Pending orders
        $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM furn_orders WHERE status = 'pending'");
        $stmt->execute();
        $pendingCount = $stmt->fetch()['total'];
        
        if ($pendingCount > 5) {
            $alerts[] = [
                'type' => 'info',
                'message' => "High pending orders: {$pendingCount} orders require attention",
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
        
        return array_slice($alerts, 0, 5); // Return only recent 5 alerts
    }
}