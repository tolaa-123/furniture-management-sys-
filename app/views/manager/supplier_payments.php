<?php
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['manager', 'admin'])) {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}
require_once __DIR__ . '/../../../config/db_config.php';

$managerName = $_SESSION['user_name'] ?? 'Manager';
$managerId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];

// Fetch summary statistics
$stats = ['total_outstanding' => 0, 'overdue_count' => 0, 'due_this_week' => 0, 'paid_this_month' => 0];
try {
    $stmt = $pdo->query("SELECT COALESCE(SUM(balance_due),0) FROM furn_supplier_invoices WHERE status IN ('pending','approved','overdue')");
    $stats['total_outstanding'] = floatval($stmt->fetchColumn());
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM furn_supplier_invoices WHERE status='overdue'");
    $stats['overdue_count'] = intval($stmt->fetchColumn());
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(balance_due),0) FROM furn_supplier_invoices WHERE due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND status IN ('pending','approved')");
    $stats['due_this_week'] = floatval($stmt->fetchColumn());
    
    $stmt = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM furn_supplier_payments WHERE MONTH(payment_date)=MONTH(CURDATE()) AND YEAR(payment_date)=YEAR(CURDATE()) AND status='verified'");
    $stats['paid_this_month'] = floatval($stmt->fetchColumn());
} catch (PDOException $e) {
    error_log("Stats error: " . $e->getMessage());
}

// Fetch outstanding invoices
$outstandingInvoices = [];
try {
    $stmt = $pdo->query("
        SELECT si.*, 
               DATEDIFF(CURDATE(), si.due_date) as days_overdue,
               CASE 
                   WHEN DATEDIFF(CURDATE(), si.due_date) > 0 THEN 'Overdue'
                   WHEN DATEDIFF(si.due_date, CURDATE()) <= 7 THEN 'Due Soon'
                   ELSE 'Current'
               END as aging_status
        FROM furn_supplier_invoices si
        WHERE si.status IN ('pending', 'approved', 'overdue')
          AND si.balance_due > 0
        ORDER BY si.due_date ASC
        LIMIT 50
    ");
    $outstandingInvoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Outstanding invoices error: " . $e->getMessage());
}


// Fetch recent payments
$recentPayments = [];
try {
    $stmt = $pdo->query("
        SELECT sp.*, si.invoice_number, si.total_amount,
               CONCAT(u.first_name, ' ', u.last_name) as paid_by_name
        FROM furn_supplier_payments sp
        JOIN furn_supplier_invoices si ON sp.invoice_id = si.id
        LEFT JOIN furn_users u ON sp.paid_by = u.id
        WHERE sp.status = 'verified'
        ORDER BY sp.payment_date DESC
        LIMIT 20
    ");
    $recentPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Recent payments error: " . $e->getMessage());
}

$pageTitle = 'Supplier Payments';
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
<?php include_once __DIR__ . '/../../includes/manager_header.php'; ?>

<div class="main-content">
    <?php if (isset($_SESSION['success_message'])): ?>
        <div style="background:#d4edda;color:#155724;padding:15px;border-radius:8px;margin-bottom:20px;border:1px solid #c3e6cb;">
            <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div style="background:#f8d7da;color:#721c24;padding:15px;border-radius:8px;margin-bottom:20px;border:1px solid #f5c6cb;">
            <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="stats-grid" style="margin-bottom:20px;">
        <div class="stat-card" style="border-left:4px solid #E74C3C;">
            <div style="display:flex;justify-content:space-between;align-items:start;">
                <div>
                    <div class="stat-value" style="font-size:20px;color:#E74C3C;">ETB <?php echo number_format($stats['total_outstanding'], 2); ?></div>
                    <div class="stat-label">Total Outstanding</div>
                </div>
                <div style="font-size:28px;color:#E74C3C;opacity:.25;"><i class="fas fa-exclamation-triangle"></i></div>
            </div>
        </div>
        
        <div class="stat-card" style="border-left:4px solid #F39C12;">
            <div style="display:flex;justify-content:space-between;align-items:start;">
                <div>
                    <div class="stat-value" style="font-size:28px;color:#F39C12;"><?php echo $stats['overdue_count']; ?></div>
                    <div class="stat-label">Overdue Invoices</div>
                </div>
                <div style="font-size:28px;color:#F39C12;opacity:.25;"><i class="fas fa-clock"></i></div>
            </div>
        </div>

        
        <div class="stat-card" style="border-left:4px solid #F1C40F;">
            <div style="display:flex;justify-content:space-between;align-items:start;">
                <div>
                    <div class="stat-value" style="font-size:20px;color:#F1C40F;">ETB <?php echo number_format($stats['due_this_week'], 2); ?></div>
                    <div class="stat-label">Due This Week</div>
                </div>
                <div style="font-size:28px;color:#F1C40F;opacity:.25;"><i class="fas fa-calendar-week"></i></div>
            </div>
        </div>
        
        <div class="stat-card" style="border-left:4px solid #27AE60;">
            <div style="display:flex;justify-content:space-between;align-items:start;">
                <div>
                    <div class="stat-value" style="font-size:20px;color:#27AE60;">ETB <?php echo number_format($stats['paid_this_month'], 2); ?></div>
                    <div class="stat-label">Paid This Month</div>
                </div>
                <div style="font-size:28px;color:#27AE60;opacity:.25;"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div style="margin-bottom:20px;display:flex;gap:10px;flex-wrap:wrap;">
        <a href="<?php echo BASE_URL; ?>/public/manager/create-supplier-invoice" class="btn-action btn-success-custom">
            <i class="fas fa-file-invoice"></i> Create Invoice
        </a>
        <a href="<?php echo BASE_URL; ?>/public/manager/record-supplier-payment" class="btn-action btn-primary-custom">
            <i class="fas fa-money-bill-wave"></i> Record Payment
        </a>
    </div>

    <!-- Outstanding Invoices -->
    <div class="section-card" style="border-left: 4px solid #E74C3C;">
        <div class="section-header">
            <h2 class="section-title"><i class="fas fa-file-invoice-dollar" style="color:#E74C3C;"></i> Outstanding Invoices
                <?php if (count($outstandingInvoices) > 0): ?>
                    <span style="background:#E74C3C;color:white;border-radius:50%;padding:2px 8px;font-size:13px;margin-left:8px;"><?php echo count($outstandingInvoices); ?></span>
                <?php endif; ?>
            </h2>
        </div>
        <?php if (empty($outstandingInvoices)): ?>
            <p style="text-align:center;padding:40px;color:#aaa;">
                <i class="fas fa-check-circle" style="font-size:48px;margin-bottom:20px;display:block;color:#27AE60;"></i>
                No outstanding invoices. All payments are up to date!
            </p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table mobile-cards">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Supplier</th>
                        <th>Invoice Date</th>
                        <th>Due Date</th>
                        <th>Total Amount</th>
                        <th>Paid</th>
                        <th>Balance Due</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($outstandingInvoices as $inv): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($inv['invoice_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($inv['supplier_name']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($inv['invoice_date'])); ?></td>
                        <td><?php echo date('M d, Y', strtotime($inv['due_date'])); ?></td>
                        <td>ETB <?php echo number_format($inv['total_amount'], 2); ?></td>
                        <td>ETB <?php echo number_format($inv['paid_amount'], 2); ?></td>
                        <td><strong style="color:#E74C3C;">ETB <?php echo number_format($inv['balance_due'], 2); ?></strong></td>
                        <td>
                            <?php if ($inv['aging_status'] === 'Overdue'): ?>
                                <span class="badge badge-danger">Overdue (<?php echo abs($inv['days_overdue']); ?> days)</span>
                            <?php elseif ($inv['aging_status'] === 'Due Soon'): ?>
                                <span class="badge badge-warning">Due Soon</span>
                            <?php else: ?>
                                <span class="badge badge-info">Current</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?php echo BASE_URL; ?>/public/manager/record-supplier-payment?invoice_id=<?php echo $inv['id']; ?>" class="btn-action btn-primary-custom" style="padding:5px 10px;font-size:12px;">
                                <i class="fas fa-money-bill-wave"></i> Pay
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>



    <!-- Recent Payments -->
    <div class="section-card">
        <div class="section-header">
            <h2 class="section-title"><i class="fas fa-history"></i> Recent Payments</h2>
        </div>
        <?php if (empty($recentPayments)): ?>
            <p style="text-align:center;padding:40px;color:#aaa;">No payment records yet.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table mobile-cards">
                <thead>
                    <tr>
                        <th>Payment Date</th>
                        <th>Invoice #</th>
                        <th>Supplier</th>
                        <th>Amount</th>
                        <th>Payment Method</th>
                        <th>Reference #</th>
                        <th>Paid By</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentPayments as $pmt): ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($pmt['payment_date'])); ?></td>
                        <td><strong><?php echo htmlspecialchars($pmt['invoice_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($pmt['supplier_name']); ?></td>
                        <td><strong style="color:#27AE60;">ETB <?php echo number_format($pmt['amount'], 2); ?></strong></td>
                        <td><?php echo ucwords(str_replace('_', ' ', $pmt['payment_method'])); ?></td>
                        <td><?php echo $pmt['reference_number'] ? htmlspecialchars($pmt['reference_number']) : '<span style="color:#aaa;">—</span>'; ?></td>
                        <td><?php echo htmlspecialchars($pmt['paid_by_name'] ?? 'N/A'); ?></td>
                        <td><span class="badge badge-success">Verified</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
