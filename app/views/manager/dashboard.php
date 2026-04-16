<?php
// Manager authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}

// Get database connection
require_once __DIR__ . '/../../../config/db_config.php';

$managerName = $_SESSION['user_name'] ?? 'Manager User';

// Fetch ALL statistics
$stats = [];
try {
    // ── Orders ──
    $stats['total_orders']      = (int)$pdo->query("SELECT COUNT(*) FROM furn_orders")->fetchColumn();
    $stats['pending_approval']  = (int)$pdo->query("SELECT COUNT(*) FROM furn_orders WHERE status IN ('pending_review','pending_cost_approval')")->fetchColumn();
    $stats['in_production']     = (int)$pdo->query("SELECT COUNT(*) FROM furn_orders WHERE status IN ('in_production','production_started')")->fetchColumn();
    $stats['ready_delivery']    = (int)$pdo->query("SELECT COUNT(*) FROM furn_orders WHERE status = 'ready_for_delivery'")->fetchColumn();
    $stats['completed_orders']  = (int)$pdo->query("SELECT COUNT(*) FROM furn_orders WHERE status = 'completed'")->fetchColumn();
    $stats['cancelled_orders']  = (int)$pdo->query("SELECT COUNT(*) FROM furn_orders WHERE status = 'cancelled'")->fetchColumn();

    // ── Revenue & Profit ──
    $stats['total_revenue']     = floatval($pdo->query("SELECT COALESCE(SUM(amount),0) FROM furn_payments WHERE status IN ('approved','verified')")->fetchColumn());
    $stats['revenue_month']     = floatval($pdo->query("SELECT COALESCE(SUM(amount),0) FROM furn_payments WHERE status IN ('approved','verified') AND MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())")->fetchColumn());
    $stats['pending_payments']  = (int)$pdo->query("SELECT COUNT(*) FROM furn_payments WHERE status='pending'")->fetchColumn();
    $stats['total_material_cost']= floatval($pdo->query("SELECT COALESCE(SUM(total_cost),0) FROM furn_order_materials")->fetchColumn());
    $stats['total_payroll']     = floatval($pdo->query("SELECT COALESCE(SUM(net_salary),0) FROM furn_payroll WHERE status='approved'")->fetchColumn());
    $overheadRate = 0.10;
    try { $ov = $pdo->query("SELECT setting_value FROM furn_settings WHERE setting_key='overhead_rate' LIMIT 1")->fetchColumn(); if($ov) $overheadRate = floatval($ov)/100; } catch(PDOException $e2){}
    $stats['total_profit']      = $stats['total_revenue'] - $stats['total_material_cost'] - $stats['total_payroll'] - ($stats['total_revenue'] * $overheadRate);

    // ── Employees ──
    $stats['total_employees']   = (int)$pdo->query("SELECT COUNT(*) FROM furn_users WHERE role='employee'")->fetchColumn();
    $attCols = $pdo->query("SHOW COLUMNS FROM furn_attendance")->fetchAll(PDO::FETCH_COLUMN);
    $dateExpr = in_array('date',$attCols) ? 'date' : 'DATE(check_in_time)';
    $stats['present_today']     = (int)$pdo->query("SELECT COUNT(DISTINCT employee_id) FROM furn_attendance WHERE $dateExpr=CURDATE() AND status IN ('present','late')")->fetchColumn();
    $stats['active_tasks']      = (int)$pdo->query("SELECT COUNT(*) FROM furn_production_tasks WHERE status IN ('pending','in_progress')")->fetchColumn();
    $stats['completed_tasks_month'] = (int)$pdo->query("SELECT COUNT(*) FROM furn_production_tasks WHERE status='completed' AND MONTH(completed_at)=MONTH(CURDATE()) AND YEAR(completed_at)=YEAR(CURDATE())")->fetchColumn();

    // ── Inventory ──
    $stats['total_materials']   = (int)$pdo->query("SELECT COUNT(*) FROM furn_materials")->fetchColumn();
    $stats['low_stock']         = (int)$pdo->query("SELECT COUNT(*) FROM furn_materials WHERE current_stock < minimum_stock")->fetchColumn();
    $stats['inventory_value']   = floatval($pdo->query("SELECT COALESCE(SUM(current_stock * cost_per_unit),0) FROM furn_materials")->fetchColumn());
    $stats['materials_used_month'] = floatval($pdo->query("SELECT COALESCE(SUM(total_cost),0) FROM furn_order_materials WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())")->fetchColumn());

    // ── Customers ──
    $stats['total_customers']   = (int)$pdo->query("SELECT COUNT(*) FROM furn_users WHERE role='customer'")->fetchColumn();
    $stats['new_customers_month']= (int)$pdo->query("SELECT COUNT(*) FROM furn_users WHERE role='customer' AND MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())")->fetchColumn();
    $rr = $pdo->query("SELECT COUNT(*) as total, ROUND(AVG(rating),1) as avg FROM furn_ratings")->fetch(PDO::FETCH_ASSOC);
    $stats['avg_rating']        = floatval($rr['avg'] ?? 0);
    $stats['total_reviews']     = (int)($rr['total'] ?? 0);

    // ── Payroll ──
    $stats['pending_payroll']   = (int)$pdo->query("SELECT COUNT(*) FROM furn_payroll WHERE status='pending_approval'")->fetchColumn();
    $stats['payroll_month']     = floatval($pdo->query("SELECT COALESCE(SUM(net_salary),0) FROM furn_payroll WHERE status='approved' AND month=MONTH(CURDATE()) AND year=YEAR(CURDATE())")->fetchColumn());

    // ── Open Complaints ──
    $stats['open_complaints']   = 0;
    try { $stats['open_complaints'] = (int)$pdo->query("SELECT COUNT(*) FROM furn_complaints WHERE status='open'")->fetchColumn(); } catch(PDOException $e2){}

    // ── Action-needed counts ──
    $stats['awaiting_assignment'] = (int)$pdo->query("SELECT COUNT(*) FROM furn_orders WHERE status = 'cost_estimated'")->fetchColumn();
    $stats['overdue_tasks']       = 0;
    try { $stats['overdue_tasks'] = (int)$pdo->query("SELECT COUNT(*) FROM furn_production_tasks WHERE status IN ('pending','in_progress') AND due_date IS NOT NULL AND due_date < CURDATE()")->fetchColumn(); } catch(PDOException $e2){}
    $stats['completed_month']     = (int)$pdo->query("SELECT COUNT(*) FROM furn_orders WHERE status='completed' AND MONTH(updated_at)=MONTH(CURDATE()) AND YEAR(updated_at)=YEAR(CURDATE())")->fetchColumn();

} catch (PDOException $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
    $stats = array_fill_keys(array_keys($stats ?? []), 0);
}

// Fetch current production orders
$productionOrders = [];
try {
    $stmt = $pdo->query("
        SELECT o.*, u.first_name, u.last_name, 
               COALESCE(pt.progress, 0) as progress
        FROM furn_orders o
        LEFT JOIN furn_users u ON o.customer_id = u.id
        LEFT JOIN (
            SELECT order_id, ROUND(AVG(progress), 0) as progress
            FROM furn_production_tasks
            GROUP BY order_id
        ) pt ON o.id = pt.order_id
        WHERE o.status IN ('in_production', 'production_started')
        ORDER BY o.created_at DESC
        LIMIT 10
    ");
    $productionOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Production orders error: " . $e->getMessage());
}

// Fetch new orders waiting for review
$newOrders = [];
try {
    $stmt = $pdo->query("
        SELECT o.*, u.first_name, u.last_name, u.email
        FROM furn_orders o
        LEFT JOIN furn_users u ON o.customer_id = u.id
        WHERE o.status IN ('pending', 'pending_review', 'pending_cost_approval')
        ORDER BY o.created_at DESC
        LIMIT 10
    ");
    $newOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("New orders error: " . $e->getMessage());
}

// Fetch low stock materials
$lowStockMaterials = [];
try {
    $stmt = $pdo->query("
        SELECT * FROM furn_materials 
        WHERE current_stock < minimum_stock
        ORDER BY current_stock ASC
        LIMIT 10
    ");
    $lowStockMaterials = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Low stock error: " . $e->getMessage());
}

$pageTitle = 'Manager Dashboard';
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
        .top-header { position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; width: 100% !important; z-index: 1998 !important; }
        @media (min-width: 1024px) { .top-header { left: 260px !important; width: calc(100% - 260px) !important; } }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" aria-label="Toggle Menu">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay"></div>

    <?php include_once __DIR__ . '/../../includes/manager_sidebar.php'; ?>

    <!-- Top Header -->
    <?php 
    $pageTitle = 'Manager Dashboard';
    include_once __DIR__ . '/../../includes/manager_header.php'; 
    ?>
        <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
            <span style="font-size: 20px;">🔨</span>
            <span style="font-weight: 700; font-size: 16px; color: white;"><span style="color: #e67e22;">Smart</span>Workshop</span>
            <span style="color: rgba(255,255,255,0.4); margin: 0 5px;">|</span>
            <span style="font-size: 14px; color: rgba(255,255,255,0.85);">Furniture ERP &nbsp;<strong style="color:white;">Manager Dashboard</strong></span>
        </div>
        <div class="header-right" style="display: flex; align-items: center; gap: 15px;">
            <div class="system-status" style="background: #27AE60; color: white; padding: 5px 12px; border-radius: 20px; font-size: 12px; display: flex; align-items: center; gap: 6px;">
                <span style="width: 8px; height: 8px; background: white; border-radius: 50%; display: inline-block;"></span> Operational
            </div>
            <div style="position: relative; cursor: pointer;">
                <i class="fas fa-bell" style="font-size: 18px; color: rgba(255,255,255,0.85);"></i>
                <?php if(($stats['pending_approval'] ?? 0) > 0): ?>
                <span style="position: absolute; top: -6px; right: -6px; background: #e74c3c; color: white; border-radius: 50%; width: 18px; height: 18px; font-size: 10px; display: flex; align-items: center; justify-content: center; font-weight: 700;"><?php echo $stats['pending_approval']; ?></span>
                <?php endif; ?>
            </div>
            <div class="admin-profile" style="display: flex; align-items: center; gap: 10px;">
                <div class="admin-avatar" style="background: #3498DB;"><?php echo strtoupper(substr($managerName, 0, 1)); ?></div>
                <div>
                    <div style="font-size: 13px; font-weight: 600; color: white;"><?php echo htmlspecialchars($managerName); ?></div>
                    <div class="admin-role-badge" style="background: #3498DB;">MANAGER</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Statistics Cards -->
        <div class="stats-grid" style="margin-bottom:24px;">
            <?php
            $dashCards = [
                ['val'=>number_format($stats['total_orders']),                 'lbl'=>'Total Orders',       'icon'=>'fa-shopping-cart',  'color'=>'#3498DB', 'href'=>BASE_URL.'/public/manager/orders'],
                ['val'=>'ETB '.number_format($stats['total_revenue'],0),       'lbl'=>'Total Revenue',      'icon'=>'fa-money-bill-wave','color'=>'#27AE60', 'href'=>BASE_URL.'/public/manager/payments'],
                ['val'=>number_format($stats['total_employees']),              'lbl'=>'Total Employees',    'icon'=>'fa-user-tie',       'color'=>'#9B59B6', 'href'=>BASE_URL.'/public/manager/attendance'],
                ['val'=>number_format($stats['total_customers']),              'lbl'=>'Total Customers',    'icon'=>'fa-users',          'color'=>'#1ABC9C', 'href'=>BASE_URL.'/public/manager/orders'],
                ['val'=>'ETB '.number_format(max(0,$stats['total_profit']),0), 'lbl'=>'Total Profit',       'icon'=>'fa-chart-line',     'color'=>'#F39C12', 'href'=>BASE_URL.'/public/manager/profit-report'],
                ['val'=>number_format($stats['total_materials']),              'lbl'=>'Raw Material Types', 'icon'=>'fa-boxes',          'color'=>'#E67E22', 'href'=>BASE_URL.'/public/manager/inventory'],
                ['val'=>number_format($stats['ready_delivery']),               'lbl'=>'Ready for Delivery', 'icon'=>'fa-truck',          'color'=>'#E74C3C', 'href'=>BASE_URL.'/public/manager/orders'],
                // Action alert cards merged into same grid
                ['val'=>(string)$stats['pending_payments'],                    'lbl'=>'Pending Payments',   'icon'=>'fa-credit-card',    'color'=>$stats['pending_payments']>0?'#e74c3c':'#27ae60', 'href'=>BASE_URL.'/public/manager/payments',        'sub'=>$stats['pending_payments']>0?'Needs approval':'All clear'],
                ['val'=>(string)$stats['awaiting_assignment'],                 'lbl'=>'Awaiting Assignment','icon'=>'fa-user-plus',      'color'=>$stats['awaiting_assignment']>0?'#f39c12':'#27ae60','href'=>BASE_URL.'/public/manager/assign-employees','sub'=>$stats['awaiting_assignment']>0?'Assign employees':'All assigned'],
                ['val'=>(string)$stats['overdue_tasks'],                       'lbl'=>'Overdue Tasks',      'icon'=>'fa-clock',          'color'=>$stats['overdue_tasks']>0?'#e74c3c':'#27ae60',    'href'=>BASE_URL.'/public/manager/production',      'sub'=>$stats['overdue_tasks']>0?'Past due date':'On schedule'],
                ['val'=>'ETB '.number_format($stats['revenue_month'],0),       'lbl'=>'Revenue This Month', 'icon'=>'fa-chart-bar',      'color'=>'#27ae60', 'href'=>BASE_URL.'/public/manager/payments',        'sub'=>'Approved payments'],
                ['val'=>(string)$stats['completed_month'],                     'lbl'=>'Completed This Month','icon'=>'fa-check-double',  'color'=>'#9b59b6', 'href'=>BASE_URL.'/public/manager/orders',           'sub'=>'Finished orders'],
            ];
            foreach($dashCards as $c): ?>
            <a href="<?php echo $c['href']; ?>" style="text-decoration:none;">
                <div class="stat-card" style="border-left:4px solid <?php echo $c['color']; ?>;cursor:pointer;transition:transform .15s,box-shadow .15s;min-height:90px;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,0.12)';" onmouseout="this.style.transform='';this.style.boxShadow='';">
                    <div style="display:flex;justify-content:space-between;align-items:center;height:100%;">
                        <div>
                            <div class="stat-value" style="font-size:<?php echo strpos($c['val'],'ETB')!==false?'15px':'26px';?>;color:<?php echo $c['color']; ?>;line-height:1.2;min-height:32px;display:flex;align-items:center;"><?php echo $c['val']; ?></div>
                            <div class="stat-label"><?php echo $c['lbl']; ?></div>
                            <?php if(!empty($c['sub'])): ?><div style="font-size:11px;color:#aaa;margin-top:2px;"><?php echo $c['sub']; ?></div><?php endif; ?>
                        </div>
                        <div style="font-size:28px;color:<?php echo $c['color']; ?>;opacity:.25;"><i class="fas <?php echo $c['icon']; ?>"></i></div>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Current Production Orders -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-industry"></i> Current Production Orders</h2>
                <a href="<?php echo BASE_URL; ?>/public/manager/production" class="btn-action btn-primary-custom">View All</a>
            </div>
            
            <?php if (empty($productionOrders)): ?>
                <p style="text-align: center; color: #7f8c8d; padding: 40px;">No orders in production</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table mobile-cards">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Furniture Type</th>
                                <th>Progress</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productionOrders as $order): ?>
                                <tr>
                                    <td>#<?php echo $order['id']; ?></td>
                                    <td><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($order['furniture_type'] ?? 'Custom'); ?></td>
                                    <td>
                                        <div style="background: #f0f0f0; border-radius: 10px; height: 20px; overflow: hidden;">
                                            <div style="background: #27AE60; height: 100%; width: <?php echo $order['progress']; ?>%; transition: width 0.3s;"></div>
                                        </div>
                                        <small><?php echo $order['progress']; ?>%</small>
                                    </td>
                                    <td><span class="status-badge status-in_production">In Production</span></td>
                                    <td>
                                        <button class="btn-action btn-primary-custom" onclick="viewOrder(<?php echo $order['id']; ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- New Orders Waiting for Review -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-clipboard-check"></i> New Orders Waiting for Review</h2>
                <a href="<?php echo BASE_URL; ?>/public/manager/orders" class="btn-action btn-warning-custom">Review All</a>
            </div>

            <?php if (empty($newOrders)): ?>
                <p style="text-align: center; color: #7f8c8d; padding: 40px;">No pending orders</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table mobile-cards">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Furniture Type</th>
                                <th>Budget</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($newOrders as $order): ?>
                                <tr>
                                    <td>#<?php echo $order['id']; ?></td>
                                    <td><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($order['furniture_type'] ?? 'Custom'); ?></td>
                                    <td>ETB <?php echo number_format($order['estimated_cost'] ?? 0, 2); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                    <td>
                                        <button class="btn-action btn-success-custom" onclick="approveOrder(<?php echo $order['id']; ?>)">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button class="btn-action btn-danger-custom" onclick="rejectOrder(<?php echo $order['id']; ?>)">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Low Stock Alerts -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-exclamation-triangle"></i> Low Stock Alerts</h2>
                <a href="<?php echo BASE_URL; ?>/public/manager/inventory" class="btn-action btn-danger-custom">Manage Inventory</a>
            </div>
            
            <?php if (empty($lowStockMaterials)): ?>
                <p style="text-align: center; color: #27AE60; padding: 40px;"><i class="fas fa-check-circle"></i> All materials are well stocked</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table mobile-cards">
                        <thead>
                            <tr>
                                <th>Material Name</th>
                                <th>Current Stock</th>
                                <th>Unit</th>
                                <th>Threshold</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lowStockMaterials as $material): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($material['name'] ?? $material['material_name'] ?? 'Unknown'); ?></td>
                                    <td><strong><?php echo $material['quantity'] ?? 0; ?></strong></td>
                                    <td><?php echo htmlspecialchars($material['unit'] ?? 'pcs'); ?></td>
                                    <td>20</td>
                                    <td><span class="badge badge-danger">Low Stock</span></td>
                                    <td>
                                        <button class="btn-action btn-warning-custom" onclick="restockMaterial(<?php echo $material['id']; ?>)">
                                            <i class="fas fa-plus"></i> Restock
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <!-- Recent Customer Reviews -->
        <?php if (!empty($recentRatings)): ?>
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-star"></i> Recent Customer Reviews</h2>
                <a href="<?php echo BASE_URL; ?>/public/manager/reports" class="btn-action btn-primary-custom">View All</a>
            </div>
            <?php foreach ($recentRatings as $rev): ?>
            <div style="padding: 15px; border-bottom: 1px solid #f0f0f0;">
                <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 8px;">
                    <div>
                        <strong><?php echo htmlspecialchars($rev['customer_name']); ?></strong>
                        <span style="color: #7f8c8d; font-size: 13px; margin-left: 8px;">Order #<?php echo htmlspecialchars($rev['order_number']); ?></span><br>
                        <small style="color: #888;">Employee: <strong><?php echo htmlspecialchars($rev['employee_name']); ?></strong> — <?php echo htmlspecialchars($rev['furniture_name'] ?? ''); ?></small>
                    </div>
                    <div style="text-align: right;">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star" style="color: <?php echo $i <= $rev['rating'] ? '#f39c12' : '#ddd'; ?>;"></i>
                        <?php endfor; ?>
                        <br><small style="color: #aaa;"><?php echo date('M j, Y', strtotime($rev['created_at'])); ?></small>
                    </div>
                </div>
                <?php if (!empty($rev['review_text'])): ?>
                    <p style="margin: 8px 0 0; color: #555; font-style: italic;">"<?php echo htmlspecialchars($rev['review_text']); ?>"</p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function viewOrder(orderId) {
            window.location.href = '<?php echo BASE_URL; ?>/public/manager/orders?view=' + orderId;
        }

        function approveOrder(orderId) {
            // Redirect manager to cost-estimation page to set price before approving
            window.location.href = '<?php echo BASE_URL; ?>/public/manager/cost-estimation?order_id=' + orderId;
        }

        function rejectOrder(orderId) {
            document.getElementById('rejectOrderId').value = orderId;
            document.getElementById('rejectReason').value = '';
            document.getElementById('rejectModal').style.display = 'flex';
            document.getElementById('rejectReason').focus();
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').style.display = 'none';
        }

        function submitReject() {
            const reason = document.getElementById('rejectReason').value.trim();
            if (!reason) {
                document.getElementById('rejectReason').style.borderColor = '#e74c3c';
                document.getElementById('rejectReason').placeholder = 'Rejection reason is required!';
                return;
            }
            const orderId = document.getElementById('rejectOrderId').value;
            const btn = document.getElementById('rejectSubmitBtn');
            btn.disabled = true;
            btn.textContent = 'Rejecting...';
            fetch('<?php echo BASE_URL; ?>/public/api/order_action.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=reject&order_id=' + orderId + '&reason=' + encodeURIComponent(reason) + '&csrf_token=<?php echo $_SESSION[CSRF_TOKEN_NAME] ?? ""; ?>'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    closeRejectModal();
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Could not reject order.'));
                    btn.disabled = false;
                    btn.textContent = 'Confirm Reject';
                }
            })
            .catch(() => {
                alert('Network error. Please try again.');
                btn.disabled = false;
                btn.textContent = 'Confirm Reject';
            });
        }

        function restockMaterial(materialId) {
            window.location.href = '<?php echo BASE_URL; ?>/public/manager/inventory#restock-' + materialId;
        }
    </script>

    <!-- Reject Order Modal -->
    <div id="rejectModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
        <div style="background:white;border-radius:12px;width:100%;max-width:460px;margin:20px;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,0.2);">
            <div style="background:linear-gradient(135deg,#e74c3c,#c0392b);padding:16px 20px;display:flex;justify-content:space-between;align-items:center;">
                <h3 style="margin:0;color:white;font-size:16px;"><i class="fas fa-times-circle"></i> Reject Order</h3>
                <button onclick="closeRejectModal()" style="background:none;border:none;color:white;font-size:20px;cursor:pointer;line-height:1;">&times;</button>
            </div>
            <div style="padding:20px;">
                <input type="hidden" id="rejectOrderId">
                <p style="margin:0 0 6px;color:#555;font-size:14px;">You are rejecting this order. The customer will be notified with your reason.</p>
                <div style="margin-top:14px;">
                    <label style="font-weight:600;font-size:13px;color:#2c3e50;display:block;margin-bottom:6px;">Rejection Reason <span style="color:#e74c3c;">*</span></label>
                    <textarea id="rejectReason" rows="4" placeholder="e.g. Budget too low, incomplete information, not feasible..."
                        style="width:100%;padding:10px 12px;border:1.5px solid #ddd;border-radius:8px;font-size:14px;font-family:inherit;resize:vertical;box-sizing:border-box;"
                        oninput="this.style.borderColor='#ddd'"></textarea>
                </div>
            </div>
            <div style="padding:14px 20px;border-top:1px solid #eee;display:flex;justify-content:flex-end;gap:10px;">
                <button onclick="closeRejectModal()" style="padding:9px 20px;border:1.5px solid #ddd;background:white;border-radius:8px;font-size:14px;cursor:pointer;font-family:inherit;">Cancel</button>
                <button id="rejectSubmitBtn" onclick="submitReject()" style="padding:9px 20px;background:#e74c3c;color:white;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit;">Confirm Reject</button>
            </div>
        </div>
    </div>

    <!-- Mobile Menu Script -->
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
