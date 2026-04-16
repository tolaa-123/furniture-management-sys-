<?php
/**
 * API: Order approve/reject actions for manager dashboard
 */
ob_start();
require_once '../../config/db_config.php';
ob_end_clean();
header('Content-Type: application/json');

// Session already started by db_config or config
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auth check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Method check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// CSRF check
$csrfToken = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION[CSRF_TOKEN_NAME] ?? '';
if (!$csrfToken || !$sessionToken || !hash_equals($sessionToken, $csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

$action  = $_POST['action'] ?? '';
$orderId = (int) ($_POST['order_id'] ?? 0);

if ($orderId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit();
}

try {
    if ($action === 'approve') {
        // Verify the order exists and is in a reviewable state — do NOT change status here.
        // Manager must set cost via the cost-estimation page first.
        $stmt = $pdo->prepare("
            SELECT id FROM furn_orders
            WHERE id = ? AND status IN ('pending', 'pending_review', 'pending_cost_approval')
        ");
        $stmt->execute([$orderId]);

        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Order not found or already processed']);
            exit();
        }

        // Return redirect URL so the manager can set the cost
        echo json_encode([
            'success'      => true,
            'redirect'     => true,
            'redirect_url' => BASE_URL . '/public/manager/cost-estimation?order_id=' . $orderId,
            'message'      => 'Redirecting to cost estimation'
        ]);

    } elseif ($action === 'reject') {
        $reason = trim($_POST['reason'] ?? '');
        if ($reason === '') {
            echo json_encode(['success' => false, 'message' => 'Rejection reason is required']);
            exit();
        }

        $stmt = $pdo->prepare("
            UPDATE furn_orders
            SET status = 'cancelled', manager_notes = ?, updated_at = NOW()
            WHERE id = ? AND status IN ('pending', 'pending_review', 'pending_cost_approval')
        ");
        $stmt->execute([$reason, $orderId]);

        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Order not found or already processed']);
            exit();
        }

        // Notify customer
        try {
            require_once __DIR__ . '/../../app/includes/notification_helper.php';
            $oStmt = $pdo->prepare("SELECT customer_id, order_number, furniture_type FROM furn_orders WHERE id = ?");
            $oStmt->execute([$orderId]);
            $oRow = $oStmt->fetch(PDO::FETCH_ASSOC);
            if ($oRow) {
                insertNotification($pdo, $oRow['customer_id'], 'order',
                    'Order Rejected',
                    'Your order ' . $oRow['order_number'] . ' (' . ($oRow['furniture_type'] ?? 'Custom') . ') was rejected. Reason: ' . $reason,
                    $orderId, '/customer/my-orders', 'high');
            }
        } catch (Exception $e2) { error_log("Notify error: " . $e2->getMessage()); }

        echo json_encode(['success' => true, 'message' => 'Order rejected']);

    } else {
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }

} catch (PDOException $e) {
    error_log("order_action.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
