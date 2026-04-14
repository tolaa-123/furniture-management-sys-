<?php
// API endpoint: Low Stock Materials for Manager Dashboard
require_once '../../config/db_config.php';
header('Content-Type: application/json');

try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS);
    $stmt = $pdo->prepare("SELECT name, quantity FROM furn_materials WHERE quantity < 20 ORDER BY quantity ASC LIMIT 8");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $labels = array_column($rows, 'name');
    $values = array_map('floatval', array_column($rows, 'quantity'));
    echo json_encode(['labels' => $labels, 'values' => $values]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
