<?php require_once dirname(__DIR__) . '/includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <?php require_once dirname(__DIR__) . '/includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Payment Verification Dashboard</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary">Export</button>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <h5 class="card-title">Pending Verification</h5>
                            <h2><?php echo count($pendingReceipts ?? []); ?></h2>
                            <small>Payment receipts awaiting approval</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <h5 class="card-title">Today's Approvals</h5>
                            <h2>
                                <?php
                                $stmt = $db->prepare("SELECT COUNT(*) FROM furn_payment_receipts WHERE status = 'approved' AND DATE(approved_at) = CURDATE()");
                                $stmt->execute();
                                echo $stmt->fetchColumn();
                                ?>
                            </h2>
                            <small>Payments approved today</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <h5 class="card-title">Total Revenue</h5>
                            <h2>
                                <?php
                                $stmt = $db->prepare("SELECT SUM(amount) FROM furn_payment_receipts WHERE status = 'approved' AND payment_type = 'deposit'");
                                $stmt->execute();
                                $total = $stmt->fetchColumn();
                                echo 'ETB ' . number_format($total ?? 0, 2);
                                ?>
                            </h2>
                            <small>From approved deposits</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Receipts Section -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Pending Payment Receipts</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($pendingReceipts)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                    <h4>No pending payments</h4>
                                    <p class="text-muted">All payment receipts have been processed.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Order #</th>
                                                <th>Customer</th>
                                                <th>Payment Type</th>
                                                <th>Amount</th>
                                                <th>Date Uploaded</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($pendingReceipts as $receipt): ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($receipt['order_number']); ?></strong></td>
                                                    <td>
                                                        <?php echo htmlspecialchars($receipt['first_name'] . ' ' . $receipt['last_name']); ?>
                                                        <div class="small text-muted"><?php echo htmlspecialchars($receipt['email']); ?></div>
                                                    </td>
                                                    <td>
                                                        <?php if ($receipt['payment_type'] === 'deposit'): ?>
                                                            <span class="badge bg-warning">Deposit</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-success">Final Payment</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><strong>ETB <?php echo number_format($receipt['amount'], 2); ?></strong></td>
                                                    <td><?php echo date('M j, Y g:i A', strtotime($receipt['created_at'])); ?></td>
                                                    <td>
                                                        <a href="/payments/view-receipt/<?php echo $receipt['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye"></i> View
                                                        </a>
                                                        <a href="/payments/verify/<?php echo $receipt['id']; ?>" class="btn btn-sm btn-success">
                                                            <i class="fas fa-check"></i> Verify
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Recent Payment Activity</h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group">
                                <?php 
                                $stmt = $db->prepare("
                                    SELECT al.*, u.first_name, u.last_name, u.role,
                                           pr.payment_type, pr.amount, o.order_number
                                    FROM furn_audit_logs al
                                    LEFT JOIN furn_users u ON al.user_id = u.id
                                    LEFT JOIN furn_payment_receipts pr ON al.record_id = pr.id AND al.table_name = 'furn_payment_receipts'
                                    LEFT JOIN furn_orders o ON pr.order_id = o.id
                                    WHERE al.action LIKE '%payment%'
                                    ORDER BY al.created_at DESC
                                    LIMIT 10
                                ");
                                $stmt->execute();
                                $recentActivities = $stmt->fetchAll();
                                
                                if (empty($recentActivities)):
                                ?>
                                    <div class="text-center py-3">
                                        <p class="text-muted">No recent payment activity</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recentActivities as $activity): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1">
                                                    <?php 
                                                    $actionText = '';
                                                    switch ($activity['action']) {
                                                        case 'deposit_receipt_uploaded':
                                                            $actionText = 'Deposit receipt uploaded';
                                                            break;
                                                        case 'final_receipt_uploaded':
                                                            $actionText = 'Final payment receipt uploaded';
                                                            break;
                                                        case 'payment_approved':
                                                            $actionText = 'Payment approved';
                                                            break;
                                                        case 'payment_rejected':
                                                            $actionText = 'Payment rejected';
                                                            break;
                                                        default:
                                                            $actionText = $activity['action'];
                                                    }
                                                    echo htmlspecialchars($actionText);
                                                    ?>
                                                </h6>
                                                <small><?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?></small>
                                            </div>
                                            <p class="mb-1">
                                                <?php if ($activity['user_id']): ?>
                                                    By <?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?>
                                                    (<?php echo htmlspecialchars($activity['role']); ?>)
                                                <?php else: ?>
                                                    System action
                                                <?php endif; ?>
                                            </p>
                                            <?php if ($activity['order_number']): ?>
                                                <small class="text-muted">Order: <?php echo $activity['order_number']; ?></small>
                                                <?php if ($activity['amount']): ?>
                                                    <small class="text-muted"> | Amount: ETB <?php echo number_format($activity['amount'], 2); ?></small>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>