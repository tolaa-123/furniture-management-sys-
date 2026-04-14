<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . BASE_URL . '/public/login'); exit();
}
require_once __DIR__ . '/../../../config/db_config.php';
$csrf_token = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;
$adminName = $_SESSION['user_name'] ?? 'Admin';

// ── Ensure tables exist ──
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS furn_employee_salary (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL UNIQUE,
        base_salary DECIMAL(10,2) NOT NULL DEFAULT 0,
        working_days_per_month INT NOT NULL DEFAULT 26,
        overtime_rate DECIMAL(10,2) NOT NULL DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS furn_payroll (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL, month INT NOT NULL, year INT NOT NULL,
        present_days INT NOT NULL DEFAULT 0, half_day_count INT NOT NULL DEFAULT 0,
        absent_days INT NOT NULL DEFAULT 0, late_days INT NOT NULL DEFAULT 0,
        overtime_hours DECIMAL(5,2) NOT NULL DEFAULT 0,
        base_salary DECIMAL(10,2) NOT NULL DEFAULT 0,
        bonus DECIMAL(10,2) NOT NULL DEFAULT 0,
        working_days_per_month INT NOT NULL DEFAULT 26,
        overtime_rate DECIMAL(10,2) NOT NULL DEFAULT 0,
        basic_earned DECIMAL(10,2) NOT NULL DEFAULT 0,
        overtime_pay DECIMAL(10,2) NOT NULL DEFAULT 0,
        gross_salary DECIMAL(10,2) NOT NULL DEFAULT 0,
        tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        other_deductions DECIMAL(10,2) NOT NULL DEFAULT 0,
        net_salary DECIMAL(10,2) NOT NULL DEFAULT 0,
        payment_date DATE NULL,
        status ENUM('draft','pending_approval','approved','rejected') NOT NULL DEFAULT 'draft',
        notes TEXT NULL, calculated_by INT NULL, approved_by INT NULL,
        approved_at TIMESTAMP NULL, rejection_reason TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_emp_month(employee_id, month, year)
    )");
    $cols = $pdo->query("SHOW COLUMNS FROM furn_payroll")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('bonus', $cols))            $pdo->exec("ALTER TABLE furn_payroll ADD COLUMN bonus DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER base_salary");
    if (!in_array('payment_date', $cols))     $pdo->exec("ALTER TABLE furn_payroll ADD COLUMN payment_date DATE NULL AFTER net_salary");
    if (!in_array('approved_by', $cols))      $pdo->exec("ALTER TABLE furn_payroll ADD COLUMN approved_by INT NULL");
    if (!in_array('approved_at', $cols))      $pdo->exec("ALTER TABLE furn_payroll ADD COLUMN approved_at TIMESTAMP NULL");
    if (!in_array('rejection_reason', $cols)) $pdo->exec("ALTER TABLE furn_payroll ADD COLUMN rejection_reason TEXT NULL");
    if (!in_array('calculated_by', $cols))    $pdo->exec("ALTER TABLE furn_payroll ADD COLUMN calculated_by INT NULL");
    if (!in_array('basic_earned', $cols))     $pdo->exec("ALTER TABLE furn_payroll ADD COLUMN basic_earned DECIMAL(10,2) NOT NULL DEFAULT 0");
    if (!in_array('overtime_pay', $cols))     $pdo->exec("ALTER TABLE furn_payroll ADD COLUMN overtime_pay DECIMAL(10,2) NOT NULL DEFAULT 0");
} catch (PDOException $e) {}

// ── Handle POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Invalid CSRF token.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'approve_payroll') {
            try {
                $payrollId = (int)$_POST['payroll_id'];
                // Get employee_id before updating
                $pr = $pdo->prepare("SELECT employee_id FROM furn_payroll WHERE id=?");
                $pr->execute([$payrollId]); $prRow = $pr->fetch(PDO::FETCH_ASSOC);
                $pdo->prepare("UPDATE furn_payroll SET status='approved', approved_by=?, approved_at=NOW() WHERE id=? AND status='pending_approval'")
                    ->execute([$_SESSION['user_id'], $payrollId]);
                // Notify employee
                require_once __DIR__ . '/../../../app/includes/notification_helper.php';
                if ($prRow) insertNotification($pdo, $prRow['employee_id'], 'payroll', 'Payroll Approved',
                    'Your payroll for ' . date('F Y') . ' has been approved by admin.',
                    $payrollId, '/employee/payroll', 'high');
                $_SESSION['adm_pay_success'] = "Payroll approved successfully.";
                header('Location: ' . BASE_URL . '/public/admin/payroll'); exit();
            } catch (PDOException $e) { $error = $e->getMessage(); }

        } elseif ($action === 'reject_payroll') {
            try {
                $reason = trim($_POST['rejection_reason'] ?? '');
                $pdo->prepare("UPDATE furn_payroll SET status='rejected', rejection_reason=? WHERE id=? AND status='pending_approval'")
                    ->execute([$reason, (int)$_POST['payroll_id']]);
                $_SESSION['adm_pay_success'] = "Payroll rejected.";
                header('Location: ' . BASE_URL . '/public/admin/payroll'); exit();
            } catch (PDOException $e) { $error = $e->getMessage(); }

        } elseif ($action === 'bulk_approve') {
            $ids = array_map('intval', $_POST['bulk_ids'] ?? []);
            if (!empty($ids)) {
                try {
                    $ph = implode(',', array_fill(0, count($ids), '?'));
                    $pdo->prepare("UPDATE furn_payroll SET status='approved', approved_by=?, approved_at=NOW() WHERE id IN ($ph) AND status='pending_approval'")
                        ->execute(array_merge([$_SESSION['user_id']], $ids));
                    $_SESSION['adm_pay_success'] = count($ids) . " payroll record(s) approved.";
                } catch (PDOException $e) { $_SESSION['adm_pay_error'] = $e->getMessage(); }
            }
            header('Location: ' . BASE_URL . '/public/admin/payroll'); exit();

        } elseif ($action === 'mark_paid') {
            try {
                $pdo->prepare("UPDATE furn_payroll SET payment_date=? WHERE id=? AND status='approved'")
                    ->execute([$_POST['paid_date'] ?? date('Y-m-d'), (int)$_POST['payroll_id']]);
                $_SESSION['adm_pay_success'] = "Payment date recorded.";
                header('Location: ' . BASE_URL . '/public/admin/payroll'); exit();
            } catch (PDOException $e) { $error = $e->getMessage(); }
        }
    }
}
if (isset($_SESSION['adm_pay_success'])) { $success = $_SESSION['adm_pay_success']; unset($_SESSION['adm_pay_success']); }
if (isset($_SESSION['adm_pay_error']))   { $error   = $_SESSION['adm_pay_error'];   unset($_SESSION['adm_pay_error']); }

// ── Filter params ──
$filterMonth  = isset($_GET['fm']) ? (int)$_GET['fm'] : 0;
$filterYear   = isset($_GET['fy']) ? (int)$_GET['fy'] : 0;
$filterStatus = $_GET['fs'] ?? '';

// ── Stats ──
$stats = ['total_employees'=>0,'pending'=>0,'approved_month'=>0,'total_net_month'=>0,'total_paid_year'=>0];
try {
    $stats['total_employees'] = $pdo->query("SELECT COUNT(*) FROM furn_users WHERE role='employee'")->fetchColumn();
    $stats['pending']         = $pdo->query("SELECT COUNT(*) FROM furn_payroll WHERE status='pending_approval'")->fetchColumn();
    $cm = date('n'); $cy = date('Y');
    $r = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(net_salary),0) as tot FROM furn_payroll WHERE month=? AND year=? AND status='approved'");
    $r->execute([$cm,$cy]); $row = $r->fetch();
    $stats['approved_month']  = $row['cnt'];
    $stats['total_net_month'] = $row['tot'];
    $r2 = $pdo->prepare("SELECT COALESCE(SUM(net_salary),0) as tot FROM furn_payroll WHERE year=? AND status='approved'");
    $r2->execute([$cy]); $stats['total_paid_year'] = $r2->fetchColumn();
} catch (PDOException $e) {}

// ── Fetch records ──
$records = [];
try {
    $where = []; $params = [];
    if ($filterMonth)  { $where[] = 'p.month=?';  $params[] = $filterMonth; }
    if ($filterYear)   { $where[] = 'p.year=?';   $params[] = $filterYear; }
    if ($filterStatus) { $where[] = 'p.status=?'; $params[] = $filterStatus; }
    $whereSQL = $where ? 'WHERE '.implode(' AND ',$where) : '';
    $stmt = $pdo->prepare("
        SELECT p.*, u.first_name, u.last_name, u.email,
               m.first_name as mgr_fn, m.last_name as mgr_ln
        FROM furn_payroll p
        LEFT JOIN furn_users u ON p.employee_id = u.id
        LEFT JOIN furn_users m ON p.calculated_by = m.id
        $whereSQL ORDER BY p.year DESC, p.month DESC, p.created_at DESC
    ");
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $error = $e->getMessage(); }

$monthNames = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$monthFull  = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Management - FurnitureCraft Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/css/admin-responsive.css">
    <style>
        .pay-header{background:linear-gradient(135deg,#1a252f 0%,#2C3E50 50%,#8E44AD 100%);color:#fff;padding:28px 30px;border-radius:14px;margin-bottom:24px;}
        .s-badge{display:inline-flex;align-items:center;gap:5px;border-radius:20px;padding:4px 12px;font-size:12px;font-weight:700;}
        .s-draft{background:#95A5A618;color:#7F8C8D;border:1px solid #95A5A640;}
        .s-pending{background:#F39C1218;color:#E67E22;border:1px solid #F39C1240;}
        .s-approved{background:#27AE6018;color:#27AE60;border:1px solid #27AE6040;}
        .s-rejected{background:#E74C3C18;color:#E74C3C;border:1px solid #E74C3C40;}
        .cb-cell{width:40px;text-align:center;}
        input[type=checkbox].row-cb{width:16px;height:16px;cursor:pointer;accent-color:#8E44AD;}
        #bulkBar{display:none;align-items:center;gap:12px;background:#8E44AD10;border:1.5px solid #8E44AD30;border-radius:10px;padding:10px 16px;margin-bottom:14px;flex-wrap:wrap;}
        #bulkBar.show{display:flex;}
        .filter-bar{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;background:#F8F9FA;border:1.5px solid #E8ECEF;border-radius:10px;padding:14px 16px;margin-bottom:18px;}
        .filter-bar select{padding:7px 10px;border:1.5px solid #E0E0E0;border-radius:7px;font-family:inherit;font-size:12px;outline:none;background:#fff;}
        .filter-bar label{font-size:11px;font-weight:700;color:#555;display:block;margin-bottom:3px;text-transform:uppercase;letter-spacing:.3px;}
        .btn-sm{padding:6px 12px;border-radius:7px;font-size:12px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:5px;border:1.5px solid transparent;text-decoration:none;}
    </style>
</head>
<body>
    <button class="mobile-menu-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>
    <div class="sidebar-overlay"></div>
    <?php include_once __DIR__ . '/../../includes/admin_sidebar.php'; ?>
    <!-- Top Header -->
    <?php 
    $pageTitle = 'Payroll';
    include_once __DIR__ . '/../../includes/admin_header.php'; 
    ?>
        <div class="header-left"><div class="system-status"><i class="fas fa-circle"></i> Payroll Management</div></div>
        <div class="header-right">
            <div class="admin-profile">
                <div class="admin-avatar"><?php echo strtoupper(substr($adminName,0,1)); ?></div>
                <div>
                    <div style="font-weight:600;font-size:14px;"><?php echo htmlspecialchars($adminName); ?></div>
                    <div class="admin-role-badge" style="background:#8E44AD;">ADMIN</div>
                </div>
            </div>
        </div>
    </div>

    <div class="main-content">
        <?php if (isset($success)): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="pay-header">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px;">
                <div>
                    <h1 style="margin:0 0 6px;font-size:26px;"><i class="fas fa-money-bill-wave"></i> Payroll Management</h1>
                    <p style="margin:0;opacity:.85;font-size:14px;"><?php echo date('F Y'); ?> &nbsp;|&nbsp; Review and approve payroll submitted by managers</p>
                </div>
                <?php if ($stats['pending'] > 0): ?>
                <span style="display:inline-flex;align-items:center;gap:8px;padding:10px 18px;background:rgba(243,156,18,.3);border:2px solid rgba(243,156,18,.6);border-radius:10px;font-size:13px;font-weight:700;color:#FAD7A0;">
                    <i class="fas fa-hourglass-half"></i> <?php echo $stats['pending']; ?> Awaiting Approval
                </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid" style="margin-bottom:24px;">
            <div class="stat-card" style="border-left:4px solid #3498DB;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div><div class="stat-value"><?php echo $stats['total_employees']; ?></div><div class="stat-label">Total Employees</div></div>
                    <i class="fas fa-users" style="font-size:30px;color:#3498DB;opacity:.7;"></i>
                </div>
            </div>
            <div class="stat-card" style="border-left:4px solid #F39C12;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div><div class="stat-value"><?php echo $stats['pending']; ?></div><div class="stat-label">Pending Approval</div></div>
                    <i class="fas fa-hourglass-half" style="font-size:30px;color:#F39C12;opacity:.7;"></i>
                </div>
            </div>
            <div class="stat-card" style="border-left:4px solid #27AE60;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div><div class="stat-value"><?php echo $stats['approved_month']; ?></div><div class="stat-label">Approved This Month</div></div>
                    <i class="fas fa-check-circle" style="font-size:30px;color:#27AE60;opacity:.7;"></i>
                </div>
            </div>
            <div class="stat-card" style="border-left:4px solid #8E44AD;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <div><div class="stat-value">ETB <?php echo number_format($stats['total_net_month'],0); ?></div><div class="stat-label">Approved Payroll (Month)</div></div>
                    <i class="fas fa-coins" style="font-size:30px;color:#8E44AD;opacity:.7;"></i>
                </div>
            </div>
        </div>

        <!-- Records Table -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-list-alt"></i> All Payroll Records</h2>
            </div>

            <!-- Filter Bar -->
            <form method="GET" class="filter-bar">
                <div>
                    <label>Month</label>
                    <select name="fm">
                        <option value="">All Months</option>
                        <?php for($m=1;$m<=12;$m++): ?>
                        <option value="<?php echo $m;?>" <?php echo $filterMonth==$m?'selected':'';?>><?php echo $monthFull[$m];?></option>
                        <?php endfor;?>
                    </select>
                </div>
                <div>
                    <label>Year</label>
                    <select name="fy">
                        <option value="">All Years</option>
                        <?php for($y=date('Y');$y>=date('Y')-3;$y--): ?>
                        <option value="<?php echo $y;?>" <?php echo $filterYear==$y?'selected':'';?>><?php echo $y;?></option>
                        <?php endfor;?>
                    </select>
                </div>
                <div>
                    <label>Status</label>
                    <select name="fs">
                        <option value="">All Statuses</option>
                        <option value="pending_approval" <?php echo $filterStatus==='pending_approval'?'selected':'';?>>Pending Approval</option>
                        <option value="approved" <?php echo $filterStatus==='approved'?'selected':'';?>>Approved</option>
                        <option value="rejected" <?php echo $filterStatus==='rejected'?'selected':'';?>>Rejected</option>
                        <option value="draft" <?php echo $filterStatus==='draft'?'selected':'';?>>Draft</option>
                    </select>
                </div>
                <div style="display:flex;gap:8px;align-items:flex-end;">
                    <button type="submit" style="padding:8px 16px;background:#2C3E50;color:#fff;border:none;border-radius:7px;font-size:12px;font-weight:700;cursor:pointer;"><i class="fas fa-filter"></i> Filter</button>
                    <a href="<?php echo BASE_URL;?>/public/admin/payroll" style="padding:8px 14px;background:#F0F2F5;color:#555;border:none;border-radius:7px;font-size:12px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:4px;"><i class="fas fa-times"></i> Clear</a>
                </div>
            </form>

            <!-- Bulk Bar -->
            <div id="bulkBar">
                <span id="bulkCount" style="font-weight:700;color:#2C3E50;font-size:13px;">0 selected</span>
                <form method="POST" id="bulkForm" onsubmit="return confirmBulk()">
                    <input type="hidden" name="action" value="bulk_approve">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <div id="bulkInputs"></div>
                    <button type="submit" style="padding:8px 18px;background:linear-gradient(135deg,#27AE60,#1E8449);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px;"><i class="fas fa-check-double"></i> Approve Selected</button>
                </form>
                <button onclick="clearAll()" style="padding:8px 14px;background:transparent;color:#95A5A6;border:1.5px solid #E0E0E0;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;"><i class="fas fa-times"></i> Clear</button>
            </div>

            <?php if (empty($records)): ?>
            <div style="text-align:center;padding:60px 20px;color:#95A5A6;">
                <i class="fas fa-money-bill-wave" style="font-size:56px;opacity:.3;display:block;margin-bottom:16px;"></i>
                <div style="font-size:16px;font-weight:600;margin-bottom:8px;">No payroll records found</div>
                <div style="font-size:13px;">Managers generate payroll and submit for your approval.</div>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="data-table" id="payrollTable">
                    <thead><tr>
                        <th class="cb-cell"><input type="checkbox" id="checkAll" class="row-cb" onchange="toggleAll(this)" title="Select all pending"></th>
                        <th>#</th><th>Employee</th><th>Period</th>
                        <th>Gross</th><th>Deductions</th><th>Net Salary</th>
                        <th>Submitted By</th><th>Status</th><th>Actions</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($records as $rec):
                        $statusMap = ['draft'=>['s-draft','Draft','fa-pencil-alt'],'pending_approval'=>['s-pending','Pending','fa-hourglass-half'],'approved'=>['s-approved','Approved','fa-check-circle'],'rejected'=>['s-rejected','Rejected','fa-times-circle']];
                        [$sc,$sl,$si] = $statusMap[$rec['status']] ?? ['s-draft','Draft','fa-pencil-alt'];
                        $isPending = $rec['status'] === 'pending_approval';
                        $totalDed = ($rec['tax_amount']??0) + ($rec['other_deductions']??0);
                    ?>
                    <tr style="<?php echo $isPending ? 'background:#FEF9E7;' : ''; ?>">
                        <td class="cb-cell">
                            <?php if ($isPending): ?>
                            <input type="checkbox" class="row-cb pending-cb" value="<?php echo $rec['id']; ?>">
                            <?php else: ?><span style="color:#E0E0E0;">—</span><?php endif; ?>
                        </td>
                        <td style="color:#95A5A6;font-size:13px;">#<?php echo str_pad($rec['id'],4,'0',STR_PAD_LEFT); ?></td>
                        <td>
                            <div style="font-weight:600;font-size:13px;"><?php echo htmlspecialchars(($rec['first_name']??'').' '.($rec['last_name']??'')); ?></div>
                            <div style="font-size:11px;color:#95A5A6;"><?php echo htmlspecialchars($rec['email']??''); ?></div>
                        </td>
                        <td style="font-weight:600;"><?php echo $monthNames[$rec['month']]; ?> <?php echo $rec['year']; ?></td>
                        <td style="color:#27AE60;">ETB <?php echo number_format($rec['gross_salary'],2); ?></td>
                        <td style="color:#E74C3C;">ETB <?php echo number_format($totalDed,2); ?></td>
                        <td><strong>ETB <?php echo number_format($rec['net_salary'],2); ?></strong></td>
                        <td style="font-size:12px;color:#7F8C8D;"><?php echo htmlspecialchars(trim(($rec['mgr_fn']??'').' '.($rec['mgr_ln']??''))); ?></td>
                        <td><span class="s-badge <?php echo $sc; ?>"><i class="fas <?php echo $si; ?>"></i> <?php echo $sl; ?></span></td>
                        <td style="white-space:nowrap;">
                            <button type="button"
                                class="btn-sm view-btn" style="background:#3498DB18;color:#3498DB;border-color:#3498DB55;margin-bottom:4px;display:inline-flex;"
                                data-rec="<?php echo htmlspecialchars(json_encode($rec), ENT_QUOTES); ?>">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <?php if ($isPending): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Approve this payroll?')">
                                <input type="hidden" name="action" value="approve_payroll">
                                <input type="hidden" name="payroll_id" value="<?php echo $rec['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <button type="submit" class="btn-sm" style="background:#27AE6018;color:#27AE60;border-color:#27AE6055;margin-bottom:4px;"><i class="fas fa-check"></i> Approve</button>
                            </form>
                            <button type="button" onclick="openRejectModal(<?php echo $rec['id']; ?>,'<?php echo htmlspecialchars(addslashes(($rec['first_name']??'').' '.($rec['last_name']??''))); ?>')"
                                class="btn-sm" style="background:#E74C3C18;color:#E74C3C;border-color:#E74C3C55;display:block;margin-bottom:4px;">
                                <i class="fas fa-times"></i> Reject
                            </button>
                            <?php elseif ($rec['status'] === 'approved'): ?>
                            <button type="button" onclick="openPaidModal(<?php echo $rec['id']; ?>,'<?php echo htmlspecialchars(addslashes(($rec['first_name']??'').' '.($rec['last_name']??''))); ?>','<?php echo $rec['payment_date']??''; ?>')"
                                class="btn-sm" style="background:<?php echo empty($rec['payment_date'])?'#F39C1218':'#27AE6010'; ?>;color:<?php echo empty($rec['payment_date'])?'#E67E22':'#27AE60'; ?>;border-color:<?php echo empty($rec['payment_date'])?'#F39C1255':'#27AE6040'; ?>;">
                                <i class="fas fa-<?php echo empty($rec['payment_date'])?'calendar-plus':'calendar-check'; ?>"></i>
                                <?php echo empty($rec['payment_date']) ? 'Mark Paid' : date('M d', strtotime($rec['payment_date'])); ?>
                            </button>
                            <?php else: ?>
                            <span style="font-size:12px;color:#BDC3C7;">—</span>
                            <?php endif; ?>
                            <?php if ($rec['status'] === 'rejected' && !empty($rec['rejection_reason'])): ?>
                            <div style="font-size:10px;color:#E74C3C;margin-top:4px;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo htmlspecialchars($rec['rejection_reason']); ?>">
                                <i class="fas fa-times-circle"></i> <?php echo htmlspecialchars(mb_strimwidth($rec['rejection_reason'],0,40,'…')); ?>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Detail Modal -->
    <div id="detailModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;overflow-y:auto;padding:20px;">
        <div style="background:#fff;border-radius:14px;padding:28px;width:100%;max-width:560px;margin:auto;box-shadow:0 20px 60px rgba(0,0,0,.3);max-height:90vh;overflow-y:auto;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                <h3 style="margin:0;font-size:17px;color:#2C3E50;"><i class="fas fa-file-invoice-dollar" style="color:#3498DB;margin-right:8px;"></i> Payroll Details</h3>
                <button onclick="closeDetailModal()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#95A5A6;">&times;</button>
            </div>
            <div id="detailContent"></div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;padding:20px;">
        <div style="background:#fff;border-radius:14px;padding:28px;width:100%;max-width:440px;margin:auto;box-shadow:0 20px 60px rgba(0,0,0,.3);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h3 style="margin:0;font-size:17px;color:#2C3E50;"><i class="fas fa-times-circle" style="color:#E74C3C;margin-right:8px;"></i> Reject Payroll</h3>
                <button onclick="closeRejectModal()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#95A5A6;">&times;</button>
            </div>
            <div id="rejectEmpName" style="background:#F8F9FA;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:13px;color:#555;"></div>
            <form method="POST">
                <input type="hidden" name="action" value="reject_payroll">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="payroll_id" id="rejectPayrollId">
                <div style="margin-bottom:16px;">
                    <label style="font-size:12px;font-weight:700;color:#555;display:block;margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px;">Reason for Rejection <span style="color:#E74C3C;">*</span></label>
                    <textarea name="rejection_reason" rows="3" required placeholder="Explain why this payroll is being rejected..."
                        style="width:100%;padding:10px 12px;border:1.5px solid #E0E0E0;border-radius:8px;font-family:inherit;font-size:13px;resize:vertical;outline:none;box-sizing:border-box;"
                        onfocus="this.style.borderColor='#E74C3C'" onblur="this.style.borderColor='#E0E0E0'"></textarea>
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end;">
                    <button type="button" onclick="closeRejectModal()" style="padding:10px 20px;background:#F0F2F5;color:#555;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">Cancel</button>
                    <button type="submit" style="padding:10px 20px;background:#E74C3C;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;"><i class="fas fa-times"></i> Reject Payroll</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Mark Paid Modal -->
    <div id="paidModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;padding:20px;">
        <div style="background:#fff;border-radius:14px;padding:28px;width:100%;max-width:400px;margin:auto;box-shadow:0 20px 60px rgba(0,0,0,.3);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h3 style="margin:0;font-size:17px;color:#2C3E50;"><i class="fas fa-calendar-check" style="color:#27AE60;margin-right:8px;"></i> Record Payment Date</h3>
                <button onclick="closePaidModal()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#95A5A6;">&times;</button>
            </div>
            <div id="paidEmpName" style="background:#F8F9FA;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:13px;color:#555;"></div>
            <form method="POST">
                <input type="hidden" name="action" value="mark_paid">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="payroll_id" id="paidPayrollId">
                <div style="margin-bottom:16px;">
                    <label style="font-size:12px;font-weight:700;color:#555;display:block;margin-bottom:6px;text-transform:uppercase;letter-spacing:.4px;">Payment Date</label>
                    <input type="date" name="paid_date" id="paidDateInput" value="<?php echo date('Y-m-d'); ?>"
                        style="width:100%;padding:10px 12px;border:1.5px solid #E0E0E0;border-radius:8px;font-family:inherit;font-size:13px;outline:none;box-sizing:border-box;">
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end;">
                    <button type="button" onclick="closePaidModal()" style="padding:10px 20px;background:#F0F2F5;color:#555;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">Cancel</button>
                    <button type="submit" style="padding:10px 20px;background:#27AE60;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;"><i class="fas fa-check"></i> Save</button>
                </div>
            </form>
        </div>
    </div>

    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
    <script>
    // View button — use event delegation to avoid inline JSON issues
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.view-btn');
        if (btn) {
            try {
                const rec = JSON.parse(btn.getAttribute('data-rec'));
                openDetailModal(rec);
            } catch(err) { console.error('View btn parse error:', err); }
        }
    });

    function openRejectModal(id, name) {
        document.getElementById('rejectPayrollId').value = id;
        document.getElementById('rejectEmpName').innerHTML = '<strong>Employee:</strong> ' + name;
        document.getElementById('rejectModal').style.display = 'flex';
    }
    function closeRejectModal() { document.getElementById('rejectModal').style.display = 'none'; }
    document.getElementById('rejectModal').addEventListener('click', function(e) { if (e.target===this) closeRejectModal(); });

    function openPaidModal(id, name, existingDate) {
        document.getElementById('paidPayrollId').value = id;
        document.getElementById('paidEmpName').innerHTML = '<strong>Employee:</strong> ' + name;
        if (existingDate) document.getElementById('paidDateInput').value = existingDate;
        document.getElementById('paidModal').style.display = 'flex';
    }
    function closePaidModal() { document.getElementById('paidModal').style.display = 'none'; }
    document.getElementById('paidModal').addEventListener('click', function(e) { if (e.target===this) closePaidModal(); });

    const monthNames = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const monthFull  = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
    function fmt(v) { return 'ETB ' + parseFloat(v||0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,','); }
    function openDetailModal(rec) {
        const statusLabels = {draft:'Draft',pending_approval:'Pending Approval',approved:'Approved',rejected:'Rejected'};
        const statusColors = {draft:'#95A5A6',pending_approval:'#F39C12',approved:'#27AE60',rejected:'#E74C3C'};
        const sc = statusColors[rec.status] || '#95A5A6';
        document.getElementById('detailContent').innerHTML = `
          <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:20px;padding-bottom:16px;border-bottom:1.5px solid #F0F0F0;">
            <div>
              <div style="font-size:18px;font-weight:800;color:#2C3E50;">${rec.first_name||''} ${rec.last_name||''}</div>
              <div style="font-size:13px;color:#95A5A6;">${rec.email||''}</div>
              <div style="font-size:13px;color:#555;margin-top:4px;">${monthFull[rec.month]} ${rec.year} &nbsp;|&nbsp; Payroll #${String(rec.id).padStart(4,'0')}</div>
            </div>
            <div style="text-align:right;">
              <div style="font-size:11px;color:#95A5A6;">Net Salary</div>
              <div style="font-size:26px;font-weight:800;color:#2C3E50;">${fmt(rec.net_salary)}</div>
              <span style="display:inline-block;margin-top:4px;background:${sc}22;color:${sc};border:1px solid ${sc}55;border-radius:20px;padding:3px 12px;font-size:12px;font-weight:700;">${statusLabels[rec.status]||rec.status}</span>
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div>
              <div style="font-size:12px;font-weight:700;color:#2C3E50;margin-bottom:8px;text-transform:uppercase;letter-spacing:.4px;">Attendance</div>
              <div style="font-size:13px;color:#555;line-height:2;">
                <span style="color:#27AE60;font-weight:700;">${rec.present_days}P</span> &nbsp;
                <span style="color:#F39C12;font-weight:700;">${rec.late_days}L</span> &nbsp;
                <span style="color:#9B59B6;font-weight:700;">${rec.half_day_count}H</span> &nbsp;
                <span style="color:#E74C3C;font-weight:700;">${rec.absent_days}A</span>
                <div style="color:#95A5A6;font-size:11px;">/ ${rec.working_days_per_month} working days &nbsp;|&nbsp; ${rec.overtime_hours} OT hrs</div>
              </div>
            </div>
            <div>
              <div style="font-size:12px;font-weight:700;color:#2C3E50;margin-bottom:8px;text-transform:uppercase;letter-spacing:.4px;">Salary</div>
              <div style="font-size:13px;line-height:2;">
                <div style="display:flex;justify-content:space-between;"><span style="color:#7F8C8D;">Base</span><span>${fmt(rec.base_salary)}</span></div>
                <div style="display:flex;justify-content:space-between;"><span style="color:#7F8C8D;">Basic Earned</span><span>${fmt(rec.basic_earned)}</span></div>
                <div style="display:flex;justify-content:space-between;"><span style="color:#7F8C8D;">Overtime</span><span>${fmt(rec.overtime_pay)}</span></div>
                <div style="display:flex;justify-content:space-between;"><span style="color:#27AE60;">Bonus</span><span style="color:#27AE60;">${fmt(rec.bonus||0)}</span></div>
                <div style="display:flex;justify-content:space-between;font-weight:700;"><span style="color:#27AE60;">Gross</span><span style="color:#27AE60;">${fmt(rec.gross_salary)}</span></div>
                <div style="display:flex;justify-content:space-between;"><span style="color:#E74C3C;">Tax</span><span style="color:#E74C3C;">- ${fmt(rec.tax_amount)}</span></div>
                <div style="display:flex;justify-content:space-between;"><span style="color:#E74C3C;">Deductions</span><span style="color:#E74C3C;">- ${fmt(rec.other_deductions)}</span></div>
                <div style="display:flex;justify-content:space-between;font-size:15px;font-weight:800;border-top:2px solid #2C3E50;padding-top:6px;margin-top:4px;"><span>Net</span><span>${fmt(rec.net_salary)}</span></div>
              </div>
            </div>
          </div>
          ${rec.notes ? `<div style="margin-top:14px;background:#FEF9E7;border-radius:8px;padding:10px 14px;font-size:13px;color:#555;"><strong style="color:#E67E22;">Notes:</strong> ${rec.notes}</div>` : ''}
          ${rec.rejection_reason ? `<div style="margin-top:14px;background:#FDEDEC;border-radius:8px;padding:10px 14px;font-size:13px;color:#E74C3C;"><strong>Rejection Reason:</strong> ${rec.rejection_reason}</div>` : ''}
        `;
        document.getElementById('detailModal').style.display = 'flex';
    }
    function closeDetailModal() { document.getElementById('detailModal').style.display = 'none'; }
    document.getElementById('detailModal').addEventListener('click', function(e) { if (e.target===this) closeDetailModal(); });

    // Bulk checkboxes
    document.querySelectorAll('.pending-cb').forEach(cb => {
        cb.addEventListener('change', function() {
            const all = document.querySelectorAll('.pending-cb'), chk = document.querySelectorAll('.pending-cb:checked');
            const ca = document.getElementById('checkAll');
            if (ca) { ca.indeterminate = chk.length > 0 && chk.length < all.length; ca.checked = all.length > 0 && chk.length === all.length; }
            updateBulkBar();
        });
    });
    function toggleAll(master) {
        document.querySelectorAll('.pending-cb').forEach(cb => { cb.checked = master.checked; });
        updateBulkBar();
    }
    function updateBulkBar() {
        const chk = document.querySelectorAll('.pending-cb:checked');
        document.getElementById('bulkCount').textContent = chk.length + ' selected';
        document.getElementById('bulkBar').classList.toggle('show', chk.length > 0);
        const c = document.getElementById('bulkInputs'); c.innerHTML = '';
        chk.forEach(cb => { const i = document.createElement('input'); i.type='hidden'; i.name='bulk_ids[]'; i.value=cb.value; c.appendChild(i); });
    }
    function clearAll() {
        document.querySelectorAll('.pending-cb').forEach(cb => { cb.checked=false; });
        const ca = document.getElementById('checkAll'); if(ca){ca.checked=false;ca.indeterminate=false;}
        updateBulkBar();
    }
    function confirmBulk() {
        const n = document.querySelectorAll('.pending-cb:checked').length;
        return n > 0 && confirm('Approve ' + n + ' payroll record(s)?');
    }
    </script>
</body>
</html>
