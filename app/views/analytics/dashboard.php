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
        .ch-card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 8px rgba(0,0,0,.07);margin-bottom:20px;}
        .ch-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;}
        .ch-title{font-size:14px;font-weight:600;color:#2c3e50;margin:0;}
        .ch-head select{padding:5px 10px;border:1px solid #ddd;border-radius:6px;font-size:12px;font-family:inherit;outline:none;}
        .g2{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
        @media(max-width:768px){.g2{grid-template-columns:1fr;}}
        .dt{width:100%;border-collapse:collapse;font-size:12px;margin-top:10px;}
        .dt th{background:#f8f9fa;padding:6px 10px;text-align:left;font-weight:600;color:#555;border-bottom:2px solid #e0e0e0;}
        .dt td{padding:6px 10px;border-bottom:1px solid #f0f0f0;color:#444;}
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
        <h1 style="margin:0;font-size:22px;color:#2c3e50;"><i class="fas fa-chart-line" style="color:#3498DB;margin-right:8px;"></i>Analytics Dashboard</h1>
        <?php if ($role === 'admin'): ?>
        <button onclick="doRefreshCache()" style="padding:8px 16px;background:#e67e22;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;"><i class="fas fa-sync-alt"></i> Refresh Cache</button>
        <?php endif; ?>
    </div>
    <div class="stats-grid" style="margin-bottom:22px;">
        <div class="stat-card" style="border-left:4px solid #3498DB;"><div style="display:flex;justify-content:space-between;"><div><div class="stat-value" id="s_total_orders"><?php echo number_format($stats['total_orders']??0); ?></div><div class="stat-label">Total Orders</div></div><i class="fas fa-box" style="font-size:28px;color:#3498DB;opacity:.2;align-self:center;"></i></div></div>
        <div class="stat-card" style="border-left:4px solid #F39C12;"><div style="display:flex;justify-content:space-between;"><div><div class="stat-value" id="s_pending"><?php echo number_format($stats['pending_orders']??0); ?></div><div class="stat-label">Pending Orders</div></div><i class="fas fa-hourglass-half" style="font-size:28px;color:#F39C12;opacity:.2;align-self:center;"></i></div></div>
        <div class="stat-card" style="border-left:4px solid #27AE60;"><div style="display:flex;justify-content:space-between;"><div><div class="stat-value" id="s_revenue" style="font-size:16px;">ETB <?php echo number_format($stats['this_month_revenue']??0,0); ?></div><div class="stat-label">This Month Revenue</div></div><i class="fas fa-money-bill" style="font-size:28px;color:#27AE60;opacity:.2;align-self:center;"></i></div></div>
        <div class="stat-card" style="border-left:4px solid #9B59B6;"><div style="display:flex;justify-content:space-between;"><div><div class="stat-value" id="s_profit" style="font-size:16px;">ETB <?php echo number_format($stats['this_month_profit']??0,0); ?></div><div class="stat-label">This Month Profit</div></div><i class="fas fa-chart-line" style="font-size:28px;color:#9B59B6;opacity:.2;align-self:center;"></i></div></div>
        <div class="stat-card" style="border-left:4px solid #1ABC9C;"><div style="display:flex;justify-content:space-between;"><div><div class="stat-value" id="s_employees"><?php echo number_format($stats['active_employees']??0); ?></div><div class="stat-label">Active Employees</div></div><i class="fas fa-users" style="font-size:28px;color:#1ABC9C;opacity:.2;align-self:center;"></i></div></div>
        <div class="stat-card" style="border-left:4px solid #E74C3C;"><div style="display:flex;justify-content:space-between;"><div><div class="stat-value" id="s_lowstock" style="color:#E74C3C;"><?php echo number_format($stats['low_stock_items']??0); ?></div><div class="stat-label">Low Stock Items</div></div><i class="fas fa-exclamation-triangle" style="font-size:28px;color:#E74C3C;opacity:.2;align-self:center;"></i></div></div>
    </div>
    <div class="ch-card">
        <div class="ch-head">
            <h3 class="ch-title"><i class="fas fa-calendar-week" style="color:#3498DB;margin-right:6px;"></i>Weekly Orders &amp; Revenue</h3>
            <select id="weeklyWeeks" onchange="upd('weekly_orders',weeklyChart,{limit:this.value})"><option value="8">Last 8 Weeks</option><option value="12" selected>Last 12 Weeks</option><option value="24">Last 24 Weeks</option></select>
        </div>
        <canvas id="weeklyChart" height="80"></canvas>
    </div>
    <div class="g2">
        <div class="ch-card">
            <div class="ch-head"><h3 class="ch-title"><i class="fas fa-chart-area" style="color:#27AE60;margin-right:6px;"></i>Monthly Revenue</h3><select id="revMonths" onchange="upd('monthly_revenue',revChart,{months:this.value})"><option value="6">6 Mo</option><option value="12" selected>12 Mo</option><option value="24">24 Mo</option></select></div>
            <canvas id="revChart" height="150"></canvas>
        </div>
        <div class="ch-card">
            <div class="ch-head"><h3 class="ch-title"><i class="fas fa-chart-pie" style="color:#9B59B6;margin-right:6px;"></i>Orders by Status</h3></div>
            <canvas id="statusChart" height="150"></canvas>
        </div>
    </div>
    <div class="ch-card">
        <div class="ch-head"><h3 class="ch-title"><i class="fas fa-chart-line" style="color:#E74C3C;margin-right:6px;"></i>Monthly Profit Trend</h3><select id="profitMonths" onchange="upd('monthly_profit',profitChart,{months:this.value})"><option value="6">6 Mo</option><option value="12" selected>12 Mo</option><option value="24">24 Mo</option></select></div>
        <canvas id="profitChart" height="80"></canvas>
    </div>
    <div class="g2">
        <div class="ch-card">
            <div class="ch-head"><h3 class="ch-title"><i class="fas fa-couch" style="color:#F39C12;margin-right:6px;"></i>Top Selling Products</h3><select id="prodLimit" onchange="upd('top_products',prodChart,{limit:this.value})"><option value="5">Top 5</option><option value="10" selected>Top 10</option></select></div>
            <canvas id="prodChart" height="150"></canvas><div id="prodDetails"></div>
        </div>
        <div class="ch-card">
            <div class="ch-head"><h3 class="ch-title"><i class="fas fa-users" style="color:#1ABC9C;margin-right:6px;"></i>Top Customers</h3><div style="display:flex;gap:6px;"><select id="custLimit" onchange="upd('top_customers',custChart,{limit:this.value,months:document.getElementById('custMonths').value})"><option value="5">Top 5</option><option value="10" selected>Top 10</option></select><select id="custMonths" onchange="upd('top_customers',custChart,{limit:document.getElementById('custLimit').value,months:this.value})"><option value="6">6 Mo</option><option value="12" selected>12 Mo</option></select></div></div>
            <canvas id="custChart" height="150"></canvas><div id="custDetails"></div>
        </div>
    </div>
    <div class="g2">
        <div class="ch-card">
            <div class="ch-head"><h3 class="ch-title"><i class="fas fa-clock" style="color:#3498DB;margin-right:6px;"></i>Employee Hours (30 Days)</h3><select id="empLimit" onchange="upd('employee_hours',empChart,{limit:this.value})"><option value="5">Top 5</option><option value="8" selected>Top 8</option><option value="10">Top 10</option></select></div>
            <canvas id="empChart" height="150"></canvas>
        </div>
        <div class="ch-card">
            <div class="ch-head"><h3 class="ch-title"><i class="fas fa-exclamation-triangle" style="color:#E74C3C;margin-right:6px;"></i>Low Stock Alerts</h3></div>
            <canvas id="stockChart" height="150"></canvas><div id="stockDetails"></div>
        </div>
    </div>
    <div class="g2">
        <div class="ch-card">
            <div class="ch-head"><h3 class="ch-title"><i class="fas fa-boxes" style="color:#8E44AD;margin-right:6px;"></i>Material Usage Trends</h3><div style="display:flex;gap:6px;"><select id="matMonths" onchange="upd('material_usage',matChart,{months:this.value,limit:document.getElementById('matLimit').value})"><option value="6">6 Mo</option><option value="12" selected>12 Mo</option></select><select id="matLimit" onchange="upd('material_usage',matChart,{months:document.getElementById('matMonths').value,limit:this.value})"><option value="3">Top 3</option><option value="6" selected>Top 6</option><option value="10">Top 10</option></select></div></div>
            <canvas id="matChart" height="150"></canvas>
        </div>
        <div class="ch-card">
            <div class="ch-head"><h3 class="ch-title"><i class="fas fa-user-check" style="color:#27AE60;margin-right:6px;"></i>Employee Productivity</h3><div style="display:flex;gap:6px;"><select id="epMonths" onchange="upd('employee_productivity',epChart,{months:this.value,limit:document.getElementById('epLimit').value})"><option value="3">3 Mo</option><option value="6" selected>6 Mo</option><option value="12">12 Mo</option></select><select id="epLimit" onchange="upd('employee_productivity',epChart,{months:document.getElementById('epMonths').value,limit:this.value})"><option value="5">Top 5</option><option value="8" selected>Top 8</option></select></div></div>
            <canvas id="epChart" height="150"></canvas><div id="epDetails"></div>
        </div>
    </div>
    <div class="ch-card">
        <div class="ch-head"><h3 class="ch-title"><i class="fas fa-download" style="margin-right:6px;"></i>Export Data</h3></div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a href="<?php echo BASE_URL; ?>/public/analytics/export?type=summary" class="btn-action btn-primary-custom"><i class="fas fa-file-csv"></i> Summary CSV</a>
            <a href="<?php echo BASE_URL; ?>/public/analytics/export?type=detailed&data=revenue" class="btn-action btn-success-custom"><i class="fas fa-chart-bar"></i> Revenue CSV</a>
            <a href="<?php echo BASE_URL; ?>/public/analytics/export?type=detailed&data=products" class="btn-action btn-warning-custom"><i class="fas fa-couch"></i> Products CSV</a>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const BASE='<?php echo BASE_URL; ?>';
let weeklyChart,revChart,statusChart,profitChart,prodChart,custChart,empChart,stockChart,matChart,epChart;
document.addEventListener('DOMContentLoaded',function(){
    const D=<?php echo json_encode($chartData); ?>;
    const sc2=(ya,yb)=>({y:{beginAtZero:true,title:{display:true,text:ya}},y1:{position:'right',beginAtZero:true,title:{display:true,text:yb},grid:{drawOnChartArea:false}}});
    weeklyChart=mk('weeklyChart','bar',D.weeklyOrders,sc2('Orders','Revenue (ETB)'));
    revChart=mk('revChart','line',D.monthlyRevenue,sc2('Revenue (ETB)','Orders'));
    statusChart=new Chart(document.getElementById('statusChart'),{type:'pie',data:D.ordersByStatus,options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}}}});
    profitChart=mk('profitChart','line',D.monthlyProfit,sc2('ETB','Margin %'));
    prodChart=mk('prodChart','bar',D.topProducts,sc2('Revenue (ETB)','Qty'));
    tbl('prodDetails',D.topProducts.detailed_data||[],['product_name','category','orders_count','total_revenue'],['Product','Category','Orders','Revenue']);
    custChart=mk('custChart','bar',D.topCustomers,sc2('Revenue (ETB)','Orders'));
    tbl('custDetails',D.topCustomers.detailed_data||[],['customer_name','orders_count','total_revenue'],['Customer','Orders','Revenue (ETB)']);
    empChart=mk('empChart','bar',D.employeeHours,{y:{beginAtZero:true}});
    stockChart=new Chart(document.getElementById('stockChart'),{type:'doughnut',data:D.lowStockAlerts,options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}}}});
    tbl('stockDetails',D.lowStockAlerts.detailed_data||[],['name','current_stock','min_stock_level','unit','stock_status'],['Material','Stock','Min','Unit','Status']);
    matChart=mk('matChart','line',D.materialUsage,{y:{beginAtZero:true,title:{display:true,text:'Qty Used'}}});
    epChart=mk('epChart','bar',D.employeeProductivity,sc2('Completed','Avg Days'));
    tbl('epDetails',D.employeeProductivity.detailed_data||[],['employee_name','completed_orders','avg_completion_days'],['Employee','Completed','Avg Days']);
    setInterval(liveStats,30000);
});
function mk(id,type,data,scales){return new Chart(document.getElementById(id),{type,data,options:{responsive:true,maintainAspectRatio:false,scales:scales||{}}});}
function tbl(id,rows,cols,heads){if(!rows||!rows.length)return;let h='<table class="dt"><thead><tr>'+heads.map(x=>`<th>${x}</th>`).join('')+'</tr></thead><tbody>';rows.forEach(r=>{h+='<tr>'+cols.map(c=>`<td>${r[c]??''}</td>`).join('')+'</tr>';});h+='</tbody></table>';const el=document.getElementById(id);if(el)el.innerHTML=h;}
function upd(type,chart,params){fetch(`${BASE}/public/analytics/get-chart-data?chart=${type}&${new URLSearchParams(params)}`).then(r=>r.json()).then(d=>{if(!d.success)return;chart.data=d.data;chart.update();if(type==='top_products')tbl('prodDetails',d.data.detailed_data||[],['product_name','category','orders_count','total_revenue'],['Product','Category','Orders','Revenue']);if(type==='top_customers')tbl('custDetails',d.data.detailed_data||[],['customer_name','orders_count','total_revenue'],['Customer','Orders','Revenue (ETB)']);if(type==='employee_productivity')tbl('epDetails',d.data.detailed_data||[],['employee_name','completed_orders','avg_completion_days'],['Employee','Completed','Avg Days']);}).catch(()=>{});}
function liveStats(){fetch(`${BASE}/public/analytics/get-updates`).then(r=>r.json()).then(d=>{if(!d.success)return;const s=d.stats;document.getElementById('s_total_orders').textContent=Number(s.total_orders??0).toLocaleString();document.getElementById('s_pending').textContent=Number(s.pending_orders??0).toLocaleString();document.getElementById('s_revenue').textContent='ETB '+Number(s.this_month_revenue??0).toLocaleString();document.getElementById('s_profit').textContent='ETB '+Number(s.this_month_profit??0).toLocaleString();document.getElementById('s_employees').textContent=Number(s.active_employees??0).toLocaleString();document.getElementById('s_lowstock').textContent=Number(s.low_stock_items??0).toLocaleString();}).catch(()=>{});}
function doRefreshCache(){if(!confirm('Refresh analytics cache?'))return;fetch(`${BASE}/public/analytics/refresh-cache`,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'csrf_token=<?php echo htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? $_SESSION["csrf_token"] ?? ""); ?>'}).then(r=>r.json()).then(d=>alert(d.message||d.error||'Done')).catch(()=>alert('Error'));}
</script>
<script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
