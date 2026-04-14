<?php require_once APP_DIR . '/includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Attendance Dashboard</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="/">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="/attendance/check-in">Attendance</a></li>
                        <li class="breadcrumb-item active">Dashboard</li>
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
                            <p class="text-muted mb-1">Total Employees</p>
                            <h4 class="mb-0"><?php echo $stats['total_employees'] ?? 0; ?></h4>
                        </div>
                        <div class="flex-shrink-0 align-self-center">
                            <div class="avatar-sm rounded-circle bg-primary">
                                <span class="avatar-title">
                                    <i class="bx bx-user font-size-24"></i>
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
                            <p class="text-muted mb-1">Checked In Today</p>
                            <h4 class="mb-0 text-success"><?php echo $stats['checked_in_today'] ?? 0; ?></h4>
                        </div>
                        <div class="flex-shrink-0 align-self-center">
                            <div class="avatar-sm rounded-circle bg-success">
                                <span class="avatar-title">
                                    <i class="bx bx-log-in-circle font-size-24"></i>
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
                            <p class="text-muted mb-1">On Time Today</p>
                            <h4 class="mb-0 text-info"><?php echo $stats['on_time_today'] ?? 0; ?></h4>
                        </div>
                        <div class="flex-shrink-0 align-self-center">
                            <div class="avatar-sm rounded-circle bg-info">
                                <span class="avatar-title">
                                    <i class="bx bx-time font-size-24"></i>
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
                            <p class="text-muted mb-1">Late Today</p>
                            <h4 class="mb-0 text-warning"><?php echo $stats['late_today'] ?? 0; ?></h4>
                        </div>
                        <div class="flex-shrink-0 align-self-center">
                            <div class="avatar-sm rounded-circle bg-warning">
                                <span class="avatar-title">
                                    <i class="bx bx-alarm font-size-24"></i>
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
                    <h4 class="card-title mb-0">Today's Attendance Records</h4>
                    <div class="card-options">
                        <form method="GET" class="d-inline">
                            <input type="date" name="date" value="<?php echo $date; ?>" class="form-control form-control-sm d-inline w-auto">
                            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($todaysRecords)): ?>
                        <div class="table-responsive">
                            <table class="table table-centered table-nowrap">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Employee ID</th>
                                        <th>Department</th>
                                        <th>Check-in Time</th>
                                        <th>IP Address</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($todaysRecords as $record): ?>
                                    <tr>
                                        <td>
                                            <h5 class="font-size-14 mb-1"><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></h5>
                                        </td>
                                        <td><?php echo htmlspecialchars($record['employee_id']); ?></td>
                                        <td><?php echo htmlspecialchars($record['department'] ?? 'N/A'); ?></td>
                                        <td><?php echo date('g:i A', strtotime($record['check_in_time'])); ?></td>
                                        <td><?php echo htmlspecialchars($record['ip_address']); ?></td>
                                        <td>
                                            <?php if ($record['is_late']): ?>
                                                <span class="badge bg-warning font-size-12">Late (<?php echo $record['late_minutes']; ?>m)</span>
                                            <?php else: ?>
                                                <span class="badge bg-success font-size-12">On Time</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bx bx-user-check text-muted" style="font-size: 3rem;"></i>
                            <h5 class="mt-3">No Attendance Records</h5>
                            <p class="text-muted">No employees have checked in yet for <?php echo date('F j, Y', strtotime($date)); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Attendance Settings -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Current Settings</h4>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Check-in Window</label>
                        <p class="mb-1"><strong>Start:</strong> <?php echo date('g:i A', strtotime($settings['check_in_start_time'])); ?></p>
                        <p class="mb-0"><strong>End:</strong> <?php echo date('g:i A', strtotime($settings['check_in_end_time'])); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Late Threshold</label>
                        <p class="mb-0"><?php echo $settings['late_threshold_minutes']; ?> minutes after start time</p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Authorized IP</label>
                        <p class="mb-0 text-monospace"><?php echo htmlspecialchars($settings['company_ip_address']); ?></p>
                    </div>
                    
                    <a href="/attendance/settings" class="btn btn-primary btn-sm">Edit Settings</a>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Quick Actions</h4>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <a href="/attendance/check-in" class="list-group-item list-group-item-action">
                            <i class="bx bx-log-in-circle me-2"></i>Employee Check-in
                        </a>
                        <a href="/attendance/reports" class="list-group-item list-group-item-action">
                            <i class="bx bx-file me-2"></i>Attendance Reports
                        </a>
                        <a href="/attendance/settings" class="list-group-item list-group-item-action">
                            <i class="bx bx-cog me-2"></i>System Settings
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Employee Filter -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Employee Attendance Details</h4>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label for="employee" class="form-label">Select Employee</label>
                            <select class="form-select" id="employee" name="employee">
                                <option value="">All Employees</option>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee['id']; ?>" <?php echo ($selectedEmployee == $employee['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($employee['employee_id'] . ' - ' . $employee['first_name'] . ' ' . $employee['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="date_filter" class="form-label">Date</label>
                            <input type="date" class="form-control" id="date_filter" name="date" value="<?php echo $date; ?>">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary">Filter</button>
                            <a href="/attendance/dashboard" class="btn btn-secondary ms-2">Clear</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once APP_DIR . '/includes/footer.php'; ?>