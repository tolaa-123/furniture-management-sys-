<?php
session_start();
require_once __DIR__.'/../../config/db_config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit();
}

$managerId   = $_SESSION['user_id'];
$reportType  = $_POST['report_type'] ?? '';
$reportToRole= $_POST['report_to_role'] ?? 'admin';
$reportToId  = intval($_POST['report_to_id'] ?? 0) ?: null;

// Debug logging
error_log("Submit report - Type: $reportType, Role: $reportToRole, ToId: " . ($reportToId ?? 'null') . ", Manager: $managerId");

$validTypes = ['production_update','inventory_summary','team_performance','incident','daily_summary','leave_request','other'];
if (!in_array($reportType, $validTypes)) {
    echo json_encode(['success'=>false,'message'=>'Invalid report type']); exit();
}
if (!in_array($reportToRole, ['admin','employee'])) $reportToRole = 'admin';

// Build report_data from POST
$data = [];
switch ($reportType) {
    case 'production_update':
        $data = [
            'order_ref'   => trim($_POST['order_ref'] ?? ''),
            'progress'    => intval($_POST['progress'] ?? 0),
            'update'      => trim($_POST['update'] ?? ''),
            'blockers'    => trim($_POST['blockers'] ?? ''),
            'est_completion' => trim($_POST['est_completion'] ?? ''),
        ];
        $title = 'Production Update — ' . ($data['order_ref'] ?: date('M d, Y'));
        break;
    case 'inventory_summary':
        $data = [
            'summary'     => trim($_POST['summary'] ?? ''),
            'low_stock'   => trim($_POST['low_stock'] ?? ''),
            'action_needed' => trim($_POST['action_needed'] ?? ''),
        ];
        $title = 'Inventory Summary — ' . date('M d, Y');
        break;
    case 'team_performance':
        $data = [
            'period'      => trim($_POST['period'] ?? ''),
            'highlights'  => trim($_POST['highlights'] ?? ''),
            'concerns'    => trim($_POST['concerns'] ?? ''),
            'recommendations' => trim($_POST['recommendations'] ?? ''),
        ];
        $title = 'Team Performance — ' . ($data['period'] ?: date('M Y'));
        break;
    case 'incident':
        $data = [
            'incident_title'    => trim($_POST['incident_title'] ?? ''),
            'incident_datetime' => trim($_POST['incident_datetime'] ?? ''),
            'incident_type'     => trim($_POST['incident_type'] ?? ''),
            'severity'          => trim($_POST['severity'] ?? 'medium'),
            'description'       => trim($_POST['description'] ?? ''),
            'action_taken'      => trim($_POST['action_taken'] ?? ''),
            'injuries'          => trim($_POST['injuries'] ?? 'no'),
        ];
        $title = 'Incident: ' . ($data['incident_title'] ?: 'Report');
        break;
    case 'daily_summary':
        $data = [
            'report_date'   => trim($_POST['report_date'] ?? date('Y-m-d')),
            'summary'       => trim($_POST['summary'] ?? ''),
            'challenges'    => trim($_POST['challenges'] ?? ''),
            'tomorrow_plan' => trim($_POST['tomorrow_plan'] ?? ''),
        ];
        $title = 'Daily Summary — ' . $data['report_date'];
        break;
    case 'leave_request':
        $from = trim($_POST['leave_from'] ?? '');
        $to   = trim($_POST['leave_to'] ?? '');
        $days = ($from && $to) ? (int)((strtotime($to) - strtotime($from)) / 86400) + 1 : 0;
        $data = [
            'leave_type' => trim($_POST['leave_type'] ?? ''),
            'leave_from' => $from,
            'leave_to'   => $to,
            'days'       => $days,
            'reason'     => trim($_POST['reason'] ?? ''),
            'coverage'   => trim($_POST['coverage'] ?? ''),
        ];
        $title = 'Leave Request: ' . ucfirst($data['leave_type']) . ' (' . $days . ' days)';
        break;
    default:
        $data  = ['details' => trim($_POST['details'] ?? '')];
        $title = trim($_POST['title'] ?? 'Report — ' . date('M d, Y'));
}

if (empty($title)) $title = ucwords(str_replace('_',' ',$reportType)) . ' — ' . date('M d, Y');

// Ensure table exists with correct schema
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `furn_manager_reports` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `manager_id` INT NOT NULL,
            `report_type` VARCHAR(50) NOT NULL,
            `report_to_role` ENUM('admin','manager','employee') DEFAULT 'admin',
            `report_to_id` INT DEFAULT NULL,
            `title` VARCHAR(255) NOT NULL,
            `report_data` JSON NOT NULL,
            `status` ENUM('submitted','reviewed','acknowledged') DEFAULT 'submitted',
            `admin_feedback` TEXT DEFAULT NULL,
            `reviewed_by` INT DEFAULT NULL,
            `reviewed_at` DATETIME DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`manager_id`) REFERENCES `furn_users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`reviewed_by`) REFERENCES `furn_users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Ensure correct schema (idempotent)
    $pdo->exec("ALTER TABLE furn_manager_reports MODIFY report_type VARCHAR(50) NOT NULL");
    $pdo->exec("ALTER TABLE furn_manager_reports MODIFY report_to_role ENUM('admin','manager','employee') DEFAULT 'admin'");
} catch (PDOException $e) {
    error_log("Table schema error: " . $e->getMessage());
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO furn_manager_reports (manager_id, report_to_role, report_to_id, report_type, title, report_data, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $result = $stmt->execute([$managerId, $reportToRole, $reportToId, $reportType, $title, json_encode($data)]);
    $insertId = $pdo->lastInsertId();
    error_log("Report inserted successfully - ID: $insertId, Result: " . ($result ? 'true' : 'false'));
    echo json_encode(['success'=>true,'message'=>'Report submitted successfully.', 'report_id' => $insertId]);
} catch (PDOException $e) {
    error_log("Report insert error: " . $e->getMessage());
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
