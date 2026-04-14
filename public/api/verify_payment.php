<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/db_config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // CSRF check
    $csrfPost = $_POST['csrf_token'] ?? '';
    $csrfSess = $_SESSION[CSRF_TOKEN_NAME] ?? '';
    if (!$csrfPost || !$csrfSess || !hash_equals($csrfSess, $csrfPost)) {
        throw new Exception('Invalid CSRF token');
    }

    $paymentId = intval($_POST['payment_id'] ?? 0);
    $action = $_POST['action'] ?? ''; // 'approve' or 'reject'
    
    if (!$paymentId || !in_array($action, ['approve', 'reject'])) {
        throw new Exception('Invalid request');
    }
    
    $pdo->beginTransaction();
    
    if ($action === 'approve') {
        // Update payment
        $stmt = $pdo->prepare("UPDATE furn_payments SET status = 'verified', verified_by = ?, verified_at = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $paymentId]);
        
        // Get order and payment type
        $stmt = $pdo->prepare("SELECT p.order_id, p.payment_type, o.customer_id, o.order_number FROM furn_payments p JOIN furn_orders o ON o.id=p.order_id WHERE p.id = ?");
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch();
        
        if ($payment) {
            if ($payment['payment_type'] === 'deposit' || $payment['payment_type'] === 'prepayment') {
                $pdo->prepare("UPDATE furn_orders SET status = 'payment_verified' WHERE id = ?")->execute([$payment['order_id']]);
            } elseif ($payment['payment_type'] === 'postpayment' || $payment['payment_type'] === 'final_payment' || $payment['payment_type'] === 'final') {
                $pdo->prepare("UPDATE furn_orders SET status = 'completed' WHERE id = ?")->execute([$payment['order_id']]);
            }
            // Notify customer
            $pdo->commit();
            require_once __DIR__ . '/../../app/includes/notification_helper.php';
            $ptLabel = in_array($payment['payment_type'],['deposit','prepayment']) ? 'Deposit' : 'Final Payment';
            insertNotification($pdo, $payment['customer_id'], 'payment', $ptLabel . ' Payment Approved',
                'Your ' . strtolower($ptLabel) . ' payment for order ' . $payment['order_number'] . ' has been approved.',
                $payment['order_id'], '/customer/my-orders', 'high');
        } else {
            $pdo->commit();
        }
    } else {
        $stmt = $pdo->prepare("UPDATE furn_payments SET status = 'rejected', verified_by = ?, verified_at = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $paymentId]);
        // Get customer to notify
        $stmt = $pdo->prepare("SELECT p.order_id, o.customer_id, o.order_number FROM furn_payments p JOIN furn_orders o ON o.id=p.order_id WHERE p.id = ?");
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch();
        $pdo->commit();
        if ($payment) {
            require_once __DIR__ . '/../../app/includes/notification_helper.php';
            insertNotification($pdo, $payment['customer_id'], 'payment', 'Payment Rejected',
                'Your payment for order ' . $payment['order_number'] . ' was rejected. Please re-upload a valid receipt.',
                $payment['order_id'], '/customer/my-orders', 'high');
        }
    }
    echo json_encode(['success' => true, 'message' => 'Payment ' . $action . 'd successfully']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}