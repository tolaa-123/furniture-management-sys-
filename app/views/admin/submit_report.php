<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . BASE_URL . '/public/login'); exit();
}
require_once __DIR__ . '/../../../config/db_config.php';

$adminName = $_SESSION['user_name'] ?? 'Admin';
$adminId   = $_SESSION['user_id'];
$type      = $_GET['type'] ?? '';

$validTypes = ['business_performance','budget_financial','staffing_hr','system_operations','policy_compliance','leave_request','general_other'];
if (!in_array($type, $validTypes)) {
    header('Location: ' . BASE_URL . '/public/admin/reports'); exit();
}

$typeLabels = [
    'business_performance' => 'Business Performance Report',
    'budget_financial'     => 'Budget / Financial Report',
    'staffing_hr'          => 'Staffing / HR Report',
    'system_operations'    => 'System / Operations Issue',
    'policy_compliance'    => 'Policy / Compliance Report',
    'leave_request'        => 'Leave / Absence Request',
    'general_other'        => 'General / Other',
];
$typeIcons = [
    'business_performance' => 'fa-chart-line',
    'budget_financial'     => 'fa-dollar-sign',
    'staffing_hr'          => 'fa-users',
    'system_operations'    => 'fa-exclamation-triangle',
    'policy_compliance'    => 'fa-shield-alt',
    'leave_request'        => 'fa-calendar-times',
    'general_other'        => 'fa-file-alt',
];
$typeColors = [
    'business_performance' => '#3498DB',
    'budget_financial'     => '#9B59B6',
    'staffing_hr'          => '#27AE60',
    'system_operations'    => '#E74C3C',
    'policy_compliance'    => '#F39C12',
    'leave_request'        => '#E67E22',
    'general_other'        => '#7F8C8D',
];

$label = $typeLabels[$type];
$icon  = $typeIcons[$type];
$color = $typeColors[$type];

// Fetch managers for "Report To"
$managers = [];
try {
    $stmt = $pdo->query("SELECT id, CONCAT(first_name,' ',last_name) as name FROM furn_users WHERE role='manager' AND status='active' ORDER BY name");
    $managers = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        .severity-btn.low.active  { background:#27AE60; }
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
    </style>
</head>
<body>
    <button class="mobile-menu-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>
    <div class="sidebar-overlay"></div>
    <?php include_once __DIR__ . '/../../includes/admin_sidebar.php'; ?>
    <!-- Top Header -->
    <?php 
    $pageTitle = 'Submit Report';
    include_once __DIR__ . '/../../includes/admin_header.php'; 
    ?>
        <div class="header-left"><div class="system-status"><i class="fas fa-circle"></i> <?php echo $label; ?></div></div>
        <div class="header-right">
            <div class="admin-profile">
                <div class="admin-avatar"><?php echo strtoupper(substr($adminName,0,1)); ?></div>
                <div>
                    <div style="font-weight:600;font-size:14px;"><?php echo htmlspecialchars($adminName); ?></div>
                    <div class="admin-role-badge">ADMIN</div>
                </div>
            </div>
        </div>
    </div>
    <div class="main-content">
        <a href="<?php echo BASE_URL; ?>/public/admin/reports" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Reports
        </a>
        <div class="report-form-card">
            <div class="form-header">
                <div class="form-header-icon"><i class="fas <?php echo $icon; ?>"></i></div>
                <div>
                    <div style="font-size:20px;font-weight:700;color:#2C3E50;"><?php echo $label; ?></div>
                    <div style="font-size:13px;color:#95A5A6;margin-top:3px;">Fill in the details and submit to manager</div>
                </div>
            </div>
            <div id="alertBox"></div>
            <form id="reportForm" onsubmit="submitReport(event)">
                <input type="hidden" name="report_type" value="<?php echo $type; ?>">

                <!-- Report To: Manager -->
                <div class="form-group">
                    <label>Report To (Manager) <span class="req">*</span></label>
                    <select name="report_to_id" class="form-control">
                        <option value="">— All Managers —</option>
                        <?php foreach ($managers as $m): ?>
                        <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

<?php if ($type === 'business_performance'): ?>
                <div class="form-group">
                    <label>Period <span class="req">*</span></label>
                    <input type="text" name="period" class="form-control" placeholder="e.g. March 2026, Q1 2026" required>
                </div>
                <div class="form-group">
                    <label>Performance Summary <span class="req">*</span></label>
                    <textarea name="summary" class="form-control" rows="5"
                        placeholder="Sales, revenue, orders, KPIs, and key metrics..." required></textarea>
                </div>

<?php elseif ($type === 'budget_financial'): ?>
                <div class="form-group">
                    <label>Period <span class="req">*</span></label>
                    <input type="text" name="period" class="form-control" placeholder="e.g. March 2026, Q1 2026" required>
                </div>
                <div class="form-group">
                    <label>Financial Summary <span class="req">*</span></label>
                    <textarea name="summary" class="form-control" rows="5"
                        placeholder="Budget status, spending, major expenses, concerns..." required></textarea>
                </div>

<?php elseif ($type === 'staffing_hr'): ?>
                <div class="form-group">
                    <label>HR Summary <span class="req">*</span></label>
                    <textarea name="summary" class="form-control" rows="5"
                        placeholder="Headcount, staffing levels, hiring needs, HR issues..." required></textarea>
                </div>

<?php elseif ($type === 'system_operations'): ?>
                <div class="form-group">
                    <label>Issue Title <span class="req">*</span></label>
                    <input type="text" name="issue_title" class="form-control"
                        placeholder="Brief description of the issue" required>
                </div>
                <div class="form-group">
                    <label>Priority <span class="req">*</span></label>
                    <input type="hidden" name="priority" id="priorityVal" value="medium">
                    <div class="severity-group">
                        <button type="button" class="severity-btn low" onclick="setPriority('low',this)"><i class="fas fa-circle" style="color:#27AE60;"></i><br>Low</button>
                        <button type="button" class="severity-btn medium active" onclick="setPriority('medium',this)"><i class="fas fa-circle" style="color:#F39C12;"></i><br>Medium</button>
                        <button type="button" class="severity-btn high" onclick="setPriority('high',this)"><i class="fas fa-circle" style="color:#E74C3C;"></i><br>High</button>
                    </div>
                </div>
                <div class="form-group">
                    <label>Issue Details <span class="req">*</span></label>
                    <textarea name="details" class="form-control" rows="5"
                        placeholder="Problem description, impact, and requested action..." required></textarea>
                </div>

<?php elseif ($type === 'policy_compliance'): ?>
                <div class="form-group">
                    <label>Subject <span class="req">*</span></label>
                    <input type="text" name="subject" class="form-control"
                        placeholder="e.g. Safety policy update, Audit results" required>
                </div>
                <div class="form-group">
                    <label>Details <span class="req">*</span></label>
                    <textarea name="details" class="form-control" rows="5"
                        placeholder="Policy/compliance details and required actions..." required></textarea>
                </div>

<?php elseif ($type === 'leave_request'): ?>
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
                        <input type="date" name="leave_from" class="form-control" min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div>
                        <label>To Date <span class="req">*</span></label>
                        <input type="date" name="leave_to" class="form-control" min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Duration <span class="range-display" id="leaveDaysDisplay"></span></label>
                    <div class="hint">Auto-calculated from the dates above.</div>
                </div>
                <div class="form-group">
                    <label>Reason <span class="req">*</span></label>
                    <textarea name="reason" class="form-control" rows="3"
                        placeholder="Brief reason for leave request..." required></textarea>
                </div>

<?php else: // general_other ?>
                <div class="form-group">
                    <label>Report Title <span class="req">*</span></label>
                    <input type="text" name="title" class="form-control" placeholder="Enter a descriptive title..." required>
                </div>
                <div class="form-group">
                    <label>Details <span class="req">*</span></label>
                    <textarea name="details" class="form-control" rows="5"
                        placeholder="Write your report details here..." required></textarea>
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
    function setPriority(val, btn) {
        document.getElementById('priorityVal').value = val;
        document.querySelectorAll('.severity-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
    }

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
        const form = document.getElementById('reportForm');
        const btn  = document.getElementById('submitBtn');
        const alertBox = document.getElementById('alertBox');
        alertBox.innerHTML = '';
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

        fetch('<?php echo BASE_URL; ?>/public/api/submit_admin_report.php', { method:'POST', body: new FormData(form) })
        .then(r => r.json())
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Report';
            if (res.success) {
                alertBox.innerHTML = '<div class="alert-success"><i class="fas fa-check-circle"></i> ' + res.message + '</div>';
                form.reset();
                window.scrollTo({top:0, behavior:'smooth'});
                setTimeout(() => { window.location.href = '<?php echo BASE_URL; ?>/public/admin/reports'; }, 2000);
            } else {
                alertBox.innerHTML = '<div class="alert-error"><i class="fas fa-times-circle"></i> ' + (res.message || 'Failed.') + '</div>';
                window.scrollTo({top:0, behavior:'smooth'});
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Report';
            alertBox.innerHTML = '<div class="alert-error">Network error. Please try again.</div>';
        });
    }
    </script>
</body>
</html>
