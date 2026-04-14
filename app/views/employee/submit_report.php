<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}
require_once __DIR__ . '/../../../config/db_config.php';

$employeeName = $_SESSION['user_name'] ?? 'Employee';
$employeeId   = $_SESSION['user_id'];
$type         = $_GET['type'] ?? '';

$validTypes = ['task_progress', 'material_usage', 'incident', 'daily_summary', 'leave_request'];
if (!in_array($type, $validTypes)) {
    header('Location: ' . BASE_URL . '/public/employee/reports');
    exit();
}

$typeLabels = [
    'task_progress'  => 'Task Progress Report',
    'material_usage' => 'Material Usage Report',
    'incident'       => 'Incident / Problem Report',
    'daily_summary'  => 'Daily Work Summary',
    'leave_request'  => 'Leave / Absence Request',
];
$typeIcons = [
    'task_progress'  => 'fa-tasks',
    'material_usage' => 'fa-boxes',
    'incident'       => 'fa-exclamation-triangle',
    'daily_summary'  => 'fa-clipboard-list',
    'leave_request'  => 'fa-calendar-times',
];
$typeColors = [
    'task_progress'  => '#3498DB',
    'material_usage' => '#9B59B6',
    'incident'       => '#E74C3C',
    'daily_summary'  => '#27AE60',
    'leave_request'  => '#F39C12',
];

$label = $typeLabels[$type];
$icon  = $typeIcons[$type];
$color = $typeColors[$type];

// Fetch data needed for dropdowns
$myTasks = [];
$myMaterials = [];

try {
    // Active tasks assigned to this employee
    $stmt = $pdo->prepare("
        SELECT t.id, t.task_name, t.progress, o.order_number, p.product_name
        FROM furn_production_tasks t
        LEFT JOIN furn_orders o ON t.order_id = o.id
        LEFT JOIN furn_products p ON t.product_id = p.product_id
        WHERE t.employee_id = ? AND t.status IN ('pending','in_progress')
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([$employeeId]);
    $myTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Available materials
    $stmt2 = $pdo->query("SELECT id, material_name, unit, quantity FROM furn_materials ORDER BY material_name");
    $myMaterials = $stmt2->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("submit_report fetch error: " . $e->getMessage());
}

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
        .report-form-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.08);
            padding: 32px;
            max-width: 720px;
            margin: 0 auto;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 13px;
            color: #2C3E50;
            margin-bottom: 7px;
            text-transform: uppercase;
            letter-spacing: .4px;
        }
        .form-group label .req { color: #E74C3C; margin-left: 3px; }
        .form-control {
            width: 100%;
            padding: 11px 14px;
            border: 2px solid #E0E0E0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            color: #2C3E50;
            transition: border-color .2s;
            box-sizing: border-box;
            background: #FAFAFA;
        }
        .form-control:focus { outline: none; border-color: <?php echo $color; ?>; background: #fff; }
        textarea.form-control { resize: vertical; min-height: 100px; }
        .severity-group { display: flex; gap: 10px; flex-wrap: wrap; }
        .severity-btn {
            flex: 1; min-width: 100px;
            padding: 10px 8px;
            border: 2px solid #E0E0E0;
            border-radius: 8px;
            background: #fff;
            cursor: pointer;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            text-align: center;
            transition: all .2s;
            color: #7F8C8D;
        }
        .severity-btn.active { color: #fff; border-color: transparent; }
        .severity-btn.low.active  { background: #27AE60; }
        .severity-btn.medium.active { background: #F39C12; }
        .severity-btn.high.active { background: #E74C3C; }
        .submit-btn {
            width: 100%;
            padding: 14px;
            background: <?php echo $color; ?>;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: opacity .2s;
            margin-top: 8px;
        }
        .submit-btn:hover { opacity: .88; }
        .submit-btn:disabled { opacity: .6; cursor: not-allowed; }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #7F8C8D;
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 20px;
            transition: color .2s;
        }
        .back-link:hover { color: <?php echo $color; ?>; }
        .form-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 2px solid #F0F0F0;
        }
        .form-header-icon {
            width: 52px; height: 52px;
            border-radius: 12px;
            background: <?php echo $color; ?>22;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
            color: <?php echo $color; ?>;
            flex-shrink: 0;
        }
        .alert-success {
            background: #d4edda; color: #155724;
            border: 1px solid #c3e6cb;
            border-radius: 8px; padding: 14px 18px;
            margin-bottom: 20px; font-size: 14px;
        }
        .alert-error {
            background: #f8d7da; color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 8px; padding: 14px 18px;
            margin-bottom: 20px; font-size: 14px;
        }
        .range-display {
            font-size: 13px; color: <?php echo $color; ?>;
            font-weight: 600; margin-left: 8px;
        }
        .hint { font-size: 12px; color: #95A5A6; margin-top: 5px; }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>
    <div class="sidebar-overlay"></div>
    <?php include_once __DIR__ . '/../../includes/employee_sidebar.php'; ?>

    <!-- Top Header -->
    <?php 
    $pageTitle = 'Submit Report';
    include_once __DIR__ . '/../../includes/employee_header.php'; 
    ?>
        <div class="header-left">
            <div class="system-status">
                <i class="fas fa-circle"></i> <?php echo $label; ?>
            </div>
        </div>
        <div class="header-right">
            <div class="admin-profile">
                <div class="admin-avatar"><?php echo strtoupper(substr($employeeName, 0, 1)); ?></div>
                <div>
                    <div style="font-weight:600;font-size:14px;"><?php echo htmlspecialchars($employeeName); ?></div>
                    <div class="admin-role-badge" style="background:#27AE60;">EMPLOYEE</div>
                </div>
            </div>
        </div>
    </div>

    <div class="main-content">
        <a href="<?php echo BASE_URL; ?>/public/employee/reports" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Reports
        </a>

        <div class="report-form-card">
            <div class="form-header">
                <div class="form-header-icon"><i class="fas <?php echo $icon; ?>"></i></div>
                <div>
                    <div style="font-size:20px;font-weight:700;color:#2C3E50;"><?php echo $label; ?></div>
                    <div style="font-size:13px;color:#95A5A6;margin-top:3px;">Fill in the details and submit to your manager</div>
                </div>
            </div>

            <div id="alertBox"></div>

            <form id="reportForm" onsubmit="submitReport(event)">
                <input type="hidden" name="report_type" value="<?php echo $type; ?>">

<?php if ($type === 'task_progress'): ?>
                <!-- ── TASK PROGRESS REPORT ── -->
                <div class="form-group">
                    <label>Related Task <span class="req">*</span></label>
                    <select name="task_id" class="form-control" required>
                        <option value="">— Select a task —</option>
                        <?php foreach ($myTasks as $t): ?>
                        <option value="<?php echo $t['id']; ?>">
                            #<?php echo str_pad($t['id'],4,'0',STR_PAD_LEFT); ?> —
                            <?php echo htmlspecialchars($t['task_name'] ?? 'Task'); ?>
                            (<?php echo htmlspecialchars($t['order_number'] ?? 'No order'); ?>)
                            — <?php echo $t['progress']; ?>% done
                        </option>
                        <?php endforeach; ?>
                        <?php if (empty($myTasks)): ?>
                        <option disabled>No active tasks assigned to you</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Current Progress <span class="req">*</span>
                        <span class="range-display" id="progressVal">50%</span>
                    </label>
                    <input type="range" name="progress" min="0" max="100" value="50" class="form-control"
                        style="padding:6px 0;background:transparent;border:none;"
                        oninput="document.getElementById('progressVal').textContent=this.value+'%'">
                </div>
                <div class="form-group">
                    <label>What was done <span class="req">*</span></label>
                    <textarea name="work_done" class="form-control" rows="4"
                        placeholder="Describe what work was completed on this task..." required></textarea>
                </div>
                <div class="form-group">
                    <label>Any blockers or issues?</label>
                    <textarea name="blockers" class="form-control" rows="3"
                        placeholder="Describe any problems, delays, or things blocking progress (leave blank if none)..."></textarea>
                </div>
                <div class="form-group">
                    <label>Estimated completion date</label>
                    <input type="date" name="est_completion" class="form-control" min="<?php echo date('Y-m-d'); ?>">
                </div>

<?php elseif ($type === 'material_usage'): ?>
                <!-- ── MATERIAL USAGE REPORT ── -->
                <div class="form-group">
                    <label>Related Task <span class="req">*</span></label>
                    <select name="task_id" class="form-control" required>
                        <option value="">— Select a task —</option>
                        <?php foreach ($myTasks as $t): ?>
                        <option value="<?php echo $t['id']; ?>">
                            #<?php echo str_pad($t['id'],4,'0',STR_PAD_LEFT); ?> —
                            <?php echo htmlspecialchars($t['task_name'] ?? 'Task'); ?>
                            (<?php echo htmlspecialchars($t['order_number'] ?? 'No order'); ?>)
                        </option>
                        <?php endforeach; ?>
                        <?php if (empty($myTasks)): ?>
                        <option disabled>No active tasks assigned to you</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div id="materialRows">
                    <div class="material-row" style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:10px;align-items:end;margin-bottom:12px;">
                        <div class="form-group" style="margin:0;">
                            <label>Material <span class="req">*</span></label>
                            <select name="material_id[]" class="form-control" required>
                                <option value="">— Select —</option>
                                <?php foreach ($myMaterials as $m): ?>
                                <option value="<?php echo $m['id']; ?>" data-unit="<?php echo htmlspecialchars($m['unit']); ?>">
                                    <?php echo htmlspecialchars($m['material_name']); ?> (<?php echo $m['unit']; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>Qty Used <span class="req">*</span></label>
                            <input type="number" name="qty_used[]" class="form-control" min="0.01" step="0.01" placeholder="0" required>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>Waste Qty</label>
                            <input type="number" name="qty_waste[]" class="form-control" min="0" step="0.01" placeholder="0" value="0">
                        </div>
                        <div style="padding-bottom:2px;">
                            <button type="button" onclick="removeMaterialRow(this)"
                                style="background:#FDEDEC;color:#E74C3C;border:none;border-radius:6px;padding:10px 12px;cursor:pointer;font-size:14px;">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <button type="button" onclick="addMaterialRow()"
                    style="background:#EBF5FB;color:#3498DB;border:1px dashed #AED6F1;border-radius:8px;padding:9px 16px;cursor:pointer;font-size:13px;font-weight:600;margin-bottom:20px;">
                    <i class="fas fa-plus"></i> Add Another Material
                </button>
                <div class="form-group">
                    <label>Notes / Reason for waste</label>
                    <textarea name="notes" class="form-control" rows="3"
                        placeholder="Explain any waste or unusual usage..."></textarea>
                </div>

<?php elseif ($type === 'incident'): ?>
                <!-- ── INCIDENT / PROBLEM REPORT ── -->
                <div class="form-group">
                    <label>Incident Title <span class="req">*</span></label>
                    <input type="text" name="incident_title" class="form-control"
                        placeholder="e.g. Machine malfunction, Material damage, Workplace injury..." required>
                </div>
                <div class="form-group">
                    <label>Incident Date & Time <span class="req">*</span></label>
                    <input type="datetime-local" name="incident_datetime" class="form-control"
                        value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                </div>
                <div class="form-group">
                    <label>Incident Type <span class="req">*</span></label>
                    <select name="incident_type" class="form-control" required>
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
                    <input type="hidden" name="severity" id="severityVal" value="medium" required>
                    <div class="severity-group">
                        <button type="button" class="severity-btn low" onclick="setSeverity('low',this)">
                            <i class="fas fa-circle" style="color:#27AE60;"></i><br>Low
                        </button>
                        <button type="button" class="severity-btn medium active" onclick="setSeverity('medium',this)">
                            <i class="fas fa-circle" style="color:#F39C12;"></i><br>Medium
                        </button>
                        <button type="button" class="severity-btn high" onclick="setSeverity('high',this)">
                            <i class="fas fa-circle" style="color:#E74C3C;"></i><br>High
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label>What happened? <span class="req">*</span></label>
                    <textarea name="description" class="form-control" rows="5"
                        placeholder="Describe the incident in detail — what happened, where, and how..." required></textarea>
                </div>
                <div class="form-group">
                    <label>Immediate action taken</label>
                    <textarea name="action_taken" class="form-control" rows="3"
                        placeholder="What did you do immediately after the incident?"></textarea>
                </div>
                <div class="form-group">
                    <label>Were any people injured?</label>
                    <select name="injuries" class="form-control">
                        <option value="no">No injuries</option>
                        <option value="minor">Minor injury (first aid only)</option>
                        <option value="serious">Serious injury (medical attention needed)</option>
                    </select>
                </div>

<?php elseif ($type === 'daily_summary'): ?>
                <!-- ── DAILY WORK SUMMARY ── -->
                <div class="form-group">
                    <label>Report Date <span class="req">*</span></label>
                    <input type="date" name="report_date" class="form-control"
                        value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label>Tasks worked on today <span class="req">*</span></label>
                    <div style="display:flex;flex-direction:column;gap:8px;" id="taskCheckboxes">
                        <?php if (!empty($myTasks)): ?>
                            <?php foreach ($myTasks as $t): ?>
                            <label style="display:flex;align-items:center;gap:10px;font-weight:400;text-transform:none;letter-spacing:0;cursor:pointer;padding:10px 12px;border:1.5px solid #E0E0E0;border-radius:8px;transition:border-color .2s;"
                                onmouseover="this.style.borderColor='<?php echo $color; ?>'" onmouseout="this.style.borderColor='#E0E0E0'">
                                <input type="checkbox" name="tasks_worked[]" value="<?php echo $t['id']; ?>"
                                    style="width:16px;height:16px;accent-color:<?php echo $color; ?>;">
                                <span>
                                    <strong>#<?php echo str_pad($t['id'],4,'0',STR_PAD_LEFT); ?></strong> —
                                    <?php echo htmlspecialchars($t['task_name'] ?? 'Task'); ?>
                                    <span style="color:#95A5A6;font-size:12px;">(<?php echo htmlspecialchars($t['order_number'] ?? ''); ?>)</span>
                                </span>
                            </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="color:#95A5A6;font-size:13px;padding:10px;">No active tasks assigned.</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label>Summary of work done today <span class="req">*</span></label>
                    <textarea name="summary" class="form-control" rows="5"
                        placeholder="Describe what you accomplished today in detail..." required></textarea>
                </div>
                <div class="form-group">
                    <label>Challenges faced today</label>
                    <textarea name="challenges" class="form-control" rows="3"
                        placeholder="Any difficulties, delays, or problems encountered today?"></textarea>
                </div>
                <div class="form-group">
                    <label>Plan for tomorrow</label>
                    <textarea name="tomorrow_plan" class="form-control" rows="3"
                        placeholder="What do you plan to work on tomorrow?"></textarea>
                </div>

<?php elseif ($type === 'leave_request'): ?>
                <!-- ── LEAVE / ABSENCE REQUEST ── -->
                <div class="form-group">
                    <label>Leave Type <span class="req">*</span></label>
                    <select name="leave_type" class="form-control" required>
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
                        <input type="date" name="leave_from" class="form-control"
                            min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div>
                        <label>To Date <span class="req">*</span></label>
                        <input type="date" name="leave_to" class="form-control"
                            min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Number of days <span class="range-display" id="leaveDaysDisplay"></span></label>
                    <div class="hint">Auto-calculated from the dates above.</div>
                </div>
                <div class="form-group">
                    <label>Reason <span class="req">*</span></label>
                    <textarea name="reason" class="form-control" rows="4"
                        placeholder="Explain the reason for your leave request..." required></textarea>
                </div>
                <div class="form-group">
                    <label>Who will cover your tasks?</label>
                    <input type="text" name="coverage" class="form-control"
                        placeholder="Name of colleague who will handle your tasks (if known)">
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
    // Severity selector (incident)
    function setSeverity(val, btn) {
        document.getElementById('severityVal').value = val;
        document.querySelectorAll('.severity-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
    }

    // Material rows (material_usage)
    const materialTemplate = `<?php
        $opts = '';
        foreach ($myMaterials as $m) {
            $opts .= '<option value="' . $m['id'] . '" data-unit="' . htmlspecialchars($m['unit']) . '">'
                   . htmlspecialchars($m['material_name']) . ' (' . $m['unit'] . ')</option>';
        }
        echo addslashes('<div class="material-row" style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:10px;align-items:end;margin-bottom:12px;">
            <div class="form-group" style="margin:0;">
                <label>Material</label>
                <select name="material_id[]" class="form-control" required>
                    <option value="">— Select —</option>' . $opts . '
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label>Qty Used</label>
                <input type="number" name="qty_used[]" class="form-control" min="0.01" step="0.01" placeholder="0" required>
            </div>
            <div class="form-group" style="margin:0;">
                <label>Waste Qty</label>
                <input type="number" name="qty_waste[]" class="form-control" min="0" step="0.01" placeholder="0" value="0">
            </div>
            <div style="padding-bottom:2px;">
                <button type="button" onclick="removeMaterialRow(this)" style="background:#FDEDEC;color:#E74C3C;border:none;border-radius:6px;padding:10px 12px;cursor:pointer;font-size:14px;"><i class="fas fa-trash"></i></button>
            </div>
        </div>');
    ?>`; // end template

    function addMaterialRow() {
        const container = document.getElementById('materialRows');
        const div = document.createElement('div');
        div.innerHTML = materialTemplate;
        container.appendChild(div.firstChild);
    }
    function removeMaterialRow(btn) {
        const rows = document.querySelectorAll('.material-row');
        if (rows.length <= 1) return; // keep at least one
        btn.closest('.material-row').remove();
    }

    // Leave days auto-calc
    const fromEl = document.querySelector('[name="leave_from"]');
    const toEl   = document.querySelector('[name="leave_to"]');
    if (fromEl && toEl) {
        function calcDays() {
            const f = new Date(fromEl.value), t = new Date(toEl.value);
            if (fromEl.value && toEl.value && t >= f) {
                const days = Math.round((t - f) / 86400000) + 1;
                document.getElementById('leaveDaysDisplay').textContent = days + ' day' + (days > 1 ? 's' : '');
            } else {
                document.getElementById('leaveDaysDisplay').textContent = '';
            }
        }
        fromEl.addEventListener('change', calcDays);
        toEl.addEventListener('change', calcDays);
    }

    // Form submission
    function submitReport(e) {
        e.preventDefault();
        const form   = document.getElementById('reportForm');
        const btn    = document.getElementById('submitBtn');
        const alert  = document.getElementById('alertBox');
        alert.innerHTML = '';
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

        const data = new FormData(form);

        fetch('<?php echo BASE_URL; ?>/public/api/submit_employee_report.php', {
            method: 'POST',
            body: data
        })
        .then(r => r.json())
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Report';
            if (res.success) {
                alert.innerHTML = '<div class="alert-success"><i class="fas fa-check-circle"></i> ' + res.message + '</div>';
                form.reset();
                window.scrollTo({top:0, behavior:'smooth'});
                setTimeout(() => {
                    window.location.href = '<?php echo BASE_URL; ?>/public/employee/reports';
                }, 2000);
            } else {
                alert.innerHTML = '<div class="alert-error"><i class="fas fa-times-circle"></i> ' + (res.message || 'Failed to submit.') + '</div>';
                window.scrollTo({top:0, behavior:'smooth'});
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Report';
            alert.innerHTML = '<div class="alert-error">Network error. Please try again.</div>';
        });
    }
    </script>
</body>
</html>
