<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    header('Location: ' . BASE_URL . '/public/login'); exit();
}
require_once __DIR__ . '/../../../config/db_config.php';
$csrf_token = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;
$managerName = $_SESSION['user_name'] ?? 'Manager';
$managerId   = $_SESSION['user_id'];

if (isset($_SESSION['pay_success'])) { $success = $_SESSION['pay_success']; unset($_SESSION['pay_success']); }

// ── Handle POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Invalid CSRF token.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'submit_for_approval') {
            try {
                $pdo->prepare("UPDATE furn_payroll SET status='pending_approval' WHERE id=? AND status='draft' AND calculated_by=?")
                    ->execute([(int)$_POST['payroll_id'], $managerId]);
                $_SESSION['pay_success'] = "Payroll submitted for admin approval.";
                header('Location: ' . BASE_URL . '/public/manager/payroll-details?id=' . (int)$_POST['payroll_id']); exit();
            } catch (PDOException $e) { $error = $e->getMessage(); }
        }
    }
}

$payrollId = (int)($_GET['id'] ?? 0);
if (!$payrollId) { header('Location: ' . BASE_URL . '/public/manager/payroll'); exit(); }

// ── Fetch payroll ──
$payroll = null;
try {
    $stmt = $pdo->prepare("
        SELECT p.*, u.first_name, u.last_name, u.email,
               m.first_name as mgr_fn, m.last_name as mgr_ln,
               a.first_name as apr_fn, a.last_name as apr_ln
        FROM furn_payroll p
        LEFT JOIN furn_users u ON p.employee_id = u.id
        LEFT JOIN furn_users m ON p.calculated_by = m.id
        LEFT JOIN furn_users a ON p.approved_by = a.id
        WHERE p.id = ?
    ");
    $stmt->execute([$payrollId]);
    $payroll = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $error = $e->getMessage(); }

if (!$payroll) { header('Location: ' . BASE_URL . '/public/manager/payroll'); exit(); }

// ── Fetch attendance for that month/year ──
$attRecords = [];
try {
    $from = sprintf('%04d-%02d-01', $payroll['year'], $payroll['month']);
    $to   = date('Y-m-t', strtotime($from));
    $attCols  = $pdo->query("SHOW COLUMNS FROM furn_attendance")->fetchAll(PDO::FETCH_COLUMN);
    $dateExpr = in_array('date', $attCols) ? 'date' : 'DATE(check_in_time)';
    $stmt = $pdo->prepare("
        SELECT $dateExpr as date, status,
               TIME(check_in_time) as ci_time, TIME(check_out_time) as co_time, notes
        FROM furn_attendance WHERE employee_id=? AND $dateExpr BETWEEN ? AND ? ORDER BY $dateExpr
    ");
    $stmt->execute([$payroll['employee_id'], $from, $to]);
    $attRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$monthNames = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
$statusMap  = ['draft'=>['#95A5A6','Draft'],'pending_approval'=>['#F39C12','Pending Approval'],'approved'=>['#27AE60','Approved'],'rejected'=>['#E74C3C','Rejected']];
[$sc, $sl]  = $statusMap[$payroll['status']] ?? ['#95A5A6','Unknown'];
$pageTitle  = 'Payroll Details';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - FurnitureCraft</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/css/admin-responsive.css">
    <style>
        .pay-header { background:linear-gradient(135deg,#1a252f 0%,#2C3E50 50%,#3D1F14 100%); color:#fff; padding:28px 30px; border-radius:14px; margin-bottom:24px; }
        .detail-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:20px; margin-bottom:24px; }
        .detail-card { background:#fff; border:1.5px solid #F0F0F0; border-radius:12px; padding:20px; }
        .detail-row { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #F5F5F5; font-size:13px; }
        .detail-row:last-child { border-bottom:none; }
        .detail-label { color:#7F8C8D; font-weight:600; }
        .detail-value { color:#2C3E50; font-weight:600; text-align:right; }
        .s-badge { display:inline-flex;align-items:center;gap:5px;border-radius:20px;padding:5px 14px;font-size:12px;font-weight:700; }
        .att-icon { display:inline-flex;align-items:center;gap:4px;border-radius:12px;padding:3px 10px;font-size:11px;font-weight:700; }
        @media print { .no-print { display:none !important; } .main-content { margin-left:0 !important; } }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle no-print" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>
    <div class="sidebar-overlay no-print"></div>
    <?php include_once __DIR__ . '/../../includes/manager_sidebar.php'; ?>
    <div class="top-header no-print">
        <div class="header-left"><div class="system-status"><i class="fas fa-circle"></i> Payroll Details</div></div>
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
        <?php if (isset($success)): ?>
        <div class="alert alert-success no-print"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
        <div class="alert alert-danger no-print"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Header -->
        <div class="pay-header">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px;">
                <div>
                    <h1 style="margin:0 0 6px;font-size:24px;"><i class="fas fa-file-invoice-dollar"></i>
                        <?php echo htmlspecialchars($payroll['first_name'].' '.$payroll['last_name']); ?>
                    </h1>
                    <p style="margin:0;opacity:.85;font-size:14px;">
                        <?php echo $monthNames[$payroll['month']]; ?> <?php echo $payroll['year']; ?>
                        &nbsp;|&nbsp; Payroll #<?php echo str_pad($payroll['id'],4,'0',STR_PAD_LEFT); ?>
                    </p>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:13px;opacity:.8;margin-bottom:4px;">Net Salary</div>
                    <div style="font-size:32px;font-weight:800;">ETB <?php echo number_format($payroll['net_salary'],2); ?></div>
                    <span style="display:inline-block;margin-top:6px;background:<?php echo $sc; ?>33;color:#fff;border:1.5px solid <?php echo $sc; ?>88;border-radius:20px;padding:4px 14px;font-size:12px;font-weight:700;">
                        <?php echo $sl; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Detail Cards -->
        <div class="detail-grid">
            <!-- Employee -->
            <div class="detail-card">
                <h3 style="margin:0 0 14px;font-size:14px;color:#2C3E50;"><i class="fas fa-user" style="color:#3498DB;margin-right:6px;"></i> Employee</h3>
                <div class="detail-row"><span class="detail-label">Name</span><span class="detail-value"><?php echo htmlspecialchars($payroll['first_name'].' '.$payroll['last_name']); ?></span></div>
                <div class="detail-row"><span class="detail-label">Email</span><span class="detail-value" style="font-size:12px;"><?php echo htmlspecialchars($payroll['email']); ?></span></div>
                <div class="detail-row"><span class="detail-label">Period</span><span class="detail-value"><?php echo $monthNames[$payroll['month']]; ?> <?php echo $payroll['year']; ?></span></div>
                <div class="detail-row"><span class="detail-label">Calculated By</span><span class="detail-value"><?php echo htmlspecialchars(($payroll['mgr_fn']??'').' '.($payroll['mgr_ln']??'')); ?></span></div>
                <?php if ($payroll['approved_by']): ?>
                <div class="detail-row"><span class="detail-label">Approved By</span><span class="detail-value"><?php echo htmlspecialchars(($payroll['apr_fn']??'').' '.($payroll['apr_ln']??'')); ?></span></div>
                <?php endif; ?>
                <?php if ($payroll['approved_at']): ?>
                <div class="detail-row"><span class="detail-label">Approved At</span><span class="detail-value"><?php echo date('M d, Y', strtotime($payroll['approved_at'])); ?></span></div>
                <?php endif; ?>
            </div>

            <!-- Attendance -->
            <div class="detail-card">
                <h3 style="margin:0 0 14px;font-size:14px;color:#2C3E50;"><i class="fas fa-calendar-check" style="color:#27AE60;margin-right:6px;"></i> Attendance</h3>
                <div class="detail-row"><span class="detail-label">Present Days</span><span class="detail-value" style="color:#27AE60;"><?php echo $payroll['present_days']; ?></span></div>
                <div class="detail-row"><span class="detail-label">Late Days</span><span class="detail-value" style="color:#F39C12;"><?php echo $payroll['late_days']; ?></span></div>
                <div class="detail-row"><span class="detail-label">Half Days</span><span class="detail-value" style="color:#9B59B6;"><?php echo $payroll['half_day_count']; ?></span></div>
                <div class="detail-row"><span class="detail-label">Absent Days</span><span class="detail-value" style="color:#E74C3C;"><?php echo $payroll['absent_days']; ?></span></div>
                <div class="detail-row"><span class="detail-label">Working Days</span><span class="detail-value"><?php echo $payroll['working_days_per_month']; ?></span></div>
                <div class="detail-row"><span class="detail-label">Overtime Hours</span><span class="detail-value"><?php echo $payroll['overtime_hours']; ?> hrs</span></div>
            </div>

            <!-- Salary Breakdown -->
            <div class="detail-card">
                <h3 style="margin:0 0 14px;font-size:14px;color:#2C3E50;"><i class="fas fa-calculator" style="color:#9B59B6;margin-right:6px;"></i> Salary Breakdown</h3>
                <div class="detail-row"><span class="detail-label">Base Salary</span><span class="detail-value">ETB <?php echo number_format($payroll['base_salary'],2); ?></span></div>
                <div class="detail-row"><span class="detail-label">Daily Rate</span><span class="detail-value">ETB <?php echo number_format($payroll['base_salary']/max(1,$payroll['working_days_per_month']),2); ?></span></div>
                <div class="detail-row"><span class="detail-label">Basic Earned</span><span class="detail-value">ETB <?php echo number_format($payroll['basic_earned'],2); ?></span></div>
                <div class="detail-row"><span class="detail-label">Overtime Pay</span><span class="detail-value">ETB <?php echo number_format($payroll['overtime_pay'],2); ?></span></div>
                <div class="detail-row"><span class="detail-label" style="color:#27AE60;">Bonus</span><span class="detail-value" style="color:#27AE60;">ETB <?php echo number_format($payroll['bonus'] ?? 0,2); ?></span></div>
                <div class="detail-row"><span class="detail-label" style="color:#27AE60;font-weight:700;">Gross Salary</span><span class="detail-value" style="color:#27AE60;">ETB <?php echo number_format($payroll['gross_salary'],2); ?></span></div>
                <div class="detail-row"><span class="detail-label" style="color:#E74C3C;">Tax</span><span class="detail-value" style="color:#E74C3C;">- ETB <?php echo number_format($payroll['tax_amount'],2); ?></span></div>
                <div class="detail-row"><span class="detail-label" style="color:#E74C3C;">Other Deductions</span><span class="detail-value" style="color:#E74C3C;">- ETB <?php echo number_format($payroll['other_deductions'],2); ?></span></div>
                <div class="detail-row" style="border-top:2px solid #2C3E50;margin-top:6px;padding-top:10px;">
                    <span class="detail-label" style="font-size:15px;color:#2C3E50;">Net Salary</span>
                    <span class="detail-value" style="font-size:18px;color:#2C3E50;">ETB <?php echo number_format($payroll['net_salary'],2); ?></span>
                </div>
                <?php if (!empty($payroll['payment_date'])): ?>
                <div class="detail-row"><span class="detail-label">Payment Date</span><span class="detail-value"><?php echo date('M d, Y', strtotime($payroll['payment_date'])); ?></span></div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($payroll['notes'])): ?>
        <div class="section-card" style="margin-bottom:24px;">
            <h3 style="margin:0 0 10px;font-size:14px;color:#2C3E50;"><i class="fas fa-sticky-note" style="color:#F39C12;margin-right:6px;"></i> Notes</h3>
            <p style="margin:0;color:#555;font-size:13px;line-height:1.6;"><?php echo nl2br(htmlspecialchars($payroll['notes'])); ?></p>
        </div>
        <?php endif; ?>

        <?php if ($payroll['status'] === 'rejected' && !empty($payroll['rejection_reason'])): ?>
        <div style="background:#FDEDEC;border:1.5px solid #E74C3C55;border-radius:10px;padding:16px;margin-bottom:24px;">
            <div style="font-size:13px;font-weight:700;color:#E74C3C;margin-bottom:6px;"><i class="fas fa-times-circle"></i> Rejection Reason</div>
            <div style="font-size:13px;color:#555;"><?php echo nl2br(htmlspecialchars($payroll['rejection_reason'])); ?></div>
        </div>
        <?php endif; ?>

        <!-- Attendance Records Table -->
        <div class="section-card" style="margin-bottom:24px;">
            <h3 style="margin:0 0 16px;font-size:14px;color:#2C3E50;"><i class="fas fa-history" style="color:#3498DB;margin-right:6px;"></i>
                Attendance — <?php echo $monthNames[$payroll['month']]; ?> <?php echo $payroll['year']; ?>
                <span style="font-size:12px;color:#95A5A6;font-weight:400;margin-left:8px;"><?php echo count($attRecords); ?> records</span>
            </h3>
            <?php if (empty($attRecords)): ?>
            <p style="text-align:center;color:#95A5A6;padding:30px 0;font-size:13px;">No attendance records found for this period.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Date</th><th>Day</th><th>Status</th><th>Check-In</th><th>Check-Out</th><th>Notes</th></tr></thead>
                    <tbody>
                    <?php
                    $attColors = ['present'=>'#27AE60','absent'=>'#E74C3C','late'=>'#F39C12','half_day'=>'#9B59B6'];
                    $attIcons  = ['present'=>'fa-check-circle','absent'=>'fa-times-circle','late'=>'fa-clock','half_day'=>'fa-adjust'];
                    $attLabels = ['present'=>'Present','absent'=>'Absent','late'=>'Late','half_day'=>'Half Day'];
                    foreach ($attRecords as $r):
                        $ac = $attColors[$r['status']] ?? '#95A5A6';
                        $ai = $attIcons[$r['status']]  ?? 'fa-circle';
                        $al = $attLabels[$r['status']] ?? ucfirst($r['status']);
                    ?>
                    <tr>
                        <td style="font-weight:600;"><?php echo date('M d, Y', strtotime($r['date'])); ?></td>
                        <td style="color:#7F8C8D;font-size:13px;"><?php echo date('l', strtotime($r['date'])); ?></td>
                        <td><span class="att-icon" style="background:<?php echo $ac; ?>18;color:<?php echo $ac; ?>;"><i class="fas <?php echo $ai; ?>"></i> <?php echo $al; ?></span></td>
                        <td style="font-size:13px;color:#7F8C8D;"><?php echo !empty($r['ci_time']) ? date('h:i A', strtotime($r['ci_time'])) : '—'; ?></td>
                        <td style="font-size:13px;color:#7F8C8D;"><?php echo !empty($r['co_time']) ? date('h:i A', strtotime($r['co_time'])) : '—'; ?></td>
                        <td style="font-size:12px;color:#7F8C8D;"><?php echo htmlspecialchars($r['notes'] ?: '—'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Actions -->
        <div class="no-print" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:30px;">
            <a href="<?php echo BASE_URL; ?>/public/manager/payroll"
               style="padding:11px 20px;background:#F0F2F5;color:#555;border:none;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                <i class="fas fa-arrow-left"></i> Back to Payroll
            </a>
            <?php if ($payroll['status'] === 'draft'): ?>
            <form method="POST" onsubmit="return confirm('Submit this payroll for admin approval?')">
                <input type="hidden" name="action" value="submit_for_approval">
                <input type="hidden" name="payroll_id" value="<?php echo $payroll['id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <button type="submit" style="padding:11px 22px;background:#27AE60;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
                    <i class="fas fa-paper-plane"></i> Submit for Approval
                </button>
            </form>
            <?php endif; ?>
            <button onclick="window.print()" style="padding:11px 20px;background:#3498DB;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
