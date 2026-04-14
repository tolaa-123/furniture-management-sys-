<?php
// API endpoint: Employee Attendance for Manager Dashboard
require_once '../../config/db_config.php';
header('Content-Type: application/json');

try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS);
    $stmt = $pdo->prepare("SELECT DATE(check_in_time) as day, COUNT(DISTINCT user_id) as present FROM furn_attendance WHERE check_in_time IS NOT NULL GROUP BY day ORDER BY day DESC LIMIT 14");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $labels = array_reverse(array_column($rows, 'day'));
    $values = array_reverse(array_map('intval', array_column($rows, 'present')));
    echo json_encode(['labels' => $labels, 'values' => $values]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
