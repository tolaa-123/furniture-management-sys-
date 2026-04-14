<?php
/**
 * Payment Controller
 * Handles deposit and final payment processing
 */
require_once dirname(__DIR__) . '/../core/BaseController.php';
require_once dirname(__DIR__) . '/../models/OrderModel.php';
require_once dirname(__DIR__) . '/../models/PaymentModel.php';
require_once dirname(__DIR__) . '/../models/AuditLogModel.php';
require_once dirname(__DIR__) . '/../services/SmsService.php';

class PaymentController extends BaseController {
    private $orderModel;
    private $paymentModel;
    private $auditLogModel;
    private $db;
    
    public function __construct() {
        parent::__construct();
        $this->orderModel = new OrderModel();
        $this->paymentModel = new PaymentModel();
        $this->auditLogModel = new AuditLogModel();
        $this->db = $this->orderModel->db;
    }
    
    /**
     * Customer uploads deposit payment receipt
     */
    public function uploadDepositReceipt($orderId) {
        // Check if customer is logged in
        if (!$this->auth->isLoggedIn() || $this->auth->getUserRole() !== 'customer') {
            $this->redirect('/login');
            return;
        }
        
        $order = $this->orderModel->getOrderWithDetails($orderId);
        if (!$order) {
            $this->showError('Order not found');
            return;
        }
        
        // Check if customer owns this order
        if ($order['customer_id'] != $this->auth->getUserId()) {
            $this->showError('Access denied');
            return;
        }
        
        // Check if order is in correct status
        if ($order['status'] !== 'waiting_for_deposit') {
            $this->showError('Order is not ready for deposit payment');
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->processDepositReceipt($orderId, $order);
            return;
        }
        
        // Show upload form
        $this->view('payments/upload_deposit', [
            'order' => $order,
            'pageTitle' => 'Upload Deposit Payment Receipt'
        ]);
    }
    
    /**
     * Process deposit receipt upload
     */
    private function processDepositReceipt($orderId, $order) {
        // Validate CSRF token
        if (!$this->security->validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->showError('Invalid security token');
            return;
        }
        
        // Validate amount
        $amount = floatval($_POST['amount'] ?? 0);
        if ($amount <= 0) {
            $this->showError('Please enter a valid amount');
            return;
        }
        
        // Validate required deposit
        if ($amount != $order['deposit_amount']) {
            $this->showError('Payment amount must match the required deposit amount of ETB ' . number_format($order['deposit_amount'], 2));
            return;
        }
        
        // Validate file upload
        if (!isset($_FILES['receipt_image']) || $_FILES['receipt_image']['error'] !== UPLOAD_ERR_OK) {
            $this->showError('Please upload a payment receipt image');
            return;
        }
        
        $uploadResult = $this->security->validateUpload($_FILES['receipt_image'], ['jpg', 'jpeg', 'png', 'gif'], 5 * 1024 * 1024);
        if (!$uploadResult || !$uploadResult['valid']) {
            $this->showError('Invalid file. Please upload a valid image file (JPG, PNG, GIF) under 5MB.');
            return;
        }
        
        try {
            // Save uploaded file
            $uploadDir = dirname(dirname(__DIR__)) . '/public/uploads/receipts/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $filePath = $uploadDir . $uploadResult['secure_name'];
            if (!move_uploaded_file($_FILES['receipt_image']['tmp_name'], $filePath)) {
                throw new Exception('Failed to save uploaded file');
            }
            
            // Create payment receipt record
            $receiptData = [
                'order_id' => $orderId,
                'payment_type' => 'deposit',
                'amount' => $amount,
                'receipt_image_path' => '/uploads/receipts/' . $uploadResult['secure_name'],
                'status' => 'pending'
            ];
            
            $receiptId = $this->paymentModel->createReceipt($receiptData);
            
            // Log the action
            $this->auditLogModel->logAction(
                $this->auth->getUserId(),
                'deposit_receipt_uploaded',
                'furn_payment_receipts',
                $receiptId,
                null,
                ['order_id' => $orderId, 'amount' => $amount]
            );
            
            $this->setFlashMessage('success', 'Deposit receipt uploaded successfully. A manager will review and approve your payment shortly.');
            $this->redirect('/orders/view/' . $orderId);
            
        } catch (Exception $e) {
            error_log('Deposit receipt upload error: ' . $e->getMessage());
            $this->showError('Failed to upload receipt. Please try again.');
        }
    }
    
    /**
     * Manager verifies payment receipt
     */
    public function verifyPayment($receiptId) {
        // Check if manager or admin is logged in
        if (!$this->auth->isLoggedIn() || !in_array($this->auth->getUserRole(), ['manager', 'admin'])) {
            $this->redirect('/login');
            return;
        }
        
        $receipt = $this->paymentModel->getReceiptWithOrder($receiptId);
        if (!$receipt) {
            $this->showError('Payment receipt not found');
            return;
        }
        
        if ($receipt['status'] !== 'pending') {
            $this->showError('This payment receipt has already been processed');
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->processPaymentVerification($receiptId, $receipt);
            return;
        }
        
        // Show verification form
        $this->view('payments/verify_payment', [
            'receipt' => $receipt,
            'pageTitle' => 'Verify Payment Receipt'
        ]);
    }
    
    /**
     * Process payment verification
     */
    private function processPaymentVerification($receiptId, $receipt) {
        // Validate CSRF token
        if (!$this->security->validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->showError('Invalid security token');
            return;
        }
        
        $action = $_POST['action'] ?? '';
        $managerNotes = trim($_POST['manager_notes'] ?? '');
        
        if (!in_array($action, ['approve', 'reject'])) {
            $this->showError('Invalid action');
            return;
        }
        
        try {
            if ($action === 'approve') {
                // Approve payment receipt
                $this->paymentModel->approveReceipt($receiptId, $this->auth->getUserId(), $managerNotes);
                
                // Update order based on payment type
                if ($receipt['payment_type'] === 'deposit') {
                    $this->orderModel->updateDepositPayment($receipt['order_id'], $receipt['amount']);
                    $this->orderModel->updateStatus($receipt['order_id'], 'deposit_paid');
                    
                    // Log order status change
                    $this->auditLogModel->logAction(
                        $this->auth->getUserId(),
                        'order_status_changed',
                        'furn_orders',
                        $receipt['order_id'],
                        ['status' => 'waiting_for_deposit'],
                        ['status' => 'deposit_paid', 'deposit_paid' => $receipt['amount']]
                    );
                } else {
                    // Final payment
                    $this->orderModel->updateFinalPayment($receipt['order_id'], $receipt['amount']);
                    
                    // Log final payment
                    $this->auditLogModel->logAction(
                        $this->auth->getUserId(),
                        'final_payment_approved',
                        'furn_orders',
                        $receipt['order_id'],
                        null,
                        ['final_payment_paid' => $receipt['amount']]
                    );
                }
                
                $message = 'Payment approved successfully.';
                if ($receipt['payment_type'] === 'deposit') {
                    $message .= ' Order is now ready for production.';
                } else {
                    $message .= ' Order completed.';
                }
                
            } else {
                // Reject payment receipt
                $this->paymentModel->rejectReceipt($receiptId, $this->auth->getUserId(), $managerNotes);
                $message = 'Payment receipt rejected.';
            }
            
            // Log the verification action
            $this->auditLogModel->logAction(
                $this->auth->getUserId(),
                'payment_' . $action . 'd',
                'furn_payment_receipts',
                $receiptId,
                ['status' => 'pending'],
                ['status' => $action === 'approve' ? 'approved' : 'rejected', 'manager_notes' => $managerNotes]
            );

            if ($action === 'approve') {
                try {
                    // Check if SMS notifications are enabled
                    $stmt = $this->db->prepare("SELECT setting_value FROM furn_settings WHERE setting_key = 'sms_notifications'");
                    $stmt->execute();
                    $smsEnabled = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($smsEnabled && $smsEnabled['setting_value'] == '1') {
                        $orderDetails = $this->orderModel->getOrderWithDetails($receipt['order_id']);
                        if (!empty($orderDetails['phone'])) {
                            $smsService = new SmsService();
                            $smsService->sendPaymentNotification(
                                $orderDetails['phone'],
                                $receipt['order_id'],
                                $receipt['amount'],
                                'received',
                                $orderDetails['first_name'] ?? ''
                            );

                            if ($orderDetails['status'] === 'completed') {
                                $smsService->sendOrderNotification(
                                    $orderDetails['phone'],
                                    $receipt['order_id'],
                                    'completed',
                                    $orderDetails['first_name'] ?? ''
                                );
                            }
                        }
                    }
                } catch (Exception $e) {
                    error_log('Payment SMS error: ' . $e->getMessage());
                }
            }
            
            $this->setFlashMessage('success', $message);
            $this->redirect('/manager/dashboard');
            
        } catch (Exception $e) {
            error_log('Payment verification error: ' . $e->getMessage());
            $this->showError('Failed to process payment verification. Please try again.');
        }
    }
    
    /**
     * Customer uploads final payment receipt
     */
    public function uploadFinalReceipt($orderId) {
        // Check if customer is logged in
        if (!$this->auth->isLoggedIn() || $this->auth->getUserRole() !== 'customer') {
            $this->redirect('/login');
            return;
        }
        
        $order = $this->orderModel->getOrderWithDetails($orderId);
        if (!$order) {
            $this->showError('Order not found');
            return;
        }
        
        // Check if customer owns this order
        if ($order['customer_id'] != $this->auth->getUserId()) {
            $this->showError('Access denied');
            return;
        }
        
        // Check if order is ready for final payment
        if ($order['status'] !== 'ready_for_delivery') {
            $this->showError('Order is not ready for final payment');
            return;
        }
        
        if ($order['remaining_balance'] <= 0) {
            $this->showError('No remaining balance due for this order');
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->processFinalReceipt($orderId, $order);
            return;
        }
        
        // Show upload form
        $this->view('payments/upload_final', [
            'order' => $order,
            'pageTitle' => 'Upload Final Payment Receipt'
        ]);
    }
    
    /**
     * Process final receipt upload
     */
    private function processFinalReceipt($orderId, $order) {
        // Validate CSRF token
        if (!$this->security->validateCSRFToken($_POST['csrf_token'] ?? '')) {
            $this->showError('Invalid security token');
            return;
        }
        
        // Validate amount
        $amount = floatval($_POST['amount'] ?? 0);
        if ($amount <= 0) {
            $this->showError('Please enter a valid amount');
            return;
        }
        
        // Validate amount doesn't exceed remaining balance
        if ($amount > $order['remaining_balance']) {
            $this->showError('Payment amount cannot exceed remaining balance of ETB ' . number_format($order['remaining_balance'], 2));
            return;
        }
        
        // Validate file upload
        if (!isset($_FILES['receipt_image']) || $_FILES['receipt_image']['error'] !== UPLOAD_ERR_OK) {
            $this->showError('Please upload a payment receipt image');
            return;
        }
        
        $uploadResult = $this->security->validateUpload($_FILES['receipt_image'], ['jpg', 'jpeg', 'png', 'gif'], 5 * 1024 * 1024);
        if (!$uploadResult || !$uploadResult['valid']) {
            $this->showError('Invalid file. Please upload a valid image file (JPG, PNG, GIF) under 5MB.');
            return;
        }
        
        try {
            // Save uploaded file
            $uploadDir = dirname(dirname(__DIR__)) . '/public/uploads/receipts/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $filePath = $uploadDir . $uploadResult['secure_name'];
            if (!move_uploaded_file($_FILES['receipt_image']['tmp_name'], $filePath)) {
                throw new Exception('Failed to save uploaded file');
            }
            
            // Create payment receipt record
            $receiptData = [
                'order_id' => $orderId,
                'payment_type' => 'final',
                'amount' => $amount,
                'receipt_image_path' => '/uploads/receipts/' . $uploadResult['secure_name'],
                'status' => 'pending'
            ];
            
            $receiptId = $this->paymentModel->createReceipt($receiptData);
            
            // Log the action
            $this->auditLogModel->logAction(
                $this->auth->getUserId(),
                'final_receipt_uploaded',
                'furn_payment_receipts',
                $receiptId,
                null,
                ['order_id' => $orderId, 'amount' => $amount]
            );
            
            $this->setFlashMessage('success', 'Final payment receipt uploaded successfully. A manager will review and approve your payment shortly.');
            $this->redirect('/orders/view/' . $orderId);
            
        } catch (Exception $e) {
            error_log('Final receipt upload error: ' . $e->getMessage());
            $this->showError('Failed to upload receipt. Please try again.');
        }
    }
    
    /**
     * Manager dashboard for payment verification
     */
    public function paymentDashboard() {
        // Check if manager or admin is logged in
        if (!$this->auth->isLoggedIn() || !in_array($this->auth->getUserRole(), ['manager', 'admin'])) {
            $this->redirect('/login');
            return;
        }
        
        $pendingReceipts = $this->paymentModel->getPendingReceipts();
        
        $this->view('payments/dashboard', [
            'pendingReceipts' => $pendingReceipts,
            'pageTitle' => 'Payment Verification Dashboard'
        ]);
    }
}