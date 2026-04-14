<?php
// API endpoint: Monthly Revenue for Admin Dashboard
require_once '../../config/db_config.php';
header('Content-Type: application/json');

try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS);
    $stmt = $pdo->prepare("SELECT DATE_FORMAT(created_at, '%b %Y') as month, SUM(total_amount) as revenue FROM furn_orders WHERE status IN ('completed','deposit_paid') GROUP BY month ORDER BY created_at DESC LIMIT 12");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $labels = array_reverse(array_column($rows, 'month'));
    $values = array_reverse(array_map('floatval', array_column($rows, 'revenue')));
    echo json_encode(['labels' => $labels, 'values' => $values]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
