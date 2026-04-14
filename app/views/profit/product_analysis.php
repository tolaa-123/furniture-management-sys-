<?php require_once APP_DIR . '/includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Product Profit Analysis</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="/">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="/profit/dashboard">Profit</a></li>
                        <li class="breadcrumb-item active">Product Analysis</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Product Profitability Analysis</h4>
                    <div class="card-options">
                        <form method="GET" class="d-inline">
                            <select name="product" class="form-select form-select-sm d-inline-block w-auto" onchange="this.form.submit()">
                                <option value="">All Products</option>
                                <?php foreach ($allProducts as $product): ?>
                                    <option value="<?php echo $product['id']; ?>" <?php echo ($selectedProduct == $product['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($product['name']); ?> (<?php echo htmlspecialchars($product['category']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        <a href="/profit/export?type=summary" class="btn btn-sm btn-primary">Export Summary</a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($products)): ?>
                        <div class="table-responsive">
                            <table class="table table-centered table-nowrap">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Category</th>
                                        <th>Orders Sold</th>
                                        <th>Total Revenue</th>
                                        <th>Total Cost</th>
                                        <th>Total Profit</th>
                                        <th>Avg Profit</th>
                                        <th>Avg Margin %</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td>
                                            <h5 class="font-size-14 mb-1"><?php echo htmlspecialchars($product['product_name']); ?></h5>
                                        </td>
                                        <td><?php echo htmlspecialchars($product['category']); ?></td>
                                        <td><?php echo $product['total_sold']; ?></td>
                                        <td>ETB <?php echo number_format($product['total_revenue'], 2); ?></td>
                                        <td>ETB <?php echo number_format($product['total_cost'], 2); ?></td>
                                        <td>
                                            <span class="text-<?php echo $product['total_profit'] >= 0 ? 'success' : 'danger'; ?>">
                                                ETB <?php echo number_format($product['total_profit'], 2); ?>
                                            </span>
                                        </td>
                                        <td>ETB <?php echo number_format($product['average_profit'], 2); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $product['average_profit_margin'] >= 25 ? 'success' : 
                                                    ($product['average_profit_margin'] >= 15 ? 'warning' : 'danger'); 
                                            ?> font-size-12">
                                                <?php echo number_format($product['average_profit_margin'], 2); ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <a href="/profit/view-order/<?php echo $product['product_id']; ?>" class="btn btn-sm btn-outline-primary">View Details</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bx bx-bar-chart text-muted" style="font-size: 3rem;"></i>
                            <h5 class="mt-3">No Product Data</h5>
                            <p class="text-muted">No profit calculations available for products</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Profitability Insights -->
    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Top Performing Products</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($products)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Profit Margin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $topPerformers = array_slice($products, 0, 5);
                                    foreach ($topPerformers as $product): 
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                        <td>
                                            <span class="badge bg-success">
                                                <?php echo number_format($product['average_profit_margin'], 2); ?>%
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No data available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Profit Margin Distribution</h4>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="py-2">
                                <h4 class="text-success"><?php echo count(array_filter($products, function($p) { return $p['average_profit_margin'] >= 25; })); ?></h4>
                                <p class="text-muted small">Excellent (25%+)</p>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="py-2">
                                <h4 class="text-warning"><?php echo count(array_filter($products, function($p) { return $p['average_profit_margin'] >= 15 && $p['average_profit_margin'] < 25; })); ?></h4>
                                <p class="text-muted small">Good (15-25%)</p>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="py-2">
                                <h4 class="text-danger"><?php echo count(array_filter($products, function($p) { return $p['average_profit_margin'] < 15; })); ?></h4>
                                <p class="text-muted small">Needs Attention (<15%)</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once APP_DIR . '/includes/footer.php'; ?>