<?php require_once APP_DIR . '/includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Add New Material</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="/">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="/materials">Materials</a></li>
                        <li class="breadcrumb-item active">Add New</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="/materials/create" id="materialForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? ($_SESSION['csrf_token'] ?? '')); ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Material Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="category_id" class="form-label">Category</label>
                                    <select class="form-select" id="category_id" name="category_id">
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>">
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="unit" class="form-label">Unit of Measurement <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="unit" name="unit" placeholder="e.g., meters, kg, pieces" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="supplier" class="form-label">Supplier</label>
                                    <select class="form-select" id="supplier" name="supplier">
                                        <option value="">Select Supplier</option>
                                        <?php foreach ($suppliers as $supplier): ?>
                                            <option value="<?php echo htmlspecialchars($supplier['name']); ?>">
                                                <?php echo htmlspecialchars($supplier['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="current_stock" class="form-label">Current Stock <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" class="form-control" id="current_stock" name="current_stock" value="0" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="minimum_stock" class="form-label">Minimum Stock Level <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" class="form-control" id="minimum_stock" name="minimum_stock" value="0" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="reorder_point" class="form-label">Reorder Point</label>
                                    <input type="number" step="0.01" class="form-control" id="reorder_point" name="reorder_point" value="0">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="average_cost" class="form-label">Average Cost (ETB)</label>
                                    <input type="number" step="0.01" class="form-control" id="average_cost" name="average_cost" value="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="storage_location" class="form-label">Storage Location</label>
                                    <input type="text" class="form-control" id="storage_location" name="storage_location" placeholder="e.g., Warehouse A-Section 1">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Additional Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                        </div>

                        <div class="d-flex justify-content-end">
                            <a href="/materials" class="btn btn-secondary me-2">Cancel</a>
                            <button type="submit" class="btn btn-primary">Add Material</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('materialForm').addEventListener('submit', function(e) {
    const currentStock = parseFloat(document.getElementById('current_stock').value);
    const minimumStock = parseFloat(document.getElementById('minimum_stock').value);
    const reorderPoint = parseFloat(document.getElementById('reorder_point').value);
    
    if (currentStock < 0) {
        e.preventDefault();
        alert('Current stock cannot be negative');
        return;
    }
    
    if (minimumStock < 0) {
        e.preventDefault();
        alert('Minimum stock cannot be negative');
        return;
    }
    
    if (reorderPoint < 0) {
        e.preventDefault();
        alert('Reorder point cannot be negative');
        return;
    }
    
    if (reorderPoint > minimumStock) {
        e.preventDefault();
        alert('Reorder point should be less than or equal to minimum stock level');
        return;
    }
});
</script>

<?php require_once APP_DIR . '/includes/footer.php'; ?>