<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
  header('Location: ' . BASE_URL . '/public/login'); exit();
}
require_once __DIR__ . '/../../../config/db_config.php';
$csrf_token = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;
$managerName = $_SESSION['user_name'] ?? 'Manager';
$managerId   = $_SESSION['user_id'];

// Month names array
$mnf = [1=>'January','February','March','April','May','June','July','August','September','October','November','December'];

// Load all employees for payroll
$employees = [];
try {
  $stmt = $pdo->query("
      SELECT u.id, u.first_name, u.last_name, u.email,
             COALESCE(s.base_salary, 0) as base_salary,
             COALESCE(s.working_days_per_month, 26) as working_days_per_month,
             COALESCE(s.overtime_rate, 0) as overtime_rate
      FROM furn_users u
      LEFT JOIN furn_employee_salary s ON u.id = s.employee_id
      WHERE u.role = 'employee'
      ORDER BY u.first_name, u.last_name
  ");
  $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
  if (!is_array($employees)) $employees = [];
} catch (PDOException $e) { $employees = []; }

// Payroll stats for summary cards
$stats = ['total_employees'=>0,'pending'=>0,'approved_month'=>0,'total_net_month'=>0,'total_paid_year'=>0];
try {
  $stats['total_employees'] = $pdo->query("SELECT COUNT(*) FROM furn_users WHERE role='employee'")->fetchColumn();
  $stats['pending']         = $pdo->query("SELECT COUNT(*) FROM furn_payroll WHERE status='pending_approval'")->fetchColumn();
  $cm = date('n'); $cy = date('Y');
  $r = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(net_salary),0) as tot FROM furn_payroll WHERE month=? AND year=? AND status='approved'");
  $r->execute([$cm,$cy]); $row = $r->fetch();
  $stats['approved_month']  = $row['cnt'] ?? 0;
  $stats['total_net_month'] = $row['tot'] ?? 0;
  $r2 = $pdo->prepare("SELECT COALESCE(SUM(net_salary),0) as tot FROM furn_payroll WHERE year=? AND status='approved'");
  $r2->execute([$cy]); $stats['total_paid_year'] = $r2->fetchColumn() ?: 0;
} catch (PDOException $e) { $stats = ['total_employees'=>0,'pending'=>0,'approved_month'=>0,'total_net_month'=>0,'total_paid_year'=>0]; }

// ── Auto-create / migrate tables ──
try {
  $pdo->exec("CREATE TABLE IF NOT EXISTS furn_employee_salary (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL UNIQUE,
    base_salary DECIMAL(10,2) NOT NULL DEFAULT 0,
    working_days_per_month INT NOT NULL DEFAULT 26,
    overtime_rate DECIMAL(10,2) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX(employee_id)
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
    UNIQUE KEY uq_emp_month(employee_id, month, year),
    INDEX(employee_id), INDEX(status)
  )");
  // Migrate: add missing columns
  $cols = $pdo->query("SHOW COLUMNS FROM furn_payroll")->fetchAll(PDO::FETCH_COLUMN);
  if (!in_array('bonus', $cols))            $pdo->exec("ALTER TABLE furn_payroll ADD COLUMN bonus DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER base_salary");
  if (!in_array('payment_date', $cols))     $pdo->exec("ALTER TABLE furn_payroll ADD COLUMN payment_date DATE NULL AFTER net_salary");
  if (!in_array('approved_by', $cols))      $pdo->exec("ALTER TABLE furn_payroll ADD COLUMN approved_by INT NULL");
  if (!in_array('approved_at', $cols))      $pdo->exec("ALTER TABLE furn_payroll ADD COLUMN approved_at TIMESTAMP NULL");
  if (!in_array('rejection_reason', $cols)) $pdo->exec("ALTER TABLE furn_payroll ADD COLUMN rejection_reason TEXT NULL");
  if (!in_array('basic_earned', $cols))     $pdo->exec("ALTER TABLE furn_payroll ADD COLUMN basic_earned DECIMAL(10,2) NOT NULL DEFAULT 0");
  if (!in_array('overtime_pay', $cols))     $pdo->exec("ALTER TABLE furn_payroll ADD COLUMN overtime_pay DECIMAL(10,2) NOT NULL DEFAULT 0");
} catch (PDOException $e) {}

// ── AJAX: salary config ──
if (isset($_GET['get_salary_config'], $_GET['employee_id'])) {
    header('Content-Type: application/json');
    try {
        $s = $pdo->prepare("SELECT * FROM furn_employee_salary WHERE employee_id=?");
        $s->execute([(int)$_GET['employee_id']]);
        $cfg = $s->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success'=>true,'config'=>$cfg ?: ['base_salary'=>0,'working_days_per_month'=>26,'overtime_rate'=>0]]);
    } catch (PDOException $e) { echo json_encode(['success'=>false]); }
    exit();
}

// ── AJAX: attendance counts ──
if (isset($_GET['get_attendance'], $_GET['employee_id'], $_GET['month'], $_GET['year'])) {
    header('Content-Type: application/json');
    try {
        $empId = (int)$_GET['employee_id'];
        $month = (int)$_GET['month']; $year = (int)$_GET['year'];
        $from  = sprintf('%04d-%02d-01', $year, $month);
        $to    = date('Y-m-t', strtotime($from));
        $tbl = $pdo->query("SHOW TABLES LIKE 'furn_attendance'")->fetchColumn();
        if (!$tbl) { echo json_encode(['success'=>true,'overtime_hours'=>0]); exit(); }
        $attCols  = $pdo->query("SHOW COLUMNS FROM furn_attendance")->fetchAll(PDO::FETCH_COLUMN);
        $dateExpr = in_array('date', $attCols) ? 'date' : 'DATE(check_in_time)';
        $hasOT    = in_array('overtime_hours', $attCols);
        $otHours  = 0;
        if ($hasOT) {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(overtime_hours),0) as ot FROM furn_attendance WHERE employee_id=? AND $dateExpr BETWEEN ? AND ?");
            $stmt->execute([$empId, $from, $to]);
            $otHours = floatval($stmt->fetchColumn());
        } else {
            // Fallback: calculate from check_in/check_out times
            $stmt = $pdo->prepare("SELECT check_in_time, check_out_time FROM furn_attendance WHERE employee_id=? AND $dateExpr BETWEEN ? AND ? AND check_out_time IS NOT NULL");
            $stmt->execute([$empId, $from, $to]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $worked = (strtotime($row['check_out_time']) - strtotime($row['check_in_time'])) / 3600;
                $ot = max(0, $worked - 8);
                $otHours += $ot;
            }
            $otHours = round($otHours, 2);
        }
        echo json_encode(['success'=>true,'overtime_hours'=>$otHours]);
    } catch (PDOException $e) { echo json_encode(['success'=>false,'overtime_hours'=>0]); }
    exit();
}

// ── AJAX: check existing payroll ──
if (isset($_GET['check_payroll'], $_GET['employee_id'], $_GET['month'], $_GET['year'])) {
    header('Content-Type: application/json');
    try {
        $s = $pdo->prepare("SELECT id, status, net_salary FROM furn_payroll WHERE employee_id=? AND month=? AND year=?");
        $s->execute([(int)$_GET['employee_id'], (int)$_GET['month'], (int)$_GET['year']]);
        $existing = $s->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success'=>true,'existing'=>$existing ?: null]);
    } catch (PDOException $e) { echo json_encode(['success'=>false]); }
    exit();
}

// ── Handle POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Invalid CSRF token.';
    } else {
        $action = $_POST['action'] ?? '';

        // Save / update salary config
        if ($action === 'save_salary_config') {
            try {
                $empId = (int)$_POST['sc_employee_id'];
                $base  = (float)$_POST['sc_base_salary'];
                $wd    = max(1,(int)$_POST['sc_working_days']);
                $otr   = (float)$_POST['sc_overtime_rate'];
                $pdo->prepare("INSERT INTO furn_employee_salary (employee_id,base_salary,working_days_per_month,overtime_rate)
                    VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE base_salary=VALUES(base_salary),
                    working_days_per_month=VALUES(working_days_per_month),overtime_rate=VALUES(overtime_rate)")
                    ->execute([$empId,$base,$wd,$otr]);
                $_SESSION['pay_success'] = "Salary configuration saved.";
                header('Location: ' . BASE_URL . '/public/manager/payroll'); exit();
            } catch (PDOException $e) { $error = "Config save error: " . $e->getMessage(); }

        } elseif ($action === 'save_payroll') {
            try {
                $empId=$_POST['employee_id']; $month=(int)$_POST['month']; $year=(int)$_POST['year'];
                $base=(float)$_POST['base_salary']; $bonus=(float)($_POST['bonus']??0);
                $wd=max(1,(int)$_POST['working_days_per_month']); $otr=(float)$_POST['overtime_rate'];
                $pd=(int)$_POST['present_days']; $hd=(int)$_POST['half_day_count'];
                $ad=(int)$_POST['absent_days']; $ld=(int)$_POST['late_days'];
                $oth=(float)$_POST['overtime_hours']; $tax=(float)$_POST['tax_amount'];
                $oded=(float)$_POST['other_deductions'];
                $pdate=!empty($_POST['payment_date'])?$_POST['payment_date']:null;
                $notes=trim($_POST['notes']??'');
                $daily=$wd>0?$base/$wd:0; $eff=$pd+$ld+($hd*0.5);
                $basic=$base; // full base salary (monthly)
                $otpay=$oth*$otr;
                $gross=$basic+$otpay+$bonus; $net=$gross-$tax-$oded;

                // Ensure all required columns exist
                $existingCols = $pdo->query("SHOW COLUMNS FROM furn_payroll")->fetchAll(PDO::FETCH_COLUMN);
                $neededCols = [
                    'present_days'           => 'INT NOT NULL DEFAULT 0',
                    'half_day_count'         => 'INT NOT NULL DEFAULT 0',
                    'absent_days'            => 'INT NOT NULL DEFAULT 0',
                    'late_days'              => 'INT NOT NULL DEFAULT 0',
                    'overtime_hours'         => 'DECIMAL(5,2) NOT NULL DEFAULT 0',
                    'base_salary'            => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
                    'bonus'                  => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
                    'working_days_per_month' => 'INT NOT NULL DEFAULT 26',
                    'overtime_rate'          => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
                    'basic_earned'           => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
                    'overtime_pay'           => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
                    'gross_salary'           => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
                    'tax_amount'             => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
                    'other_deductions'       => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
                    'net_salary'             => 'DECIMAL(10,2) NOT NULL DEFAULT 0',
                    'payment_date'           => 'DATE NULL DEFAULT NULL',
                    'notes'                  => 'TEXT NULL',
                    'calculated_by'          => 'INT NULL',
                    'approved_by'            => 'INT NULL',
                    'approved_at'            => 'TIMESTAMP NULL',
                    'rejection_reason'       => 'TEXT NULL',
                ];
                foreach ($neededCols as $col => $def) {
                    if (!in_array($col, $existingCols)) {
                        try { $pdo->exec("ALTER TABLE furn_payroll ADD COLUMN $col $def"); } catch(PDOException $e2){}
                    }
                }
                // Force payment_date to allow NULL
                try { $pdo->exec("ALTER TABLE furn_payroll MODIFY COLUMN payment_date DATE NULL DEFAULT NULL"); } catch(PDOException $e2){}

                // Also persist salary config
                $pdo->prepare("INSERT INTO furn_employee_salary (employee_id,base_salary,working_days_per_month,overtime_rate)
                    VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE base_salary=VALUES(base_salary),
                    working_days_per_month=VALUES(working_days_per_month),overtime_rate=VALUES(overtime_rate)")
                    ->execute([$empId,$base,$wd,$otr]);
                $pdo->prepare("INSERT INTO furn_payroll
                    (employee_id,month,year,present_days,half_day_count,absent_days,late_days,overtime_hours,
                     base_salary,bonus,working_days_per_month,overtime_rate,basic_earned,overtime_pay,gross_salary,
                     tax_amount,other_deductions,net_salary,payment_date,status,notes,calculated_by)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'draft',?,?)
                    ON DUPLICATE KEY UPDATE
                    present_days=IF(status='draft',VALUES(present_days),present_days),
                    half_day_count=IF(status='draft',VALUES(half_day_count),half_day_count),
                    absent_days=IF(status='draft',VALUES(absent_days),absent_days),
                    late_days=IF(status='draft',VALUES(late_days),late_days),
                    overtime_hours=IF(status='draft',VALUES(overtime_hours),overtime_hours),
                    base_salary=IF(status='draft',VALUES(base_salary),base_salary),
                    bonus=IF(status='draft',VALUES(bonus),bonus),
                    working_days_per_month=IF(status='draft',VALUES(working_days_per_month),working_days_per_month),
                    overtime_rate=IF(status='draft',VALUES(overtime_rate),overtime_rate),
                    basic_earned=IF(status='draft',VALUES(basic_earned),basic_earned),
                    overtime_pay=IF(status='draft',VALUES(overtime_pay),overtime_pay),
                    gross_salary=IF(status='draft',VALUES(gross_salary),gross_salary),
                    tax_amount=IF(status='draft',VALUES(tax_amount),tax_amount),
                    other_deductions=IF(status='draft',VALUES(other_deductions),other_deductions),
                    net_salary=IF(status='draft',VALUES(net_salary),net_salary),
                    payment_date=IF(status='draft',VALUES(payment_date),payment_date),
                    notes=IF(status='draft',VALUES(notes),notes),
                    calculated_by=IF(status='draft',VALUES(calculated_by),calculated_by)
                ")->execute([$empId,$month,$year,$pd,$hd,$ad,$ld,$oth,$base,$bonus,$wd,$otr,$basic,$otpay,$gross,$tax,$oded,$net,$pdate,$notes,$managerId]);
                $_SESSION['pay_success'] = "Payroll saved as draft.";
                header('Location: ' . BASE_URL . '/public/manager/payroll'); exit();
            } catch (PDOException $e) { $error = "Save error: " . $e->getMessage(); }

        } elseif ($action === 'submit_for_approval') {
          try {
            $pdo->prepare("UPDATE furn_payroll SET status='pending_approval' WHERE id=? AND status='draft' AND calculated_by=?")
              ->execute([(int)$_POST['payroll_id'], $managerId]);
            // Notify all admins
            require_once __DIR__ . '/../../../app/includes/notification_helper.php';
            notifyRole($pdo, 'admin', 'payroll', 'Payroll Awaiting Approval',
                'Manager submitted payroll for approval. Please review.',
                (int)$_POST['payroll_id'], '/admin/payroll', 'high');
            $_SESSION['pay_success'] = "Payroll submitted for admin approval.";
            header('Location: ' . BASE_URL . '/public/manager/payroll'); exit();
          } catch (PDOException $e) {
            $error = "Approval error: " . $e->getMessage();
          }

        } elseif ($action === 'delete_payroll') {
          try {
            $pdo->prepare("DELETE FROM furn_payroll WHERE id=? AND status='draft' AND calculated_by=?")
              ->execute([(int)$_POST['payroll_id'], $managerId]);
            $_SESSION['pay_success'] = "Draft payroll deleted.";
            header('Location: ' . BASE_URL . '/public/manager/payroll'); exit();
          } catch (PDOException $e) { $error = "Delete error: " . $e->getMessage(); }

        } elseif ($action === 'bulk_submit') {
          $empIds = array_map('intval', $_POST['bulk_ids'] ?? []);
          if (!empty($empIds)) {
            try {
              $cm = date('n'); $cy = date('Y');
              $ph = implode(',', array_fill(0, count($empIds), '?'));
              $params = array_merge($empIds, [$cm, $cy]);
              $updated = $pdo->prepare("UPDATE furn_payroll SET status='pending_approval' WHERE employee_id IN ($ph) AND month=? AND year=? AND status='draft'");
              $updated->execute($params);
              $cnt = $updated->rowCount();
              $_SESSION['pay_success'] = $cnt > 0
                  ? "$cnt payroll record(s) submitted for approval."
                  : "No draft payroll found. Please click Save first, then Submit.";
              header('Location: ' . BASE_URL . '/public/manager/payroll'); exit();
            } catch (PDOException $e) { $error = "Bulk submit error: " . $e->getMessage(); }
          }
        }
    }
}

// Short month names for table display
$mn = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'May',6=>'Jun',7=>'Jul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dec'];

// Filters from GET
$filterMonth  = isset($_GET['fm'])  && $_GET['fm']  !== '' ? (int)$_GET['fm']  : null;
$filterYear   = isset($_GET['fy'])  && $_GET['fy']  !== '' ? (int)$_GET['fy']  : null;
$filterStatus = isset($_GET['fs'])  && $_GET['fs']  !== '' ? $_GET['fs']       : null;
$filterEmp    = isset($_GET['fe'])  && $_GET['fe']  !== '' ? (int)$_GET['fe']  : null;

// Fetch payroll records with filters
$payrollRecords = [];
try {
  $where = ['1=1']; $params = [];
  if ($filterMonth)  { $where[] = 'p.month=?';       $params[] = $filterMonth; }
  if ($filterYear)   { $where[] = 'p.year=?';        $params[] = $filterYear; }
  if ($filterStatus) { $where[] = 'p.status=?';      $params[] = $filterStatus; }
  if ($filterEmp)    { $where[] = 'p.employee_id=?'; $params[] = $filterEmp; }
  $sql = "SELECT p.*, u.first_name, u.last_name, u.email FROM furn_payroll p
          JOIN furn_users u ON u.id = p.employee_id
          WHERE " . implode(' AND ', $where) . " ORDER BY p.year DESC, p.month DESC, u.first_name ASC";
  $st = $pdo->prepare($sql); $st->execute($params);
  $payrollRecords = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $payrollRecords = []; }

// Draft count for stats card
$stats['draft_count'] = 0;
try { $stats['draft_count'] = $pdo->query("SELECT COUNT(*) FROM furn_payroll WHERE status='draft'")->fetchColumn(); } catch(PDOException $e){}

// ── Income tax rate from admin settings (furn_tax_config) ──
$incomeTaxRate = 0; // percentage e.g. 15 means 15%
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS furn_tax_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tax_name VARCHAR(100) NOT NULL,
        tax_rate DECIMAL(5,2) NOT NULL,
        tax_type ENUM('percentage','fixed') DEFAULT 'percentage',
        is_active BOOLEAN DEFAULT TRUE
    )");
    $t = $pdo->query("SELECT tax_rate FROM furn_tax_config WHERE is_active=1 AND LOWER(tax_name) LIKE '%income%' LIMIT 1");
    $taxRow = $t->fetch(PDO::FETCH_ASSOC);
    if (!$taxRow) {
        $t2 = $pdo->query("SELECT tax_rate FROM furn_tax_config WHERE is_active=1 ORDER BY id LIMIT 1");
        $taxRow = $t2->fetch(PDO::FETCH_ASSOC);
    }
    if ($taxRow) $incomeTaxRate = floatval($taxRow['tax_rate']);
} catch (PDOException $e) {}

// Flash messages
$success = $_SESSION['pay_success'] ?? null; unset($_SESSION['pay_success']);

// --- END PHP LOGIC ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Payroll Management - FurnitureCraft</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/css/admin-responsive.css">
<style>
.pay-header{background:linear-gradient(135deg,#1a252f,#2C3E50,#3D1F14);color:#fff;padding:24px 28px;border-radius:14px;margin-bottom:22px;}
.s-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;}
.s-draft{background:#F0F2F5;color:#7F8C8D;}
.s-pending{background:#FEF9E7;color:#E67E22;}
.s-approved{background:#EAFAF1;color:#27AE60;}
.s-rejected{background:#FDEDEC;color:#E74C3C;}
.s-none{background:#F0F2F5;color:#aaa;}
.emp-av{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0;}
.row-selected{background:#EBF5FB!important;}
/* inline editable cells */
.pay-input{width:80px;padding:4px 6px;border:1.5px solid #E0E0E0;border-radius:6px;font-size:12px;font-family:inherit;text-align:right;}
.pay-input:focus{border-color:#3498DB;outline:none;}
.calc-btn{padding:6px 14px;background:linear-gradient(135deg,#2C3E50,#3D1F14);color:#fff;border:none;border-radius:7px;font-size:12px;font-weight:700;cursor:pointer;white-space:nowrap;}
.calc-btn:hover{opacity:.9;}
.submit-btn{padding:6px 14px;background:linear-gradient(135deg,#27AE60,#1E8449);color:#fff;border:none;border-radius:7px;font-size:12px;font-weight:700;cursor:pointer;}
.submit-btn:disabled{opacity:.4;cursor:not-allowed;}
.month-bar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:14px 16px;background:#F8F9FA;border-radius:10px;margin-bottom:18px;}
.month-bar label{font-size:12px;font-weight:700;color:#555;}
.month-bar select,.month-bar input{padding:7px 10px;border:1.5px solid #E0E0E0;border-radius:7px;font-size:13px;font-family:inherit;outline:none;}
.month-bar select:focus{border-color:#3498DB;}
.net-cell{font-weight:700;color:#2C3E50;font-size:13px;}
.att-cell{font-size:11px;color:#555;}
</style>
</head>
<body>
<button class="mobile-menu-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>
<div class="sidebar-overlay"></div>
<?php include_once __DIR__ . '/../../includes/manager_sidebar.php'; ?>
<div class="top-header">
  <div class="header-left"><div class="system-status"><i class="fas fa-circle"></i> Payroll Management</div></div>
  <div class="header-right">
    <div class="admin-profile">
      <div class="admin-avatar"><?php echo strtoupper(substr($managerName,0,1)); ?></div>
      <div><div style="font-weight:600;font-size:14px;"><?php echo htmlspecialchars($managerName); ?></div><div class="admin-role-badge">MANAGER</div></div>
    </div>
  </div>
</div>
<div class="main-content">
<?php
// Payroll stats cards
$pCards = [];
try {
    $pTotalEmp    = (int)$pdo->query("SELECT COUNT(*) FROM furn_users WHERE role='employee'")->fetchColumn();
    $pPending     = (int)$pdo->query("SELECT COUNT(*) FROM furn_payroll WHERE status='pending_approval'")->fetchColumn();
    $pApproved    = (int)$pdo->query("SELECT COUNT(*) FROM furn_payroll WHERE status='approved' AND month=MONTH(CURDATE()) AND year=YEAR(CURDATE())")->fetchColumn();
    $pTotalPaid   = floatval($pdo->query("SELECT COALESCE(SUM(net_salary),0) FROM furn_payroll WHERE status='approved'")->fetchColumn());
    $pCards = [
        [$pTotalEmp,                              'Total Employees',       '#3498DB','fa-users'],
        [$pPending,                               'Pending Approval',      '#F39C12','fa-hourglass-half'],
        [$pApproved,                              'Approved This Month',   '#27AE60','fa-check-circle'],
        ['ETB '.number_format($pTotalPaid,0),     'Total Payroll Paid',    '#9B59B6','fa-money-bill-wave'],
    ];
} catch(PDOException $e){}
if (!empty($pCards)):
?>
<div class="stats-grid" style="margin-bottom:20px;">
    <?php foreach($pCards as [$v,$l,$c,$i]): ?>
    <div class="stat-card" style="border-left:4px solid <?php echo $c; ?>;">
        <div style="display:flex;justify-content:space-between;align-items:start;">
            <div><div class="stat-value" style="font-size:<?php echo strpos((string)$v,'ETB')!==false?'16px':'28px';?>"><?php echo $v; ?></div><div class="stat-label"><?php echo $l; ?></div></div>
            <div style="font-size:28px;color:<?php echo $c; ?>;opacity:.25;"><i class="fas <?php echo $i; ?>"></i></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php if(isset($success)):?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success);?></div><?php endif;?>
<?php if(isset($error)):?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error);?></div><?php endif;?>

<!-- Header -->
<div class="pay-header">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
    <div>
      <h1 style="margin:0 0 4px;font-size:24px;"><i class="fas fa-money-bill-wave"></i> Payroll Management</h1>
      <p style="margin:0;opacity:.8;font-size:13px;">
        Salaries are fetched from
        <a href="<?php echo BASE_URL; ?>/public/admin/employees" style="color:var(--gold,#d4a574);font-weight:700;text-decoration:underline;">
          Admin → Employees
        </a> — check a row to auto-fill.
        Tax rate: <strong><?php echo $incomeTaxRate > 0 ? $incomeTaxRate.'%' : 'not set'; ?></strong>
        <?php if ($incomeTaxRate <= 0): ?>
        (<a href="<?php echo BASE_URL; ?>/public/admin/settings" style="color:#f39c12;text-decoration:underline;">set in Admin → Settings → Tax Config</a>)
        <?php endif; ?>
      </p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <span style="background:rgba(255,255,255,.15);padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;">
        <i class="fas fa-users me-1"></i> <?php echo $stats['total_employees']; ?> Employees
      </span>
      <span style="background:rgba(243,156,18,.3);padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;">
        <i class="fas fa-hourglass-half me-1"></i> <?php echo $stats['pending']; ?> Pending
      </span>
      <span style="background:rgba(39,174,96,.3);padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;">
        <i class="fas fa-check-circle me-1"></i> <?php echo $stats['approved_month']; ?> Approved
      </span>
    </div>
  </div>
</div>

<!-- Month/Year selector + bulk actions -->
<div class="section-card" style="margin-bottom:20px;">
  <div class="month-bar">
    <label>Payroll Period:</label>
    <select id="selMonth">
      <?php for($m=1;$m<=12;$m++): ?>
      <option value="<?php echo $m;?>" <?php echo date('n')==$m?'selected':'';?>><?php echo $mnf[$m];?></option>
      <?php endfor;?>
    </select>
    <select id="selYear">
      <?php for($y=date('Y');$y>=date('Y')-2;$y--): ?>
      <option value="<?php echo $y;?>" <?php echo date('Y')==$y?'selected':'';?>><?php echo $y;?></option>
      <?php endfor;?>
    </select>
  </div>

  <!-- Bulk action bar (shown when rows selected) -->
  <div id="bulkBar" style="display:none;padding:10px 14px;background:#EBF5FB;border-radius:8px;margin-bottom:14px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
    <span id="selCount" style="font-weight:700;color:#2C3E50;font-size:13px;">0 selected</span>
    <button onclick="calcSelected()" style="padding:7px 16px;background:linear-gradient(135deg,#2C3E50,#3D1F14);color:#fff;border:none;border-radius:7px;font-size:12px;font-weight:700;cursor:pointer;">
      <i class="fas fa-calculator"></i> Calculate Selected
    </button>
    <form method="POST" id="bulkSubmitForm" style="display:inline;">
      <input type="hidden" name="action" value="bulk_submit">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
      <div id="bulkHiddenIds"></div>
      <button type="button" onclick="bulkSubmit()" style="padding:7px 16px;background:linear-gradient(135deg,#27AE60,#1E8449);color:#fff;border:none;border-radius:7px;font-size:12px;font-weight:700;cursor:pointer;">
        <i class="fas fa-paper-plane"></i> Submit for Approval
      </button>
    </form>
    <button onclick="clearSel()" style="padding:7px 12px;background:#fff;color:#888;border:1.5px solid #E0E0E0;border-radius:7px;font-size:12px;cursor:pointer;">Clear</button>
  </div>

  <!-- Main employee payroll table -->
  <div class="table-responsive">
  <table class="data-table" id="payrollTable">
    <thead>
      <tr>
        <th style="width:36px;"><input type="checkbox" id="checkAll" onchange="toggleAll(this)"></th>
        <th>Employee</th>
        <th>Base Salary (ETB)</th>
        <th>OT Hours</th>
        <th>OT Rate (ETB/hr)</th>
        <th>OT Pay (ETB)</th>
        <th>Tax (ETB)</th>
        <th>Net Salary (ETB)</th>
        <th>Status</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
    <?php
    $avatarColors = ['#3498DB','#27AE60','#E74C3C','#9B59B6','#F39C12','#1ABC9C','#E67E22','#2980B9'];
    foreach ($employees as $emp):
        $empId    = $emp['id'];
        $initials = strtoupper(substr($emp['first_name'],0,1).substr($emp['last_name'],0,1));
        $color    = $avatarColors[$empId % count($avatarColors)];
        $base     = floatval($emp['base_salary'] ?? 0);
        $otr      = floatval($emp['overtime_rate'] ?? 0);
        $cm = date('n'); $cy = date('Y');
        $existPay = null;
        try {
            $ep = $pdo->prepare("SELECT * FROM furn_payroll WHERE employee_id=? AND month=? AND year=?");
            $ep->execute([$empId, $cm, $cy]);
            $existPay = $ep->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e){}

        // Fetch OT hours from attendance for current month
        $otHoursFromAtt = 0;
        try {
            $from = date('Y-m-01'); $to = date('Y-m-t');
            $attCols = $pdo->query("SHOW COLUMNS FROM furn_attendance")->fetchAll(PDO::FETCH_COLUMN);
            $dateExpr = in_array('date', $attCols) ? 'date' : 'DATE(check_in_time)';
            if (in_array('overtime_hours', $attCols)) {
                $ots = $pdo->prepare("SELECT COALESCE(SUM(overtime_hours),0) FROM furn_attendance WHERE employee_id=? AND $dateExpr BETWEEN ? AND ?");
                $ots->execute([$empId, $from, $to]);
                $otHoursFromAtt = floatval($ots->fetchColumn());
            } else {
                // Calculate from check_in/check_out
                $ots = $pdo->prepare("SELECT check_in_time, check_out_time FROM furn_attendance WHERE employee_id=? AND $dateExpr BETWEEN ? AND ? AND check_out_time IS NOT NULL");
                $ots->execute([$empId, $from, $to]);
                foreach ($ots->fetchAll(PDO::FETCH_ASSOC) as $att) {
                    $worked = (strtotime($att['check_out_time']) - strtotime($att['check_in_time'])) / 3600;
                    $otHoursFromAtt += max(0, $worked - 8);
                }
                $otHoursFromAtt = round($otHoursFromAtt, 2);
            }
        } catch(PDOException $e){}

        if ($existPay) {
            $sc = ['draft'=>'s-draft','pending_approval'=>'s-pending','approved'=>'s-approved','rejected'=>'s-rejected'][$existPay['status']] ?? 's-none';
            $sl = ['draft'=>'Draft','pending_approval'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'][$existPay['status']] ?? $existPay['status'];
            $statusBadge = "<span class='s-badge $sc'>$sl</span>";
        } else {
            $statusBadge = "<span class='s-badge s-none'>Not Set</span>";
        }
        $savedOT  = floatval($existPay['overtime_pay']  ?? 0);
        $savedTax = floatval($existPay['tax_amount']    ?? 0);
        $savedNet = floatval($existPay['net_salary']    ?? 0);
        $savedOTH = floatval($existPay['overtime_hours'] ?? $otHoursFromAtt);
    ?>
    <tr id="row-<?php echo $empId; ?>" data-emp="<?php echo $empId; ?>" data-status="<?php echo $existPay['status'] ?? ''; ?>">
      <td style="text-align:center;">
        <input type="checkbox" class="row-cb" data-emp="<?php echo $empId; ?>" onchange="onRowCheck(this)">
      </td>
      <td>
        <div style="display:flex;align-items:center;gap:10px;">
          <div class="emp-av" style="background:<?php echo $color; ?>;"><?php echo $initials; ?></div>
          <div>
            <div style="font-weight:600;font-size:13px;"><?php echo htmlspecialchars($emp['first_name'].' '.$emp['last_name']); ?></div>
            <div style="font-size:11px;color:#95A5A6;"><?php echo htmlspecialchars($emp['email']); ?></div>
            <?php if ($base <= 0): ?>
            <div style="font-size:10px;color:#E74C3C;font-weight:600;"><i class="fas fa-exclamation-triangle"></i> Set salary in Admin → Employees</div>
            <?php endif; ?>
          </div>
        </div>
      </td>
      <td>
        <input type="number" class="pay-input" id="base-<?php echo $empId;?>"
               value="<?php echo $existPay['base_salary'] ?? $base; ?>"
               min="0" step="0.01" style="width:110px;<?php echo $base<=0?'border-color:#E74C3C;':'' ?>" onchange="recalc(<?php echo $empId;?>)">
      </td>
      <td>
        <input type="number" class="pay-input" id="oth-<?php echo $empId;?>"
               value="<?php echo $savedOTH; ?>"
               min="0" step="0.5" style="width:80px;" onchange="recalc(<?php echo $empId;?>)">
        <?php if ($otHoursFromAtt > 0 && !$existPay): ?>
        <div style="font-size:10px;color:#27AE60;">from attendance</div>
        <?php endif; ?>
      </td>
      <td>
        <input type="number" class="pay-input" id="otr-<?php echo $empId;?>"
               value="<?php echo $existPay['overtime_rate'] ?? $otr; ?>"
               min="0" step="0.01" style="width:90px;" onchange="recalc(<?php echo $empId;?>)">
      </td>
      <td id="otpay-<?php echo $empId;?>" style="font-weight:600;color:#27AE60;">
        ETB <?php echo number_format($savedOT, 2); ?>
      </td>
      <td>
        <input type="number" class="pay-input" id="tax-<?php echo $empId;?>"
               value="<?php echo $savedTax; ?>"
               min="0" step="0.01" style="width:100px;" onchange="recalc(<?php echo $empId;?>)">
      </td>
      <td class="net-cell" id="net-<?php echo $empId;?>">
        ETB <?php echo number_format($savedNet, 2); ?>
      </td>
      <td id="status-<?php echo $empId;?>"><?php echo $statusBadge; ?></td>
      <td style="white-space:nowrap;display:flex;gap:6px;align-items:center;">
        <button class="calc-btn" onclick="savePayroll(<?php echo $empId;?>)">
          <i class="fas fa-save"></i> Save
        </button>
        <?php if ($existPay && $existPay['status'] === 'draft'): ?>
        <form method="POST" style="display:inline;" onsubmit="return confirm('Submit for approval?')">
          <input type="hidden" name="action" value="submit_for_approval">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
          <input type="hidden" name="payroll_id" value="<?php echo $existPay['id']; ?>">
          <button type="submit" class="submit-btn"><i class="fas fa-paper-plane"></i> Submit</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

</div><!-- main-content -->

<script>
const BASE  = '<?php echo BASE_URL; ?>';
const CSRF  = '<?php echo htmlspecialchars($csrf_token); ?>';
const empIds = [<?php echo implode(',', array_column($employees, 'id')); ?>];

// Salary data from PHP (set in Admin → Employees)
const salaryData = {
<?php foreach ($employees as $emp): ?>
    <?php echo $emp['id']; ?>: { base: <?php echo floatval($emp['base_salary'] ?? 0); ?>, otr: <?php echo floatval($emp['overtime_rate'] ?? 0); ?> },
<?php endforeach; ?>
};
const TAX_RATE = <?php echo $incomeTaxRate; ?>; // % from Admin → Settings → Tax Config

// ── Auto-fill when checkbox checked ──
function onRowCheck(cb) {
    const id = parseInt(cb.dataset.emp);
    document.getElementById('row-'+id).classList.toggle('row-selected', cb.checked);
    if (cb.checked) {
        const s = salaryData[id] || { base: 0, otr: 0 };
        document.getElementById('base-'+id).value = s.base.toFixed(2);
        document.getElementById('otr-'+id).value  = s.otr.toFixed(2);
        // Auto-calculate tax from rate
        const gross = s.base; // OT hours not yet known, use base as estimate
        if (TAX_RATE > 0) {
            document.getElementById('tax-'+id).value = (gross * TAX_RATE / 100).toFixed(2);
        }
        // Fetch overtime hours from attendance for selected month/year
        const month = document.getElementById('selMonth').value;
        const year  = document.getElementById('selYear').value;
        fetch(BASE + '/public/manager/payroll?get_attendance=1&employee_id='+id+'&month='+month+'&year='+year)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('oth-'+id).value = data.overtime_hours || 0;
            }
            recalc(id);
        })
        .catch(() => recalc(id));
    }
    updateBulkBar();
}

// ── Recalculate net salary live ──
function recalc(id) {
    const base  = parseFloat(document.getElementById('base-'+id).value) || 0;
    const oth   = parseFloat(document.getElementById('oth-'+id).value)  || 0;
    const otr   = parseFloat(document.getElementById('otr-'+id).value)  || 0;
    const otpay = oth * otr;
    const gross = base + otpay;
    // Always recalculate tax as % of gross
    const tax = TAX_RATE > 0 ? gross * (TAX_RATE / 100) : (parseFloat(document.getElementById('tax-'+id).value) || 0);
    document.getElementById('tax-'+id).value = tax.toFixed(2);
    const net = gross - tax;
    document.getElementById('otpay-'+id).textContent = 'ETB ' + otpay.toFixed(2);
    document.getElementById('net-'+id).textContent   = 'ETB ' + net.toFixed(2);
}

// ── Load attendance from DB for selected month/year ──
function loadAttendance() {
    const month = document.getElementById('selMonth').value;
    const year  = document.getElementById('selYear').value;
    const status = document.getElementById('loadStatus');
    status.textContent = 'Loading...';
    let done = 0;
    empIds.forEach(id => {
        fetch(BASE + '/public/manager/payroll?get_attendance=1&employee_id='+id+'&month='+month+'&year='+year)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.counts) {
                const c = data.counts;
                document.getElementById('pd-'+id).value = c.present  || 0;
                document.getElementById('hd-'+id).value = c.half_day || 0;
                document.getElementById('ad-'+id).value = c.absent   || 0;
                document.getElementById('ld-'+id).value = c.late     || 0;
                recalc(id);
            }
            done++;
            if (done === empIds.length) status.textContent = '✓ Attendance loaded for ' + month + '/' + year;
        })
        .catch(() => { done++; });
    });
}

// ── Save payroll for one employee (form POST — reliable) ──
function savePayroll(id) {
    const month = document.getElementById('selMonth').value;
    const year  = document.getElementById('selYear').value;
    const base  = parseFloat(document.getElementById('base-'+id).value) || 0;
    const oth   = parseFloat(document.getElementById('oth-'+id).value)  || 0;
    const otr   = parseFloat(document.getElementById('otr-'+id).value)  || 0;
    const tax   = parseFloat(document.getElementById('tax-'+id).value)  || 0;

    if (base <= 0) {
        showToast('Please enter a base salary first', 'error');
        document.getElementById('base-'+id).focus();
        return;
    }

    // Build and submit a hidden form
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = BASE + '/public/manager/payroll';
    const fields = {
        action: 'save_payroll', csrf_token: CSRF,
        employee_id: id, month, year,
        base_salary: base, working_days_per_month: 26,
        present_days: 0, half_day_count: 0, absent_days: 0, late_days: 0,
        overtime_hours: oth, overtime_rate: otr,
        bonus: 0, tax_amount: tax, other_deductions: 0,
        payment_date: '', notes: ''
    };
    Object.entries(fields).forEach(([k,v]) => {
        const inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = k; inp.value = v;
        form.appendChild(inp);
    });
    document.body.appendChild(form);
    form.submit();
}

// ── Calculate all selected ──
function calcSelected() {
    document.querySelectorAll('.row-cb:checked').forEach(cb => {
        recalc(parseInt(cb.dataset.emp));
    });
}

// ── Checkbox logic ──
function toggleAll(cb) {
    document.querySelectorAll('.row-cb').forEach(c => {
        c.checked = cb.checked;
        const id = parseInt(c.dataset.emp);
        document.getElementById('row-'+id).classList.toggle('row-selected', cb.checked);
        if (cb.checked) {
            const s = salaryData[id] || { base: 0, otr: 0 };
            document.getElementById('base-'+id).value = s.base.toFixed(2);
            document.getElementById('otr-'+id).value  = s.otr.toFixed(2);
            if (TAX_RATE > 0) {
                document.getElementById('tax-'+id).value = (s.base * TAX_RATE / 100).toFixed(2);
            }
            const month = document.getElementById('selMonth').value;
            const year  = document.getElementById('selYear').value;
            fetch(BASE + '/public/manager/payroll?get_attendance=1&employee_id='+id+'&month='+month+'&year='+year)
            .then(r => r.json())
            .then(data => {
                if (data.success) document.getElementById('oth-'+id).value = data.overtime_hours || 0;
                recalc(id);
            })
            .catch(() => recalc(id));
        }
    });
    updateBulkBar();
}



function updateBulkBar() {
    const checked = document.querySelectorAll('.row-cb:checked');
    const bar = document.getElementById('bulkBar');
    document.getElementById('selCount').textContent = checked.length + ' selected';
    bar.style.display = checked.length > 0 ? 'flex' : 'none';
}

function clearSel() {
    document.querySelectorAll('.row-cb').forEach(c => { c.checked = false; });
    document.getElementById('checkAll').checked = false;
    document.querySelectorAll('tr[id^="row-"]').forEach(r => r.classList.remove('row-selected'));
    updateBulkBar();
}

function bulkSubmit() {
    const ids = [...document.querySelectorAll('.row-cb:checked')].map(c => c.dataset.emp);
    if (!ids.length) return;
    if (!confirm('Submit ' + ids.length + ' payroll record(s) for approval?')) return;
    const div = document.getElementById('bulkHiddenIds');
    div.innerHTML = ids.map(id => '<input type="hidden" name="bulk_ids[]" value="'+id+'">').join('');
    document.getElementById('bulkSubmitForm').submit();
}

// ── Toast ──
function showToast(msg, type) {
    const t = document.createElement('div');
    t.style.cssText = 'position:fixed;top:20px;right:20px;padding:12px 20px;border-radius:8px;color:#fff;font-weight:600;font-size:13px;z-index:9999;background:'+(type==='success'?'#27AE60':'#E74C3C');
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3000);
}

// Auto-recalc on page load
document.addEventListener('DOMContentLoaded', () => {
    empIds.forEach(id => recalc(id));
});
</script>
<script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
