<?php
session_start();
require_once __DIR__.'/../../config/db_config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit();
}

$adminId    = $_SESSION['user_id'];
$reportType = $_POST['report_type'] ?? '';
$reportToId = intval($_POST['report_to_id'] ?? 0) ?: null;

// Debug logging
error_log("Admin Report Submission - Admin ID: $adminId, Report Type: '$reportType', Report To ID: $reportToId");
error_log("POST data: " . print_r($_POST, true));

$validTypes = ['business_performance','budget_financial','staffing_hr','system_operations','policy_compliance','leave_request','general_other'];
if (!in_array($reportType, $validTypes)) {
    error_log("Invalid report type: '$reportType'");
    echo json_encode(['success'=>false,'message'=>'Invalid report type']); exit();
}

$data = [];
switch ($reportType) {
    case 'business_performance':
        $data = [
            'period'  => trim($_POST['period'] ?? ''),
            'summary' => trim($_POST['summary'] ?? ''),
        ];
        $title = 'Business Performance — ' . ($data['period'] ?: date('M Y'));
        break;
    case 'budget_financial':
        $data = [
            'period'  => trim($_POST['period'] ?? ''),
            'summary' => trim($_POST['summary'] ?? ''),
        ];
        $title = 'Budget / Financial — ' . ($data['period'] ?: date('M Y'));
        break;
    case 'staffing_hr':
        $data = [
            'summary' => trim($_POST['summary'] ?? ''),
        ];
        $title = 'Staffing / HR Report — ' . date('M Y');
        break;
    case 'system_operations':
        $data = [
            'issue_title' => trim($_POST['issue_title'] ?? ''),
            'priority'    => trim($_POST['priority'] ?? 'medium'),
            'details'     => trim($_POST['details'] ?? ''),
        ];
        $title = 'System / Operations: ' . ($data['issue_title'] ?: 'Issue');
        break;
    case 'policy_compliance':
        $data = [
            'subject' => trim($_POST['subject'] ?? ''),
            'details' => trim($_POST['details'] ?? ''),
        ];
        $title = 'Policy / Compliance: ' . ($data['subject'] ?: 'Report');
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
        ];
        $title = 'Leave Request: ' . ucfirst($data['leave_type']) . ' (' . $days . ' days)';
        break;
    default: // general_other
        $data  = ['details' => trim($_POST['details'] ?? '')];
        $title = trim($_POST['title'] ?? 'Report — ' . date('M d, Y'));
}

if (empty($title)) $title = ucwords(str_replace('_',' ',$reportType)) . ' — ' . date('M d, Y');

error_log("About to insert - Type: '$reportType', Title: '$title', Data: " . json_encode($data));

try {
    $sql = "INSERT INTO furn_manager_reports (manager_id, report_to_role, report_to_id, report_type, title, report_data, created_at)
            VALUES (:manager_id, :report_to_role, :report_to_id, :report_type, :title, :report_data, NOW())";
    
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':manager_id' => $adminId,
        ':report_to_role' => 'manager',
        ':report_to_id' => $reportToId,
        ':report_type' => $reportType,
        ':title' => $title,
        ':report_data' => json_encode($data)
    ]);
    
    $insertId = $pdo->lastInsertId();
    error_log("Insert successful - Last Insert ID: $insertId");
    
    // Verify the insert
    $verify = $pdo->prepare("SELECT report_type, report_to_role FROM furn_manager_reports WHERE id = ?");
    $verify->execute([$insertId]);
    $check = $verify->fetch(PDO::FETCH_ASSOC);
    error_log("Verification - Type: '{$check['report_type']}', Role: '{$check['report_to_role']}'");
    
    echo json_encode(['success'=>true,'message'=>'Report submitted successfully.']);
} catch (PDOException $e) {
    error_log("Insert failed: " . $e->getMessage());
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
