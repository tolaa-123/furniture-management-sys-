<?php
/**
 * Profit Controller
 * Handles profit calculation, analysis, and reporting
 */
require_once dirname(__DIR__) . '/../core/BaseController.php';
require_once dirname(__DIR__) . '/models/ProfitModel.php';

class ProfitController extends BaseController {
    private $profitModel;
    
    public function __construct() {
        parent::__construct();
        $this->profitModel = new ProfitModel();
    }
    
    /**
     * Profit dashboard
     */
    public function dashboard() {
        if (!$this->isManager()) {
            $this->setFlashMessage('error', 'Access denied. Manager access required.');
            $this->redirect('/login');
            return;
        }
        
        // Get profit statistics
        $stats = $this->profitModel->getProfitStats();
        
        // Get top profitable products
        $topProducts = $this->profitModel->getTopProfitableProducts(5);
        
        // Get recent profit calculations
        $recentProfits = $this->getRecentProfits(10);
        
        // Get profit trends
        $trends = $this->profitModel->getProfitTrends(30);
        
        $data = [
            'stats' => $stats,
            'topProducts' => $topProducts,
            'recentProfits' => $recentProfits,
            'trends' => $trends
        ];
        
        $this->render('profit/dashboard', $data);
    }
    
    /**
     * Calculate profit for specific order
     */
    public function calculate($orderId) {
        if (!$this->isManager()) {
            $this->setFlashMessage('error', 'Access denied. Manager access required.');
            $this->redirect('/login');
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $profitId = $this->profitModel->calculateOrderProfit($orderId);
                
                if ($profitId) {
                    $this->logAudit('profit_calculated', 'furn_profit_calculations', $profitId, ['order_id' => $orderId]);
                    $this->setFlashMessage('success', 'Profit calculated successfully');
                }
            } catch (Exception $e) {
                $this->setFlashMessage('error', $e->getMessage());
            }
            
            $this->redirect('/profit/dashboard');
        }
        
        // Show calculation confirmation page
        $order = $this->getOrderById($orderId);
        if (!$order) {
            $this->setFlashMessage('error', 'Order not found');
            $this->redirect('/profit/dashboard');
            return;
        }
        
        $data = ['order' => $order];
        $this->render('profit/calculate', $data);
    }
    
    /**
     * View order profit details
     */
    public function viewOrder($orderId) {
        if (!$this->isManager()) {
            $this->setFlashMessage('error', 'Access denied. Manager access required.');
            $this->redirect('/login');
            return;
        }
        
        $profit = $this->profitModel->getOrderProfit($orderId);
        $order = $this->getOrderById($orderId);
        
        if (!$profit || !$order) {
            $this->setFlashMessage('error', 'Profit calculation not found for this order');
            $this->redirect('/profit/dashboard');
            return;
        }
        
        $data = [
            'profit' => $profit,
            'order' => $order
        ];
        
        $this->render('profit/order_detail', $data);
    }
    
    /**
     * Product profit analysis
     */
    public function productAnalysis() {
        if (!$this->isManager()) {
            $this->setFlashMessage('error', 'Access denied. Manager access required.');
            $this->redirect('/login');
            return;
        }
        
        $productId = $_GET['product'] ?? null;
        $products = $this->profitModel->getProductProfitSummary($productId);
        
        // Get all products for filter
        $stmt = $this->db->prepare("SELECT id, name, category FROM furn_products WHERE is_active = 1 ORDER BY name");
        $stmt->execute();
        $allProducts = $stmt->fetchAll();
        
        $data = [
            'products' => $products,
            'allProducts' => $allProducts,
            'selectedProduct' => $productId
        ];
        
        $this->render('profit/product_analysis', $data);
    }
    
    /**
     * Monthly profit report
     */
    public function monthlyReport() {
        if (!$this->isManager()) {
            $this->setFlashMessage('error', 'Access denied. Manager access required.');
            $this->redirect('/login');
            return;
        }
        
        $months = $_GET['months'] ?? 12;
        $monthlyData = $this->profitModel->getMonthlyProfitSummary($months);
        
        $data = [
            'monthlyData' => $monthlyData,
            'selectedMonths' => $months
        ];
        
        $this->render('profit/monthly_report', $data);
    }
    
    /**
     * Profit settings management
     */
    public function settings() {
        if (!$this->isAdmin()) {
            $this->setFlashMessage('error', 'Access denied. Admin access required.');
            $this->redirect('/login');
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $settings = [
                'labor_hourly_rate' => $_POST['labor_hourly_rate'] ?? '50.00',
                'production_time_cost_rate' => $_POST['production_time_cost_rate'] ?? '30.00',
                'profit_margin_target' => $_POST['profit_margin_target'] ?? '25.00'
            ];
            
            foreach ($settings as $key => $value) {
                $this->profitModel->updateSetting($key, $value);
            }
            
            $this->logAudit('profit_settings_updated', 'furn_profit_settings', null, $settings);
            $this->setFlashMessage('success', 'Profit settings updated successfully');
            $this->redirect('/profit/settings');
        }
        
        $settings = $this->profitModel->getProfitSettings();
        
        $data = ['settings' => $settings];
        $this->render('profit/settings', $data);
    }
    
    /**
     * Export profit data
     */
    public function export() {
        if (!$this->isManager()) {
            $this->setFlashMessage('error', 'Access denied. Manager access required.');
            $this->redirect('/login');
            return;
        }
        
        $exportType = $_GET['type'] ?? 'summary';
        $period = $_GET['period'] ?? 'all';
        
        // Generate CSV export
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="profit_report_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        if ($exportType === 'summary') {
            // Export summary data
            fputcsv($output, ['Product', 'Category', 'Total Sold', 'Total Revenue', 'Total Cost', 'Total Profit', 'Avg Profit Margin %']);
            
            $products = $this->profitModel->getProductProfitSummary();
            foreach ($products as $product) {
                fputcsv($output, [
                    $product['product_name'],
                    $product['category'],
                    $product['total_sold'],
                    $product['total_revenue'],
                    $product['total_cost'],
                    $product['total_profit'],
                    $product['average_profit_margin']
                ]);
            }
        } else {
            // Export detailed data
            fputcsv($output, ['Order ID', 'Product', 'Selling Price', 'Material Cost', 'Labor Cost', 'Time Cost', 'Total Cost', 'Profit', 'Margin %', 'Date']);
            
            $recentProfits = $this->getRecentProfits(100);
            foreach ($recentProfits as $profit) {
                fputcsv($output, [
                    $profit['order_id'],
                    $profit['product_name'],
                    $profit['final_selling_price'],
                    $profit['material_cost'],
                    $profit['labor_cost'],
                    $profit['production_time_cost'],
                    $profit['total_cost'],
                    $profit['profit'],
                    $profit['profit_margin_percentage'],
                    date('Y-m-d', strtotime($profit['calculated_at']))
                ]);
            }
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Get recent profit calculations
     */
    private function getRecentProfits($limit = 10) {
        try {
            $stmt = $this->db->prepare("
                SELECT pc.*, o.order_number, p.name as product_name, p.category
                FROM furn_profit_calculations pc
                JOIN furn_orders o ON pc.order_id = o.id
                JOIN furn_products p ON pc.product_id = p.id
                ORDER BY pc.calculated_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            // Fallback if p.id doesn't exist
            error_log("Recent profits query error: " . $e->getMessage());
            try {
                $stmt = $this->db->prepare("
                    SELECT pc.*, o.order_number, 'Product' as product_name, 'N/A' as category
                    FROM furn_profit_calculations pc
                    JOIN furn_orders o ON pc.order_id = o.id
                    ORDER BY pc.calculated_at DESC
                    LIMIT ?
                ");
                $stmt->execute([$limit]);
                return $stmt->fetchAll();
            } catch (PDOException $e2) {
                error_log("Recent profits fallback error: " . $e2->getMessage());
                return [];
            }
        }
    }
    
    /**
     * Get order by ID
     */
    private function getOrderById($orderId) {
        try {
            $stmt = $this->db->prepare("
                SELECT o.*, oc.product_id, p.name as product_name
                FROM furn_orders o
                JOIN furn_order_customizations oc ON o.id = oc.order_id
                JOIN furn_products p ON oc.product_id = p.id
                WHERE o.id = ?
            ");
            $stmt->execute([$orderId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            // Fallback if p.id doesn't exist
            error_log("Order by ID query error: " . $e->getMessage());
            try {
                $stmt = $this->db->prepare("
                    SELECT o.*, oc.product_id, 'Product' as product_name
                    FROM furn_orders o
                    JOIN furn_order_customizations oc ON o.id = oc.order_id
                    WHERE o.id = ?
                ");
                $stmt->execute([$orderId]);
                return $stmt->fetch();
            } catch (PDOException $e2) {
                error_log("Order by ID fallback error: " . $e2->getMessage());
                return null;
            }
        }
    }
}