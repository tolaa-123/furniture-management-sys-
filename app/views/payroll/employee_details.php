<?php require_once APP_DIR . '/includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">My Payroll Details</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="/">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="/payroll/dashboard">Payroll</a></li>
                        <li class="breadcrumb-item active">My Details</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Employee Information</h4>
                </div>
                <div class="card-body">
                    <div class="text-center">
                        <div class="avatar-lg mx-auto mb-4">
                            <span class="avatar-title rounded-circle bg-primary font-size-24">
                                <?php echo strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1)); ?>
                            </span>
                        </div>
                        <h5><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></h5>
                        <p class="text-muted"><?php echo htmlspecialchars($employee['position'] ?? 'Employee'); ?></p>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-borderless mb-0">
                            <tbody>
                                <tr>
                                    <td><strong>Employee ID:</strong></td>
                                    <td><?php echo htmlspecialchars($employee['employee_id']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Department:</strong></td>
                                    <td><?php echo htmlspecialchars($employee['department'] ?? 'N/A'); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Email:</strong></td>
                                    <td><?php echo htmlspecialchars($employee['email']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Role:</strong></td>
                                    <td class="text-capitalize"><?php echo htmlspecialchars($employee['role']); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Payroll History</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($payrollHistory)): ?>
                        <div class="table-responsive">
                            <table class="table table-centered table-nowrap">
                                <thead>
                                    <tr>
                                        <th>Period</th>
                                        <th>Date Range</th>
                                        <th>Working Days</th>
                                        <th>Working Hours</th>
                                        <th>Basic Salary</th>
                                        <th>Gross Salary</th>
                                        <th>Deductions</th>
                                        <th>Net Salary</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payrollHistory as $record): ?>
                                    <tr>
                                        <td>
                                            <h5 class="font-size-14 mb-1"><?php echo htmlspecialchars($record['period_name']); ?></h5>
                                        </td>
                                        <td><?php echo date('M j', strtotime($record['start_date'])); ?> - <?php echo date('M j, Y', strtotime($record['end_date'])); ?></td>
                                        <td><?php echo $record['working_days']; ?> days</td>
                                        <td><?php echo $record['working_hours']; ?> hours</td>
                                        <td>ETB <?php echo number_format($record['basic_salary'], 2); ?></td>
                                        <td>ETB <?php echo number_format($record['gross_salary'], 2); ?></td>
                                        <td>ETB <?php echo number_format($record['total_deductions'], 2); ?></td>
                                        <td>
                                            <strong class="text-success">ETB <?php echo number_format($record['net_salary'], 2); ?></strong>
                                        </td>
                                        <td>
                                            <?php 
                                            $statusClass = $record['status'] === 'paid' ? 'success' : 
                                                          ($record['status'] === 'approved' ? 'info' : 'warning');
                                            ?>
                                            <span class="badge bg-<?php echo $statusClass; ?> font-size-12">
                                                <?php echo ucfirst($record['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Summary Statistics -->
                        <div class="row mt-4">
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">Total Gross</h5>
                                        <h3 class="text-primary">
                                            ETB <?php 
                                            echo number_format(array_sum(array_column($payrollHistory, 'gross_salary')), 2);
                                            ?>
                                        </h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">Total Deductions</h5>
                                        <h3 class="text-warning">
                                            ETB <?php 
                                            echo number_format(array_sum(array_column($payrollHistory, 'total_deductions')), 2);
                                            ?>
                                        </h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">Total Net</h5>
                                        <h3 class="text-success">
                                            ETB <?php 
                                            echo number_format(array_sum(array_column($payrollHistory, 'net_salary')), 2);
                                            ?>
                                        </h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">Average Net</h5>
                                        <h3 class="text-info">
                                            ETB <?php 
                                            $avgNet = count($payrollHistory) > 0 ? 
                                                     array_sum(array_column($payrollHistory, 'net_salary')) / count($payrollHistory) : 0;
                                            echo number_format($avgNet, 2);
                                            ?>
                                        </h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bx bx-receipt text-muted" style="font-size: 3rem;"></i>
                            <h5 class="mt-3">No Payroll History</h5>
                            <p class="text-muted">No payroll records found for this employee</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Deduction Breakdown -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Deduction Breakdown</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5>Common Deductions</h5>
                            <ul class="list-group">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Income Tax (15%)
                                    <span class="badge bg-primary rounded-pill">Percentage</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Social Security (7%)
                                    <span class="badge bg-primary rounded-pill">Percentage</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Health Insurance (ETB 200)
                                    <span class="badge bg-secondary rounded-pill">Fixed</span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    Pension Contribution (5%)
                                    <span class="badge bg-primary rounded-pill">Percentage</span>
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h5>How Deductions Work</h5>
                            <div class="alert alert-info">
                                <h6 class="alert-heading">Calculation Process:</h6>
                                <ol class="mb-0">
                                    <li>Gross salary is calculated based on working days/hours</li>
                                    <li>Percentage deductions are calculated from gross salary</li>
                                    <li>Fixed deductions are applied as set amounts</li>
                                    <li>Total deductions are subtracted from gross salary</li>
                                    <li>Net salary is the final take-home amount</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once APP_DIR . '/includes/footer.php'; ?>