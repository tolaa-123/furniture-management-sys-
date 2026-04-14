<?php require_once APP_DIR . '/includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Daily Attendance Check-in</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="/">Dashboard</a></li>
                        <li class="breadcrumb-item active">Attendance</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Check-in Status</h4>
                </div>
                <div class="card-body">
                    <?php if ($hasCheckedIn): ?>
                        <!-- Already checked in today -->
                        <div class="text-center py-5">
                            <div class="mb-4">
                                <i class="bx bx-check-circle text-success" style="font-size: 4rem;"></i>
                            </div>
                            <h3 class="text-success">You're Already Checked In!</h3>
                            <p class="text-muted">Your attendance for today has been recorded.</p>
                            
                            <div class="row mt-4">
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h5 class="card-title">Check-in Time</h5>
                                            <h3 class="text-primary"><?php echo date('g:i A', strtotime($todaysAttendance['check_in_time'])); ?></h3>
                                            <p class="text-muted"><?php echo date('F j, Y', strtotime($todaysAttendance['check_in_time'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h5 class="card-title">Status</h5>
                                            <?php if ($todaysAttendance['is_late']): ?>
                                                <h3 class="text-warning">Late</h3>
                                                <p class="text-muted"><?php echo $todaysAttendance['late_minutes']; ?> minutes late</p>
                                            <?php else: ?>
                                                <h3 class="text-success">On Time</h3>
                                                <p class="text-muted">Great job!</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Check-in form -->
                        <div class="text-center py-4">
                            <?php if ($canCheckIn): ?>
                                <!-- Eligible to check in -->
                                <div class="mb-4">
                                    <i class="bx bx-time-five text-primary" style="font-size: 4rem;"></i>
                                </div>
                                <h3 class="text-primary">Ready to Check In</h3>
                                <p class="text-muted">Click the button below to record your attendance</p>
                                
                                <div class="row mt-4">
                                    <div class="col-md-4">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h5 class="card-title">Current Time</h5>
                                                <h3 class="text-info"><?php echo date('g:i A'); ?></h3>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h5 class="card-title">Your IP</h5>
                                                <h3 class="text-info"><?php echo $clientIP; ?></h3>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h5 class="card-title">Status</h5>
                                                <h3 class="text-success">Authorized</h3>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <form method="POST" action="/attendance/process-check-in" class="mt-4">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SecurityUtil::getCsrfToken()); ?>">
                                    <button type="submit" class="btn btn-primary btn-lg px-5 py-3">
                                        <i class="bx bx-log-in-circle me-2"></i>Check In Now
                                    </button>
                                </form>
                            <?php else: ?>
                                <!-- Not eligible to check in -->
                                <div class="mb-4">
                                    <i class="bx bx-error text-danger" style="font-size: 4rem;"></i>
                                </div>
                                <h3 class="text-danger">Check-in Not Available</h3>
                                
                                <?php if (!$isWithinTime): ?>
                                    <p class="text-muted">Check-in is only allowed between <?php echo date('g:i A', strtotime($settings['check_in_start_time'])); ?> and <?php echo date('g:i A', strtotime($settings['check_in_end_time'])); ?></p>
                                    <div class="alert alert-warning">
                                        <strong>Current Time:</strong> <?php echo date('g:i A'); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!$isAuthorizedIP): ?>
                                    <p class="text-muted">Unauthorized IP address for check-in</p>
                                    <div class="alert alert-danger">
                                        <strong>Your IP:</strong> <?php echo $clientIP; ?><br>
                                        <strong>Required IP:</strong> <?php echo $settings['company_ip_address']; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="row mt-4">
                                    <div class="col-md-6">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h5 class="card-title">Check-in Window</h5>
                                                <p class="mb-1"><strong>Start:</strong> <?php echo date('g:i A', strtotime($settings['check_in_start_time'])); ?></p>
                                                <p class="mb-0"><strong>End:</strong> <?php echo date('g:i A', strtotime($settings['check_in_end_time'])); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <h5 class="card-title">Late Threshold</h5>
                                                <p class="mb-0">After <?php echo $settings['late_threshold_minutes']; ?> minutes</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Attendance Information -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Attendance Information</h4>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0 me-3">
                            <div class="avatar-sm rounded-circle bg-primary">
                                <span class="avatar-title">
                                    <i class="bx bx-calendar font-size-24"></i>
                                </span>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="mb-1">Today's Date</h5>
                            <p class="text-muted mb-0"><?php echo date('l, F j, Y'); ?></p>
                        </div>
                    </div>

                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0 me-3">
                            <div class="avatar-sm rounded-circle bg-success">
                                <span class="avatar-title">
                                    <i class="bx bx-time font-size-24"></i>
                                </span>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="mb-1">Current Time</h5>
                            <p class="text-muted mb-0" id="currentTime"><?php echo date('g:i:s A'); ?></p>
                        </div>
                    </div>

                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <div class="avatar-sm rounded-circle bg-info">
                                <span class="avatar-title">
                                    <i class="bx bx-user font-size-24"></i>
                                </span>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="mb-1">Your Role</h5>
                            <p class="text-muted mb-0 text-capitalize"><?php echo $userRole; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Quick Links</h4>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <?php if ($userRole === 'manager'): ?>
                            <a href="/attendance/dashboard" class="list-group-item list-group-item-action">
                                <i class="bx bx-bar-chart-alt me-2"></i>Attendance Dashboard
                            </a>
                            <a href="/attendance/reports" class="list-group-item list-group-item-action">
                                <i class="bx bx-file me-2"></i>Attendance Reports
                            </a>
                        <?php endif; ?>
                        <a href="/attendance/settings" class="list-group-item list-group-item-action">
                            <i class="bx bx-cog me-2"></i>Settings
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Update current time every second
setInterval(function() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    });
    document.getElementById('currentTime').textContent = timeString;
}, 1000);
</script>

<?php require_once APP_DIR . '/includes/footer.php'; ?>