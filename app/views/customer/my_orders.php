<?php
// Session and authentication already handled by index.php
// The user_id in session is from furn_users table (not users table)
$customerId = $_SESSION['user_id'] ?? 0;
$customerName = $_SESSION['first_name'] ?? $_SESSION['user_name'] ?? 'Customer';

// Get database connection
require_once __DIR__ . '/../../../config/db_config.php';

// Ensure required columns exist
if ($customerId > 0) {
    try {
        $pdo->exec("ALTER TABLE furn_orders 
            ADD COLUMN IF NOT EXISTS estimated_cost DECIMAL(12,2) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS deposit_amount DECIMAL(12,2) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS remaining_balance DECIMAL(12,2) DEFAULT NULL");
        
        // Fix empty/null status for this customer's orders
        $pdo->prepare("UPDATE furn_orders SET status = 'pending_review' WHERE customer_id = ? AND (status IS NULL OR status = '')")
            ->execute([$customerId]);
    } catch (PDOException $e) {
        error_log("ALTER TABLE warning: " . $e->getMessage());
    }
}

// Query orders with actual approved payment totals
$orders = [];
if ($customerId > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                o.id, o.customer_id, o.order_number, o.furniture_type, o.furniture_name,
                o.length, o.width, o.height, o.material, o.color,
                o.design_description, o.design_image, o.special_notes,
                COALESCE(o.estimated_cost, o.total_amount, 0) as total_amount,
                COALESCE(o.deposit_amount, o.total_amount * 0.4, 0) as deposit_amount,
                COALESCE(o.remaining_balance, o.total_amount, 0) as remaining_balance,
                o.status, o.created_at, o.updated_at,
                COALESCE(SUM(CASE WHEN p.status IN ('approved','verified') THEN p.amount ELSE 0 END), 0) as amount_paid
            FROM furn_orders o
            LEFT JOIN furn_payments p ON p.order_id = o.id
            WHERE o.customer_id = ?
            GROUP BY o.id
            ORDER BY o.created_at DESC
        ");
        $stmt->execute([$customerId]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching orders: " . $e->getMessage());
        $orders = [];
    }
}

$pageTitle = 'My Orders';

// Fetch all complaints for this customer keyed by order_id
$complaintsByOrder = [];
if ($customerId > 0) {
    try {
        $stmtC = $pdo->prepare("SELECT * FROM furn_complaints WHERE customer_id = ? ORDER BY created_at DESC");
        $stmtC->execute([$customerId]);
        foreach ($stmtC->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $complaintsByOrder[$c['order_id']][] = $c;
        }
    } catch (PDOException $e) {}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title><?php echo $pageTitle; ?> - FurnitureCraft</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/css/admin-responsive.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .orders-header { background: linear-gradient(135deg, #8B4513, #5D4037); color: #fff; border-radius: 12px; padding: 20px; margin-bottom: 25px; box-shadow: 0 8px 25px rgba(139, 69, 19, 0.2); }
        .orders-table-card { background: #fff; border-radius: 12px; box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1); overflow: hidden; }
        .table-header { background: linear-gradient(135deg, #8B4513, #5D4037); color: #fff; padding: 15px 20px; }
        .orders-table { margin-bottom: 0; width: 100%; }
        .orders-table thead th { background: #f8f4f0; color: #5D4037; font-weight: 600; border-bottom: 2px solid #8B4513; padding: 15px; }
        .orders-table tbody tr { transition: all 0.3s; cursor: pointer; }
        .orders-table tbody tr:hover { background: #FFF8F0; transform: scale(1.01); }
        .status-pill { padding: 5px 12px; border-radius: 20px; font-size: 0.82rem; font-weight: 600; display: inline-block; }
        .status-pending_review, .status-pending_cost_approval { background: #fff3cd; color: #856404; }
        .status-cost_estimated { background: #ffeeba; color: #7d4e00; }
        .status-waiting_for_deposit, .status-awaiting_deposit { background: #ffe5b4; color: #7d4e00; }
        .status-deposit_paid { background: #b8e994; color: #155724; }
        .status-payment_verified { background: #c3e6cb; color: #155724; }
        .status-in_production { background: #ffd59e; color: #b36b00; }
        .status-ready_for_delivery { background: #d1ecf1; color: #0c5460; }
        .status-final_payment_paid { background: #bee5eb; color: #0c5460; }
        .status-awaiting_final_payment { background: #cce5ff; color: #004085; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .stats-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); transition: transform 0.3s; }
        .stats-card:hover { transform: translateY(-5px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .empty-state { text-align: center; padding: 60px 20px; color: #6c757d; }
        .empty-state i { font-size: 4rem; color: #dee2e6; margin-bottom: 20px; }
        .btn-action { margin: 2px; }
        .btn-track { background: #17a2b8; color: white; }
        .btn-track:hover { background: #138496; color: white; }
        .btn-pay { background: #28a745; color: white; }
        .btn-pay:hover { background: #218838; color: white; }
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
    <?php include_once __DIR__ . '/../../includes/customer_sidebar.php'; ?>

    <!-- Top Header -->
    <?php 
    $pageTitle = 'My Orders';
    include_once __DIR__ . '/../../includes/customer_header.php'; 
    ?>

    <!-- Main Content -->
    <div class="main-content">
        <h2 style="margin-bottom: 30px; color: #2c3e50;">My Orders</h2>
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
            <div style="color: #7f8c8d;">View and manage all your furniture orders</div>
            <a href="<?php echo BASE_URL; ?>/public/customer/create_order" class="btn-action btn-primary-custom">
                <i class="fas fa-plus-circle me-2"></i>Create New Order
            </a>
        </div>
            <?php
            $totalOrders = count($orders ?? []);
            $pendingDeposit = 0;
            $inProduction = 0;
            $completed = 0;
            if (!empty($orders)) {
                foreach ($orders as $order) {
                    $s = $order['status'] ?? '';
                    if (in_array($s, ['pending_review', 'pending_cost_approval', 'cost_estimated', 'waiting_for_deposit', 'awaiting_deposit'])) {
                        $pendingDeposit++;
                    }
                    if (in_array($s, ['payment_verified', 'deposit_paid', 'in_production', 'ready_for_delivery', 'awaiting_final_payment'])) {
                        $inProduction++;
                    }
                    if (in_array($s, ['completed', 'final_payment_paid'])) {
                        $completed++;
                    }
                }
            }
            ?>
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(52, 152, 219, 0.1); display: flex; align-items: center; justify-content: center; color: #3498db; font-size: 24px;">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div>
                            <div style="font-size: 24px; font-weight: 700; color: #2c3e50;"><?php echo $totalOrders; ?></div>
                            <div style="font-size: 13px; color: #7f8c8d;">Total Orders</div>
                        </div>
                    </div>
                </div>
                <div class="stat-card">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(243, 156, 18, 0.1); display: flex; align-items: center; justify-content: center; color: #f39c12; font-size: 24px;">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div>
                            <div style="font-size: 24px; font-weight: 700; color: #2c3e50;"><?php echo $pendingDeposit; ?></div>
                            <div style="font-size: 13px; color: #7f8c8d;">Pending Payment</div>
                        </div>
                    </div>
                </div>
                <div class="stat-card">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(52, 152, 219, 0.1); display: flex; align-items: center; justify-content: center; color: #3498db; font-size: 24px;">
                            <i class="fas fa-hammer"></i>
                        </div>
                        <div>
                            <div style="font-size: 24px; font-weight: 700; color: #2c3e50;"><?php echo $inProduction; ?></div>
                            <div style="font-size: 13px; color: #7f8c8d;">In Production</div>
                        </div>
                    </div>
                </div>
                <div class="stat-card">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div style="width: 50px; height: 50px; border-radius: 12px; background: rgba(39, 174, 96, 0.1); display: flex; align-items: center; justify-content: center; color: #27ae60; font-size: 24px;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div>
                            <div style="font-size: 24px; font-weight: 700; color: #2c3e50;"><?php echo $completed; ?></div>
                            <div style="font-size: 13px; color: #7f8c8d;">Completed</div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Orders Table -->
            <div class="section-card">
                <div class="section-title"><i class="fas fa-list me-2"></i>Order History</div>
                <?php if (empty($orders)): ?>
                    <div style="text-align: center; padding: 60px 20px; color: #7f8c8d;">
                        <i class="fas fa-shopping-cart" style="font-size: 4rem; color: #dee2e6; margin-bottom: 20px; display: block;"></i>
                        <h4>No Orders Yet</h4>
                        <p style="color: #7f8c8d;">You haven't placed any orders. Start by creating your first custom furniture order.</p>
                        <a href="<?php echo BASE_URL; ?>/public/customer/create_order" class="btn-action btn-primary-custom" style="display: inline-block; margin-top: 15px;">
                            <i class="fas fa-plus-circle me-2"></i>Create Your First Order
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Payment Status Filter -->
                    <div style="padding:14px 20px;border-bottom:1px solid #f0f0f0;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                        <label style="font-size:13px;font-weight:600;color:#555;white-space:nowrap;"><i class="fas fa-filter me-1"></i>Filter by Payment Status:</label>
                        <select id="paymentStatusFilter" onchange="filterOrders()" style="padding:7px 12px;border:1.5px solid #ddd;border-radius:8px;font-size:13px;font-family:inherit;outline:none;min-width:200px;">
                            <option value="">All Payment Statuses</option>
                            <option value="Pending">Pending</option>
                            <option value="Deposit Paid">Deposit Paid</option>
                            <option value="Fully Paid">Fully Paid</option>
                            <option value="Final Payment Required">Final Payment Required</option>
                            <option value="Final Payment Under Review">Final Payment Under Review</option>
                            <option value="N/A">N/A (Cancelled)</option>
                        </select>
                        <button onclick="document.getElementById('paymentStatusFilter').value='';filterOrders();" style="padding:7px 12px;background:#f0f0f0;border:1.5px solid #ddd;border-radius:8px;font-size:13px;cursor:pointer;font-family:inherit;"><i class="fas fa-times"></i> Clear</button>
                        <span id="filterCount" style="font-size:12px;color:#7f8c8d;"></span>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table" id="ordersTable" aria-label="Order History">
                            <thead>
                                <tr>
                                    <th scope="col">Order Number</th>
                                    <th scope="col">Furniture Name</th>
                                    <th scope="col">Order Date</th>
                                    <th scope="col">Total Cost</th>
                                    <th scope="col">Payment Status</th>
                                    <th scope="col">Order Status</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <?php
                                    $orderId = $order['id'];
                                    $orderNumber = htmlspecialchars($order['order_number'] ?? 'N/A');
                                    $furnitureName = htmlspecialchars($order['furniture_name'] ?? $order['furniture_type'] ?? 'N/A');
                                    $orderDate = date('M j, Y', strtotime($order['created_at']));
                                    $totalAmount = floatval($order['total_amount'] ?? 0);
                                    $amountPaid = floatval($order['amount_paid'] ?? 0);
                                    $status = $order['status'] ?? '';
                                    if (empty($status)) {
                                        $status = 'pending_review';
                                    }
                                    $statusDisplay = ucwords(str_replace('_', ' ', $status));
                                    $statusClass = 'status-' . $status;

                                    // Payment status based on actual approved payments vs total
                                    if ($status === 'cancelled') {
                                        $paymentStatus = 'N/A';
                                        $paymentBadgeColor = '#6c757d';
                                    } elseif ($status === 'completed') {
                                        $paymentStatus = 'Fully Paid';
                                        $paymentBadgeColor = '#28a745';
                                    } elseif ($amountPaid >= $totalAmount && $totalAmount > 0) {
                                        $paymentStatus = 'Fully Paid';
                                        $paymentBadgeColor = '#28a745';
                                    } elseif ($status === 'final_payment_paid') {
                                        $paymentStatus = 'Final Payment Under Review';
                                        $paymentBadgeColor = '#17a2b8';
                                    } elseif ($status === 'awaiting_final_payment') {
                                        $paymentStatus = 'Final Payment Required';
                                        $paymentBadgeColor = '#fd7e14';
                                    } elseif ($amountPaid > 0) {
                                        $paymentStatus = 'Deposit Paid — ETB ' . number_format($amountPaid, 2);
                                        $paymentBadgeColor = '#17a2b8';
                                    } else {
                                        $paymentStatus = 'Pending';
                                        $paymentBadgeColor = '#dc3545';
                                    }

                                    // Action buttons
                                    $actionButtons = '';
                                    
                                    // Edit button (only for pending orders before cost approval)
                                    if (in_array($status, ['pending_review', 'pending_cost_approval'])) {
                                        $actionButtons .= '<a href="' . BASE_URL . '/public/customer/edit-order?id=' . $orderId . '" class="btn-action btn-warning-custom" style="font-size: 12px; padding: 6px 12px; background: #ffc107; color: #000;"><i class="fas fa-edit me-1"></i>Edit</a> ';
                                    }
                                    
                                    $actionButtons .= '<a href="' . BASE_URL . '/public/customer/order-details?id=' . $orderId . '" class="btn-action btn-primary-custom" style="font-size: 12px; padding: 6px 12px;"><i class="fas fa-eye me-1"></i>View</a>';

                                    if (in_array($status, ['deposit_paid', 'payment_verified', 'in_production', 'ready_for_delivery', 'completed'])) {
                                        $actionButtons .= ' <a href="' . BASE_URL . '/public/customer/order-tracking?id=' . $orderId . '" class="btn-action btn-success-custom" style="font-size: 12px; padding: 6px 12px;"><i class="fas fa-chart-line me-1"></i>Track</a>';
                                    }

                                    if ($status === 'ready_for_delivery') {
                                        $actionButtons .= ' <a href="' . BASE_URL . '/public/customer/pay-remaining?order_id=' . $orderId . '" class="btn-action btn-success-custom" style="font-size: 12px; padding: 6px 12px; background: #e74c3c;"><i class="fas fa-credit-card me-1"></i>Pay Final</a>';
                                    } elseif ($status === 'cost_estimated') {
                                        $actionButtons .= ' <a href="' . BASE_URL . '/public/customer/pay-deposit?order_id=' . $orderId . '" class="btn-action btn-success-custom" style="font-size: 12px; padding: 6px 12px;"><i class="fas fa-credit-card me-1"></i>Pay Deposit</a>';
                                    }

                                    if (in_array($status, ['pending_cost_approval', 'pending_review'])) {
                                        $actionButtons .= '
                                        <form method="POST" action="' . BASE_URL . '/public/api/cancel_order.php" style="display:inline;" onsubmit="return confirm(\'Cancel this order? This cannot be undone.\')">
                                            <input type="hidden" name="order_id" value="' . $orderId . '">
                                            <input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? '') . '">
                                            <button type="submit" class="btn-action btn-danger-custom" style="font-size: 12px; padding: 6px 12px;"><i class="fas fa-times me-1"></i>Cancel</button>
                                        </form>';
                                    }

                                    // Complaint button — always visible
                                    $orderComplaints = $complaintsByOrder[$orderId] ?? [];
                                    $openCount = count(array_filter($orderComplaints, fn($c) => $c['status'] === 'open'));
                                    $hasResponse = count(array_filter($orderComplaints, fn($c) => !empty($c['manager_response']))) > 0;
                                    $btnBg = $openCount > 0 ? '#e67e22' : ($hasResponse ? '#27ae60' : '#e67e22');
                                    $complaintsJson = htmlspecialchars(json_encode($orderComplaints), ENT_QUOTES);
                                    $actionButtons .= ' <button onclick="openComplaintModal(' . $orderId . ', \'' . htmlspecialchars(addslashes($orderNumber)) . '\', JSON.parse(this.dataset.complaints))" data-complaints="' . $complaintsJson . '" class="btn-action" style="font-size:12px;padding:6px 12px;background:' . $btnBg . ';color:white;border:none;border-radius:6px;cursor:pointer;"><i class="fas fa-exclamation-circle me-1"></i>Complaint' . ($openCount > 0 ? ' (' . $openCount . ')' : '') . '</button>';
                                    ?>
                                    <tr data-payment="<?php echo htmlspecialchars($paymentStatus); ?>">
                                        <td><strong><?php echo $orderNumber; ?></strong></td>
                                        <td><?php echo $furnitureName; ?></td>
                                        <td><?php echo $orderDate; ?></td>
                                        <td><strong>ETB <?php echo number_format($totalAmount, 2); ?></strong></td>
                                        <td><span style="background: <?php echo $paymentBadgeColor; ?>; color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;"><?php echo $paymentStatus; ?></span></td>
                                        <td><span class="status-badge status-<?php echo htmlspecialchars($status); ?>"><?php echo $statusDisplay; ?></span></td>
                                        <td><?php echo $actionButtons; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Mobile Menu Script -->
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>

    <!-- Complaint Modal -->
    <div id="complaintModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:14px;padding:28px;width:90%;max-width:560px;max-height:90vh;overflow-y:auto;box-shadow:0 8px 30px rgba(0,0,0,.2);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                <h4 style="margin:0;color:#e67e22;"><i class="fas fa-exclamation-circle me-2"></i>Complaints</h4>
                <button onclick="document.getElementById('complaintModal').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;">&times;</button>
            </div>
            <p id="complaintOrderLabel" style="font-size:13px;color:#888;margin:0 0 18px;"></p>

            <!-- Existing complaints list -->
            <div id="existingComplaints"></div>

            <!-- Divider -->
            <div style="border-top:1.5px solid #f0f0f0;margin:18px 0;"></div>

            <!-- Submit new complaint -->
            <div style="font-size:14px;font-weight:600;color:#555;margin-bottom:12px;"><i class="fas fa-plus me-1"></i>Submit a New Complaint</div>
            <form id="complaintForm">
                <input type="hidden" name="order_id" id="complaintOrderId">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? ''); ?>">
                <div style="margin-bottom:12px;">
                    <input type="text" name="subject" required placeholder="Subject — brief description of your issue..." style="width:100%;padding:10px;border:1.5px solid #ddd;border-radius:8px;font-size:13px;box-sizing:border-box;">
                </div>
                <div style="margin-bottom:14px;">
                    <textarea name="message" required rows="3" placeholder="Describe your complaint in detail..." style="width:100%;padding:10px;border:1.5px solid #ddd;border-radius:8px;font-size:13px;resize:vertical;box-sizing:border-box;font-family:inherit;"></textarea>
                </div>
                <div id="complaintMsg" style="display:none;margin-bottom:12px;padding:10px;border-radius:8px;font-size:13px;"></div>
                <div style="display:flex;gap:10px;justify-content:flex-end;">
                    <button type="button" onclick="document.getElementById('complaintModal').style.display='none'" style="padding:9px 18px;background:#6c757d;color:white;border:none;border-radius:8px;font-size:13px;cursor:pointer;">Cancel</button>
                    <button type="submit" id="complaintSubmitBtn" style="padding:9px 20px;background:#e67e22;color:white;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;"><i class="fas fa-paper-plane me-1"></i>Submit</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openComplaintModal(orderId, orderNumber, complaints) {
        document.getElementById('complaintOrderId').value = orderId;
        document.getElementById('complaintOrderLabel').textContent = 'Order: ' + orderNumber;
        document.getElementById('complaintMsg').style.display = 'none';
        document.getElementById('complaintForm').reset();
        document.getElementById('complaintOrderId').value = orderId;

        // Render existing complaints
        let html = '';
        if (complaints && complaints.length > 0) {
            complaints.forEach(c => {
                const isResolved = c.status === 'resolved';
                const borderColor = isResolved ? '#27ae60' : '#e67e22';
                const bg = isResolved ? '#f0fff4' : '#fff8f0';
                const date = new Date(c.created_at).toLocaleDateString('en-US', {year:'numeric',month:'short',day:'numeric'});
                html += `
                <div style="background:${bg};border:1.5px solid ${borderColor};border-radius:10px;padding:14px;margin-bottom:12px;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:6px;">
                        <strong style="color:${borderColor};font-size:13px;">${c.subject}</strong>
                        <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;margin-left:8px;">
                            <span style="background:${borderColor};color:white;padding:2px 9px;border-radius:10px;font-size:11px;font-weight:700;">${isResolved ? 'Resolved' : 'Open'}</span>
                            <small style="color:#aaa;">${date}</small>
                        </div>
                    </div>
                    <p style="margin:0 0 6px;font-size:13px;color:#555;line-height:1.5;">${c.message.replace(/\n/g,'<br>')}</p>
                    ${c.manager_response ? `
                    <div style="margin-top:10px;background:#fff;border:1px solid #c3e6cb;border-radius:8px;padding:10px;">
                        <div style="font-size:11px;font-weight:700;color:#27ae60;text-transform:uppercase;margin-bottom:5px;"><i class="fas fa-reply me-1"></i>Manager Response</div>
                        <p style="margin:0;font-size:13px;color:#333;line-height:1.5;">${c.manager_response.replace(/\n/g,'<br>')}</p>
                        ${c.resolved_at ? `<small style="color:#aaa;display:block;margin-top:6px;"><i class="fas fa-check-circle me-1"></i>Resolved on ${new Date(c.resolved_at).toLocaleDateString('en-US',{year:'numeric',month:'short',day:'numeric'})}</small>` : ''}
                    </div>` : `<small style="color:#aaa;font-style:italic;">Awaiting manager response...</small>`}
                </div>`;
            });
        } else {
            html = '<p style="color:#aaa;font-size:13px;margin:0 0 4px;">No complaints submitted yet for this order.</p>';
        }
        document.getElementById('existingComplaints').innerHTML = html;
        document.getElementById('complaintModal').style.display = 'flex';
    }

    document.getElementById('complaintForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = document.getElementById('complaintSubmitBtn');
        const msg = document.getElementById('complaintMsg');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Submitting...';
        fetch('<?php echo BASE_URL; ?>/public/api/submit_complaint.php', {
            method: 'POST', body: new FormData(this)
        })
        .then(r => r.json())
        .then(data => {
            msg.style.display = 'block';
            if (data.success) {
                msg.style.background = '#d4edda'; msg.style.color = '#155724';
                msg.innerHTML = '<i class="fas fa-check-circle me-1"></i>Complaint submitted. The manager will respond shortly.';
                setTimeout(() => location.reload(), 1500);
            } else {
                msg.style.background = '#f8d7da'; msg.style.color = '#721c24';
                msg.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i>' + data.message;
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Submit';
            }
        });
    });
    document.getElementById('complaintModal').addEventListener('click', function(e) {
        if (e.target === this) this.style.display = 'none';
    });
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('form[method="POST"]').forEach(function(form) {
            form.addEventListener('submit', function() {
                var btn = form.querySelector('button[type="submit"]');
                if (btn) setButtonLoading(btn, true);
            });
        });
        filterOrders();
    });

    function filterOrders() {
        const filter = document.getElementById('paymentStatusFilter').value.toLowerCase().trim();
        const rows = document.querySelectorAll('#ordersTable tbody tr');
        let visible = 0;
        rows.forEach(function(row) {
            const payment = (row.getAttribute('data-payment') || '').toLowerCase();
            const match = !filter || payment.startsWith(filter);
            row.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        const countEl = document.getElementById('filterCount');
        if (countEl) {
            countEl.textContent = visible + ' of ' + rows.length + ' orders';
        }
    }
    </script>
</body>
</html>
