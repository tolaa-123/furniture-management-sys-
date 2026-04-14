<?php require_once dirname(__DIR__) . '/includes/header.php'; ?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/">Home</a></li>
                    <li class="breadcrumb-item"><a href="/orders/my-orders">My Orders</a></li>
                    <li class="breadcrumb-item"><a href="/orders/view/<?php echo $order['id']; ?>">Order <?php echo htmlspecialchars($order['order_number']); ?></a></li>
                    <li class="breadcrumb-item active">Upload Final Payment Receipt</li>
                </ol>
            </nav>
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Upload Final Payment Receipt</h1>
                <a href="/orders/view/<?php echo $order['id']; ?>" class="btn btn-secondary">Back to Order</a>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Final Payment Details</h5>
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
                                    <th>Total Amount:</th>
                                    <td><strong>ETB <?php echo number_format($order['total_amount'], 2); ?></strong></td>
                                </tr>
                                <tr>
                                    <th>Deposit Paid:</th>
                                    <td>ETB <?php echo number_format($order['deposit_paid'], 2); ?></td>
                                </tr>
                                <tr>
                                    <th>Remaining Balance:</th>
                                    <td><strong class="text-danger">ETB <?php echo number_format($order['remaining_balance'], 2); ?></strong></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <div class="alert alert-success">
                                <h6><i class="fas fa-check-circle"></i> Order Ready for Final Payment</h6>
                                <p class="mb-0">Your order has been completed and is ready for delivery. Please pay the remaining balance to complete your order.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Upload Final Payment Receipt</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="/payments/upload-final/<?php echo $order['id']; ?>" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                        
                        <div class="mb-3">
                            <label for="amount" class="form-label">Payment Amount (ETB) *</label>
                            <input type="number" 
                                   class="form-control form-control-lg" 
                                   id="amount" 
                                   name="amount" 
                                   step="0.01" 
                                   min="0.01"
                                   max="<?php echo $order['remaining_balance']; ?>"
                                   value="<?php echo $order['remaining_balance']; ?>"
                                   required>
                            <div class="form-text">Enter the amount you're paying (up to ETB <?php echo number_format($order['remaining_balance'], 2); ?>)</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="receipt_image" class="form-label">Payment Receipt Image *</label>
                            <input type="file" 
                                   class="form-control" 
                                   id="receipt_image" 
                                   name="receipt_image" 
                                   accept="image/*"
                                   required>
                            <div class="form-text">Upload a clear image of your payment receipt (JPG, PNG, GIF - max 5MB)</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Additional Notes (Optional)</label>
                            <textarea class="form-control" 
                                      id="notes" 
                                      name="notes" 
                                      rows="3" 
                                      placeholder="Add any additional information about your payment..."></textarea>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="/orders/view/<?php echo $order['id']; ?>" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-upload"></i> Upload Final Receipt
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Payment Methods</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="border rounded p-3 text-center">
                                <i class="fas fa-building fa-2x text-primary mb-2"></i>
                                <h6>Bank Transfer</h6>
                                <p class="small mb-0">Transfer to our business account</p>
                                <p class="small text-muted mb-0">Account: 1234567890</p>
                                <p class="small text-muted">Bank: Commercial Bank of Ethiopia</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="border rounded p-3 text-center">
                                <i class="fas fa-mobile-alt fa-2x text-success mb-2"></i>
                                <h6>Mobile Money</h6>
                                <p class="small mb-0">Pay via mobile banking</p>
                                <p class="small text-muted mb-0">Phone: +251 911 123 456</p>
                                <p class="small text-muted">Telecom: Ethio Telecom</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="border rounded p-3 text-center">
                                <i class="fas fa-qrcode fa-2x text-info mb-2"></i>
                                <h6>QR Payment</h6>
                                <p class="small mb-0">Scan QR code to pay</p>
                                <p class="small text-muted">Available at delivery</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>