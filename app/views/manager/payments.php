<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}
require_once __DIR__ . '/../../../config/db_config.php';
$csrf_token = $_SESSION['csrf_token'] ?? null;
if (!$csrf_token) {
    $csrf_token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrf_token;
}
$managerName = $_SESSION['user_name'] ?? 'Manager User';

// Handle payment verification actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error_message'] = 'Invalid CSRF token.';
        header('Location: ' . BASE_URL . '/public/manager/payments');
        exit();
    }
    if (isset($_POST['verify_payment'])) {
        $paymentId = intval($_POST['payment_id']);
        $action = $_POST['action']; // 'approve' or 'reject'
        $notes = trim($_POST['notes'] ?? '');
        
        try {
            if ($action === 'approve') {
                // Use 'verified' status (consistent with verify_payment.php API)
                $stmt = $pdo->prepare("UPDATE furn_payments SET status = 'verified', verified_by = ?, verified_at = NOW() WHERE payment_id = ?");
                $stmt->execute([$_SESSION['user_id'], $paymentId]);
                
                // Get payment details
                $stmt = $pdo->prepare("SELECT * FROM furn_payments WHERE payment_id = ?");
                $stmt->execute([$paymentId]);
                $payment = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($payment) {
                    $isDeposit = in_array($payment['payment_type'], ['deposit', 'prepayment']);
                    $isFinal   = in_array($payment['payment_type'], ['final', 'remaining', 'final_payment', 'postpayment', 'full_payment']);
                    
                    if ($isDeposit) {
                        try {
                            $pdo->prepare("UPDATE furn_orders SET status = 'payment_verified', deposit_paid = ?, remaining_balance = COALESCE(estimated_cost, total_amount, 0) - ? WHERE id = ?")
                                ->execute([$payment['amount'], $payment['amount'], $payment['order_id']]);
                        } catch (PDOException $e2) {
                            $pdo->prepare("UPDATE furn_orders SET status = 'payment_verified', deposit_paid = ? WHERE id = ?")
                                ->execute([$payment['amount'], $payment['order_id']]);
                        }
                    } elseif ($isFinal) {
                        $pdo->prepare("UPDATE furn_orders SET status = 'completed' WHERE id = ?")
                            ->execute([$payment['order_id']]);
                    }

                    // Notify customer
                    require_once __DIR__ . '/../../includes/notification_helper.php';
                    $ptLabel = $isDeposit ? 'Deposit' : 'Final Payment';
                    $orderStmt = $pdo->prepare("SELECT order_number FROM furn_orders WHERE id = ?");
                    $orderStmt->execute([$payment['order_id']]);
                    $orderNum = $orderStmt->fetchColumn() ?: '#'.$payment['order_id'];
                    insertNotification($pdo, $payment['customer_id'], 'payment', $ptLabel . ' Payment Approved',
                        'Your ' . strtolower($ptLabel) . ' payment for order ' . $orderNum . ' has been approved.',
                        $payment['order_id'], '/customer/my-orders', 'high');
                }
                
                $_SESSION['success_message'] = "Payment approved successfully!";
            } else {
                $stmt = $pdo->prepare("UPDATE furn_payments SET status = 'rejected' WHERE payment_id = ?");
                $stmt->execute([$paymentId]);
                $_SESSION['success_message'] = "Payment rejected.";
            }
            
            header('Location: ' . BASE_URL . '/public/manager/payments');
            exit();
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Error processing payment: " . $e->getMessage();
        }
    }
}

// Fetch pending payments
$pendingPayments = [];
$verifiedPayments = [];
$rejectedPayments = [];

// Pending payments
try {
    $stmt = $pdo->query("
        SELECT p.payment_id, p.order_id, p.customer_id, p.amount, p.payment_type,
               p.payment_method, COALESCE(p.receipt_image, p.receipt_file) as receipt_file,
               p.transaction_reference, p.bank_name, p.payment_date, p.status,
               p.created_at,
               o.furniture_type, o.furniture_name, o.order_number,
               COALESCE(o.estimated_cost, o.total_amount, 0) as order_total,
               o.status as order_status,
               COALESCE(o.deposit_paid, 0) as deposit_paid,
               COALESCE(o.remaining_balance, 0) as remaining_balance,
               CONCAT(u.first_name, ' ', u.last_name) as customer_name,
               u.email as customer_email, u.phone as customer_phone,
               COALESCE(prev_paid.total_prev, 0) as already_paid
        FROM furn_payments p
        LEFT JOIN furn_orders o ON p.order_id = o.id
        LEFT JOIN furn_users u ON p.customer_id = u.id
        LEFT JOIN (
            SELECT order_id, SUM(amount) as total_prev
            FROM furn_payments
            WHERE status IN ('approved','verified')
            GROUP BY order_id
        ) prev_paid ON prev_paid.order_id = p.order_id
        WHERE p.status = 'pending'
        ORDER BY p.created_at DESC
    ");
    $pendingPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Pending payments error: " . $e->getMessage());
    $pendingPayments = [];
    $debugError = $e->getMessage();
}

// Approved payments
try {
    $stmt = $pdo->query("
        SELECT p.payment_id, p.order_id, p.customer_id, p.amount, p.payment_type,
               p.payment_method, COALESCE(p.receipt_image, p.receipt_file) as receipt_file, p.payment_date, p.status,
               p.verified_by, p.verified_at,
               o.furniture_type, o.furniture_name, o.order_number,
               COALESCE(o.estimated_cost, o.total_amount, 0) as order_total,
               COALESCE(o.deposit_paid, 0) as deposit_paid,
               o.status as order_status,
               CONCAT(u.first_name, ' ', u.last_name) as customer_name,
               CONCAT(v.first_name, ' ', v.last_name) as verified_by_name
        FROM furn_payments p
        LEFT JOIN furn_orders o ON p.order_id = o.id
        LEFT JOIN furn_users u ON p.customer_id = u.id
        LEFT JOIN furn_users v ON p.verified_by = v.id
        WHERE p.status = 'approved'
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $verifiedPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Verified payments error: " . $e->getMessage());
    $verifiedDebugError = $e->getMessage();
}

// Rejected payments
try {
    $stmt = $pdo->query("
        SELECT p.payment_id, p.order_id, p.customer_id, p.amount, p.payment_type,
               p.payment_method, COALESCE(p.receipt_image, p.receipt_file) as receipt_file, p.payment_date, p.status,
               o.furniture_type, o.furniture_name, o.order_number,
               CONCAT(u.first_name, ' ', u.last_name) as customer_name
        FROM furn_payments p
        LEFT JOIN furn_orders o ON p.order_id = o.id
        LEFT JOIN furn_users u ON p.customer_id = u.id
        WHERE p.status = 'rejected'
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $rejectedPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Rejected payments error: " . $e->getMessage());
}

$pageTitle = 'Payment Verification';
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
    
    <?php include_once __DIR__ . '/../../includes/manager_sidebar.php'; ?>
    
    <!-- Top Header -->
    <?php 
    $pageTitle = 'Payments';
    include_once __DIR__ . '/../../includes/manager_header.php'; 
    ?>
        <div class="header-left"><div class="system-status"><i class="fas fa-circle"></i> Workshop Manager</div></div>
        <div class="header-right">
            <div class="admin-profile">
                <div class="admin-avatar"><?php echo strtoupper(substr($managerName, 0, 1)); ?></div>
                <div><div style="font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($managerName); ?></div><div class="admin-role-badge">MANAGER</div></div>
            </div>
        </div>
    </div>
    <div class="main-content">
        <?php
        // Payments stats cards
        $pmCards = [];
        try {
            $pmReceived  = floatval($pdo->query("SELECT COALESCE(SUM(amount),0) FROM furn_payments WHERE status IN ('approved','verified')")->fetchColumn());
            $pmPending   = (int)$pdo->query("SELECT COUNT(*) FROM furn_payments WHERE status='pending'")->fetchColumn();
            $pmMonth     = floatval($pdo->query("SELECT COALESCE(SUM(amount),0) FROM furn_payments WHERE status IN ('approved','verified') AND MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())")->fetchColumn());
            $pmRejected  = (int)$pdo->query("SELECT COUNT(*) FROM furn_payments WHERE status='rejected'")->fetchColumn();
            $pmCards = [
                ['ETB '.number_format($pmReceived,0), 'Total Received',       '#27AE60','fa-money-bill-wave', '#'],
                [$pmPending,                          'Pending Verification', '#F39C12','fa-hourglass-half',  BASE_URL.'/public/manager/payments'],
                ['ETB '.number_format($pmMonth,0),    'Approved This Month',  '#3498DB','fa-calendar-check',  BASE_URL.'/public/manager/payments'],
                [$pmRejected,                         'Rejected',             '#E74C3C','fa-times-circle',     BASE_URL.'/public/manager/payments'],
            ];
        } catch(PDOException $e){}
        if (!empty($pmCards)):
        ?>
        <div class="stats-grid" style="margin-bottom:20px;">
            <?php foreach($pmCards as [$v,$l,$c,$i,$href]): ?>
            <a href="<?php echo $href; ?>" style="text-decoration:none;">
                <div class="stat-card" style="border-left:4px solid <?php echo $c; ?>;cursor:pointer;transition:transform .15s,box-shadow .15s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,0.12)';" onmouseout="this.style.transform='';this.style.boxShadow='';">
                    <div style="display:flex;justify-content:space-between;align-items:start;">
                        <div><div class="stat-value" style="font-size:<?php echo strpos((string)$v,'ETB')!==false?'16px':'28px';?>;color:<?php echo $c; ?>"><?php echo $v; ?></div><div class="stat-label"><?php echo $l; ?></div></div>
                        <div style="font-size:28px;color:<?php echo $c; ?>;opacity:.25;"><i class="fas <?php echo $i; ?>"></i></div>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (isset($debugError)): ?>
            <div style="background:#f8d7da;color:#721c24;padding:15px;border-radius:8px;margin-bottom:20px;">
                <strong>DB Error:</strong> <?php echo htmlspecialchars($debugError); ?>
            </div>
        <?php endif; ?>        <?php if (isset($_SESSION['success_message'])): ?>
            <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
                <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Pending Payments -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-clock"></i> Pending Payment Verification</h2>
                <span class="badge badge-warning" style="font-size: 14px; padding: 6px 12px;"><?php echo count($pendingPayments); ?> Pending</span>
            </div>
            
            <?php if (empty($pendingPayments)): ?>
                <p style="text-align: center; padding: 40px 20px; color: #7f8c8d;">
                    <i class="fas fa-check-circle" style="font-size: 48px; margin-bottom: 15px; display: block; color: #27AE60;"></i>
                    No pending payments to verify
                </p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table mobile-cards">
                        <thead>
                            <tr>
                                <th>Payment ID</th>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Receipt</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingPayments as $payment): ?>
                                <tr>
                                    <td>#<?php echo $payment['payment_id'] ?? 'N/A'; ?></td>
                                    <td>#<?php echo $payment['order_id'] ?? 'N/A'; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($payment['customer_name'] ?? 'N/A'); ?></strong><br>
                                        <small><?php echo htmlspecialchars($payment['customer_email'] ?? ''); ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $ptype = $payment['payment_type'] ?? '';
                                        // Infer type from amount if empty
                                        if (empty($ptype)) {
                                            $orderTotal = floatval($payment['total_amount'] ?? $payment['estimated_cost'] ?? 0);
                                            $amt = floatval($payment['amount'] ?? 0);
                                            if ($orderTotal > 0 && $amt >= $orderTotal * 0.95) {
                                                $ptype = 'full_payment';
                                            } elseif ($orderTotal > 0 && $amt <= $orderTotal * 0.5) {
                                                $ptype = 'prepayment';
                                            } else {
                                                $ptype = 'postpayment';
                                            }
                                        }
                                        if (in_array($ptype, ['deposit', 'prepayment'])) {
                                            $ptypeLabel = 'Pre Payment';
                                            $ptypeBadge = 'badge-warning';
                                        } elseif (in_array($ptype, ['final', 'remaining', 'final_payment', 'postpayment'])) {
                                            $ptypeLabel = 'Post Payment';
                                            $ptypeBadge = 'badge-success';
                                        } elseif ($ptype === 'full_payment') {
                                            $ptypeLabel = 'Full Payment';
                                            $ptypeBadge = 'badge-primary';
                                        } else {
                                            $ptypeLabel = ucfirst(str_replace('_',' ',$ptype));
                                            $ptypeBadge = 'badge-info';
                                        }
                                        ?>
                                        <span class="badge <?php echo $ptypeBadge; ?>"><?php echo $ptypeLabel; ?></span>
                                    </td>
                                    <td><strong>ETB <?php echo number_format($payment['amount'] ?? 0, 2); ?></strong></td>
                                    <td>
                                        <?php
                                        $receiptPath  = $payment['receipt_file'] ?? '';
                                        $payMethod    = $payment['payment_method'] ?? '';
                                        $isCash       = in_array($payMethod, ['cash', 'Cash']);
                                        if (!empty($receiptPath)):
                                            $receiptUrl = BASE_URL . '/public/' . ltrim($receiptPath, '/');
                                        ?>
                                            <a href="<?php echo htmlspecialchars($receiptUrl); ?>" target="_blank" style="background:#3498db;color:white;padding:6px 12px;border-radius:6px;text-decoration:none;font-size:13px;">
                                                <i class="fas fa-file-image"></i> View Receipt
                                            </a>
                                        <?php elseif ($isCash): ?>
                                            <span style="background:#27AE60;color:white;padding:4px 10px;border-radius:6px;font-size:12px;font-weight:600;">
                                                <i class="fas fa-money-bill-wave me-1"></i>Cash
                                            </span>
                                        <?php else: ?>
                                            <span style="color:#95a5a6;font-size:13px;"><i class="fas fa-times-circle"></i> No receipt</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php
                                    $rawDate = $payment['payment_date'] ?: $payment['submitted_at'] ?: $payment['created_at'];
                                    $displayDate = ($rawDate && $rawDate !== '0000-00-00') ? date('M d, Y', strtotime($rawDate)) : date('M d, Y', strtotime($payment['created_at']));
                                    ?>
                                    <td><?php echo $displayDate; ?></td>
                                    <td>
                                        <button class="btn-action btn-success-custom" onclick="openVerifyModal(<?php echo htmlspecialchars(json_encode($payment)); ?>, 'approve')">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button class="btn-action btn-danger-custom" onclick="openVerifyModal(<?php echo htmlspecialchars(json_encode($payment)); ?>, 'reject')">
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
        
        <!-- Verified Payments -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-check-circle"></i> Recently Verified Payments</h2>
            </div>
            <?php if (isset($verifiedDebugError)): ?>
                <div style="background:#f8d7da;color:#721c24;padding:12px;border-radius:8px;margin:10px 0;">
                    <strong>Query Error:</strong> <?php echo htmlspecialchars($verifiedDebugError); ?>
                </div>
            <?php endif; ?>
            <?php if (empty($verifiedPayments)): ?>
                <p style="text-align: center; padding: 40px 20px; color: #7f8c8d;">No verified payments yet</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table mobile-cards">
                        <thead>
                            <tr>
                                <th>Payment ID</th>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Verified By</th>
                                <th>Verified Date</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($verifiedPayments as $payment): ?>
                                <tr>
                                    <td>#<?php echo $payment['payment_id'] ?? $payment['id'] ?? 'N/A'; ?></td>
                                    <td>#<?php echo $payment['order_id'] ?? 'N/A'; ?></td>
                                    <td><?php echo htmlspecialchars($payment['customer_name'] ?? 'N/A'); ?></td>
                                    <td>ETB <?php echo number_format($payment['amount'] ?? 0, 2); ?></td>
                                    <td><?php echo htmlspecialchars($payment['verified_by_name'] ?? 'N/A'); ?></td>
                                    <td><?php 
                                        $vDate = $payment['verified_at'] ?? null;
                                        echo ($vDate && $vDate !== '0000-00-00 00:00:00') ? date('M d, Y', strtotime($vDate)) : 'N/A';
                                    ?></td>
                                    <td>-</td>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Rejected Payments -->
        <?php if (!empty($rejectedPayments)): ?>
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-times-circle"></i> Recently Rejected Payments</h2>
            </div>
            
            <div class="table-responsive">
                <table class="data-table mobile-cards">
                    <thead>
                        <tr>
                            <th>Payment ID</th>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Rejected By</th>
                            <th>Rejected Date</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rejectedPayments as $payment): ?>
                            <tr>
                                <td>#<?php echo $payment['id']; ?></td>
                                <td>#<?php echo $payment['order_id']; ?></td>
                                <td><?php echo htmlspecialchars($payment['customer_name'] ?? 'N/A'); ?></td>
                                <td>ETB <?php echo number_format($payment['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($payment['approved_by_name'] ?? 'N/A'); ?></td>
                                <td><?php echo date('M d, Y H:i', strtotime($payment['approved_at'])); ?></td>
                                <td><?php echo htmlspecialchars($payment['rejection_reason'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Verification Modal -->
    <div id="verifyModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.55);z-index:9999;align-items:center;justify-content:center;overflow-y:auto;padding:20px;">
        <div style="background:white;border-radius:14px;padding:0;max-width:620px;width:100%;margin:auto;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,.2);">

            <!-- Modal header -->
            <div id="modalHeader" style="padding:18px 24px;display:flex;justify-content:space-between;align-items:center;">
                <h3 style="margin:0;font-size:17px;" id="modalTitle"><i class="fas fa-check-circle"></i> Verify Payment</h3>
                <button onclick="closeVerifyModal()" style="background:none;border:none;font-size:22px;cursor:pointer;color:white;opacity:.8;">&times;</button>
            </div>

            <div style="padding:20px 24px;">
                <form method="POST">
                    <input type="hidden" name="verify_payment" value="1">
                    <input type="hidden" name="payment_id" id="verify_payment_id">
                    <input type="hidden" name="action" id="verify_action">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? $csrf_token); ?>">

                    <!-- Order summary -->
                    <div style="background:#f8f4e9;border-radius:10px;padding:16px;margin-bottom:16px;">
                        <div style="font-size:11px;font-weight:700;color:#856404;text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;"><i class="fas fa-shopping-cart me-1"></i>Order Information</div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px;" id="orderInfo"></div>
                    </div>

                    <!-- Payment breakdown -->
                    <div style="background:#e8f5e9;border-radius:10px;padding:16px;margin-bottom:16px;">
                        <div style="font-size:11px;font-weight:700;color:#155724;text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;"><i class="fas fa-credit-card me-1"></i>Payment Details</div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px;" id="paymentInfo"></div>
                        <!-- Cost breakdown bar -->
                        <div id="costBar" style="margin-top:12px;"></div>
                    </div>

                    <!-- Receipt -->
                    <div id="receiptSection" style="margin-bottom:16px;"></div>

                    <!-- Notes -->
                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:6px;">Notes / Reason <span id="notesHint" style="font-weight:400;color:#aaa;"></span></label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Add verification notes or rejection reason..."></textarea>
                    </div>

                    <div style="display:flex;gap:10px;justify-content:flex-end;">
                        <button type="button" style="padding:10px 20px;background:#95a5a6;color:white;border:none;border-radius:8px;font-size:14px;cursor:pointer;" onclick="closeVerifyModal()">Cancel</button>
                        <button type="submit" id="submitBtn" style="padding:10px 24px;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;">Confirm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    const BASE = '<?php echo BASE_URL; ?>';

    function openVerifyModal(payment, action) {
        document.getElementById('verify_payment_id').value = payment.payment_id || payment.id;
        document.getElementById('verify_action').value = action;

        const isApprove = action === 'approve';
        const headerColor = isApprove ? '#27ae60' : '#e74c3c';

        document.getElementById('modalHeader').style.background = headerColor;
        document.getElementById('modalTitle').innerHTML = isApprove
            ? '<i class="fas fa-check-circle"></i> Approve Payment'
            : '<i class="fas fa-times-circle"></i> Reject Payment';
        document.getElementById('modalTitle').style.color = 'white';

        const btn = document.getElementById('submitBtn');
        btn.style.background = headerColor;
        btn.style.color = 'white';
        btn.textContent = isApprove ? 'Approve Payment' : 'Reject Payment';

        document.getElementById('notesHint').textContent = isApprove ? '(optional)' : '(required — explain reason)';

        // Payment type label
        const ptype = payment.payment_type || '';
        let ptypeLabel = ptype, ptypeColor = '#3498db';
        if (['deposit','prepayment'].includes(ptype)) { ptypeLabel = 'Pre-Payment (Deposit)'; ptypeColor = '#f39c12'; }
        else if (['final','remaining','final_payment','postpayment'].includes(ptype)) { ptypeLabel = 'Post-Payment (Final)'; ptypeColor = '#27ae60'; }
        else if (ptype === 'full_payment') { ptypeLabel = 'Full Payment'; ptypeColor = '#9b59b6'; }

        // Payment method
        const method = payment.payment_method || 'N/A';
        const bankName = payment.bank_name || '';
        const ref      = payment.transaction_reference || '';

        // Order info
        const orderTotal   = parseFloat(payment.order_total || 0);
        const alreadyPaid  = parseFloat(payment.already_paid || 0);
        const thisPay      = parseFloat(payment.amount || 0);
        const afterApprove = alreadyPaid + thisPay;
        const remaining    = Math.max(0, orderTotal - afterApprove);

        document.getElementById('orderInfo').innerHTML = `
            <div><span style="color:#856404;font-weight:600;">Order #</span><br>${payment.order_number || '#'+payment.order_id}</div>
            <div><span style="color:#856404;font-weight:600;">Furniture</span><br>${payment.furniture_name || payment.furniture_type || 'N/A'}</div>
            <div><span style="color:#856404;font-weight:600;">Customer</span><br>${payment.customer_name || 'N/A'}</div>
            <div><span style="color:#856404;font-weight:600;">Order Status</span><br>${(payment.order_status||'').replace(/_/g,' ')}</div>
            <div style="grid-column:1/-1;background:#fff;border-radius:8px;padding:12px;margin-top:4px;">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;text-align:center;">
                    <div style="background:#e8f5e9;border-radius:6px;padding:8px;">
                        <div style="font-size:10px;color:#555;font-weight:600;text-transform:uppercase;">Total Cost</div>
                        <div style="font-size:15px;font-weight:800;color:#2c3e50;">ETB ${orderTotal.toLocaleString('en',{minimumFractionDigits:2})}</div>
                    </div>
                    <div style="background:#fff3cd;border-radius:6px;padding:8px;">
                        <div style="font-size:10px;color:#555;font-weight:600;text-transform:uppercase;">Deposit Paid</div>
                        <div style="font-size:15px;font-weight:800;color:#f39c12;">ETB ${parseFloat(payment.deposit_paid||0).toLocaleString('en',{minimumFractionDigits:2})}</div>
                    </div>
                    <div style="background:#fdecea;border-radius:6px;padding:8px;">
                        <div style="font-size:10px;color:#555;font-weight:600;text-transform:uppercase;">Remaining (60%)</div>
                        <div style="font-size:15px;font-weight:800;color:#e74c3c;">ETB ${Math.max(0,orderTotal - parseFloat(payment.deposit_paid||0)).toLocaleString('en',{minimumFractionDigits:2})}</div>
                    </div>
                </div>
            </div>
        `;

        document.getElementById('paymentInfo').innerHTML = `
            <div><span style="color:#155724;font-weight:600;">Payment ID</span><br>#${payment.payment_id || payment.id}</div>
            <div><span style="color:#155724;font-weight:600;">Payment Type</span><br><span style="background:${ptypeColor};color:white;padding:2px 8px;border-radius:8px;font-size:12px;font-weight:700;">${ptypeLabel}</span></div>
            <div><span style="color:#155724;font-weight:600;">This Payment</span><br><strong style="font-size:16px;color:#2c3e50;">ETB ${thisPay.toLocaleString('en',{minimumFractionDigits:2})}</strong></div>
            <div><span style="color:#155724;font-weight:600;">Payment Method</span><br>${method}</div>
            ${bankName ? `<div><span style="color:#155724;font-weight:600;">Bank Name</span><br><strong>${bankName}</strong></div>` : ''}
            ${ref ? `<div><span style="color:#155724;font-weight:600;">Transaction Reference</span><br><code style="background:#f0f0f0;padding:2px 6px;border-radius:4px;font-size:13px;">${ref}</code></div>` : ''}
            <div><span style="color:#155724;font-weight:600;">After Approval — Paid</span><br><strong>ETB ${afterApprove.toLocaleString('en',{minimumFractionDigits:2})}</strong></div>
            <div><span style="color:#155724;font-weight:600;">Remaining After</span><br><strong style="color:${remaining>0?'#e74c3c':'#27ae60'};">ETB ${remaining.toLocaleString('en',{minimumFractionDigits:2})}</strong></div>
        `;

        // Progress bar
        const pct = orderTotal > 0 ? Math.min(100, Math.round((afterApprove / orderTotal) * 100)) : 0;
        document.getElementById('costBar').innerHTML = orderTotal > 0 ? `
            <div style="font-size:11px;color:#555;margin-bottom:4px;">Payment progress after approval: <strong>${pct}%</strong></div>
            <div style="background:#e0e0e0;border-radius:6px;height:10px;overflow:hidden;">
                <div style="background:${pct>=100?'#27ae60':'#3498db'};height:100%;width:${pct}%;transition:width .4s;"></div>
            </div>
        ` : '';

        // Receipt
        const receipt = payment.receipt_file || '';
        const isCash  = ['cash','Cash'].includes(payment.payment_method || '');
        let receiptHtml = '';
        if (receipt) {
            receiptHtml = `<a href="${BASE}/public/${receipt}" target="_blank" style="display:inline-flex;align-items:center;gap:8px;background:#3498db;color:white;padding:9px 16px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;"><i class="fas fa-file-image"></i> View Payment Receipt</a>`;
        } else if (isCash) {
            receiptHtml = `<span style="background:#27ae60;color:white;padding:8px 14px;border-radius:8px;font-size:13px;font-weight:600;"><i class="fas fa-money-bill-wave me-1"></i>Cash Payment — No receipt needed</span>`;
        } else {
            receiptHtml = `<span style="color:#e74c3c;font-size:13px;"><i class="fas fa-exclamation-triangle me-1"></i>No receipt uploaded</span>`;
        }
        document.getElementById('receiptSection').innerHTML = `
            <div style="font-size:11px;font-weight:700;color:#555;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;"><i class="fas fa-file-alt me-1"></i>Receipt</div>
            ${receiptHtml}
        `;

        document.getElementById('verifyModal').style.display = 'flex';
    }

    function closeVerifyModal() {
        document.getElementById('verifyModal').style.display = 'none';
    }

    document.getElementById('verifyModal').addEventListener('click', function(e) {
        if (e.target === this) closeVerifyModal();
    });
    </script>
    
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
