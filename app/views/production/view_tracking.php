<?php require_once dirname(__DIR__) . '/includes/header.php'; ?>

<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/">Home</a></li>
                    <?php if ($auth->getUserRole() === 'manager' || $auth->getUserRole() === 'admin'): ?>
                        <li class="breadcrumb-item"><a href="/production/manager-dashboard">Production Dashboard</a></li>
                    <?php else: ?>
                        <li class="breadcrumb-item"><a href="/orders/my-orders">My Orders</a></li>
                    <?php endif; ?>
                    <li class="breadcrumb-item active">Production Tracking</li>
                </ol>
            </nav>
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Production Tracking - <?php echo htmlspecialchars($order['order_number']); ?></h1>
                <div>
                    <?php if ($auth->getUserRole() === 'manager' || $auth->getUserRole() === 'admin'): ?>
                        <a href="/production/manager-dashboard" class="btn btn-secondary">Back to Dashboard</a>
                    <?php else: ?>
                        <a href="/orders/my-orders" class="btn btn-secondary">Back to My Orders</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Order Status -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Order Status</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th>Current Status:</th>
                                    <td>
                                        <?php
                                        $statusClass = '';
                                        $statusText = '';
                                        switch ($order['status']) {
                                            case 'pending_cost_approval':
                                                $statusClass = 'bg-warning';
                                                $statusText = 'Pending Cost Approval';
                                                break;
                                            case 'waiting_for_deposit':
                                                $statusClass = 'bg-info';
                                                $statusText = 'Waiting for Deposit';
                                                break;
                                            case 'deposit_paid':
                                                $statusClass = 'bg-primary';
                                                $statusText = 'Deposit Paid';
                                                break;
                                            case 'in_production':
                                                $statusClass = 'bg-success';
                                                $statusText = 'In Production';
                                                break;
                                            case 'ready_for_delivery':
                                                $statusClass = 'bg-success';
                                                $statusText = 'Ready for Delivery';
                                                break;
                                            case 'completed':
                                                $statusClass = 'bg-secondary';
                                                $statusText = 'Completed';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Total Amount:</th>
                                    <td><strong>ETB <?php echo number_format($order['total_amount'], 2); ?></strong></td>
                                </tr>
                                <tr>
                                    <th>Deposit Paid:</th>
                                    <td>ETB <?php echo number_format($order['deposit_paid'], 2); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th>Production Started:</th>
                                    <td>
                                        <?php if ($timeline['production_started_at']): ?>
                                            <?php echo date('F j, Y g:i A', strtotime($timeline['production_started_at'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not started</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Estimated Completion:</th>
                                    <td>
                                        <?php if ($timeline['estimated_completion_date']): ?>
                                            <?php echo date('F j, Y', strtotime($timeline['estimated_completion_date'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not set</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Actual Completion:</th>
                                    <td>
                                        <?php if ($timeline['production_completed_at']): ?>
                                            <?php echo date('F j, Y g:i A', strtotime($timeline['production_completed_at'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">In progress</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Production Assignments -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Production Assignments (<?php echo count($assignments); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($assignments)): ?>
                        <div class="text-center py-3">
                            <i class="fas fa-user-clock fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0">No production assignments yet</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Assigned By</th>
                                        <th>Estimated Hours</th>
                                        <th>Actual Hours</th>
                                        <th>Status</th>
                                        <th>Timeline</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assignments as $assignment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($assignment['employee_first_name'] . ' ' . $assignment['employee_last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($assignment['assigned_by_first_name'] . ' ' . $assignment['assigned_by_last_name']); ?></td>
                                            <td><?php echo $assignment['estimated_hours'] ?? 'N/A'; ?> hours</td>
                                            <td><?php echo $assignment['actual_hours'] ?? 'N/A'; ?> hours</td>
                                            <td>
                                                <?php
                                                $statusClass = '';
                                                $statusText = '';
                                                switch ($assignment['status']) {
                                                    case 'assigned':
                                                        $statusClass = 'bg-warning';
                                                        $statusText = 'Assigned';
                                                        break;
                                                    case 'in_progress':
                                                        $statusClass = 'bg-primary';
                                                        $statusText = 'In Progress';
                                                        break;
                                                    case 'completed':
                                                        $statusClass = 'bg-success';
                                                        $statusText = 'Completed';
                                                        break;
                                                    case 'cancelled':
                                                        $statusClass = 'bg-danger';
                                                        $statusText = 'Cancelled';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                            </td>
                                            <td>
                                                <?php if ($assignment['assigned_at']): ?>
                                                    <small>Assigned: <?php echo date('M j', strtotime($assignment['assigned_at'])); ?></small><br>
                                                <?php endif; ?>
                                                <?php if ($assignment['started_at']): ?>
                                                    <small>Started: <?php echo date('M j', strtotime($assignment['started_at'])); ?></small><br>
                                                <?php endif; ?>
                                                <?php if ($assignment['completed_at']): ?>
                                                    <small>Completed: <?php echo date('M j', strtotime($assignment['completed_at'])); ?></small>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Material Reservations -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Material Reservations (<?php echo count($reservations); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($reservations)): ?>
                        <div class="text-center py-3">
                            <i class="fas fa-boxes fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-0">No material reservations</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Material</th>
                                        <th>Reserved Quantity</th>
                                        <th>Unit</th>
                                        <th>Status</th>
                                        <th>Reserved At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reservations as $reservation): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($reservation['material_name']); ?></td>
                                            <td><?php echo $reservation['quantity']; ?></td>
                                            <td><?php echo htmlspecialchars($reservation['unit']); ?></td>
                                            <td>
                                                <?php
                                                $statusClass = '';
                                                $statusText = '';
                                                switch ($reservation['status']) {
                                                    case 'reserved':
                                                        $statusClass = 'bg-warning';
                                                        $statusText = 'Reserved';
                                                        break;
                                                    case 'used':
                                                        $statusClass = 'bg-success';
                                                        $statusText = 'Used';
                                                        break;
                                                    case 'cancelled':
                                                        $statusClass = 'bg-danger';
                                                        $statusText = 'Cancelled';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                            </td>
                                            <td><?php echo date('M j, Y g:i A', strtotime($reservation['reserved_at'])); ?></td>
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
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>