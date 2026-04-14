<?php
// API endpoint: Production Progress for Admin Dashboard
require_once '../../config/db_config.php';
header('Content-Type: application/json');

try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASS);
    $stmt = $pdo->prepare("SELECT product_id, status, COUNT(*) as count FROM furn_orders GROUP BY product_id, status");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $types = [];
    $statuses = [];
    $data = [];
    foreach ($rows as $row) {
        $types[$row['product_id']] = true;
        $statuses[$row['status']] = true;
        $data[$row['status']][$row['product_id']] = (int)$row['count'];
    }
    $types = array_keys($types);
    $statuses = array_keys($statuses);
    $datasets = [];
    $colors = ['#27AE60', '#F39C12', '#E74C3C', '#3498DB', '#d4a574'];
    $i = 0;
    foreach ($statuses as $status) {
        $datasets[] = [
            'label' => $status,
            'data' => array_map(function($type) use ($data, $status) {
                return isset($data[$status][$type]) ? $data[$status][$type] : 0;
            }, $types),
            'backgroundColor' => $colors[$i % count($colors)]
        ];
        $i++;
    }
    echo json_encode(['labels' => $types, 'datasets' => $datasets]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
