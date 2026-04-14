<?php require_once dirname(__DIR__) . '/includes/header.php'; ?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/">Home</a></li>
                    <li class="breadcrumb-item"><a href="/production/employee-dashboard">My Assignments</a></li>
                    <li class="breadcrumb-item active">Start Production Work</li>
                </ol>
            </nav>
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Start Production Work</h1>
                <a href="/production/employee-dashboard" class="btn btn-secondary">Back to Dashboard</a>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Assignment Confirmation</h5>
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
                                    <th>Assigned By:</th>
                                    <td><?php echo htmlspecialchars($assignment['assigned_by_first_name'] . ' ' . $assignment['assigned_by_last_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Assigned Date:</th>
                                    <td><?php echo date('F j, Y', strtotime($assignment['assigned_at'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Estimated Hours:</th>
                                    <td><?php echo $assignment['estimated_hours']; ?> hours</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6>Order Information</h6>
                            <table class="table table-borderless">
                                <tr>
                                    <th>Customer:</th>
                                    <td><?php echo htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Email:</th>
                                    <td><?php echo htmlspecialchars($assignment['email']); ?></td>
                                </tr>
                                <tr>
                                    <th>Order Status:</th>
                                    <td><span class="badge bg-primary">In Production</span></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <?php if ($assignment['notes']): ?>
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6>Assignment Notes:</h6>
                                <p class="text-muted"><?php echo nl2br(htmlspecialchars($assignment['notes'])); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info mt-4">
                        <h6><i class="fas fa-info-circle"></i> Production Guidelines</h6>
                        <ul class="mb-0">
                            <li>Ensure all required materials are available before starting</li>
                            <li>Follow quality standards and safety procedures</li>
                            <li>Track actual time spent on this assignment</li>
                            <li>Report any issues or delays immediately</li>
                        </ul>
                    </div>
                    
                    <form method="POST" action="/production/start-work/<?php echo $assignment['id']; ?>" class="mt-4">
                        <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                        
                        <div class="d-flex justify-content-between">
                            <a href="/production/employee-dashboard" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-play"></i> Start Production Work
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>