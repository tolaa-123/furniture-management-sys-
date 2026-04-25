<?php
/**
 * Submit Full Payment (pay entire order amount at once, no deposit split)
 */
session_start();
require_once '../../config/config.php';
require_once '../../config/db_config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $csrfToken    = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION[CSRF_TOKEN_NAME] ?? '';
    if (!$csrfToken || !$sessionToken || !hash_equals($sessionToken, $csrfToken)) {
        throw new Exception('Invalid CSRF token');
    }

    $orderId       = intval($_POST['order_id'] ?? 0);
    $amount        = floatval($_POST['amount'] ?? 0);
    $paymentMethod = $_POST['payment_method'] ?? '';
    $transactionNotes = $_POST['transaction_notes'] ?? '';
    $customerId    = $_SESSION['user_id'];

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
        $filename = 'fullpay_' . $orderId . '_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['receipt_image']['tmp_name'], $uploadDir . $filename);
        $receiptImage = 'uploads/payments/' . $filename;
    }

    // Ensure columns exist before transaction (ALTER TABLE causes implicit commit)
    try { $pdo->exec("ALTER TABLE furn_payments ADD COLUMN IF NOT EXISTS receipt_image VARCHAR(255) DEFAULT NULL, ADD COLUMN IF NOT EXISTS transaction_notes TEXT DEFAULT NULL"); } catch (PDOException $e) {}

    // Prevent duplicate full payment
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM furn_payments WHERE order_id = ? AND payment_type IN ('full','prepayment','deposit') AND status IN ('pending','approved','verified')");
    $stmtCheck->execute([$orderId]);
    if ($stmtCheck->fetchColumn() > 0) {
        throw new Exception('A payment has already been submitted for this order.');
    }

    $pdo->beginTransaction();

    // Insert as full payment
    $stmt = $pdo->prepare("INSERT INTO furn_payments (order_id, customer_id, amount, payment_type, payment_method, receipt_image, transaction_notes, status, created_at) VALUES (?, ?, ?, 'full', ?, ?, ?, 'pending', NOW())");
    $stmt->execute([$orderId, $customerId, $amount, $paymentMethod, $receiptImage, $transactionNotes]);

    // Update order — mark deposit_paid = full amount, status = deposit_paid (manager will verify)
    $pdo->prepare("UPDATE furn_orders SET deposit_paid = ?, status = 'deposit_paid', updated_at = NOW() WHERE id = ? AND customer_id = ?")
        ->execute([$amount, $orderId, $customerId]);

    $pdo->commit();

    // Notify manager
    try {
        require_once __DIR__ . '/../../app/includes/notification_helper.php';
        notifyRole($pdo, 'manager', 'payment', 'Full Payment Received',
            'A customer submitted full payment for order #' . $orderId . '. Amount: ETB ' . number_format($amount, 2),
            $orderId, '/manager/payments', 'high');
    } catch (Exception $e) { error_log("Notification error: " . $e->getMessage()); }

    echo json_encode(['success' => true, 'message' => 'Full payment submitted successfully. Manager will verify shortly.']);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
