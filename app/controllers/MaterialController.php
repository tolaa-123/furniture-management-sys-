<?php
/**
 * Material Controller
 * Handles material management, stock tracking, and inventory operations
 */
require_once dirname(__DIR__) . '/../core/BaseController.php';
require_once dirname(__DIR__) . '/models/MaterialModel.php';
require_once dirname(__DIR__) . '/models/MaterialCategoryModel.php';
require_once dirname(__DIR__) . '/models/SupplierModel.php';
require_once dirname(__DIR__) . '/models/MaterialTransactionModel.php';
require_once dirname(__DIR__) . '/models/LowStockAlertModel.php';

class MaterialController extends BaseController {
    private $materialModel;
    private $categoryModel;
    private $supplierModel;
    private $transactionModel;
    private $alertModel;
    
    public function __construct() {
        parent::__construct();
        $this->materialModel = new MaterialModel();
        $this->categoryModel = new MaterialCategoryModel();
        $this->supplierModel = new SupplierModel();
        $this->transactionModel = new MaterialTransactionModel();
        $this->alertModel = new LowStockAlertModel();
    }
    
    /**
     * Material Management Dashboard
     */
    public function dashboard() {
        // Check admin access
        if (!$this->isAdmin()) {
            $this->redirect('/login');
            return;
        }
        
        // Get dashboard statistics
        $stats = $this->materialModel->getMaterialStats();
        $lowStockCount = $this->materialModel->getLowStockCount();
        $alertStats = $this->alertModel->getAlertStats();
        $valuation = $this->materialModel->getMaterialValuation();
        
        // Get recent transactions
        $recentTransactions = $this->transactionModel->getRecentTransactions(10);
        
        // Get low stock alerts
        $activeAlerts = $this->alertModel->getActiveAlerts();
        
        // Get materials by category
        $materialsByCategory = $this->materialModel->getMaterialsByCategoryWithStatus();
        
        $data = [
            'stats' => $stats,
            'lowStockCount' => $lowStockCount,
            'alertStats' => $alertStats,
            'valuation' => $valuation,
            'recentTransactions' => $recentTransactions,
            'activeAlerts' => $activeAlerts,
            'materialsByCategory' => $materialsByCategory
        ];
        
        $this->render('materials/dashboard', $data);
    }
    
    /**
     * List all materials
     */
    public function index() {
        if (!$this->isAdmin()) {
            $this->redirect('/login');
            return;
        }
        
        $search = $_GET['search'] ?? '';
        $categoryId = $_GET['category'] ?? '';
        $stockStatus = $_GET['status'] ?? '';
        
        $materials = $this->materialModel->getAllMaterials($search, $categoryId, $stockStatus);
        $categories = $this->categoryModel->getActiveCategories();
        
        $data = [
            'materials' => $materials,
            'categories' => $categories,
            'search' => $search,
            'selectedCategory' => $categoryId,
            'selectedStatus' => $stockStatus
        ];
        
        $this->render('materials/index', $data);
    }
    
    /**
     * Add new material
     */
    public function create() {
        if (!$this->isAdmin()) {
            $this->redirect('/login');
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'name' => $_POST['name'] ?? '',
                'description' => $_POST['description'] ?? '',
                'category_id' => $_POST['category_id'] ?? null,
                'unit' => $_POST['unit'] ?? '',
                'current_stock' => floatval($_POST['current_stock'] ?? 0),
                'minimum_stock' => floatval($_POST['minimum_stock'] ?? 0),
                'reorder_point' => floatval($_POST['reorder_point'] ?? 0),
                'supplier' => $_POST['supplier'] ?? '',
                'average_cost' => floatval($_POST['average_cost'] ?? 0),
                'storage_location' => $_POST['storage_location'] ?? '',
                'notes' => $_POST['notes'] ?? ''
            ];
            
            $materialId = $this->materialModel->createMaterial($data);
            
            if ($materialId) {
                // Log the action
                $this->logAudit('material_created', 'furn_materials', $materialId, $data);
                $this->setFlashMessage('success', 'Material added successfully');
                $this->redirect('/materials');
            } else {
                $this->setFlashMessage('error', 'Failed to add material');
            }
        }
        
        $categories = $this->categoryModel->getActiveCategories();
        $suppliers = $this->supplierModel->getActiveSuppliers();
        
        $data = [
            'categories' => $categories,
            'suppliers' => $suppliers
        ];
        
        $this->render('materials/create', $data);
    }
    
    /**
     * Edit material
     */
    public function edit($materialId) {
        if (!$this->isAdmin()) {
            $this->redirect('/login');
            return;
        }
        
        $material = $this->materialModel->getMaterial($materialId);
        if (!$material) {
            $this->setFlashMessage('error', 'Material not found');
            $this->redirect('/materials');
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'name' => $_POST['name'] ?? '',
                'description' => $_POST['description'] ?? '',
                'category_id' => $_POST['category_id'] ?? null,
                'unit' => $_POST['unit'] ?? '',
                'minimum_stock' => floatval($_POST['minimum_stock'] ?? 0),
                'reorder_point' => floatval($_POST['reorder_point'] ?? 0),
                'supplier' => $_POST['supplier'] ?? '',
                'storage_location' => $_POST['storage_location'] ?? '',
                'notes' => $_POST['notes'] ?? ''
            ];
            
            if ($this->materialModel->updateMaterial($materialId, $data)) {
                // Log the action
                $this->logAudit('material_updated', 'furn_materials', $materialId, $data);
                $this->setFlashMessage('success', 'Material updated successfully');
                $this->redirect('/materials');
            } else {
                $this->setFlashMessage('error', 'Failed to update material');
            }
        }
        
        $categories = $this->categoryModel->getActiveCategories();
        $suppliers = $this->supplierModel->getActiveSuppliers();
        
        $data = [
            'material' => $material,
            'categories' => $categories,
            'suppliers' => $suppliers
        ];
        
        $this->render('materials/edit', $data);
    }
    
    /**
     * Add stock to material
     */
    public function addStock($materialId) {
        if (!$this->isAdmin()) {
            $this->redirect('/login');
            return;
        }
        
        $material = $this->materialModel->getMaterial($materialId);
        if (!$material) {
            $this->setFlashMessage('error', 'Material not found');
            $this->redirect('/materials');
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $quantity = floatval($_POST['quantity'] ?? 0);
            $unitCost = floatval($_POST['unit_cost'] ?? 0);
            $notes = $_POST['notes'] ?? '';
            
            if ($quantity <= 0) {
                $this->setFlashMessage('error', 'Please enter a valid quantity');
            } else {
                if ($this->materialModel->addStock($materialId, $quantity, $unitCost, $notes, $_SESSION['user_id'])) {
                    $this->logAudit('stock_added', 'furn_materials', $materialId, [
                        'quantity' => $quantity,
                        'unit_cost' => $unitCost,
                        'notes' => $notes
                    ]);
                    $this->setFlashMessage('success', 'Stock added successfully');
                    $this->redirect('/materials');
                } else {
                    $this->setFlashMessage('error', 'Failed to add stock');
                }
            }
        }
        
        $data = ['material' => $material];
        $this->render('materials/add_stock', $data);
    }
    
    /**
     * Adjust stock manually
     */
    public function adjustStock($materialId) {
        if (!$this->isAdmin()) {
            $this->redirect('/login');
            return;
        }
        
        $material = $this->materialModel->getMaterial($materialId);
        if (!$material) {
            $this->setFlashMessage('error', 'Material not found');
            $this->redirect('/materials');
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $quantity = floatval($_POST['quantity'] ?? 0);
            $notes = $_POST['notes'] ?? '';
            
            if ($quantity == 0) {
                $this->setFlashMessage('error', 'Please enter a valid adjustment quantity');
            } else {
                if ($this->materialModel->adjustStock($materialId, $quantity, $notes, $_SESSION['user_id'])) {
                    $this->logAudit('stock_adjusted', 'furn_materials', $materialId, [
                        'adjustment' => $quantity,
                        'notes' => $notes
                    ]);
                    $this->setFlashMessage('success', 'Stock adjusted successfully');
                    $this->redirect('/materials');
                } else {
                    $this->setFlashMessage('error', 'Failed to adjust stock');
                }
            }
        }
        
        $data = ['material' => $material];
        $this->render('materials/adjust_stock', $data);
    }
    
    /**
     * View material transactions
     */
    public function transactions($materialId) {
        if (!$this->isAdmin()) {
            $this->redirect('/login');
            return;
        }
        
        $material = $this->materialModel->getMaterial($materialId);
        if (!$material) {
            $this->setFlashMessage('error', 'Material not found');
            $this->redirect('/materials');
            return;
        }
        
        $transactions = $this->transactionModel->getTransactionsByMaterial($materialId);
        $transactionSummary = $this->transactionModel->getTransactionSummary();
        
        $data = [
            'material' => $material,
            'transactions' => $transactions,
            'transactionSummary' => $transactionSummary
        ];
        
        $this->render('materials/transactions', $data);
    }
    
    /**
     * View low stock alerts
     */
    public function alerts() {
        if (!$this->isAdmin()) {
            $this->redirect('/login');
            return;
        }
        
        $alertLevel = $_GET['level'] ?? 'all';
        $alerts = $alertLevel === 'all' 
            ? $this->alertModel->getActiveAlerts() 
            : $this->alertModel->getAlertsByLevel($alertLevel);
        
        $alertStats = $this->alertModel->getAlertStats();
        
        $data = [
            'alerts' => $alerts,
            'alertStats' => $alertStats,
            'selectedLevel' => $alertLevel
        ];
        
        $this->render('materials/alerts', $data);
    }
    
    /**
     * Resolve low stock alert
     */
    public function resolveAlert($alertId) {
        if (!$this->isAdmin()) {
            $this->redirect('/login');
            return;
        }
        
        if ($this->alertModel->resolveAlert($alertId, $_SESSION['user_id'])) {
            $this->logAudit('alert_resolved', 'furn_low_stock_alerts', $alertId);
            $this->setFlashMessage('success', 'Alert resolved successfully');
        } else {
            $this->setFlashMessage('error', 'Failed to resolve alert');
        }
        
        $this->redirect('/materials/alerts');
    }
    
    /**
     * Check for low stock and create alerts
     */
    public function checkAlerts() {
        if (!$this->isAdmin()) {
            $this->redirect('/login');
            return;
        }
        
        $newAlerts = $this->alertModel->checkAndCreateAlerts();
        
        if ($newAlerts > 0) {
            $this->setFlashMessage('info', "$newAlerts new low stock alerts created");
        } else {
            $this->setFlashMessage('success', 'No new low stock alerts found');
        }
        
        $this->redirect('/materials/alerts');
    }
    
    /**
     * Material categories management
     */
    public function categories() {
        if (!$this->isAdmin()) {
            $this->redirect('/login');
            return;
        }
        
        $categories = $this->categoryModel->getMaterialCountByCategory();
        
        $data = ['categories' => $categories];
        $this->render('materials/categories', $data);
    }
    
    /**
     * Suppliers management
     */
    public function suppliers() {
        if (!$this->isAdmin()) {
            $this->redirect('/login');
            return;
        }
        
        $suppliers = $this->supplierModel->getActiveSuppliers();
        $supplierStats = $this->supplierModel->getSupplierStats();
        
        $data = [
            'suppliers' => $suppliers,
            'supplierStats' => $supplierStats
        ];
        
        $this->render('materials/suppliers', $data);
    }
    
    /**
     * Get material details via AJAX
     */
    public function getMaterial($materialId) {
        header('Content-Type: application/json');
        
        if (!$this->isAdmin()) {
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }
        
        $material = $this->materialModel->getMaterial($materialId);
        if ($material) {
            echo json_encode($material);
        } else {
            echo json_encode(['error' => 'Material not found']);
        }
    }
}