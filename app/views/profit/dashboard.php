<?php require_once APP_DIR . '/includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Profit Dashboard</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="/">Dashboard</a></li>
                        <li class="breadcrumb-item active">Profit Analysis</li>
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
                            <p class="text-muted mb-1">Total Calculated Orders</p>
                            <h4 class="mb-0"><?php echo $stats['total_calculated_orders'] ?? 0; ?></h4>
                        </div>
                        <div class="flex-shrink-0 align-self-center">
                            <div class="avatar-sm rounded-circle bg-primary">
                                <span class="avatar-title">
                                    <i class="bx bx-calculator font-size-24"></i>
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
                            <p class="text-muted mb-1">Total Revenue</p>
                            <h4 class="mb-0">ETB <?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></h4>
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

        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-1">Total Profit</p>
                            <h4 class="mb-0">ETB <?php echo number_format($stats['total_profit'] ?? 0, 2); ?></h4>
                        </div>
                        <div class="flex-shrink-0 align-self-center">
                            <div class="avatar-sm rounded-circle bg-info">
                                <span class="avatar-title">
                                    <i class="bx bx-trending-up font-size-24"></i>
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
                            <p class="text-muted mb-1">Avg Profit Margin</p>
                            <h4 class="mb-0"><?php echo number_format($stats['average_profit_margin'] ?? 0, 2); ?>%</h4>
                        </div>
                        <div class="flex-shrink-0 align-self-center">
                            <div class="avatar-sm rounded-circle bg-warning">
                                <span class="avatar-title">
                                    <i class="bx bx-pie-chart font-size-24"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Recent Profit Calculations</h4>
                    <div class="card-options">
                        <a href="/profit/monthly-report" class="btn btn-sm btn-primary">Monthly Report</a>
                        <a href="/profit/product-analysis" class="btn btn-sm btn-info">Product Analysis</a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($recentProfits)): ?>
                        <div class="table-responsive">
                            <table class="table table-centered table-nowrap">
                                <thead>
                                    <tr>
                                        <th>Order</th>
                                        <th>Product</th>
                                        <th>Category</th>
                                        <th>Selling Price</th>
                                        <th>Total Cost</th>
                                        <th>Profit</th>
                                        <th>Margin %</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentProfits as $profit): ?>
                                    <tr>
                                        <td>
                                            <h5 class="font-size-14 mb-1"><?php echo htmlspecialchars($profit['order_number']); ?></h5>
                                        </td>
                                        <td><?php echo htmlspecialchars($profit['product_name']); ?></td>
                                        <td><?php echo htmlspecialchars($profit['category']); ?></td>
                                        <td>ETB <?php echo number_format($profit['final_selling_price'], 2); ?></td>
                                        <td>ETB <?php echo number_format($profit['total_cost'], 2); ?></td>
                                        <td>
                                            <span class="text-<?php echo $profit['profit'] >= 0 ? 'success' : 'danger'; ?>">
                                                ETB <?php echo number_format($profit['profit'], 2); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $profit['profit_margin_percentage'] >= 25 ? 'success' : 
                                                    ($profit['profit_margin_percentage'] >= 15 ? 'warning' : 'danger'); 
                                            ?> font-size-12">
                                                <?php echo number_format($profit['profit_margin_percentage'], 2); ?>%
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($profit['calculated_at'])); ?></td>
                                        <td>
                                            <a href="/profit/view-order/<?php echo $profit['order_id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bx bx-calculator text-muted" style="font-size: 3rem;"></i>
                            <h5 class="mt-3">No Profit Calculations</h5>
                            <p class="text-muted">No profit calculations have been performed yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Top Profitable Products -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Top Profitable Products</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($topProducts)): ?>
                        <div class="table-responsive">
                            <table class="table table-centered table-nowrap">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Profit</th>
                                        <th>Margin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topProducts as $product): ?>
                                    <tr>
                                        <td>
                                            <h5 class="font-size-14 mb-1"><?php echo htmlspecialchars($product['product_name']); ?></h5>
                                            <p class="text-muted mb-0"><?php echo htmlspecialchars($product['category']); ?></p>
                                        </td>
                                        <td class="text-success">ETB <?php echo number_format($product['total_profit'], 2); ?></td>
                                        <td><?php echo number_format($product['avg_profit_margin'], 2); ?>%</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <p class="text-muted">No product data available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Quick Actions</h4>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <a href="/profit/settings" class="list-group-item list-group-item-action">
                            <i class="bx bx-cog me-2"></i>Profit Settings
                        </a>
                        <a href="/profit/export?type=summary" class="list-group-item list-group-item-action">
                            <i class="bx bx-download me-2"></i>Export Summary
                        </a>
                        <a href="/profit/export?type=detailed" class="list-group-item list-group-item-action">
                            <i class="bx bx-file me-2"></i>Export Detailed
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cost Breakdown -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Cost Breakdown Analysis</h4>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="py-3">
                                <h3 class="text-primary">ETB <?php echo number_format($stats['total_material_cost'] ?? 0, 2); ?></h3>
                                <p class="text-muted">Total Material Cost</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="py-3">
                                <h3 class="text-info">ETB <?php echo number_format($stats['total_labor_cost'] ?? 0, 2); ?></h3>
                                <p class="text-muted">Total Labor Cost</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="py-3">
                                <h3 class="text-warning">ETB <?php echo number_format($stats['total_production_cost'] ?? 0, 2); ?></h3>
                                <p class="text-muted">Production Time Cost</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="py-3">
                                <h3 class="text-success">ETB <?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></h3>
                                <p class="text-muted">Total Revenue</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Profit Margin Indicator -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="text-center">
                                <h4>Overall Profit Margin: 
                                    <span class="badge bg-<?php 
                                        echo ($stats['average_profit_margin'] ?? 0) >= 25 ? 'success' : 
                                            (($stats['average_profit_margin'] ?? 0) >= 15 ? 'warning' : 'danger'); 
                                    ?> font-size-16">
                                        <?php echo number_format($stats['average_profit_margin'] ?? 0, 2); ?>%
                                    </span>
                                </h4>
                                <p class="text-muted">
                                    <?php 
                                    $margin = $stats['average_profit_margin'] ?? 0;
                                    if ($margin >= 25) {
                                        echo "Excellent profit margin! Well above target.";
                                    } elseif ($margin >= 15) {
                                        echo "Good profit margin. Meeting target range.";
                                    } else {
                                        echo "Profit margin below target. Consider cost optimization.";
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once APP_DIR . '/includes/footer.php'; ?>