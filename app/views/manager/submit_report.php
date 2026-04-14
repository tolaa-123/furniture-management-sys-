<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    header('Location: ' . BASE_URL . '/public/login'); exit();
}
require_once __DIR__ . '/../../../config/db_config.php';

$managerName = $_SESSION['user_name'] ?? 'Manager';
$managerId   = $_SESSION['user_id'];
$type        = $_GET['type'] ?? '';

$validTypes = ['production_update','inventory_summary','team_performance','incident','daily_summary','leave_request','other'];
if (!in_array($type, $validTypes)) {
    header('Location: ' . BASE_URL . '/public/manager/reports'); exit();
}

$typeLabels = [
    'production_update'  => 'Production Update',
    'inventory_summary'  => 'Inventory Summary',
    'team_performance'   => 'Team Performance Report',
    'incident'           => 'Incident / Problem Report',
    'daily_summary'      => 'Daily Work Summary',
    'leave_request'      => 'Leave / Absence Request',
    'other'              => 'General Report',
];
$typeIcons = [
    'production_update'  => 'fa-industry',
    'inventory_summary'  => 'fa-boxes',
    'team_performance'   => 'fa-users',
    'incident'           => 'fa-exclamation-triangle',
    'daily_summary'      => 'fa-clipboard-list',
    'leave_request'      => 'fa-calendar-times',
    'other'              => 'fa-file-alt',
];
$typeColors = [
    'production_update'  => '#3498DB',
    'inventory_summary'  => '#9B59B6',
    'team_performance'   => '#27AE60',
    'incident'           => '#E74C3C',
    'daily_summary'      => '#F39C12',
    'leave_request'      => '#E67E22',
    'other'              => '#7F8C8D',
];

$label = $typeLabels[$type];
$icon  = $typeIcons[$type];
$color = $typeColors[$type];

$employees = []; $admins = [];
try {
    $stmt = $pdo->query("SELECT id, CONCAT(first_name,' ',last_name) as name FROM furn_users WHERE role='employee' AND status='active' ORDER BY name");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt2 = $pdo->query("SELECT id, CONCAT(first_name,' ',last_name) as name FROM furn_users WHERE role='admin' ORDER BY name");
    $admins = $stmt2->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log($e->getMessage()); }
$pageTitle = $label;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $label; ?> - FurnitureCraft</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/css/admin-responsive.css">
    <style>
        .report-form-card { background:#fff;border-radius:12px;box-shadow:0 2px 16px rgba(0,0,0,0.08);padding:32px;max-width:720px;margin:0 auto; }
        .form-group { margin-bottom:20px; }
        .form-group label { display:block;font-weight:600;font-size:13px;color:#2C3E50;margin-bottom:7px;text-transform:uppercase;letter-spacing:.4px; }
        .form-group label .req { color:#E74C3C;margin-left:3px; }
        .form-control { width:100%;padding:11px 14px;border:2px solid #E0E0E0;border-radius:8px;font-family:inherit;font-size:14px;color:#2C3E50;transition:border-color .2s;box-sizing:border-box;background:#FAFAFA; }
        .form-control:focus { outline:none;border-color:<?php echo $color; ?>;background:#fff; }
        textarea.form-control { resize:vertical;min-height:100px; }
        .severity-group { display:flex;gap:10px;flex-wrap:wrap; }
        .severity-btn { flex:1;min-width:100px;padding:10px 8px;border:2px solid #E0E0E0;border-radius:8px;background:#fff;cursor:pointer;font-family:inherit;font-size:13px;font-weight:600;text-align:center;transition:all .2s;color:#7F8C8D; }
        .severity-btn.active { color:#fff;border-color:transparent; }
        .severity-btn.low.active { background:#27AE60; }
        .severity-btn.medium.active { background:#F39C12; }
        .severity-btn.high.active { background:#E74C3C; }
        .submit-btn { width:100%;padding:14px;background:<?php echo $color; ?>;color:#fff;border:none;border-radius:8px;font-size:16px;font-weight:600;font-family:inherit;cursor:pointer;transition:opacity .2s;margin-top:8px; }
        .submit-btn:hover { opacity:.88; }
        .submit-btn:disabled { opacity:.6;cursor:not-allowed; }
        .back-link { display:inline-flex;align-items:center;gap:7px;color:#7F8C8D;text-decoration:none;font-size:14px;margin-bottom:20px;transition:color .2s; }
        .back-link:hover { color:<?php echo $color; ?>; }
        .form-header { display:flex;align-items:center;gap:14px;margin-bottom:28px;padding-bottom:20px;border-bottom:2px solid #F0F0F0; }
        .form-header-icon { width:52px;height:52px;border-radius:12px;background:<?php echo $color; ?>22;display:flex;align-items:center;justify-content:center;font-size:22px;color:<?php echo $color; ?>;flex-shrink:0; }
        .alert-success { background:#d4edda;color:#155724;border:1px solid #c3e6cb;border-radius:8px;padding:14px 18px;margin-bottom:20px;font-size:14px; }
        .alert-error { background:#f8d7da;color:#721c24;border:1px solid #f5c6cb;border-radius:8px;padding:14px 18px;margin-bottom:20px;font-size:14px; }
        .range-display { font-size:13px;color:<?php echo $color; ?>;font-weight:600;margin-left:8px; }
        .hint { font-size:12px;color:#95A5A6;margin-top:5px; }
        .recipient-tabs { display:flex;gap:0;border:2px solid #E0E0E0;border-radius:8px;overflow:hidden;margin-bottom:16px; }
        .recipient-tab { flex:1;padding:10px;text-align:center;cursor:pointer;font-size:13px;font-weight:600;color:#7F8C8D;background:#FAFAFA;border:none;font-family:inherit;transition:all .2s; }
        .recipient-tab.active { background:<?php echo $color; ?>;color:#fff; }
        .recipient-fields[data-role="employee"] { border-left:3px solid #3498DB;padding-left:16px; }
        .recipient-fields[data-role="admin"] { border-left:3px solid #E74C3C;padding-left:16px; }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>
    <div class="sidebar-overlay"></div>
    <?php include_once __DIR__ . '/../../includes/manager_sidebar.php'; ?>
    <!-- Top Header -->
    <?php 
    $pageTitle = 'Submit Report';
    include_once __DIR__ . '/../../includes/manager_header.php'; 
    ?>
        <div class="header-left"><div class="system-status"><i class="fas fa-circle"></i> <?php echo $label; ?></div></div>
        <div class="header-right">
            <div class="admin-profile">
                <div class="admin-avatar"><?php echo strtoupper(substr($managerName,0,1)); ?></div>
                <div>
                    <div style="font-weight:600;font-size:14px;"><?php echo htmlspecialchars($managerName); ?></div>
                    <div class="admin-role-badge">MANAGER</div>
                </div>
            </div>
        </div>
    </div>
    <div class="main-content">
        <a href="<?php echo BASE_URL; ?>/public/manager/reports" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Reports
        </a>
        <div class="report-form-card">
            <div class="form-header">
                <div class="form-header-icon"><i class="fas <?php echo $icon; ?>"></i></div>
                <div>
                    <div style="font-size:20px;font-weight:700;color:#2C3E50;"><?php echo $label; ?></div>
                    <div style="font-size:13px;color:#95A5A6;margin-top:3px;">Fill in the details and submit</div>
                </div>
            </div>
            <div id="alertBox"></div>
            <form id="reportForm" onsubmit="submitReport(event)">
                <input type="hidden" name="report_type" value="<?php echo $type; ?>">
                <input type="hidden" name="report_to_role" id="reportToRole" value="admin">

                <!-- REPORT TO -->
                <div class="form-group">
                    <label>Report To <span class="req">*</span></label>
                    <div class="recipient-tabs">
                        <button type="button" class="recipient-tab active" onclick="setRecipient('admin',this)">
                            <i class="fas fa-user-shield"></i> Admin
                        </button>
                        <button type="button" class="recipient-tab" onclick="setRecipient('employee',this)">
                            <i class="fas fa-hard-hat"></i> Specific Employee
                        </button>
                    </div>
                    <div id="adminSelect">
                        <select name="report_to_id_admin" class="form-control">
                            <option value="">— All Admins —</option>
                            <?php foreach ($admins as $a): ?>
                            <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="employeeSelect" style="display:none;">
                        <select name="report_to_id_employee" class="form-control">
                            <option value="">— Select Employee —</option>
                            <?php foreach ($employees as $e): ?>
                            <option value="<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

<?php if ($type === 'production_update'): ?>
                <!-- === ADMIN FORM: Production Update === -->
                <div class="recipient-fields" data-role="admin">
                    <div class="form-group">
                        <label>Order / Project Reference</label>
                        <input type="text" name="order_ref" class="form-control" placeholder="e.g. ORD202603091513 or project name">
                    </div>
                    <div class="form-group">
                        <label>Overall Progress <span class="req">*</span>
                            <span class="range-display" id="progressVal">50%</span>
                        </label>
                        <input type="range" name="progress" min="0" max="100" value="50" class="form-control"
                            style="padding:6px 0;background:transparent;border:none;"
                            oninput="document.getElementById('progressVal').textContent=this.value+'%'">
                    </div>
                    <div class="form-group">
                        <label>Production Update <span class="req">*</span></label>
                        <textarea name="update" class="form-control" rows="5"
                            placeholder="Describe current production status, what has been completed, what is in progress..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Blockers / Issues</label>
                        <textarea name="blockers" class="form-control" rows="3"
                            placeholder="Any problems, delays, or resource shortages?"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Estimated Completion Date</label>
                        <input type="date" name="est_completion" class="form-control" min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Resources / Budget Needed</label>
                        <textarea name="resources_needed" class="form-control" rows="3"
                            placeholder="List any resources, materials, or budget approvals required from admin..."></textarea>
                    </div>
                </div>
                <!-- === EMPLOYEE FORM: Production Update === -->
                <div class="recipient-fields" data-role="employee" style="display:none;">
                    <div class="form-group">
                        <label>Task / Order Reference <span class="req">*</span></label>
                        <input type="text" name="emp_task_ref" class="form-control" placeholder="e.g. Task #12 or ORD202603091513">
                    </div>
                    <div class="form-group">
                        <label>Instructions for Employee <span class="req">*</span></label>
                        <textarea name="emp_instructions" class="form-control" rows="5"
                            placeholder="Describe what the employee needs to do on this production task..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Priority</label>
                        <select name="emp_priority" class="form-control">
                            <option value="normal">Normal</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Deadline</label>
                        <input type="date" name="emp_deadline" class="form-control" min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Additional Notes</label>
                        <textarea name="emp_notes" class="form-control" rows="3"
                            placeholder="Any extra information or safety notes for the employee..."></textarea>
                    </div>
                </div>

<?php elseif ($type === 'inventory_summary'): ?>
                <!-- === ADMIN FORM: Inventory Summary === -->
                <div class="recipient-fields" data-role="admin">
                    <div class="form-group">
                        <label>Inventory Summary <span class="req">*</span></label>
                        <textarea name="summary" class="form-control" rows="5"
                            placeholder="Summarize current inventory levels, recent stock movements..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Low Stock Items</label>
                        <textarea name="low_stock" class="form-control" rows="3"
                            placeholder="List materials that are running low and need restocking..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Action Needed</label>
                        <textarea name="action_needed" class="form-control" rows="3"
                            placeholder="What actions are required? (e.g. purchase orders, supplier contact)"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Estimated Restock Cost</label>
                        <input type="text" name="restock_cost" class="form-control" placeholder="e.g. ₱15,000 for wood and fabric">
                    </div>
                </div>
                <!-- === EMPLOYEE FORM: Inventory Summary === -->
                <div class="recipient-fields" data-role="employee" style="display:none;">
                    <div class="form-group">
                        <label>Task: Inventory Check <span class="req">*</span></label>
                        <textarea name="emp_check_instructions" class="form-control" rows="4"
                            placeholder="Describe what the employee needs to count, check, or verify in the inventory..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Materials to Check</label>
                        <textarea name="emp_materials_list" class="form-control" rows="3"
                            placeholder="List specific materials or storage areas to inspect..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Due By</label>
                        <input type="date" name="emp_due_date" class="form-control" min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Special Instructions</label>
                        <textarea name="emp_special_notes" class="form-control" rows="3"
                            placeholder="Any specific counting method, format, or reporting requirements..."></textarea>
                    </div>
                </div>

<?php elseif ($type === 'team_performance'): ?>
                <!-- === ADMIN FORM: Team Performance === -->
                <div class="recipient-fields" data-role="admin">
                    <div class="form-group">
                        <label>Period Covered <span class="req">*</span></label>
                        <input type="text" name="period" class="form-control" placeholder="e.g. March 2026, Week 12, Q1 2026">
                    </div>
                    <div class="form-group">
                        <label>Performance Highlights <span class="req">*</span></label>
                        <textarea name="highlights" class="form-control" rows="4"
                            placeholder="What went well? Notable achievements, completed tasks, good performance..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Concerns / Issues</label>
                        <textarea name="concerns" class="form-control" rows="3"
                            placeholder="Any performance issues, attendance problems, or concerns to flag?"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Recommendations</label>
                        <textarea name="recommendations" class="form-control" rows="3"
                            placeholder="Suggestions for improvement, training needs, resource requests..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Overall Team Rating</label>
                        <select name="team_rating" class="form-control">
                            <option value="">— Select rating —</option>
                            <option value="excellent">Excellent</option>
                            <option value="good">Good</option>
                            <option value="satisfactory">Satisfactory</option>
                            <option value="needs_improvement">Needs Improvement</option>
                        </select>
                    </div>
                </div>
                <!-- === EMPLOYEE FORM: Team Performance === -->
                <div class="recipient-fields" data-role="employee" style="display:none;">
                    <div class="form-group">
                        <label>Employee Performance Feedback <span class="req">*</span></label>
                        <textarea name="emp_performance_feedback" class="form-control" rows="5"
                            placeholder="Provide direct performance feedback to this employee..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Period Covered <span class="req">*</span></label>
                        <input type="text" name="emp_period" class="form-control" placeholder="e.g. March 2026, Week 12">
                    </div>
                    <div class="form-group">
                        <label>Individual Rating</label>
                        <select name="emp_rating" class="form-control">
                            <option value="">— Select rating —</option>
                            <option value="excellent">Excellent</option>
                            <option value="good">Good</option>
                            <option value="satisfactory">Satisfactory</option>
                            <option value="needs_improvement">Needs Improvement</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Areas for Improvement</label>
                        <textarea name="emp_improvement" class="form-control" rows="3"
                            placeholder="What should this employee focus on improving?"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Action Required from Employee</label>
                        <textarea name="emp_action_required" class="form-control" rows="3"
                            placeholder="Any specific actions the employee must take based on this review?"></textarea>
                    </div>
                </div>

<?php elseif ($type === 'incident'): ?>
                <!-- === ADMIN FORM: Incident === -->
                <div class="recipient-fields" data-role="admin">
                    <div class="form-group">
                        <label>Incident Title <span class="req">*</span></label>
                        <input type="text" name="incident_title" class="form-control"
                            placeholder="e.g. Machine malfunction, Material damage, Workplace injury...">
                    </div>
                    <div class="form-group">
                        <label>Incident Date & Time <span class="req">*</span></label>
                        <input type="datetime-local" name="incident_datetime" class="form-control"
                            value="<?php echo date('Y-m-d\TH:i'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Incident Type <span class="req">*</span></label>
                        <select name="incident_type" class="form-control">
                            <option value="">— Select type —</option>
                            <option value="equipment_failure">Equipment / Machine Failure</option>
                            <option value="material_damage">Material Damage</option>
                            <option value="workplace_injury">Workplace Injury</option>
                            <option value="quality_issue">Quality Issue</option>
                            <option value="safety_hazard">Safety Hazard</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Severity <span class="req">*</span></label>
                        <input type="hidden" name="severity" id="severityVal" value="medium">
                        <div class="severity-group">
                            <button type="button" class="severity-btn low" onclick="setSeverity('low',this)"><i class="fas fa-circle" style="color:#27AE60;"></i><br>Low</button>
                            <button type="button" class="severity-btn medium active" onclick="setSeverity('medium',this)"><i class="fas fa-circle" style="color:#F39C12;"></i><br>Medium</button>
                            <button type="button" class="severity-btn high" onclick="setSeverity('high',this)"><i class="fas fa-circle" style="color:#E74C3C;"></i><br>High</button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>What happened? <span class="req">*</span></label>
                        <textarea name="description" class="form-control" rows="5"
                            placeholder="Describe the incident in detail..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Immediate action taken</label>
                        <textarea name="action_taken" class="form-control" rows="3"
                            placeholder="What was done immediately after the incident?"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Were any people injured?</label>
                        <select name="injuries" class="form-control">
                            <option value="no">No injuries</option>
                            <option value="minor">Minor injury (first aid only)</option>
                            <option value="serious">Serious injury (medical attention needed)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Estimated Damage / Loss</label>
                        <input type="text" name="damage_estimate" class="form-control" placeholder="e.g. ₱5,000 equipment damage">
                    </div>
                </div>
                <!-- === EMPLOYEE FORM: Incident === -->
                <div class="recipient-fields" data-role="employee" style="display:none;">
                    <div class="form-group">
                        <label>Incident Title <span class="req">*</span></label>
                        <input type="text" name="emp_incident_title" class="form-control"
                            placeholder="Brief title of the incident involving this employee...">
                    </div>
                    <div class="form-group">
                        <label>Incident Date & Time <span class="req">*</span></label>
                        <input type="datetime-local" name="emp_incident_datetime" class="form-control"
                            value="<?php echo date('Y-m-d\TH:i'); ?>">
                    </div>
                    <div class="form-group">
                        <label>What happened? <span class="req">*</span></label>
                        <textarea name="emp_incident_description" class="form-control" rows="5"
                            placeholder="Describe the incident involving this employee..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Employee's Role in Incident</label>
                        <select name="emp_incident_role" class="form-control">
                            <option value="involved">Directly Involved</option>
                            <option value="witness">Witness</option>
                            <option value="responsible">Responsible Party</option>
                            <option value="victim">Victim / Injured</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Action Required from Employee <span class="req">*</span></label>
                        <textarea name="emp_incident_action" class="form-control" rows="3"
                            placeholder="What must the employee do? (e.g. submit written statement, attend safety briefing)"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Follow-up Deadline</label>
                        <input type="date" name="emp_incident_deadline" class="form-control" min="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>

<?php elseif ($type === 'daily_summary'): ?>
                <!-- === ADMIN FORM: Daily Summary === -->
                <div class="recipient-fields" data-role="admin">
                    <div class="form-group">
                        <label>Report Date <span class="req">*</span></label>
                        <input type="date" name="report_date" class="form-control"
                            value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Summary of work done today <span class="req">*</span></label>
                        <textarea name="summary" class="form-control" rows="5"
                            placeholder="Describe what was accomplished today across the team..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Challenges faced</label>
                        <textarea name="challenges" class="form-control" rows="3"
                            placeholder="Any difficulties or problems encountered?"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Plan for tomorrow</label>
                        <textarea name="tomorrow_plan" class="form-control" rows="3"
                            placeholder="What is planned for tomorrow?"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Items Requiring Admin Attention</label>
                        <textarea name="admin_attention" class="form-control" rows="3"
                            placeholder="Any decisions, approvals, or escalations needed from admin?"></textarea>
                    </div>
                </div>
                <!-- === EMPLOYEE FORM: Daily Summary === -->
                <div class="recipient-fields" data-role="employee" style="display:none;">
                    <div class="form-group">
                        <label>Date <span class="req">*</span></label>
                        <input type="date" name="emp_report_date" class="form-control"
                            value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group">
                        <label>Tasks Assigned to Employee <span class="req">*</span></label>
                        <textarea name="emp_tasks_assigned" class="form-control" rows="4"
                            placeholder="List the tasks this employee is assigned for today or tomorrow..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Expected Output / Deliverables</label>
                        <textarea name="emp_expected_output" class="form-control" rows="3"
                            placeholder="What should the employee produce or complete?"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Special Instructions</label>
                        <textarea name="emp_daily_instructions" class="form-control" rows="3"
                            placeholder="Any specific instructions, safety reminders, or priorities for today?"></textarea>
                    </div>
                </div>

<?php elseif ($type === 'leave_request'): ?>
                <!-- === ADMIN FORM: Leave Request === -->
                <div class="recipient-fields" data-role="admin">
                    <div class="form-group">
                        <label>Leave Type <span class="req">*</span></label>
                        <select name="leave_type" class="form-control">
                            <option value="">— Select type —</option>
                            <option value="sick">Sick Leave</option>
                            <option value="personal">Personal Leave</option>
                            <option value="family">Family Emergency</option>
                            <option value="vacation">Vacation / Annual Leave</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <div>
                            <label>From Date <span class="req">*</span></label>
                            <input type="date" name="leave_from" class="form-control" min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div>
                            <label>To Date <span class="req">*</span></label>
                            <input type="date" name="leave_to" class="form-control" min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Duration <span class="range-display" id="leaveDaysDisplay"></span></label>
                        <div class="hint">Auto-calculated from the dates above.</div>
                    </div>
                    <div class="form-group">
                        <label>Reason <span class="req">*</span></label>
                        <textarea name="reason" class="form-control" rows="4"
                            placeholder="Explain the reason for your leave request..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Who will cover your responsibilities?</label>
                        <input type="text" name="coverage" class="form-control"
                            placeholder="Name of person who will handle your duties (if known)">
                    </div>
                </div>
                <!-- === EMPLOYEE FORM: Leave Request === -->
                <div class="recipient-fields" data-role="employee" style="display:none;">
                    <div class="form-group">
                        <label>Leave Decision / Notice <span class="req">*</span></label>
                        <select name="emp_leave_action" class="form-control">
                            <option value="">— Select action —</option>
                            <option value="approved">Approve Leave</option>
                            <option value="denied">Deny Leave</option>
                            <option value="notice">Inform of Scheduled Leave</option>
                        </select>
                    </div>
                    <div class="form-group" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <div>
                            <label>Leave From <span class="req">*</span></label>
                            <input type="date" name="emp_leave_from" class="form-control" min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div>
                            <label>Leave To <span class="req">*</span></label>
                            <input type="date" name="emp_leave_to" class="form-control" min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Message to Employee <span class="req">*</span></label>
                        <textarea name="emp_leave_message" class="form-control" rows="4"
                            placeholder="Explain the decision or provide instructions for the employee during their leave..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Coverage Arrangement</label>
                        <textarea name="emp_leave_coverage" class="form-control" rows="3"
                            placeholder="Who will cover this employee's tasks? Any handover instructions?"></textarea>
                    </div>
                </div>

<?php else: // other ?>
                <!-- === ADMIN FORM: General Report === -->
                <div class="recipient-fields" data-role="admin">
                    <div class="form-group">
                        <label>Report Title <span class="req">*</span></label>
                        <input type="text" name="title" class="form-control" placeholder="Enter a descriptive title...">
                    </div>
                    <div class="form-group">
                        <label>Details <span class="req">*</span></label>
                        <textarea name="details" class="form-control" rows="8"
                            placeholder="Write your report details here..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Action / Decision Needed from Admin</label>
                        <textarea name="admin_decision" class="form-control" rows="3"
                            placeholder="What do you need admin to decide or act on?"></textarea>
                    </div>
                </div>
                <!-- === EMPLOYEE FORM: General Report === -->
                <div class="recipient-fields" data-role="employee" style="display:none;">
                    <div class="form-group">
                        <label>Subject <span class="req">*</span></label>
                        <input type="text" name="emp_subject" class="form-control" placeholder="Brief subject of this message to the employee...">
                    </div>
                    <div class="form-group">
                        <label>Message / Instructions <span class="req">*</span></label>
                        <textarea name="emp_message" class="form-control" rows="8"
                            placeholder="Write your message or instructions for the employee..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>Response Required?</label>
                        <select name="emp_response_required" class="form-control">
                            <option value="no">No response needed</option>
                            <option value="yes">Yes, employee must respond</option>
                        </select>
                    </div>
                </div>
<?php endif; ?>

                <button type="submit" class="submit-btn" id="submitBtn">
                    <i class="fas fa-paper-plane"></i> Submit Report
                </button>
            </form>
        </div>
    </div>

    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
    <script>
    const accentColor = '<?php echo $color; ?>';

    function setRecipient(role, btn) {
        document.getElementById('reportToRole').value = role;
        document.querySelectorAll('.recipient-tab').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        // Show/hide recipient dropdowns
        document.getElementById('adminSelect').style.display    = role === 'admin'    ? 'block' : 'none';
        document.getElementById('employeeSelect').style.display = role === 'employee' ? 'block' : 'none';

        // Show/hide form fields based on recipient role
        document.querySelectorAll('.recipient-fields[data-role="admin"]').forEach(el => {
            el.style.display = role === 'admin' ? 'block' : 'none';
        });
        document.querySelectorAll('.recipient-fields[data-role="employee"]').forEach(el => {
            el.style.display = role === 'employee' ? 'block' : 'none';
        });
    }

    function setSeverity(val, btn) {
        document.getElementById('severityVal').value = val;
        document.querySelectorAll('.severity-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
    }

    // Leave days calculator (admin form)
    const fromEl = document.querySelector('[name="leave_from"]');
    const toEl   = document.querySelector('[name="leave_to"]');
    if (fromEl && toEl) {
        function calcDays() {
            const f = new Date(fromEl.value), t = new Date(toEl.value);
            if (fromEl.value && toEl.value && t >= f) {
                const days = Math.round((t - f) / 86400000) + 1;
                document.getElementById('leaveDaysDisplay').textContent = days + ' day' + (days > 1 ? 's' : '');
            } else { document.getElementById('leaveDaysDisplay').textContent = ''; }
        }
        fromEl.addEventListener('change', calcDays);
        toEl.addEventListener('change', calcDays);
    }

    function submitReport(e) {
        e.preventDefault();
        const form  = document.getElementById('reportForm');
        const btn   = document.getElementById('submitBtn');
        const alertBox = document.getElementById('alertBox');
        alertBox.innerHTML = '';
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

        const data = new FormData(form);
        const role = document.getElementById('reportToRole').value;
        const selId = role === 'admin'
            ? document.querySelector('[name="report_to_id_admin"]')?.value
            : document.querySelector('[name="report_to_id_employee"]')?.value;
        data.set('report_to_id', selId || '');

        fetch('<?php echo BASE_URL; ?>/public/api/submit_manager_report.php', { method:'POST', body:data })
        .then(r => r.text())
        .then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e);
                throw new Error('Invalid JSON response: ' + text.substring(0, 200));
            }
        })
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Report';
            if (res.success) {
                alertBox.innerHTML = '<div class="alert-success"><i class="fas fa-check-circle"></i> ' + res.message + '</div>';
                form.reset();
                window.scrollTo({top:0, behavior:'smooth'});
                setTimeout(() => { window.location.href = '<?php echo BASE_URL; ?>/public/manager/reports'; }, 2000);
            } else {
                alertBox.innerHTML = '<div class="alert-error"><i class="fas fa-times-circle"></i> ' + (res.message || 'Failed.') + '</div>';
                window.scrollTo({top:0, behavior:'smooth'});
            }
        })
        .catch((err) => {
            console.error('Submit error:', err);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Report';
            alertBox.innerHTML = '<div class="alert-error">Error: ' + (err.message || 'Network error. Please try again.') + '</div>';
        });
    }
    </script>
</body>
</html>
