<?php
session_start();
require_once __DIR__ . '/../../config/db_config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'message'=>'Not authenticated']); exit(); }

$userId   = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

if (!in_array($userRole, ['admin','employee','manager'])) { 
    echo json_encode(['success'=>false,'message'=>'Unauthorized','role'=>$userRole]); exit(); 
}

$rawInput   = file_get_contents('php://input');
$data       = json_decode($rawInput, true);
$reportId   = intval($data['report_id'] ?? 0);
$feedback   = trim($data['feedback'] ?? '');
$fbType     = in_array($data['feedback_type']??'', ['praise','note','warning']) ? $data['feedback_type'] : 'note';

if (!$reportId || !$feedback) { 
    echo json_encode([
        'success'   => false,
        'message'   => 'Report ID and feedback are required',
        'debug'     => ['report_id_received' => $data['report_id'] ?? 'MISSING', 'report_id_int' => $reportId, 'has_feedback' => !empty($feedback), 'raw' => substr($rawInput,0,300)]
    ]); 
    exit(); 
}

try {
    // Ensure feedback table exists with correct schema
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `furn_report_feedback` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `report_id` INT NOT NULL,
            `from_user_id` INT NOT NULL,
            `from_user_role` ENUM('admin','manager','employee') NOT NULL,
            `to_user_id` INT NOT NULL,
            `feedback` TEXT NOT NULL,
            `feedback_type` ENUM('praise','note','warning') DEFAULT 'note',
            `is_read` TINYINT(1) DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`report_id`) REFERENCES `furn_manager_reports`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`from_user_id`) REFERENCES `furn_users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`to_user_id`) REFERENCES `furn_users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $stmt = $pdo->prepare("SELECT id, manager_id FROM furn_manager_reports WHERE id=?");
    $stmt->execute([$reportId]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$report) { 
        echo json_encode(['success'=>false,'message'=>'Report not found','debug_id'=>$reportId]); 
        exit(); 
    }

    // Use new schema: from_user_id, from_user_role, to_user_id
    $pdo->prepare("
        INSERT INTO furn_report_feedback (report_id, from_user_id, from_user_role, to_user_id, feedback, feedback_type, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ")->execute([$reportId, $userId, $userRole, $report['manager_id'], $feedback, $fbType]);

    $pdo->prepare("UPDATE furn_manager_reports SET status='reviewed', reviewed_by=?, reviewed_at=NOW() WHERE id=?")
        ->execute([$userId, $reportId]);

    echo json_encode(['success'=>true,'message'=>'Feedback sent successfully']);
} catch (PDOException $e) {
    echo json_encode(['success'=>false,'message'=>'Database error: '.$e->getMessage()]);
}
