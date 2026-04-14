<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
    header('Location: ' . BASE_URL . '/public/login'); exit();
}
require_once __DIR__ . '/../../../config/db_config.php';
$employeeName = $_SESSION['user_name'] ?? 'Employee';
$employeeId   = $_SESSION['user_id'];

try {
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
} catch (PDOException $e) {}

$records = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.*, a.first_name as apr_fn, a.last_name as apr_ln
        FROM furn_payroll p
        LEFT JOIN furn_users a ON p.approved_by = a.id
        WHERE p.employee_id = ? AND p.status = 'approved'
        ORDER BY p.year DESC, p.month DESC
    ");
    $stmt->execute([$employeeId]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$totalNet = array_sum(array_column($records, 'net_salary'));
$latest   = $records[0] ?? null;

$detailId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$detail   = null;
if ($detailId) {
    try {
        $stmt = $pdo->prepare("SELECT p.*, a.first_name as apr_fn, a.last_name as apr_ln FROM furn_payroll p LEFT JOIN furn_users a ON p.approved_by=a.id WHERE p.id=? AND p.employee_id=? AND p.status='approved'");
        $stmt->execute([$detailId, $employeeId]);
        $detail = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
}

$mn  = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$mnf = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Payslips - FurnitureCraft</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/css/admin-responsive.css">
<style>
.pay-header{background:linear-gradient(135deg,#1a252f 0%,#2C3E50 50%,#27AE60 100%);color:#fff;padding:28px 30px;border-radius:14px;margin-bottom:24px;}
.slip-card{background:#fff;border:1.5px solid #E8ECEF;border-radius:12px;padding:20px;cursor:pointer;transition:all .2s;margin-bottom:12px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;}
.slip-card:hover{border-color:#27AE60;box-shadow:0 4px 16px rgba(39,174,96,.12);}
.s-approved{background:#27AE6018;color:#27AE60;border:1px solid #27AE6040;display:inline-flex;align-items:center;gap:5px;border-radius:20px;padding:4px 12px;font-size:12px;font-weight:700;}
.detail-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #F5F5F5;font-size:13px;}
.detail-row:last-child{border-bottom:none;}
.detail-label{color:#7F8C8D;font-weight:600;}
.detail-value{color:#2C3E50;font-weight:600;text-align:right;}
@media print{.no-print{display:none!important;}.main-content{margin-left:0!important;}}
</style>
</head>
<body>
<button class="mobile-menu-toggle no-print" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>
<div class="sidebar-overlay no-print"></div>
<?php include_once __DIR__ . '/../../includes/employee_sidebar.php'; ?>
<!-- Top Header -->
<?php 
$pageTitle = 'My Payroll';
include_once __DIR__ . '/../../includes/employee_header.php'; 
?>
  <div class="header-left"><div class="system-status"><i class="fas fa-circle"></i> My Payslips</div></div>
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

<div class="pay-header">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
    <div>
      <h1 style="margin:0 0 6px;font-size:26px;"><i class="fas fa-file-invoice-dollar"></i> My Payslips</h1>
      <p style="margin:0;opacity:.85;font-size:14px;">View your approved salary records</p>
    </div>
    <?php if ($latest): ?>
    <div style="text-align:right;">
      <div style="font-size:12px;opacity:.8;">Latest Net Salary</div>
      <div style="font-size:28px;font-weight:800;">ETB <?php echo number_format($latest['net_salary'],2); ?></div>
      <div style="font-size:12px;opacity:.8;"><?php echo $mnf[$latest['month']].' '.$latest['year']; ?></div>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="stats-grid" style="margin-bottom:24px;">
  <div class="stat-card" style="border-left:4px solid #27AE60;">
    <div style="display:flex;justify-content:space-between;align-items:center;">
      <div><div class="stat-value"><?php echo count($records); ?></div><div class="stat-label">Approved Payslips</div></div>
      <i class="fas fa-file-invoice-dollar" style="font-size:30px;color:#27AE60;opacity:.7;"></i>
    </div>
  </div>
  <div class="stat-card" style="border-left:4px solid #3498DB;">
    <div style="display:flex;justify-content:space-between;align-items:center;">
      <div><div class="stat-value" style="font-size:18px;">ETB <?php echo number_format($totalNet,0); ?></div><div class="stat-label">Total Earned (All Time)</div></div>
      <i class="fas fa-coins" style="font-size:30px;color:#3498DB;opacity:.7;"></i>
    </div>
  </div>
  <div class="stat-card" style="border-left:4px solid #F39C12;">
    <div style="display:flex;justify-content:space-between;align-items:center;">
      <div><div class="stat-value" style="font-size:18px;">ETB <?php echo $latest ? number_format($latest['base_salary'],0) : '0'; ?></div><div class="stat-label">Current Base Salary</div></div>
      <i class="fas fa-money-bill-wave" style="font-size:30px;color:#F39C12;opacity:.7;"></i>
    </div>
  </div>
</div>

<?php if ($detail): ?>
<div class="section-card" style="margin-bottom:20px;">
  <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
    <h2 style="margin:0;font-size:17px;color:#2C3E50;"><i class="fas fa-receipt" style="color:#27AE60;margin-right:8px;"></i>
      Payslip — <?php echo $mnf[$detail['month']].' '.$detail['year']; ?>
    </h2>
    <div style="display:flex;gap:8px;flex-wrap:wrap;" class="no-print">
      <a href="<?php echo BASE_URL; ?>/public/employee/payroll" style="padding:8px 16px;background:#F0F2F5;color:#555;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:6px;"><i class="fas fa-arrow-left"></i> Back</a>
      <button onclick="window.print()" style="padding:8px 16px;background:#3498DB;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px;"><i class="fas fa-print"></i> Print</button>
    </div>
  </div>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px;">
    <div style="background:#F8F9FA;border-radius:10px;padding:16px;">
      <div style="font-size:13px;font-weight:700;color:#2C3E50;margin-bottom:12px;"><i class="fas fa-user" style="color:#3498DB;margin-right:6px;"></i> Employee</div>
      <div class="detail-row"><span class="detail-label">Name</span><span class="detail-value"><?php echo htmlspecialchars($employeeName); ?></span></div>
      <div class="detail-row"><span class="detail-label">Period</span><span class="detail-value"><?php echo $mnf[$detail['month']].' '.$detail['year']; ?></span></div>
      <div class="detail-row"><span class="detail-label">Payslip #</span><span class="detail-value">#<?php echo str_pad($detail['id'],4,'0',STR_PAD_LEFT); ?></span></div>
      <?php if (!empty($detail['payment_date'])): ?>
      <div class="detail-row"><span class="detail-label">Payment Date</span><span class="detail-value"><?php echo date('M d, Y', strtotime($detail['payment_date'])); ?></span></div>
      <?php endif; ?>
      <?php if ($detail['approved_by']): ?>
      <div class="detail-row"><span class="detail-label">Approved By</span><span class="detail-value"><?php echo htmlspecialchars(($detail['apr_fn']??'').' '.($detail['apr_ln']??'')); ?></span></div>
      <?php endif; ?>
    </div>
    <div style="background:#F8F9FA;border-radius:10px;padding:16px;">
      <div style="font-size:13px;font-weight:700;color:#2C3E50;margin-bottom:12px;"><i class="fas fa-calendar-check" style="color:#27AE60;margin-right:6px;"></i> Attendance</div>
      <div class="detail-row"><span class="detail-label">Present</span><span class="detail-value" style="color:#27AE60;"><?php echo $detail['present_days']; ?> days</span></div>
      <div class="detail-row"><span class="detail-label">Late</span><span class="detail-value" style="color:#F39C12;"><?php echo $detail['late_days']; ?> days</span></div>
      <div class="detail-row"><span class="detail-label">Half Day</span><span class="detail-value" style="color:#9B59B6;"><?php echo $detail['half_day_count']; ?> days</span></div>
      <div class="detail-row"><span class="detail-label">Absent</span><span class="detail-value" style="color:#E74C3C;"><?php echo $detail['absent_days']; ?> days</span></div>
      <div class="detail-row"><span class="detail-label">Overtime</span><span class="detail-value"><?php echo $detail['overtime_hours']; ?> hrs</span></div>
    </div>
    <div style="background:#F8F9FA;border-radius:10px;padding:16px;">
      <div style="font-size:13px;font-weight:700;color:#2C3E50;margin-bottom:12px;"><i class="fas fa-calculator" style="color:#9B59B6;margin-right:6px;"></i> Salary Breakdown</div>
      <div class="detail-row"><span class="detail-label">Base Salary</span><span class="detail-value">ETB <?php echo number_format($detail['base_salary'],2); ?></span></div>
      <div class="detail-row"><span class="detail-label">Basic Earned</span><span class="detail-value">ETB <?php echo number_format($detail['basic_earned'],2); ?></span></div>
      <div class="detail-row"><span class="detail-label">Overtime Pay</span><span class="detail-value">ETB <?php echo number_format($detail['overtime_pay'],2); ?></span></div>
      <div class="detail-row"><span class="detail-label" style="color:#27AE60;">Bonus</span><span class="detail-value" style="color:#27AE60;">ETB <?php echo number_format($detail['bonus']??0,2); ?></span></div>
      <div class="detail-row"><span class="detail-label" style="color:#27AE60;font-weight:700;">Gross Salary</span><span class="detail-value" style="color:#27AE60;">ETB <?php echo number_format($detail['gross_salary'],2); ?></span></div>
      <div class="detail-row"><span class="detail-label" style="color:#E74C3C;">Tax</span><span class="detail-value" style="color:#E74C3C;">- ETB <?php echo number_format($detail['tax_amount'],2); ?></span></div>
      <div class="detail-row"><span class="detail-label" style="color:#E74C3C;">Other Deductions</span><span class="detail-value" style="color:#E74C3C;">- ETB <?php echo number_format($detail['other_deductions'],2); ?></span></div>
      <div class="detail-row" style="border-top:2px solid #2C3E50;margin-top:6px;padding-top:10px;">
        <span class="detail-label" style="font-size:15px;color:#2C3E50;">Net Salary</span>
        <span class="detail-value" style="font-size:18px;color:#2C3E50;">ETB <?php echo number_format($detail['net_salary'],2); ?></span>
      </div>
    </div>
  </div>
  <?php if (!empty($detail['notes'])): ?>
  <div style="margin-top:16px;background:#FEF9E7;border:1.5px solid #F39C1240;border-radius:8px;padding:12px 16px;">
    <div style="font-size:12px;font-weight:700;color:#E67E22;margin-bottom:4px;"><i class="fas fa-sticky-note"></i> Notes</div>
    <div style="font-size:13px;color:#555;"><?php echo nl2br(htmlspecialchars($detail['notes'])); ?></div>
  </div>
  <?php endif; ?>
</div>
<?php else: ?>
<div class="section-card">
  <div class="section-header"><h2 class="section-title"><i class="fas fa-list-alt"></i> Payslip History</h2></div>
  <?php if (empty($records)): ?>
  <div style="text-align:center;padding:60px 20px;color:#95A5A6;">
    <i class="fas fa-file-invoice-dollar" style="font-size:56px;opacity:.3;display:block;margin-bottom:16px;"></i>
    <div style="font-size:16px;font-weight:600;margin-bottom:8px;">No approved payslips yet</div>
    <div style="font-size:13px;">Your payslips will appear here once the manager submits and admin approves them.</div>
  </div>
  <?php else: ?>
  <?php foreach ($records as $rec): ?>
  <div class="slip-card" onclick="window.location='<?php echo BASE_URL; ?>/public/employee/payroll?id=<?php echo $rec['id']; ?>'">
    <div style="display:flex;align-items:center;gap:16px;">
      <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#27AE60,#1E8449);color:#fff;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;flex-shrink:0;"><?php echo $mn[$rec['month']]; ?></div>
      <div>
        <div style="font-weight:700;font-size:14px;color:#2C3E50;"><?php echo $mnf[$rec['month']].' '.$rec['year']; ?></div>
        <div style="font-size:12px;color:#95A5A6;margin-top:2px;"><?php echo $rec['present_days']; ?> present &nbsp;|&nbsp; <?php echo $rec['overtime_hours']; ?> OT hrs<?php if (!empty($rec['payment_date'])): ?> &nbsp;|&nbsp; Paid <?php echo date('M d, Y', strtotime($rec['payment_date'])); ?><?php endif; ?></div>
      </div>
    </div>
    <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
      <div style="text-align:right;"><div style="font-size:11px;color:#95A5A6;">Gross</div><div style="font-size:13px;font-weight:600;color:#27AE60;">ETB <?php echo number_format($rec['gross_salary'],2); ?></div></div>
      <div style="text-align:right;"><div style="font-size:11px;color:#95A5A6;">Net Salary</div><div style="font-size:18px;font-weight:800;color:#2C3E50;">ETB <?php echo number_format($rec['net_salary'],2); ?></div></div>
      <span class="s-approved"><i class="fas fa-check-circle"></i> Approved</span>
      <i class="fas fa-chevron-right" style="color:#BDC3C7;"></i>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php endif; ?>

</div>
<script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
