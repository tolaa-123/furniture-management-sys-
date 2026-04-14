<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/db_config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

try {
    $csrfToken   = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION[CSRF_TOKEN_NAME] ?? '';
    if (!$csrfToken || !$sessionToken || !hash_equals($sessionToken, $csrfToken))
        throw new Exception('Invalid CSRF token');

    $orderId    = intval($_POST['order_id'] ?? 0);
    $customerId = $_SESSION['user_id'];
    $method     = $_POST['payment_method'] ?? '';
    $notes      = $_POST['transaction_notes'] ?? '';

    if (!$orderId || !$method) throw new Exception('Missing required fields');

    // Fetch order
    $stmt = $pdo->prepare("SELECT * FROM furn_orders WHERE id = ? AND customer_id = ? AND status = 'cost_estimated'");
    $stmt->execute([$orderId, $customerId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) throw new Exception('Order not found or not eligible for payment');

    $totalAmount = floatval($order['estimated_cost'] ?? 0);
    if ($totalAmount <= 0) throw new Exception('Order has no estimated cost');

    // Prevent duplicate full payment
    $dup = $pdo->prepare("SELECT COUNT(*) FROM furn_payments WHERE order_id = ? AND payment_type = 'full_payment' AND status IN ('pending','approved','verified')");
    $dup->execute([$orderId]);
    if ($dup->fetchColumn() > 0) throw new Exception('A full payment has already been submitted for this order.');

    // Handle receipt upload (required for bank, optional for cash)
    $receiptImage = null;
    if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg','jpeg','png','pdf'];
        $ext = strtolower(pathinfo($_FILES['receipt_image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed) || $_FILES['receipt_image']['size'] > 5 * 1024 * 1024)
            throw new Exception('Invalid receipt file');
        $uploadDir = __DIR__ . '/../uploads/payments/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $filename = 'full_' . $orderId . '_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['receipt_image']['tmp_name'], $uploadDir . $filename);
        $receiptImage = 'uploads/payments/' . $filename;
    } elseif ($method === 'bank_transfer') {
        throw new Exception('Please upload your payment receipt for bank transfer');
    }

    $pdo->beginTransaction();

    // Insert full payment record
    $pdo->prepare("INSERT INTO furn_payments (order_id, customer_id, amount, payment_type, payment_method, receipt_image, transaction_notes, payment_date, status, created_at)
        VALUES (?, ?, ?, 'full_payment', ?, ?, ?, CURDATE(), 'pending', NOW())")
        ->execute([$orderId, $customerId, $totalAmount, $method, $receiptImage, $notes]);

    // Update order status
    $pdo->prepare("UPDATE furn_orders SET status = 'final_payment_paid', deposit_paid = ? WHERE id = ? AND customer_id = ?")
        ->execute([$totalAmount, $orderId, $customerId]);

    // Notify ALL managers
    require_once __DIR__ . '/../../app/includes/notification_helper.php';
    notifyRole($pdo, 'manager', 'payment', 'Full Payment Received',
        'A customer submitted full payment for order #' . $orderId . '.',
        $orderId, '/manager/payments', 'high');

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Full payment submitted successfully']);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
