<?php require_once dirname(__DIR__) . '/includes/header.php'; ?>

<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/">Home</a></li>
                    <?php if ($auth->getUserRole() === 'manager' || $auth->getUserRole() === 'admin'): ?>
                        <li class="breadcrumb-item"><a href="/manager/dashboard">Dashboard</a></li>
                    <?php else: ?>
                        <li class="breadcrumb-item"><a href="/orders/my-orders">My Orders</a></li>
                    <?php endif; ?>
                    <li class="breadcrumb-item active">Order <?php echo htmlspecialchars($order['order_number']); ?></li>
                </ol>
            </nav>
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Order Details - <?php echo htmlspecialchars($order['order_number']); ?></h1>
                <div>
                    <?php if ($auth->getUserRole() === 'manager' || $auth->getUserRole() === 'admin'): ?>
                        <a href="/manager/dashboard" class="btn btn-secondary">Back to Dashboard</a>
                    <?php else: ?>
                        <a href="/orders/my-orders" class="btn btn-secondary">Back to My Orders</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Order Summary Card -->
                        <!-- Production Progress Bar -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Production Progress</h5>
                            </div>
                            <div class="card-body">
                                <?php
                                // Example progress steps (customize as needed)
                                $steps = [
                                    'Order Submitted',
                                    'Manager Approved',
                                    'Deposit Paid',
                                    'Production Started',
                                    'Assembly Stage',
                                    'Finishing',
                                    'Completed'
                                ];
                                $currentStep = 0;
                                switch ($order['status']) {
                                    case 'pending_approval': $currentStep = 0; break;
                                    case 'approved': $currentStep = 1; break;
                                    case 'deposit_paid': $currentStep = 2; break;
                                    case 'in_production': $currentStep = 3; break;
                                    case 'production_completed': $currentStep = 5; break;
                                    case 'completed': $currentStep = 6; break;
                                    default: $currentStep = 0; break;
                                }
                                ?>
                                <div class="progress" style="height: 32px;">
                                    <?php foreach ($steps as $i => $step): ?>
                                        <div class="progress-bar <?php echo ($i <= $currentStep) ? 'bg-success' : 'bg-secondary'; ?>" role="progressbar" style="width:<?php echo (100/count($steps)); ?>%">
                                            <?php if ($i == $currentStep): ?>
                                                <strong><?php echo $step; ?> <?php echo ($i < $currentStep) ? '✔' : '🔄'; ?></strong>
                                            <?php elseif ($i < $currentStep): ?>
                                                <?php echo $step; ?> ✔
                                            <?php else: ?>
                                                <?php echo $step; ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Order Summary</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th>Order Number:</th>
                                    <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                                </tr>
                                <tr>
                                    <th>Customer:</th>
                                    <td><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Email:</th>
                                    <td><?php echo htmlspecialchars($order['email']); ?></td>
                                </tr>
                                <tr>
                                    <th>Phone:</th>
                                    <td><?php echo htmlspecialchars($order['phone'] ?? 'N/A'); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th>Status:</th>
                                    <td>
                                        <?php
                                        $statusClass = '';
                                        $statusText = '';
                                        switch ($order['status']) {
                                            case 'pending_cost_approval':
                                                $statusClass = 'bg-warning';
                                                $statusText = 'Pending Cost Approval';
                                                break;
                                            case 'cost_estimated':
                                                $statusClass = 'bg-info';
                                                $statusText = 'Cost Estimated - Pay Deposit';
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
                                            case 'cancelled':
                                                $statusClass = 'bg-danger';
                                                $statusText = 'Cancelled';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Total Amount:</th>
                                    <td>
                                        <?php if ($order['total_amount'] > 0): ?>
                                            <strong>ETB <?php echo number_format($order['total_amount'], 2); ?></strong>
                                        <?php else: ?>
                                            <span class="text-muted">Pending approval</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php if ($order['deposit_amount'] > 0): ?>
                                    <tr>
                                        <th>Required Deposit:</th>
                                        <td>
                                            <strong>ETB <?php echo number_format($order['deposit_amount'], 2); ?></strong>
                                            <?php if ($order['deposit_paid'] > 0): ?>
                                                <div class="small text-success">
                                                    Paid: ETB <?php echo number_format($order['deposit_paid'], 2); ?>
                                                    <?php if ($order['deposit_paid_at']): ?>
                                                        on <?php echo date('M j, Y', strtotime($order['deposit_paid_at'])); ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <tr>
                                    <th>Order Date:</th>
                                    <td><?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <?php if (!empty($order['special_instructions'])): ?>
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6>Special Instructions:</h6>
                                <p class="text-muted"><?php echo nl2br(htmlspecialchars($order['special_instructions'])); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Customizations -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Order Items (<?php echo count($customizations); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($customizations)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                            <h4>No items found</h4>
                            <p class="text-muted">This order doesn't contain any items.</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($customizations as $customization): ?>
                                <div class="col-12 mb-4">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-8">
                                                    <h5 class="card-title"><?php echo htmlspecialchars($customization['product_name']); ?></h5>
                                                    <p class="text-muted mb-2"><?php echo htmlspecialchars($customization['category_name']); ?></p>
                                                    
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <?php if (!empty($customization['size_modifications'])): ?>
                                                                <p><strong>Size Modifications:</strong> <?php echo htmlspecialchars($customization['size_modifications']); ?></p>
                                                            <?php endif; ?>
                                                            
                                                            <?php if (!empty($customization['color_selection'])): ?>
                                                                <p><strong>Color:</strong> <?php echo htmlspecialchars($customization['color_selection']); ?></p>
                                                            <?php endif; ?>
                                                            
                                                            <?php if (!empty($customization['material_upgrade'])): ?>
                                                                <p><strong>Material Upgrade:</strong> <?php echo htmlspecialchars($customization['material_upgrade']); ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <?php if (!empty($customization['additional_features'])): ?>
                                                                <p><strong>Additional Features:</strong> <?php echo htmlspecialchars($customization['additional_features']); ?></p>
                                                            <?php endif; ?>
                                                            
                                                            <?php if (!empty($customization['notes'])): ?>
                                                                <p><strong>Notes:</strong> <?php echo htmlspecialchars($customization['notes']); ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if (!empty($customization['reference_image_path'])): ?>
                                                        <div class="mt-3">
                                                            <strong>Reference Image:</strong>
                                                            <div class="mt-2">
                                                                <img src="<?php echo htmlspecialchars($customization['reference_image_path']); ?>" 
                                                                     alt="Reference" class="img-thumbnail" style="max-width: 200px; max-height: 150px;">
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <div class="col-md-4">
                                                    <div class="border rounded p-3 bg-light">
                                                        <h6>Price Details</h6>
                                                        <table class="table table-borderless table-sm">
                                                            <tr>
                                                                <td>Base Price:</td>
                                                                <td class="text-end">ETB <?php echo number_format($customization['base_price'], 2); ?></td>
                                                            </tr>
                                                            <tr>
                                                                <td>Quantity:</td>
                                                                <td class="text-end"><?php echo $customization['quantity']; ?></td>
                                                            </tr>
                                                            <tr>
                                                                <td><strong>Adjusted Price:</strong></td>
                                                                <td class="text-end">
                                                                    <?php if ($customization['adjusted_price'] !== null): ?>
                                                                        <strong class="text-success">ETB <?php echo number_format($customization['adjusted_price'], 2); ?></strong>
                                                                    <?php else: ?>
                                                                        <span class="text-warning">Pending Approval</span>
                                                                        <?php if ($auth->getUserRole() === 'manager' || $auth->getUserRole() === 'admin'): ?>
                                                                            <div class="mt-2">
                                                                                <a href="/manager/approve-cost-item/<?php echo $customization['id']; ?>" 
                                                                                   class="btn btn-sm btn-success">Approve Cost</a>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                            <?php if ($customization['adjusted_price'] !== null): ?>
                                                                <tr class="table-success">
                                                                    <td><strong>Subtotal:</strong></td>
                                                                    <td class="text-end">
                                                                        <strong>ETB <?php echo number_format($customization['adjusted_price'] * $customization['quantity'], 2); ?></strong>
                                                                    </td>
                                                                </tr>
                                                            <?php endif; ?>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Order Total -->
                        <?php if ($order['total_amount'] > 0): ?>
                            <div class="row mt-4">
                                <div class="col-md-4 ms-auto">
                                    <div class="card bg-primary text-white">
                                        <div class="card-body text-center">
                                            <h5 class="card-title">Order Total</h5>
                                            <h2 class="mb-0">ETB <?php echo number_format($order['total_amount'], 2); ?></h2>
                                            <?php if ($order['deposit_amount'] > 0): ?>
                                                <div class="mt-2">
                                                    <small>Deposit Required: ETB <?php echo number_format($order['deposit_amount'], 2); ?></small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Payment Summary -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Payment Summary</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <strong>Total Cost:</strong> ETB <?php echo number_format($order['total_amount'], 2); ?>
                        </div>
                        <div class="col-md-4">
                            <strong>Deposit Paid:</strong> ETB <?php echo number_format($order['deposit_paid'] ?? 0, 2); ?>
                        </div>
                        <div class="col-md-4">
                            <strong>Remaining Balance:</strong> ETB <?php echo number_format(($order['total_amount'] - ($order['deposit_paid'] ?? 0)), 2); ?>
                        </div>
                    </div>
                    <?php if (($order['total_amount'] - ($order['deposit_paid'] ?? 0)) > 0 && $order['status'] !== 'cancelled'): ?>
                        <div class="mt-3">
                            <a href="/orders/pay-balance/<?php echo $order['id']; ?>" class="btn btn-success">
                                <i class="fas fa-credit-card"></i> Pay Remaining Balance
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Order Timeline -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Order Timeline</h5>
                </div>
                <div class="card-body">
                    <ul class="timeline list-unstyled">
                        <?php if (!empty($order['created_at'])): ?>
                            <li><strong><?php echo date('M j, Y', strtotime($order['created_at'])); ?>:</strong> Order Submitted</li>
                        <?php endif; ?>
                        <?php if (!empty($order['approved_at'])): ?>
                            <li><strong><?php echo date('M j, Y', strtotime($order['approved_at'])); ?>:</strong> Manager Approved Order</li>
                        <?php endif; ?>
                        <?php if (!empty($order['deposit_paid_at'])): ?>
                            <li><strong><?php echo date('M j, Y', strtotime($order['deposit_paid_at'])); ?>:</strong> Deposit Paid</li>
                        <?php endif; ?>
                        <?php if (!empty($order['production_started_at'])): ?>
                            <li><strong><?php echo date('M j, Y', strtotime($order['production_started_at'])); ?>:</strong> Production Started</li>
                        <?php endif; ?>
                        <?php if (!empty($order['production_completed_at'])): ?>
                            <li><strong><?php echo date('M j, Y', strtotime($order['production_completed_at'])); ?>:</strong> Production Completed</li>
                        <?php endif; ?>
                        <?php if (!empty($order['completed_at'])): ?>
                            <li><strong><?php echo date('M j, Y', strtotime($order['completed_at'])); ?>:</strong> Order Completed</li>
                        <?php endif; ?>
                        <?php if (!empty($order['cancelled_at'])): ?>
                            <li><strong><?php echo date('M j, Y', strtotime($order['cancelled_at'])); ?>:</strong> Order Cancelled</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <!-- Customer Actions -->
            <div class="mb-4 text-center">
                <a href="/orders/download-summary/<?php echo $order['id']; ?>" class="btn btn-outline-primary me-2"><i class="fas fa-download"></i> Download Summary</a>
                <a href="/orders/message-manager/<?php echo $order['id']; ?>" class="btn btn-outline-info me-2"><i class="fas fa-envelope"></i> Message Manager</a>
                <?php if (in_array($order['status'], ['pending_approval', 'pending', 'deposit_pending', 'cost_estimated'])): ?>
                    <a href="/orders/cancel/<?php echo $order['id']; ?>" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to cancel this order?')"><i class="fas fa-times"></i> Cancel Order</a>
                <?php endif; ?>
            </div>
            <div class="mt-4 text-center">
                <?php if ($auth->getUserRole() === 'manager' || $auth->getUserRole() === 'admin'): ?>
                    <?php if ($order['status'] === 'pending_cost_approval'): ?>
                        <a href="/manager/dashboard" class="btn btn-primary">Back to Dashboard</a>
                    <?php elseif ($order['status'] === 'cost_estimated'): ?>
                        <button class="btn btn-success" disabled>Waiting for Customer Deposit</button>
                        <a href="/manager/dashboard" class="btn btn-secondary">Back to Dashboard</a>
                    <?php endif; ?>
                <?php else: ?>
                    <?php if ($order['status'] === 'cost_estimated' && $order['deposit_amount'] > 0): ?>
                        <a href="/orders/pay-deposit/<?php echo $order['id']; ?>" class="btn btn-success btn-lg">
                            <i class="fas fa-credit-card"></i> Pay Deposit (ETB <?php echo number_format($order['deposit_amount'], 2); ?>)
                        </a>
                    <?php elseif ($order['status'] === 'deposit_paid'): ?>
                        <button class="btn btn-success btn-lg" disabled>
                            <i class="fas fa-check"></i> Deposit Paid - Order in Production
                        </button>
                    <?php endif; ?>
                    <a href="/orders/my-orders" class="btn btn-secondary">Back to My Orders</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>