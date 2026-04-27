<?php
// Admin authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}

// Get database connection
require_once __DIR__ . '/../../../config/db_config.php';

$adminName = $_SESSION['user_name'] ?? 'Admin User';

// Fetch dashboard statistics
$stats = [
    'total_users' => 0, 'total_orders' => 0, 'low_stock_count' => 0,
    'monthly_revenue' => 0, 'pending_orders' => 0, 'avg_rating' => 0, 'total_ratings' => 0,
    'net_profit' => 0, 'finished_products' => 0, 'total_raw_materials' => 0,
    'total_payroll' => 0, 'material_cost' => 0, 'low_stock_materials' => 0,
];

try {
    $stats['total_users']       = $pdo->query("SELECT COUNT(*) FROM furn_users")->fetchColumn();
    $stats['total_orders']      = $pdo->query("SELECT COUNT(*) FROM furn_orders")->fetchColumn();
    $stats['monthly_revenue']   = $pdo->query("SELECT COALESCE(SUM(estimated_cost), 0) FROM furn_orders WHERE MONTH(created_at) = MONTH(CURRENT_DATE) AND YEAR(created_at) = YEAR(CURRENT_DATE)")->fetchColumn();
    $stats['pending_orders']    = $pdo->query("SELECT COUNT(*) FROM furn_orders WHERE status IN ('pending_review', 'pending_cost_approval')")->fetchColumn();
    $stats['finished_products'] = $pdo->query("SELECT COUNT(*) FROM furn_orders WHERE status = 'completed'")->fetchColumn();
    $stats['total_payroll']     = $pdo->query("SELECT COALESCE(SUM(net_salary), 0) FROM furn_payroll WHERE status = 'approved'")->fetchColumn();
    $stats['material_cost']     = $pdo->query("SELECT COALESCE(SUM(total_cost), 0) FROM furn_order_materials")->fetchColumn();
    $stats['low_stock_materials'] = $pdo->query("SELECT COUNT(*) FROM furn_materials WHERE (current_stock - COALESCE(reserved_stock,0)) < minimum_stock AND is_active = 1")->fetchColumn();
    $stats['total_raw_materials'] = $pdo->query("SELECT COUNT(*) FROM furn_materials WHERE is_active = 1")->fetchColumn();
    // Net profit = revenue - material cost - payroll - 10% overhead
    try {
        $totalRev  = floatval($pdo->query("SELECT COALESCE(SUM(amount),0) FROM furn_payments WHERE status IN ('approved','verified')")->fetchColumn());
        $totalMat  = floatval($stats['material_cost']);
        $totalPay  = floatval($stats['total_payroll']);
        $overhead  = $totalRev * 0.10;
        $stats['net_profit'] = $totalRev - $totalMat - $totalPay - $overhead;
    } catch (PDOException $e) { $stats['net_profit'] = 0; }
    try {
        $stats['avg_rating']   = $pdo->query("SELECT ROUND(AVG(rating), 1) FROM furn_ratings")->fetchColumn() ?: 0;
        $stats['total_ratings'] = $pdo->query("SELECT COUNT(*) FROM furn_ratings")->fetchColumn();
    } catch (PDOException $e) {}
} catch (PDOException $e) {
    error_log("Admin dashboard stats error: " . $e->getMessage());
}

// Fetch recent orders
$recentOrders = [];
try {
    $stmt = $pdo->query("
        SELECT o.*, CONCAT(u.first_name, ' ', u.last_name) as customer_name, u.email as customer_email,
               r.rating as customer_rating, r.review_text as customer_review
        FROM furn_orders o
        LEFT JOIN furn_users u ON o.customer_id = u.id
        LEFT JOIN furn_ratings r ON r.order_id = o.id
        ORDER BY o.created_at DESC LIMIT 5
    ");
    $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Recent orders error: " . $e->getMessage());
}

// Fetch recent ratings
$recentRatings = [];
try {
    $stmt = $pdo->query("
        SELECT r.*, o.order_number, o.furniture_name,
               CONCAT(c.first_name, ' ', c.last_name) as customer_name,
               CONCAT(e.first_name, ' ', e.last_name) as employee_name
        FROM furn_ratings r
        LEFT JOIN furn_orders o ON r.order_id = o.id
        LEFT JOIN furn_users c ON r.customer_id = c.id
        LEFT JOIN furn_users e ON r.employee_id = e.id
        ORDER BY r.created_at DESC LIMIT 5
    ");
    $recentRatings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Fetch low stock materials
$lowStockMaterials = [];
try {
    $stmt = $pdo->query("SELECT * FROM furn_materials WHERE current_stock < minimum_stock ORDER BY current_stock ASC LIMIT 5");
    $lowStockMaterials = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Low stock materials error: " . $e->getMessage());
}

$pageTitle = 'Admin Dashboard';
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
        .top-header {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            width: 100% !important;
            z-index: 1998 !important;
        }
        @media (min-width: 1024px) {
            .top-header {
                left: 260px !important;
                width: calc(100% - 260px) !important;
            }
        }
        /* Page-specific styles */
        .main-content { padding-top: 24px !important; }
        .main-content h2 { margin: 0 0 16px 0 !important; }
        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; white-space: nowrap; }
        .status-pending_cost_approval { background: #fff3cd; color: #856404; }
        .status-in_production { background: #ffd59e; color: #b36b00; }
        .status-completed { background: #d4edda; color: #155724; }
        .empty-state { text-align: center; padding: 40px; color: #7f8c8d; }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" aria-label="Toggle Menu">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay (for mobile) -->
    <div class="sidebar-overlay"></div>

    <!-- Sidebar -->
    <?php include_once __DIR__ . '/../../includes/admin_sidebar.php'; ?>

    <!-- Top Header -->
    <?php 
    $pageTitle = 'Admin Dashboard';
    include_once __DIR__ . '/../../includes/admin_header.php'; 
    ?>

    <!-- Main Content -->
        <div class="main-content">
                <h2 style="margin: 0 0 15px 0; color: #2c3e50;">Dashboard Overview</h2>
                <!-- KPI Cards -->
                <div class="stats-grid">
                    <?php
                    $adminCards = [
                        [$stats['total_users'],       'Users',                   BASE_URL.'/public/admin/users',       '#3498DB', 'fa-users'],
                        [$stats['total_orders'],       'Orders',                  BASE_URL.'/public/admin/orders',      '#27AE60', 'fa-shopping-cart'],
                        ['ETB '.number_format($stats['monthly_revenue'],0), 'Monthly Revenue', BASE_URL.'/public/admin/reports', '#F39C12', 'fa-money-bill-wave'],
                        [($stats['avg_rating'] ?: '—'), 'Avg Rating ('.$stats['total_ratings'].' reviews)', BASE_URL.'/public/admin/reports', '#9B59B6', 'fa-star'],
                        ['ETB '.number_format($stats['net_profit'],0), 'Net Profit', BASE_URL.'/public/admin/reports', $stats['net_profit']>=0?'#27AE60':'#E74C3C', 'fa-chart-line'],
                        [$stats['finished_products'],  'Finished Products',       BASE_URL.'/public/admin/orders',      '#1ABC9C', 'fa-check-circle'],
                        [$stats['total_raw_materials'],'Raw Materials',           BASE_URL.'/public/admin/materials',   '#E67E22', 'fa-boxes'],
                        ['ETB '.number_format($stats['total_payroll'],0), 'Total Payroll', BASE_URL.'/public/admin/payroll', '#3498DB', 'fa-wallet'],
                        ['ETB '.number_format($stats['material_cost'],0), 'Material Cost', BASE_URL.'/public/admin/materials', '#E67E22', 'fa-tools'],
                        [$stats['low_stock_materials'],'Low Stock Alerts',        BASE_URL.'/public/admin/materials',   $stats['low_stock_materials']>0?'#E74C3C':'#27AE60', 'fa-exclamation-triangle'],
                    ];
                    foreach ($adminCards as [$v,$l,$href,$c,$i]): ?>
                    <a href="<?php echo $href; ?>" style="text-decoration:none;">
                        <div class="stat-card" style="border-left:4px solid <?php echo $c; ?>;cursor:pointer;transition:transform .15s,box-shadow .15s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,0.12)';" onmouseout="this.style.transform='';this.style.boxShadow='';">
                            <div style="display:flex;justify-content:space-between;align-items:start;">
                                <div>
                                    <div class="stat-value" style="color:<?php echo $c; ?>"><?php echo $v; ?></div>
                                    <div class="stat-label"><?php echo $l; ?></div>
                                </div>
                                <div style="font-size:28px;color:<?php echo $c; ?>;opacity:.25;"><i class="fas <?php echo $i; ?>"></i></div>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>



        <!-- Recent Orders -->
        <div class="section-card">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:14px;">
                <div class="section-title" style="margin:0;"><i class="fas fa-box me-2"></i>Recent Orders</div>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <input type="text" id="dashOrderSearch" placeholder="Search orders..."
                        style="padding:7px 12px; border:1px solid #ddd; border-radius:8px; font-size:13px; outline:none; width:180px;">
                    <select id="dashOrderStatus" style="padding:7px 12px; border:1px solid #ddd; border-radius:8px; font-size:13px; outline:none;">
                        <option value="">All Statuses</option>
                        <option value="pending_review">Pending Review</option>
                        <option value="pending_cost_approval">Pending Cost Approval</option>
                        <option value="cost_estimated">Cost Estimated - Awaiting Deposit</option>
                        <option value="in_production">In Production</option>
                        <option value="payment_verified">Payment Verified</option>
                        <option value="ready_for_delivery">Ready for Delivery</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    <button onclick="document.getElementById('dashOrderSearch').value='';document.getElementById('dashOrderStatus').value='';runDashOrderFilter();"
                        style="padding:7px 12px; background:#f0f0f0; border:1px solid #ddd; border-radius:8px; font-size:13px; cursor:pointer;">
                        <i class="fas fa-times"></i>
                    </button>
                    <span id="dashOrderCount" style="padding:7px 0; font-size:13px; color:#7f8c8d; align-self:center;"></span>
                </div>
            </div>
            <?php if (empty($recentOrders)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No orders yet</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Order Number</th>
                                <th>Customer</th>
                                <th>Furniture</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Rating</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="dashOrderBody">
                            <?php foreach ($recentOrders as $order): ?>
                            <tr data-status="<?php echo trim($order['status']); ?>">
                                <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($order['customer_name'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($order['furniture_name']); ?></td>
                                <td>ETB <?php echo number_format($order['estimated_cost'], 2); ?></td>
                                <td><span class="status-badge status-<?php echo $order['status']; ?>"><?php echo ucwords(str_replace('_', ' ', $order['status'])); ?></span></td>
                                <td>
                                    <?php if (!empty($order['customer_rating'])): ?>
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star" style="color: <?php echo $i <= $order['customer_rating'] ? '#f39c12' : '#ddd'; ?>; font-size: 13px;"></i>
                                        <?php endfor; ?>
                                        <strong style="font-size: 12px; margin-left: 3px;"><?php echo $order['customer_rating']; ?>/5</strong>
                                    <?php else: ?>
                                        <span style="color: #ccc; font-size: 12px;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                                <td><button class="btn-action btn-primary-custom" onclick="viewOrder(<?php echo $order['id'] ?? ''; ?>)">View</button></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Customer Reviews -->
        <div class="section-card">
            <div class="section-title"><i class="fas fa-star me-2" style="color:#f39c12;"></i>Recent Customer Reviews</div>
            <?php if (empty($recentRatings)): ?>
                <div class="empty-state"><i class="fas fa-star" style="font-size:2rem; color:#ddd;"></i><p>No ratings yet</p></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Furniture</th>
                                <th>Customer</th>
                                <th>Employee</th>
                                <th>Rating</th>
                                <th>Suggestion / Review</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentRatings as $r): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($r['order_number'] ?? '—'); ?></strong></td>
                                <td><?php echo htmlspecialchars($r['furniture_name'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($r['customer_name'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($r['employee_name'] ?? '—'); ?></td>
                                <td style="white-space: nowrap;">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star" style="color: <?php echo $i <= $r['rating'] ? '#f39c12' : '#ddd'; ?>; font-size: 14px;"></i>
                                    <?php endfor; ?>
                                    <strong style="font-size: 12px; margin-left: 3px;"><?php echo $r['rating']; ?>/5</strong>
                                </td>
                                <td style="max-width: 200px; color: #555; font-style: italic;">
                                    <?php echo !empty($r['review_text']) ? '"' . htmlspecialchars($r['review_text']) . '"' : '<span style="color:#aaa;">No suggestion</span>'; ?>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($r['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Low Stock Materials -->
        <div class="section-card">
            <div class="section-title"><i class="fas fa-boxes me-2"></i>Low Stock Alerts</div>
            <?php if (empty($lowStockMaterials)): ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <p>All materials are well stocked</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Material Name</th>
                                <th>Current Stock</th>
                                <th>Unit</th>
                                <th>Min Stock</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lowStockMaterials as $material): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($material['material_name'] ?? $material['name'] ?? 'Unknown'); ?></strong></td>
                                <td style="color: #E74C3C; font-weight: 600;"><?php echo number_format($material['current_stock'], 2); ?></td>
                                <td><?php echo htmlspecialchars($material['unit']); ?></td>
                                <td><?php echo number_format($material['minimum_stock'], 2); ?></td>
                                <td><a href="<?php echo BASE_URL; ?>/public/admin/materials" class="btn-action btn-success-custom" style="text-decoration:none;">Restock Now</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- View Order Modal -->
    <div id="viewModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 99999; align-items: center; justify-content: center; overflow-y: auto; padding: 20px;">
        <div style="background: white; padding: 30px; border-radius: 12px; width: 100%; max-width: 800px; margin: auto; max-height: 90vh; overflow-y: auto; position: relative;">
            <button onclick="closeViewModal()" style="position: absolute; top: 15px; right: 15px; background: #e74c3c; color: white; border: none; width: 35px; height: 35px; border-radius: 50%; cursor: pointer; font-size: 20px; line-height: 1; display: flex; align-items: center; justify-content: center;">×</button>
            <h3 style="margin: 0 0 20px 0; padding-right: 40px; color: #2c3e50;">Order Details</h3>
            <div id="orderDetails"></div>
            <div style="margin-top: 20px; text-align: right; padding-top: 15px; border-top: 2px solid #f0f0f0;">
                <button type="button" onclick="closeViewModal()" style="padding: 12px 30px; border: none; background: #95a5a6; color: white; border-radius: 6px; cursor: pointer; font-size: 15px; font-weight: 600;">Close</button>
            </div>
        </div>
    </div>

    <script>
        // Dashboard recent orders search + status filter
        (function() {
            const inp   = document.getElementById('dashOrderSearch');
            const sel   = document.getElementById('dashOrderStatus');
            const tbody = document.getElementById('dashOrderBody');
            const count = document.getElementById('dashOrderCount');
            if (!inp || !tbody) return;

            window.runDashOrderFilter = function() {
                const q      = inp.value.toLowerCase().trim();
                const status = sel.value;          // e.g. "in_production"
                const rows   = tbody.querySelectorAll('tr');
                let vis      = 0;

                rows.forEach(function(r) {
                    // data-status is set directly from PHP trim($order['status'])
                    var rowStatus = (r.getAttribute('data-status') || '').trim();
                    var matchQ    = !q || r.textContent.toLowerCase().indexOf(q) !== -1;
                    var matchS    = !status || rowStatus === status;
                    r.style.display = (matchQ && matchS) ? '' : 'none';
                    if (matchQ && matchS) vis++;
                });

                count.textContent = vis + ' of ' + rows.length + ' orders';
            };

            inp.addEventListener('input', runDashOrderFilter);
            sel.addEventListener('change', runDashOrderFilter);
            runDashOrderFilter();
        })();

        function viewOrder(orderId) {
            const orders = <?php echo json_encode($recentOrders); ?>;
            const order = orders.find(o => o.id == orderId);
            
            if (order) {
                const totalCost = parseFloat(order.estimated_cost || 0);
                const depositAmount = parseFloat(order.deposit_amount || 0);
                const depositPaid = parseFloat(order.deposit_paid || 0);
                const remainingBalance = totalCost - depositPaid;
                
                const html = `
                    <div style="background: #e8f4f8; padding: 20px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #3498db;">
                        <h4 style="margin: 0 0 10px 0; color: #2c3e50;">Order Status</h4>
                        <span class="status-badge status-${order.status}" style="font-size: 16px; padding: 8px 16px;">${order.status.replace(/_/g, ' ').toUpperCase()}</span>
                    </div>
                    
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                        <h4 style="margin: 0 0 10px 0; color: #2c3e50;">Order Information</h4>
                        <strong>Order Number:</strong> ${order.order_number}<br>
                        <strong>Order Date:</strong> ${new Date(order.created_at).toLocaleString('en-US', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })}<br>
                        <strong>Last Updated:</strong> ${new Date(order.updated_at).toLocaleString('en-US', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' })}
                    </div>
                    
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                        <h4 style="margin: 0 0 10px 0; color: #2c3e50;">Furniture Details</h4>
                        <strong>Furniture Type:</strong> ${order.furniture_type}<br>
                        <strong>Furniture Name:</strong> ${order.furniture_name}<br>
                        <strong>Dimensions (L × W × H):</strong> ${order.length} m × ${order.width} m × ${order.height} m<br>
                        <strong>Material:</strong> ${order.material}<br>
                        <strong>Color:</strong> ${order.color}<br>
                        ${order.design_description ? '<strong>Design Description:</strong> ' + order.design_description + '<br>' : ''}
                    </div>
                    
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                        <h4 style="margin: 0 0 10px 0; color: #2c3e50;">Payment Information</h4>
                        <strong>Total Cost:</strong> <span style="font-size: 18px; color: #e74c3c;">ETB ${totalCost.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span><br>
                        <strong>Deposit Amount (40%):</strong> ETB ${depositAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}<br>
                        ${depositPaid > 0 ? '<strong>Deposit Paid:</strong> <span style="color: #27ae60;">ETB ' + depositPaid.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</span><br>' : ''}
                        <strong>Remaining Balance:</strong> <span style="font-size: 18px; color: ${remainingBalance > 0 ? '#e74c3c' : '#27ae60'};">ETB ${remainingBalance.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                    </div>
                    
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                        <h4 style="margin: 0 0 10px 0; color: #2c3e50;">Customer Information</h4>
                        <strong>Name:</strong> ${order.customer_name || 'N/A'}<br>
                        <strong>Email:</strong> ${order.customer_email}
                    </div>
                    
                    ${order.customer_rating ? `
                    <div style="background: #fff8e1; padding: 15px; border-radius: 8px; margin-top: 15px; border: 2px solid #f39c12;">
                        <h4 style="margin: 0 0 10px 0; color: #e67e22;"><i class="fas fa-star"></i> Customer Rating & Suggestion</h4>
                        <div style="font-size: 22px; margin-bottom: 8px;">
                            ${'<i class="fas fa-star" style="color:#f39c12;"></i>'.repeat(parseInt(order.customer_rating))}${'<i class="fas fa-star" style="color:#ddd;"></i>'.repeat(5-parseInt(order.customer_rating))}
                            <strong style="font-size:15px; margin-left:6px;">${order.customer_rating}/5</strong>
                        </div>
                        ${order.customer_review ? `<div style="background:white; border-left:4px solid #f39c12; padding:10px 14px; border-radius:6px; font-style:italic; color:#2c3e50;">"${order.customer_review}"</div>` : '<p style="color:#aaa; margin:0; font-size:13px;">No written suggestion.</p>'}
                    </div>` : ''}
                `;
                document.getElementById('orderDetails').innerHTML = html;
                document.getElementById('viewModal').style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
        }
        
        function closeViewModal() {
            document.getElementById('viewModal').style.display = 'none';
            document.body.style.overflow = '';
        }
        
        // Close modal when clicking outside
        document.getElementById('viewModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeViewModal();
            }
        });
    </script>

    <!-- Mobile Menu Script -->
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
