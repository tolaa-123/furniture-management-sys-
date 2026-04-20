<?php
ob_start();
require_once '../../config/db_config.php';
ob_end_clean();

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    header('Location: ' . BASE_URL . '/public/login'); exit;
}

$csrf = $_POST['csrf_token'] ?? '';
$sess = $_SESSION[CSRF_TOKEN_NAME] ?? '';
if (!$csrf || !$sess || !hash_equals($sess, $csrf)) {
    $_SESSION['error_message'] = 'Invalid CSRF token.';
    header('Location: ' . BASE_URL . '/public/customer/my-orders'); exit;
}

$orderId    = intval($_POST['order_id'] ?? 0);
$customerId = $_SESSION['user_id'];

if (!$orderId) {
    $_SESSION['error_message'] = 'Invalid order.';
    header('Location: ' . BASE_URL . '/public/customer/my-orders'); exit;
}

try {
    $stmt = $pdo->prepare("UPDATE furn_orders SET status = 'cancelled' WHERE id = ? AND customer_id = ? AND status IN ('pending_review','pending_cost_approval','pending')");
    $stmt->execute([$orderId, $customerId]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['success_message'] = 'Order cancelled successfully.';
    } else {
        $_SESSION['error_message'] = 'Order cannot be cancelled (may already be in production).';
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Error cancelling order.';
    error_log("Cancel order error: " . $e->getMessage());
}

header('Location: ' . BASE_URL . '/public/customer/my-orders'); exit;
