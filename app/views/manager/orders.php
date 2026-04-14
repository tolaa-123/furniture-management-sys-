<?php
// Manager authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}

require_once __DIR__ . '/../../../config/db_config.php';

$managerName = $_SESSION['user_name'] ?? 'Manager User';
$message = '';
$messageType = '';

// Orders page is view-only — actions handled via cost_estimation page

// Fetch orders with filters
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$whereParts = ["1=1"];
$params = [];

if ($filter !== 'all') {
    $whereParts[] = "o.status = ?";
    $params[] = $filter;
}
if ($search !== '') {
    $whereParts[] = "(o.id LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereClause = "WHERE " . implode(" AND ", $whereParts);

$orders = [];
try {
    $stmt = $pdo->prepare("
        SELECT o.*, u.first_name, u.last_name, u.email, u.phone
        FROM furn_orders o
        LEFT JOIN furn_users u ON o.customer_id = u.id
        $whereClause
        ORDER BY o.created_at DESC
    ");
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Orders fetch error: " . $e->getMessage());
}

// Handle complaint resolve
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resolve_complaint') {
    try {
        $cid      = intval($_POST['complaint_id']);
        $response = trim($_POST['manager_response'] ?? '');
        $pdo->prepare("UPDATE furn_complaints SET status='resolved', manager_response=?, resolved_by=?, resolved_at=NOW() WHERE id=?")
            ->execute([$response, $_SESSION['user_id'], $cid]);
        $_SESSION['mgr_success'] = 'Complaint resolved.';
        header('Location: ' . BASE_URL . '/public/manager/orders'); exit();
    } catch (PDOException $e) { error_log($e->getMessage()); }
}

// Fetch all complaints keyed by order_id
$complaintsByOrder = [];
$openComplaintCount = 0;
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS furn_complaints (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        customer_id INT NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        status ENUM('open','resolved') NOT NULL DEFAULT 'open',
        manager_response TEXT DEFAULT NULL,
        resolved_by INT DEFAULT NULL,
        resolved_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(order_id), INDEX(status)
    )");
    $cs = $pdo->query("SELECT c.*, CONCAT(u.first_name,' ',u.last_name) as customer_name FROM furn_complaints c LEFT JOIN furn_users u ON c.customer_id=u.id ORDER BY c.status ASC, c.created_at DESC");
    foreach ($cs->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $complaintsByOrder[$c['order_id']][] = $c;
        if ($c['status'] === 'open') $openComplaintCount++;
    }
} catch (PDOException $e) {}

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
    <button class="mobile-menu-toggle" aria-label="Toggle Menu">
        <i class="fas fa-bars"></i>
    </button>
    <div class="sidebar-overlay"></div>

    <?php include_once __DIR__ . '/../../includes/manager_sidebar.php'; ?>

    <!-- Top Header -->
    <?php 
    $pageTitle = 'Orders';
    include_once __DIR__ . '/../../includes/manager_header.php'; 
    ?>
        <div class="header-left">
            <div class="system-status"><i class="fas fa-circle"></i> Workshop Manager</div>
        </div>
        <div class="header-right">
            <div class="admin-profile">
                <div class="admin-avatar"><?php echo strtoupper(substr($managerName, 0, 1)); ?></div>
                <div>
                    <div style="font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($managerName); ?></div>
                    <div class="admin-role-badge">MANAGER</div>
                </div>
            </div>
        </div>
    </div>

    <div class="main-content">
        <?php if (!empty($_SESSION['mgr_success'])): ?>
            <div style="padding:14px 18px;background:#d4edda;color:#155724;border-radius:8px;margin-bottom:20px;border:1px solid #c3e6cb;">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($_SESSION['mgr_success']); unset($_SESSION['mgr_success']); ?>
            </div>
        <?php endif; ?>
        <?php if ($openComplaintCount > 0): ?>
        <div style="padding:14px 18px;background:#fff3cd;color:#856404;border-radius:8px;margin-bottom:20px;border:1px solid #ffc107;display:flex;align-items:center;gap:12px;">
            <i class="fas fa-exclamation-triangle" style="font-size:20px;"></i>
            <span>There <?php echo $openComplaintCount === 1 ? 'is' : 'are'; ?> <strong><?php echo $openComplaintCount; ?> open complaint<?php echo $openComplaintCount > 1 ? 's' : ''; ?></strong> waiting for your response. Orders with complaints are highlighted in orange below.</span>
        </div>
        <?php endif; ?>

        <!-- Order Stats -->
        <?php
        $oStats = [];
        try {
            $oStats['pending']   = (int)$pdo->query("SELECT COUNT(*) FROM furn_orders WHERE status IN ('pending_review','pending_cost_approval')")->fetchColumn();
            $oStats['production']= (int)$pdo->query("SELECT COUNT(*) FROM furn_orders WHERE status IN ('in_production','production_started')")->fetchColumn();
            $oStats['delivery']  = (int)$pdo->query("SELECT COUNT(*) FROM furn_orders WHERE status='ready_for_delivery'")->fetchColumn();
            $oStats['completed'] = (int)$pdo->query("SELECT COUNT(*) FROM furn_orders WHERE status='completed'")->fetchColumn();
            $oStats['cancelled'] = (int)$pdo->query("SELECT COUNT(*) FROM furn_orders WHERE status='cancelled'")->fetchColumn();
        } catch(PDOException $e){}
        $oRevenue = 0;
        $oPendingPay = 0;
        try {
            $oRevenue    = floatval($pdo->query("SELECT COALESCE(SUM(amount),0) FROM furn_payments WHERE status IN ('approved','verified')")->fetchColumn());
            $oPendingPay = (int)$pdo->query("SELECT COUNT(*) FROM furn_payments WHERE status='pending'")->fetchColumn();
        } catch(PDOException $e){}
        $oCards = [
            [$oStats['pending']??0,   'Pending Approval',   '#F39C12','fa-clock',          '?filter=pending_cost_approval'],
            [$oStats['production']??0,'In Production',      '#3498DB','fa-industry',        '?filter=in_production'],
            [$oStats['delivery']??0,  'Ready for Delivery', '#1ABC9C','fa-truck',           '?filter=ready_for_delivery'],
            [$oStats['completed']??0, 'Completed Orders',   '#27AE60','fa-check-circle',    '?filter=completed'],
            [$oStats['cancelled']??0, 'Cancelled Orders',   '#E74C3C','fa-times-circle',    '?filter=cancelled'],
            ['ETB '.number_format($oRevenue,0), 'Total Revenue', '#27AE60','fa-money-bill-wave', BASE_URL.'/public/manager/payments'],
            [$oPendingPay,            'Pending Payments',   '#F39C12','fa-hourglass-half',  BASE_URL.'/public/manager/payments'],
        ];
        ?>
        <div class="stats-grid" style="margin-bottom:20px;">
            <?php foreach($oCards as [$v,$l,$c,$i,$href]): ?>
            <a href="<?php echo htmlspecialchars($href); ?>" style="text-decoration:none;">
                <div class="stat-card" style="border-left:4px solid <?php echo $c; ?>;cursor:pointer;transition:transform .15s,box-shadow .15s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,0.12)';" onmouseout="this.style.transform='';this.style.boxShadow='';">
                    <div style="display:flex;justify-content:space-between;align-items:start;">
                        <div><div class="stat-value" style="font-size:<?php echo strpos((string)$v,'ETB')!==false?'16px':'28px';?>;color:<?php echo $c; ?>"><?php echo $v; ?></div><div class="stat-label"><?php echo $l; ?></div></div>
                        <div style="font-size:28px;color:<?php echo $c; ?>;opacity:.25;"><i class="fas <?php echo $i; ?>"></i></div>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-box"></i> Orders Management</h2>
            </div>

            <!-- Filters -->
            <div style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
                <a href="?filter=all" class="btn-action <?php echo $filter === 'all' ? 'btn-primary-custom' : 'btn-secondary'; ?>">All Orders</a>
                <a href="?filter=pending_cost_approval" class="btn-action <?php echo $filter === 'pending_cost_approval' ? 'btn-warning-custom' : 'btn-secondary'; ?>">Pending Review</a>
                <a href="?filter=cost_estimated" class="btn-action <?php echo $filter === 'cost_estimated' ? 'btn-success-custom' : 'btn-secondary'; ?>">Cost Estimated</a>
                <a href="?filter=payment_verified" class="btn-action <?php echo $filter === 'payment_verified' ? 'btn-primary-custom' : 'btn-secondary'; ?>">Payment Verified</a>
                <a href="?filter=in_production" class="btn-action <?php echo $filter === 'in_production' ? 'btn-primary-custom' : 'btn-secondary'; ?>">In Production</a>
                <a href="?filter=completed" class="btn-action <?php echo $filter === 'completed' ? 'btn-success-custom' : 'btn-secondary'; ?>">Completed</a>
            </div>

            <!-- Search -->
            <form method="GET" style="margin-bottom: 20px;">
                <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                <div style="display: flex; gap: 10px;">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by order ID or customer name..." style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                    <button type="submit" class="btn-action btn-primary-custom"><i class="fas fa-search"></i> Search</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="data-table mobile-cards">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Contact</th>
                            <th>Furniture Type</th>
                            <th>Cost</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <?php
                            $orderComplaints = $complaintsByOrder[$order['id']] ?? [];
                            $openCount = count(array_filter($orderComplaints, fn($c) => $c['status'] === 'open'));
                            ?>
                            <tr style="<?php echo $openCount > 0 ? 'background:#fff8f0;border-left:4px solid #e67e22;' : ''; ?>">
                                <td>
                                    #<?php echo $order['id']; ?>
                                    <?php if ($openCount > 0): ?>
                                    <span style="background:#e67e22;color:white;border-radius:10px;padding:1px 7px;font-size:10px;font-weight:700;margin-left:4px;" title="<?php echo $openCount; ?> open complaint(s)">
                                        <i class="fas fa-exclamation-circle"></i> <?php echo $openCount; ?>
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($order['phone'] ?? $order['email']); ?></td>
                                <td><?php echo htmlspecialchars($order['furniture_type'] ?? 'Custom'); ?></td>
                                <td>ETB <?php echo number_format($order['estimated_cost'] ?? 0, 2); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo htmlspecialchars($order['status']); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                <td>
                                    <?php if (in_array($order['status'], ['pending_cost_approval', 'pending_review', 'pending'])): ?>
                                        <a href="<?php echo BASE_URL; ?>/public/manager/cost-estimation?order_id=<?php echo $order['id']; ?>" class="btn-action btn-warning-custom">
                                            <i class="fas fa-calculator"></i> Estimate Cost
                                        </a>
                                    <?php else: ?>
                                        <button class="btn-action btn-primary-custom" onclick="viewOrder(<?php echo htmlspecialchars(json_encode($order)); ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    <?php endif; ?>
                                    <?php
                                    $orderComplaints = $complaintsByOrder[$order['id']] ?? [];
                                    $openCount = count(array_filter($orderComplaints, fn($c) => $c['status'] === 'open'));
                                    ?>
                                    <?php if (!empty($orderComplaints)): ?>
                                        <button class="btn-action" style="background:<?php echo $openCount > 0 ? '#e67e22' : '#27ae60'; ?>;color:white;" onclick="viewComplaints(<?php echo htmlspecialchars(json_encode($orderComplaints)); ?>, <?php echo $order['id']; ?>)">
                                            <i class="fas fa-exclamation-circle"></i>
                                            <?php if ($openCount > 0): ?>
                                                <?php echo $openCount; ?> Open Complaint<?php echo $openCount > 1 ? 's' : ''; ?>
                                            <?php else: ?>
                                                Complaints (All Resolved)
                                            <?php endif; ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- View Order Modal -->
    <div id="viewOrderModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:white; border-radius:12px; padding:30px; max-width:550px; width:90%; max-height:90vh; overflow-y:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3 style="margin:0; color:#3D1F14;"><i class="fas fa-box"></i> Order Details</h3>
                <button onclick="document.getElementById('viewOrderModal').style.display='none'" style="background:none; border:none; font-size:24px; cursor:pointer;">&times;</button>
            </div>
            <div id="viewOrderContent"></div>
        </div>
    </div>

    <script>
        function viewOrder(order) {
            const statusColors = {
                'pending_review': '#f39c12', 'cost_estimated': '#3498db',
                'awaiting_deposit': '#9b59b6', 'deposit_paid': '#1abc9c',
                'payment_verified': '#27ae60', 'in_production': '#2980b9',
                'ready_for_delivery': '#16a085', 'completed': '#27ae60', 'cancelled': '#e74c3c'
            };
            const color = statusColors[order.status] || '#7f8c8d';
            document.getElementById('viewOrderContent').innerHTML = `
                <table style="width:100%; border-collapse:collapse; font-size:14px;">
                    <tr style="border-bottom:1px solid #eee;"><td style="padding:10px; color:#7f8c8d; width:40%;">Order ID</td><td style="padding:10px; font-weight:600;">#${order.id}</td></tr>
                    <tr style="border-bottom:1px solid #eee;"><td style="padding:10px; color:#7f8c8d;">Customer</td><td style="padding:10px; font-weight:600;">${order.first_name} ${order.last_name}</td></tr>
                    <tr style="border-bottom:1px solid #eee;"><td style="padding:10px; color:#7f8c8d;">Furniture Type</td><td style="padding:10px;">${order.furniture_type || 'Custom'}</td></tr>
                    <tr style="border-bottom:1px solid #eee;"><td style="padding:10px; color:#7f8c8d;">Estimated Cost</td><td style="padding:10px; font-weight:600;">ETB ${parseFloat(order.estimated_cost || 0).toFixed(2)}</td></tr>
                    <tr style="border-bottom:1px solid #eee;"><td style="padding:10px; color:#7f8c8d;">Status</td><td style="padding:10px;"><span style="background:${color}; color:white; padding:4px 10px; border-radius:12px; font-size:12px;">${order.status.replace(/_/g,' ')}</span></td></tr>
                    <tr style="border-bottom:1px solid #eee;"><td style="padding:10px; color:#7f8c8d;">Date</td><td style="padding:10px;">${order.created_at}</td></tr>
                    ${order.manager_notes ? `<tr><td style="padding:10px; color:#7f8c8d;">Notes</td><td style="padding:10px;">${order.manager_notes}</td></tr>` : ''}
                </table>
            `;
            document.getElementById('viewOrderModal').style.display = 'flex';
        }
    </script>

    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>

    <!-- Complaints Modal -->
    <div id="complaintsModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:14px;padding:28px;width:90%;max-width:600px;max-height:90vh;overflow-y:auto;box-shadow:0 8px 30px rgba(0,0,0,.2);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                <h4 style="margin:0;color:#e67e22;"><i class="fas fa-exclamation-circle me-2"></i>Customer Complaints</h4>
                <button onclick="document.getElementById('complaintsModal').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;">&times;</button>
            </div>
            <div id="complaintsContent"></div>
        </div>
    </div>

    <script>
    let currentComplaintOrderId = null;
    function viewComplaints(complaints, orderId) {
        currentComplaintOrderId = orderId;
        let html = '';
        complaints.forEach(c => {
            const isResolved = c.status === 'resolved';
            const borderColor = isResolved ? '#27ae60' : '#e67e22';
            const bgColor = isResolved ? '#f0fff4' : '#fff8f0';
            const badgeBg = isResolved ? '#27ae60' : '#e67e22';
            const badgeText = isResolved ? 'Resolved' : 'Open';
            const date = new Date(c.created_at).toLocaleDateString('en-US', {year:'numeric',month:'short',day:'numeric'});

            html += `
            <div style="background:${bgColor};border:1.5px solid ${borderColor};border-radius:10px;padding:16px;margin-bottom:16px;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
                    <strong style="color:${borderColor};font-size:14px;">${c.subject}</strong>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <span style="background:${badgeBg};color:white;padding:2px 10px;border-radius:10px;font-size:11px;font-weight:700;">${badgeText}</span>
                        <small style="color:#aaa;">${date}</small>
                    </div>
                </div>
                <p style="margin:0 0 8px;font-size:13px;color:#444;line-height:1.6;">${c.message.replace(/\n/g,'<br>')}</p>
                <div style="font-size:12px;color:#888;margin-bottom:12px;">From: <strong>${c.customer_name}</strong></div>
                ${isResolved && c.manager_response ? `
                <div style="background:#fff;border:1px solid #c3e6cb;border-radius:8px;padding:12px;margin-bottom:10px;">
                    <div style="font-size:11px;font-weight:700;color:#27ae60;text-transform:uppercase;margin-bottom:5px;"><i class="fas fa-reply"></i> Your Response</div>
                    <p style="margin:0;font-size:13px;color:#444;line-height:1.6;">${c.manager_response.replace(/\n/g,'<br>')}</p>
                </div>` : ''}
                ${!isResolved ? `
                <div>
                    <label style="font-size:12px;font-weight:600;color:#555;display:block;margin-bottom:5px;">Your Response (optional)</label>
                    <textarea id="resp-${c.id}" rows="3" placeholder="Write a response to the customer..." style="width:100%;padding:8px;border:1.5px solid #e9ecef;border-radius:7px;font-family:inherit;font-size:13px;resize:vertical;box-sizing:border-box;"></textarea>
                    <button onclick="resolveComplaint(${c.id})" style="margin-top:8px;padding:8px 20px;background:#27AE60;color:white;border:none;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;">
                        <i class="fas fa-check me-1"></i>Mark Resolved
                    </button>
                </div>` : `<div style="font-size:12px;color:#27ae60;"><i class="fas fa-check-circle me-1"></i>Resolved on ${c.resolved_at ? new Date(c.resolved_at).toLocaleDateString('en-US',{year:'numeric',month:'short',day:'numeric'}) : ''}</div>`}
            </div>`;
        });
        document.getElementById('complaintsContent').innerHTML = html || '<p style="color:#aaa;text-align:center;">No complaints found.</p>';
        document.getElementById('complaintsModal').style.display = 'flex';
    }

    function resolveComplaint(complaintId) {
        const respEl = document.getElementById('resp-' + complaintId);
        const response = respEl ? respEl.value.trim() : '';
        const btn = document.querySelector(`button[onclick="resolveComplaint(${complaintId})"]`);
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...'; }
        const fd = new FormData();
        fd.append('complaint_id', complaintId);
        fd.append('manager_response', response);
        fetch('<?php echo BASE_URL; ?>/public/api/resolve_complaint.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('complaintsModal').style.display = 'none';
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Could not resolve complaint.'));
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check me-1"></i>Mark Resolved'; }
            }
        })
        .catch(() => {
            alert('Network error. Please try again.');
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-check me-1"></i>Mark Resolved'; }
        });
    }

    document.getElementById('complaintsModal').addEventListener('click', function(e) {
        if (e.target === this) this.style.display = 'none';
    });
    </script>
</body>
</html>
