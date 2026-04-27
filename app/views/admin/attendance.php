<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . BASE_URL . '/public/login'); exit();
}
require_once __DIR__ . '/../../../config/db_config.php';
$adminName = $_SESSION['user_name'] ?? 'Admin';

// ── Fetch employees ──
$employees = [];
try {
    $stmt = $pdo->query("SELECT id, first_name, last_name, email, '' as profile_image, 'Employee' as position FROM furn_users WHERE role='employee' ORDER BY first_name, last_name");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    try {
        $stmt2 = $pdo->query("SELECT id, first_name, last_name, email, COALESCE(profile_image,'') as profile_image, COALESCE(position,'Employee') as position FROM furn_users WHERE role='employee' ORDER BY first_name, last_name");
        $employees = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {}
} catch (PDOException $e) {}

// ── Today's attendance map ──
$todayMap = [];
try {
    $stmt = $pdo->prepare("SELECT *, TIME(check_in_time) as ci_time, TIME(check_out_time) as co_time FROM furn_attendance WHERE date=?");
    $stmt->execute([date('Y-m-d')]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $todayMap[$r['employee_id']] = $r;
} catch (PDOException $e) {
    try {
        $stmt = $pdo->prepare("SELECT *, DATE(check_in_time) as date, TIME(check_in_time) as ci_time, TIME(check_out_time) as co_time FROM furn_attendance WHERE DATE(check_in_time)=?");
        $stmt->execute([date('Y-m-d')]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $todayMap[$r['employee_id']] = $r;
    } catch (PDOException $e2) {}
}

// ── Today stats ──
$stats = ['present'=>0,'absent'=>0,'late'=>0,'half_day'=>0,'total'=>count($employees),'rate'=>0];
try {
    $today = date('Y-m-d');
    $rows = $pdo->query("SELECT status, COUNT(*) as cnt FROM furn_attendance WHERE date='$today' GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) { if (isset($stats[$r['status']])) $stats[$r['status']] = (int)$r['cnt']; }
    if ($stats['total'] > 0) $stats['rate'] = round((($stats['present']+$stats['late']+$stats['half_day']) / $stats['total']) * 100, 1);
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Overview - FurnitureCraft Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/css/admin-responsive.css">
    <style>
        .att-header { background:linear-gradient(135deg,#1a252f 0%,#2C3E50 50%,#8E44AD 100%); color:#fff; padding:28px 30px; border-radius:14px; margin-bottom:24px; }
        .stat-pill { display:inline-flex;align-items:center;gap:8px;padding:8px 16px;border-radius:30px;font-size:13px;font-weight:600; }
        .tab-nav  { display:flex;gap:4px;background:#F0F2F5;border-radius:10px;padding:4px;margin-bottom:24px; }
        .tab-btn  { flex:1;padding:10px 16px;border:none;border-radius:8px;font-family:inherit;font-size:13px;font-weight:600;cursor:pointer;background:transparent;color:#7F8C8D;transition:all .2s; }
        .tab-btn.active { background:#fff;color:#2C3E50;box-shadow:0 2px 8px rgba(0,0,0,0.1); }
        .tab-pane { display:none; }
        .tab-pane.active { display:block; }
        .emp-avatar-placeholder { width:36px;height:36px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;color:#fff;flex-shrink:0; }
        .s-badge { display:inline-flex;align-items:center;gap:5px;border-radius:20px;padding:4px 12px;font-size:12px;font-weight:700; }
        .s-present  { background:#27AE6018;color:#27AE60; }
        .s-absent   { background:#E74C3C18;color:#E74C3C; }
        .s-late     { background:#F39C1218;color:#F39C12; }
        .s-half_day { background:#9B59B618;color:#9B59B6; }
        .view-only-banner { background:#EBF5FB;border:1px solid #AED6F1;border-radius:8px;padding:10px 16px;margin-bottom:20px;display:flex;align-items:center;gap:10px;font-size:13px;color:#1A5276; }
        @media print { .no-print { display:none !important; } }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle no-print" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>
    <div class="sidebar-overlay no-print"></div>
    
    <!-- Sidebar -->
    <?php include_once __DIR__ . '/../../includes/admin_sidebar.php'; ?>

    <!-- Top Header -->
    <?php 
    $pageTitle = 'Attendance';
    include_once __DIR__ . '/../../includes/admin_header.php'; 
    ?>

    <div class="main-content">

        <!-- Page Header -->
        <div class="att-header no-print">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px;">
                <div>
                    <h1 style="margin:0 0 6px;font-size:26px;"><i class="fas fa-clipboard-user"></i> Attendance Overview</h1>
                    <p style="margin:0;opacity:.85;font-size:14px;"><?php echo date('l, F j, Y'); ?> &nbsp;|&nbsp; View-only — attendance is marked by managers</p>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                    <span class="stat-pill" style="background:rgba(39,174,96,.25);color:#A9DFBF;"><i class="fas fa-check-circle"></i> <?php echo $stats['present']; ?> Present</span>
                    <span class="stat-pill" style="background:rgba(231,76,60,.25);color:#F1948A;"><i class="fas fa-times-circle"></i> <?php echo $stats['absent']; ?> Absent</span>
                    <span class="stat-pill" style="background:rgba(243,156,18,.25);color:#FAD7A0;"><i class="fas fa-clock"></i> <?php echo $stats['late']; ?> Late</span>
                    <span class="stat-pill" style="background:rgba(155,89,182,.25);color:#D7BDE2;"><i class="fas fa-adjust"></i> <?php echo $stats['half_day']; ?> Half-Day</span>
                    <button onclick="window.print()" style="padding:10px 18px;background:rgba(255,255,255,.15);color:#fff;border:2px solid rgba(255,255,255,.4);border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid no-print" style="margin-bottom:24px;">
            <div class="stat-card" style="border-left:4px solid #27AE60;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div><div class="stat-value"><?php echo $stats['present']; ?></div><div class="stat-label">Present Today</div></div>
                    <i class="fas fa-user-check" style="font-size:30px;color:#27AE60;opacity:.7;"></i>
                </div>
            </div>
            <div class="stat-card" style="border-left:4px solid #E74C3C;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div><div class="stat-value"><?php echo $stats['absent']; ?></div><div class="stat-label">Absent Today</div></div>
                    <i class="fas fa-user-times" style="font-size:30px;color:#E74C3C;opacity:.7;"></i>
                </div>
            </div>
            <div class="stat-card" style="border-left:4px solid #F39C12;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div><div class="stat-value"><?php echo $stats['late']; ?></div><div class="stat-label">Late Arrivals</div></div>
                    <i class="fas fa-user-clock" style="font-size:30px;color:#F39C12;opacity:.7;"></i>
                </div>
            </div>
            <div class="stat-card" style="border-left:4px solid #8E44AD;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div><div class="stat-value"><?php echo $stats['rate']; ?>%</div><div class="stat-label">Attendance Rate</div></div>
                    <i class="fas fa-chart-pie" style="font-size:30px;color:#8E44AD;opacity:.7;"></i>
                </div>
            </div>
        </div>

        <!-- Tab Nav -->
        <div class="tab-nav no-print">
            <button class="tab-btn active" onclick="switchTab('tab-today',this)">
                <i class="fas fa-calendar-day"></i> Today's Attendance
            </button>
            <button class="tab-btn" onclick="switchTab('tab-records',this)">
                <i class="fas fa-history"></i> All Records
            </button>
            <button class="tab-btn" onclick="switchTab('tab-summary',this)">
                <i class="fas fa-chart-bar"></i> Monthly Summary
            </button>
        </div>

        <!-- ══ TAB 1: TODAY'S ATTENDANCE ══ -->
        <div id="tab-today" class="tab-pane active">
        <div class="section-card">
            <div class="view-only-banner">
                <i class="fas fa-eye" style="font-size:16px;color:#3498DB;flex-shrink:0;"></i>
                View-only. Attendance is marked by managers. Go to <strong style="margin:0 4px;">All Records</strong> to filter by date or employee.
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:16px;">
                <h2 class="section-title" style="margin:0;"><i class="fas fa-calendar-day" style="color:#8E44AD;"></i> Today — <?php echo date('l, F j, Y'); ?></h2>
                <span style="font-size:13px;color:#7F8C8D;"><?php echo count($employees); ?> employees</span>
            </div>
            <?php if (empty($employees)): ?>
                <p style="text-align:center;color:#95A5A6;padding:40px 0;">No employees found.</p>
            <?php else: ?>
            <div class="table-responsive">
            <table class="data-table">
                <thead><tr>
                    <th>#</th><th>Employee</th><th>Position</th><th>Status</th><th>Check-In</th><th>Check-Out</th><th>OT Hours</th><th>Notes</th>
                </tr></thead>
                <tbody>
                <?php
                $avatarColors = ['#3498DB','#27AE60','#E74C3C','#9B59B6','#F39C12','#1ABC9C','#E67E22','#2980B9'];
                $statusIcons  = ['present'=>'fa-check-circle','absent'=>'fa-times-circle','late'=>'fa-clock','half_day'=>'fa-adjust'];
                $statusLabels = ['present'=>'Present','absent'=>'Absent','late'=>'Late','half_day'=>'Half Day'];
                foreach ($employees as $i => $emp):
                    $empId  = $emp['id'];
                    $rec    = $todayMap[$empId] ?? null;
                    $st     = $rec['status'] ?? null;
                    $ci     = $rec['ci_time'] ?? null;
                    $co     = !empty($rec['co_time']) ? date('h:i A', strtotime($rec['co_time'])) : null;
                    $note   = $rec['notes'] ?? '';
                    $initials = strtoupper(substr($emp['first_name'],0,1).substr($emp['last_name'],0,1));
                    $avatarColor = $avatarColors[$empId % count($avatarColors)];
                ?>
                <tr style="<?php echo $st ? 'border-left:3px solid '.(['present'=>'#27AE60','absent'=>'#E74C3C','late'=>'#F39C12','half_day'=>'#9B59B6'][$st]??'#ccc').';' : ''; ?>">
                    <td style="color:#95A5A6;font-size:13px;font-weight:600;"><?php echo $i+1; ?></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div class="emp-avatar-placeholder" style="background:<?php echo $avatarColor; ?>;"><?php echo $initials; ?></div>
                            <div>
                                <div style="font-weight:600;font-size:13px;"><?php echo htmlspecialchars($emp['first_name'].' '.$emp['last_name']); ?></div>
                                <div style="font-size:11px;color:#95A5A6;"><?php echo htmlspecialchars($emp['email']); ?></div>
                            </div>
                        </div>
                    </td>
                    <td><span style="background:#8E44AD18;color:#8E44AD;border-radius:12px;padding:2px 10px;font-size:11px;font-weight:600;"><?php echo htmlspecialchars($emp['position']); ?></span></td>
                    <td>
                        <?php if ($st): ?>
                        <span class="s-badge s-<?php echo $st; ?>">
                            <i class="fas <?php echo $statusIcons[$st] ?? 'fa-circle'; ?>"></i>
                            <?php echo $statusLabels[$st] ?? ucfirst($st); ?>
                        </span>
                        <?php else: ?>
                        <span style="color:#BDC3C7;font-size:13px;">Not marked</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:13px;color:#7F8C8D;"><?php echo $ci ? date('h:i A', strtotime($ci)) : '—'; ?></td>
                    <td style="font-size:13px;color:#7F8C8D;"><?php echo $co ?: '—'; ?></td>
                    <td style="font-size:13px;font-weight:600;color:<?php echo ($rec['overtime_hours']??0) > 0 ? '#E67E22' : '#BDC3C7'; ?>;">
                        <?php echo ($rec['overtime_hours']??0) > 0 ? number_format($rec['overtime_hours'],2).' hrs' : '—'; ?>
                    </td>
                    <td style="font-size:12px;color:#7F8C8D;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars($note ?: '—'); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>
        </div><!-- end tab-today -->

        <!-- ══ TAB 2: ALL RECORDS ══ -->
        <div id="tab-records" class="tab-pane">
        <div class="section-card">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:16px;">
                <h2 class="section-title" style="margin:0;"><i class="fas fa-history" style="color:#3498DB;"></i> All Attendance Records</h2>
                <span id="adm-rec-count" style="font-size:13px;color:#95A5A6;"></span>
            </div>

            <!-- JS live filters: date + status only -->
            <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px;padding:16px;background:#F8F9FA;border-radius:10px;align-items:flex-end;">
                <div>
                    <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px;">Date</label>
                    <input type="date" id="adm-filter-date" class="form-control" style="min-width:150px;" oninput="applyAdminFilters()">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px;">Status</label>
                    <select id="adm-filter-status" class="form-control" style="min-width:150px;" onchange="applyAdminFilters()">
                        <option value="">All Statuses</option>
                        <option value="present">Present</option>
                        <option value="absent">Absent</option>
                        <option value="late">Late</option>
                        <option value="half_day">Half Day</option>
                    </select>
                </div>
                <button type="button" onclick="clearAdminFilters()" style="padding:9px 16px;background:#95A5A6;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
                    <i class="fas fa-redo"></i> Clear
                </button>
            </div>

            <?php
            $allAdminRecords = [];
            try {
                $stmt = $pdo->query("
                    SELECT a.id, a.date as att_date, a.status,
                           TIME(a.check_in_time) as ci_time,
                           a.check_out_time, a.notes,
                           COALESCE(a.overtime_hours, 0) as overtime_hours,
                           COALESCE(s.overtime_rate, 0) as overtime_rate,
                           u.first_name, u.last_name
                    FROM furn_attendance a
                    LEFT JOIN furn_users u ON a.employee_id = u.id
                    LEFT JOIN furn_employee_salary s ON s.employee_id = a.employee_id
                    ORDER BY a.date DESC, u.first_name
                    LIMIT 500
                ");
                $allAdminRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {}
            ?>

            <?php if (empty($allAdminRecords)): ?>
                <div style="text-align:center;padding:50px 20px;color:#95A5A6;">
                    <i class="fas fa-clipboard-list" style="font-size:48px;opacity:.3;display:block;margin-bottom:16px;"></i>
                    <div style="font-size:16px;font-weight:600;margin-bottom:8px;">No records found</div>
                    <div style="font-size:13px;">No attendance has been recorded yet.</div>
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="data-table" id="adm-records-table">
                    <thead><tr>
                        <th>Date</th><th>Employee</th><th>Status</th><th>Check-In</th><th>Check-Out</th><th>OT Hours</th><th>OT Rate</th><th>OT Pay</th><th>Notes</th>
                    </tr></thead>
                    <tbody id="adm-records-tbody">
                    <?php foreach ($allAdminRecords as $rec):
                        $sc = ['present'=>'#27AE60','absent'=>'#E74C3C','late'=>'#F39C12','half_day'=>'#9B59B6'][$rec['status']] ?? '#7F8C8D';
                        $si = ['present'=>'fa-check-circle','absent'=>'fa-times-circle','late'=>'fa-clock','half_day'=>'fa-adjust'][$rec['status']] ?? 'fa-circle';
                        $sl = ['present'=>'Present','absent'=>'Absent','late'=>'Late','half_day'=>'Half Day'][$rec['status']] ?? ucfirst($rec['status']);
                    ?>
                    <tr data-status="<?php echo htmlspecialchars($rec['status']); ?>"
                        data-date="<?php echo htmlspecialchars($rec['att_date']); ?>">
                        <td style="font-weight:600;"><?php echo date('M d, Y', strtotime($rec['att_date'])); ?></td>
                        <td><?php echo htmlspecialchars(($rec['first_name']??'').' '.($rec['last_name']??'')); ?></td>
                        <td>
                            <span style="display:inline-flex;align-items:center;gap:5px;background:<?php echo $sc; ?>18;color:<?php echo $sc; ?>;border-radius:20px;padding:4px 12px;font-size:12px;font-weight:700;">
                                <i class="fas <?php echo $si; ?>"></i> <?php echo $sl; ?>
                            </span>
                        </td>
                        <td style="font-size:13px;color:#7F8C8D;"><?php echo !empty($rec['ci_time']) ? date('h:i A', strtotime($rec['ci_time'])) : '—'; ?></td>
                        <td style="font-size:13px;color:#7F8C8D;"><?php echo !empty($rec['check_out_time']) ? date('h:i A', strtotime($rec['check_out_time'])) : '—'; ?></td>
                        <td style="font-size:13px;font-weight:600;color:<?php echo $rec['overtime_hours'] > 0 ? '#E67E22' : '#BDC3C7'; ?>;">
                            <?php echo $rec['overtime_hours'] > 0 ? number_format($rec['overtime_hours'], 2).' hrs' : '—'; ?>
                        </td>
                        <td style="font-size:13px;color:#7F8C8D;">
                            <?php echo $rec['overtime_rate'] > 0 ? 'ETB '.number_format($rec['overtime_rate'],2).'/hr' : '—'; ?>
                        </td>
                        <td style="font-size:13px;font-weight:600;color:<?php echo $rec['overtime_hours'] > 0 ? '#27AE60' : '#BDC3C7'; ?>;">
                            <?php
                            $otPay = $rec['overtime_hours'] * $rec['overtime_rate'];
                            echo $otPay > 0 ? 'ETB '.number_format($otPay,2) : '—';
                            ?>
                        </td>
                        <td style="font-size:12px;color:#7F8C8D;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars($rec['notes'] ?? '—'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div id="adm-no-results" style="display:none;text-align:center;padding:40px 20px;color:#95A5A6;">
                <i class="fas fa-search" style="font-size:36px;opacity:.3;display:block;margin-bottom:12px;"></i>
                <div style="font-size:15px;font-weight:600;">No records match the filter</div>
            </div>
            <?php endif; ?>
        </div>
        </div><!-- end tab-records -->

        <!-- ══ TAB 3: MONTHLY SUMMARY ══ -->
        <div id="tab-summary" class="tab-pane">
        <div class="section-card">
            <h2 class="section-title" style="margin-bottom:20px;"><i class="fas fa-chart-bar" style="color:#8E44AD;"></i> 30-Day Employee Summary</h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
            <?php
            $avatarColors = ['#3498DB','#27AE60','#E74C3C','#9B59B6','#F39C12','#1ABC9C','#E67E22','#2980B9'];
            foreach ($employees as $emp):
                $empId = $emp['id'];
                $initials = strtoupper(substr($emp['first_name'],0,1).substr($emp['last_name'],0,1));
                $avatarColor = $avatarColors[$empId % count($avatarColors)];
                try {
                    $s = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM furn_attendance WHERE employee_id=? AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY status");
                    $s->execute([$empId]);
                    $es = ['present'=>0,'absent'=>0,'late'=>0,'half_day'=>0];
                    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) { if (isset($es[$r['status']])) $es[$r['status']] = (int)$r['cnt']; }
                    $et = array_sum($es);
                    $er = $et > 0 ? round((($es['present']+$es['late']+$es['half_day']) / $et) * 100) : 0;
                } catch (PDOException $e) { $es=['present'=>0,'absent'=>0,'late'=>0,'half_day'=>0]; $er=0; }
                $rc = $er >= 90 ? '#27AE60' : ($er >= 70 ? '#F39C12' : '#E74C3C');
            ?>
            <div style="background:#fff;border:1.5px solid #F0F0F0;border-radius:12px;padding:16px;display:flex;align-items:center;gap:14px;">
                <div class="emp-avatar-placeholder" style="background:<?php echo $avatarColor; ?>;width:46px;height:46px;font-size:17px;flex-shrink:0;"><?php echo $initials; ?></div>
                <div style="flex:1;min-width:0;">
                    <div style="font-weight:700;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($emp['first_name'].' '.$emp['last_name']); ?></div>
                    <div style="font-size:11px;color:#95A5A6;margin-bottom:8px;"><?php echo htmlspecialchars($emp['position']); ?></div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <span style="font-size:11px;background:#27AE6018;color:#27AE60;border-radius:10px;padding:2px 8px;font-weight:600;"><?php echo $es['present']; ?> P</span>
                        <span style="font-size:11px;background:#E74C3C18;color:#E74C3C;border-radius:10px;padding:2px 8px;font-weight:600;"><?php echo $es['absent']; ?> A</span>
                        <span style="font-size:11px;background:#F39C1218;color:#F39C12;border-radius:10px;padding:2px 8px;font-weight:600;"><?php echo $es['late']; ?> L</span>
                        <span style="font-size:11px;background:#9B59B618;color:#9B59B6;border-radius:10px;padding:2px 8px;font-weight:600;"><?php echo $es['half_day']; ?> H</span>
                    </div>
                </div>
                <div style="text-align:center;flex-shrink:0;">
                    <div style="font-size:22px;font-weight:800;color:<?php echo $rc; ?>;"><?php echo $er; ?>%</div>
                    <div style="font-size:10px;color:#95A5A6;font-weight:600;">RATE</div>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
        </div><!-- end tab-summary -->

        <!-- ══ OVERTIME TABLE (always visible below all tabs) ══ -->
        <div class="section-card" style="margin-top:20px;" id="overtimeSection">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
                <div>
                    <h2 class="section-title" style="margin:0;"><i class="fas fa-clock" style="color:#F39C12;"></i> Overtime Hours</h2>
                    <p style="margin:4px 0 0;font-size:13px;color:#7F8C8D;"><?php echo count($employees); ?> employees &nbsp;|&nbsp; Enter overtime hours worked beyond standard 8hrs/day</p>
                </div>
            </div>

            <?php if (empty($employees)): ?>
                <p style="text-align:center;color:#95A5A6;padding:30px 0;">No employees found.</p>
            <?php else: ?>
            <form method="POST" action="<?php echo BASE_URL; ?>/public/manager/attendance" id="otForm">
                <input type="hidden" name="action" value="save_overtime">
                <input type="hidden" name="csrf_token" value="<?php echo bin2hex(random_bytes(16)); ?>">
                <input type="hidden" name="date" value="<?php echo date('Y-m-d'); ?>">

                <div class="table-responsive">
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="background:#2C3E50;color:#fff;">
                            <th style="padding:12px 10px;font-size:12px;font-weight:700;text-align:left;width:40px;">#</th>
                            <th style="padding:12px 10px;font-size:12px;font-weight:700;text-align:left;">Employee</th>
                            <th style="padding:12px 10px;font-size:12px;font-weight:700;text-align:left;">Position</th>
                            <th style="padding:12px 10px;font-size:12px;font-weight:700;text-align:center;width:120px;">OT Hours</th>
                            <th style="padding:12px 10px;font-size:12px;font-weight:700;text-align:center;width:130px;">OT Rate (ETB/hr)</th>
                            <th style="padding:12px 10px;font-size:12px;font-weight:700;text-align:center;width:140px;">OT Pay (ETB)</th>
                            <th style="padding:12px 10px;font-size:12px;font-weight:700;text-align:left;">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($employees as $i => $emp):
                        $empId = $emp['id'];
                        $initials = strtoupper(substr($emp['first_name'],0,1).substr($emp['last_name'],0,1));
                        $avatarColors = ['#3498DB','#27AE60','#E74C3C','#9B59B6','#F39C12','#1ABC9C','#E67E22','#2980B9'];
                        $avatarColor  = $avatarColors[$empId % count($avatarColors)];
                        $otRate = 0;
                        try {
                            $sr = $pdo->prepare("SELECT overtime_rate FROM furn_employee_salary WHERE employee_id=?");
                            $sr->execute([$empId]);
                            $otRate = floatval($sr->fetchColumn() ?: 0);
                        } catch(PDOException $e){}
                        $existOT = 0; $existOTNote = '';
                        try {
                            $attCols = $pdo->query("SHOW COLUMNS FROM furn_attendance")->fetchAll(PDO::FETCH_COLUMN);
                            $dateExpr = in_array('date', $attCols) ? 'date' : 'DATE(check_in_time)';
                            if (in_array('overtime_hours', $attCols)) {
                                $otr = $pdo->prepare("SELECT overtime_hours, notes FROM furn_attendance WHERE employee_id=? AND $dateExpr=? ORDER BY id DESC LIMIT 1");
                                $otr->execute([$empId, date('Y-m-d')]);
                                $otRow = $otr->fetch(PDO::FETCH_ASSOC);
                                if ($otRow) { $existOT = floatval($otRow['overtime_hours']); $existOTNote = $otRow['notes'] ?? ''; }
                            }
                        } catch(PDOException $e){}
                    ?>
                    <tr style="border-bottom:1px solid #F0F0F0;">
                        <td style="padding:10px;font-weight:600;color:#95A5A6;font-size:13px;"><?php echo $i+1; ?></td>
                        <td style="padding:10px;">
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div class="emp-avatar-placeholder" style="background:<?php echo $avatarColor; ?>;"><?php echo $initials; ?></div>
                                <div>
                                    <div style="font-weight:600;font-size:13px;"><?php echo htmlspecialchars($emp['first_name'].' '.$emp['last_name']); ?></div>
                                    <div style="font-size:11px;color:#95A5A6;"><?php echo htmlspecialchars($emp['email']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td style="padding:10px;">
                            <span style="background:#3498DB18;color:#3498DB;border-radius:12px;padding:2px 10px;font-size:11px;font-weight:600;">
                                <?php echo htmlspecialchars($emp['position'] ?? 'Employee'); ?>
                            </span>
                        </td>
                        <td style="padding:10px;text-align:center;">
                            <input type="number" name="ot_hours[<?php echo $empId; ?>]"
                                   id="adm-ot-hrs-<?php echo $empId; ?>"
                                   value="<?php echo $existOT; ?>"
                                   min="0" max="24" step="0.5"
                                   style="width:90px;padding:7px 10px;border:2px solid #E0E0E0;border-radius:8px;font-size:14px;font-weight:700;text-align:center;font-family:inherit;outline:none;color:#F39C12;"
                                   oninput="admCalcOTPay(<?php echo $empId; ?>, <?php echo $otRate; ?>)"
                                   onfocus="this.style.borderColor='#F39C12'" onblur="this.style.borderColor='#E0E0E0'">
                        </td>
                        <td style="padding:10px;text-align:center;font-weight:600;color:#555;">
                            ETB <?php echo number_format($otRate, 2); ?>
                            <input type="hidden" name="ot_rate[<?php echo $empId; ?>]" value="<?php echo $otRate; ?>">
                        </td>
                        <td style="padding:10px;text-align:center;font-weight:700;color:#27AE60;" id="adm-ot-pay-<?php echo $empId; ?>">
                            ETB <?php echo number_format($existOT * $otRate, 2); ?>
                        </td>
                        <td style="padding:10px;">
                            <input type="text" name="ot_notes[<?php echo $empId; ?>]"
                                   value="<?php echo htmlspecialchars($existOTNote); ?>"
                                   placeholder="Optional note..."
                                   style="width:150px;padding:6px 8px;border:1.5px solid #E0E0E0;border-radius:6px;font-size:12px;font-family:inherit;">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>

                <div style="margin-top:16px;padding:14px 18px;background:#FEF9E7;border-radius:10px;border-left:4px solid #F39C12;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                    <div style="font-size:13px;color:#555;">
                        <strong>OT Rate</strong> is set per employee in <a href="<?php echo BASE_URL; ?>/public/admin/employees" style="color:#3498DB;font-weight:600;">Admin → Employees</a>.
                        OT Pay = OT Hours × OT Rate.
                    </div>
                    <button type="submit" style="padding:11px 28px;background:linear-gradient(135deg,#F39C12,#E67E22);color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;">
                        <i class="fas fa-save"></i> Save Overtime
                    </button>
                </div>
            </form>
            <?php endif; ?>
        </div>

    </div><!-- end main-content -->

    <script>
    function admCalcOTPay(empId, rate) {
        const hrs = parseFloat(document.getElementById('adm-ot-hrs-' + empId)?.value || 0);
        const pay = hrs * rate;
        const el  = document.getElementById('adm-ot-pay-' + empId);
        if (el) el.textContent = 'ETB ' + pay.toLocaleString('en', {minimumFractionDigits:2});
    }
    </script>

    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
    <script>
    function switchTab(tabId, btn) {
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById(tabId).classList.add('active');
        btn.classList.add('active');
    }

    function applyAdminFilters() {
        const dateVal   = document.getElementById('adm-filter-date').value;
        const statusVal = document.getElementById('adm-filter-status').value;
        const rows      = document.querySelectorAll('#adm-records-tbody tr');
        let visible = 0;
        rows.forEach(row => {
            const matchDate   = !dateVal   || row.dataset.date   === dateVal;
            const matchStatus = !statusVal || row.dataset.status === statusVal;
            row.style.display = (matchDate && matchStatus) ? '' : 'none';
            if (matchDate && matchStatus) visible++;
        });
        const countEl   = document.getElementById('adm-rec-count');
        const noResults = document.getElementById('adm-no-results');
        const tableEl   = document.getElementById('adm-records-table');
        if (countEl)   countEl.textContent    = visible + ' record(s)';
        if (noResults) noResults.style.display = visible === 0 ? 'block' : 'none';
        if (tableEl)   tableEl.style.display   = visible === 0 ? 'none'  : '';
    }

    function clearAdminFilters() {
        document.getElementById('adm-filter-date').value   = '';
        document.getElementById('adm-filter-status').value = '';
        applyAdminFilters();
    }

    document.addEventListener('DOMContentLoaded', function() {
        if (document.getElementById('adm-records-tbody')) applyAdminFilters();
    });
    </script>
</body>
</html>
