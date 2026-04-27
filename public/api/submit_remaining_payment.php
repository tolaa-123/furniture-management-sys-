<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/db_config.php';
header('Content-Type: application/json');

// Check database connection
if (!isset($pdo) || !$pdo) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Define CSRF token name if not defined
if (!defined('CSRF_TOKEN_NAME')) define('CSRF_TOKEN_NAME', 'csrf_token');

try {
    // CSRF validation using the app constant
    $csrfToken = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION[CSRF_TOKEN_NAME] ?? '';
    if (!$csrfToken || !$sessionToken || !hash_equals($sessionToken, $csrfToken)) {
        throw new Exception('Invalid CSRF token');
    }
    
    $orderId = intval($_POST['order_id'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);
    $rawMethod = $_POST['payment_method'] ?? '';
    $transactionNotes = $_POST['transaction_notes'] ?? '';
    $customerId = $_SESSION['user_id'];

    // Normalize payment_method to match DB enum('cash','bank','mobile','bank_transfer')
    $methodMap = ['bank_transfer' => 'bank_transfer', 'bank' => 'bank', 'cash' => 'cash', 'mobile' => 'mobile'];
    $paymentMethod = $methodMap[$rawMethod] ?? 'cash';

    if (!$orderId || !$amount) {
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
        
        $filename = 'payment_remaining_' . $orderId . '_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['receipt_image']['tmp_name'], $uploadDir . $filename);
        $receiptImage = 'uploads/payments/' . $filename;
    }
    
    // Prevent duplicate final payment rows
    $stmtCheck = $pdo->prepare("SELECT payment_id, status FROM furn_payments WHERE order_id = ? AND customer_id = ? AND payment_type IN ('final','postpayment','remaining','final_payment','full_payment') AND status IN ('pending','approved','verified') ORDER BY created_at DESC, payment_id DESC LIMIT 1");
    $stmtCheck->execute([$orderId, $customerId]);
    $existingFinal = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($existingFinal) {
        if ($existingFinal['status'] === 'pending') {
            echo json_encode([
                'success' => true,
                'message' => 'Final payment is already submitted and pending manager verification.'
            ]);
            exit;
        }

        throw new Exception('A final payment has already been submitted for this order.');
    }

    $pdo->beginTransaction();

    // Insert payment with type 'final'
    $stmt = $pdo->prepare("INSERT INTO furn_payments (order_id, customer_id, amount, payment_type, payment_method, receipt_image, transaction_notes, payment_date, status, created_at) VALUES (?, ?, ?, 'final', ?, ?, ?, CURDATE(), 'pending', NOW())");
    $stmt->execute([$orderId, $customerId, $amount, $paymentMethod, $receiptImage, $transactionNotes]);
    
    // Update order - mark as final payment submitted (awaiting manager verification)
    $stmt = $pdo->prepare("UPDATE furn_orders SET status = 'final_payment_paid' WHERE id = ? AND customer_id = ?");
    $stmt->execute([$orderId, $customerId]);
    
    // Notify ALL managers
    require_once __DIR__ . '/../../app/includes/notification_helper.php';
    notifyRole($pdo, 'manager', 'payment', 'Remaining Balance Payment Received',
        'A customer submitted the remaining balance payment for order #' . $orderId . '.',
        $orderId, '/manager/payments', 'high');
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Payment submitted successfully']);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
