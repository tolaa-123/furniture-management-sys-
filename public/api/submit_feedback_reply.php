<?php
session_start();
require_once __DIR__ . '/../../config/db_config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data       = json_decode(file_get_contents('php://input'), true);
$feedbackId = intval($data['feedback_id'] ?? 0);
$reply      = trim($data['reply'] ?? '');
$employeeId = $_SESSION['user_id'];

if (!$feedbackId || !$reply) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

try {
    // Verify this feedback belongs to this employee
    $stmt = $pdo->prepare("SELECT id FROM furn_report_feedback WHERE id = ? AND employee_id = ?");
    $stmt->execute([$feedbackId, $employeeId]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Feedback not found']);
        exit();
    }

    // Check if reply column exists, add if not
    $cols = array_column($pdo->query("DESCRIBE furn_report_feedback")->fetchAll(PDO::FETCH_ASSOC), 'Field');
    if (!in_array('employee_reply', $cols)) {
        $pdo->exec("ALTER TABLE furn_report_feedback ADD COLUMN employee_reply TEXT NULL AFTER feedback, ADD COLUMN replied_at DATETIME NULL AFTER employee_reply");
    }

    $pdo->prepare("UPDATE furn_report_feedback SET employee_reply = ?, replied_at = NOW(), is_read = 1 WHERE id = ?")
        ->execute([$reply, $feedbackId]);

    echo json_encode(['success' => true, 'message' => 'Reply sent']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
