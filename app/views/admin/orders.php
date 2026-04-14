<?php
// Admin authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}

// Get database connection
require_once __DIR__ . '/../../../config/db_config.php';

$adminName = $_SESSION['user_name'] ?? 'Admin User';

// Handle order actions
$message = '';
$messageType = '';

// Admin has no order action permissions - view only
// Manager handles all order approvals and workflow

// Fetch statistics
$stats = [
    'total_orders'     => 0,
    'pending_orders'   => 0,
    'in_production'    => 0,
    'completed_orders' => 0,
    'cancelled_orders' => 0,
    'low_stock_materials' => 0,
    'total_revenue'    => 0,
    'net_profit'       => 0,
];

try {
    $stats['total_orders']     = $pdo->query("SELECT COUNT(*) FROM furn_orders")->fetchColumn();
    $stats['pending_orders']   = $pdo->query("SELECT COUNT(*) FROM furn_orders WHERE status IN ('pending_review', 'pending_cost_approval')")->fetchColumn();
    $stats['in_production']    = $pdo->query("SELECT COUNT(*) FROM furn_orders WHERE status = 'in_production'")->fetchColumn();
    $stats['completed_orders'] = $pdo->query("SELECT COUNT(*) FROM furn_orders WHERE status = 'completed'")->fetchColumn();
    $stats['cancelled_orders'] = $pdo->query("SELECT COUNT(*) FROM furn_orders WHERE status = 'cancelled'")->fetchColumn();
    $stats['low_stock_materials'] = $pdo->query("SELECT COUNT(*) FROM furn_materials WHERE current_stock < minimum_stock")->fetchColumn();
    $stats['total_revenue']    = $pdo->query("SELECT COALESCE(SUM(estimated_cost), 0) FROM furn_orders WHERE status = 'completed'")->fetchColumn();
    try {
        $totalRev2 = floatval($pdo->query("SELECT COALESCE(SUM(amount),0) FROM furn_payments WHERE status IN ('approved','verified')")->fetchColumn());
        $totalMat2 = floatval($pdo->query("SELECT COALESCE(SUM(total_cost),0) FROM furn_order_materials")->fetchColumn());
        $totalPay2 = floatval($pdo->query("SELECT COALESCE(SUM(net_salary),0) FROM furn_payroll WHERE status='approved'")->fetchColumn());
        $stats['net_profit'] = $totalRev2 - $totalMat2 - $totalPay2 - ($totalRev2 * 0.10);
    } catch (PDOException $e) { $stats['net_profit'] = 0; }
} catch (PDOException $e) {
    error_log("Stats error: " . $e->getMessage());
}

// Fetch all orders
$orders = [];
try {
    $stmt = $pdo->query("
        SELECT o.*, CONCAT(u.first_name, ' ', u.last_name) as customer_name, u.email as customer_email,
               r.rating as customer_rating, r.review_text as customer_review
        FROM furn_orders o 
        LEFT JOIN furn_users u ON o.customer_id = u.id
        LEFT JOIN furn_ratings r ON r.order_id = o.id
        ORDER BY o.created_at DESC
    ");
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Orders fetch error: " . $e->getMessage());
}

$pageTitle = 'Orders Management';
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
    $pageTitle = 'Orders';
    include_once __DIR__ . '/../../includes/admin_header.php'; 
    ?>
        <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
            <span style="font-size: 20px;">🔨</span>
            <span style="font-weight: 700; font-size: 16px; color: white;"><span style="color: #e67e22;">Smart</span>Workshop</span>
            <span style="color: rgba(255,255,255,0.4); margin: 0 5px;">|</span>
            <span style="font-size: 14px; color: rgba(255,255,255,0.85);">Furniture ERP &nbsp;<strong style="color:white;">Orders Management</strong></span>
        </div>
        <div class="header-right" style="display: flex; align-items: center; gap: 15px;">
            <div class="system-status" style="background: #27AE60; color: white; padding: 5px 12px; border-radius: 20px; font-size: 12px; display: flex; align-items: center; gap: 6px;">
                <span style="width: 8px; height: 8px; background: white; border-radius: 50%; display: inline-block;"></span> Operational
            </div>
            <div style="position: relative; cursor: pointer;">
                <i class="fas fa-bell" style="font-size: 18px; color: rgba(255,255,255,0.85);"></i>
                <?php if($stats['pending_orders'] > 0): ?>
                <span style="position: absolute; top: -6px; right: -6px; background: #e74c3c; color: white; border-radius: 50%; width: 18px; height: 18px; font-size: 10px; display: flex; align-items: center; justify-content: center; font-weight: 700;"><?php echo $stats['pending_orders']; ?></span>
                <?php endif; ?>
            </div>
            <div class="admin-profile" style="display: flex; align-items: center; gap: 10px;">
                <div class="admin-avatar" style="background: #e67e22;"><?php echo strtoupper(substr($adminName, 0, 1)); ?></div>
                <div>
                    <div style="font-size: 13px; font-weight: 600; color: white;"><?php echo htmlspecialchars($adminName); ?></div>
                    <div class="admin-role-badge" style="background: #e67e22;">ADMIN</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <h2 style="margin-bottom: 30px; color: #2c3e50;">Orders Management</h2>
        
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['total_orders']); ?></div>
                <div class="stat-label">Total Orders</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['pending_orders']); ?></div>
                <div class="stat-label">Pending Approval</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['in_production']); ?></div>
                <div class="stat-label">In Production</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['completed_orders']); ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color:#E74C3C;"><?php echo number_format($stats['cancelled_orders']); ?></div>
                <div class="stat-label">Cancelled</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">ETB <?php echo number_format($stats['total_revenue'], 0); ?></div>
                <div class="stat-label">Revenue (Completed)</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color:<?php echo $stats['net_profit'] >= 0 ? '#27AE60' : '#E74C3C'; ?>">
                    ETB <?php echo number_format($stats['net_profit'], 0); ?>
                </div>
                <div class="stat-label">Net Profit</div>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="section-card">
            <div class="section-header">
                <div class="section-title"><i class="fas fa-box me-2"></i>All Orders</div>
            </div>

            <!-- Search & Filter Bar -->
            <div style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:18px;">
                <input type="text" id="searchInput" placeholder="Search order #, customer, furniture..." 
                    style="flex:1; min-width:200px; padding:9px 14px; border:1px solid #ddd; border-radius:8px; font-size:13px; outline:none;">
                <select id="statusFilter" style="padding:9px 14px; border:1px solid #ddd; border-radius:8px; font-size:13px; outline:none;">
                    <option value="">All Statuses</option>
                    <option value="pending_review">Pending Review</option>
                    <option value="pending_cost_approval">Pending Cost Approval</option>
                    <option value="waiting_for_deposit">Waiting for Deposit</option>
                    <option value="in_production">In Production</option>
                    <option value="payment_verified">Payment Verified</option>
                    <option value="ready_for_delivery">Ready for Delivery</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <button onclick="clearFilters()" style="padding:9px 16px; background:#f0f0f0; border:1px solid #ddd; border-radius:8px; font-size:13px; cursor:pointer;">
                    <i class="fas fa-times"></i> Clear
                </button>
                <span id="rowCount" style="padding:9px 0; font-size:13px; color:#7f8c8d; align-self:center;"></span>
            </div>
            
            <div class="table-responsive">
                <table class="data-table" id="ordersTable">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Furniture</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Rating</th>
                            <th>Suggestion</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="ordersBody">
                        <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                            <td>
                                <?php echo htmlspecialchars($order['customer_name'] ?: 'N/A'); ?><br>
                                <small style="color: #7f8c8d;"><?php echo htmlspecialchars($order['customer_email']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($order['furniture_name']); ?></td>
                            <td><strong>ETB <?php echo number_format($order['estimated_cost'], 2); ?></strong></td>
                            <td data-status="<?php echo $order['status']; ?>">
                                <span class="status-badge status-<?php echo $order['status']; ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $order['status'])); ?>
                                </span>
                            </td>
                            <td style="white-space: nowrap;">
                                <?php if (!empty($order['customer_rating'])): ?>
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star" style="color: <?php echo $i <= $order['customer_rating'] ? '#f39c12' : '#ddd'; ?>; font-size: 13px;"></i>
                                    <?php endfor; ?>
                                    <strong style="font-size: 12px; margin-left: 3px;"><?php echo $order['customer_rating']; ?>/5</strong>
                                <?php else: ?>
                                    <span style="color: #ccc;">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="max-width: 180px;">
                                <?php if (!empty($order['customer_review'])): ?>
                                    <span style="font-size: 12px; color: #e67e22; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($order['customer_review']); ?>">
                                        <i class="fas fa-comment-dots"></i> <?php echo htmlspecialchars($order['customer_review']); ?>
                                    </span>
                                <?php elseif (!empty($order['customer_rating'])): ?>
                                    <span style="color: #aaa; font-size: 12px;"><i class="fas fa-minus-circle"></i> No suggestion</span>
                                <?php else: ?>
                                    <span style="color: #ccc;">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                            <td>
                                <button class="btn-action btn-primary-custom" onclick="viewOrder(<?php echo $order['id'] ?? ''; ?>)">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
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
        // Search & filter logic
        (function() {
            const searchInput  = document.getElementById('searchInput');
            const statusFilter = document.getElementById('statusFilter');
            const tbody        = document.getElementById('ordersBody');
            const rowCount     = document.getElementById('rowCount');

            function applyFilters() {
                const q      = searchInput.value.toLowerCase().trim();
                const status = statusFilter.value;
                const rows   = tbody.querySelectorAll('tr');
                let visible  = 0;

                rows.forEach(row => {
                    const text       = row.textContent.toLowerCase();
                    const rowStatus  = (row.querySelector('[data-status]') || {}).dataset?.status || '';
                    const matchText  = !q || text.includes(q);
                    const matchStatus = !status || rowStatus === status;

                    if (matchText && matchStatus) {
                        row.style.display = '';
                        visible++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                rowCount.textContent = visible + ' of ' + rows.length + ' orders';
            }

            searchInput.addEventListener('input', applyFilters);
            statusFilter.addEventListener('change', applyFilters);
            applyFilters(); // init count

            window.clearFilters = function() {
                searchInput.value  = '';
                statusFilter.value = '';
                applyFilters();
            };
        })();

        function closeViewModal() {
            document.getElementById('viewModal').style.display = 'none';
            document.body.style.overflow = ''; // Restore scroll
        }

        function viewOrder(orderId) {
            // Find order data
            const orders = <?php echo json_encode($orders); ?>;
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
    </script>
    <!-- Mobile Menu Script -->
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
