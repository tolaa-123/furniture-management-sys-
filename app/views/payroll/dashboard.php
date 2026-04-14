<?php require_once APP_DIR . '/includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Payroll Dashboard</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="/">Dashboard</a></li>
                        <li class="breadcrumb-item active">Payroll</li>
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
                            <p class="text-muted mb-1">Total Payroll Periods</p>
                            <h4 class="mb-0"><?php echo $stats['total_periods'] ?? 0; ?></h4>
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
                            <p class="text-muted mb-1">Paid Periods</p>
                            <h4 class="mb-0 text-success"><?php echo $stats['paid_periods'] ?? 0; ?></h4>
                        </div>
                        <div class="flex-shrink-0 align-self-center">
                            <div class="avatar-sm rounded-circle bg-success">
                                <span class="avatar-title">
                                    <i class="bx bx-check-circle font-size-24"></i>
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
                            <p class="text-muted mb-1">Total Paid Amount</p>
                            <h4 class="mb-0">ETB <?php echo number_format($stats['total_paid'] ?? 0, 2); ?></h4>
                        </div>
                        <div class="flex-shrink-0 align-self-center">
                            <div class="avatar-sm rounded-circle bg-info">
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
                            <p class="text-muted mb-1">Average Payroll</p>
                            <h4 class="mb-0">ETB <?php echo number_format($stats['average_payroll'] ?? 0, 2); ?></h4>
                        </div>
                        <div class="flex-shrink-0 align-self-center">
                            <div class="avatar-sm rounded-circle bg-warning">
                                <span class="avatar-title">
                                    <i class="bx bx-bar-chart font-size-24"></i>
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
                    <h4 class="card-title mb-0">Recent Payroll Periods</h4>
                    <div class="card-options">
                        <a href="/payroll/periods" class="btn btn-sm btn-primary">View All Periods</a>
                        <a href="/payroll/create-period" class="btn btn-sm btn-success">Create New Period</a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($periods)): ?>
                        <div class="table-responsive">
                            <table class="table table-centered table-nowrap">
                                <thead>
                                    <tr>
                                        <th>Period Name</th>
                                        <th>Date Range</th>
                                        <th>Status</th>
                                        <th>Employees</th>
                                        <th>Total Net Salary</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($periods as $period): ?>
                                    <tr>
                                        <td>
                                            <h5 class="font-size-14 mb-1"><?php echo htmlspecialchars($period['period_name']); ?></h5>
                                            <p class="text-muted mb-0">Created: <?php echo date('M j, Y', strtotime($period['created_at'])); ?></p>
                                        </td>
                                        <td>
                                            <?php echo date('M j', strtotime($period['start_date'])); ?> - 
                                            <?php echo date('M j, Y', strtotime($period['end_date'])); ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $statusClass = $period['status'] === 'paid' ? 'success' : 
                                                          ($period['status'] === 'approved' ? 'info' : 
                                                          ($period['status'] === 'calculated' ? 'warning' : 'secondary'));
                                            $statusText = ucfirst(str_replace('_', ' ', $period['status']));
                                            ?>
                                            <span class="badge bg-<?php echo $statusClass; ?> font-size-12">
                                                <?php echo $statusText; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $period['total_employees']; ?></td>
                                        <td>ETB <?php echo number_format($period['total_net_salary'], 2); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="/payroll/view/<?php echo $period['id']; ?>" class="btn btn-outline-primary">View</a>
                                                <?php if ($period['status'] === 'draft'): ?>
                                                    <a href="/payroll/calculate/<?php echo $period['id']; ?>" class="btn btn-outline-success">Calculate</a>
                                                <?php endif; ?>
                                                <?php if ($period['status'] === 'calculated'): ?>
                                                    <a href="/payroll/export/<?php echo $period['id']; ?>" class="btn btn-outline-info">Export</a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bx bx-spreadsheet text-muted" style="font-size: 3rem;"></i>
                            <h5 class="mt-3">No Payroll Periods</h5>
                            <p class="text-muted">No payroll periods have been created yet</p>
                            <a href="/payroll/create-period" class="btn btn-primary">Create First Period</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Quick Actions</h4>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <a href="/payroll/create-period" class="list-group-item list-group-item-action">
                            <i class="bx bx-plus-circle me-2"></i>Create New Payroll Period
                        </a>
                        <a href="/payroll/periods" class="list-group-item list-group-item-action">
                            <i class="bx bx-calendar me-2"></i>Manage Payroll Periods
                        </a>
                        <a href="/payroll/reports" class="list-group-item list-group-item-action">
                            <i class="bx bx-file me-2"></i>Payroll Reports
                        </a>
                        <a href="/payroll/employee-details" class="list-group-item list-group-item-action">
                            <i class="bx bx-user me-2"></i>My Payroll Details
                        </a>
                    </div>
                </div>
            </div>

            <!-- Payroll Summary -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Current Payroll Summary</h4>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="text-muted">Total Employees</h6>
                        <h3><?php echo array_sum(array_column($periods, 'total_employees')) ?: 0; ?></h3>
                    </div>
                    <div class="mb-3">
                        <h6 class="text-muted">Total Gross Salary</h6>
                        <h3>ETB <?php echo number_format(array_sum(array_column($periods, 'total_gross_salary')) ?: 0, 2); ?></h3>
                    </div>
                    <div class="mb-3">
                        <h6 class="text-muted">Total Deductions</h6>
                        <h3>ETB <?php echo number_format(array_sum(array_column($periods, 'total_deductions')) ?: 0, 2); ?></h3>
                    </div>
                    <div>
                        <h6 class="text-muted">Total Net Salary</h6>
                        <h3>ETB <?php echo number_format(array_sum(array_column($periods, 'total_net_salary')) ?: 0, 2); ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payroll Process Overview -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Payroll Process Overview</h4>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="py-3">
                                <div class="avatar-sm mx-auto mb-3">
                                    <span class="avatar-title rounded-circle bg-primary">
                                        <i class="bx bx-calendar-plus font-size-24"></i>
                                    </span>
                                </div>
                                <h5 class="font-size-15">1. Create Period</h5>
                                <p class="text-muted">Define payroll period dates</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="py-3">
                                <div class="avatar-sm mx-auto mb-3">
                                    <span class="avatar-title rounded-circle bg-warning">
                                        <i class="bx bx-calculator font-size-24"></i>
                                    </span>
                                </div>
                                <h5 class="font-size-15">2. Calculate Payroll</h5>
                                <p class="text-muted">Automatic salary calculations</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="py-3">
                                <div class="avatar-sm mx-auto mb-3">
                                    <span class="avatar-title rounded-circle bg-info">
                                        <i class="bx bx-check-shield font-size-24"></i>
                                    </span>
                                </div>
                                <h5 class="font-size-15">3. Review & Approve</h5>
                                <p class="text-muted">Verify calculations and approve</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="py-3">
                                <div class="avatar-sm mx-auto mb-3">
                                    <span class="avatar-title rounded-circle bg-success">
                                        <i class="bx bx-money font-size-24"></i>
                                    </span>
                                </div>
                                <h5 class="font-size-15">4. Process Payment</h5>
                                <p class="text-muted">Execute payroll payments</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once APP_DIR . '/includes/footer.php'; ?>