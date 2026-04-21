<?php
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['manager', 'admin'])) {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}
require_once __DIR__ . '/../../../config/db_config.php';

$role = $_SESSION['user_role'];
$managerId = $_SESSION['user_id'];

// Fetch usage summary by material
$usageByMaterial = [];
try {
    $stmt = $pdo->query("
        SELECT m.name as material_name, m.unit,
               COUNT(mu.id) as report_count,
               SUM(mu.quantity_used) as total_used,
               SUM(mu.waste_amount) as total_waste,
               SUM(mu.quantity_used + mu.waste_amount) as total_consumed,
               SUM((mu.quantity_used + mu.waste_amount) * m.cost_per_unit) as total_cost
        FROM furn_material_usage mu
        JOIN furn_materials m ON mu.material_id = m.id
        GROUP BY mu.material_id, m.name, m.unit
        ORDER BY total_consumed DESC
    ");
    $usageByMaterial = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("Usage by material: " . $e->getMessage()); }

// Fetch usage summary by employee
$usageByEmployee = [];
try {
    $stmt = $pdo->query("
        SELECT CONCAT(u.first_name,' ',u.last_name) as employee_name,
               COUNT(mu.id) as report_count,
               SUM(mu.quantity_used) as total_used,
               SUM(mu.waste_amount) as total_waste
        FROM furn_material_usage mu
        JOIN furn_users u ON mu.employee_id = u.id
        GROUP BY mu.employee_id, u.first_name, u.last_name
        ORDER BY total_used DESC
    ");
    $usageByEmployee = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("Usage by employee: " . $e->getMessage()); }

// Fetch monthly usage trend (last 6 months)
$monthlyTrend = [];
try {
    $stmt = $pdo->query("
        SELECT DATE_FORMAT(mu.created_at,'%Y-%m') as month,
               SUM(mu.quantity_used) as total_used,
               SUM(mu.waste_amount) as total_waste,
               COUNT(mu.id) as report_count
        FROM furn_material_usage mu
        WHERE mu.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(mu.created_at,'%Y-%m')
        ORDER BY month ASC
    ");
    $monthlyTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("Monthly trend: " . $e->getMessage()); }

// Fetch all usage detail records
$usageDetail = [];
try {
    $stmt = $pdo->query("
        SELECT mu.*, m.name as material_name, m.unit, m.cost_per_unit,
               CONCAT(u.first_name,' ',u.last_name) as employee_name,
               o.order_number
        FROM furn_material_usage mu
        JOIN furn_materials m ON mu.material_id = m.id
        JOIN furn_users u ON mu.employee_id = u.id
        LEFT JOIN furn_production_tasks t ON mu.task_id = t.id
        LEFT JOIN furn_orders o ON t.order_id = o.id
        ORDER BY mu.created_at DESC
        LIMIT 200
    ");
    $usageDetail = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("Usage detail: " . $e->getMessage()); }

// Purchase history
$purchaseHistory = [];
$totalPurchaseCost = 0;
try {
    $stmt = $pdo->query("
        SELECT fp.*, m.name as material_name, m.unit,
               CONCAT(u.first_name,' ',u.last_name) as recorded_by
        FROM furn_material_purchases fp
        LEFT JOIN furn_materials m ON fp.material_id = m.id
        LEFT JOIN furn_users u ON fp.manager_id = u.id
        ORDER BY fp.purchase_date DESC LIMIT 100
    ");
    $purchaseHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($purchaseHistory as $p) $totalPurchaseCost += floatval($p['total_cost']);
} catch (PDOException $e) { error_log("Purchase history: " . $e->getMessage()); }

// Summary stats
$totalUsed = array_sum(array_column($usageByMaterial, 'total_used'));
$totalWaste = array_sum(array_column($usageByMaterial, 'total_waste'));
$totalCost = array_sum(array_column($usageByMaterial, 'total_cost'));

$pageTitle = 'Material Report';
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
</head>
<body>
    <button class="mobile-menu-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>
    <div class="sidebar-overlay"></div>
    <?php
    if ($role === 'admin') {
        include_once __DIR__ . '/../../includes/admin_sidebar.php';
    } else {
        include_once __DIR__ . '/../../includes/manager_sidebar.php';
    }
    $pageTitle = 'Material Report';
    if ($role === 'admin') {
        include_once __DIR__ . '/../../includes/admin_header.php';
    } else {
        include_once __DIR__ . '/../../includes/manager_header.php';
    }
    ?>

    <div class="main-content">
        <h2 style="margin-bottom:24px;color:#2c3e50;"><i class="fas fa-chart-bar" style="color:#3498DB;margin-right:10px;"></i>Material Usage Report</h2>

        <!-- Summary Stats -->
        <div class="stats-grid" style="margin-bottom:24px;">
            <div class="stat-card" style="border-left:4px solid #3498DB;">
                <div style="display:flex;justify-content:space-between;align-items:start;">
                    <div><div class="stat-value"><?php echo number_format($totalUsed,2); ?></div><div class="stat-label">Total Units Used</div></div>
                    <div style="font-size:28px;color:#3498DB;opacity:.25;"><i class="fas fa-boxes"></i></div>
                </div>
            </div>
            <div class="stat-card" style="border-left:4px solid #E74C3C;">
                <div style="display:flex;justify-content:space-between;align-items:start;">
                    <div><div class="stat-value"><?php echo number_format($totalWaste,2); ?></div><div class="stat-label">Total Waste</div></div>
                    <div style="font-size:28px;color:#E74C3C;opacity:.25;"><i class="fas fa-trash"></i></div>
                </div>
            </div>
            <div class="stat-card" style="border-left:4px solid #27AE60;">
                <div style="display:flex;justify-content:space-between;align-items:start;">
                    <div><div class="stat-value">ETB <?php echo number_format($totalCost,0); ?></div><div class="stat-label">Total Usage Cost</div></div>
                    <div style="font-size:28px;color:#27AE60;opacity:.25;"><i class="fas fa-money-bill"></i></div>
                </div>
            </div>
            <div class="stat-card" style="border-left:4px solid #9B59B6;">
                <div style="display:flex;justify-content:space-between;align-items:start;">
                    <div><div class="stat-value">ETB <?php echo number_format($totalPurchaseCost,0); ?></div><div class="stat-label">Total Purchased</div></div>
                    <div style="font-size:28px;color:#9B59B6;opacity:.25;"><i class="fas fa-shopping-cart"></i></div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div style="display:flex;gap:4px;margin-bottom:20px;border-bottom:2px solid #e0e0e0;">
            <?php foreach ([['usage','Usage by Material','fa-boxes'],['employee','Usage by Employee','fa-users'],['trend','Monthly Trend','fa-chart-line'],['detail','Usage Detail','fa-list'],['purchase','Purchase History','fa-receipt']] as [$tab,$label,$icon]): ?>
            <button onclick="switchTab('<?php echo $tab; ?>')" id="tab_<?php echo $tab; ?>"
                style="padding:10px 18px;border:none;background:none;font-size:13px;font-weight:600;cursor:pointer;border-bottom:3px solid transparent;color:#7f8c8d;font-family:inherit;transition:all .2s;"
                class="mat-tab">
                <i class="fas <?php echo $icon; ?>" style="margin-right:6px;"></i><?php echo $label; ?>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- Usage by Material -->
        <div id="panel_usage" class="mat-panel">
            <div class="section-card">
                <div class="section-header"><h2 class="section-title"><i class="fas fa-boxes"></i> Usage by Material</h2></div>
                <?php if (empty($usageByMaterial)): ?>
                    <p style="text-align:center;padding:40px;color:#aaa;">No usage data yet.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>Material</th><th>Reports</th><th>Total Used</th><th>Total Waste</th><th>Total Consumed</th><th>Cost</th></tr></thead>
                        <tbody>
                            <?php foreach ($usageByMaterial as $row): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['material_name']); ?></strong> <small style="color:#aaa;">(<?php echo $row['unit']; ?>)</small></td>
                                <td><?php echo $row['report_count']; ?></td>
                                <td><?php echo number_format($row['total_used'],2); ?></td>
                                <td style="color:<?php echo floatval($row['total_waste'])>0?'#E74C3C':'#27AE60'; ?>"><?php echo number_format($row['total_waste'],2); ?></td>
                                <td><strong><?php echo number_format($row['total_consumed'],2); ?></strong></td>
                                <td>ETB <?php echo number_format($row['total_cost'],2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Usage by Employee -->
        <div id="panel_employee" class="mat-panel" style="display:none;">
            <div class="section-card">
                <div class="section-header"><h2 class="section-title"><i class="fas fa-users"></i> Usage by Employee</h2></div>
                <?php if (empty($usageByEmployee)): ?>
                    <p style="text-align:center;padding:40px;color:#aaa;">No usage data yet.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>Employee</th><th>Reports Submitted</th><th>Total Used</th><th>Total Waste</th><th>Waste %</th></tr></thead>
                        <tbody>
                            <?php foreach ($usageByEmployee as $row):
                                $wastePercent = floatval($row['total_used']) > 0 ? (floatval($row['total_waste']) / floatval($row['total_used'])) * 100 : 0;
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['employee_name']); ?></strong></td>
                                <td><?php echo $row['report_count']; ?></td>
                                <td><?php echo number_format($row['total_used'],2); ?></td>
                                <td style="color:<?php echo floatval($row['total_waste'])>0?'#E74C3C':'#27AE60'; ?>"><?php echo number_format($row['total_waste'],2); ?></td>
                                <td>
                                    <span style="color:<?php echo $wastePercent>10?'#E74C3C':($wastePercent>5?'#F39C12':'#27AE60'); ?>;font-weight:600;">
                                        <?php echo number_format($wastePercent,1); ?>%
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Monthly Trend -->
        <div id="panel_trend" class="mat-panel" style="display:none;">
            <div class="section-card">
                <div class="section-header"><h2 class="section-title"><i class="fas fa-chart-line"></i> Monthly Usage Trend (Last 6 Months)</h2></div>
                <?php if (empty($monthlyTrend)): ?>
                    <p style="text-align:center;padding:40px;color:#aaa;">No trend data yet.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>Month</th><th>Total Used</th><th>Total Waste</th><th>Reports</th></tr></thead>
                        <tbody>
                            <?php foreach ($monthlyTrend as $row): ?>
                            <tr>
                                <td><strong><?php echo date('F Y', strtotime($row['month'].'-01')); ?></strong></td>
                                <td><?php echo number_format($row['total_used'],2); ?></td>
                                <td style="color:<?php echo floatval($row['total_waste'])>0?'#E74C3C':'#27AE60'; ?>"><?php echo number_format($row['total_waste'],2); ?></td>
                                <td><?php echo $row['report_count']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Usage Detail -->
        <div id="panel_detail" class="mat-panel" style="display:none;">
            <div class="section-card">
                <div class="section-header"><h2 class="section-title"><i class="fas fa-list"></i> Usage Detail (Latest 200)</h2></div>
                <?php if (empty($usageDetail)): ?>
                    <p style="text-align:center;padding:40px;color:#aaa;">No usage records yet.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>Date</th><th>Employee</th><th>Material</th><th>Used</th><th>Waste</th><th>Order</th><th>Notes</th></tr></thead>
                        <tbody>
                            <?php foreach ($usageDetail as $u): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($u['employee_name']); ?></td>
                                <td><?php echo htmlspecialchars($u['material_name']); ?> <small style="color:#aaa;">(<?php echo $u['unit']; ?>)</small></td>
                                <td><?php echo number_format($u['quantity_used'],2); ?></td>
                                <td style="color:<?php echo floatval($u['waste_amount'])>0?'#E74C3C':'#27AE60'; ?>"><?php echo number_format($u['waste_amount'],2); ?></td>
                                <td><?php echo !empty($u['order_number']) ? htmlspecialchars($u['order_number']) : '<span style="color:#aaa;">N/A</span>'; ?></td>
                                <td style="font-size:12px;color:#666;"><?php echo htmlspecialchars($u['notes'] ?? ''); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Purchase History -->
        <div id="panel_purchase" class="mat-panel" style="display:none;">
            <div class="section-card">
                <div class="section-header"><h2 class="section-title"><i class="fas fa-receipt"></i> Purchase History</h2></div>
                <?php if (empty($purchaseHistory)): ?>
                    <p style="text-align:center;padding:40px;color:#aaa;">No purchase records yet.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>Date</th><th>Material</th><th>Qty</th><th>Unit Price</th><th>Total</th><th>Invoice</th><th>Supplier</th><th>By</th></tr></thead>
                        <tbody>
                            <?php foreach ($purchaseHistory as $p): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($p['purchase_date'])); ?></td>
                                <td><strong><?php echo htmlspecialchars($p['material_name']); ?></strong> <small style="color:#aaa;">(<?php echo $p['unit']; ?>)</small></td>
                                <td><?php echo number_format($p['quantity'],2); ?></td>
                                <td>ETB <?php echo number_format($p['unit_price'],2); ?></td>
                                <td><strong>ETB <?php echo number_format($p['total_cost'],2); ?></strong></td>
                                <td><?php echo $p['invoice_number'] ? htmlspecialchars($p['invoice_number']) : '<span style="color:#aaa;">—</span>'; ?></td>
                                <td><?php echo $p['supplier'] ? htmlspecialchars($p['supplier']) : '<span style="color:#aaa;">—</span>'; ?></td>
                                <td><?php echo htmlspecialchars($p['recorded_by'] ?? 'N/A'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr style="background:#f8f9fa;font-weight:700;">
                                <td colspan="4" style="text-align:right;">Total:</td>
                                <td>ETB <?php echo number_format($totalPurchaseCost,2); ?></td>
                                <td colspan="3"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <script>
    function switchTab(name) {
        document.querySelectorAll('.mat-panel').forEach(p => p.style.display = 'none');
        document.querySelectorAll('.mat-tab').forEach(b => {
            b.style.borderBottomColor = 'transparent';
            b.style.color = '#7f8c8d';
        });
        document.getElementById('panel_' + name).style.display = 'block';
        const btn = document.getElementById('tab_' + name);
        btn.style.borderBottomColor = '#3498DB';
        btn.style.color = '#3498DB';
    }
    switchTab('usage');
    </script>
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
