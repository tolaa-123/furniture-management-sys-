<?php
// API endpoint: Tasks Completed Timeline for Employee Dashboard
require_once '../../config/db_config.php';
header('Content-Type: application/json');
session_start();
$employeeId = $_SESSION['user_id'] ?? 0;

try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS);
    $stmt = $pdo->prepare("SELECT DATE(completed_at) as day, COUNT(*) as completed FROM furn_production_tasks WHERE employee_id = ? AND status = 'completed' AND completed_at IS NOT NULL GROUP BY day ORDER BY day DESC LIMIT 14");
    $stmt->execute([$employeeId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $labels = array_reverse(array_column($rows, 'day'));
    $values = array_reverse(array_map('intval', array_column($rows, 'completed')));
    echo json_encode(['labels' => $labels, 'values' => $values]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
