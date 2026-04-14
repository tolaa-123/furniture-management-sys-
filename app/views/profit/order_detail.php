<?php require_once APP_DIR . '/includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Order Profit Details</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="/">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="/profit/dashboard">Profit</a></li>
                        <li class="breadcrumb-item active">Order Details</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Profit Analysis for Order #<?php echo htmlspecialchars($order['order_number']); ?></h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Order Information</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Order Number:</strong></td>
                                    <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Product:</strong></td>
                                    <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $order['status'] === 'completed' ? 'success' : 'warning'; 
                                        ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Order Date:</strong></td>
                                    <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h5>Profit Summary</h5>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <tr>
                                        <td><strong>Final Selling Price</strong></td>
                                        <td class="text-end">ETB <?php echo number_format($profit['final_selling_price'], 2); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Total Cost</strong></td>
                                        <td class="text-end">ETB <?php echo number_format($profit['total_cost'], 2); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Profit</strong></td>
                                        <td class="text-end">
                                            <span class="text-<?php echo $profit['profit'] >= 0 ? 'success' : 'danger'; ?>">
                                                <strong>ETB <?php echo number_format($profit['profit'], 2); ?></strong>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr class="table-info">
                                        <td><strong>Profit Margin</strong></td>
                                        <td class="text-end">
                                            <span class="badge bg-<?php 
                                                echo $profit['profit_margin_percentage'] >= 25 ? 'success' : 
                                                    ($profit['profit_margin_percentage'] >= 15 ? 'warning' : 'danger'); 
                                            ?> font-size-14">
                                                <?php echo number_format($profit['profit_margin_percentage'], 2); ?>%
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Cost Breakdown -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <h5>Cost Breakdown</h5>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Cost Component</th>
                                            <th class="text-end">Amount (ETB)</th>
                                            <th class="text-end">Percentage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Material Cost</td>
                                            <td class="text-end"><?php echo number_format($profit['material_cost'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format(($profit['material_cost'] / $profit['final_selling_price']) * 100, 2); ?>%</td>
                                        </tr>
                                        <tr>
                                            <td>Labor Cost</td>
                                            <td class="text-end"><?php echo number_format($profit['labor_cost'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format(($profit['labor_cost'] / $profit['final_selling_price']) * 100, 2); ?>%</td>
                                        </tr>
                                        <tr>
                                            <td>Production Time Cost</td>
                                            <td class="text-end"><?php echo number_format($profit['production_time_cost'], 2); ?></td>
                                            <td class="text-end"><?php echo number_format(($profit['production_time_cost'] / $profit['final_selling_price']) * 100, 2); ?>%</td>
                                        </tr>
                                        <tr class="table-primary">
                                            <td><strong>Total Cost</strong></td>
                                            <td class="text-end"><strong><?php echo number_format($profit['total_cost'], 2); ?></strong></td>
                                            <td class="text-end"><strong><?php echo number_format(($profit['total_cost'] / $profit['final_selling_price']) * 100, 2); ?>%</strong></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Profit Analysis -->
                    <div class="row mt-4">
                        <div class="col-md-4">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h3 class="card-title">ETB <?php echo number_format($profit['profit'], 2); ?></h3>
                                    <p class="card-text">Total Profit</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <h3 class="card-title"><?php echo number_format($profit['profit_margin_percentage'], 2); ?>%</h3>
                                    <p class="card-text">Profit Margin</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <h3 class="card-title"><?php echo number_format($profit['final_selling_price'] - $profit['total_cost'], 2); ?></h3>
                                    <p class="card-text">Net Profit</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <a href="/profit/dashboard" class="btn btn-secondary">Back to Dashboard</a>
                        <a href="/profit/export?type=detailed&order=<?php echo $order['id']; ?>" class="btn btn-primary">Export Details</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once APP_DIR . '/includes/footer.php'; ?>