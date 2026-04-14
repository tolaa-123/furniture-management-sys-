<?php require_once APP_DIR . '/includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Attendance Settings</h4>
                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        <li class="breadcrumb-item"><a href="/">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="/attendance/check-in">Attendance</a></li>
                        <li class="breadcrumb-item active">Settings</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Attendance Configuration</h4>
                </div>
                <div class="card-body">
                    <form method="POST" action="/attendance/settings">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SecurityUtil::getCsrfToken()); ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="check_in_start_time" class="form-label">Check-in Start Time</label>
                                    <input type="time" class="form-control" id="check_in_start_time" name="check_in_start_time" 
                                           value="<?php echo $settings['check_in_start_time'] ?? '07:00'; ?>" required>
                                    <div class="form-text">Earliest time employees can check in</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="check_in_end_time" class="form-label">Check-in End Time</label>
                                    <input type="time" class="form-control" id="check_in_end_time" name="check_in_end_time" 
                                           value="<?php echo $settings['check_in_end_time'] ?? '09:00'; ?>" required>
                                    <div class="form-text">Latest time employees can check in</div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="company_ip_address" class="form-label">Authorized Company IP</label>
                                    <input type="text" class="form-control" id="company_ip_address" name="company_ip_address" 
                                           value="<?php echo $settings['company_ip_address'] ?? '192.168.1.100'; ?>" required>
                                    <div class="form-text">IP address required for check-in</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="late_threshold_minutes" class="form-label">Late Threshold (Minutes)</label>
                                    <input type="number" class="form-control" id="late_threshold_minutes" name="late_threshold_minutes" 
                                           value="<?php echo $settings['late_threshold_minutes'] ?? '30'; ?>" min="1" max="120" required>
                                    <div class="form-text">Minutes after start time to mark as late</div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">Save Settings</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Current Configuration</h4>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6>Check-in Window</h6>
                        <p class="mb-1"><strong>Start:</strong> <?php echo date('g:i A', strtotime($settings['check_in_start_time'] ?? '07:00:00')); ?></p>
                        <p class="mb-0"><strong>End:</strong> <?php echo date('g:i A', strtotime($settings['check_in_end_time'] ?? '09:00:00')); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <h6>Security Settings</h6>
                        <p class="mb-1"><strong>Authorized IP:</strong> <?php echo $settings['company_ip_address'] ?? '192.168.1.100'; ?></p>
                        <p class="mb-0"><strong>Late Threshold:</strong> <?php echo $settings['late_threshold_minutes'] ?? '30'; ?> minutes</p>
                    </div>
                    
                    <div class="mb-3">
                        <h6>System Information</h6>
                        <p class="mb-1"><strong>Current Server Time:</strong> <?php echo date('g:i:s A'); ?></p>
                        <p class="mb-0"><strong>Your IP Address:</strong> <?php echo $_SERVER['REMOTE_ADDR'] ?? 'Unknown'; ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once APP_DIR . '/includes/footer.php'; ?>