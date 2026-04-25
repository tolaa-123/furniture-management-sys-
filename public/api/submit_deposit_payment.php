<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/db_config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // CSRF validation using the app constant
    $csrfToken = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION[CSRF_TOKEN_NAME] ?? '';
    if (!$csrfToken || !$sessionToken || !hash_equals($sessionToken, $csrfToken)) {
        throw new Exception('Invalid CSRF token');
    }
    
    $orderId = intval($_POST['order_id'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);
    $paymentMethod = $_POST['payment_method'] ?? '';
    $transactionNotes = $_POST['transaction_notes'] ?? '';
    $customerId = $_SESSION['user_id'];
    
    if (!$orderId || !$amount || !$paymentMethod) {
        throw new Exception('Missing required fields');
    }
    
    // Handle receipt upload
    $receiptImage = null;
    if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $ext = strtolower(pathinfo($_FILES['receipt_image']['name'], PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed) || $_FILES['receipt_image']['size'] > 5 * 1024 * 1024) {
            throw new Exception('Invalid file');
        }
        
        $uploadDir = __DIR__ . '/../uploads/payments/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        
        $filename = 'payment_' . $orderId . '_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['receipt_image']['tmp_name'], $uploadDir . $filename);
        $receiptImage = 'uploads/payments/' . $filename;
    }
    
    // Ensure required columns exist BEFORE transaction (ALTER TABLE causes implicit commit)
    try {
        $pdo->exec("ALTER TABLE furn_payments 
            ADD COLUMN IF NOT EXISTS receipt_image VARCHAR(255) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS transaction_notes TEXT DEFAULT NULL");
    } catch (PDOException $e) { /* ignore */ }
    
    try {
        $pdo->exec("ALTER TABLE furn_orders 
            ADD COLUMN IF NOT EXISTS deposit_paid DECIMAL(12,2) DEFAULT 0,
            ADD COLUMN IF NOT EXISTS estimated_cost DECIMAL(12,2) DEFAULT NULL");
    } catch (PDOException $e) { /* ignore */ }
    
    $pdo->beginTransaction();

    // Prevent duplicate deposit rows — only insert if no pending/approved deposit exists
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM furn_payments WHERE order_id = ? AND payment_type IN ('deposit','prepayment') AND status IN ('pending','approved','verified')");
    $stmtCheck->execute([$orderId]);
    if ($stmtCheck->fetchColumn() > 0) {
        throw new Exception('A deposit payment has already been submitted for this order.');
    }

    // Insert payment
    try {
        $stmt = $pdo->prepare("INSERT INTO furn_payments (order_id, customer_id, amount, payment_type, payment_method, receipt_image, transaction_notes, status, created_at) VALUES (?, ?, ?, 'deposit', ?, ?, ?, 'pending', NOW())");
        $stmt->execute([$orderId, $customerId, $amount, $paymentMethod, $receiptImage, $transactionNotes]);
    } catch (PDOException $e) {
        $stmt = $pdo->prepare("INSERT INTO furn_payments (order_id, customer_id, amount, payment_type, payment_method, receipt_image, transaction_notes, status, created_at) VALUES (?, ?, ?, 'prepayment', ?, ?, ?, 'pending', NOW())");
        $stmt->execute([$orderId, $customerId, $amount, $paymentMethod, $receiptImage, $transactionNotes]);
    }
    
    // Update order
    $stmt = $pdo->prepare("UPDATE furn_orders SET deposit_paid = ?, status = 'deposit_paid' WHERE id = ? AND customer_id = ?");
    $stmt->execute([$amount, $orderId, $customerId]);
    
    // Notify managers and admins
    $stmt = $pdo->prepare("INSERT INTO furn_notifications (user_id, type, title, message, related_id, created_at) SELECT id, 'payment', 'New Deposit Payment', 'Customer submitted deposit payment', ?, NOW() FROM furn_users WHERE role IN ('manager','admin')");
    $stmt->execute([$orderId]);
    
    // Send SMS notifications
    try {
        // Check if SMS notifications are enabled
        $stmtSmsCheck = $pdo->prepare("SELECT setting_value FROM furn_settings WHERE setting_key = 'sms_notifications'");
        $stmtSmsCheck->execute();
        $smsEnabled = $stmtSmsCheck->fetch(PDO::FETCH_ASSOC);
        
        if ($smsEnabled && $smsEnabled['setting_value'] == '1') {
            require_once '../../app/services/SmsService.php';
            $smsService = new SmsService(); // Uses SMS_MODE constant from db_config.php
            
            // Get customer info
            $stmtCust = $pdo->prepare("SELECT phone, first_name FROM furn_users WHERE id = ?");
            $stmtCust->execute([$customerId]);
            $customer = $stmtCust->fetch(PDO::FETCH_ASSOC);
            
            // SMS to customer
            if ($customer && $customer['phone']) {
                $smsService->sendPaymentNotification($customer['phone'], $orderId, $amount, 'received', $customer['first_name']);
            }
            
            // SMS to all managers and admins
            $stmtMgr = $pdo->prepare("SELECT phone FROM furn_users WHERE role IN ('manager','admin') AND phone IS NOT NULL");
            $stmtMgr->execute();
            $managerPhones = $stmtMgr->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($managerPhones as $managerPhone) {
                if ($managerPhone) {
                    $smsService->sendManagerNotification($managerPhone, 'new_payment', [
                        'order_id' => $orderId,
                        'amount' => $amount
                    ]);
                }
            }
        }
    } catch (Exception $e) {
        error_log("SMS error: " . $e->getMessage());
    }
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Payment submitted successfully']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}