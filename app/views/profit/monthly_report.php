<?php require_once APP_DIR . '/includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Monthly Profit Report</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="/">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="/profit/dashboard">Profit</a></li>
                        <li class="breadcrumb-item active">Monthly Report</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Monthly Profit Analysis</h4>
                    <div class="card-options">
                        <form method="GET" class="d-inline">
                            <select name="months" class="form-select form-select-sm d-inline-block w-auto" onchange="this.form.submit()">
                                <option value="6" <?php echo ($selectedMonths == 6) ? 'selected' : ''; ?>>Last 6 Months</option>
                                <option value="12" <?php echo ($selectedMonths == 12) ? 'selected' : ''; ?>>Last 12 Months</option>
                                <option value="24" <?php echo ($selectedMonths == 24) ? 'selected' : ''; ?>>Last 24 Months</option>
                            </select>
                        </form>
                        <a href="/profit/export?type=monthly&months=<?php echo $selectedMonths; ?>" class="btn btn-sm btn-primary">Export Report</a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($monthlyData)): ?>
                        <div class="table-responsive">
                            <table class="table table-centered table-nowrap">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>Orders</th>
                                        <th>Revenue</th>
                                        <th>Material Cost</th>
                                        <th>Labor Cost</th>
                                        <th>Total Cost</th>
                                        <th>Profit</th>
                                        <th>Margin %</th>
                                        <th>Trend</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($monthlyData as $month): ?>
                                    <tr>
                                        <td>
                                            <h5 class="font-size-14 mb-1"><?php echo htmlspecialchars($month['month_name']); ?></h5>
                                            <p class="text-muted mb-0"><?php echo $month['year']; ?></p>
                                        </td>
                                        <td><?php echo $month['total_orders']; ?></td>
                                        <td>ETB <?php echo number_format($month['total_revenue'], 2); ?></td>
                                        <td>ETB <?php echo number_format($month['total_material_cost'], 2); ?></td>
                                        <td>ETB <?php echo number_format($month['total_labor_cost'], 2); ?></td>
                                        <td>ETB <?php echo number_format($month['total_cost'], 2); ?></td>
                                        <td>
                                            <span class="text-<?php echo $month['total_profit'] >= 0 ? 'success' : 'danger'; ?>">
                                                ETB <?php echo number_format($month['total_profit'], 2); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $month['average_profit_margin'] >= 25 ? 'success' : 
                                                    ($month['average_profit_margin'] >= 15 ? 'warning' : 'danger'); 
                                            ?> font-size-12">
                                                <?php echo number_format($month['average_profit_margin'], 2); ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <i class="bx <?php 
                                                echo $month['total_profit'] >= 0 ? 'bx-trending-up text-success' : 'bx-trending-down text-danger'; 
                                            ?>"></i>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bx bx-calendar text-muted" style="font-size: 3rem;"></i>
                            <h5 class="mt-3">No Monthly Data</h5>
                            <p class="text-muted">No monthly profit summaries available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Summary Cards -->
    <div class="row">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex">
                        <div class="flex-grow-1">
                            <p class="text-muted mb-1">Total Months</p>
                            <h4 class="mb-0"><?php echo count($monthlyData); ?></h4>
                        </div>
                        <div class="flex-shrink-0 align-self-center">
                            <div class="avatar-sm rounded-circle bg-primary">
                                <span class="avatar-title">
                                    <i class="bx bx-calendar font-size-24"></i>
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
                            <h4 class="mb-0">ETB <?php 
                                echo number_format(array_sum(array_column($monthlyData, 'total_revenue')), 2); 
                            ?></h4>
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
                            <h4 class="mb-0">ETB <?php 
                                echo number_format(array_sum(array_column($monthlyData, 'total_profit')), 2); 
                            ?></h4>
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
                            <p class="text-muted mb-1">Avg Margin</p>
                            <h4 class="mb-0"><?php 
                                $avgMargin = count($monthlyData) > 0 ? array_sum(array_column($monthlyData, 'average_profit_margin')) / count($monthlyData) : 0;
                                echo number_format($avgMargin, 2); 
                            ?>%</h4>
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

    <!-- Profit Trend Analysis -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Profit Trend Analysis</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Best Performing Months</h5>
                            <?php 
                            $bestMonths = array_slice($monthlyData, 0, 3);
                            foreach ($bestMonths as $month): 
                            ?>
                            <div class="d-flex justify-content-between py-2 border-bottom">
                                <span><?php echo htmlspecialchars($month['month_name']); ?> <?php echo $month['year']; ?></span>
                                <span class="text-success">ETB <?php echo number_format($month['total_profit'], 2); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="col-md-6">
                            <h5>Areas for Improvement</h5>
                            <?php 
                            $worstMonths = array_slice(array_filter($monthlyData, function($m) { return $m['average_profit_margin'] < 20; }), 0, 3);
                            foreach ($worstMonths as $month): 
                            ?>
                            <div class="d-flex justify-content-between py-2 border-bottom">
                                <span><?php echo htmlspecialchars($month['month_name']); ?> <?php echo $month['year']; ?></span>
                                <span class="text-danger"><?php echo number_format($month['average_profit_margin'], 2); ?>% margin</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once APP_DIR . '/includes/footer.php'; ?>