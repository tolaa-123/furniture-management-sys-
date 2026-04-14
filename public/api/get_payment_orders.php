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

    // Ensure required columns exist
    try {
        $pdo->exec("ALTER TABLE furn_orders 
            ADD COLUMN IF NOT EXISTS estimated_cost DECIMAL(12,2) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS deposit_amount DECIMAL(12,2) DEFAULT NULL");
    } catch (PDOException $e) {
        error_log('ALTER TABLE warning: ' . $e->getMessage());
    }

    // Get orders with payment information
    // Uses LEFT JOIN so it works even if furn_payments has no rows yet
    $stmt = $pdo->prepare("
        SELECT 
            o.id,
            o.order_number,
            COALESCE(o.furniture_name, o.furniture_type, 'Custom Order') AS furniture_name,
            COALESCE(o.estimated_cost, o.total_amount, 0) AS estimated_cost,
            COALESCE(o.deposit_amount, o.estimated_cost * 0.4, o.total_amount * 0.4, 0) AS deposit_amount,
            COALESCE(SUM(p.amount), 0) AS amount_paid,
            COALESCE(o.estimated_cost, o.total_amount, 0) AS total_amount,
            o.status
        FROM furn_orders o
        LEFT JOIN furn_payments p ON o.id = p.order_id AND p.status = 'approved'
        WHERE o.customer_id = ?
        AND o.status IN (
            'cost_estimated', 'approved', 'waiting_for_deposit',
            'deposit_paid', 'payment_verified', 'in_production',
            'production_started', 'production_completed',
            'ready_for_delivery', 'final_payment_paid'
        )
        AND COALESCE(o.estimated_cost, o.total_amount, 0) > 0
        GROUP BY o.id, o.order_number, o.furniture_name, o.furniture_type, o.estimated_cost, o.deposit_amount, o.total_amount, o.status
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$customerId]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'orders' => $orders]);

} catch (Exception $e) {
    error_log('get_payment_orders error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
