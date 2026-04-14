<?php require_once dirname(__DIR__) . '/includes/header.php'; ?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/">Home</a></li>
                    <li class="breadcrumb-item"><a href="/production/employee-dashboard">My Assignments</a></li>
                    <li class="breadcrumb-item active">Complete Production Work</li>
                </ol>
            </nav>
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Complete Production Work</h1>
                <a href="/production/employee-dashboard" class="btn btn-secondary">Back to Dashboard</a>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Work Completion Form</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Assignment Details</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <th>Order Number:</th>
                                    <td><?php echo htmlspecialchars($assignment['order_number']); ?></td>
                                </tr>
                                <tr>
                                    <th>Started:</th>
                                    <td><?php echo date('F j, Y g:i A', strtotime($assignment['started_at'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Estimated Hours:</th>
                                    <td><?php echo $assignment['estimated_hours']; ?> hours</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Time Tracking</h6>
                            <div class="border rounded p-3 bg-light">
                                <p class="mb-1"><strong>Start Time:</strong> <?php echo date('M j, Y g:i A', strtotime($assignment['started_at'])); ?></p>
                                <p class="mb-0"><strong>Current Time:</strong> <?php echo date('M j, Y g:i A'); ?></p>
                                <hr>
                                <p class="mb-0">
                                    <strong>Elapsed Time:</strong> 
                                    <?php 
                                    $start = new DateTime($assignment['started_at']);
                                    $now = new DateTime();
                                    $interval = $start->diff($now);
                                    echo $interval->h . ' hours ' . $interval->i . ' minutes';
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <form method="POST" action="/production/complete-work/<?php echo $assignment['id']; ?>" class="mt-4">
                        <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="actual_hours" class="form-label">Actual Hours Worked *</label>
                                    <input type="number" 
                                           class="form-control form-control-lg" 
                                           id="actual_hours" 
                                           name="actual_hours" 
                                           step="0.25" 
                                           min="0.25"
                                           value="<?php echo $assignment['estimated_hours']; ?>"
                                           required>
                                    <div class="form-text">
                                        Estimated: <?php echo $assignment['estimated_hours']; ?> hours | 
                                        Difference: <span id="hourDifference">0</span> hours
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="completion_notes" class="form-label">Completion Notes</label>
                            <textarea class="form-control" 
                                      id="completion_notes" 
                                      name="completion_notes" 
                                      rows="4" 
                                      placeholder="Add notes about the work completed, any issues encountered, quality observations, etc..."></textarea>
                        </div>
                        
                        <div class="alert alert-warning">
                            <h6><i class="fas fa-exclamation-triangle"></i> Quality Check</h6>
                            <p class="mb-0">Please ensure all work meets quality standards before marking as complete.</p>
                        </div>
                        
                        <div class="d-flex justify-content-between">
                            <a href="/production/employee-dashboard" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-check"></i> Complete Work
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const estimatedHours = <?php echo $assignment['estimated_hours']; ?>;
    const actualHoursInput = document.getElementById('actual_hours');
    const hourDifference = document.getElementById('hourDifference');
    
    function updateHourDifference() {
        const actualHours = parseFloat(actualHoursInput.value) || 0;
        const difference = actualHours - estimatedHours;
        hourDifference.textContent = difference.toFixed(2);
        hourDifference.className = difference > 0 ? 'text-danger' : difference < 0 ? 'text-success' : 'text-muted';
    }
    
    actualHoursInput.addEventListener('input', updateHourDifference);
    updateHourDifference();
});
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>