<?php
// create_payroll is now embedded in payroll.php as a slide-in panel
header('Location: ' . BASE_URL . '/public/manager/payroll'); exit();
$csrf_token = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;
$managerName = $_SESSION['user_name'] ?? 'Manager';
$managerId   = $_SESSION['user_id'];

// ── AJAX: get salary config ──
if (isset($_GET['get_salary_config'], $_GET['employee_id'])) {
    header('Content-Type: application/json');
    try {
        $s = $pdo->prepare("SELECT * FROM furn_employee_salary WHERE employee_id=?");
        $s->execute([(int)$_GET['employee_id']]);
        $cfg = $s->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success'=>true,'config'=>$cfg ?: ['base_salary'=>0,'working_days_per_month'=>26,'overtime_rate'=>0]]);
    } catch (PDOException $e) { echo json_encode(['success'=>false,'error'=>$e->getMessage()]); }
    exit();
}

// ── AJAX: get attendance counts ──
if (isset($_GET['get_attendance'], $_GET['employee_id'], $_GET['month'], $_GET['year'])) {
    header('Content-Type: application/json');
    try {
        $empId = (int)$_GET['employee_id'];
        $month = (int)$_GET['month'];
        $year  = (int)$_GET['year'];
        $from  = sprintf('%04d-%02d-01', $year, $month);
        $to    = date('Y-m-t', strtotime($from));
        $stmt  = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM furn_attendance WHERE employee_id=? AND date BETWEEN ? AND ? GROUP BY status");
        $stmt->execute([$empId, $from, $to]);
        $counts = ['present'=>0,'absent'=>0,'late'=>0,'half_day'=>0];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) { if (isset($counts[$r['status']])) $counts[$r['status']] = (int)$r['cnt']; }
        echo json_encode(['success'=>true,'counts'=>$counts]);
    } catch (PDOException $e) { echo json_encode(['success'=>false,'error'=>$e->getMessage()]); }
    exit();
}

// ── Handle POST: save payroll ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_payroll') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Invalid CSRF token.';
    } else {
        try {
            $empId       = (int)$_POST['employee_id'];
            $month       = (int)$_POST['month'];
            $year        = (int)$_POST['year'];
            $baseSalary  = (float)$_POST['base_salary'];
            $bonus       = (float)($_POST['bonus'] ?? 0);
            $workDays    = max(1, (int)$_POST['working_days_per_month']);
            $otRate      = (float)$_POST['overtime_rate'];
            $presentDays = (int)$_POST['present_days'];
            $halfDays    = (int)$_POST['half_day_count'];
            $absentDays  = (int)$_POST['absent_days'];
            $lateDays    = (int)$_POST['late_days'];
            $otHours     = (float)$_POST['overtime_hours'];
            $taxAmt      = (float)$_POST['tax_amount'];
            $otherDed    = (float)$_POST['other_deductions'];
            $paymentDate = !empty($_POST['payment_date']) ? $_POST['payment_date'] : null;
            $notes       = trim($_POST['notes'] ?? '');

            $dailyRate   = $baseSalary / $workDays;
            $effDays     = $presentDays + $lateDays + ($halfDays * 0.5);
            $basicEarned = $dailyRate * $effDays;
            $otPay       = $otHours * $otRate;
            $gross       = $basicEarned + $otPay + $bonus;
            $net         = $gross - $taxAmt - $otherDed;

            // Upsert
            $pdo->prepare("INSERT INTO furn_payroll
                (employee_id,month,year,present_days,half_day_count,absent_days,late_days,overtime_hours,
                 base_salary,bonus,working_days_per_month,overtime_rate,basic_earned,overtime_pay,gross_salary,
                 tax_amount,other_deductions,net_salary,payment_date,status,notes,calculated_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'draft',?,?)
                ON DUPLICATE KEY UPDATE
                present_days=VALUES(present_days),half_day_count=VALUES(half_day_count),
                absent_days=VALUES(absent_days),late_days=VALUES(late_days),
                overtime_hours=VALUES(overtime_hours),base_salary=VALUES(base_salary),
                bonus=VALUES(bonus),working_days_per_month=VALUES(working_days_per_month),
                overtime_rate=VALUES(overtime_rate),basic_earned=VALUES(basic_earned),
                overtime_pay=VALUES(overtime_pay),gross_salary=VALUES(gross_salary),
                tax_amount=VALUES(tax_amount),other_deductions=VALUES(other_deductions),
                net_salary=VALUES(net_salary),payment_date=VALUES(payment_date),
                notes=VALUES(notes),calculated_by=VALUES(calculated_by),status='draft'
            ")->execute([$empId,$month,$year,$presentDays,$halfDays,$absentDays,$lateDays,$otHours,
                         $baseSalary,$bonus,$workDays,$otRate,$basicEarned,$otPay,$gross,$taxAmt,$otherDed,$net,$paymentDate,$notes,$managerId]);

            $newId = $pdo->lastInsertId();
            // If ON DUPLICATE KEY fired, lastInsertId() may be 0 — fetch the real id
            if (!$newId) {
                $r = $pdo->prepare("SELECT id FROM furn_payroll WHERE employee_id=? AND month=? AND year=?");
                $r->execute([$empId,$month,$year]);
                $newId = $r->fetchColumn();
            }
            $_SESSION['pay_success'] = "Payroll saved as draft.";
            header('Location: ' . BASE_URL . '/public/manager/payroll-details?id=' . $newId);
            exit();
        } catch (PDOException $e) { $error = "Save error: " . $e->getMessage(); }
    }
}

// ── Fetch employees ──
$employees = [];
try {
    $stmt = $pdo->query("SELECT id, first_name, last_name, email FROM furn_users WHERE role='employee' ORDER BY first_name, last_name");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $error = "Error fetching employees: " . $e->getMessage(); }

$monthNames = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
$pageTitle = 'Calculate Payroll';
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
        .calc-box { background:#F8F9FA; border:1.5px solid #E0E0E0; border-radius:12px; padding:20px; margin-top:20px; }
        .calc-row { display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid #ECEFF1; font-size:14px; }
        .calc-row:last-child { border-bottom:none; }
        .calc-total { font-size:18px; font-weight:800; color:#2C3E50; padding-top:12px; border-top:2px solid #2C3E50; margin-top:8px; }
        .field-group { margin-bottom:16px; }
        .field-group label { display:block; font-size:12px; font-weight:700; color:#555; margin-bottom:5px; text-transform:uppercase; letter-spacing:.4px; }
        .field-group input, .field-group select, .field-group textarea { width:100%; padding:10px 12px; border:1.5px solid #E0E0E0; border-radius:8px; font-family:inherit; font-size:13px; outline:none; box-sizing:border-box; }
        .field-group input:focus, .field-group select:focus { border-color:#3498DB; }
        .form-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        .form-grid-4 { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; }
        @media(max-width:600px) { .form-grid-2,.form-grid-4 { grid-template-columns:1fr 1fr; } }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>
    <div class="sidebar-overlay"></div>
    <?php include_once __DIR__ . '/../../includes/manager_sidebar.php'; ?>
    <!-- Top Header -->
    <?php 
    $pageTitle = 'Create Payroll';
    include_once __DIR__ . '/../../includes/manager_header.php'; 
    ?>
        <div class="header-left"><div class="system-status"><i class="fas fa-circle"></i> Calculate Payroll</div></div>
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
        <?php if (isset($error)): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Header -->
        <div class="pay-header">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                <div>
                    <h1 style="margin:0 0 6px;font-size:24px;"><i class="fas fa-calculator"></i> Calculate Employee Payroll</h1>
                    <p style="margin:0;opacity:.85;font-size:13px;">Select employee and month — attendance is auto-loaded from records</p>
                </div>
                <a href="<?php echo BASE_URL; ?>/public/manager/payroll"
                   style="padding:10px 18px;background:rgba(255,255,255,.15);color:#fff;border:2px solid rgba(255,255,255,.4);border-radius:10px;font-size:13px;font-weight:600;text-decoration:none;">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <div class="section-card">
        <form method="POST" id="payrollForm">
            <input type="hidden" name="action" value="save_payroll">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <!-- hidden computed fields -->
            <input type="hidden" name="present_days"   id="h_present">
            <input type="hidden" name="half_day_count" id="h_half">
            <input type="hidden" name="absent_days"    id="h_absent">
            <input type="hidden" name="late_days"      id="h_late">

            <!-- Step 1: Employee + Period -->
            <h3 style="color:#2C3E50;margin-bottom:16px;font-size:15px;"><span style="background:#3498DB;color:#fff;border-radius:50%;width:24px;height:24px;display:inline-flex;align-items:center;justify-content:center;font-size:12px;margin-right:8px;">1</span> Employee & Period</h3>
            <div class="form-grid-2" style="margin-bottom:20px;">
                <div class="field-group">
                    <label>Employee <span style="color:#E74C3C;">*</span></label>
                    <select name="employee_id" id="sel_emp" required onchange="onEmpChange()">
                        <option value="">— Select Employee —</option>
                        <?php foreach ($employees as $emp): ?>
                        <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['first_name'].' '.$emp['last_name']); ?> (<?php echo htmlspecialchars($emp['email']); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-grid-2" style="gap:12px;">
                    <div class="field-group">
                        <label>Month <span style="color:#E74C3C;">*</span></label>
                        <select name="month" id="sel_month" required onchange="onPeriodChange()">
                            <?php for ($m=1;$m<=12;$m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m==date('n')?'selected':''; ?>><?php echo $monthNames[$m]; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="field-group">
                        <label>Year <span style="color:#E74C3C;">*</span></label>
                        <select name="year" id="sel_year" required onchange="onPeriodChange()">
                            <?php for ($y=date('Y');$y>=date('Y')-3;$y--): ?>
                            <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Step 2: Salary Config -->
            <h3 style="color:#2C3E50;margin-bottom:16px;font-size:15px;"><span style="background:#27AE60;color:#fff;border-radius:50%;width:24px;height:24px;display:inline-flex;align-items:center;justify-content:center;font-size:12px;margin-right:8px;">2</span> Salary Configuration</h3>
            <div class="form-grid-2" style="margin-bottom:20px;">
                <div class="field-group">
                    <label>Base Salary (ETB/month) <span style="color:#E74C3C;">*</span></label>
                    <input type="number" name="base_salary" id="f_base" step="0.01" min="0" required placeholder="e.g. 8000" oninput="recalc()">
                </div>
                <div class="field-group">
                    <label>Working Days / Month <span style="color:#E74C3C;">*</span></label>
                    <input type="number" name="working_days_per_month" id="f_workdays" min="1" max="31" value="26" required oninput="recalc()">
                </div>
                <div class="field-group">
                    <label>Overtime Rate (ETB/hour)</label>
                    <input type="number" name="overtime_rate" id="f_otrate" step="0.01" min="0" value="0" oninput="recalc()">
                </div>
                <div class="field-group">
                    <label>Overtime Hours</label>
                    <input type="number" name="overtime_hours" id="f_othours" step="0.5" min="0" value="0" oninput="recalc()">
                </div>
                <div class="field-group">
                    <label>Bonus (ETB)</label>
                    <input type="number" name="bonus" id="f_bonus" step="0.01" min="0" value="0" oninput="recalc()" placeholder="0.00">
                </div>
                <div class="field-group">
                    <label>Payment Date</label>
                    <input type="date" name="payment_date" id="f_paydate" value="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>

            <!-- Step 3: Attendance (auto-loaded) -->
            <h3 style="color:#2C3E50;margin-bottom:16px;font-size:15px;"><span style="background:#F39C12;color:#fff;border-radius:50%;width:24px;height:24px;display:inline-flex;align-items:center;justify-content:center;font-size:12px;margin-right:8px;">3</span> Attendance Summary <span id="att_loading" style="font-size:12px;color:#95A5A6;font-weight:400;"></span></h3>
            <div class="form-grid-4" style="margin-bottom:20px;" id="att_grid">
                <div style="background:#27AE6010;border:1.5px solid #27AE6040;border-radius:10px;padding:14px;text-align:center;">
                    <div style="font-size:28px;font-weight:800;color:#27AE60;" id="d_present">—</div>
                    <div style="font-size:11px;color:#555;font-weight:600;margin-top:4px;">PRESENT</div>
                </div>
                <div style="background:#E74C3C10;border:1.5px solid #E74C3C40;border-radius:10px;padding:14px;text-align:center;">
                    <div style="font-size:28px;font-weight:800;color:#E74C3C;" id="d_absent">—</div>
                    <div style="font-size:11px;color:#555;font-weight:600;margin-top:4px;">ABSENT</div>
                </div>
                <div style="background:#F39C1210;border:1.5px solid #F39C1240;border-radius:10px;padding:14px;text-align:center;">
                    <div style="font-size:28px;font-weight:800;color:#F39C12;" id="d_late">—</div>
                    <div style="font-size:11px;color:#555;font-weight:600;margin-top:4px;">LATE</div>
                </div>
                <div style="background:#9B59B610;border:1.5px solid #9B59B640;border-radius:10px;padding:14px;text-align:center;">
                    <div style="font-size:28px;font-weight:800;color:#9B59B6;" id="d_half">—</div>
                    <div style="font-size:11px;color:#555;font-weight:600;margin-top:4px;">HALF DAY</div>
                </div>
            </div>

            <!-- Step 4: Deductions -->
            <h3 style="color:#2C3E50;margin-bottom:16px;font-size:15px;"><span style="background:#E74C3C;color:#fff;border-radius:50%;width:24px;height:24px;display:inline-flex;align-items:center;justify-content:center;font-size:12px;margin-right:8px;">4</span> Deductions</h3>
            <div class="form-grid-2" style="margin-bottom:20px;">
                <div class="field-group">
                    <label>Tax Amount (ETB)</label>
                    <input type="number" name="tax_amount" id="f_tax" step="0.01" min="0" value="0" oninput="recalc()">
                </div>
                <div class="field-group">
                    <label>Other Deductions (ETB)</label>
                    <input type="number" name="other_deductions" id="f_otherded" step="0.01" min="0" value="0" oninput="recalc()">
                </div>
            </div>

            <!-- Notes -->
            <div class="field-group" style="margin-bottom:20px;">
                <label>Notes (optional)</label>
                <textarea name="notes" rows="2" placeholder="Any remarks about this payroll..."></textarea>
            </div>

            <!-- Calculation Summary -->
            <div class="calc-box" id="calcBox" style="display:none;">
                <h3 style="margin:0 0 16px;font-size:15px;color:#2C3E50;"><i class="fas fa-receipt" style="color:#9B59B6;margin-right:8px;"></i> Payroll Summary</h3>
                <div class="calc-row"><span style="color:#7F8C8D;">Daily Rate</span><span id="r_daily">ETB 0.00</span></div>
                <div class="calc-row"><span style="color:#7F8C8D;">Effective Days</span><span id="r_effdays">0</span></div>
                <div class="calc-row"><span style="color:#7F8C8D;">Basic Earned</span><span id="r_basic">ETB 0.00</span></div>
                <div class="calc-row"><span style="color:#7F8C8D;">Overtime Pay</span><span id="r_otpay">ETB 0.00</span></div>
                <div class="calc-row"><span style="color:#27AE60;">Bonus</span><span id="r_bonus" style="color:#27AE60;">ETB 0.00</span></div>
                <div class="calc-row"><span style="color:#27AE60;font-weight:700;">Gross Salary</span><span id="r_gross" style="color:#27AE60;font-weight:700;">ETB 0.00</span></div>
                <div class="calc-row"><span style="color:#E74C3C;">Tax</span><span id="r_tax" style="color:#E74C3C;">ETB 0.00</span></div>
                <div class="calc-row"><span style="color:#E74C3C;">Other Deductions</span><span id="r_otherded" style="color:#E74C3C;">ETB 0.00</span></div>
                <div class="calc-row calc-total"><span>Net Salary</span><span id="r_net">ETB 0.00</span></div>
            </div>

            <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:24px;">
                <a href="<?php echo BASE_URL; ?>/public/manager/payroll"
                   style="padding:11px 22px;background:#F0F2F5;color:#555;border:none;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="submit" id="saveBtn" disabled
                    style="padding:11px 28px;background:linear-gradient(135deg,#2C3E50,#3D1F14);color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;opacity:.5;transition:opacity .2s;">
                    <i class="fas fa-save"></i> Save as Draft
                </button>
            </div>
        </form>
        </div>
    </div>

    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
    <script>
    const BASE = '<?php echo BASE_URL; ?>';
    let attCounts = {present:0,absent:0,late:0,half_day:0};
    let attLoaded = false;

    function onEmpChange() { loadAttendance(); loadSalaryConfig(); }
    function onPeriodChange() { loadAttendance(); }

    function loadSalaryConfig() {
        const empId = document.getElementById('sel_emp').value;
        if (!empId) return;
        fetch(`?get_salary_config=1&employee_id=${empId}`)
            .then(r => r.json()).then(d => {
                if (d.success && d.config) {
                    document.getElementById('f_base').value     = d.config.base_salary     || '';
                    document.getElementById('f_workdays').value = d.config.working_days_per_month || 26;
                    document.getElementById('f_otrate').value   = d.config.overtime_rate   || 0;
                    recalc();
                }
            }).catch(()=>{});
    }

    function loadAttendance() {
        const empId = document.getElementById('sel_emp').value;
        const month = document.getElementById('sel_month').value;
        const year  = document.getElementById('sel_year').value;
        if (!empId || !month || !year) return;
        document.getElementById('att_loading').textContent = '(loading…)';
        fetch(`?get_attendance=1&employee_id=${empId}&month=${month}&year=${year}`)
            .then(r => r.json()).then(d => {
                document.getElementById('att_loading').textContent = '';
                if (d.success) {
                    attCounts = d.counts;
                    attLoaded = true;
                    document.getElementById('d_present').textContent = d.counts.present;
                    document.getElementById('d_absent').textContent  = d.counts.absent;
                    document.getElementById('d_late').textContent    = d.counts.late;
                    document.getElementById('d_half').textContent    = d.counts.half_day;
                    document.getElementById('h_present').value = d.counts.present;
                    document.getElementById('h_half').value    = d.counts.half_day;
                    document.getElementById('h_absent').value  = d.counts.absent;
                    document.getElementById('h_late').value    = d.counts.late;
                    recalc();
                }
            }).catch(()=>{ document.getElementById('att_loading').textContent = '(error)'; });
    }

    function recalc() {
        const base     = parseFloat(document.getElementById('f_base').value)     || 0;
        const workDays = parseFloat(document.getElementById('f_workdays').value) || 26;
        const otRate   = parseFloat(document.getElementById('f_otrate').value)   || 0;
        const otHours  = parseFloat(document.getElementById('f_othours').value)  || 0;
        const bonus    = parseFloat(document.getElementById('f_bonus').value)    || 0;
        const tax      = parseFloat(document.getElementById('f_tax').value)      || 0;
        const otherDed = parseFloat(document.getElementById('f_otherded').value) || 0;

        const present  = attLoaded ? attCounts.present  : 0;
        const half     = attLoaded ? attCounts.half_day : 0;
        const late     = attLoaded ? attCounts.late     : 0;

        const dailyRate  = base / workDays;
        const effDays    = present + late + (half * 0.5);
        const basicEarned= dailyRate * effDays;
        const otPay      = otHours * otRate;
        const gross      = basicEarned + otPay + bonus;
        const net        = gross - tax - otherDed;

        const fmt = v => 'ETB ' + v.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g,',');
        document.getElementById('r_daily').textContent   = fmt(dailyRate);
        document.getElementById('r_effdays').textContent = effDays.toFixed(1) + ' days';
        document.getElementById('r_basic').textContent   = fmt(basicEarned);
        document.getElementById('r_otpay').textContent   = fmt(otPay);
        document.getElementById('r_bonus').textContent   = fmt(bonus);
        document.getElementById('r_gross').textContent   = fmt(gross);
        document.getElementById('r_tax').textContent     = fmt(tax);
        document.getElementById('r_otherded').textContent= fmt(otherDed);
        document.getElementById('r_net').textContent     = fmt(net);

        const calcBox = document.getElementById('calcBox');
        const saveBtn = document.getElementById('saveBtn');
        if (base > 0) {
            calcBox.style.display = 'block';
            saveBtn.disabled = false;
            saveBtn.style.opacity = '1';
        }
    }
    </script>
</body>
</html>
