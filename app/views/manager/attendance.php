<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    header('Location: ' . BASE_URL . '/public/login'); exit();
}
$csrf_token = $_SESSION['csrf_token'] ?? null;
if (!$csrf_token) { $csrf_token = bin2hex(random_bytes(32)); $_SESSION['csrf_token'] = $csrf_token; }
require_once __DIR__ . '/../../../config/db_config.php';
$managerName = $_SESSION['user_name'] ?? 'Manager User';
$managerId   = $_SESSION['user_id'];

// ── Handle POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Invalid CSRF token.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'bulk_mark_attendance') {
            try {
                $date = $_POST['date'] ?? date('Y-m-d');
                $attendanceData = $_POST['attendance'] ?? [];
                $checkIn  = $_POST['check_in']  ?? [];
                $notes    = $_POST['notes']     ?? [];

                // Ensure schema supports all needed values
                try {
                    $pdo->exec("ALTER TABLE furn_attendance MODIFY COLUMN status ENUM('present','late','absent','half_day') NOT NULL DEFAULT 'absent'");
                    $pdo->exec("ALTER TABLE furn_attendance ADD COLUMN IF NOT EXISTS date DATE DEFAULT NULL");
                    $pdo->exec("ALTER TABLE furn_attendance ADD COLUMN IF NOT EXISTS check_out_time TIMESTAMP NULL DEFAULT NULL");
                    $pdo->exec("ALTER TABLE furn_attendance MODIFY COLUMN ip_address VARCHAR(45) NULL DEFAULT NULL");
                    $pdo->exec("ALTER TABLE furn_attendance ADD COLUMN IF NOT EXISTS marked_by INT DEFAULT NULL");
                    $pdo->exec("ALTER TABLE furn_attendance ADD COLUMN IF NOT EXISTS overtime_hours DECIMAL(5,2) NOT NULL DEFAULT 0");
                } catch (PDOException $e2) {}

                // Drop the unique key on check_in_time if it exists — we want unique per employee per date
                try {
                    $pdo->exec("ALTER TABLE furn_attendance DROP INDEX unique_employee_date");
                } catch (PDOException $e2) {}
                try {
                    $pdo->exec("ALTER TABLE furn_attendance ADD UNIQUE KEY unique_emp_date (employee_id, date)");
                } catch (PDOException $e2) {}

                $count = 0;
                foreach ($attendanceData as $empId => $status) {
                    $empId = intval($empId);
                    if (!$empId) continue;
                    if (!in_array($status, ['present','absent','late','half_day'])) {
                        $status = 'absent';
                    }
                    $ciTime = !empty($checkIn[$empId]) ? $checkIn[$empId] : '08:00';
                    $ciTimestamp = $date . ' ' . $ciTime . ':00';
                    $nt = !empty($notes[$empId]) ? trim($notes[$empId]) : null;

                    // Calculate overtime: hours worked beyond 8 standard hours
                    $checkOutVal = $_POST['check_out'][$empId] ?? '';
                    $coTimestamp = !empty($checkOutVal) ? $date . ' ' . $checkOutVal . ':00' : null;
                    $overtimeHours = 0;
                    if ($coTimestamp) {
                        $workedMinutes = (strtotime($coTimestamp) - strtotime($ciTimestamp)) / 60;
                        $standardMinutes = 8 * 60; // 8 hour standard day
                        $overtimeMinutes = max(0, $workedMinutes - $standardMinutes);
                        $overtimeHours = round($overtimeMinutes / 60, 2);
                    }

                    // Delete existing record for this employee on this date
                    $pdo->prepare("DELETE FROM furn_attendance WHERE employee_id = ? AND date = ?")
                        ->execute([$empId, $date]);

                    $pdo->prepare("
                        INSERT INTO furn_attendance
                            (employee_id, date, check_in_time, check_out_time, status, notes, overtime_hours, marked_by, ip_address, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, '127.0.0.1', NOW())
                    ")->execute([$empId, $date, $ciTimestamp, $coTimestamp, $status, $nt, $overtimeHours, $managerId]);
                    $count++;
                }

                $_SESSION['att_success'] = "Attendance saved for $count employees on " . date('M d, Y', strtotime($date));
                header('Location: ' . BASE_URL . '/public/manager/attendance?saved=1&tab=records');
                exit();
            } catch (PDOException $e) { $error = "Save error: " . $e->getMessage(); }
        } elseif ($action === 'delete_attendance') {
            try {
                $pdo->prepare("DELETE FROM furn_attendance WHERE id=?")->execute([$_POST['attendance_id']]);
                $_SESSION['att_success'] = "Record deleted.";
                header('Location: ' . BASE_URL . '/public/manager/attendance?tab=records');
                exit();
            } catch (PDOException $e) { $error = $e->getMessage(); }
        } elseif ($action === 'edit_attendance') {
            try {
                $attId    = (int)$_POST['attendance_id'];
                $newStatus = $_POST['new_status'] ?? '';
                $newCI    = $_POST['new_check_in'] ?? '';
                $newCO    = $_POST['new_check_out'] ?? '';
                $newNote  = trim($_POST['new_notes'] ?? '');
                if (!in_array($newStatus, ['present','absent','late','half_day'])) throw new Exception("Invalid status.");

                $cols = $pdo->query("SHOW COLUMNS FROM furn_attendance")->fetchAll(PDO::FETCH_COLUMN);
                $hasCheckOut = in_array('check_out_time', $cols);
                $hasNotes    = in_array('notes', $cols);

                // Get the record date for timestamp building
                $recRow = $pdo->prepare("SELECT date FROM furn_attendance WHERE id=?");
                $recRow->execute([$attId]);
                $recDate = $recRow->fetchColumn();

                $sets = ["status=?", "check_in_time=?"];
                $vals = [$newStatus, $recDate . ' ' . ($newCI ?: '08:00') . ':00'];
                if ($hasCheckOut) { $sets[] = "check_out_time=?"; $vals[] = $newCO ? $recDate . ' ' . $newCO . ':00' : null; }
                if ($hasNotes)    { $sets[] = "notes=?";           $vals[] = $newNote ?: null; }
                $vals[] = $attId;
                $pdo->prepare("UPDATE furn_attendance SET ".implode(',',$sets)." WHERE id=?")->execute($vals);

                // Resolve any open dispute for this record if checkbox checked
                if (!empty($_POST['resolve_dispute_on_edit'])) {
                    try {
                        $pdo->prepare("UPDATE furn_attendance_disputes SET status='resolved', resolved_at=NOW() WHERE attendance_id=? AND status='open'")
                            ->execute([$attId]);
                    } catch (PDOException $e2) {}
                }

                $_SESSION['att_success'] = "Record updated and dispute resolved.";
                header('Location: ' . BASE_URL . '/public/manager/attendance?tab=records');
                exit();
            } catch (Exception $e) { $error = "Edit error: " . $e->getMessage(); }
        } elseif ($action === 'resolve_dispute') {
            try {
                $pdo->prepare("UPDATE furn_attendance_disputes SET status='resolved', resolved_at=NOW() WHERE id=? AND status='open'")
                    ->execute([(int)$_POST['dispute_id']]);
                $_SESSION['att_success'] = "Dispute marked as resolved.";
                header('Location: ' . BASE_URL . '/public/manager/attendance?tab=records');
                exit();
            } catch (PDOException $e) { $error = $e->getMessage(); }

        } elseif ($action === 'save_overtime') {
            try {
                $date    = $_POST['date'] ?? date('Y-m-d');
                $otHours = $_POST['ot_hours'] ?? [];
                $otNotes = $_POST['ot_notes'] ?? [];
                $count   = 0;
                foreach ($otHours as $empId => $hrs) {
                    $empId = intval($empId);
                    $hrs   = floatval($hrs);
                    if (!$empId) continue;
                    // Update overtime_hours on the attendance record for that date
                    try {
                        $pdo->exec("ALTER TABLE furn_attendance ADD COLUMN IF NOT EXISTS overtime_hours DECIMAL(5,2) NOT NULL DEFAULT 0");
                    } catch(PDOException $e2){}
                    $attCols  = $pdo->query("SHOW COLUMNS FROM furn_attendance")->fetchAll(PDO::FETCH_COLUMN);
                    $dateExpr = in_array('date', $attCols) ? 'date' : 'DATE(check_in_time)';
                    $note = trim($otNotes[$empId] ?? '');
                    // Update existing record or insert a new one
                    $existing = $pdo->prepare("SELECT id FROM furn_attendance WHERE employee_id=? AND $dateExpr=? LIMIT 1");
                    $existing->execute([$empId, $date]);
                    $attId = $existing->fetchColumn();
                    if ($attId) {
                        $pdo->prepare("UPDATE furn_attendance SET overtime_hours=?, notes=? WHERE id=?")
                            ->execute([$hrs, $note ?: null, $attId]);
                    } else {
                        $pdo->prepare("INSERT INTO furn_attendance (employee_id, date, check_in_time, status, overtime_hours, notes, ip_address, marked_by, created_at) VALUES (?,?,?,?,?,?,'127.0.0.1',?,NOW())")
                            ->execute([$empId, $date, $date.' 08:00:00', 'present', $hrs, $note ?: null, $managerId]);
                    }
                    $count++;
                }
                $_SESSION['att_success'] = "Overtime saved for $count employees on ".date('M d, Y', strtotime($date)).".";
                header('Location: ' . BASE_URL . '/public/manager/attendance');
                exit();
            } catch (PDOException $e) { $error = "OT save error: " . $e->getMessage(); }
        }
    }
}

// ── Fetch employees (safe: no optional columns) ──
$employees = [];
try {
    $stmt = $pdo->query("
        SELECT id, first_name, last_name, email,
               '' as profile_image, 'Employee' as position
        FROM furn_users WHERE role='employee' ORDER BY first_name, last_name
    ");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Try to enrich with optional columns if they exist
    if (!empty($employees)) {
        try {
            $stmt2 = $pdo->query("
                SELECT id, first_name, last_name, email,
                       COALESCE(profile_image,'') as profile_image,
                       COALESCE(position,'Employee') as position
                FROM furn_users WHERE role='employee' ORDER BY first_name, last_name
            ");
            $employees = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e2) { /* optional columns missing, keep basic result */ }
    }
} catch (PDOException $e) { $error = "Error fetching employees: " . $e->getMessage(); }

// ── Flash message from session ──
if (isset($_SESSION['att_success'])) {
    $success = $_SESSION['att_success'];
    unset($_SESSION['att_success']);
}

// ── Load open disputes (keyed by attendance_id) ──
$openDisputes = []; // [attendance_id => ['id'=>..,'reason'=>..,'employee_name'=>..]]
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
    $ds = $pdo->query("SELECT d.*, u.first_name, u.last_name FROM furn_attendance_disputes d LEFT JOIN furn_users u ON d.employee_id=u.id WHERE d.status='open'");
    foreach ($ds->fetchAll(PDO::FETCH_ASSOC) as $d) $openDisputes[$d['attendance_id']] = $d;
} catch (PDOException $e) {}

// ── Fetch today's existing attendance (pre-fill the mark sheet) ──
$todayMap = [];
try {
    $stmt = $pdo->prepare("SELECT *, COALESCE(date, DATE(check_in_time)) as date, TIME(check_in_time) as ci_time, TIME(check_out_time) as co_time FROM furn_attendance WHERE COALESCE(date, DATE(check_in_time)) = ?");
    $stmt->execute([date('Y-m-d')]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $todayMap[$r['employee_id']] = $r;
} catch (PDOException $e) {}

// ── Stats ──
$stats = ['present'=>0,'absent'=>0,'late'=>0,'half_day'=>0,'total'=>count($employees),'rate'=>0];
try {
    $today = date('Y-m-d');
    $rows = $pdo->query("SELECT status, COUNT(*) as cnt FROM furn_attendance WHERE COALESCE(date, DATE(check_in_time)) = '$today' GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) { if (isset($stats[$r['status']])) $stats[$r['status']] = (int)$r['cnt']; }
    if ($stats['total'] > 0) $stats['rate'] = round((($stats['present']+$stats['late']+$stats['half_day']) / $stats['total']) * 100, 1);
} catch (PDOException $e) {}

$pageTitle = 'Team Attendance';
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
        :root { --green:#27AE60; --red:#E74C3C; --blue:#3498DB; --orange:#F39C12; --purple:#9B59B6; --dark:#2C3E50; }
        .att-header { background:linear-gradient(135deg,#1a252f 0%,#2C3E50 50%,#3D1F14 100%); color:#fff; padding:28px 30px; border-radius:14px; margin-bottom:24px; }
        .stat-pill { display:inline-flex; align-items:center; gap:8px; padding:8px 16px; border-radius:30px; font-size:13px; font-weight:600; }
        /* Status toggle buttons */
        .status-btn { display:inline-flex; align-items:center; justify-content:center; gap:5px; padding:7px 14px; border-radius:8px; border:2px solid transparent; font-size:12px; font-weight:700; cursor:pointer; transition:all .15s; white-space:nowrap; }
        .status-btn.present  { border-color:#27AE60; color:#27AE60; background:#fff; }
        .status-btn.absent   { border-color:#E74C3C; color:#E74C3C; background:#fff; }
        .status-btn.late     { border-color:#F39C12; color:#F39C12; background:#fff; }
        .status-btn.half_day { border-color:#9B59B6; color:#9B59B6; background:#fff; }
        .status-btn.active.present  { background:#27AE60; color:#fff; }
        .status-btn.active.absent   { background:#E74C3C; color:#fff; }
        .status-btn.active.late     { background:#F39C12; color:#fff; }
        .status-btn.active.half_day { background:#9B59B6; color:#fff; }
        /* Attendance table */
        .att-table { width:100%; border-collapse:collapse; }
        .att-table thead tr { background:#2C3E50; color:#fff; }
        .att-table th { padding:12px 10px; font-size:12px; font-weight:700; text-align:left; white-space:nowrap; }
        .att-table td { padding:10px; border-bottom:1px solid #F0F0F0; vertical-align:middle; }
        .att-table tbody tr:hover { background:#FAFBFC; }
        .att-table tbody tr.row-present { border-left:3px solid #27AE60; }
        .att-table tbody tr.row-absent  { border-left:3px solid #E74C3C; }
        .att-table tbody tr.row-late    { border-left:3px solid #F39C12; }
        .att-table tbody tr.row-half_day{ border-left:3px solid #9B59B6; }
        /* Employee avatar */
        .emp-avatar { width:38px; height:38px; border-radius:50%; object-fit:cover; border:2px solid #E0E0E0; }
        .emp-avatar-placeholder { width:38px; height:38px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-weight:700; font-size:15px; color:#fff; flex-shrink:0; }
        /* Time input */
        .time-input { padding:6px 8px; border:1.5px solid #E0E0E0; border-radius:6px; font-size:12px; font-family:inherit; width:90px; outline:none; }
        .time-input:focus { border-color:#3498DB; }
        .ot-input { padding:6px 8px; border:1.5px solid #E0E0E0; border-radius:6px; font-size:12px; font-family:inherit; width:60px; outline:none; }
        .note-input { padding:6px 8px; border:1.5px solid #E0E0E0; border-radius:6px; font-size:12px; font-family:inherit; width:130px; outline:none; }
        /* Tab nav */
        .tab-nav { display:flex; gap:4px; background:#F0F2F5; border-radius:10px; padding:4px; margin-bottom:24px; }
        .tab-btn { flex:1; padding:10px 16px; border:none; border-radius:8px; font-family:inherit; font-size:13px; font-weight:600; cursor:pointer; background:transparent; color:#7F8C8D; transition:all .2s; }
        .tab-btn.active { background:#fff; color:#2C3E50; box-shadow:0 2px 8px rgba(0,0,0,0.1); }
        .tab-pane { display:none; }
        .tab-pane.active { display:block; }
        /* Progress ring */
        .ring-wrap { position:relative; width:80px; height:80px; }
        .ring-wrap svg { transform:rotate(-90deg); }
        .ring-val { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-size:16px; font-weight:700; color:#2C3E50; }
        .print-only { display:none; }
        @media print {
            * { -webkit-print-color-adjust:exact !important; print-color-adjust:exact !important; }
            .no-print, .sidebar-overlay, .mobile-menu-toggle,
            .tab-nav, .att-header, .stats-grid, .top-header,
            nav, aside { display:none !important; }
            body { margin:0; padding:0; background:#fff; font-family:Arial,sans-serif; }
            .main-content { margin:0 !important; padding:8px !important; }
            .section-card { box-shadow:none !important; border:1px solid #ccc; padding:10px !important; }

            /* Remove the scroll indicator pseudo-elements */
            .section-card::after,
            .section-card::before,
            .table-responsive::before,
            .table-responsive::after { display:none !important; content:none !important; }

            /* Hide all tabs by default, show only the one being printed */
            .tab-pane { display:none !important; }
            .tab-pane.print-target { display:block !important; }

            /* Print header */
            .print-header { display:block !important; text-align:center; margin-bottom:14px; border-bottom:2px solid #2C3E50; padding-bottom:10px; }
            .print-header h2 { margin:0 0 4px; font-size:18px; color:#2C3E50; }
            .print-header p  { margin:0; font-size:12px; color:#555; }

            /* Tables */
            .att-table, .data-table { width:100%; border-collapse:collapse; font-size:11px; }
            .att-table th, .data-table th { background:#2C3E50 !important; color:#fff !important; padding:7px 8px; }
            .att-table td, .data-table td { padding:6px 8px; border-bottom:1px solid #e0e0e0; }

            /* Status badges — remove background/border, show plain text */
            span[style*="border-radius"] { background:none !important; border:none !important; padding:0 !important; font-size:11px !important; }

            /* Hide form inputs borders */
            input[type="time"], input[type="text"] { border:none !important; background:transparent !important; font-size:11px; }
            button, .btn-action { display:none !important; }

            /* Hide any tooltip or overlay */
            [data-tooltip], [title]:hover::after, [title]:hover::before { display:none !important; }
        }
        .print-header { display:none; }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle no-print" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>
    <div class="sidebar-overlay no-print"></div>
    <?php include_once __DIR__ . '/../../includes/manager_sidebar.php'; ?>
    <?php $pageTitle = 'Team Attendance'; include_once __DIR__ . '/../../includes/manager_header.php'; ?>

    <div class="main-content">
        <?php if (isset($success)): ?>
        <div class="alert alert-success" style="display:flex;align-items:center;gap:10px;"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
        <div class="alert alert-danger" style="display:flex;align-items:center;gap:10px;"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- ── Page Header ── -->
        <div class="att-header no-print">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px;">
                <div>
                    <h1 style="margin:0 0 6px;font-size:26px;"><i class="fas fa-clipboard-user"></i> Team Attendance</h1>
                    <p style="margin:0;opacity:.85;font-size:14px;">Manager: <strong><?php echo htmlspecialchars($managerName); ?></strong> &nbsp;|&nbsp; <?php echo date('l, F j, Y'); ?></p>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                    <span class="stat-pill" style="background:rgba(39,174,96,.25);color:#A9DFBF;">
                        <i class="fas fa-check-circle"></i> <?php echo $stats['present']; ?> Present
                    </span>
                    <span class="stat-pill" style="background:rgba(231,76,60,.25);color:#F1948A;">
                        <i class="fas fa-times-circle"></i> <?php echo $stats['absent']; ?> Absent
                    </span>
                    <span class="stat-pill" style="background:rgba(243,156,18,.25);color:#FAD7A0;">
                        <i class="fas fa-clock"></i> <?php echo $stats['late']; ?> Late
                    </span>
                    <span class="stat-pill" style="background:rgba(155,89,182,.25);color:#D7BDE2;">
                        <i class="fas fa-adjust"></i> <?php echo $stats['half_day']; ?> Half-Day
                    </span>
                </div>
            </div>
        </div>

        <!-- ── Stats Cards ── -->
        <div class="stats-grid no-print" style="margin-bottom:24px;">
            <?php
            $attCards = [
                [$stats['present'], 'Present Today',    '#27AE60', 'fa-user-check',  '?tab=records'],
                [$stats['absent'],  'Absent Today',     '#E74C3C', 'fa-user-times',  '?tab=records'],
                [$stats['late'],    'Late Arrivals',    '#F39C12', 'fa-user-clock',  '?tab=records'],
                [$stats['rate'].'%','Attendance Rate',  '#9B59B6', 'fa-chart-pie',   '?tab=summary'],
            ];
            foreach ($attCards as [$v,$l,$c,$i,$href]): ?>
            <a href="<?php echo $href; ?>" style="text-decoration:none;">
                <div class="stat-card" style="border-left:4px solid <?php echo $c; ?>;cursor:pointer;transition:transform .15s,box-shadow .15s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,0.12)';" onmouseout="this.style.transform='';this.style.boxShadow='';">
                    <div style="display:flex;justify-content:space-between;align-items:center;">
                        <div><div class="stat-value"><?php echo $v; ?></div><div class="stat-label"><?php echo $l; ?></div></div>
                        <i class="fas <?php echo $i; ?>" style="font-size:30px;color:<?php echo $c; ?>;opacity:.7;"></i>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- ── Tab Navigation ── -->
        <div class="tab-nav no-print">
            <button class="tab-btn <?php echo ($_GET['tab'] ?? '') === 'records' ? '' : 'active'; ?>" onclick="switchTab('tab-sheet',this)"><i class="fas fa-clipboard-list"></i> Mark Attendance</button>
            <button class="tab-btn <?php echo ($_GET['tab'] ?? '') === 'records' ? 'active' : ''; ?>" onclick="switchTab('tab-records',this)">
                <i class="fas fa-history"></i> Records
                <?php if (!empty($openDisputes)): ?>
                <span style="background:#E74C3C;color:#fff;border-radius:10px;padding:1px 7px;font-size:11px;font-weight:700;margin-left:4px;"><?php echo count($openDisputes); ?></span>
                <?php endif; ?>
            </button>
            <button class="tab-btn" onclick="switchTab('tab-summary',this)"><i class="fas fa-chart-bar"></i> Monthly Summary</button>
        </div>

        <!-- ══════════════════════════════════════════════
             TAB 1: MARK ATTENDANCE SHEET
        ══════════════════════════════════════════════ -->
        <div id="tab-sheet" class="tab-pane <?php echo ($_GET['tab'] ?? '') === 'records' ? '' : 'active'; ?>">
        <!-- Print header for attendance sheet -->
        <div class="print-header">
            <h2>FurnitureCraft — Attendance Sheet</h2>
            <p>Date: <strong id="printSheetDate"><?php echo date('d/m/Y'); ?></strong> &nbsp;|&nbsp; Manager: <strong><?php echo htmlspecialchars($managerName); ?></strong> &nbsp;|&nbsp; Total Employees: <strong><?php echo count($employees); ?></strong></p>
        </div>
        <div class="section-card">
            <!-- Sheet header controls -->
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
                <div>
                    <h2 class="section-title" style="margin:0;"><i class="fas fa-clipboard-user" style="color:#27AE60;"></i> Team Attendance Sheet</h2>
                    <p style="margin:4px 0 0;font-size:13px;color:#7F8C8D;"><?php echo count($employees); ?> employees &nbsp;|&nbsp; Mark present, absent, late, or half-day</p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;" class="no-print">
                    <input type="date" id="sheetDate" value="<?php echo date('Y-m-d'); ?>"
                        style="padding:9px 12px;border:2px solid #E0E0E0;border-radius:8px;font-family:inherit;font-size:13px;outline:none;"
                        onfocus="this.style.borderColor='#27AE60'" onblur="this.style.borderColor='#E0E0E0'">
                    <button onclick="setAllStatus('present')" style="padding:9px 14px;background:#27AE60;color:#fff;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;">
                        <i class="fas fa-check-double"></i> All Present
                    </button>
                    <button onclick="setAllStatus('absent')" style="padding:9px 14px;background:#E74C3C;color:#fff;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;">
                        <i class="fas fa-times"></i> All Absent
                    </button>
                    <button onclick="clearAll()" style="padding:9px 14px;background:#95A5A6;color:#fff;border:none;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;">
                        <i class="fas fa-undo"></i> Clear
                    </button>
                </div>
            </div>

            <?php if (empty($employees)): ?>
                <p style="text-align:center;color:#95A5A6;padding:40px 0;">No employees found.</p>
            <?php else: ?>
            <form method="POST" id="bulkForm" action="<?php echo BASE_URL; ?>/public/manager/attendance">
                <input type="hidden" name="action" value="bulk_mark_attendance">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="date" id="bulkDate" value="<?php echo date('Y-m-d'); ?>">

                <!-- Print header (only visible when printing) -->
                <div style="display:none;" class="print-only" id="printHeader">
                    <div style="text-align:center;margin-bottom:16px;">
                        <h2 style="margin:0;font-size:20px;">Team Attendance Record</h2>
                        <p style="margin:4px 0;font-size:13px;">Date: <span id="printDate"><?php echo date('d/m/Y'); ?></span></p>
                    </div>
                </div>

                <div class="table-responsive">
                <table class="att-table" id="attendanceTable">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Employee</th>
                            <th>Position</th>
                            <th style="text-align:center;background:#27AE6022;color:#27AE60;width:80px;">
                                <i class="fas fa-check-circle"></i><br><span style="font-size:10px;letter-spacing:.5px;">PRESENT</span>
                            </th>
                            <th style="text-align:center;background:#E74C3C22;color:#E74C3C;width:80px;">
                                <i class="fas fa-times-circle"></i><br><span style="font-size:10px;letter-spacing:.5px;">ABSENT</span>
                            </th>
                            <th style="text-align:center;background:#F39C1222;color:#F39C12;width:80px;">
                                <i class="fas fa-clock"></i><br><span style="font-size:10px;letter-spacing:.5px;">LATE</span>
                            </th>
                            <th style="text-align:center;background:#9B59B622;color:#9B59B6;width:80px;">
                                <i class="fas fa-adjust"></i><br><span style="font-size:10px;letter-spacing:.5px;">HALF DAY</span>
                            </th>
                            <th class="no-print">Check-In</th>
                            <th class="no-print">Check-Out</th>
                            <th class="no-print">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($employees as $i => $emp):
                        $empId = $emp['id'];
                        $rec   = $todayMap[$empId] ?? null;
                        $curStatus = $rec['status'] ?? '';
                        $curCI = $rec['ci_time']  ?? '';
                        $curCO = !empty($rec['co_time']) ? $rec['co_time'] : '';
                        $curOT = $rec['overtime_hours'] ?? '';
                        $curNote = $rec['notes'] ?? '';
                        $initials = strtoupper(substr($emp['first_name'],0,1).substr($emp['last_name'],0,1));
                        $avatarColors = ['#3498DB','#27AE60','#E74C3C','#9B59B6','#F39C12','#1ABC9C','#E67E22','#2980B9'];
                        $avatarColor  = $avatarColors[$empId % count($avatarColors)];
                    ?>
                    <tr id="row-<?php echo $empId; ?>" class="<?php echo $curStatus ? 'row-'.$curStatus : ''; ?>">
                        <td style="font-weight:600;color:#95A5A6;font-size:13px;"><?php echo $i+1; ?></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <?php if (!empty($emp['profile_image']) && file_exists($_SERVER['DOCUMENT_ROOT'].'/NEWkoder/public/uploads/'.$emp['profile_image'])): ?>
                                    <img src="<?php echo BASE_URL; ?>/public/uploads/<?php echo htmlspecialchars($emp['profile_image']); ?>" class="emp-avatar" alt="">
                                <?php else: ?>
                                    <div class="emp-avatar-placeholder" style="background:<?php echo $avatarColor; ?>;"><?php echo $initials; ?></div>
                                <?php endif; ?>
                                <div>
                                    <div style="font-weight:600;font-size:13px;"><?php echo htmlspecialchars($emp['first_name'].' '.$emp['last_name']); ?></div>
                                    <div style="font-size:11px;color:#95A5A6;"><?php echo htmlspecialchars($emp['email']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span style="background:#3498DB18;color:#3498DB;border-radius:12px;padding:2px 10px;font-size:11px;font-weight:600;">
                                <?php echo htmlspecialchars($emp['position']); ?>
                            </span>
                        </td>
                        <!-- PRESENT -->
                        <td style="text-align:center;padding:8px 4px;" id="cell-present-<?php echo $empId; ?>">
                            <input type="hidden" name="attendance[<?php echo $empId; ?>]" id="status-<?php echo $empId; ?>" value="<?php echo htmlspecialchars($curStatus); ?>">
                            <button type="button" onclick="setStatus(<?php echo $empId; ?>,'present')"
                                id="btn-present-<?php echo $empId; ?>"
                                style="width:44px;height:44px;border-radius:50%;border:2.5px solid #27AE60;background:<?php echo $curStatus==='present'?'#27AE60':'#fff'; ?>;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;transition:all .15s;box-shadow:<?php echo $curStatus==='present'?'0 3px 10px #27AE6055':'none'; ?>;">
                                <i class="fas fa-check" style="font-size:16px;color:<?php echo $curStatus==='present'?'#fff':'#27AE60'; ?>;"></i>
                            </button>
                        </td>
                        <!-- ABSENT -->
                        <td style="text-align:center;padding:8px 4px;" id="cell-absent-<?php echo $empId; ?>">
                            <button type="button" onclick="setStatus(<?php echo $empId; ?>,'absent')"
                                id="btn-absent-<?php echo $empId; ?>"
                                style="width:44px;height:44px;border-radius:50%;border:2.5px solid #E74C3C;background:<?php echo $curStatus==='absent'?'#E74C3C':'#fff'; ?>;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;transition:all .15s;box-shadow:<?php echo $curStatus==='absent'?'0 3px 10px #E74C3C55':'none'; ?>;">
                                <i class="fas fa-times" style="font-size:16px;color:<?php echo $curStatus==='absent'?'#fff':'#E74C3C'; ?>;"></i>
                            </button>
                        </td>
                        <!-- LATE -->
                        <td style="text-align:center;padding:8px 4px;" id="cell-late-<?php echo $empId; ?>">
                            <button type="button" onclick="setStatus(<?php echo $empId; ?>,'late')"
                                id="btn-late-<?php echo $empId; ?>"
                                style="width:44px;height:44px;border-radius:50%;border:2.5px solid #F39C12;background:<?php echo $curStatus==='late'?'#F39C12':'#fff'; ?>;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;transition:all .15s;box-shadow:<?php echo $curStatus==='late'?'0 3px 10px #F39C1255':'none'; ?>;">
                                <i class="fas fa-clock" style="font-size:16px;color:<?php echo $curStatus==='late'?'#fff':'#F39C12'; ?>;"></i>
                            </button>
                        </td>
                        <!-- HALF DAY -->
                        <td style="text-align:center;padding:8px 4px;" id="cell-half_day-<?php echo $empId; ?>">
                            <button type="button" onclick="setStatus(<?php echo $empId; ?>,'half_day')"
                                id="btn-half_day-<?php echo $empId; ?>"
                                style="width:44px;height:44px;border-radius:50%;border:2.5px solid #9B59B6;background:<?php echo $curStatus==='half_day'?'#9B59B6':'#fff'; ?>;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;transition:all .15s;box-shadow:<?php echo $curStatus==='half_day'?'0 3px 10px #9B59B655':'none'; ?>;">
                                <i class="fas fa-adjust" style="font-size:16px;color:<?php echo $curStatus==='half_day'?'#fff':'#9B59B6'; ?>;"></i>
                            </button>
                        </td>
                        <td class="no-print">
                            <input type="time" name="check_in[<?php echo $empId; ?>]" class="time-input"
                                value="<?php echo htmlspecialchars($curCI); ?>" placeholder="08:00">
                        </td>
                        <td class="no-print">
                            <input type="time" name="check_out[<?php echo $empId; ?>]" class="time-input"
                                value="<?php echo htmlspecialchars($curCO); ?>" placeholder="17:00">
                        </td>
                        <td class="no-print">
                            <input type="text" name="notes[<?php echo $empId; ?>]" class="note-input"
                                value="<?php echo htmlspecialchars($curNote); ?>" placeholder="Optional note...">
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>

                <!-- Footer -->
                <div style="margin-top:18px;padding:14px 18px;background:#F8F9FA;border-radius:10px;border-left:4px solid #2C3E50;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                    <div style="font-size:13px;color:#555;">
                        <strong>Marked By:</strong> <?php echo htmlspecialchars($managerName); ?>
                        <span style="background:#2C3E5018;color:#2C3E50;border-radius:12px;padding:2px 10px;font-size:12px;font-weight:600;margin-left:8px;">Manager</span>
                        &nbsp;|&nbsp; <span id="markedCount" style="color:#27AE60;font-weight:700;">0</span> marked
                    </div>
                    <div style="display:flex;gap:10px;" class="no-print">
                        <button type="button" onclick="printTab('tab-sheet')" style="padding:11px 20px;background:#95A5A6;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
                            <i class="fas fa-print"></i> Print Sheet
                        </button>
                        <button type="submit" style="padding:11px 28px;background:linear-gradient(135deg,#2C3E50,#3D1F14);color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;">
                            <i class="fas fa-save"></i> Save Attendance
                        </button>
                    </div>
                </div>
            </form>
            <?php endif; ?>
        </div>

        </div><!-- end tab-sheet -->

        <!-- ══════════════════════════════════════════════
             TAB 2: ATTENDANCE RECORDS
        ══════════════════════════════════════════════ -->
        <div id="tab-records" class="tab-pane <?php echo ($_GET['tab'] ?? '') === 'records' ? 'active' : ''; ?>">
        <!-- Print header for records -->
        <div class="print-header">
            <h2>FurnitureCraft — Attendance Records</h2>
            <p>Printed: <strong><?php echo date('d/m/Y H:i'); ?></strong> &nbsp;|&nbsp; Manager: <strong><?php echo htmlspecialchars($managerName); ?></strong></p>
        </div>
        <div class="section-card">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
                <h2 class="section-title" style="margin:0;"><i class="fas fa-history" style="color:#3498DB;"></i> Attendance Records</h2>
                <div style="display:flex;align-items:center;gap:10px;">
                    <span id="rec-count" style="font-size:13px;color:#95A5A6;"></span>
                    <button type="button" onclick="printTab('tab-records')" class="no-print" style="padding:8px 16px;background:#3498DB;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
                        <i class="fas fa-print"></i> Print Records
                    </button>
                </div>
            </div>

            <!-- JS-based filters (no page reload) -->
            <div class="no-print" style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:20px;padding:16px;background:#F8F9FA;border-radius:10px;align-items:flex-end;">
                <div>
                    <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px;">Date</label>
                    <input type="date" id="rec-filter-date" class="form-control" style="min-width:150px;" oninput="applyRecordFilters()">
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:4px;">Status</label>
                    <select id="rec-filter-status" class="form-control" style="min-width:150px;" onchange="applyRecordFilters()">
                        <option value="">All Statuses</option>
                        <option value="present">Present</option>
                        <option value="absent">Absent</option>
                        <option value="late">Late</option>
                        <option value="half_day">Half Day</option>
                    </select>
                </div>
                <button type="button" onclick="clearRecordFilters()" style="padding:9px 16px;background:#95A5A6;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
                    <i class="fas fa-redo"></i> Clear
                </button>
            </div>

            <?php
            // Load ALL records (no server-side filter) for JS filtering
            $allRecords = [];
            try {
                $stmt = $pdo->query("
                    SELECT a.id, a.employee_id,
                           COALESCE(a.date, DATE(a.check_in_time)) as att_date,
                           a.status,
                           TIME(a.check_in_time) as ci_time,
                           TIME(a.check_out_time) as co_time,
                           a.check_out_time, a.notes,
                           u.first_name, u.last_name
                    FROM furn_attendance a
                    LEFT JOIN furn_users u ON a.employee_id = u.id
                    ORDER BY COALESCE(a.date, DATE(a.check_in_time)) DESC, u.first_name
                    LIMIT 500
                ");
                $allRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {}
            ?>

            <?php if (empty($allRecords)): ?>
                <div style="text-align:center;padding:50px 20px;color:#95A5A6;">
                    <i class="fas fa-clipboard-list" style="font-size:48px;opacity:.3;display:block;margin-bottom:16px;"></i>
                    <div style="font-size:16px;font-weight:600;margin-bottom:8px;">No attendance records yet</div>
                    <div style="font-size:13px;">Go to the <strong>Mark Attendance</strong> tab, mark your team, and click <strong>Save Attendance</strong>.</div>
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="data-table" id="records-table">
                    <thead><tr>
                        <th>Date</th><th>Employee</th><th>Status</th>
                        <th>Check-In</th><th>Check-Out</th><th>Notes</th><th class="no-print">Dispute</th><th class="no-print">Actions</th>
                    </tr></thead>
                    <tbody id="records-tbody">
                    <?php foreach ($allRecords as $rec):
                        $sc = ['present'=>'#27AE60','absent'=>'#E74C3C','late'=>'#F39C12','half_day'=>'#9B59B6'][$rec['status']] ?? '#7F8C8D';
                        $si = ['present'=>'fa-check-circle','absent'=>'fa-times-circle','late'=>'fa-clock','half_day'=>'fa-adjust'][$rec['status']] ?? 'fa-circle';
                        $sl = ['present'=>'Present','absent'=>'Absent','late'=>'Late','half_day'=>'Half Day'][$rec['status']] ?? ucfirst($rec['status']);
                        $dispute = $openDisputes[$rec['id']] ?? null;
                    ?>
                    <tr data-status="<?php echo htmlspecialchars($rec['status']); ?>"
                        data-date="<?php echo htmlspecialchars($rec['att_date']); ?>"
                        style="<?php echo $dispute ? 'background:#FEF9E7;' : ''; ?>">
                        <td style="font-weight:600;"><?php echo date('M d, Y', strtotime($rec['att_date'])); ?></td>
                        <td><?php echo htmlspecialchars(($rec['first_name'] ?? '') . ' ' . ($rec['last_name'] ?? '')); ?></td>
                        <td>
                            <span style="display:inline-flex;align-items:center;gap:5px;background:<?php echo $sc; ?>18;color:<?php echo $sc; ?>;border-radius:20px;padding:4px 12px;font-size:12px;font-weight:700;">
                                <i class="fas <?php echo $si; ?>"></i> <?php echo $sl; ?>
                            </span>
                        </td>
                        <td style="font-size:13px;"><?php echo $rec['ci_time'] ? date('h:i A', strtotime($rec['ci_time'])) : '<span style="color:#BDC3C7;">—</span>'; ?></td>
                        <td style="font-size:13px;"><?php echo !empty($rec['check_out_time']) ? date('h:i A', strtotime($rec['check_out_time'])) : '<span style="color:#BDC3C7;">—</span>'; ?></td>
                        <td style="font-size:12px;color:#7F8C8D;max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo htmlspecialchars($rec['notes'] ?: '—'); ?></td>
                        <td class="no-print">
                            <?php if ($dispute): ?>
                            <div style="display:flex;flex-direction:column;gap:4px;">
                                <span style="display:inline-flex;align-items:center;gap:4px;background:#FDEBD0;color:#E67E22;border-radius:12px;padding:3px 8px;font-size:11px;font-weight:700;white-space:nowrap;">
                                    <i class="fas fa-flag"></i> Dispute
                                </span>
                                <span style="font-size:10px;color:#7F8C8D;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo htmlspecialchars($dispute['reason']); ?>">
                                    <?php echo htmlspecialchars(mb_strimwidth($dispute['reason'], 0, 40, '…')); ?>
                                </span>
                            </div>
                            <?php else: ?>
                            <span style="color:#BDC3C7;font-size:12px;">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="no-print" style="white-space:nowrap;">
                            <button type="button"
                                onclick="openViewModal(<?php echo $rec['id']; ?>,'<?php echo htmlspecialchars($rec['att_date']); ?>','<?php echo $rec['status']; ?>','<?php echo $rec['ci_time'] ? date('h:i A', strtotime($rec['ci_time'])) : ''; ?>','<?php echo !empty($rec['co_time']) ? date('h:i A', strtotime($rec['co_time'])) : ''; ?>','<?php echo addslashes($rec['notes'] ?? ''); ?>','<?php echo addslashes(($rec['first_name'] ?? '').' '.($rec['last_name'] ?? '')); ?>',<?php echo $dispute ? $dispute['id'] : 'null'; ?>,'<?php echo $dispute ? addslashes($dispute['reason']) : ''; ?>')"
                                style="padding:6px 10px;background:#27AE6018;color:#27AE60;border:1.5px solid #27AE6055;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;margin-bottom:4px;display:block;width:100%;text-align:center;">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <button type="button"
                                onclick="openEditModal(<?php echo $rec['id']; ?>,'<?php echo htmlspecialchars($rec['att_date']); ?>','<?php echo $rec['status']; ?>','<?php echo $rec['ci_time'] ? substr($rec['ci_time'],0,5) : '08:00'; ?>','<?php echo $rec['co_time'] ? substr($rec['co_time'],0,5) : ''; ?>','<?php echo addslashes($rec['notes'] ?? ''); ?>',<?php echo $dispute ? $dispute['id'] : 'null'; ?>,'<?php echo $dispute ? addslashes($dispute['reason']) : ''; ?>')"
                                style="padding:6px 10px;background:#3498DB18;color:#3498DB;border:1.5px solid #3498DB55;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;margin-bottom:4px;display:block;width:100%;text-align:center;">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <form method="POST" style="display:block;" onsubmit="return confirm('Delete this record?')">
                                <input type="hidden" name="action" value="delete_attendance">
                                <input type="hidden" name="attendance_id" value="<?php echo $rec['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <button type="submit" class="btn-action btn-danger-custom" style="padding:6px 10px;width:100%;"><i class="fas fa-trash"></i> Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div id="rec-no-results" style="display:none;text-align:center;padding:40px 20px;color:#95A5A6;">
                <i class="fas fa-search" style="font-size:36px;opacity:.3;display:block;margin-bottom:12px;"></i>
                <div style="font-size:15px;font-weight:600;">No records match the filter</div>
            </div>
            <?php endif; ?>
        </div>
        </div><!-- end tab-records -->

        <!-- ══════════════════════════════════════════════
             TAB 3: MONTHLY SUMMARY
        ══════════════════════════════════════════════ -->
        <div id="tab-summary" class="tab-pane">
        <!-- Print header for summary -->
        <div class="print-header">
            <h2>FurnitureCraft — 30-Day Attendance Summary</h2>
            <p>Period: <strong><?php echo date('d/m/Y', strtotime('-30 days')); ?></strong> to <strong><?php echo date('d/m/Y'); ?></strong> &nbsp;|&nbsp; Manager: <strong><?php echo htmlspecialchars($managerName); ?></strong></p>
        </div>
        <div class="section-card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h2 class="section-title" style="margin:0;"><i class="fas fa-chart-bar" style="color:#9B59B6;"></i> 30-Day Attendance Summary</h2>
                <button type="button" onclick="printTab('tab-summary')" class="no-print" style="padding:8px 16px;background:#9B59B6;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
                    <i class="fas fa-print"></i> Print Summary
                </button>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-bottom:24px;">
                <?php foreach ($employees as $emp):
                    $empId = $emp['id'];
                    $initials = strtoupper(substr($emp['first_name'],0,1).substr($emp['last_name'],0,1));
                    $avatarColors = ['#3498DB','#27AE60','#E74C3C','#9B59B6','#F39C12','#1ABC9C','#E67E22','#2980B9'];
                    $avatarColor  = $avatarColors[$empId % count($avatarColors)];
                    // Count per employee
                    try {
                        $s = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM furn_attendance WHERE employee_id=? AND COALESCE(date, DATE(check_in_time)) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY status");
                        $s->execute([$empId]);
                        $empStats = ['present'=>0,'absent'=>0,'late'=>0,'half_day'=>0];
                        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) { if (isset($empStats[$r['status']])) $empStats[$r['status']] = (int)$r['cnt']; }
                        $empTotal = array_sum($empStats);
                        $empRate  = $empTotal > 0 ? round((($empStats['present']+$empStats['late']+$empStats['half_day']) / $empTotal) * 100) : 0;
                    } catch (PDOException $e) { $empStats=['present'=>0,'absent'=>0,'late'=>0,'half_day'=>0]; $empRate=0; }
                    $rateColor = $empRate >= 90 ? '#27AE60' : ($empRate >= 70 ? '#F39C12' : '#E74C3C');
                ?>
                <div style="background:#fff;border:1.5px solid #F0F0F0;border-radius:12px;padding:16px;display:flex;align-items:center;gap:14px;">
                    <div class="emp-avatar-placeholder" style="background:<?php echo $avatarColor; ?>;width:46px;height:46px;font-size:17px;flex-shrink:0;"><?php echo $initials; ?></div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:700;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($emp['first_name'].' '.$emp['last_name']); ?></div>
                        <div style="font-size:11px;color:#95A5A6;margin-bottom:8px;"><?php echo htmlspecialchars($emp['position']); ?></div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <span style="font-size:11px;background:#27AE6018;color:#27AE60;border-radius:10px;padding:2px 8px;font-weight:600;"><?php echo $empStats['present']; ?> P</span>
                            <span style="font-size:11px;background:#E74C3C18;color:#E74C3C;border-radius:10px;padding:2px 8px;font-weight:600;"><?php echo $empStats['absent']; ?> A</span>
                            <span style="font-size:11px;background:#F39C1218;color:#F39C12;border-radius:10px;padding:2px 8px;font-weight:600;"><?php echo $empStats['late']; ?> L</span>
                            <span style="font-size:11px;background:#9B59B618;color:#9B59B6;border-radius:10px;padding:2px 8px;font-weight:600;"><?php echo $empStats['half_day']; ?> H</span>
                        </div>
                    </div>
                    <div style="text-align:center;flex-shrink:0;">
                        <div style="font-size:22px;font-weight:800;color:<?php echo $rateColor; ?>;"><?php echo $empRate; ?>%</div>
                        <div style="font-size:10px;color:#95A5A6;font-weight:600;">RATE</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        </div><!-- end tab-summary -->

        <!-- ══════════════════════════════════════════════
             OVERTIME TABLE (always visible below all tabs)
        ══════════════════════════════════════════════ -->
        <div class="section-card" style="margin-top:20px;" id="overtimeSection">
            <!-- Print header -->
            <div class="print-header">
                <h2>FurnitureCraft — Overtime Record</h2>
                <p>Date: <strong id="printOTDate"><?php echo date('d/m/Y'); ?></strong> &nbsp;|&nbsp; Manager: <strong><?php echo htmlspecialchars($managerName); ?></strong></p>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
                <div>
                    <h2 class="section-title" style="margin:0;"><i class="fas fa-clock" style="color:#F39C12;"></i> Overtime Hours</h2>
                    <p style="margin:4px 0 0;font-size:13px;color:#7F8C8D;"><?php echo count($employees); ?> employees &nbsp;|&nbsp; Enter overtime hours worked beyond standard 8hrs/day</p>
                </div>
                <div style="display:flex;gap:8px;" class="no-print">
                    <button onclick="printTab('overtimeSection')" style="padding:8px 16px;background:#F39C12;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
                        <i class="fas fa-print"></i> Print OT Sheet
                    </button>
                </div>
            </div>

            <?php if (empty($employees)): ?>
                <p style="text-align:center;color:#95A5A6;padding:30px 0;">No employees found.</p>
            <?php else: ?>
            <form method="POST" action="<?php echo BASE_URL; ?>/public/manager/attendance" id="otForm">
                <input type="hidden" name="action" value="save_overtime">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="date" id="otDate" value="<?php echo date('Y-m-d'); ?>">

                <div class="table-responsive">
                <table class="att-table" id="otTable">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th>
                            <th>Employee</th>
                            <th>Position</th>
                            <th style="text-align:center;width:120px;">OT Hours</th>
                            <th style="text-align:center;width:130px;">OT Rate (ETB/hr)</th>
                            <th style="text-align:center;width:140px;">OT Pay (ETB)</th>
                            <th class="no-print">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($employees as $i => $emp):
                        $empId = $emp['id'];
                        $initials = strtoupper(substr($emp['first_name'],0,1).substr($emp['last_name'],0,1));
                        $avatarColors = ['#3498DB','#27AE60','#E74C3C','#9B59B6','#F39C12','#1ABC9C','#E67E22','#2980B9'];
                        $avatarColor  = $avatarColors[$empId % count($avatarColors)];

                        // Fetch OT rate from salary config
                        $otRate = 0;
                        try {
                            $sr = $pdo->prepare("SELECT overtime_rate FROM furn_employee_salary WHERE employee_id=?");
                            $sr->execute([$empId]);
                            $otRate = floatval($sr->fetchColumn() ?: 0);
                        } catch(PDOException $e){}

                        // Fetch today's existing OT hours from attendance
                        $existOT = 0;
                        $existOTNote = '';
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
                    <tr id="ot-row-<?php echo $empId; ?>">
                        <td style="font-weight:600;color:#95A5A6;font-size:13px;"><?php echo $i+1; ?></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div class="emp-avatar-placeholder" style="background:<?php echo $avatarColor; ?>;"><?php echo $initials; ?></div>
                                <div>
                                    <div style="font-weight:600;font-size:13px;"><?php echo htmlspecialchars($emp['first_name'].' '.$emp['last_name']); ?></div>
                                    <div style="font-size:11px;color:#95A5A6;"><?php echo htmlspecialchars($emp['email']); ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span style="background:#3498DB18;color:#3498DB;border-radius:12px;padding:2px 10px;font-size:11px;font-weight:600;">
                                <?php echo htmlspecialchars($emp['position'] ?? 'Employee'); ?>
                            </span>
                        </td>
                        <td style="text-align:center;">
                            <input type="number" name="ot_hours[<?php echo $empId; ?>]"
                                   id="ot-hrs-<?php echo $empId; ?>"
                                   value="<?php echo $existOT; ?>"
                                   min="0" max="24" step="0.5"
                                   style="width:90px;padding:7px 10px;border:2px solid #E0E0E0;border-radius:8px;font-size:14px;font-weight:700;text-align:center;font-family:inherit;outline:none;color:#F39C12;"
                                   oninput="calcOTPay(<?php echo $empId; ?>, <?php echo $otRate; ?>)"
                                   onfocus="this.style.borderColor='#F39C12'" onblur="this.style.borderColor='#E0E0E0'">
                        </td>
                        <td style="text-align:center;font-weight:600;color:#555;">
                            ETB <?php echo number_format($otRate, 2); ?>
                            <input type="hidden" name="ot_rate[<?php echo $empId; ?>]" value="<?php echo $otRate; ?>">
                        </td>
                        <td style="text-align:center;font-weight:700;color:#27AE60;" id="ot-pay-<?php echo $empId; ?>">
                            ETB <?php echo number_format($existOT * $otRate, 2); ?>
                        </td>
                        <td class="no-print">
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
                    <div class="no-print">
                        <button type="submit" style="padding:11px 28px;background:linear-gradient(135deg,#F39C12,#E67E22);color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;">
                            <i class="fas fa-save"></i> Save Overtime
                        </button>
                    </div>
                </div>
            </form>
            <?php endif; ?>
        </div>

    </div><!-- end main-content -->

    <!-- ── View Attendance Modal ── -->
    <div id="viewModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:14px;padding:28px;width:100%;max-width:480px;margin:20px;box-shadow:0 20px 60px rgba(0,0,0,.3);max-height:90vh;overflow-y:auto;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h3 style="margin:0;font-size:17px;color:#2C3E50;"><i class="fas fa-eye" style="color:#27AE60;margin-right:8px;"></i> Attendance Record</h3>
                <button onclick="closeViewModal()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#95A5A6;">&times;</button>
            </div>

            <!-- Dispute alert (shown only when there's an open dispute) -->
            <div id="viewDisputeBox" style="display:none;background:#FEF9E7;border:1.5px solid #F39C12;border-radius:8px;padding:12px 16px;margin-bottom:16px;">
                <div style="font-size:12px;font-weight:700;color:#E67E22;margin-bottom:6px;"><i class="fas fa-flag"></i> Employee Reported an Error</div>
                <div id="viewDisputeReason" style="font-size:13px;color:#555;line-height:1.5;"></div>
            </div>

            <!-- Record details grid -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px;">
                <div style="background:#F8F9FA;border-radius:8px;padding:12px;">
                    <div style="font-size:11px;font-weight:700;color:#95A5A6;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Employee</div>
                    <div id="viewEmpName" style="font-size:14px;font-weight:700;color:#2C3E50;"></div>
                </div>
                <div style="background:#F8F9FA;border-radius:8px;padding:12px;">
                    <div style="font-size:11px;font-weight:700;color:#95A5A6;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Date</div>
                    <div id="viewDate" style="font-size:14px;font-weight:700;color:#2C3E50;"></div>
                </div>
                <div style="background:#F8F9FA;border-radius:8px;padding:12px;">
                    <div style="font-size:11px;font-weight:700;color:#95A5A6;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Status</div>
                    <div id="viewStatus"></div>
                </div>
                <div style="background:#F8F9FA;border-radius:8px;padding:12px;">
                    <div style="font-size:11px;font-weight:700;color:#95A5A6;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Check-In</div>
                    <div id="viewCheckIn" style="font-size:14px;font-weight:600;color:#2C3E50;"></div>
                </div>
                <div style="background:#F8F9FA;border-radius:8px;padding:12px;">
                    <div style="font-size:11px;font-weight:700;color:#95A5A6;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Check-Out</div>
                    <div id="viewCheckOut" style="font-size:14px;font-weight:600;color:#2C3E50;"></div>
                </div>
                <div style="background:#F8F9FA;border-radius:8px;padding:12px;">
                    <div style="font-size:11px;font-weight:700;color:#95A5A6;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Notes</div>
                    <div id="viewNotes" style="font-size:13px;color:#555;"></div>
                </div>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" onclick="closeViewModal()" style="padding:10px 20px;background:#F0F2F5;color:#555;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">Close</button>
            </div>
        </div>
    </div>

    <!-- ── Edit Attendance Modal ── -->
    <div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:14px;padding:28px;width:100%;max-width:500px;margin:20px;box-shadow:0 20px 60px rgba(0,0,0,.3);max-height:90vh;overflow-y:auto;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h3 style="margin:0;font-size:17px;color:#2C3E50;"><i class="fas fa-edit" style="color:#3498DB;margin-right:8px;"></i> Edit Attendance Record</h3>
                <button onclick="closeEditModal()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#95A5A6;">&times;</button>
            </div>

            <!-- Dispute info box (shown only when there's a dispute) -->
            <div id="editDisputeBox" style="display:none;background:#FEF9E7;border:1.5px solid #F39C12;border-radius:8px;padding:12px 16px;margin-bottom:16px;">
                <div style="font-size:12px;font-weight:700;color:#E67E22;margin-bottom:4px;"><i class="fas fa-flag"></i> Employee Reported an Error</div>
                <div id="editDisputeReason" style="font-size:13px;color:#555;"></div>
            </div>

            <div id="editRecordInfo" style="background:#F8F9FA;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:13px;color:#555;"></div>

            <form method="POST">
                <input type="hidden" name="action" value="edit_attendance">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="attendance_id" id="editAttId">

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                    <div>
                        <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:5px;">Status <span style="color:#E74C3C;">*</span></label>
                        <select name="new_status" id="editStatus" class="form-control" style="width:100%;">
                            <option value="present">Present</option>
                            <option value="absent">Absent</option>
                            <option value="late">Late</option>
                            <option value="half_day">Half Day</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:5px;">Check-In Time</label>
                        <input type="time" name="new_check_in" id="editCheckIn" class="form-control" style="width:100%;">
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:5px;">Check-Out Time</label>
                        <input type="time" name="new_check_out" id="editCheckOut" class="form-control" style="width:100%;">
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:5px;">Notes</label>
                        <input type="text" name="new_notes" id="editNotes" class="form-control" placeholder="Optional..." style="width:100%;">
                    </div>
                </div>

                <div id="editResolveRow" style="display:none;margin-bottom:14px;">
                    <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#555;cursor:pointer;">
                        <input type="checkbox" name="resolve_dispute_on_edit" value="1" checked style="width:16px;height:16px;">
                        Mark dispute as resolved after saving
                    </label>
                </div>

                <div style="display:flex;gap:10px;justify-content:flex-end;">
                    <button type="button" onclick="closeEditModal()" style="padding:10px 20px;background:#F0F2F5;color:#555;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">Cancel</button>
                    <button type="submit" style="padding:10px 24px;background:linear-gradient(135deg,#2C3E50,#3498DB);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
    <script>
    function openViewModal(attId, date, status, ci, co, notes, empName, disputeId, disputeReason) {
        const statusLabels = { present:'Present', absent:'Absent', late:'Late', half_day:'Half Day' };
        const statusColors = { present:'#27AE60', absent:'#E74C3C', late:'#F39C12', half_day:'#9B59B6' };
        const statusIcons  = { present:'fa-check-circle', absent:'fa-times-circle', late:'fa-clock', half_day:'fa-adjust' };
        const sc = statusColors[status] || '#7F8C8D';
        const si = statusIcons[status]  || 'fa-circle';
        const sl = statusLabels[status] || status;

        document.getElementById('viewEmpName').textContent = empName || '—';
        document.getElementById('viewDate').textContent    = new Date(date + 'T00:00:00').toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'});
        document.getElementById('viewStatus').innerHTML    = '<span style="display:inline-flex;align-items:center;gap:5px;background:'+sc+'18;color:'+sc+';border-radius:20px;padding:4px 12px;font-size:12px;font-weight:700;"><i class="fas '+si+'"></i> '+sl+'</span>';
        document.getElementById('viewCheckIn').textContent  = ci  || '—';
        document.getElementById('viewCheckOut').textContent = co  || '—';
        document.getElementById('viewNotes').textContent    = notes || '—';

        const dispBox = document.getElementById('viewDisputeBox');
        if (disputeId) {
            dispBox.style.display = 'block';
            document.getElementById('viewDisputeReason').textContent = disputeReason;
        } else {
            dispBox.style.display = 'none';
        }
        document.getElementById('viewModal').style.display = 'flex';
    }
    function closeViewModal() {
        document.getElementById('viewModal').style.display = 'none';
    }
    document.getElementById('viewModal').addEventListener('click', function(e) {
        if (e.target === this) closeViewModal();
    });

    function openEditModal(attId, date, status, ci, co, notes, disputeId, disputeReason) {
        document.getElementById('editAttId').value   = attId;
        document.getElementById('editStatus').value  = status;
        document.getElementById('editCheckIn').value = ci || '';
        document.getElementById('editCheckOut').value= co || '';
        document.getElementById('editNotes').value   = notes || '';
        document.getElementById('editRecordInfo').innerHTML = '<strong>Date:</strong> ' + new Date(date + 'T00:00:00').toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'});

        const dispBox = document.getElementById('editDisputeBox');
        const resolveRow = document.getElementById('editResolveRow');
        if (disputeId) {
            dispBox.style.display = 'block';
            resolveRow.style.display = 'block';
            document.getElementById('editDisputeReason').textContent = disputeReason;
        } else {
            dispBox.style.display = 'none';
            resolveRow.style.display = 'none';
        }
        document.getElementById('editModal').style.display = 'flex';
    }
    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }
    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === this) closeEditModal();
    });
    // ── Tab switching ──
    function switchTab(tabId, btn) {
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById(tabId).classList.add('active');
        btn.classList.add('active');
    }

    // ── Date sync ──
    document.getElementById('sheetDate').addEventListener('change', function() {
        document.getElementById('bulkDate').value = this.value;
        if (document.getElementById('otDate')) document.getElementById('otDate').value = this.value;
        const d = new Date(this.value);
        document.getElementById('printDate').textContent = d.toLocaleDateString('en-GB');
        if (document.getElementById('printOTDate')) document.getElementById('printOTDate').textContent = d.toLocaleDateString('en-GB');
    });

    // ── Status toggle ──
    const statuses = ['present','absent','late','half_day'];
    const statusColors = { present:'#27AE60', absent:'#E74C3C', late:'#F39C12', half_day:'#9B59B6' };

    function setStatus(empId, status) {
        document.getElementById('status-'+empId).value = status;
        const row = document.getElementById('row-'+empId);
        row.className = 'row-'+status;

        statuses.forEach(s => {
            const btn = document.getElementById('btn-'+s+'-'+empId);
            if (!btn) return;
            const color = statusColors[s];
            if (s === status) {
                btn.style.background = color;
                btn.style.boxShadow = '0 3px 10px '+color+'55';
                btn.querySelector('i').style.color = '#fff';
            } else {
                btn.style.background = '#fff';
                btn.style.boxShadow = 'none';
                btn.querySelector('i').style.color = color;
            }
        });
        updateCount();
    }

    function setAllStatus(status) {
        <?php foreach ($employees as $emp): ?>
        setStatus(<?php echo $emp['id']; ?>, status);
        <?php endforeach; ?>
    }

    function clearAll() {
        <?php foreach ($employees as $emp): ?>
        document.getElementById('status-<?php echo $emp['id']; ?>').value = '';
        document.getElementById('row-<?php echo $emp['id']; ?>').className = '';
        statuses.forEach(s => {
            const btn = document.getElementById('btn-'+s+'-<?php echo $emp['id']; ?>');
            if (!btn) return;
            btn.style.background = '#fff';
            btn.style.boxShadow = 'none';
            btn.querySelector('i').style.color = statusColors[s];
        });
        <?php endforeach; ?>
        updateCount();
    }

    function updateCount() {
        const count = document.querySelectorAll('input[id^="status-"]').length;
        let marked = 0;
        document.querySelectorAll('input[id^="status-"]').forEach(i => { if (i.value) marked++; });
        document.getElementById('markedCount').textContent = marked;
    }

    // Init count
    updateCount();

    // ── Pre-submit: default unmarked employees to 'absent' ──
    document.getElementById('bulkForm').addEventListener('submit', function(e) {
        document.querySelectorAll('input[id^="status-"]').forEach(function(inp) {
            if (!inp.value || !['present','absent','late','half_day'].includes(inp.value)) {
                inp.value = 'absent';
            }
        });
        // Sync date
        const dateEl = document.getElementById('sheetDate');
        if (dateEl) document.getElementById('bulkDate').value = dateEl.value;
    });

    // ── Records tab: live filter by date + status ──
    function applyRecordFilters() {
        const dateVal   = document.getElementById('rec-filter-date').value;
        const statusVal = document.getElementById('rec-filter-status').value;
        const rows      = document.querySelectorAll('#records-tbody tr');
        let visible = 0;
        rows.forEach(row => {
            const rowDate   = row.dataset.date   || '';
            const rowStatus = row.dataset.status || '';
            const matchDate   = !dateVal   || rowDate   === dateVal;
            const matchStatus = !statusVal || rowStatus === statusVal;
            if (matchDate && matchStatus) {
                row.style.display = '';
                visible++;
            } else {
                row.style.display = 'none';
            }
        });
        const countEl     = document.getElementById('rec-count');
        const noResults   = document.getElementById('rec-no-results');
        const tableEl     = document.getElementById('records-table');
        if (countEl)   countEl.textContent = visible + ' record(s)';
        if (noResults) noResults.style.display = visible === 0 ? 'block' : 'none';
        if (tableEl)   tableEl.style.display   = visible === 0 ? 'none'  : '';
    }

    function clearRecordFilters() {
        document.getElementById('rec-filter-date').value   = '';
        document.getElementById('rec-filter-status').value = '';
        applyRecordFilters();
    }

    // Run on page load to set initial count
    document.addEventListener('DOMContentLoaded', function() {
        if (document.getElementById('records-tbody')) applyRecordFilters();
    });

    // ── OT Pay live calculation ──
    function calcOTPay(empId, rate) {
        const hrs = parseFloat(document.getElementById('ot-hrs-'+empId).value) || 0;
        const pay = hrs * rate;
        document.getElementById('ot-pay-'+empId).textContent = 'ETB ' + pay.toFixed(2);
    }

    // ── Print: only print the target tab ──
    function printTab(tabId) {
        document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('print-target'));
        document.getElementById(tabId).classList.add('print-target');

        // Sync sheet date label
        const sheetDateEl = document.getElementById('sheetDate');
        const printSheetDate = document.getElementById('printSheetDate');
        if (sheetDateEl && printSheetDate) {
            const d = new Date(sheetDateEl.value);
            printSheetDate.textContent = d.toLocaleDateString('en-GB');
        }

        window.print();

        setTimeout(function() {
            document.getElementById(tabId).classList.remove('print-target');
        }, 1500);
    }
    </script>
</body>
</html>
