<?php
session_start();
require_once '../../config/db_config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        throw new Exception('Invalid CSRF token');
    }

    $orderId    = intval($_POST['order_id'] ?? 0);
    $rating     = intval($_POST['rating'] ?? 0);
    $review     = trim($_POST['review_text'] ?? '');
    $customerId = $_SESSION['user_id'];

    if (!$orderId || $rating < 1 || $rating > 5) {
        throw new Exception('Invalid rating data');
    }

    // Ensure all required columns exist (table may have been created from an older schema)
    try {
        $pdo->exec("
            ALTER TABLE furn_ratings
                ADD COLUMN IF NOT EXISTS employee_id INT DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS review_text TEXT DEFAULT NULL
        ");
    } catch (PDOException $e) {
        // Ignore — columns already exist
    }

    // Verify order belongs to customer and is completed
    $stmt = $pdo->prepare("SELECT id FROM furn_orders WHERE id = ? AND customer_id = ? AND status = 'completed'");
    $stmt->execute([$orderId, $customerId]);
    if (!$stmt->fetch()) {
        throw new Exception('Order not found or not completed');
    }

    // Get employee_id from the completed task (nullable — rating still allowed if no task assigned)
    $stmt = $pdo->prepare("SELECT employee_id FROM furn_production_tasks WHERE order_id = ? AND status = 'completed' LIMIT 1");
    $stmt->execute([$orderId]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    $employeeId = ($task && $task['employee_id']) ? intval($task['employee_id']) : null;

    // Insert rating (ignore duplicate — one rating per order)
    $stmt = $pdo->prepare("INSERT IGNORE INTO furn_ratings (order_id, customer_id, employee_id, rating, review_text) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$orderId, $customerId, $employeeId, $rating, $review]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('You have already rated this order');
    }

    // Fetch order number and customer name for notification messages
    $stmtOrder = $pdo->prepare("
        SELECT o.order_number, CONCAT(u.first_name, ' ', u.last_name) as customer_name
        FROM furn_orders o
        JOIN furn_users u ON u.id = o.customer_id
        WHERE o.id = ?
    ");
    $stmtOrder->execute([$orderId]);
    $orderInfo = $stmtOrder->fetch(PDO::FETCH_ASSOC);
    $orderNumber  = $orderInfo['order_number'] ?? "Order #$orderId";
    $customerName = $orderInfo['customer_name'] ?? 'A customer';
    $stars        = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);

    // Notify all managers
    $pdo->prepare("
        INSERT INTO furn_notifications (user_id, type, title, message, related_id, link, created_at)
        SELECT id, 'rating',
               'New Customer Rating',
               CONCAT(?, ' rated ', ?, ' — ', ?, ' (', ?, '/5)'),
               ?, '/manager/completed-tasks', NOW()
        FROM furn_users WHERE role = 'manager'
    ")->execute([$customerName, $orderNumber, $stars, $rating, $orderId]);

    // Notify the assigned employee (if any)
    if ($employeeId) {
        $pdo->prepare("
            INSERT INTO furn_notifications (user_id, type, title, message, related_id, link, created_at)
            VALUES (?, 'rating', 'You received a rating!',
                    CONCAT(?, ' rated your work on ', ?, ' — ', ?, ' (', ?, '/5)'),
                    ?, '/employee/tasks', NOW())
        ")->execute([$employeeId, $customerName, $orderNumber, $stars, $rating, $orderId]);
    }

    echo json_encode(['success' => true, 'message' => 'Thank you for your rating!']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
