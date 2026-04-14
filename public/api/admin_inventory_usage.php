<?php
// API endpoint: Inventory Usage for Admin Dashboard
require_once '../../config/db_config.php';
header('Content-Type: application/json');

try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS);
    $stmt = $pdo->prepare("SELECT name as material_name, quantity as usage FROM furn_materials ORDER BY usage DESC LIMIT 8");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $labels = array_column($rows, 'material_name');
    $values = array_map('floatval', array_column($rows, 'usage'));
    echo json_encode(['labels' => $labels, 'values' => $values]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
