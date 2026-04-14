<?php
// API endpoint: Attendance Timeline for Employee Dashboard
require_once '../../config/db_config.php';
header('Content-Type: application/json');
session_start();
$employeeId = $_SESSION['user_id'] ?? 0;

try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS);
    $stmt = $pdo->prepare("SELECT date, TIMESTAMPDIFF(HOUR, check_in_time, COALESCE(clock_out, NOW())) as hours FROM furn_attendance WHERE employee_id = ? AND check_in_time IS NOT NULL GROUP BY date ORDER BY date DESC LIMIT 14");
    $stmt->execute([$employeeId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $labels = array_reverse(array_column($rows, 'date'));
    $values = array_reverse(array_map('floatval', array_column($rows, 'hours')));
    echo json_encode(['labels' => $labels, 'values' => $values]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
