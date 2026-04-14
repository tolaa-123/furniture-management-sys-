<?php
session_start();
require_once __DIR__ . '/../../config/db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

// Only admin and employee can acknowledge manager reports
if (!in_array($userRole, ['admin', 'employee'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$reportId = $input['report_id'] ?? null;
$note = $input['note'] ?? '';

if (!$reportId) {
    echo json_encode(['success' => false, 'message' => 'Report ID is required']);
    exit();
}

try {
    // Update the report status to acknowledged
    $stmt = $pdo->prepare("
        UPDATE furn_manager_reports 
        SET status = 'acknowledged',
            reviewer_note = ?,
            reviewed_by = ?,
            reviewed_at = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    
    $stmt->execute([$note, $userId, $reportId]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Report acknowledged successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Report not found or already acknowledged']);
    }
} catch (PDOException $e) {
    error_log("Acknowledge manager report error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
