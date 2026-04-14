<?php require_once APP_DIR . '/includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Material Management Dashboard</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="/">Dashboard</a></li>
                        <li class="breadcrumb-item active">Materials</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-1">Total Materials</p>
                            <h4 class="mb-0"><?php echo $stats['total_materials'] ?? 0; ?></h4>
                        </div>
                        <div class="flex-shrink-0 align-self-center">
                            <div class="avatar-sm rounded-circle bg-primary">
                                <span class="avatar-title">
                                    <i class="bx bx-package font-size-24"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-1">Low Stock Items</p>
                            <h4 class="mb-0 text-warning"><?php echo $lowStockCount ?? 0; ?></h4>
                        </div>
                        <div class="flex-shrink-0 align-self-center">
                            <div class="avatar-sm rounded-circle bg-warning">
                                <span class="avatar-title">
                                    <i class="bx bx-error font-size-24"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-1">Active Alerts</p>
                            <h4 class="mb-0 text-danger"><?php echo $alertStats['active_alerts'] ?? 0; ?></h4>
                        </div>
                        <div class="flex-shrink-0 align-self-center">
                            <div class="avatar-sm rounded-circle bg-danger">
                                <span class="avatar-title">
                                    <i class="bx bx-bell font-size-24"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-1">Inventory Value</p>
                            <h4 class="mb-0">ETB <?php echo number_format($valuation['total_value'] ?? 0, 2); ?></h4>
                        </div>
                        <div class="flex-shrink-0 align-self-center">
                            <div class="avatar-sm rounded-circle bg-success">
                                <span class="avatar-title">
                                    <i class="bx bx-money font-size-24"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Active Alerts -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Active Low Stock Alerts</h4>
                    <div class="card-options">
                        <a href="/materials/alerts" class="btn btn-sm btn-primary">View All Alerts</a>
                        <a href="/materials/check-alerts" class="btn btn-sm btn-info">Check Alerts</a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($activeAlerts)): ?>
                        <div class="table-responsive">
                            <table class="table table-centered table-nowrap">
                                <thead>
                                    <tr>
                                        <th>Material</th>
                                        <th>Current Stock</th>
                                        <th>Minimum</th>
                                        <th>Level</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activeAlerts as $alert): ?>
                                    <tr>
                                        <td>
                                            <h5 class="font-size-14 mb-1"><?php echo htmlspecialchars($alert['material_name']); ?></h5>
                                            <p class="text-muted mb-0"><?php echo htmlspecialchars($alert['category_name'] ?? 'Uncategorized'); ?></p>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $alert['alert_level'] === 'critical' ? 'danger' : 'warning'; ?> font-size-12">
                                                <?php echo $alert['current_stock'] . ' ' . htmlspecialchars($alert['unit']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $alert['minimum_stock'] . ' ' . htmlspecialchars($alert['unit']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $alert['alert_level'] === 'critical' ? 'danger' : 'warning'; ?>">
                                                <?php echo ucfirst($alert['alert_level']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="/materials/resolve-alert/<?php echo $alert['id']; ?>" 
                                               class="btn btn-sm btn-success" 
                                               onclick="return confirm('Mark this alert as resolved?')">
                                                Resolve
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bx bx-check-circle text-success" style="font-size: 3rem;"></i>
                            <h5 class="mt-3">No Active Alerts</h5>
                            <p class="text-muted">All materials are at adequate stock levels</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Recent Material Transactions</h4>
                    <div class="card-options">
                        <a href="/materials" class="btn btn-sm btn-primary">View All Materials</a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($recentTransactions)): ?>
                        <div class="table-responsive">
                            <table class="table table-centered table-nowrap">
                                <thead>
                                    <tr>
                                        <th>Material</th>
                                        <th>Type</th>
                                        <th>Quantity</th>
                                        <th>Value</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentTransactions as $transaction): ?>
                                    <tr>
                                        <td>
                                            <h5 class="font-size-14 mb-1"><?php echo htmlspecialchars($transaction['material_name']); ?></h5>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $transaction['transaction_type'] === 'purchase' ? 'success' : 
                                                    ($transaction['transaction_type'] === 'usage' ? 'info' : 'warning'); 
                                            ?> font-size-12">
                                                <?php echo ucfirst($transaction['transaction_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $transaction['quantity']; ?></td>
                                        <td>ETB <?php echo number_format($transaction['total_cost'] ?? 0, 2); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($transaction['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bx bx-history text-muted" style="font-size: 3rem;"></i>
                            <h5 class="mt-3">No Recent Transactions</h5>
                            <p class="text-muted">No material transactions recorded yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Materials by Category -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Materials by Category</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($materialsByCategory)): ?>
                        <div class="table-responsive">
                            <table class="table table-centered table-nowrap">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Material</th>
                                        <th>Current Stock</th>
                                        <th>Available Stock</th>
                                        <th>Status</th>
                                        <th>Avg Cost</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($materialsByCategory as $material): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo htmlspecialchars($material['category_name'] ?? 'Uncategorized'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <h5 class="font-size-14 mb-1"><?php echo htmlspecialchars($material['name']); ?></h5>
                                            <p class="text-muted mb-0"><?php echo htmlspecialchars($material['unit']); ?></p>
                                        </td>
                                        <td><?php echo $material['current_stock'] . ' ' . htmlspecialchars($material['unit']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $material['available_stock'] <= $material['minimum_stock'] ? 'danger' : 
                                                    ($material['available_stock'] <= $material['reorder_point'] ? 'warning' : 'success'); 
                                            ?>">
                                                <?php echo $material['available_stock'] . ' ' . htmlspecialchars($material['unit']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $status = $material['stock_status'];
                                            $statusClass = $status === 'low_stock' ? 'danger' : 
                                                          ($status === 'reorder_soon' ? 'warning' : 'success');
                                            $statusText = $status === 'low_stock' ? 'Low Stock' : 
                                                         ($status === 'reorder_soon' ? 'Reorder Soon' : 'Adequate');
                                            ?>
                                            <span class="badge bg-<?php echo $statusClass; ?> font-size-12">
                                                <?php echo $statusText; ?>
                                            </span>
                                        </td>
                                        <td>ETB <?php echo number_format($material['average_cost'] ?? 0, 2); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="/materials/edit/<?php echo $material['id']; ?>" class="btn btn-outline-primary">Edit</a>
                                                <a href="/materials/add-stock/<?php echo $material['id']; ?>" class="btn btn-outline-success">Add Stock</a>
                                                <a href="/materials/transactions/<?php echo $material['id']; ?>" class="btn btn-outline-info">History</a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bx bx-package text-muted" style="font-size: 3rem;"></i>
                            <h5 class="mt-3">No Materials Found</h5>
                            <p class="text-muted">No materials have been added to the system yet</p>
                            <a href="/materials/create" class="btn btn-primary">Add New Material</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Quick Actions</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <a href="/materials/create" class="btn btn-primary btn-lg w-100 mb-3">
                                <i class="bx bx-plus"></i> Add New Material
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="/materials/categories" class="btn btn-info btn-lg w-100 mb-3">
                                <i class="bx bx-category"></i> Manage Categories
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="/materials/suppliers" class="btn btn-warning btn-lg w-100 mb-3">
                                <i class="bx bx-building"></i> Manage Suppliers
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="/materials/alerts" class="btn btn-danger btn-lg w-100 mb-3">
                                <i class="bx bx-bell"></i> View Alerts
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once APP_DIR . '/includes/footer.php'; ?>