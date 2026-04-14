<?php require_once APP_DIR . '/includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Add Stock to Material</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="/">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="/materials">Materials</a></li>
                        <li class="breadcrumb-item active">Add Stock</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Material: <?php echo htmlspecialchars($material['name']); ?></h4>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Current Stock</h5>
                                    <h3 class="text-primary"><?php echo $material['current_stock']; ?> <?php echo htmlspecialchars($material['unit']); ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Reserved Stock</h5>
                                    <h3 class="text-warning"><?php echo $material['reserved_stock']; ?> <?php echo htmlspecialchars($material['unit']); ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Available Stock</h5>
                                    <h3 class="text-success"><?php echo ($material['current_stock'] - $material['reserved_stock']); ?> <?php echo htmlspecialchars($material['unit']); ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Avg Cost</h5>
                                    <h3 class="text-info">ETB <?php echo number_format($material['average_cost'] ?? 0, 2); ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="/materials/add-stock/<?php echo $material['id']; ?>" id="addStockForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? $_SESSION['csrf_token'] ?? ''); ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="quantity" class="form-label">Quantity to Add <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" class="form-control" id="quantity" name="quantity" required>
                                    <div class="form-text">Current total: <?php echo $material['current_stock']; ?> <?php echo htmlspecialchars($material['unit']); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="unit_cost" class="form-label">Unit Cost (ETB)</label>
                                    <input type="number" step="0.01" class="form-control" id="unit_cost" name="unit_cost" value="<?php echo $material['last_purchase_price'] ?? $material['average_cost'] ?? 0; ?>">
                                    <div class="form-text">Leave blank if no cost information</div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Reason for adding stock, supplier information, etc."></textarea>
                        </div>

                        <div class="d-flex justify-content-end">
                            <a href="/materials" class="btn btn-secondary me-2">Cancel</a>
                            <button type="submit" class="btn btn-success">Add Stock</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('addStockForm').addEventListener('submit', function(e) {
    const quantity = parseFloat(document.getElementById('quantity').value);
    const unitCost = parseFloat(document.getElementById('unit_cost').value);
    
    if (quantity <= 0) {
        e.preventDefault();
        alert('Please enter a valid quantity greater than zero');
        return;
    }
    
    if (unitCost < 0) {
        e.preventDefault();
        alert('Unit cost cannot be negative');
        return;
    }
    
    const totalValue = quantity * unitCost;
    if (unitCost > 0) {
        if (!confirm(`This will add ${quantity} ${document.querySelector('#quantity').nextElementSibling.textContent.split(' ')[1]} at ETB ${unitCost.toFixed(2)} each, total value: ETB ${totalValue.toFixed(2)}. Continue?`)) {
            e.preventDefault();
        }
    }
});
</script>

<?php require_once APP_DIR . '/includes/footer.php'; ?>