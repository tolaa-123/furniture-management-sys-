<?php
session_start();
require_once __DIR__ . '/../../config/db_config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit();
}

$data         = json_decode(file_get_contents('php://input'), true);
$usageId      = intval($data['usage_report_id'] ?? 0);
$reportId     = intval($data['report_id'] ?? 0);
$feedback     = trim($data['feedback'] ?? '');
$feedbackType = $data['feedback_type'] ?? 'note';
$managerId    = $_SESSION['user_id'];

if ((!$usageId && !$reportId) || !$feedback) {
    echo json_encode(['success'=>false,'message'=>'Missing required fields']); exit();
}
if (!in_array($feedbackType, ['note','warning','praise'])) $feedbackType = 'note';

try {
    $employeeId = null;
    if ($usageId) {
        $s = $pdo->prepare("SELECT employee_id FROM furn_material_usage WHERE id=?");
        $s->execute([$usageId]); $row = $s->fetch(PDO::FETCH_ASSOC);
        if (!$row) { echo json_encode(['success'=>false,'message'=>'Usage report not found']); exit(); }
        $employeeId = $row['employee_id'];
    } else {
        $s = $pdo->prepare("SELECT employee_id FROM furn_employee_reports WHERE id=?");
        $s->execute([$reportId]); $row = $s->fetch(PDO::FETCH_ASSOC);
        if (!$row) { echo json_encode(['success'=>false,'message'=>'Report not found']); exit(); }
        $employeeId = $row['employee_id'];
    }

    $pdo->prepare("
        INSERT INTO furn_report_feedback (usage_report_id, report_id, manager_id, employee_id, feedback, feedback_type, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ")->execute([$usageId ?: null, $reportId ?: null, $managerId, $employeeId, $feedback, $feedbackType]);

    echo json_encode(['success'=>true,'message'=>'Feedback sent successfully']);
} catch (PDOException $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
