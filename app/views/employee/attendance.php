<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
    header('Location: ' . BASE_URL . '/public/login'); exit();
}
require_once __DIR__ . '/../../../config/db_config.php';
$employeeName = $_SESSION['user_name'] ?? 'Employee User';
$employeeId   = $_SESSION['user_id'];

// ── Ensure disputes table exists with all columns ──
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS furn_attendance_disputes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        attendance_id INT NOT NULL,
        employee_id INT NOT NULL,
        reason TEXT NOT NULL,
        status ENUM('open','resolved') NOT NULL DEFAULT 'open',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        resolved_at TIMESTAMP NULL DEFAULT NULL,
        INDEX(attendance_id), INDEX(employee_id)
    )");
    // Add missing columns if table existed without them
    $cols = $pdo->query("SHOW COLUMNS FROM furn_attendance_disputes")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('reason', $cols))      $pdo->exec("ALTER TABLE furn_attendance_disputes ADD COLUMN reason TEXT NOT NULL DEFAULT ''");
    if (!in_array('resolved_at', $cols)) $pdo->exec("ALTER TABLE furn_attendance_disputes ADD COLUMN resolved_at TIMESTAMP NULL DEFAULT NULL");
} catch (PDOException $e) {}

// ── Handle dispute submission ──
$disputeSuccess = null; $disputeError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'report_dispute') {
    $attId  = (int)($_POST['attendance_id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    if ($attId && $reason) {
        try {
            // Verify this record belongs to this employee
            $chk = $pdo->prepare("SELECT id FROM furn_attendance WHERE id=? AND employee_id=?");
            $chk->execute([$attId, $employeeId]);
            if ($chk->fetch()) {
                // Only one open dispute per record
                $dup = $pdo->prepare("SELECT id FROM furn_attendance_disputes WHERE attendance_id=? AND status='open'");
                $dup->execute([$attId]);
                if ($dup->fetch()) {
                    $disputeError = "A dispute is already open for this record.";
                } else {
                    $pdo->prepare("INSERT INTO furn_attendance_disputes (attendance_id, employee_id, reason) VALUES (?,?,?)")
                        ->execute([$attId, $employeeId, $reason]);
                    $disputeSuccess = "Dispute reported. Your manager will review it.";
                }
            } else {
                $disputeError = "Invalid record.";
            }
        } catch (PDOException $e) { $disputeError = "Error: " . $e->getMessage(); }
    } else {
        $disputeError = "Please provide a reason.";
    }
}

// ── Load open disputes for this employee (to show badge) ──
$openDisputes = [];
try {
    $ds = $pdo->prepare("SELECT attendance_id FROM furn_attendance_disputes WHERE employee_id=? AND status='open'");
    $ds->execute([$employeeId]);
    foreach ($ds->fetchAll(PDO::FETCH_COLUMN) as $aid) $openDisputes[$aid] = true;
} catch (PDOException $e) {}

// ── Fetch last 60 days of attendance ──
$history = [];
try {
    $stmt = $pdo->prepare("
        SELECT a.*, TIME(a.check_in_time) as ci_time,
               TIME(a.check_out_time) as co_time,
               COALESCE(a.overtime_hours, 0) as overtime_hours,
               COALESCE(s.overtime_rate, 0) as overtime_rate
        FROM furn_attendance a
        LEFT JOIN furn_employee_salary s ON s.employee_id = a.employee_id
        WHERE a.employee_id=? AND a.date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
        ORDER BY a.date DESC
    ");
    $stmt->execute([$employeeId]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // check_out_time may not exist yet — fallback without it
    try {
        $stmt = $pdo->prepare("
            SELECT *, DATE(check_in_time) as date, TIME(check_in_time) as ci_time, NULL as co_time
            FROM furn_attendance
            WHERE employee_id=? AND DATE(check_in_time) >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
            ORDER BY DATE(check_in_time) DESC
        ");
        $stmt->execute([$employeeId]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) { error_log($e2->getMessage()); }
}

// ── Build date-keyed map ──
$dateMap = [];
foreach ($history as $r) $dateMap[$r['date']] = $r;

// ── Today's status ──
$today = date('Y-m-d');
$todayRec = $dateMap[$today] ?? null;
$todayStatus = $todayRec['status'] ?? null;

// ── Stats (last 30 days) ──
$stats = ['present'=>0,'absent'=>0,'late'=>0,'half_day'=>0,'total'=>0,'rate'=>0];
foreach ($history as $r) {
    if ($r['date'] < date('Y-m-d', strtotime('-30 days'))) continue;
    $stats['total']++;
    if (isset($stats[$r['status']])) $stats[$r['status']]++;
}
if ($stats['total'] > 0)
    $stats['rate'] = round((($stats['present']+$stats['late']+$stats['half_day']) / $stats['total']) * 100, 1);

$pageTitle = 'My Attendance';
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
        .att-header { background:linear-gradient(135deg,#1a252f 0%,#2C3E50 50%,#27AE60 100%); color:#fff; padding:28px 30px; border-radius:14px; margin-bottom:24px; }
        /* Today status badge */
        .today-badge { display:inline-flex; align-items:center; gap:10px; padding:10px 20px; border-radius:30px; font-size:15px; font-weight:700; }
        /* Calendar strip */
        .cal-strip { display:flex; gap:4px; flex-wrap:wrap; }
        .cal-day { width:32px; height:32px; border-radius:6px; display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:700; cursor:default; position:relative; }
        .cal-day:hover .cal-tooltip { display:block; }
        .cal-tooltip { display:none; position:absolute; bottom:calc(100% + 6px); left:50%; transform:translateX(-50%); background:#2C3E50; color:#fff; font-size:10px; padding:4px 8px; border-radius:6px; white-space:nowrap; z-index:10; }
        .cal-day.present  { background:#27AE60; color:#fff; }
        .cal-day.absent   { background:#E74C3C; color:#fff; }
        .cal-day.late     { background:#F39C12; color:#fff; }
        .cal-day.half_day { background:#9B59B6; color:#fff; }
        .cal-day.none     { background:#F0F2F5; color:#BDC3C7; }
        /* Status badge */
        .s-badge { display:inline-flex; align-items:center; gap:5px; border-radius:20px; padding:4px 12px; font-size:12px; font-weight:700; }
        .s-present  { background:#27AE6018; color:#27AE60; }
        .s-absent   { background:#E74C3C18; color:#E74C3C; }
        .s-late     { background:#F39C1218; color:#F39C12; }
        .s-half_day { background:#9B59B618; color:#9B59B6; }
        /* Legend */
        .legend-dot { width:12px; height:12px; border-radius:3px; display:inline-block; }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>
    <div class="sidebar-overlay"></div>
    <?php include_once __DIR__ . '/../../includes/employee_sidebar.php'; ?>
    <!-- Top Header -->
    <?php 
    $pageTitle = 'Attendance';
    include_once __DIR__ . '/../../includes/employee_header.php'; 
    ?>
        <div class="header-left"><div class="system-status"><i class="fas fa-circle"></i> My Attendance</div></div>
        <div class="header-right">
            <div class="admin-profile">
                <div class="admin-avatar"><?php echo strtoupper(substr($employeeName,0,1)); ?></div>
                <div>
                    <div style="font-weight:600;font-size:14px;"><?php echo htmlspecialchars($employeeName); ?></div>
                    <div class="admin-role-badge" style="background:#27AE60;">EMPLOYEE</div>
                </div>
            </div>
        </div>
    </div>

    <div class="main-content">

        <!-- ── Page Header ── -->
        <div class="att-header">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px;">
                <div>
                    <h1 style="margin:0 0 6px;font-size:26px;"><i class="fas fa-clipboard-user"></i> My Attendance</h1>
                    <p style="margin:0;opacity:.85;font-size:14px;"><?php echo htmlspecialchars($employeeName); ?> &nbsp;|&nbsp; <?php echo date('l, F j, Y'); ?></p>
                </div>
                <div>
                    <?php
                    $todayLabels = [
                        'present'  => ['#27AE60','fa-check-circle','Present Today'],
                        'absent'   => ['#E74C3C','fa-times-circle','Absent Today'],
                        'late'     => ['#F39C12','fa-clock','Late Today'],
                        'half_day' => ['#9B59B6','fa-adjust','Half Day Today'],
                    ];
                    if ($todayStatus && isset($todayLabels[$todayStatus])):
                        [$tc,$ti,$tl] = $todayLabels[$todayStatus];
                    ?>
                    <div class="today-badge" style="background:<?php echo $tc; ?>33;border:2px solid <?php echo $tc; ?>88;">
                        <i class="fas <?php echo $ti; ?>" style="font-size:22px;color:<?php echo $tc; ?>;"></i>
                        <span style="color:#fff;"><?php echo $tl; ?></span>
                    </div>
                    <?php else: ?>
                    <div class="today-badge" style="background:rgba(255,255,255,.15);border:2px solid rgba(255,255,255,.3);">
                        <i class="fas fa-question-circle" style="font-size:22px;color:#BDC3C7;"></i>
                        <span style="color:#fff;opacity:.8;">Not Marked Yet</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ── Stats Cards ── -->
        <div class="stats-grid" style="margin-bottom:24px;">
            <div class="stat-card" style="border-left:4px solid #27AE60;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div><div class="stat-value"><?php echo $stats['present']; ?></div><div class="stat-label">Days Present</div></div>
                    <i class="fas fa-user-check" style="font-size:30px;color:#27AE60;opacity:.7;"></i>
                </div>
            </div>
            <div class="stat-card" style="border-left:4px solid #E74C3C;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div><div class="stat-value"><?php echo $stats['absent']; ?></div><div class="stat-label">Days Absent</div></div>
                    <i class="fas fa-user-times" style="font-size:30px;color:#E74C3C;opacity:.7;"></i>
                </div>
            </div>
            <div class="stat-card" style="border-left:4px solid #F39C12;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div><div class="stat-value"><?php echo $stats['late']; ?></div><div class="stat-label">Late Arrivals</div></div>
                    <i class="fas fa-user-clock" style="font-size:30px;color:#F39C12;opacity:.7;"></i>
                </div>
            </div>
            <div class="stat-card" style="border-left:4px solid #9B59B6;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div><div class="stat-value"><?php echo $stats['rate']; ?>%</div><div class="stat-label">Attendance Rate</div></div>
                    <i class="fas fa-chart-pie" style="font-size:30px;color:#9B59B6;opacity:.7;"></i>
                </div>
            </div>
        </div>

        <!-- ── 30-Day Calendar Strip ── -->
        <div class="section-card" style="margin-bottom:24px;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:16px;">
                <h2 class="section-title" style="margin:0;"><i class="fas fa-calendar-alt" style="color:#3498DB;"></i> 30-Day Overview</h2>
                <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;font-size:12px;">
                    <span><span class="legend-dot" style="background:#27AE60;"></span> Present</span>
                    <span><span class="legend-dot" style="background:#E74C3C;"></span> Absent</span>
                    <span><span class="legend-dot" style="background:#F39C12;"></span> Late</span>
                    <span><span class="legend-dot" style="background:#9B59B6;"></span> Half Day</span>
                    <span><span class="legend-dot" style="background:#F0F2F5;"></span> No Record</span>
                </div>
            </div>
            <div class="cal-strip">
            <?php
            for ($i = 29; $i >= 0; $i--) {
                $d = date('Y-m-d', strtotime("-$i days"));
                $rec = $dateMap[$d] ?? null;
                $st  = $rec['status'] ?? 'none';
                $label = date('M d', strtotime($d));
                $dayNum = date('d', strtotime($d));
                $isToday = ($d === $today);
                $outline = $isToday ? 'outline:2px solid #2C3E50;outline-offset:2px;' : '';
                echo "<div class='cal-day $st' style='$outline' title='$label: ".ucfirst(str_replace('_',' ',$st))."'>
                        $dayNum
                        <div class='cal-tooltip'>$label<br>".ucfirst(str_replace('_',' ',$st))."</div>
                      </div>";
            }
            ?>
            </div>
        </div>

        <!-- ── Attendance History Table ── -->
        <div class="section-card">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:16px;">
                <h2 class="section-title" style="margin:0;"><i class="fas fa-history" style="color:#2C3E50;"></i> Attendance History</h2>
                <span style="font-size:13px;color:#95A5A6;"><?php echo count($history); ?> records (last 60 days)</span>
            </div>

            <div style="background:#EBF5FB;border:1px solid #AED6F1;border-radius:8px;padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:10px;font-size:13px;color:#1A5276;">
                <i class="fas fa-info-circle" style="font-size:16px;color:#3498DB;flex-shrink:0;"></i>
                Your attendance is marked by your manager. If you spot an error, click <strong style="margin:0 4px;">Report Error</strong> on that row.
            </div>
            <?php if ($disputeSuccess): ?>
            <div style="background:#EAFAF1;border:1px solid #A9DFBF;border-radius:8px;padding:10px 16px;margin-bottom:12px;display:flex;align-items:center;gap:10px;font-size:13px;color:#1E8449;">
                <i class="fas fa-check-circle" style="color:#27AE60;"></i> <?php echo htmlspecialchars($disputeSuccess); ?>
            </div>
            <?php endif; ?>
            <?php if ($disputeError): ?>
            <div style="background:#FDEDEC;border:1px solid #F1948A;border-radius:8px;padding:10px 16px;margin-bottom:12px;display:flex;align-items:center;gap:10px;font-size:13px;color:#922B21;">
                <i class="fas fa-exclamation-circle" style="color:#E74C3C;"></i> <?php echo htmlspecialchars($disputeError); ?>
            </div>
            <?php endif; ?>

            <?php if (empty($history)): ?>
                <div style="text-align:center;padding:50px 20px;color:#95A5A6;">
                    <i class="fas fa-calendar-times" style="font-size:48px;opacity:.3;display:block;margin-bottom:16px;"></i>
                    <div style="font-size:15px;font-weight:600;">No attendance records yet</div>
                    <div style="font-size:13px;margin-top:6px;">Your manager will mark your attendance daily.</div>
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Day</th>
                            <th>Status</th>
                            <th>Check-In</th>
                            <th>Check-Out</th>
                            <th>OT Hours</th>
                            <th>OT Rate</th>
                            <th>OT Pay</th>
                            <th>Notes</th>
                            <th>Report</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($history as $rec):
                        $st = $rec['status'];
                        $icons = ['present'=>'fa-check-circle','absent'=>'fa-times-circle','late'=>'fa-clock','half_day'=>'fa-adjust'];
                        $labels = ['present'=>'Present','absent'=>'Absent','late'=>'Late','half_day'=>'Half Day'];
                        $isToday = ($rec['date'] === $today);
                    ?>
                    <tr style="<?php echo $isToday ? 'background:#F0FFF4;font-weight:600;' : ''; ?>">
                        <td>
                            <?php echo date('M d, Y', strtotime($rec['date'])); ?>
                            <?php if ($isToday): ?>
                            <span style="background:#27AE60;color:#fff;border-radius:10px;padding:1px 8px;font-size:10px;font-weight:700;margin-left:6px;">TODAY</span>
                            <?php endif; ?>
                        </td>
                        <td style="color:#7F8C8D;font-size:13px;"><?php echo date('l', strtotime($rec['date'])); ?></td>
                        <td>
                            <span class="s-badge s-<?php echo $st; ?>">
                                <i class="fas <?php echo $icons[$st] ?? 'fa-circle'; ?>"></i>
                                <?php echo $labels[$st] ?? ucfirst($st); ?>
                            </span>
                        </td>
                        <td style="font-size:13px;color:#7F8C8D;">
                            <?php echo !empty($rec['ci_time']) ? date('h:i A', strtotime($rec['ci_time'])) : '—'; ?>
                        </td>
                        <td style="font-size:13px;color:#7F8C8D;">
                            <?php echo !empty($rec['co_time']) ? date('h:i A', strtotime($rec['co_time'])) : '—'; ?>
                        </td>
                        <td style="font-size:13px;font-weight:600;color:<?php echo ($rec['overtime_hours']??0) > 0 ? '#E67E22' : '#BDC3C7'; ?>;">
                            <?php echo ($rec['overtime_hours']??0) > 0 ? number_format($rec['overtime_hours'],2).' hrs' : '—'; ?>
                        </td>
                        <td style="font-size:13px;color:#7F8C8D;">
                            <?php echo ($rec['overtime_rate']??0) > 0 ? 'ETB '.number_format($rec['overtime_rate'],2).'/hr' : '—'; ?>
                        </td>
                        <td style="font-size:13px;font-weight:600;color:<?php echo ($rec['overtime_hours']??0) > 0 ? '#27AE60' : '#BDC3C7'; ?>;">
                            <?php
                            $otPay = ($rec['overtime_hours']??0) * ($rec['overtime_rate']??0);
                            echo $otPay > 0 ? 'ETB '.number_format($otPay,2) : '—';
                            ?>
                        </td>
                        <td style="font-size:13px;color:#7F8C8D;"><?php echo htmlspecialchars($rec['notes'] ?: '—'); ?></td>
                        <td>
                            <?php if (isset($openDisputes[$rec['id']])): ?>
                                <span style="display:inline-flex;align-items:center;gap:5px;background:#FEF9E7;border:1px solid #F39C12;color:#E67E22;border-radius:20px;padding:4px 10px;font-size:11px;font-weight:700;">
                                    <i class="fas fa-clock"></i> Pending
                                </span>
                            <?php else: ?>
                                <button type="button"
                                    onclick="openDisputeModal(<?php echo $rec['id']; ?>, '<?php echo date('M d, Y', strtotime($rec['date'])); ?>', '<?php echo addslashes($labels[$st] ?? ucfirst($st)); ?>')"
                                    style="padding:5px 12px;background:#E74C3C18;color:#E74C3C;border:1.5px solid #E74C3C55;border-radius:8px;font-size:11px;font-weight:700;cursor:pointer;white-space:nowrap;">
                                    <i class="fas fa-flag"></i> Report Error
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- end main-content -->

    <!-- ── Dispute Modal ── -->
    <div id="disputeModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:14px;padding:28px;width:100%;max-width:460px;margin:20px;box-shadow:0 20px 60px rgba(0,0,0,.3);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h3 style="margin:0;font-size:17px;color:#2C3E50;"><i class="fas fa-flag" style="color:#E74C3C;margin-right:8px;"></i> Report Attendance Error</h3>
                <button onclick="closeDisputeModal()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#95A5A6;">&times;</button>
            </div>
            <div id="disputeRecordInfo" style="background:#F8F9FA;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:#555;"></div>
            <form method="POST">
                <input type="hidden" name="action" value="report_dispute">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? $_SESSION['csrf_token'] ?? ''); ?>">
                <input type="hidden" name="attendance_id" id="disputeAttId">
                <div style="margin-bottom:16px;">
                    <label style="font-size:13px;font-weight:600;color:#2C3E50;display:block;margin-bottom:6px;">Describe the error <span style="color:#E74C3C;">*</span></label>
                    <textarea name="reason" rows="4" required placeholder="e.g. I was present but marked absent. I arrived at 8:30 AM..."
                        style="width:100%;padding:10px 12px;border:1.5px solid #E0E0E0;border-radius:8px;font-family:inherit;font-size:13px;resize:vertical;outline:none;box-sizing:border-box;"
                        onfocus="this.style.borderColor='#E74C3C'" onblur="this.style.borderColor='#E0E0E0'"></textarea>
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end;">
                    <button type="button" onclick="closeDisputeModal()" style="padding:10px 20px;background:#F0F2F5;color:#555;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">Cancel</button>
                    <button type="submit" style="padding:10px 20px;background:#E74C3C;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;"><i class="fas fa-paper-plane"></i> Submit Report</button>
                </div>
            </form>
        </div>
    </div>

    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
    <script>
    function openDisputeModal(attId, date, status) {
        document.getElementById('disputeAttId').value = attId;
        document.getElementById('disputeRecordInfo').innerHTML =
            '<strong>Date:</strong> ' + date + ' &nbsp;|&nbsp; <strong>Marked Status:</strong> ' + status;
        const modal = document.getElementById('disputeModal');
        modal.style.display = 'flex';
    }
    function closeDisputeModal() {
        document.getElementById('disputeModal').style.display = 'none';
    }
    document.getElementById('disputeModal').addEventListener('click', function(e) {
        if (e.target === this) closeDisputeModal();
    });
    </script>
