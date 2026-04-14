<?php require_once dirname(__DIR__) . '/includes/header.php'; ?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/">Home</a></li>
                    <li class="breadcrumb-item"><a href="/manager/dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item active">Verify Payment</li>
                </ol>
            </nav>
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Verify Payment Receipt</h1>
                <a href="/payments/dashboard" class="btn btn-secondary">Back to Dashboard</a>
            </div>
            
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Payment Receipt Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <th>Order Number:</th>
                                            <td><?php echo htmlspecialchars($receipt['order_number']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Customer:</th>
                                            <td><?php echo htmlspecialchars($receipt['first_name'] . ' ' . $receipt['last_name']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Email:</th>
                                            <td><?php echo htmlspecialchars($receipt['email']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Payment Type:</th>
                                            <td>
                                                <?php if ($receipt['payment_type'] === 'deposit'): ?>
                                                    <span class="badge bg-warning">Deposit Payment</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Final Payment</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <th>Amount:</th>
                                            <td><strong>ETB <?php echo number_format($receipt['amount'], 2); ?></strong></td>
                                        </tr>
                                        <tr>
                                            <th>Order Status:</th>
                                            <td>
                                                <?php
                                                $statusClass = '';
                                                $statusText = '';
                                                switch ($receipt['order_status']) {
                                                    case 'pending_cost_approval':
                                                        $statusClass = 'bg-warning';
                                                        $statusText = 'Pending Approval';
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
                                            <th>Total Order Amount:</th>
                                            <td>ETB <?php echo number_format($receipt['total_amount'], 2); ?></td>
                                        </tr>
                                        <?php if ($receipt['deposit_amount']): ?>
                                            <tr>
                                                <th>Required Deposit:</th>
                                                <td>ETB <?php echo number_format($receipt['deposit_amount'], 2); ?></td>
                                            </tr>
                                        <?php endif; ?>
                                        <?php if ($receipt['remaining_balance']): ?>
                                            <tr>
                                                <th>Remaining Balance:</th>
                                                <td>ETB <?php echo number_format($receipt['remaining_balance'], 2); ?></td>
                                            </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <h6>Payment Receipt Image:</h6>
                                <div class="text-center">
                                    <img src="<?php echo htmlspecialchars($receipt['receipt_image_path']); ?>" 
                                         alt="Payment Receipt" 
                                         class="img-fluid border rounded" 
                                         style="max-height: 400px; cursor: pointer;"
                                         onclick="enlargeImage(this)">
                                    <div class="mt-2">
                                        <a href="<?php echo htmlspecialchars($receipt['receipt_image_path']); ?>" 
                                           target="_blank" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-external-link-alt"></i> View Full Size
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Verification Actions</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="/payments/verify/<?php echo $receipt['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                                
                                <div class="mb-3">
                                    <label for="manager_notes" class="form-label">Manager Notes</label>
                                    <textarea class="form-control" 
                                              id="manager_notes" 
                                              name="manager_notes" 
                                              rows="4" 
                                              placeholder="Add notes about the verification..."></textarea>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" 
                                            name="action" 
                                            value="approve" 
                                            class="btn btn-success btn-lg">
                                        <i class="fas fa-check"></i> Approve Payment
                                    </button>
                                    <button type="submit" 
                                            name="action" 
                                            value="reject" 
                                            class="btn btn-danger btn-lg">
                                        <i class="fas fa-times"></i> Reject Payment
                                    </button>
                                </div>
                            </form>
                            
                            <div class="mt-4">
                                <h6>Verification Guidelines:</h6>
                                <ul class="small">
                                    <li>Check if amount matches required payment</li>
                                    <li>Verify receipt authenticity</li>
                                    <li>Confirm payment method details</li>
                                    <li>Add notes for customer reference</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="card-title mb-0">Quick Actions</h6>
                        </div>
                        <div class="card-body">
                            <a href="/orders/view/<?php echo $receipt['order_id']; ?>" class="btn btn-outline-primary btn-sm w-100 mb-2">
                                <i class="fas fa-eye"></i> View Order Details
                            </a>
                            <a href="/payments/dashboard" class="btn btn-outline-secondary btn-sm w-100">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Image Modal -->
<div class="modal fade" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Payment Receipt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="modalImage" src="" alt="Payment Receipt" class="img-fluid">
            </div>
        </div>
    </div>
</div>

<script>
function enlargeImage(img) {
    const modal = new bootstrap.Modal(document.getElementById('imageModal'));
    document.getElementById('modalImage').src = img.src;
    modal.show();
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>