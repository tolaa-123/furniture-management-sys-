<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . BASE_URL . '/public/login'); exit();
}
require_once __DIR__ . '/../../../config/db_config.php';

// Check database connection
if (!$pdo) {
    die('<div style="padding:20px;background:#f8d7da;color:#721c24;border-radius:8px;margin:20px;text-align:center;"><h3>Database Connection Error</h3><p>Unable to connect to the database. Please check your configuration.</p></div>');
}

$adminName = $_SESSION['user_name'] ?? 'Admin User';

// ── Date range & active tab ──
$dateFrom = $_GET['from']   ?? date('Y-m-01');
$dateTo   = $_GET['to']     ?? date('Y-m-d');
$report   = $_GET['report'] ?? 'sales';
if ($dateFrom > $dateTo) $dateFrom = $dateTo;

// ── Overhead rate ──
$overheadRate = 0.10;
try {
    $s = $pdo->query("SELECT setting_value FROM furn_settings WHERE setting_key='overhead_rate' LIMIT 1");
    $ov = $s->fetch(PDO::FETCH_ASSOC);
    if ($ov && floatval($ov['setting_value']) > 0) $overheadRate = floatval($ov['setting_value']) / 100;
} catch (PDOException $e) {}

// ── Income tax rate ──
$incomeTaxRate = 0.0;
try {
    $s = $pdo->query("SELECT setting_value FROM furn_settings WHERE setting_key='income_tax_rate' LIMIT 1");
    $tv = $s->fetchColumn();
    if ($tv !== false) $incomeTaxRate = floatval($tv) / 100;
} catch (PDOException $e) {}

// ══ DATA QUERIES ══

// 1. SALES
$salesSummary = ['total_orders'=>0,'total_revenue'=>0,'avg_order_value'=>0,'completed'=>0,'cancelled'=>0,'pending'=>0];
$salesRows = [];
if ($report === 'sales') {
    try {
        $s = $pdo->prepare("
            SELECT o.id, o.order_number, o.status, o.created_at,
                   COALESCE(o.estimated_cost,0) as order_value,
                   COALESCE(SUM(p.amount),0) as paid,
                   CONCAT(u.first_name,' ',u.last_name) as customer_name
            FROM furn_orders o
            LEFT JOIN furn_users u ON o.customer_id = u.id
            LEFT JOIN furn_payments p ON p.order_id = o.id AND p.status IN ('approved','verified')
            WHERE DATE(o.created_at) BETWEEN ? AND ?
            GROUP BY o.id ORDER BY o.created_at DESC
        ");
        $s->execute([$dateFrom, $dateTo]);
        $salesRows = $s->fetchAll(PDO::FETCH_ASSOC);
        foreach ($salesRows as $r) {
            $salesSummary['total_orders']++;
            $salesSummary['total_revenue'] += floatval($r['paid']);
            if ($r['status'] === 'completed') $salesSummary['completed']++;
            if ($r['status'] === 'cancelled')  $salesSummary['cancelled']++;
            if (in_array($r['status'], ['pending_review','pending_cost_approval'])) $salesSummary['pending']++;
        }
        $salesSummary['avg_order_value'] = $salesSummary['total_orders'] > 0
            ? $salesSummary['total_revenue'] / $salesSummary['total_orders'] : 0;
    } catch (PDOException $e) { error_log($e->getMessage()); }
}

// 2. PRODUCTION
$prodSummary = ['total_tasks'=>0,'completed'=>0,'in_progress'=>0,'pending'=>0,'avg_progress'=>0];
$prodRows = [];
if ($report === 'production') {
    try {
        $s = $pdo->prepare("
            SELECT t.id, t.status, t.progress, t.created_at, t.completed_at,
                   o.order_number, o.furniture_name, o.furniture_type,
                   CONCAT(e.first_name,' ',e.last_name) as employee_name
            FROM furn_production_tasks t
            LEFT JOIN furn_orders o ON t.order_id = o.id
            LEFT JOIN furn_users e ON t.employee_id = e.id
            WHERE DATE(t.created_at) BETWEEN ? AND ?
            ORDER BY t.created_at DESC
        ");
        $s->execute([$dateFrom, $dateTo]);
        $prodRows = $s->fetchAll(PDO::FETCH_ASSOC);
        $totalProg = 0;
        foreach ($prodRows as $r) {
            $prodSummary['total_tasks']++;
            $totalProg += intval($r['progress']);
            if ($r['status'] === 'completed')   $prodSummary['completed']++;
            elseif ($r['status'] === 'in_progress') $prodSummary['in_progress']++;
            else $prodSummary['pending']++;
        }
        $prodSummary['avg_progress'] = $prodSummary['total_tasks'] > 0
            ? round($totalProg / $prodSummary['total_tasks'], 1) : 0;
    } catch (PDOException $e) { error_log($e->getMessage()); }
}

// 3. PAYROLL — admin sees ALL roles including managers
$paySummary = ['total_employees'=>0,'total_net'=>0,'approved'=>0,'pending'=>0];
$payRows = [];
if ($report === 'payroll') {
    try {
        $s = $pdo->prepare("
            SELECT p.*, CONCAT(u.first_name,' ',u.last_name) as employee_name, u.role
            FROM furn_payroll p
            LEFT JOIN furn_users u ON p.employee_id = u.id
            WHERE STR_TO_DATE(CONCAT(p.year,'-',LPAD(p.month,2,'0'),'-01'),'%Y-%m-%d') BETWEEN ? AND ?
            ORDER BY p.year DESC, p.month DESC, u.first_name
        ");
        $s->execute([$dateFrom, $dateTo]);
        $payRows = $s->fetchAll(PDO::FETCH_ASSOC);
        foreach ($payRows as $r) {
            $paySummary['total_employees']++;
            $paySummary['total_net'] += floatval($r['net_salary']);
            if ($r['status'] === 'approved') $paySummary['approved']++;
            else $paySummary['pending']++;
        }
    } catch (PDOException $e) { error_log($e->getMessage()); }
}

// 4. MATERIALS
$matSummary = ['total_types'=>0,'low_stock'=>0,'total_value'=>0,'used_cost'=>0,'purchase_cost'=>0,'waste_rate'=>0,'unlogged_orders'=>0];
$matLowStock = [];
$matUsageByMaterial = []; $matUsageByEmployee = []; $matMonthlyTrend = []; $matUsageDetail = []; $matPurchaseHistory = []; $matTotalPurchaseCost = 0;
if ($report === 'materials') {
    try {
        $matRows = $pdo->query("SELECT id, name, current_stock, minimum_stock, unit, cost_per_unit, COALESCE(reserved_stock,0) as reserved, (current_stock-COALESCE(reserved_stock,0)) as available FROM furn_materials ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($matRows as $r) {
            $matSummary['total_types']++;
            $matSummary['total_value'] += floatval($r['current_stock']) * floatval($r['cost_per_unit']);
            if (floatval($r['available']) < floatval($r['minimum_stock'])) { $matSummary['low_stock']++; $matLowStock[] = $r; }
        }
        $matLowStock = array_slice($matLowStock, 0, 5);
        $s = $pdo->prepare("SELECT COALESCE(SUM(total_cost),0) FROM furn_material_purchases WHERE purchase_date BETWEEN ? AND ?");
        $s->execute([$dateFrom, $dateTo]); $matSummary['purchase_cost'] = floatval($s->fetchColumn());
        $s = $pdo->prepare("SELECT COALESCE(SUM(total_cost),0) FROM furn_order_materials WHERE DATE(created_at) BETWEEN ? AND ?");
        $s->execute([$dateFrom, $dateTo]); $matSummary['used_cost'] = floatval($s->fetchColumn());
        $s = $pdo->prepare("SELECT COALESCE(SUM(quantity_used),0) as u, COALESCE(SUM(waste_amount),0) as w FROM furn_material_usage WHERE DATE(created_at) BETWEEN ? AND ?");
        $s->execute([$dateFrom, $dateTo]); $wr = $s->fetch(PDO::FETCH_ASSOC);
        $tu = floatval($wr['u']); $tw = floatval($wr['w']);
        $matSummary['waste_rate'] = ($tu+$tw) > 0 ? round(($tw/($tu+$tw))*100,1) : 0;
        $matSummary['unlogged_orders'] = (int)$pdo->query("SELECT COUNT(DISTINCT o.id) FROM furn_orders o LEFT JOIN furn_order_materials om ON om.order_id=o.id WHERE o.status='completed' AND om.id IS NULL")->fetchColumn();

        // Usage by material (sub-report)
        $s = $pdo->query("SELECT m.name as material_name, m.unit, COUNT(mu.id) as report_count, SUM(mu.quantity_used) as total_used, SUM(mu.waste_amount) as total_waste, SUM(mu.quantity_used+mu.waste_amount) as total_consumed, SUM((mu.quantity_used+mu.waste_amount)*m.cost_per_unit) as total_cost FROM furn_material_usage mu JOIN furn_materials m ON mu.material_id=m.id GROUP BY mu.material_id,m.name,m.unit ORDER BY total_consumed DESC");
        $matUsageByMaterial = $s->fetchAll(PDO::FETCH_ASSOC);

        // Usage by employee
        $s = $pdo->query("SELECT CONCAT(u.first_name,' ',u.last_name) as employee_name, COUNT(mu.id) as report_count, SUM(mu.quantity_used) as total_used, SUM(mu.waste_amount) as total_waste FROM furn_material_usage mu JOIN furn_users u ON mu.employee_id=u.id GROUP BY mu.employee_id,u.first_name,u.last_name ORDER BY total_used DESC");
        $matUsageByEmployee = $s->fetchAll(PDO::FETCH_ASSOC);

        // Monthly trend (last 6 months)
        $s = $pdo->query("SELECT DATE_FORMAT(mu.created_at,'%Y-%m') as month, SUM(mu.quantity_used) as total_used, SUM(mu.waste_amount) as total_waste, COUNT(mu.id) as report_count FROM furn_material_usage mu WHERE mu.created_at>=DATE_SUB(NOW(),INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(mu.created_at,'%Y-%m') ORDER BY month ASC");
        $matMonthlyTrend = $s->fetchAll(PDO::FETCH_ASSOC);

        // Usage detail
        $s = $pdo->query("SELECT mu.*, m.name as material_name, m.unit, m.cost_per_unit, CONCAT(u.first_name,' ',u.last_name) as employee_name, o.order_number FROM furn_material_usage mu JOIN furn_materials m ON mu.material_id=m.id JOIN furn_users u ON mu.employee_id=u.id LEFT JOIN furn_production_tasks t ON mu.task_id=t.id LEFT JOIN furn_orders o ON t.order_id=o.id ORDER BY mu.created_at DESC LIMIT 200");
        $matUsageDetail = $s->fetchAll(PDO::FETCH_ASSOC);

        // Purchase history
        $s = $pdo->query("SELECT fp.*, m.name as material_name, m.unit, CONCAT(u.first_name,' ',u.last_name) as recorded_by FROM furn_material_purchases fp LEFT JOIN furn_materials m ON fp.material_id=m.id LEFT JOIN furn_users u ON fp.manager_id=u.id ORDER BY fp.purchase_date DESC LIMIT 100");
        $matPurchaseHistory = $s->fetchAll(PDO::FETCH_ASSOC);
        foreach ($matPurchaseHistory as $p) $matTotalPurchaseCost += floatval($p['total_cost']);
    } catch (PDOException $e) { error_log($e->getMessage()); }
}

// 5. ATTENDANCE
$attSummary = ['total_records'=>0,'present'=>0,'absent'=>0,'late'=>0,'rate'=>0];
$attRows = [];
if ($report === 'attendance') {
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM furn_attendance")->fetchAll(PDO::FETCH_COLUMN);
        $dateExpr = in_array('date', $cols) ? 'date' : 'DATE(check_in_time)';
        $s = $pdo->prepare("SELECT a.*, $dateExpr as att_date, CONCAT(u.first_name,' ',u.last_name) as employee_name FROM furn_attendance a LEFT JOIN furn_users u ON a.employee_id = u.id WHERE $dateExpr BETWEEN ? AND ? ORDER BY $dateExpr DESC, u.first_name");
        $s->execute([$dateFrom, $dateTo]);
        $attRows = $s->fetchAll(PDO::FETCH_ASSOC);
        foreach ($attRows as $r) {
            $attSummary['total_records']++;
            if (in_array($r['status'], ['present','late'])) $attSummary['present']++;
            if ($r['status'] === 'absent') $attSummary['absent']++;
            if ($r['status'] === 'late')   $attSummary['late']++;
        }
        $attSummary['rate'] = $attSummary['total_records'] > 0
            ? round(($attSummary['present'] / $attSummary['total_records']) * 100, 1) : 0;
    } catch (PDOException $e) { error_log($e->getMessage()); }
}

// 6. RATINGS
$ratSummary = ['total'=>0,'avg'=>0,'five_star'=>0,'one_star'=>0];
$ratRows = [];
if ($report === 'ratings') {
    try {
        $s = $pdo->prepare("SELECT r.rating, r.review_text, r.created_at, o.order_number, o.furniture_name, CONCAT(c.first_name,' ',c.last_name) as customer_name, CONCAT(e.first_name,' ',e.last_name) as employee_name FROM furn_ratings r LEFT JOIN furn_orders o ON r.order_id=o.id LEFT JOIN furn_users c ON r.customer_id=c.id LEFT JOIN furn_users e ON r.employee_id=e.id WHERE DATE(r.created_at) BETWEEN ? AND ? ORDER BY r.created_at DESC");
        $s->execute([$dateFrom, $dateTo]);
        $ratRows = $s->fetchAll(PDO::FETCH_ASSOC);
        $sum = 0;
        foreach ($ratRows as $r) { $sum += intval($r['rating']); if ($r['rating']==5) $ratSummary['five_star']++; if ($r['rating']==1) $ratSummary['one_star']++; }
        $ratSummary['total'] = count($ratRows);
        $ratSummary['avg']   = $ratSummary['total'] > 0 ? round($sum/$ratSummary['total'],1) : 0;
    } catch (PDOException $e) { error_log($e->getMessage()); }
}

// 7. PROFIT
$profitSummary = ['revenue'=>0,'material_cost'=>0,'payroll_cost'=>0,'overhead'=>0,'net_profit'=>0,'margin'=>0];
$profitByMonth = [];
if ($report === 'profit') {
    try {
        $s = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM furn_payments WHERE status IN ('approved','verified') AND DATE(created_at) BETWEEN ? AND ?");
        $s->execute([$dateFrom,$dateTo]); $profitSummary['revenue'] = floatval($s->fetchColumn());
        $s = $pdo->prepare("SELECT COALESCE(SUM(total_cost),0) FROM furn_order_materials WHERE DATE(created_at) BETWEEN ? AND ?");
        $s->execute([$dateFrom,$dateTo]); $profitSummary['material_cost'] = floatval($s->fetchColumn());
        $s = $pdo->prepare("SELECT COALESCE(SUM(net_salary),0) FROM furn_payroll WHERE status='approved' AND STR_TO_DATE(CONCAT(year,'-',LPAD(month,2,'0'),'-01'),'%Y-%m-%d') BETWEEN ? AND ?");
        $s->execute([$dateFrom,$dateTo]); $profitSummary['payroll_cost'] = floatval($s->fetchColumn());
        $profitSummary['overhead']    = $profitSummary['revenue'] * $overheadRate;
        $profitSummary['net_profit']  = $profitSummary['revenue'] - $profitSummary['material_cost'] - $profitSummary['payroll_cost'] - $profitSummary['overhead'];
        $profitSummary['income_tax']  = $profitSummary['net_profit'] > 0 ? round($profitSummary['net_profit'] * $incomeTaxRate, 2) : 0;
        $profitSummary['net_profit'] -= $profitSummary['income_tax'];
        $profitSummary['margin']      = $profitSummary['revenue'] > 0 ? round(($profitSummary['net_profit']/$profitSummary['revenue'])*100,1) : 0;

        $s = $pdo->prepare("SELECT DATE_FORMAT(created_at,'%Y-%m') as ym, DATE_FORMAT(created_at,'%M %Y') as label, SUM(amount) as revenue FROM furn_payments WHERE status IN ('approved','verified') AND DATE(created_at) BETWEEN ? AND ? GROUP BY ym ORDER BY ym ASC");
        $s->execute([$dateFrom,$dateTo]); $revByM = [];
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) $revByM[$r['ym']] = ['label'=>$r['label'],'revenue'=>floatval($r['revenue'])];
        $s = $pdo->prepare("SELECT DATE_FORMAT(created_at,'%Y-%m') as ym, SUM(total_cost) as mat FROM furn_order_materials WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY ym");
        $s->execute([$dateFrom,$dateTo]); $matByM = [];
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) $matByM[$r['ym']] = floatval($r['mat']);
        $s = $pdo->prepare("SELECT CONCAT(year,'-',LPAD(month,2,'0')) as ym, SUM(net_salary) as pay FROM furn_payroll WHERE status='approved' AND STR_TO_DATE(CONCAT(year,'-',LPAD(month,2,'0'),'-01'),'%Y-%m-%d') BETWEEN ? AND ? GROUP BY ym");
        $s->execute([$dateFrom,$dateTo]); $payByM = [];
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) $payByM[$r['ym']] = floatval($r['pay']);
        foreach ($revByM as $ym => $data) {
            $rev=$data['revenue']; $mat=$matByM[$ym]??0; $pay=$payByM[$ym]??0; $ovh=$rev*$overheadRate; $net=$rev-$mat-$pay-$ovh;
            $profitByMonth[] = ['label'=>$data['label'],'revenue'=>$rev,'material'=>$mat,'payroll'=>$pay,'overhead'=>$ovh,'net'=>$net,'margin'=>$rev>0?round(($net/$rev)*100,1):0];
        }
    } catch (PDOException $e) { error_log($e->getMessage()); }
}

$pageTitle = 'Reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - FurnitureCraft</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/css/admin-responsive.css">
    <style>
        .rpt-nav{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:24px;}
        .rpt-tab{padding:10px 18px;border-radius:8px;border:2px solid #e0e0e0;background:#fff;color:#555;font-size:13px;font-weight:600;cursor:pointer;text-decoration:none;transition:all .15s;display:inline-flex;align-items:center;gap:7px;}
        .rpt-tab:hover{border-color:#e67e22;color:#e67e22;}
        .rpt-tab.active{background:#e67e22;border-color:#e67e22;color:#fff;}
        .rpt-filter{background:#fff;border-radius:10px;padding:16px 20px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,0.07);display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
        .rpt-filter label{font-size:13px;font-weight:600;color:#555;}
        .rpt-filter input[type=date]{padding:8px 12px;border:1.5px solid #ddd;border-radius:8px;font-size:13px;font-family:inherit;outline:none;}
        .rpt-filter input[type=date]:focus{border-color:#e67e22;}
        .rpt-filter button{padding:9px 20px;background:#e67e22;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;}
        .sum-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:22px;}
        .sum-card{background:#fff;border-radius:10px;padding:16px 18px;box-shadow:0 2px 8px rgba(0,0,0,0.07);border-left:4px solid #ccc;}
        .sum-val{font-size:22px;font-weight:700;color:#2c3e50;line-height:1.2;}
        .sum-lbl{font-size:12px;color:#888;margin-top:3px;}
        .rpt-table{width:100%;border-collapse:collapse;font-size:13px;}
        .rpt-table thead tr{background:#2c3e50;color:#fff;}
        .rpt-table th{padding:11px 12px;text-align:left;font-size:12px;font-weight:600;white-space:nowrap;}
        .rpt-table td{padding:10px 12px;border-bottom:1px solid #f0f0f0;color:#444;}
        .rpt-table tbody tr:hover{background:#fafbfc;}
        .empty-rpt{text-align:center;padding:50px 20px;color:#aaa;}
        .empty-rpt i{font-size:40px;display:block;margin-bottom:12px;}
        @media print{.no-print{display:none!important;}.rpt-filter{display:none!important;}body{background:#fff;}.main-content{margin:0!important;padding:10px!important;}}
    </style>
</head>
<body>
<button class="mobile-menu-toggle no-print" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>
<div class="sidebar-overlay no-print"></div>
<?php include_once __DIR__.'/../../includes/admin_sidebar.php'; ?>
<?php $pageTitle='Reports'; include_once __DIR__.'/../../includes/admin_header.php'; ?>

<div class="main-content">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
        <div>
            <h1 style="margin:0;font-size:24px;color:#2c3e50;"><i class="fas fa-chart-bar" style="color:#e67e22;"></i> Reports</h1>
            <p style="margin:4px 0 0;color:#888;font-size:13px;">Period: <?php echo date('M j, Y',strtotime($dateFrom)); ?> — <?php echo date('M j, Y',strtotime($dateTo)); ?></p>
        </div>
        <button onclick="window.print()" class="no-print" style="padding:9px 18px;background:#2c3e50;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
            <i class="fas fa-print me-1"></i> Print Report
        </button>
    </div>

    <!-- Tabs -->
    <div class="rpt-nav no-print">
        <?php foreach([
            ['sales','fa-shopping-cart','Sales & Revenue'],
            ['production','fa-industry','Production'],
            ['payroll','fa-wallet','Payroll'],
            ['materials','fa-boxes','Materials'],
            ['attendance','fa-user-check','Attendance'],
            ['ratings','fa-star','Customer Ratings'],
            ['profit','fa-chart-line','Profit Summary'],
        ] as [$key,$icon,$label]): ?>
        <a href="?report=<?php echo $key; ?>&from=<?php echo $dateFrom; ?>&to=<?php echo $dateTo; ?>"
           class="rpt-tab <?php echo $report===$key?'active':''; ?>">
            <i class="fas <?php echo $icon; ?>"></i> <?php echo $label; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Date filter -->
    <form method="GET" class="rpt-filter no-print">
        <input type="hidden" name="report" value="<?php echo htmlspecialchars($report); ?>">
        <label>From</label><input type="date" name="from" value="<?php echo $dateFrom; ?>">
        <label>To</label><input type="date" name="to" value="<?php echo $dateTo; ?>">
        <button type="submit"><i class="fas fa-filter me-1"></i>Apply</button>
        <a href="?report=<?php echo $report; ?>&from=<?php echo date('Y-m-01'); ?>&to=<?php echo date('Y-m-d'); ?>" style="padding:9px 16px;background:#f0f0f0;color:#555;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">This Month</a>
        <a href="?report=<?php echo $report; ?>&from=<?php echo date('Y-01-01'); ?>&to=<?php echo date('Y-m-d'); ?>" style="padding:9px 16px;background:#f0f0f0;color:#555;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">This Year</a>
        <a href="?report=<?php echo $report; ?>&from=<?php echo date('Y-m-d', strtotime('monday this week')); ?>&to=<?php echo date('Y-m-d', strtotime('sunday this week')); ?>" style="padding:9px 16px;background:#f0f0f0;color:#555;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;">This Week</a>
    </form>

    <?php if($report==='sales'): ?>
    <div class="sum-grid">
        <div class="sum-card" style="border-color:#3498db;"><div class="sum-val" style="color:#3498db;"><?php echo $salesSummary['total_orders']; ?></div><div class="sum-lbl">Total Orders</div></div>
        <div class="sum-card" style="border-color:#27ae60;"><div class="sum-val" style="color:#27ae60;font-size:17px;">ETB <?php echo number_format($salesSummary['total_revenue'],0); ?></div><div class="sum-lbl">Total Revenue</div></div>
        <div class="sum-card" style="border-color:#f39c12;"><div class="sum-val" style="color:#f39c12;font-size:17px;">ETB <?php echo number_format($salesSummary['avg_order_value'],0); ?></div><div class="sum-lbl">Avg Order Value</div></div>
        <div class="sum-card" style="border-color:#27ae60;"><div class="sum-val" style="color:#27ae60;"><?php echo $salesSummary['completed']; ?></div><div class="sum-lbl">Completed</div></div>
        <div class="sum-card" style="border-color:#e74c3c;"><div class="sum-val" style="color:#e74c3c;"><?php echo $salesSummary['cancelled']; ?></div><div class="sum-lbl">Cancelled</div></div>
        <div class="sum-card" style="border-color:#f39c12;"><div class="sum-val" style="color:#f39c12;"><?php echo $salesSummary['pending']; ?></div><div class="sum-lbl">Pending</div></div>
    </div>
    <div class="section-card" style="padding:0;overflow:hidden;">
        <?php if(empty($salesRows)): ?><div class="empty-rpt"><i class="fas fa-inbox"></i>No orders found for this period.</div>
        <?php else: ?><div class="table-responsive"><table class="rpt-table">
            <thead><tr><th>#</th><th>Order Number</th><th>Customer</th><th>Order Value</th><th>Paid</th><th>Status</th><th>Date</th></tr></thead>
            <tbody><?php foreach($salesRows as $i=>$r): $sc=['completed'=>'#27ae60','cancelled'=>'#e74c3c','in_production'=>'#3498db','pending_review'=>'#f39c12'][$r['status']]??'#888'; ?>
            <tr><td style="color:#aaa;"><?php echo $i+1; ?></td><td><strong><?php echo htmlspecialchars($r['order_number']); ?></strong></td><td><?php echo htmlspecialchars($r['customer_name']); ?></td><td>ETB <?php echo number_format($r['order_value'],2); ?></td><td style="color:#27ae60;font-weight:600;">ETB <?php echo number_format($r['paid'],2); ?></td>
            <td><span style="background:<?php echo $sc; ?>18;color:<?php echo $sc; ?>;border-radius:12px;padding:3px 10px;font-size:11px;font-weight:600;"><?php echo ucwords(str_replace('_',' ',$r['status'])); ?></span></td>
            <td><?php echo date('M j, Y',strtotime($r['created_at'])); ?></td></tr>
            <?php endforeach; ?></tbody>
            <tfoot><tr style="background:#f8f9fa;font-weight:700;"><td colspan="3" style="padding:10px 12px;text-align:right;">Total:</td><td style="padding:10px 12px;">ETB <?php echo number_format(array_sum(array_column($salesRows,'order_value')),2); ?></td><td style="padding:10px 12px;color:#27ae60;">ETB <?php echo number_format($salesSummary['total_revenue'],2); ?></td><td colspan="2"></td></tr></tfoot>
        </table></div><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if($report==='production'): ?>
    <div class="sum-grid">
        <div class="sum-card" style="border-color:#3498db;"><div class="sum-val" style="color:#3498db;"><?php echo $prodSummary['total_tasks']; ?></div><div class="sum-lbl">Total Tasks</div></div>
        <div class="sum-card" style="border-color:#27ae60;"><div class="sum-val" style="color:#27ae60;"><?php echo $prodSummary['completed']; ?></div><div class="sum-lbl">Completed</div></div>
        <div class="sum-card" style="border-color:#f39c12;"><div class="sum-val" style="color:#f39c12;"><?php echo $prodSummary['in_progress']; ?></div><div class="sum-lbl">In Progress</div></div>
        <div class="sum-card" style="border-color:#888;"><div class="sum-val" style="color:#888;"><?php echo $prodSummary['pending']; ?></div><div class="sum-lbl">Pending</div></div>
        <div class="sum-card" style="border-color:#9b59b6;"><div class="sum-val" style="color:#9b59b6;"><?php echo $prodSummary['avg_progress']; ?>%</div><div class="sum-lbl">Avg Progress</div></div>
    </div>
    <div class="section-card" style="padding:0;overflow:hidden;">
        <?php if(empty($prodRows)): ?><div class="empty-rpt"><i class="fas fa-industry"></i>No production tasks found.</div>
        <?php else: ?><div class="table-responsive"><table class="rpt-table">
            <thead><tr><th>#</th><th>Furniture</th><th>Employee</th><th>Progress</th><th>Status</th><th>Created</th><th>Completed</th></tr></thead>
            <tbody><?php foreach($prodRows as $i=>$r): $sc=['completed'=>'#27ae60','in_progress'=>'#3498db','pending'=>'#f39c12'][$r['status']]??'#888'; $prog=intval($r['progress']); ?>
            <tr><td style="color:#aaa;"><?php echo $i+1; ?></td><td><?php echo htmlspecialchars($r['furniture_name']??$r['furniture_type']??'—'); ?></td><td><?php echo htmlspecialchars($r['employee_name']??'—'); ?></td>
            <td><div style="display:flex;align-items:center;gap:8px;"><div style="flex:1;background:#f0f0f0;border-radius:6px;height:8px;min-width:80px;"><div style="background:<?php echo $sc; ?>;height:100%;border-radius:6px;width:<?php echo $prog; ?>%;"></div></div><span style="font-size:12px;font-weight:600;color:<?php echo $sc; ?>;min-width:32px;"><?php echo $prog; ?>%</span></div></td>
            <td><span style="background:<?php echo $sc; ?>18;color:<?php echo $sc; ?>;border-radius:12px;padding:3px 10px;font-size:11px;font-weight:600;"><?php echo ucwords(str_replace('_',' ',$r['status'])); ?></span></td>
            <td><?php echo date('M j, Y',strtotime($r['created_at'])); ?></td><td><?php echo $r['completed_at']?date('M j, Y',strtotime($r['completed_at'])):'—'; ?></td></tr>
            <?php endforeach; ?></tbody>
        </table></div><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if($report==='payroll'): ?>
    <div class="sum-grid">
        <div class="sum-card" style="border-color:#3498db;"><div class="sum-val" style="color:#3498db;"><?php echo $paySummary['total_employees']; ?></div><div class="sum-lbl">Payroll Records</div></div>
        <div class="sum-card" style="border-color:#27ae60;"><div class="sum-val" style="color:#27ae60;font-size:17px;">ETB <?php echo number_format($paySummary['total_net'],0); ?></div><div class="sum-lbl">Total Net Salary</div></div>
        <div class="sum-card" style="border-color:#27ae60;"><div class="sum-val" style="color:#27ae60;"><?php echo $paySummary['approved']; ?></div><div class="sum-lbl">Approved</div></div>
        <div class="sum-card" style="border-color:#f39c12;"><div class="sum-val" style="color:#f39c12;"><?php echo $paySummary['pending']; ?></div><div class="sum-lbl">Pending</div></div>
    </div>
    <div class="section-card" style="padding:0;overflow:hidden;">
        <?php if(empty($payRows)): ?><div class="empty-rpt"><i class="fas fa-wallet"></i>No payroll records found.</div>
        <?php else: ?><div class="table-responsive"><table class="rpt-table">
            <thead><tr><th>#</th><th>Employee</th><th>Role</th><th>Month / Year</th><th>Basic Salary</th><th>Deductions</th><th>Net Salary</th><th>Status</th></tr></thead>
            <tbody><?php foreach($payRows as $i=>$r): $sc=$r['status']==='approved'?'#27ae60':'#f39c12'; $basic=floatval($r['basic_salary']??$r['gross_salary']??$r['net_salary']); $ded=max(0,$basic-floatval($r['net_salary'])); ?>
            <tr><td style="color:#aaa;"><?php echo $i+1; ?></td><td><strong><?php echo htmlspecialchars($r['employee_name']); ?></strong></td>
            <td><span style="background:#3498db18;color:#3498db;border-radius:10px;padding:2px 8px;font-size:11px;font-weight:600;"><?php echo ucfirst($r['role']??'employee'); ?></span></td>
            <td><?php echo date('F Y',mktime(0,0,0,$r['month'],1,$r['year'])); ?></td><td>ETB <?php echo number_format($basic,2); ?></td><td style="color:#e74c3c;">ETB <?php echo number_format($ded,2); ?></td><td style="font-weight:700;color:#27ae60;">ETB <?php echo number_format($r['net_salary'],2); ?></td>
            <td><span style="background:<?php echo $sc; ?>18;color:<?php echo $sc; ?>;border-radius:12px;padding:3px 10px;font-size:11px;font-weight:600;"><?php echo ucfirst($r['status']); ?></span></td></tr>
            <?php endforeach; ?></tbody>
            <tfoot><tr style="background:#f8f9fa;font-weight:700;"><td colspan="6" style="padding:10px 12px;text-align:right;">Total Net:</td><td style="padding:10px 12px;color:#27ae60;">ETB <?php echo number_format($paySummary['total_net'],2); ?></td><td></td></tr></tfoot>
        </table></div><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if($report==='materials'): ?>
    <div class="sum-grid">
        <div class="sum-card" style="border-color:#f39c12;"><div class="sum-val" style="color:#f39c12;"><?php echo $matSummary['total_types']; ?></div><div class="sum-lbl">Material Types</div></div>
        <div class="sum-card" style="border-color:#27ae60;"><div class="sum-val" style="color:#27ae60;font-size:17px;">ETB <?php echo number_format($matSummary['purchase_cost'],0); ?></div><div class="sum-lbl">Purchased (Period)</div></div>
        <div class="sum-card" style="border-color:#e74c3c;"><div class="sum-val" style="color:#e74c3c;font-size:17px;">ETB <?php echo number_format($matSummary['used_cost'],0); ?></div><div class="sum-lbl">Used Cost (Period)</div></div>
        <?php $wc=$matSummary['waste_rate']>15?'#e74c3c':($matSummary['waste_rate']>8?'#f39c12':'#27ae60'); ?>
        <div class="sum-card" style="border-color:<?php echo $wc; ?>;"><div class="sum-val" style="color:<?php echo $wc; ?>"><?php echo $matSummary['waste_rate']; ?>%</div><div class="sum-lbl">Waste Rate</div></div>
        <div class="sum-card" style="border-color:<?php echo $matSummary['low_stock']>0?'#e74c3c':'#27ae60'; ?>;"><div class="sum-val" style="color:<?php echo $matSummary['low_stock']>0?'#e74c3c':'#27ae60'; ?>"><?php echo $matSummary['low_stock']; ?></div><div class="sum-lbl">Low Stock Items</div></div>
        <div class="sum-card" style="border-color:#27ae60;"><div class="sum-val" style="color:#27ae60;font-size:17px;">ETB <?php echo number_format($matSummary['total_value'],0); ?></div><div class="sum-lbl">Inventory Value</div></div>
    </div>
    <?php if($matSummary['unlogged_orders']>0): ?>
    <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:10px;padding:14px 18px;margin-bottom:16px;">
        <strong style="color:#856404;"><i class="fas fa-exclamation-triangle me-1"></i><?php echo $matSummary['unlogged_orders']; ?> completed orders have no materials logged</strong>
        <div style="font-size:12px;color:#856404;margin-top:3px;">Profit is overstated for these orders.</div>
    </div>
    <?php endif; ?>
    <?php if(!empty($matLowStock)): ?>
    <div class="section-card" style="padding:0;overflow:hidden;">
        <div style="padding:12px 18px;border-bottom:1px solid #f0f0f0;"><span style="font-weight:600;color:#e74c3c;font-size:14px;"><i class="fas fa-exclamation-triangle me-1"></i>Low Stock — Needs Restocking</span></div>
        <div class="table-responsive"><table class="rpt-table">
            <thead><tr><th>Material</th><th>Available</th><th>Min Required</th><th>Unit</th><th>Unit Price</th></tr></thead>
            <tbody><?php foreach($matLowStock as $r): ?>
            <tr><td><strong><?php echo htmlspecialchars($r['name']); ?></strong></td><td style="color:#e74c3c;font-weight:700;"><?php echo number_format(floatval($r['available']),2); ?></td><td><?php echo number_format(floatval($r['minimum_stock']),2); ?></td><td><?php echo htmlspecialchars($r['unit']); ?></td><td>ETB <?php echo number_format(floatval($r['cost_per_unit']),2); ?></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
    </div>
    <?php endif; ?>

    <!-- ── Material Detail Sub-Tabs ── -->
    <div style="margin-top:28px;">
        <div style="display:flex;gap:4px;margin-bottom:16px;border-bottom:2px solid #e0e0e0;flex-wrap:wrap;">
            <?php foreach([['mat_usage','fa-boxes','Usage by Material'],['mat_employee','fa-users','By Employee'],['mat_trend','fa-chart-line','Monthly Trend'],['mat_detail','fa-list','Usage Detail'],['mat_purchase','fa-receipt','Purchase History']] as [$tid,$tic,$tlbl]): ?>
            <button onclick="switchMatTab('<?php echo $tid; ?>')" id="mtab_<?php echo $tid; ?>"
                style="padding:9px 16px;border:none;background:none;font-size:12px;font-weight:600;cursor:pointer;border-bottom:3px solid transparent;color:#7f8c8d;font-family:inherit;transition:all .2s;">
                <i class="fas <?php echo $tic; ?>" style="margin-right:5px;"></i><?php echo $tlbl; ?>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- Usage by Material -->
        <div id="mpanel_mat_usage">
            <div class="section-card" style="padding:0;overflow:hidden;">
                <div style="padding:12px 18px;border-bottom:1px solid #f0f0f0;font-weight:600;color:#2c3e50;font-size:14px;"><i class="fas fa-boxes" style="color:#3498db;margin-right:6px;"></i>Usage by Material</div>
                <?php if(empty($matUsageByMaterial)): ?><div class="empty-rpt"><i class="fas fa-inbox"></i>No usage data yet.</div>
                <?php else: ?><div class="table-responsive"><table class="rpt-table">
                    <thead><tr><th>Material</th><th>Reports</th><th>Total Used</th><th>Total Waste</th><th>Total Consumed</th><th>Cost</th></tr></thead>
                    <tbody><?php foreach($matUsageByMaterial as $r): ?>
                    <tr><td><strong><?php echo htmlspecialchars($r['material_name']); ?></strong> <small style="color:#aaa;">(<?php echo $r['unit']; ?>)</small></td>
                    <td><?php echo $r['report_count']; ?></td>
                    <td><?php echo number_format($r['total_used'],2); ?></td>
                    <td style="color:<?php echo floatval($r['total_waste'])>0?'#e74c3c':'#27ae60'; ?>"><?php echo number_format($r['total_waste'],2); ?></td>
                    <td><strong><?php echo number_format($r['total_consumed'],2); ?></strong></td>
                    <td>ETB <?php echo number_format($r['total_cost'],2); ?></td></tr>
                    <?php endforeach; ?></tbody>
                </table></div><?php endif; ?>
            </div>
        </div>

        <!-- Usage by Employee -->
        <div id="mpanel_mat_employee" style="display:none;">
            <div class="section-card" style="padding:0;overflow:hidden;">
                <div style="padding:12px 18px;border-bottom:1px solid #f0f0f0;font-weight:600;color:#2c3e50;font-size:14px;"><i class="fas fa-users" style="color:#9b59b6;margin-right:6px;"></i>Usage by Employee</div>
                <?php if(empty($matUsageByEmployee)): ?><div class="empty-rpt"><i class="fas fa-users"></i>No usage data yet.</div>
                <?php else: ?><div class="table-responsive"><table class="rpt-table">
                    <thead><tr><th>Employee</th><th>Reports</th><th>Total Used</th><th>Total Waste</th><th>Waste %</th></tr></thead>
                    <tbody><?php foreach($matUsageByEmployee as $r):
                        $wp = floatval($r['total_used'])>0 ? round((floatval($r['total_waste'])/floatval($r['total_used']))*100,1) : 0; ?>
                    <tr><td><strong><?php echo htmlspecialchars($r['employee_name']); ?></strong></td>
                    <td><?php echo $r['report_count']; ?></td>
                    <td><?php echo number_format($r['total_used'],2); ?></td>
                    <td style="color:<?php echo floatval($r['total_waste'])>0?'#e74c3c':'#27ae60'; ?>"><?php echo number_format($r['total_waste'],2); ?></td>
                    <td><span style="color:<?php echo $wp>10?'#e74c3c':($wp>5?'#f39c12':'#27ae60'); ?>;font-weight:600;"><?php echo $wp; ?>%</span></td></tr>
                    <?php endforeach; ?></tbody>
                </table></div><?php endif; ?>
            </div>
        </div>

        <!-- Monthly Trend -->
        <div id="mpanel_mat_trend" style="display:none;">
            <div class="section-card" style="padding:0;overflow:hidden;">
                <div style="padding:12px 18px;border-bottom:1px solid #f0f0f0;font-weight:600;color:#2c3e50;font-size:14px;"><i class="fas fa-chart-line" style="color:#27ae60;margin-right:6px;"></i>Monthly Trend (Last 6 Months)</div>
                <?php if(empty($matMonthlyTrend)): ?><div class="empty-rpt"><i class="fas fa-chart-line"></i>No trend data yet.</div>
                <?php else: ?><div class="table-responsive"><table class="rpt-table">
                    <thead><tr><th>Month</th><th>Total Used</th><th>Total Waste</th><th>Reports</th></tr></thead>
                    <tbody><?php foreach($matMonthlyTrend as $r): ?>
                    <tr><td><strong><?php echo date('F Y',strtotime($r['month'].'-01')); ?></strong></td>
                    <td><?php echo number_format($r['total_used'],2); ?></td>
                    <td style="color:<?php echo floatval($r['total_waste'])>0?'#e74c3c':'#27ae60'; ?>"><?php echo number_format($r['total_waste'],2); ?></td>
                    <td><?php echo $r['report_count']; ?></td></tr>
                    <?php endforeach; ?></tbody>
                </table></div><?php endif; ?>
            </div>
        </div>

        <!-- Usage Detail -->
        <div id="mpanel_mat_detail" style="display:none;">
            <div class="section-card" style="padding:0;overflow:hidden;">
                <div style="padding:12px 18px;border-bottom:1px solid #f0f0f0;font-weight:600;color:#2c3e50;font-size:14px;"><i class="fas fa-list" style="color:#e67e22;margin-right:6px;"></i>Usage Detail (Latest 200)</div>
                <?php if(empty($matUsageDetail)): ?><div class="empty-rpt"><i class="fas fa-list"></i>No usage records yet.</div>
                <?php else: ?><div class="table-responsive"><table class="rpt-table">
                    <thead><tr><th>Date</th><th>Employee</th><th>Material</th><th>Used</th><th>Waste</th><th>Order</th><th>Notes</th></tr></thead>
                    <tbody><?php foreach($matUsageDetail as $u): ?>
                    <tr><td><?php echo date('M d, Y',strtotime($u['created_at'])); ?></td>
                    <td><?php echo htmlspecialchars($u['employee_name']); ?></td>
                    <td><?php echo htmlspecialchars($u['material_name']); ?> <small style="color:#aaa;">(<?php echo $u['unit']; ?>)</small></td>
                    <td><?php echo number_format($u['quantity_used'],2); ?></td>
                    <td style="color:<?php echo floatval($u['waste_amount'])>0?'#e74c3c':'#27ae60'; ?>"><?php echo number_format($u['waste_amount'],2); ?></td>
                    <td><?php echo !empty($u['order_number'])?htmlspecialchars($u['order_number']):'<span style="color:#aaa;">N/A</span>'; ?></td>
                    <td style="font-size:12px;color:#666;"><?php echo htmlspecialchars($u['notes']??''); ?></td></tr>
                    <?php endforeach; ?></tbody>
                </table></div><?php endif; ?>
            </div>
        </div>

        <!-- Purchase History -->
        <div id="mpanel_mat_purchase" style="display:none;">
            <div class="section-card" style="padding:0;overflow:hidden;">
                <div style="padding:12px 18px;border-bottom:1px solid #f0f0f0;font-weight:600;color:#2c3e50;font-size:14px;"><i class="fas fa-receipt" style="color:#8e44ad;margin-right:6px;"></i>Purchase History</div>
                <?php if(empty($matPurchaseHistory)): ?><div class="empty-rpt"><i class="fas fa-receipt"></i>No purchase records yet.</div>
                <?php else: ?><div class="table-responsive"><table class="rpt-table">
                    <thead><tr><th>Date</th><th>Material</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Invoice</th><th>Supplier</th><th>By</th></tr></thead>
                    <tbody><?php foreach($matPurchaseHistory as $p): ?>
                    <tr><td><?php echo date('M d, Y',strtotime($p['purchase_date'])); ?></td>
                    <td><strong><?php echo htmlspecialchars($p['material_name']); ?></strong> <small style="color:#aaa;">(<?php echo $p['unit']; ?>)</small></td>
                    <td><?php echo number_format($p['quantity'],2); ?></td>
                    <td>ETB <?php echo number_format($p['unit_price'],2); ?></td>
                    <td><strong>ETB <?php echo number_format($p['total_cost'],2); ?></strong></td>
                    <td><?php echo $p['invoice_number']?htmlspecialchars($p['invoice_number']):'<span style="color:#aaa;">—</span>'; ?></td>
                    <td><?php echo $p['supplier']?htmlspecialchars($p['supplier']):'<span style="color:#aaa;">—</span>'; ?></td>
                    <td><?php echo htmlspecialchars($p['recorded_by']??'N/A'); ?></td></tr>
                    <?php endforeach; ?>
                    <tr style="background:#f8f9fa;font-weight:700;"><td colspan="4" style="padding:10px 12px;text-align:right;">Total:</td><td style="padding:10px 12px;">ETB <?php echo number_format($matTotalPurchaseCost,2); ?></td><td colspan="3"></td></tr>
                    </tbody>
                </table></div><?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if($report==='attendance'): ?>
    <div class="sum-grid">
        <div class="sum-card" style="border-color:#3498db;"><div class="sum-val" style="color:#3498db;"><?php echo $attSummary['total_records']; ?></div><div class="sum-lbl">Total Records</div></div>
        <div class="sum-card" style="border-color:#27ae60;"><div class="sum-val" style="color:#27ae60;"><?php echo $attSummary['present']; ?></div><div class="sum-lbl">Present / Late</div></div>
        <div class="sum-card" style="border-color:#e74c3c;"><div class="sum-val" style="color:#e74c3c;"><?php echo $attSummary['absent']; ?></div><div class="sum-lbl">Absent</div></div>
        <div class="sum-card" style="border-color:#f39c12;"><div class="sum-val" style="color:#f39c12;"><?php echo $attSummary['late']; ?></div><div class="sum-lbl">Late Arrivals</div></div>
        <div class="sum-card" style="border-color:#9b59b6;"><div class="sum-val" style="color:#9b59b6;"><?php echo $attSummary['rate']; ?>%</div><div class="sum-lbl">Attendance Rate</div></div>
    </div>
    <div class="section-card" style="padding:0;overflow:hidden;">
        <?php if(empty($attRows)): ?><div class="empty-rpt"><i class="fas fa-user-check"></i>No attendance records found.</div>
        <?php else: ?><div class="table-responsive"><table class="rpt-table">
            <thead><tr><th>#</th><th>Employee</th><th>Date</th><th>Check In</th><th>Check Out</th><th>Status</th><th>Overtime</th></tr></thead>
            <tbody><?php foreach($attRows as $i=>$r): $sc=['present'=>'#27ae60','late'=>'#f39c12','absent'=>'#e74c3c','half_day'=>'#9b59b6'][$r['status']]??'#888'; ?>
            <tr><td style="color:#aaa;"><?php echo $i+1; ?></td><td><strong><?php echo htmlspecialchars($r['employee_name']); ?></strong></td><td><?php echo date('M j, Y',strtotime($r['att_date'])); ?></td>
            <td><?php echo $r['check_in_time']?date('H:i',strtotime($r['check_in_time'])):'—'; ?></td>
            <td><?php echo !empty($r['check_out_time'])&&$r['check_out_time']!=='0000-00-00 00:00:00'?date('H:i',strtotime($r['check_out_time'])):'—'; ?></td>
            <td><span style="background:<?php echo $sc; ?>18;color:<?php echo $sc; ?>;border-radius:12px;padding:3px 10px;font-size:11px;font-weight:600;"><?php echo ucfirst(str_replace('_',' ',$r['status'])); ?></span></td>
            <td><?php echo floatval($r['overtime_hours']??0)>0?number_format($r['overtime_hours'],1).'h':'—'; ?></td></tr>
            <?php endforeach; ?></tbody>
        </table></div><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if($report==='ratings'): ?>
    <div class="sum-grid">
        <div class="sum-card" style="border-color:#f39c12;"><div class="sum-val" style="color:#f39c12;"><?php echo $ratSummary['total']; ?></div><div class="sum-lbl">Total Reviews</div></div>
        <div class="sum-card" style="border-color:#f39c12;"><div class="sum-val" style="color:#f39c12;"><?php echo $ratSummary['avg']; ?><?php if($ratSummary['avg']): ?><span style="font-size:16px;">★</span><?php endif; ?></div><div class="sum-lbl">Average Rating</div></div>
        <div class="sum-card" style="border-color:#27ae60;"><div class="sum-val" style="color:#27ae60;"><?php echo $ratSummary['five_star']; ?></div><div class="sum-lbl">5-Star Reviews</div></div>
        <div class="sum-card" style="border-color:#e74c3c;"><div class="sum-val" style="color:#e74c3c;"><?php echo $ratSummary['one_star']; ?></div><div class="sum-lbl">1-Star Reviews</div></div>
    </div>
    <div class="section-card" style="padding:0;overflow:hidden;">
        <?php if(empty($ratRows)): ?><div class="empty-rpt"><i class="fas fa-star"></i>No ratings found.</div>
        <?php else: ?><div class="table-responsive"><table class="rpt-table">
            <thead><tr><th>#</th><th>Order</th><th>Furniture</th><th>Customer</th><th>Employee</th><th>Rating</th><th>Review</th><th>Date</th></tr></thead>
            <tbody><?php foreach($ratRows as $i=>$r): $stars=intval($r['rating']); $sc=$stars>=4?'#27ae60':($stars>=3?'#f39c12':'#e74c3c'); ?>
            <tr><td style="color:#aaa;"><?php echo $i+1; ?></td><td><strong><?php echo htmlspecialchars($r['order_number']??'—'); ?></strong></td><td><?php echo htmlspecialchars($r['furniture_name']??'—'); ?></td><td><?php echo htmlspecialchars($r['customer_name']??'—'); ?></td><td><?php echo htmlspecialchars($r['employee_name']??'—'); ?></td>
            <td><?php for($s=1;$s<=5;$s++): ?><i class="fas fa-star" style="color:<?php echo $s<=$stars?'#f39c12':'#ddd'; ?>;font-size:12px;"></i><?php endfor; ?><strong style="color:<?php echo $sc; ?>;font-size:12px;margin-left:4px;"><?php echo $stars; ?>/5</strong></td>
            <td style="max-width:180px;color:#555;font-style:italic;"><?php echo $r['review_text']?'"'.htmlspecialchars(substr($r['review_text'],0,80)).(strlen($r['review_text'])>80?'…':'').'"':'<span style="color:#ccc;">—</span>'; ?></td>
            <td><?php echo date('M j, Y',strtotime($r['created_at'])); ?></td></tr>
            <?php endforeach; ?></tbody>
        </table></div><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if($report==='profit'): $isProfit=$profitSummary['net_profit']>=0; $pc=$isProfit?'#27ae60':'#e74c3c'; ?>
    <div class="sum-grid">
        <div class="sum-card" style="border-color:#27ae60;"><div class="sum-val" style="color:#27ae60;font-size:17px;">ETB <?php echo number_format($profitSummary['revenue'],0); ?></div><div class="sum-lbl">Total Revenue</div></div>
        <div class="sum-card" style="border-color:#e74c3c;"><div class="sum-val" style="color:#e74c3c;font-size:17px;">ETB <?php echo number_format($profitSummary['material_cost'],0); ?></div><div class="sum-lbl">Material Cost</div></div>
        <div class="sum-card" style="border-color:#3498db;"><div class="sum-val" style="color:#3498db;font-size:17px;">ETB <?php echo number_format($profitSummary['payroll_cost'],0); ?></div><div class="sum-lbl">Payroll Cost</div></div>
        <div class="sum-card" style="border-color:#f39c12;"><div class="sum-val" style="color:#f39c12;font-size:17px;">ETB <?php echo number_format($profitSummary['overhead'],0); ?></div><div class="sum-lbl">Overhead (<?php echo round($overheadRate*100,0); ?>%)</div></div>
        <?php if(($profitSummary['income_tax']??0) > 0): ?>
        <div class="sum-card" style="border-color:#8e44ad;"><div class="sum-val" style="color:#8e44ad;font-size:17px;">ETB <?php echo number_format($profitSummary['income_tax'],0); ?></div><div class="sum-lbl">Income Tax (<?php echo round($incomeTaxRate*100,0); ?>%)</div></div>
        <?php endif; ?>
        <div class="sum-card" style="border-color:<?php echo $pc; ?>;background:<?php echo $isProfit?'#f0fdf4':'#fff5f5'; ?>;"><div class="sum-val" style="color:<?php echo $pc; ?>;font-size:20px;">ETB <?php echo number_format($profitSummary['net_profit'],0); ?></div><div class="sum-lbl" style="font-weight:700;"><?php echo $isProfit?'✓ Net Profit':'✗ Net Loss'; ?></div></div>
        <div class="sum-card" style="border-color:<?php echo $pc; ?>;"><div class="sum-val" style="color:<?php echo $pc; ?>"><?php echo $profitSummary['margin']; ?>%</div><div class="sum-lbl">Profit Margin</div></div>
    </div>
    <div class="section-card" style="padding:0;overflow:hidden;">
        <?php if(empty($profitByMonth)): ?><div class="empty-rpt"><i class="fas fa-chart-line"></i>No financial data found for this period.</div>
        <?php else: ?>
        <div style="padding:14px 18px;border-bottom:1px solid #f0f0f0;font-weight:600;color:#2c3e50;font-size:14px;"><i class="fas fa-calendar-alt" style="color:#e67e22;margin-right:6px;"></i>Monthly Breakdown</div>
        <div class="table-responsive"><table class="rpt-table">
            <thead><tr><th>Month</th><th>Revenue</th><th>Material Cost</th><th>Payroll</th><th>Overhead</th><th>Net Profit</th><th>Margin</th></tr></thead>
            <tbody><?php foreach($profitByMonth as $m): $mc=$m['net']>=0?'#27ae60':'#e74c3c'; ?>
            <tr><td><strong><?php echo htmlspecialchars($m['label']); ?></strong></td><td style="color:#27ae60;">ETB <?php echo number_format($m['revenue'],0); ?></td><td style="color:#e74c3c;">ETB <?php echo number_format($m['material'],0); ?></td><td style="color:#3498db;">ETB <?php echo number_format($m['payroll'],0); ?></td><td style="color:#f39c12;">ETB <?php echo number_format($m['overhead'],0); ?></td>
            <td style="font-weight:700;color:<?php echo $mc; ?>;">ETB <?php echo number_format($m['net'],0); ?></td>
            <td><span style="background:<?php echo $mc; ?>18;color:<?php echo $mc; ?>;border-radius:12px;padding:3px 10px;font-size:12px;font-weight:600;"><?php echo $m['margin']; ?>%</span></td></tr>
            <?php endforeach; ?></tbody>
            <tfoot><tr style="background:#f8f9fa;font-weight:700;"><td style="padding:10px 12px;">Total</td><td style="padding:10px 12px;color:#27ae60;">ETB <?php echo number_format($profitSummary['revenue'],0); ?></td><td style="padding:10px 12px;color:#e74c3c;">ETB <?php echo number_format($profitSummary['material_cost'],0); ?></td><td style="padding:10px 12px;color:#3498db;">ETB <?php echo number_format($profitSummary['payroll_cost'],0); ?></td><td style="padding:10px 12px;color:#f39c12;">ETB <?php echo number_format($profitSummary['overhead'],0); ?></td><td style="padding:10px 12px;color:<?php echo $pc; ?>;">ETB <?php echo number_format($profitSummary['net_profit'],0); ?></td><td style="padding:10px 12px;color:<?php echo $pc; ?>;"><?php echo $profitSummary['margin']; ?>%</td></tr></tfoot>
        </table></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>
<script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
<script>
function switchMatTab(name) {
    document.querySelectorAll('[id^="mpanel_"]').forEach(function(p){ p.style.display='none'; });
    document.querySelectorAll('[id^="mtab_"]').forEach(function(b){ b.style.borderBottomColor='transparent'; b.style.color='#7f8c8d'; });
    document.getElementById('mpanel_'+name).style.display='block';
    var btn = document.getElementById('mtab_'+name);
    btn.style.borderBottomColor='#3498db'; btn.style.color='#3498db';
}
// Auto-activate first sub-tab if on materials report
if (document.getElementById('mpanel_mat_usage')) switchMatTab('mat_usage');
</script>
</body>
</html>
