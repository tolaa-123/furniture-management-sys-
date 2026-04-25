<?php
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['manager', 'admin'])) {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}
$role = $_SESSION['user_role'];
$pageTitle = 'Analytics Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - FurnitureCraft</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/css/admin-responsive.css">
    <style>
        .tab-nav{display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;}
        .tab-btn{padding:10px 20px;border:2px solid #e0e0e0;background:#fff;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;color:#666;transition:all .2s;font-family:inherit;}
        .tab-btn:hover{border-color:#3498DB;color:#3498DB;}
        .tab-btn.active{background:#3498DB;border-color:#3498DB;color:#fff;}
        .tab-pane{display:none;}
        .tab-pane.active{display:block;}
        .ch-wrap{position:relative;height:300px;width:100%;margin-bottom:20px;}
        .ch-wrap-sm{position:relative;height:260px;width:100%;margin-bottom:20px;}
        .g2{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
        @media(max-width:768px){.g2{grid-template-columns:1fr;}}
    </style>
</head>
<body>
<button class="mobile-menu-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>
<div class="sidebar-overlay"></div>
<?php
if ($role === 'admin') {
    include_once __DIR__ . '/../../includes/admin_sidebar.php';
    $pageTitle = 'Analytics';
    include_once __DIR__ . '/../../includes/admin_header.php';
} else {
    include_once __DIR__ . '/../../includes/manager_sidebar.php';
    $pageTitle = 'Analytics';
    include_once __DIR__ . '/../../includes/manager_header.php';
}
?>
<div class="main-content">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:22px;flex-wrap:wrap;gap:10px;">
        <h2 style="margin:0;color:#2c3e50;"><i class="fas fa-chart-line" style="color:#3498DB;margin-right:8px;"></i>Analytics Dashboard</h2>
        <?php if ($role === 'admin'): ?>
        <button onclick="doRefreshCache()" style="padding:8px 16px;background:#e67e22;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;"><i class="fas fa-sync-alt"></i> Refresh Cache</button>
        <?php endif; ?>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid" style="margin-bottom:22px;">
        <div class="stat-card" style="border-left:4px solid #3498DB;"><div style="display:flex;justify-content:space-between;"><div><div class="stat-value" id="s_total_orders"><?php echo number_format($stats['total_orders']??0); ?></div><div class="stat-label">Total Orders</div></div><i class="fas fa-box" style="font-size:28px;color:#3498DB;opacity:.2;align-self:center;"></i></div></div>
        <div class="stat-card" style="border-left:4px solid #F39C12;"><div style="display:flex;justify-content:space-between;"><div><div class="stat-value" id="s_pending"><?php echo number_format($stats['pending_orders']??0); ?></div><div class="stat-label">Pending Orders</div></div><i class="fas fa-hourglass-half" style="font-size:28px;color:#F39C12;opacity:.2;align-self:center;"></i></div></div>
        <div class="stat-card" style="border-left:4px solid #27AE60;"><div style="display:flex;justify-content:space-between;"><div><div class="stat-value" id="s_revenue" style="font-size:16px;">ETB <?php echo number_format($stats['this_month_revenue']??0,0); ?></div><div class="stat-label">This Month Revenue</div></div><i class="fas fa-money-bill" style="font-size:28px;color:#27AE60;opacity:.2;align-self:center;"></i></div></div>
        <div class="stat-card" style="border-left:4px solid #9B59B6;"><div style="display:flex;justify-content:space-between;"><div><div class="stat-value" id="s_profit" style="font-size:16px;">ETB <?php echo number_format($stats['this_month_profit']??0,0); ?></div><div class="stat-label">This Month Profit</div></div><i class="fas fa-chart-line" style="font-size:28px;color:#9B59B6;opacity:.2;align-self:center;"></i></div></div>
        <div class="stat-card" style="border-left:4px solid #1ABC9C;"><div style="display:flex;justify-content:space-between;"><div><div class="stat-value" id="s_employees"><?php echo number_format($stats['active_employees']??0); ?></div><div class="stat-label">Active Employees</div></div><i class="fas fa-users" style="font-size:28px;color:#1ABC9C;opacity:.2;align-self:center;"></i></div></div>
        <div class="stat-card" style="border-left:4px solid #E74C3C;"><div style="display:flex;justify-content:space-between;"><div><div class="stat-value" id="s_lowstock" style="color:#E74C3C;"><?php echo number_format($stats['low_stock_items']??0); ?></div><div class="stat-label">Low Stock Items</div></div><i class="fas fa-exclamation-triangle" style="font-size:28px;color:#E74C3C;opacity:.2;align-self:center;"></i></div></div>
    </div>

    <!-- Tab Navigation -->
    <div class="tab-nav">
        <button class="tab-btn active" onclick="switchTab('overview',this)"><i class="fas fa-chart-line"></i> Overview</button>
        <button class="tab-btn" onclick="switchTab('sales',this)"><i class="fas fa-shopping-cart"></i> Sales &amp; Revenue</button>
        <button class="tab-btn" onclick="switchTab('employees',this)"><i class="fas fa-users"></i> Employees</button>
        <button class="tab-btn" onclick="switchTab('materials',this)"><i class="fas fa-boxes"></i> Materials</button>
    </div>

    <!-- TAB: Overview -->
    <div id="tab-overview" class="tab-pane active">

        <!-- Weekly Orders & Revenue -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-calendar-week"></i> Weekly Orders &amp; Revenue</h2>
            </div>
            <div class="ch-wrap"><canvas id="weeklyChart"></canvas></div>
        </div>

        <!-- Orders by Status + Low Stock side by side -->
        <div class="g2">
            <div class="section-card">
                <div class="section-header"><h2 class="section-title"><i class="fas fa-chart-pie"></i> Orders by Status</h2></div>
                <div class="ch-wrap-sm"><canvas id="statusChart"></canvas></div>
            </div>
            <div class="section-card">
                <div class="section-header"><h2 class="section-title"><i class="fas fa-exclamation-triangle"></i> Low Stock Alerts</h2></div>
                <div class="ch-wrap-sm"><canvas id="stockChart"></canvas></div>
            </div>
        </div>

    </div><!-- end tab-overview -->

    <!-- TAB: Sales & Revenue -->
    <div id="tab-sales" class="tab-pane">

        <!-- Monthly Revenue -->
        <div class="section-card">
            <div class="section-header"><h2 class="section-title"><i class="fas fa-chart-area"></i> Monthly Revenue</h2></div>
            <div class="ch-wrap"><canvas id="revChart"></canvas></div>
        </div>

        <!-- Monthly Profit -->
        <div class="section-card">
            <div class="section-header"><h2 class="section-title"><i class="fas fa-chart-line"></i> Monthly Profit Trend</h2></div>
            <div class="ch-wrap"><canvas id="profitChart"></canvas></div>
        </div>

        <!-- Top Products + Top Customers side by side -->
        <div class="g2">
            <div class="section-card">
                <div class="section-header"><h2 class="section-title"><i class="fas fa-couch"></i> Top Selling Products</h2></div>
                <div class="ch-wrap-sm"><canvas id="prodChart"></canvas></div>
            </div>
            <div class="section-card">
                <div class="section-header"><h2 class="section-title"><i class="fas fa-users"></i> Top Customers</h2></div>
                <div class="ch-wrap-sm"><canvas id="custChart"></canvas></div>
            </div>
        </div>

    </div><!-- end tab-sales -->

    <!-- TAB: Employees -->
    <div id="tab-employees" class="tab-pane">
        <div class="g2">
            <div class="section-card">
                <div class="section-header"><h2 class="section-title"><i class="fas fa-clock"></i> Employee Hours (30 Days)</h2></div>
                <div class="ch-wrap-sm"><canvas id="empChart"></canvas></div>
            </div>
            <div class="section-card">
                <div class="section-header"><h2 class="section-title"><i class="fas fa-user-check"></i> Employee Productivity</h2></div>
                <div class="ch-wrap-sm"><canvas id="epChart"></canvas></div>
            </div>
        </div>
    </div><!-- end tab-employees -->

    <!-- TAB: Materials -->
    <div id="tab-materials" class="tab-pane">
        <div class="section-card">
            <div class="section-header"><h2 class="section-title"><i class="fas fa-boxes"></i> Material Usage Trends</h2></div>
            <div class="ch-wrap"><canvas id="matChart"></canvas></div>
        </div>
    </div><!-- end tab-materials -->

    <!-- Export -->
    <div class="section-card">
        <div class="section-header"><h2 class="section-title"><i class="fas fa-download"></i> Export Data</h2></div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="<?php echo BASE_URL; ?>/public/analytics/export?type=summary" class="btn-action btn-primary-custom"><i class="fas fa-file-csv"></i> Summary CSV</a>
            <a href="<?php echo BASE_URL; ?>/public/analytics/export?type=detailed&data=revenue" class="btn-action btn-success-custom"><i class="fas fa-chart-bar"></i> Revenue CSV</a>
            <a href="<?php echo BASE_URL; ?>/public/analytics/export?type=detailed&data=products" class="btn-action btn-warning-custom"><i class="fas fa-couch"></i> Products CSV</a>
        </div>
    </div>

</div><!-- end main-content -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const BASE = '<?php echo BASE_URL; ?>';
const D = <?php echo json_encode($chartData); ?>;

// Shared consistent color palette
const PAL = {
    blue:   { bg:'rgba(52,152,219,0.7)',  bd:'rgba(52,152,219,1)'  },
    green:  { bg:'rgba(39,174,96,0.7)',   bd:'rgba(39,174,96,1)'   },
    orange: { bg:'rgba(230,126,34,0.7)',  bd:'rgba(230,126,34,1)'  },
    purple: { bg:'rgba(155,89,182,0.7)',  bd:'rgba(155,89,182,1)'  },
    teal:   { bg:'rgba(26,188,156,0.7)',  bd:'rgba(26,188,156,1)'  },
    red:    { bg:'rgba(231,76,60,0.7)',   bd:'rgba(231,76,60,1)'   },
    yellow: { bg:'rgba(243,156,18,0.7)',  bd:'rgba(243,156,18,1)'  },
    navy:   { bg:'rgba(44,62,80,0.7)',    bd:'rgba(44,62,80,1)'    }
};
const PLIST = Object.values(PAL);

// Track which charts are initialized
const inited = {};

// Chart instances
let weeklyChart, statusChart, stockChart, revChart, profitChart, prodChart, custChart, empChart, epChart, matChart;

function mkBar(id, labels, datasets) {
    const ctx = document.getElementById(id);
    if (!ctx) return null;
    return new Chart(ctx, {
        type: 'bar',
        data: { labels, datasets },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true } } }
    });
}

function mkLine(id, labels, datasets) {
    const ctx = document.getElementById(id);
    if (!ctx) return null;
    return new Chart(ctx, {
        type: 'line',
        data: { labels, datasets },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true } } }
    });
}

function mkPie(id, labels, data, type) {
    const ctx = document.getElementById(id);
    if (!ctx) return null;
    return new Chart(ctx, {
        type: type || 'pie',
        data: {
            labels,
            data: data,
            datasets: [{ data, backgroundColor: PLIST.map(p => p.bg), borderColor: '#fff', borderWidth: 2 }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });
}

function initOverview() {
    if (inited.overview) { weeklyChart && weeklyChart.resize(); statusChart && statusChart.resize(); stockChart && stockChart.resize(); return; }
    inited.overview = true;

    // Weekly Orders & Revenue
    const wL = D.weeklyOrders.labels || [];
    const wOrders = (D.weeklyOrders.datasets[0] || {}).data || [];
    const wRev    = (D.weeklyOrders.datasets[1] || {}).data || [];
    weeklyChart = new Chart(document.getElementById('weeklyChart'), {
        type: 'bar',
        data: {
            labels: wL,
            datasets: [
                { label: 'Orders', data: wOrders, backgroundColor: PAL.blue.bg, borderColor: PAL.blue.bd, borderWidth: 2, yAxisID: 'y' },
                { label: 'Revenue (ETB)', data: wRev, type: wL.length > 1 ? 'line' : 'bar', backgroundColor: PAL.green.bg, borderColor: PAL.green.bd, borderWidth: 2, tension: 0.3, fill: false, yAxisID: 'y1', pointRadius: 5 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'top' } },
            scales: {
                y:  { beginAtZero: true, position: 'left',  title: { display: true, text: 'Orders' } },
                y1: { beginAtZero: true, position: 'right', title: { display: true, text: 'Revenue (ETB)' }, grid: { drawOnChartArea: false } }
            }
        }
    });

    // Orders by Status — pie
    const sL = D.ordersByStatus.labels || [];
    const sD = (D.ordersByStatus.datasets[0] || {}).data || [];
    statusChart = new Chart(document.getElementById('statusChart'), {
        type: 'pie',
        data: { labels: sL, datasets: [{ data: sD, backgroundColor: PLIST.map(p => p.bg), borderColor: '#fff', borderWidth: 2 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });

    // Low Stock — doughnut
    const stL = D.lowStockAlerts.labels || [];
    const stD = (D.lowStockAlerts.datasets[0] || {}).data || [];
    stockChart = new Chart(document.getElementById('stockChart'), {
        type: 'doughnut',
        data: { labels: stL, datasets: [{ data: stD, backgroundColor: [PAL.red.bg, PAL.yellow.bg, PAL.teal.bg], borderColor: '#fff', borderWidth: 2 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });
}

function initSales() {
    if (inited.sales) { revChart && revChart.resize(); profitChart && profitChart.resize(); prodChart && prodChart.resize(); custChart && custChart.resize(); return; }
    inited.sales = true;

    // Monthly Revenue
    const rL = D.monthlyRevenue.labels || [];
    const rRev = (D.monthlyRevenue.datasets[0] || {}).data || [];
    const rOrd = (D.monthlyRevenue.datasets[1] || {}).data || [];
    const revType = rL.length > 1 ? 'line' : 'bar';
    revChart = new Chart(document.getElementById('revChart'), {
        type: revType,
        data: {
            labels: rL,
            datasets: [
                { label: 'Revenue (ETB)', data: rRev, borderColor: PAL.green.bd, backgroundColor: PAL.green.bg, tension: 0.3, fill: revType==='line', yAxisID: 'y', pointRadius: 5 },
                { label: 'Orders', data: rOrd, borderColor: PAL.blue.bd, backgroundColor: PAL.blue.bg, tension: 0.3, fill: false, yAxisID: 'y1', pointRadius: 5 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'top' } },
            scales: {
                y:  { beginAtZero: true, position: 'left',  title: { display: true, text: 'Revenue (ETB)' } },
                y1: { beginAtZero: true, position: 'right', title: { display: true, text: 'Orders' }, grid: { drawOnChartArea: false } }
            }
        }
    });

    // Monthly Profit
    const pL = D.monthlyProfit.labels || [];
    const pRev = (D.monthlyProfit.datasets[0] || {}).data || [];
    const pPro = (D.monthlyProfit.datasets[1] || {}).data || [];
    const pMar = (D.monthlyProfit.datasets[2] || {}).data || [];
    const profType = pL.length > 1 ? 'line' : 'bar';
    profitChart = new Chart(document.getElementById('profitChart'), {
        type: profType,
        data: {
            labels: pL,
            datasets: [
                { label: 'Revenue (ETB)', data: pRev, borderColor: PAL.blue.bd,   backgroundColor: PAL.blue.bg,   tension: 0.3, fill: false, yAxisID: 'y',  pointRadius: 5 },
                { label: 'Profit (ETB)',  data: pPro, borderColor: PAL.green.bd,  backgroundColor: PAL.green.bg,  tension: 0.3, fill: false, yAxisID: 'y',  pointRadius: 5 },
                { label: 'Margin %',      data: pMar, borderColor: PAL.orange.bd, backgroundColor: PAL.orange.bg, tension: 0.3, fill: false, yAxisID: 'y1', pointRadius: 5, borderDash: profType==='line'?[5,5]:[] }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'top' } },
            scales: {
                y:  { beginAtZero: true, position: 'left',  title: { display: true, text: 'ETB' } },
                y1: { beginAtZero: true, position: 'right', title: { display: true, text: 'Margin %' }, grid: { drawOnChartArea: false } }
            }
        }
    });

    // Top Products — horizontal bar
    const prL = D.topProducts.labels || [];
    const prRev = (D.topProducts.datasets[0] || {}).data || [];
    prodChart = new Chart(document.getElementById('prodChart'), {
        type: 'bar',
        data: { labels: prL, datasets: [{ label: 'Revenue (ETB)', data: prRev, backgroundColor: PAL.purple.bg, borderColor: PAL.purple.bd, borderWidth: 2 }] },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: true, position: 'top' } },
            scales: {
                x: { title: { display: true, text: 'Product' } },
                y: { beginAtZero: true, title: { display: true, text: 'Revenue (ETB)' } }
            }
        }
    });

    // Top Customers — bar
    const cL = D.topCustomers.labels || [];
    const cRev = (D.topCustomers.datasets[0] || {}).data || [];
    custChart = new Chart(document.getElementById('custChart'), {
        type: 'bar',
        data: { labels: cL, datasets: [{ label: 'Revenue (ETB)', data: cRev, backgroundColor: PAL.teal.bg, borderColor: PAL.teal.bd, borderWidth: 2 }] },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: true, position: 'top' } },
            scales: {
                x: { title: { display: true, text: 'Customer' } },
                y: { beginAtZero: true, title: { display: true, text: 'Revenue (ETB)' } }
            }
        }
    });
}

function initEmployees() {
    if (inited.employees) { empChart && empChart.resize(); epChart && epChart.resize(); return; }
    inited.employees = true;

    // Employee Hours — bar
    const eL = D.employeeHours.labels || [];
    const eDays = (D.employeeHours.datasets[0] || {}).data || [];
    const eAvg  = (D.employeeHours.datasets[1] || {}).data || [];
    empChart = new Chart(document.getElementById('empChart'), {
        type: 'bar',
        data: {
            labels: eL,
            datasets: [
                { label: 'Days Present',    data: eDays, backgroundColor: PAL.blue.bg,  borderColor: PAL.blue.bd,  borderWidth: 2 },
                { label: 'Avg Daily Hours', data: eAvg,  backgroundColor: PAL.orange.bg, borderColor: PAL.orange.bd, borderWidth: 2 }
            ]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true } } }
    });

    // Employee Productivity — bar
    const epL = D.employeeProductivity.labels || [];
    const epC = (D.employeeProductivity.datasets[0] || {}).data || [];
    const epA = (D.employeeProductivity.datasets[1] || {}).data || [];
    epChart = new Chart(document.getElementById('epChart'), {
        type: 'bar',
        data: {
            labels: epL,
            datasets: [
                { label: 'Tasks Completed', data: epC, backgroundColor: PAL.green.bg,  borderColor: PAL.green.bd,  borderWidth: 2, yAxisID: 'y' },
                { label: 'Avg Days',        data: epA, backgroundColor: PAL.yellow.bg, borderColor: PAL.yellow.bd, borderWidth: 2, yAxisID: 'y1' }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'top' } },
            scales: {
                y:  { beginAtZero: true, position: 'left',  title: { display: true, text: 'Tasks' } },
                y1: { beginAtZero: true, position: 'right', title: { display: true, text: 'Avg Days' }, grid: { drawOnChartArea: false } }
            }
        }
    });
}

function initMaterials() {
    if (inited.materials) { matChart && matChart.resize(); return; }
    inited.materials = true;

    const mL = D.materialUsage.labels || [];
    const mDS = D.materialUsage.datasets || [];

    // If only 1 month of data, use bar chart — line needs 2+ points to draw lines
    const chartType = mL.length > 1 ? 'line' : 'bar';

    if (chartType === 'bar') {
        // Flatten: one bar per material showing total usage
        const matNames = mDS.map(ds => ds.label);
        const matTotals = mDS.map(ds => ds.data.reduce((a, b) => a + b, 0));
        matChart = new Chart(document.getElementById('matChart'), {
            type: 'bar',
            data: {
                labels: matNames,
                datasets: [{
                    label: 'Qty Used',
                    data: matTotals,
                    backgroundColor: PLIST.map(p => p.bg),
                    borderColor: PLIST.map(p => p.bd),
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, title: { display: true, text: 'Qty Used' } } }
            }
        });
    } else {
        const mappedDS = mDS.map((ds, i) => ({
            label: ds.label,
            data: ds.data,
            borderColor: PLIST[i % PLIST.length].bd,
            backgroundColor: 'transparent',
            tension: 0.3,
            borderWidth: 2,
            fill: false,
            pointRadius: 5
        }));
        matChart = new Chart(document.getElementById('matChart'), {
            type: 'line',
            data: { labels: mL, datasets: mappedDS },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'top' } },
                scales: { y: { beginAtZero: true, title: { display: true, text: 'Qty Used' } } }
            }
        });
    }
}

function switchTab(name, btn) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
    // Init charts for the tab that just became visible
    if (name === 'overview')   initOverview();
    if (name === 'sales')      initSales();
    if (name === 'employees')  initEmployees();
    if (name === 'materials')  initMaterials();
}

// Init the default visible tab on load
window.addEventListener('load', function() {
    initOverview();
    setInterval(liveStats, 30000);
});

function liveStats() {
    fetch(BASE + '/public/analytics/get-updates')
        .then(r => r.json())
        .then(d => {
            if (!d.success) return;
            const s = d.stats;
            document.getElementById('s_total_orders').textContent = Number(s.total_orders ?? 0).toLocaleString();
            document.getElementById('s_pending').textContent      = Number(s.pending_orders ?? 0).toLocaleString();
            document.getElementById('s_revenue').textContent      = 'ETB ' + Number(s.this_month_revenue ?? 0).toLocaleString();
            document.getElementById('s_profit').textContent       = 'ETB ' + Number(s.this_month_profit ?? 0).toLocaleString();
            document.getElementById('s_employees').textContent    = Number(s.active_employees ?? 0).toLocaleString();
            document.getElementById('s_lowstock').textContent     = Number(s.low_stock_items ?? 0).toLocaleString();
        }).catch(() => {});
}

function doRefreshCache() {
    if (!confirm('Refresh analytics cache?')) return;
    fetch(BASE + '/public/analytics/refresh-cache', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'csrf_token=<?php echo htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? $_SESSION["csrf_token"] ?? ""); ?>'
    }).then(r => r.json()).then(d => alert(d.message || d.error || 'Done')).catch(() => alert('Error'));
}
</script>
<script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
