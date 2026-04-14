<?php require_once dirname(__DIR__) . '/includes/header.php'; ?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/">Home</a></li>
                    <li class="breadcrumb-item"><a href="/production/manager-dashboard">Production Dashboard</a></li>
                    <li class="breadcrumb-item active">Assign Order</li>
                </ol>
            </nav>
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Assign Order for Production</h1>
                <a href="/production/manager-dashboard" class="btn btn-secondary">Back to Dashboard</a>
            </div>
            
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Order Details</h5>
                        </div>
                        <div class="card-body">
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
                                    <th>Total Amount:</th>
                                    <td><strong>ETB <?php echo number_format($order['total_amount'], 2); ?></strong></td>
                                </tr>
                                <tr>
                                    <th>Deposit Paid:</th>
                                    <td>ETB <?php echo number_format($order['deposit_paid'], 2); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Required Materials</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($requiredMaterials)): ?>
                                <p class="text-muted">No materials required for this order.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Material</th>
                                                <th>Required Quantity</th>
                                                <th>Available Stock</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($requiredMaterials as $materialData): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($materialData['material']['name']); ?></td>
                                                    <td><?php echo $materialData['total_quantity']; ?> <?php echo htmlspecialchars($materialData['material']['unit']); ?></td>
                                                    <td><?php echo number_format($materialData['availability']['available_stock'], 2); ?></td>
                                                    <td>
                                                        <?php if ($materialData['total_quantity'] <= $materialData['availability']['available_stock']): ?>
                                                            <span class="badge bg-success">Sufficient</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Insufficient</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="form-check mt-3">
                                    <input class="form-check-input" type="checkbox" id="confirmMaterials" name="confirm_materials" required>
                                    <label class="form-check-label" for="confirmMaterials">
                                        I confirm that all required materials are available and will be reserved for this order
                                    </label>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Assignment Details</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="/production/assign/<?php echo $order['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                                
                                <div class="mb-3">
                                    <label for="employee_id" class="form-label">Assign to Employee *</label>
                                    <select class="form-select" id="employee_id" name="employee_id" required>
                                        <option value="">Select an employee</option>
                                        <?php foreach ($employees as $employee): ?>
                                            <option value="<?php echo $employee['id']; ?>">
                                                <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="estimated_hours" class="form-label">Estimated Hours *</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="estimated_hours" 
                                           name="estimated_hours" 
                                           step="0.5" 
                                           min="0.5"
                                           placeholder="Enter estimated hours"
                                           required>
                                    <div class="form-text">Estimated time to complete this assignment</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="notes" class="form-label">Assignment Notes</label>
                                    <textarea class="form-control" 
                                              id="notes" 
                                              name="notes" 
                                              rows="4" 
                                              placeholder="Add any special instructions or notes..."></textarea>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg" id="assignButton" disabled>
                                        <i class="fas fa-user-plus"></i> Assign Order
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="card-title mb-0">Status Information</h6>
                        </div>
                        <div class="card-body">
                            <p class="mb-1"><strong>Current Status:</strong></p>
                            <p class="text-warning mb-2">Deposit Paid</p>
                            <p class="mb-1"><strong>After Assignment:</strong></p>
                            <p class="text-primary">In Production</p>
                            <hr>
                            <p class="small text-muted mb-0">
                                <i class="fas fa-info-circle"></i> 
                                Materials will be automatically reserved when assigned
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const confirmCheckbox = document.getElementById('confirmMaterials');
    const assignButton = document.getElementById('assignButton');
    const employeeSelect = document.getElementById('employee_id');
    const hoursInput = document.getElementById('estimated_hours');
    
    function checkFormValidity() {
        const isConfirmed = confirmCheckbox.checked;
        const hasEmployee = employeeSelect.value !== '';
        const hasHours = hoursInput.value !== '' && parseFloat(hoursInput.value) > 0;
        
        assignButton.disabled = !(isConfirmed && hasEmployee && hasHours);
    }
    
    confirmCheckbox.addEventListener('change', checkFormValidity);
    employeeSelect.addEventListener('change', checkFormValidity);
    hoursInput.addEventListener('input', checkFormValidity);
});
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>