<?php
// API endpoint: Order Approvals for Manager Dashboard
require_once '../../config/db_config.php';
header('Content-Type: application/json');

try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS);
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM furn_orders WHERE status IN ('pending','pending_cost_approval','approved','rejected') GROUP BY status");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $labels = array_column($rows, 'status');
    $values = array_map('intval', array_column($rows, 'count'));
    echo json_encode(['labels' => $labels, 'values' => $values]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
