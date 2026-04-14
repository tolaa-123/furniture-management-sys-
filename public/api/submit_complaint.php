<?php
session_start();
require_once '../../config/db_config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

try {
    $orderId      = intval($_POST['order_id'] ?? 0);
    $subject      = trim($_POST['subject'] ?? '');
    $message      = trim($_POST['message'] ?? '');
    $customerId   = $_SESSION['user_id'];
    $customerName = $_SESSION['first_name'] ?? $_SESSION['user_name'] ?? 'Customer';

    if (!$orderId || !$subject || !$message)
        throw new Exception('Please fill in all fields.');

    // Verify order belongs to customer
    $stmt = $pdo->prepare("SELECT order_number FROM furn_orders WHERE id = ? AND customer_id = ?");
    $stmt->execute([$orderId, $customerId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) throw new Exception('Order not found.');

    // Create complaints table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS furn_complaints (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        customer_id INT NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        status ENUM('open','resolved') NOT NULL DEFAULT 'open',
        manager_response TEXT DEFAULT NULL,
        resolved_by INT DEFAULT NULL,
        resolved_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(order_id), INDEX(customer_id), INDEX(status)
    )");

    // Insert complaint
    $pdo->prepare("INSERT INTO furn_complaints (order_id, customer_id, subject, message) VALUES (?,?,?,?)")
        ->execute([$orderId, $customerId, $subject, $message]);
    $complaintId = $pdo->lastInsertId();

    // Notify all managers with link to the order
    $pdo->prepare("
        INSERT INTO furn_notifications (user_id, type, title, message, related_id, link, created_at)
        SELECT id, 'complaint',
               CONCAT('⚠ Customer Complaint: ', ?),
               CONCAT(?, ' — Order ', ?),
               ?, '/manager/orders', NOW()
        FROM furn_users WHERE role = 'manager'
    ")->execute([$subject, $customerName, $order['order_number'], $orderId]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
