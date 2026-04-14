<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
    header('Location: ' . BASE_URL . '/public/login'); exit();
}
require_once __DIR__ . '/../../../config/db_config.php';

// ── Date range filter ──
$dateFrom = $_GET['from'] ?? date('Y-m-01');
$dateTo   = $_GET['to']   ?? date('Y-m-d');
$report   = $_GET['report'] ?? 'tasks';
if ($dateFrom > $dateTo) $dateFrom = $dateTo;

$employeeId = (int)$_SESSION['user_id'];
$pageTitle  = 'My Reports';

// ══════════════════════════════════════════
// DATA QUERIES
// ══════════════════════════════════════════

// ── 1. MY TASKS ──
$taskSummary = ['total'=>0,'completed'=>0,'in_progress'=>0,'pending'=>0,'avg_progress'=>0];
$taskRows = [];
if ($report === 'tasks') {
    try {
        $s = $pdo->prepare("
            SELECT t.id, t.status, t.progress, t.created_at, t.completed_at,
                   t.estimated_hours, t.actual_hours,
                   o.order_number, o.furniture_name, o.furniture_type
            FROM furn_production_tasks t
            LEFT JOIN furn_orders o ON t.order_id = o.id
            WHERE t.employee_id = ?
              AND DATE(t.created_at) BETWEEN ? AND ?
            ORDER BY t.created_at DESC
        ");
        $s->execute([$employeeId, $dateFrom, $dateTo]);
        $taskRows = $s->fetchAll(PDO::FETCH_ASSOC);
        $totalProg = 0;
        foreach ($taskRows as $r) {
            $taskSummary['total']++;
            $totalProg += intval($r['progress']);
            if ($r['status'] === 'completed')   $taskSummary['completed']++;
            elseif ($r['status'] === 'in_progress') $taskSummary['in_progress']++;
            else $taskSummary['pending']++;
        }
        $taskSummary['avg_progress'] = $taskSummary['total'] > 0
            ? round($totalProg / $taskSummary['total'], 1) : 0;
    } catch (PDOException $e) { error_log($e->getMessage()); }
}

// ── 2. MY ATTENDANCE ──
$attSummary = ['total'=>0,'present'=>0,'absent'=>0,'late'=>0,'rate'=>0,'total_hours'=>0];
$attRows = [];
if ($report === 'attendance') {
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM furn_attendance")->fetchAll(PDO::FETCH_COLUMN);
        $dateExpr = in_array('date', $cols) ? 'date' : 'DATE(check_in_time)';
        $s = $pdo->prepare("
            SELECT a.*, $dateExpr as att_date
            FROM furn_attendance a
            WHERE a.employee_id = ?
              AND $dateExpr BETWEEN ? AND ?
            ORDER BY $dateExpr DESC
        ");
        $s->execute([$employeeId, $dateFrom, $dateTo]);
        $attRows = $s->fetchAll(PDO::FETCH_ASSOC);
        foreach ($attRows as $r) {
            $attSummary['total']++;
            if (in_array($r['status'], ['present','late'])) $attSummary['present']++;
            if ($r['status'] === 'absent') $attSummary['absent']++;
            if ($r['status'] === 'late')   $attSummary['late']++;
            $attSummary['total_hours'] += floatval($r['hours_worked'] ?? $r['total_hours'] ?? 0);
        }
        $attSummary['rate'] = $attSummary['total'] > 0
            ? round(($attSummary['present'] / $attSummary['total']) * 100, 1) : 0;
        $attSummary['total_hours'] = round($attSummary['total_hours'], 1);
    } catch (PDOException $e) { error_log($e->getMessage()); }
}

// ── 3. MY PAYROLL ──
$paySummary = ['total'=>0,'total_net'=>0,'approved'=>0,'pending'=>0];
$payRows = [];
if ($report === 'payroll') {
    try {
        $s = $pdo->prepare("
            SELECT p.*
            FROM furn_payroll p
            WHERE p.employee_id = ?
              AND STR_TO_DATE(CONCAT(p.year,'-',LPAD(p.month,2,'0'),'-01'),'%Y-%m-%d') BETWEEN ? AND ?
            ORDER BY p.year DESC, p.month DESC
        ");
        $s->execute([$employeeId, $dateFrom, $dateTo]);
        $payRows = $s->fetchAll(PDO::FETCH_ASSOC);
        foreach ($payRows as $r) {
            $paySummary['total']++;
            $paySummary['total_net'] += floatval($r['net_salary']);
            if ($r['status'] === 'approved') $paySummary['approved']++;
            else $paySummary['pending']++;
        }
    } catch (PDOException $e) { error_log($e->getMessage()); }
}

// ── 4. MATERIALS USED ──
$matSummary = ['total_records'=>0,'total_qty'=>0,'total_cost'=>0];
$matRows = [];
if ($report === 'materials') {
    try {
        $s = $pdo->prepare("
            SELECT mu.id, mu.quantity_used, mu.total_cost, mu.created_at,
                   m.name as material_name, m.unit,
                   o.order_number,
                   t.id as task_id
            FROM furn_material_usage mu
            LEFT JOIN furn_materials m ON mu.material_id = m.id
            LEFT JOIN furn_production_tasks t ON mu.task_id = t.id
            LEFT JOIN furn_orders o ON t.order_id = o.id
            WHERE t.employee_id = ?
              AND DATE(mu.created_at) BETWEEN ? AND ?
            ORDER BY mu.created_at DESC
        ");
        $s->execute([$employeeId, $dateFrom, $dateTo]);
        $matRows = $s->fetchAll(PDO::FETCH_ASSOC);
        foreach ($matRows as $r) {
            $matSummary['total_records']++;
            $matSummary['total_qty']  += floatval($r['quantity_used']);
            $matSummary['total_cost'] += floatval($r['total_cost']);
        }
        $matSummary['total_qty']  = round($matSummary['total_qty'], 2);
        $matSummary['total_cost'] = round($matSummary['total_cost'], 2);
    } catch (PDOException $e) { error_log($e->getMessage()); }
}

// ── 5. MY RATINGS ──
$ratSummary = ['total'=>0,'avg'=>0,'five_star'=>0,'one_star'=>0];
$ratRows = [];
if ($report === 'ratings') {
    try {
        $s = $pdo->prepare("
            SELECT r.rating, r.review_text, r.created_at,
                   o.order_number, o.furniture_name,
                   CONCAT(c.first_name,' ',c.last_name) as customer_name
            FROM furn_ratings r
            LEFT JOIN furn_orders o ON r.order_id = o.id
            LEFT JOIN furn_users c ON r.customer_id = c.id
            WHERE r.employee_id = ?
              AND DATE(r.created_at) BETWEEN ? AND ?
            ORDER BY r.created_at DESC
        ");
        $s->execute([$employeeId, $dateFrom, $dateTo]);
        $ratRows = $s->fetchAll(PDO::FETCH_ASSOC);
        $sum = 0;
        foreach ($ratRows as $r) {
            $ratSummary['total']++;
            $sum += intval($r['rating']);
            if ($r['rating'] == 5) $ratSummary['five_star']++;
            if ($r['rating'] == 1) $ratSummary['one_star']++;
        }
        $ratSummary['avg'] = $ratSummary['total'] > 0 ? round($sum / $ratSummary['total'], 1) : 0;
    } catch (PDOException $e) { error_log($e->getMessage()); }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reports - FurnitureCraft</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/css/admin-responsive.css">
    <style>
        /* ── Tab navigation ── */
        .rpt-nav { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:24px; }
        .rpt-tab {
            padding:10px 18px; border-radius:8px; border:2px solid #e0e0e0;
            background:#fff; color:#555; font-size:13px; font-weight:600;
            cursor:pointer; text-decoration:none; transition:all .15s;
            display:inline-flex; align-items:center; gap:7px;
        }
        .rpt-tab:hover  { border-color:#27AE60; color:#27AE60; }
        .rpt-tab.active { background:#27AE60; border-color:#27AE60; color:#fff; }

        /* ── Date filter bar ── */
        .rpt-filter {
            background:#fff; border-radius:10px; padding:16px 20px;
            margin-bottom:20px; box-shadow:0 2px 8px rgba(0,0,0,0.07);
            display:flex; align-items:center; gap:12px; flex-wrap:wrap;
        }
        .rpt-filter label { font-size:13px; font-weight:600; color:#555; }
        .rpt-filter input[type=date] {
            padding:8px 12px; border:1.5px solid #ddd; border-radius:8px;
            font-size:13px; font-family:inherit; outline:none;
        }
        .rpt-filter input[type=date]:focus { border-color:#27AE60; }
        .rpt-filter .btn-apply {
            padding:9px 20px; background:#27AE60; color:#fff;
            border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer;
        }
        .rpt-filter .btn-apply:hover { background:#219150; }
        .rpt-filter .btn-shortcut {
            padding:9px 16px; background:#f0f0f0; color:#555;
            border-radius:8px; font-size:13px; font-weight:600; text-decoration:none;
        }
        .rpt-filter .btn-shortcut:hover { background:#e0e0e0; }

        /* ── Summary cards ── */
        .sum-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:14px; margin-bottom:22px; }
        .sum-card {
            background:#fff; border-radius:10px; padding:16px 18px;
            box-shadow:0 2px 8px rgba(0,0,0,0.07); border-left:4px solid #ccc;
        }
        .sum-val { font-size:22px; font-weight:700; color:#2c3e50; line-height:1.2; }
        .sum-lbl { font-size:12px; color:#888; margin-top:3px; }

        /* ── Data table ── */
        .rpt-table { width:100%; border-collapse:collapse; font-size:13px; }
        .rpt-table thead tr { background:#2c3e50; color:#fff; }
        .rpt-table th { padding:11px 12px; text-align:left; font-size:12px; font-weight:600; white-space:nowrap; }
        .rpt-table td { padding:10px 12px; border-bottom:1px solid #f0f0f0; color:#444; }
        .rpt-table tbody tr:hover { background:#fafbfc; }

        /* ── Empty state ── */
        .empty-rpt { text-align:center; padding:50px 20px; color:#aaa; }
        .empty-rpt i { font-size:40px; display:block; margin-bottom:12px; }

        /* ── Progress bar ── */
        .prog-bar-wrap { display:flex; align-items:center; gap:8px; }
        .prog-bar-bg { flex:1; background:#f0f0f0; border-radius:6px; height:8px; min-width:80px; }
        .prog-bar-fill { height:100%; border-radius:6px; }

        /* ── Star rating ── */
        .stars { color:#f39c12; letter-spacing:1px; }

        /* ── Status badge ── */
        .badge {
            border-radius:12px; padding:3px 10px;
            font-size:11px; font-weight:600; display:inline-block;
        }

        /* ── Print ── */
        @media print {
            .no-print, .rpt-filter, .rpt-nav { display:none !important; }
            body { background:#fff; }
            .main-content { margin:0 !important; padding:10px !important; }
            .sum-card { box-shadow:none; border:1px solid #ddd; }
            .rpt-table thead tr { background:#444 !important; -webkit-print-color-adjust:exact; }
        }
    </style>
</head>
<body>
<button class="mobile-menu-toggle no-print" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>
<div class="sidebar-overlay no-print"></div>
<?php include_once __DIR__ . '/../../includes/employee_sidebar.php'; ?>
<?php $pageTitle = 'My Reports'; include_once __DIR__ . '/../../includes/employee_header.php'; ?>

<div class="main-content">

    <!-- Page heading -->
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
        <div>
            <h1 style="margin:0;font-size:24px;color:#2c3e50;">
                <i class="fas fa-chart-bar" style="color:#27AE60;"></i> My Reports
            </h1>
            <p style="margin:4px 0 0;color:#888;font-size:13px;">
                Period: <?php echo date('M j, Y', strtotime($dateFrom)); ?> &mdash; <?php echo date('M j, Y', strtotime($dateTo)); ?>
            </p>
        </div>
        <button onclick="window.print()" class="no-print"
            style="padding:9px 18px;background:#2c3e50;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
            <i class="fas fa-print"></i> Print Report
        </button>
    </div>

    <!-- Tab navigation -->
    <div class="rpt-nav no-print">
        <?php
        $tabs = [
            ['key'=>'tasks',      'icon'=>'fa-tasks',       'label'=>'My Tasks'],
            ['key'=>'attendance', 'icon'=>'fa-user-check',  'label'=>'My Attendance'],
            ['key'=>'payroll',    'icon'=>'fa-wallet',      'label'=>'My Payroll'],
            ['key'=>'materials',  'icon'=>'fa-boxes',       'label'=>'Materials Used'],
            ['key'=>'ratings',    'icon'=>'fa-star',        'label'=>'My Ratings'],
        ];
        foreach ($tabs as $t): ?>
        <a href="?report=<?php echo $t['key']; ?>&from=<?php echo $dateFrom; ?>&to=<?php echo $dateTo; ?>"
           class="rpt-tab <?php echo $report === $t['key'] ? 'active' : ''; ?>">
            <i class="fas <?php echo $t['icon']; ?>"></i> <?php echo $t['label']; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Date filter -->
    <form method="GET" class="rpt-filter no-print">
        <input type="hidden" name="report" value="<?php echo htmlspecialchars($report); ?>">
        <label>From</label>
        <input type="date" name="from" value="<?php echo $dateFrom; ?>">
        <label>To</label>
        <input type="date" name="to" value="<?php echo $dateTo; ?>">
        <button type="submit" class="btn-apply"><i class="fas fa-filter"></i> Apply</button>
        <a href="?report=<?php echo $report; ?>&from=<?php echo date('Y-m-01'); ?>&to=<?php echo date('Y-m-d'); ?>" class="btn-shortcut">This Month</a>
        <a href="?report=<?php echo $report; ?>&from=<?php echo date('Y-01-01'); ?>&to=<?php echo date('Y-m-d'); ?>" class="btn-shortcut">This Year</a>
    </form>

    <?php
    // Helper: status badge
    function empBadge(string $status): string {
        $map = [
            'completed'  => '#27ae60',
            'in_progress'=> '#3498db',
            'pending'    => '#f39c12',
            'absent'     => '#e74c3c',
            'late'       => '#f39c12',
            'present'    => '#27ae60',
            'approved'   => '#27ae60',
        ];
        $c = $map[strtolower($status)] ?? '#888';
        $label = ucwords(str_replace('_', ' ', $status));
        return "<span class='badge' style='background:{$c}18;color:{$c};'>{$label}</span>";
    }
    ?>

    <!-- ══════════════════════════════════════════ -->
    <!-- TAB 1 — MY TASKS                          -->
    <!-- ══════════════════════════════════════════ -->
    <?php if ($report === 'tasks'): ?>
    <div class="sum-grid">
        <div class="sum-card" style="border-color:#3498db;">
            <div class="sum-val" style="color:#3498db;"><?php echo $taskSummary['total']; ?></div>
            <div class="sum-lbl">Total Tasks</div>
        </div>
        <div class="sum-card" style="border-color:#27ae60;">
            <div class="sum-val" style="color:#27ae60;"><?php echo $taskSummary['completed']; ?></div>
            <div class="sum-lbl">Completed</div>
        </div>
        <div class="sum-card" style="border-color:#3498db;">
            <div class="sum-val" style="color:#3498db;"><?php echo $taskSummary['in_progress']; ?></div>
            <div class="sum-lbl">In Progress</div>
        </div>
        <div class="sum-card" style="border-color:#f39c12;">
            <div class="sum-val" style="color:#f39c12;"><?php echo $taskSummary['pending']; ?></div>
            <div class="sum-lbl">Pending</div>
        </div>
        <div class="sum-card" style="border-color:#27AE60;">
            <div class="sum-val" style="color:#27AE60;"><?php echo $taskSummary['avg_progress']; ?>%</div>
            <div class="sum-lbl">Avg Progress</div>
        </div>
    </div>

    <div class="section-card" style="padding:0;overflow:hidden;">
        <?php if (empty($taskRows)): ?>
        <div class="empty-rpt">
            <i class="fas fa-tasks"></i>
            No tasks found for this period.
        </div>
        <?php else: ?>
        <div class="table-responsive">
        <table class="rpt-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Order</th>
                    <th>Furniture</th>
                    <th>Progress</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Completed</th>
                    <th>Hours</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($taskRows as $i => $r):
                $sc   = ['completed'=>'#27ae60','in_progress'=>'#3498db','pending'=>'#f39c12'][$r['status']] ?? '#888';
                $prog = intval($r['progress']);
            ?>
            <tr>
                <td style="color:#aaa;"><?php echo $i + 1; ?></td>
                <td><strong><?php echo htmlspecialchars($r['order_number'] ?? '—'); ?></strong></td>
                <td><?php echo htmlspecialchars($r['furniture_name'] ?? $r['furniture_type'] ?? '—'); ?></td>
                <td>
                    <div class="prog-bar-wrap">
                        <div class="prog-bar-bg">
                            <div class="prog-bar-fill" style="background:<?php echo $sc; ?>;width:<?php echo $prog; ?>%;"></div>
                        </div>
                        <span style="font-size:12px;font-weight:600;color:<?php echo $sc; ?>;min-width:32px;"><?php echo $prog; ?>%</span>
                    </div>
                </td>
                <td><?php echo empBadge($r['status']); ?></td>
                <td><?php echo date('M j, Y', strtotime($r['created_at'])); ?></td>
                <td><?php echo $r['completed_at'] ? date('M j, Y', strtotime($r['completed_at'])) : '—'; ?></td>
                <td><?php echo $r['actual_hours'] ? number_format($r['actual_hours'], 1) . 'h' : ($r['estimated_hours'] ? number_format($r['estimated_hours'], 1) . 'h est.' : '—'); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ══════════════════════════════════════════ -->
    <!-- TAB 2 — MY ATTENDANCE                     -->
    <!-- ══════════════════════════════════════════ -->
    <?php if ($report === 'attendance'): ?>
    <div class="sum-grid">
        <div class="sum-card" style="border-color:#3498db;">
            <div class="sum-val" style="color:#3498db;"><?php echo $attSummary['total']; ?></div>
            <div class="sum-lbl">Total Records</div>
        </div>
        <div class="sum-card" style="border-color:#27ae60;">
            <div class="sum-val" style="color:#27ae60;"><?php echo $attSummary['present']; ?></div>
            <div class="sum-lbl">Present</div>
        </div>
        <div class="sum-card" style="border-color:#e74c3c;">
            <div class="sum-val" style="color:#e74c3c;"><?php echo $attSummary['absent']; ?></div>
            <div class="sum-lbl">Absent</div>
        </div>
        <div class="sum-card" style="border-color:#f39c12;">
            <div class="sum-val" style="color:#f39c12;"><?php echo $attSummary['late']; ?></div>
            <div class="sum-lbl">Late</div>
        </div>
        <div class="sum-card" style="border-color:#27AE60;">
            <div class="sum-val" style="color:#27AE60;"><?php echo $attSummary['rate']; ?>%</div>
            <div class="sum-lbl">Attendance Rate</div>
        </div>
        <div class="sum-card" style="border-color:#9b59b6;">
            <div class="sum-val" style="color:#9b59b6;"><?php echo $attSummary['total_hours']; ?>h</div>
            <div class="sum-lbl">Total Hours</div>
        </div>
    </div>

    <div class="section-card" style="padding:0;overflow:hidden;">
        <?php if (empty($attRows)): ?>
        <div class="empty-rpt">
            <i class="fas fa-user-check"></i>
            No attendance records found for this period.
        </div>
        <?php else: ?>
        <div class="table-responsive">
        <table class="rpt-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                    <th>Hours</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($attRows as $i => $r): ?>
            <tr>
                <td style="color:#aaa;"><?php echo $i + 1; ?></td>
                <td><?php echo date('M j, Y', strtotime($r['att_date'])); ?></td>
                <td><?php echo !empty($r['check_in_time']) ? date('h:i A', strtotime($r['check_in_time'])) : '—'; ?></td>
                <td><?php echo !empty($r['check_out_time']) ? date('h:i A', strtotime($r['check_out_time'])) : '—'; ?></td>
                <td><?php
                    $hrs = floatval($r['hours_worked'] ?? $r['total_hours'] ?? 0);
                    echo $hrs > 0 ? number_format($hrs, 1) . 'h' : '—';
                ?></td>
                <td><?php echo empBadge($r['status']); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ══════════════════════════════════════════ -->
    <!-- TAB 3 — MY PAYROLL                        -->
    <!-- ══════════════════════════════════════════ -->
    <?php if ($report === 'payroll'): ?>
    <div class="sum-grid">
        <div class="sum-card" style="border-color:#3498db;">
            <div class="sum-val" style="color:#3498db;"><?php echo $paySummary['total']; ?></div>
            <div class="sum-lbl">Total Records</div>
        </div>
        <div class="sum-card" style="border-color:#27ae60;">
            <div class="sum-val" style="color:#27ae60;font-size:17px;">ETB <?php echo number_format($paySummary['total_net'], 0); ?></div>
            <div class="sum-lbl">Total Net Salary</div>
        </div>
        <div class="sum-card" style="border-color:#27ae60;">
            <div class="sum-val" style="color:#27ae60;"><?php echo $paySummary['approved']; ?></div>
            <div class="sum-lbl">Approved</div>
        </div>
        <div class="sum-card" style="border-color:#f39c12;">
            <div class="sum-val" style="color:#f39c12;"><?php echo $paySummary['pending']; ?></div>
            <div class="sum-lbl">Pending</div>
        </div>
    </div>

    <div class="section-card" style="padding:0;overflow:hidden;">
        <?php if (empty($payRows)): ?>
        <div class="empty-rpt">
            <i class="fas fa-wallet"></i>
            No payroll records found for this period.
        </div>
        <?php else: ?>
        <div class="table-responsive">
        <table class="rpt-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Month / Year</th>
                    <th>Basic Salary</th>
                    <th>Deductions</th>
                    <th>Net Salary</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($payRows as $i => $r):
                $basic      = floatval($r['basic_salary'] ?? $r['gross_salary'] ?? $r['net_salary']);
                $deductions = max(0, $basic - floatval($r['net_salary']));
            ?>
            <tr>
                <td style="color:#aaa;"><?php echo $i + 1; ?></td>
                <td><strong><?php echo date('F Y', mktime(0, 0, 0, $r['month'], 1, $r['year'])); ?></strong></td>
                <td>ETB <?php echo number_format($basic, 2); ?></td>
                <td style="color:#e74c3c;">ETB <?php echo number_format($deductions, 2); ?></td>
                <td style="font-weight:700;color:#27ae60;">ETB <?php echo number_format($r['net_salary'], 2); ?></td>
                <td><?php echo empBadge($r['status']); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#f8f9fa;font-weight:700;">
                    <td colspan="4" style="padding:10px 12px;text-align:right;">Total Net:</td>
                    <td style="padding:10px 12px;color:#27ae60;">ETB <?php echo number_format($paySummary['total_net'], 2); ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ══════════════════════════════════════════ -->
    <!-- TAB 4 — MATERIALS USED                    -->
    <!-- ══════════════════════════════════════════ -->
    <?php if ($report === 'materials'): ?>
    <div class="sum-grid">
        <div class="sum-card" style="border-color:#3498db;">
            <div class="sum-val" style="color:#3498db;"><?php echo $matSummary['total_records']; ?></div>
            <div class="sum-lbl">Usage Records</div>
        </div>
        <div class="sum-card" style="border-color:#27AE60;">
            <div class="sum-val" style="color:#27AE60;"><?php echo $matSummary['total_qty']; ?></div>
            <div class="sum-lbl">Total Qty Used</div>
        </div>
        <div class="sum-card" style="border-color:#f39c12;">
            <div class="sum-val" style="color:#f39c12;font-size:17px;">ETB <?php echo number_format($matSummary['total_cost'], 0); ?></div>
            <div class="sum-lbl">Total Cost</div>
        </div>
    </div>

    <div class="section-card" style="padding:0;overflow:hidden;">
        <?php if (empty($matRows)): ?>
        <div class="empty-rpt">
            <i class="fas fa-boxes"></i>
            No material usage records found for this period.
        </div>
        <?php else: ?>
        <div class="table-responsive">
        <table class="rpt-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Material</th>
                    <th>Order</th>
                    <th>Qty Used</th>
                    <th>Unit</th>
                    <th>Cost</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($matRows as $i => $r): ?>
            <tr>
                <td style="color:#aaa;"><?php echo $i + 1; ?></td>
                <td><strong><?php echo htmlspecialchars($r['material_name'] ?? '—'); ?></strong></td>
                <td><?php echo htmlspecialchars($r['order_number'] ?? '—'); ?></td>
                <td><?php echo number_format($r['quantity_used'], 2); ?></td>
                <td style="color:#888;"><?php echo htmlspecialchars($r['unit'] ?? '—'); ?></td>
                <td style="color:#27ae60;font-weight:600;">ETB <?php echo number_format($r['total_cost'], 2); ?></td>
                <td><?php echo date('M j, Y', strtotime($r['created_at'])); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background:#f8f9fa;font-weight:700;">
                    <td colspan="3" style="padding:10px 12px;text-align:right;">Totals:</td>
                    <td style="padding:10px 12px;"><?php echo $matSummary['total_qty']; ?></td>
                    <td></td>
                    <td style="padding:10px 12px;color:#27ae60;">ETB <?php echo number_format($matSummary['total_cost'], 2); ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ══════════════════════════════════════════ -->
    <!-- TAB 5 — MY RATINGS                        -->
    <!-- ══════════════════════════════════════════ -->
    <?php if ($report === 'ratings'): ?>
    <div class="sum-grid">
        <div class="sum-card" style="border-color:#3498db;">
            <div class="sum-val" style="color:#3498db;"><?php echo $ratSummary['total']; ?></div>
            <div class="sum-lbl">Total Ratings</div>
        </div>
        <div class="sum-card" style="border-color:#f39c12;">
            <div class="sum-val" style="color:#f39c12;"><?php echo $ratSummary['avg']; ?> <span style="font-size:16px;">&#9733;</span></div>
            <div class="sum-lbl">Average Rating</div>
        </div>
        <div class="sum-card" style="border-color:#27ae60;">
            <div class="sum-val" style="color:#27ae60;"><?php echo $ratSummary['five_star']; ?></div>
            <div class="sum-lbl">5-Star Reviews</div>
        </div>
        <div class="sum-card" style="border-color:#e74c3c;">
            <div class="sum-val" style="color:#e74c3c;"><?php echo $ratSummary['one_star']; ?></div>
            <div class="sum-lbl">1-Star Reviews</div>
        </div>
    </div>

    <div class="section-card" style="padding:0;overflow:hidden;">
        <?php if (empty($ratRows)): ?>
        <div class="empty-rpt">
            <i class="fas fa-star"></i>
            No ratings found for this period.
        </div>
        <?php else: ?>
        <div class="table-responsive">
        <table class="rpt-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Order</th>
                    <th>Furniture</th>
                    <th>Rating</th>
                    <th>Review</th>
                    <th>Customer</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($ratRows as $i => $r):
                $stars = intval($r['rating']);
                $starHtml = str_repeat('&#9733;', $stars) . str_repeat('&#9734;', 5 - $stars);
            ?>
            <tr>
                <td style="color:#aaa;"><?php echo $i + 1; ?></td>
                <td><strong><?php echo htmlspecialchars($r['order_number'] ?? '—'); ?></strong></td>
                <td><?php echo htmlspecialchars($r['furniture_name'] ?? '—'); ?></td>
                <td>
                    <span class="stars" title="<?php echo $stars; ?> out of 5"><?php echo $starHtml; ?></span>
                    <span style="font-size:12px;color:#888;margin-left:4px;">(<?php echo $stars; ?>)</span>
                </td>
                <td style="max-width:220px;white-space:normal;font-size:12px;color:#666;">
                    <?php echo $r['review_text'] ? htmlspecialchars(mb_strimwidth($r['review_text'], 0, 100, '…')) : '<em style="color:#bbb;">No review</em>'; ?>
                </td>
                <td><?php echo htmlspecialchars($r['customer_name'] ?? '—'); ?></td>
                <td><?php echo date('M j, Y', strtotime($r['created_at'])); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div><!-- /.main-content -->

<script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
