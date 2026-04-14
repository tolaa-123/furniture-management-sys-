<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$employeeId = $_SESSION['user_id'];
$type       = $_POST['report_type'] ?? '';

$validTypes = ['task_progress', 'material_usage', 'incident', 'daily_summary', 'leave_request'];
if (!in_array($type, $validTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid report type.']);
    exit();
}

try {
    $reportData = [];
    $title      = '';

    switch ($type) {
        case 'task_progress':
            $taskId    = (int)($_POST['task_id'] ?? 0);
            $progress  = (int)($_POST['progress'] ?? 0);
            $workDone  = trim($_POST['work_done'] ?? '');
            $blockers  = trim($_POST['blockers'] ?? '');
            $estDate   = $_POST['est_completion'] ?? '';

            if (!$taskId || !$workDone) {
                echo json_encode(['success' => false, 'message' => 'Task and work description are required.']);
                exit();
            }
            // Verify task belongs to employee
            $chk = $pdo->prepare("SELECT id, task_name FROM furn_production_tasks WHERE id = ? AND employee_id = ?");
            $chk->execute([$taskId, $employeeId]);
            $task = $chk->fetch(PDO::FETCH_ASSOC);
            if (!$task) {
                echo json_encode(['success' => false, 'message' => 'Task not found or not assigned to you.']);
                exit();
            }
            $title = 'Task Progress: ' . ($task['task_name'] ?? 'Task #' . $taskId);
            $reportData = [
                'task_id'        => $taskId,
                'progress'       => $progress,
                'work_done'      => $workDone,
                'blockers'       => $blockers,
                'est_completion' => $estDate,
            ];
            break;

        case 'material_usage':
            $taskId      = (int)($_POST['task_id'] ?? 0);
            $materialIds = $_POST['material_id'] ?? [];
            $qtysUsed    = $_POST['qty_used'] ?? [];
            $qtysWaste   = $_POST['qty_waste'] ?? [];
            $notes       = trim($_POST['notes'] ?? '');

            if (!$taskId || empty($materialIds)) {
                echo json_encode(['success' => false, 'message' => 'Task and at least one material are required.']);
                exit();
            }
            // Verify task
            $chk = $pdo->prepare("SELECT id FROM furn_production_tasks WHERE id = ? AND employee_id = ?");
            $chk->execute([$taskId, $employeeId]);
            if (!$chk->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Task not found or not assigned to you.']);
                exit();
            }

            $materials = [];
            foreach ($materialIds as $i => $matId) {
                $matId = (int)$matId;
                $qty   = (float)($qtysUsed[$i] ?? 0);
                $waste = (float)($qtysWaste[$i] ?? 0);
                if (!$matId || $qty <= 0) continue;
                $materials[] = ['material_id' => $matId, 'qty_used' => $qty, 'waste' => $waste];

                // Insert into furn_material_usage
                $ins = $pdo->prepare("
                    INSERT INTO furn_material_usage (employee_id, task_id, material_id, quantity_used, waste_amount, notes, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $ins->execute([$employeeId, $taskId, $matId, $qty, $waste, $notes]);
            }

            if (empty($materials)) {
                echo json_encode(['success' => false, 'message' => 'Please enter valid material quantities.']);
                exit();
            }

            $title = 'Material Usage Report — Task #' . str_pad($taskId, 4, '0', STR_PAD_LEFT);
            $reportData = ['task_id' => $taskId, 'materials' => $materials, 'notes' => $notes];
            break;

        case 'incident':
            $incTitle    = trim($_POST['incident_title'] ?? '');
            $incDatetime = $_POST['incident_datetime'] ?? '';
            $incType     = $_POST['incident_type'] ?? '';
            $severity    = $_POST['severity'] ?? 'medium';
            $description = trim($_POST['description'] ?? '');
            $actionTaken = trim($_POST['action_taken'] ?? '');
            $injuries    = $_POST['injuries'] ?? 'no';

            if (!$incTitle || !$description || !$incType) {
                echo json_encode(['success' => false, 'message' => 'Title, type, and description are required.']);
                exit();
            }
            $title = 'Incident: ' . $incTitle;
            $reportData = [
                'incident_title'    => $incTitle,
                'incident_datetime' => $incDatetime,
                'incident_type'     => $incType,
                'severity'          => $severity,
                'description'       => $description,
                'action_taken'      => $actionTaken,
                'injuries'          => $injuries,
            ];
            break;

        case 'daily_summary':
            $reportDate   = $_POST['report_date'] ?? date('Y-m-d');
            $tasksWorked  = $_POST['tasks_worked'] ?? [];
            $summary      = trim($_POST['summary'] ?? '');
            $challenges   = trim($_POST['challenges'] ?? '');
            $tomorrowPlan = trim($_POST['tomorrow_plan'] ?? '');

            if (!$summary) {
                echo json_encode(['success' => false, 'message' => 'Work summary is required.']);
                exit();
            }
            $title = 'Daily Summary — ' . date('M d, Y', strtotime($reportDate));
            $reportData = [
                'report_date'   => $reportDate,
                'tasks_worked'  => array_map('intval', $tasksWorked),
                'summary'       => $summary,
                'challenges'    => $challenges,
                'tomorrow_plan' => $tomorrowPlan,
            ];
            break;

        case 'leave_request':
            $leaveType = $_POST['leave_type'] ?? '';
            $leaveFrom = $_POST['leave_from'] ?? '';
            $leaveTo   = $_POST['leave_to'] ?? '';
            $reason    = trim($_POST['reason'] ?? '');
            $coverage  = trim($_POST['coverage'] ?? '');

            if (!$leaveType || !$leaveFrom || !$leaveTo || !$reason) {
                echo json_encode(['success' => false, 'message' => 'Leave type, dates, and reason are required.']);
                exit();
            }
            if ($leaveTo < $leaveFrom) {
                echo json_encode(['success' => false, 'message' => '"To" date cannot be before "From" date.']);
                exit();
            }
            $days  = (int)((strtotime($leaveTo) - strtotime($leaveFrom)) / 86400) + 1;
            $title = 'Leave Request: ' . ucfirst($leaveType) . ' (' . $days . ' day' . ($days > 1 ? 's' : '') . ')';
            $reportData = [
                'leave_type' => $leaveType,
                'leave_from' => $leaveFrom,
                'leave_to'   => $leaveTo,
                'days'       => $days,
                'reason'     => $reason,
                'coverage'   => $coverage,
            ];
            break;
    }

    // Save to furn_employee_reports
    $stmt = $pdo->prepare("
        INSERT INTO furn_employee_reports (employee_id, report_type, title, report_data, status, created_at)
        VALUES (?, ?, ?, ?, 'submitted', NOW())
    ");
    $stmt->execute([$employeeId, $type, $title, json_encode($reportData)]);

    echo json_encode(['success' => true, 'message' => 'Report submitted successfully. Your manager will review it shortly.']);

} catch (PDOException $e) {
    error_log("submit_employee_report error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}
