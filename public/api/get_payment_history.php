<?php
session_start();
require_once '../../config/db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $customerId = $_SESSION['user_id'];
    
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            o.order_number
        FROM furn_payments p
        JOIN furn_orders o ON p.order_id = o.id
        WHERE p.customer_id = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$customerId]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'payments' => $payments]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
