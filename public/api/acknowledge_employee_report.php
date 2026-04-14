<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$input    = json_decode(file_get_contents('php://input'), true);
$reportId = (int)($input['report_id'] ?? 0);
$note     = trim($input['note'] ?? '');

if (!$reportId) {
    echo json_encode(['success' => false, 'message' => 'Invalid report ID.']);
    exit();
}

try {
    $stmt = $pdo->prepare("
        UPDATE furn_employee_reports
        SET status = 'acknowledged', manager_note = ?, reviewed_by = ?, reviewed_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$note ?: null, $_SESSION['user_id'], $reportId]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Report not found.']);
        exit();
    }

    echo json_encode(['success' => true, 'message' => 'Report acknowledged.']);
} catch (PDOException $e) {
    error_log("acknowledge_employee_report error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
