<?php
/**
 * Order Controller
 * Handles order management and manager approval workflow
 */
require_once dirname(__DIR__) . '/core/BaseController.php';
require_once dirname(__DIR__) . '/models/OrderModel.php';
require_once dirname(__DIR__) . '/models/CustomizationModel.php';
require_once dirname(__DIR__) . '/models/ProductModel.php';
require_once dirname(__DIR__) . '/models/AuditLogModel.php';
require_once dirname(__DIR__) . '/services/SmsService.php';

class OrderController extends BaseController {
    private $orderModel;
    private $customizationModel;
    private $productModel;
    private $auditLogModel;
    
    public function __construct() {
        parent::__construct();
        $this->orderModel = new OrderModel();
        $this->customizationModel = new CustomizationModel();
        $this->productModel = new ProductModel();
        $this->auditLogModel = new AuditLogModel();
    }
    
    /**
     * Manager dashboard - view pending orders for approval
     */
    public function managerDashboard() {
        // Check if user is manager or admin
        if (!$this->auth->isLoggedIn() || !in_array($this->auth->getUserRole(), ['manager', 'admin'])) {
            $this->redirect('/login');
            return;
        }
        
        $pendingOrders = $this->orderModel->getPendingApprovalOrders();
        $pendingCustomizations = $this->customizationModel->getPendingCustomizations();
        
        $this->view('manager/dashboard', [
            'pendingOrders' => $pendingOrders,
            'pendingCustomizations' => $pendingCustomizations,
            'pageTitle' => 'Manager Dashboard - Order Approvals'
        ]);
    }
    
    /**
     * View order details for manager review
     */
    public function viewOrder($orderId) {
        // Check authentication and permissions
        if (!$this->auth->isLoggedIn() || !in_array($this->auth->getUserRole(), ['manager', 'admin', 'customer'])) {
            $this->redirect('/login');
            return;
        }
        
        $order = $this->orderModel->getOrderWithDetails($orderId);
        if (!$order) {
            $this->showError('Order not found');
            return;
        }
        
        // Check if customer can view their own order
        if ($this->auth->getUserRole() === 'customer' && $order['customer_id'] != $this->auth->getUserId()) {
            $this->showError('Access denied');
            return;
        }
        
        $customizations = $this->customizationModel->getCustomizationsByOrder($orderId);
        
        $this->view('orders/view', [
            'order' => $order,
            'customizations' => $customizations,
            'pageTitle' => 'Order Details - ' . $order['order_number']
        ]);
    }
    
    /**
     * Manager cost approval form
     */
    public function approveCost($customizationId) {
        // Check if user is manager or admin
        if (!$this->auth->isLoggedIn() || !in_array($this->auth->getUserRole(), ['manager', 'admin'])) {
            $this->redirect('/login');
            return;
        }
        
        $customization = $this->customizationModel->getCustomizationWithProduct($customizationId);
        if (!$customization) {
            $this->showError('Customization not found');
            return;
        }
        
        // Check if already approved
        if ($customization['adjusted_price'] !== null) {
            $this->showError('This customization has already been approved');
            return;
        }
        
        $order = $this->orderModel->getOrderWithDetails($customization['order_id']);
        
        $this->view('manager/approve_cost', [
            'customization' => $customization,
            'order' => $order,
            'pageTitle' => 'Approve Customization Cost'
        ]);
    }
    
    /**
     * Process cost approval
     */
    public function processApproval() {
        // Check authentication and permissions
        if (!$this->auth->isLoggedIn() || !in_array($this->auth->getUserRole(), ['manager', 'admin'])) {
            $this->redirect('/login');
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->showError('Invalid request method');
            return;
        }
        
        // Validate CSRF token
        if (!$this->security->validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->showError('Invalid security token');
            return;
        }
        
        $customizationId = intval($_POST['customization_id'] ?? 0);
        $adjustedPrice = floatval($_POST['adjusted_price'] ?? 0);
        $depositPercentage = intval($_POST['deposit_percentage'] ?? 50);
        $notes = trim($_POST['notes'] ?? '');
        
        // Validate input
        if ($customizationId <= 0) {
            $this->showError('Invalid customization ID');
            return;
        }
        
        if ($adjustedPrice <= 0) {
            $this->showError('Adjusted price must be greater than zero');
            return;
        }
        
        if ($depositPercentage < 10 || $depositPercentage > 100) {
            $this->showError('Deposit percentage must be between 10% and 100%');
            return;
        }
        
        // Get customization details
        $customization = $this->customizationModel->getCustomizationWithProduct($customizationId);
        if (!$customization) {
            $this->showError('Customization not found');
            return;
        }
        
        if ($customization['adjusted_price'] !== null) {
            $this->showError('This customization has already been approved');
            return;
        }
        
        $order = $this->orderModel->getOrderWithDetails($customization['order_id']);
        
        try {
            // Start transaction
            $sendApprovalSms = false;
            $this->db->beginTransaction();
            
            // Log old values for audit
            $oldValues = [
                'adjusted_price' => $customization['adjusted_price'],
                'status' => $customization['status'] ?? 'pending'
            ];
            
            // Update customization with adjusted price
            $this->customizationModel->updatePrice($customizationId, $adjustedPrice);
            
            // Check if all customizations for this order are approved
            $pendingCustomizations = $this->customizationModel->getPendingCustomizations($customization['order_id']);
            // If there are no pending customizations, always update order status to cost_estimated
            if (empty($pendingCustomizations)) {
                $orderTotal = $this->orderModel->calculateOrderTotal($customization['order_id']);
                // Calculate deposit amount based on the total order value and deposit percentage
                $depositAmount = $orderTotal * ($depositPercentage / 100);
                $this->orderModel->updateStatus($customization['order_id'], 'cost_estimated', [
                    'total_amount' => $orderTotal,
                    'deposit_amount' => $depositAmount
                ]);
                $sendApprovalSms = true;
                // Log order status change
                $this->auditLogModel->logAction(
                    $this->auth->getUserId(),
                    'order_status_changed',
                    'furn_orders',
                    $customization['order_id'],
                    ['status' => 'pending_cost_approval'],
                    ['status' => 'cost_estimated', 'total_amount' => $orderTotal, 'deposit_amount' => $depositAmount]
                );
            }
            
            // Log the approval action
            $newValues = [
                'adjusted_price' => $adjustedPrice,
                'deposit_percentage' => $depositPercentage,
                'manager_notes' => $notes
            ];
            
            $this->auditLogModel->logAction(
                $this->auth->getUserId(),
                'cost_approved',
                'furn_order_customizations',
                $customizationId,
                $oldValues,
                $newValues
            );
            
            // Commit transaction
            $this->db->commit();

            if ($sendApprovalSms) {
                try {
                    if (!empty($order['phone'])) {
                        $smsService = new SmsService();
                        $smsService->sendOrderNotification(
                            $order['phone'],
                            $customization['order_id'],
                            'approved',
                            $order['first_name'] ?? ''
                        );
                    }
                } catch (Exception $e) {
                    error_log('Approval SMS error: ' . $e->getMessage());
                }
            }
            
            $this->setFlashMessage('success', 'Cost approved successfully. Customer can now proceed with deposit payment.');
            $this->redirect('/manager/dashboard');
            
        } catch (Exception $e) {
            // Rollback transaction
            $this->db->rollback();
            error_log('Order approval error: ' . $e->getMessage());
            $this->showError('Failed to process approval. Please try again.');
        }
    }
    
    /**
     * Customer creates new order with customization
     */
    public function createOrder() {
        // Check if customer is logged in
        if (!$this->auth->isLoggedIn() || $this->auth->getUserRole() !== 'customer') {
            $this->redirect('/login');
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->processOrderCreation();
            return;
        }
        
        // Show order creation form
        $products = $this->productModel->getActiveProductsWithCategories();
        
        $this->view('orders/create', [
            'products' => $products,
            'pageTitle' => 'Create Custom Order'
        ]);
    }
    
    /**
     * Process order creation
     */
    private function processOrderCreation() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->showError('Invalid request method');
            return;
        }
        
        // Validate CSRF token
        if (!$this->security->validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->showError('Invalid security token');
            return;
        }
        
        $productId = intval($_POST['product_id'] ?? 0);
        $quantity = intval($_POST['quantity'] ?? 1);
        $sizeModifications = trim($_POST['size_modifications'] ?? '');
        $colorSelection = trim($_POST['color_selection'] ?? '');
        $materialUpgrade = trim($_POST['material_upgrade'] ?? '');
        $additionalFeatures = trim($_POST['additional_features'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $specialInstructions = trim($_POST['special_instructions'] ?? '');
        
        // Validate required fields
        if ($productId <= 0) {
            $this->showError('Please select a product');
            return;
        }
        
        if ($quantity <= 0) {
            $this->showError('Quantity must be greater than zero');
            return;
        }
        
        try {
            // Get product details
            $product = $this->productModel->findById($productId);
            if (!$product) {
                $this->showError('Selected product not found');
                return;
            }
            
            // Start transaction
            $this->db->beginTransaction();
            
            // Create order
            $orderData = [
                'customer_id' => $this->auth->getUserId(),
                'special_instructions' => $specialInstructions
            ];
            
            $orderId = $this->orderModel->createOrder($orderData);
            
            // Create customization
            $customizationData = [
                'order_id' => $orderId,
                'product_id' => $productId,
                'quantity' => $quantity,
                'size_modifications' => $sizeModifications,
                'color_selection' => $colorSelection,
                'material_upgrade' => $materialUpgrade,
                'additional_features' => $additionalFeatures,
                'notes' => $notes,
                'base_price' => $product['base_price']
            ];
            
            // Handle file upload if provided
            if (isset($_FILES['reference_image']) && $_FILES['reference_image']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = $this->security->validateUpload($_FILES['reference_image'], ['jpg', 'jpeg', 'png', 'gif'], 5 * 1024 * 1024);
                if ($uploadResult && $uploadResult['valid']) {
                    $uploadDir = dirname(dirname(__DIR__)) . '/public/uploads/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    $filePath = $uploadDir . $uploadResult['secure_name'];
                    if (move_uploaded_file($_FILES['reference_image']['tmp_name'], $filePath)) {
                        $customizationData['reference_image_path'] = '/uploads/' . $uploadResult['secure_name'];
                    }
                }
            }
            
            $this->customizationModel->createCustomization($customizationData);
            
            // Log the order creation
            $this->auditLogModel->logAction(
                $this->auth->getUserId(),
                'order_created',
                'furn_orders',
                $orderId
            );
            
            // Commit transaction
            $this->db->commit();

            try {
                $orderDetails = $this->orderModel->getOrderWithDetails($orderId);
                if (!empty($orderDetails['phone'])) {
                    $smsService = new SmsService();
                    $smsService->sendOrderNotification(
                        $orderDetails['phone'],
                        $orderId,
                        'created',
                        $orderDetails['first_name'] ?? ''
                    );
                }
            } catch (Exception $e) {
                error_log('Order SMS error: ' . $e->getMessage());
            }
            
            $this->setFlashMessage('success', 'Order created successfully! A manager will review and approve the cost shortly.');
            $this->redirect('/orders/my-orders');
            
        } catch (Exception $e) {
            // Rollback transaction
            $this->db->rollback();
            error_log('Order creation error: ' . $e->getMessage());
            $this->showError('Failed to create order. Please try again.');
        }
    }
    
    /**
     * View customer's orders
     */
    public function myOrders() {
        // Check if customer is logged in
        if (!$this->auth->isLoggedIn() || $this->auth->getUserRole() !== 'customer') {
            $this->redirect('/login');
            return;
        }
        
        $orders = $this->orderModel->getOrdersByCustomer($this->auth->getUserId());
        
        $this->view('orders/my_orders', [
            'orders' => $orders,
            'pageTitle' => 'My Orders'
        ]);
    }
}