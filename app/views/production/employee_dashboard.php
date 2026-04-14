<?php
// Employee authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}

// Get database connection
require_once __DIR__ . '/../../../config/db_config.php';

$employeeName = $_SESSION['user_name'] ?? 'Employee User';

// Fetch employee statistics
$stats = [
    'pending_tasks' => 0,
    'in_progress_tasks' => 0,
    'completed_tasks' => 0,
    'today_attendance' => false
];

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM furn_production_tasks WHERE employee_id = ? AND status = 'pending'");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['pending_tasks'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM furn_production_tasks WHERE employee_id = ? AND status = 'in_progress'");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['in_progress_tasks'] = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM furn_production_tasks WHERE employee_id = ? AND status = 'completed'");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['completed_tasks'] = $stmt->fetchColumn();
    
    // Check today's attendance
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM furn_attendance WHERE user_id = ? AND DATE(check_in_time) = CURDATE()");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['today_attendance'] = $stmt->fetchColumn() > 0;
} catch (PDOException $e) {
    error_log("Employee dashboard stats error: " . $e->getMessage());
}

// Fetch current assignments
$currentAssignments = [];
$inProgressAssignments = [];
$completedAssignments = [];

try {
    $stmt = $pdo->prepare("
        SELECT t.*, o.order_number, o.furniture_name
        FROM furn_production_tasks t
        LEFT JOIN furn_orders o ON t.order_id = o.id
        WHERE t.employee_id = ? AND t.status = 'pending'
        ORDER BY t.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $currentAssignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("
        SELECT t.*, o.order_number, o.furniture_name
        FROM furn_production_tasks t
        LEFT JOIN furn_orders o ON t.order_id = o.id
        WHERE t.employee_id = ? AND t.status = 'in_progress'
        ORDER BY t.started_at DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $inProgressAssignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("
        SELECT t.*, o.order_number, o.furniture_name
        FROM furn_production_tasks t
        LEFT JOIN furn_orders o ON t.order_id = o.id
        WHERE t.employee_id = ? AND t.status = 'completed'
        ORDER BY t.completed_at DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $completedAssignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Assignments error: " . $e->getMessage());
}

$pageTitle = 'Employee Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - FurnitureCraft</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/css/admin-responsive.css">
    <style>
        .top-header { position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; width: 100% !important; z-index: 1998 !important; }
        @media (min-width: 1024px) { .top-header { left: 260px !important; width: calc(100% - 260px) !important; } }
        /* Page-specific styles */
        .kpi-grid { display: grid; grid-template-columns: 1fr; gap: 15px; margin-bottom: 25px; }
        .kpi-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); transition: transform 0.3s; }
        .kpi-card:hover { transform: translateY(-5px); }
        .kpi-icon { width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; margin-bottom: 12px; }
        .kpi-icon.blue { background: rgba(52, 152, 219, 0.1); color: #3498DB; }
        .kpi-icon.green { background: rgba(39, 174, 96, 0.1); color: #27AE60; }
        .kpi-icon.orange { background: rgba(243, 156, 18, 0.1); color: #F39C12; }
        .empty-state { text-align: center; padding: 40px; color: #7f8c8d; }
        
        @media (min-width: 768px) {
            .kpi-grid { grid-template-columns: repeat(2, 1fr); }
            .kpi-icon { width: 50px; height: 50px; font-size: 24px; }
            .kpi-value { font-size: 28px; }
        }
        
        @media (min-width: 1024px) {
            .kpi-grid { grid-template-columns: repeat(3, 1fr); gap: 20px; }
            .kpi-value { font-size: 32px; }
            .kpi-label { font-size: 14px; }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" aria-label="Toggle Menu">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay (for mobile) -->
    <div class="sidebar-overlay"></div>

    <!-- Sidebar -->
    <?php include_once __DIR__ . '/../../includes/employee_sidebar.php'; ?>

    <!-- Top Header -->
    <?php 
    $pageTitle = 'Employee Dashboard';
    include_once __DIR__ . '/../../includes/employee_header.php'; 
    ?>

    <!-- Main Content -->
    <div class="main-content">
        <h2 style="margin-bottom: 30px; color: #2c3e50;">Welcome, <?php echo htmlspecialchars($employeeName); ?>!</h2>
        
        <!-- KPI Cards -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-icon orange"><i class="fas fa-clock"></i></div>
                <div class="kpi-value"><?php echo number_format($stats['pending_tasks']); ?></div>
                <div class="kpi-label">Pending Tasks</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon blue"><i class="fas fa-hammer"></i></div>
                <div class="kpi-value"><?php echo number_format($stats['in_progress_tasks']); ?></div>
                <div class="kpi-label">In Progress</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="kpi-value"><?php echo number_format($stats['completed_tasks']); ?></div>
                <div class="kpi-label">Completed Tasks</div>
            </div>
        </div>

        <!-- Current Assignments -->
        <div class="section-card">
            <div class="section-title"><i class="fas fa-clipboard-list me-2"></i>Current Assignments</div>
            <?php if (empty($currentAssignments)): ?>
                <div class="empty-state">
                    <i class="fas fa-clipboard-list" style="font-size: 2rem; color: #ddd;"></i>
                    <p>No current assignments</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Order Number</th>
                                <th>Furniture</th>
                                <th>Task Type</th>
                                <th>Estimated Hours</th>
                                <th>Assigned Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($currentAssignments as $assignment): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($assignment['order_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($assignment['furniture_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($assignment['task_type'] ?? 'Production'); ?></td>
                                <td><?php echo $assignment['estimated_hours'] ?? 'N/A'; ?> hours</td>
                                <td><?php echo date('M j, Y', strtotime($assignment['created_at'])); ?></td>
                                <td>
                                    <button class="btn-action btn-primary-custom" onclick="startTask(<?php echo $assignment['id']; ?>)">
                                        <i class="fas fa-play"></i> Start Work
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- In Progress Assignments -->
        <div class="section-card">
            <div class="section-title"><i class="fas fa-hammer me-2"></i>In Progress</div>
            <?php if (empty($inProgressAssignments)): ?>
                <div class="empty-state">
                    <i class="fas fa-hammer" style="font-size: 2rem; color: #ddd;"></i>
                    <p>No assignments in progress</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Order Number</th>
                                <th>Furniture</th>
                                <th>Started At</th>
                                <th>Estimated Hours</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inProgressAssignments as $assignment): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($assignment['order_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($assignment['furniture_name'] ?? 'N/A'); ?></td>
                                <td><?php echo date('M j, Y g:i A', strtotime($assignment['started_at'])); ?></td>
                                <td><?php echo $assignment['estimated_hours'] ?? 'N/A'; ?> hours</td>
                                <td>
                                    <button class="btn-action btn-success-custom" onclick="completeTask(<?php echo $assignment['id']; ?>)">
                                        <i class="fas fa-check"></i> Complete Work
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Completed Assignments -->
        <div class="section-card">
            <div class="section-title"><i class="fas fa-check-circle me-2"></i>Completed Assignments</div>
            <?php if (empty($completedAssignments)): ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle" style="font-size: 2rem; color: #ddd;"></i>
                    <p>No completed assignments yet</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Order Number</th>
                                <th>Furniture</th>
                                <th>Completed Date</th>
                                <th>Actual Hours</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($completedAssignments as $assignment): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($assignment['order_number']); ?></strong></td>
                                <td><?php echo htmlspecialchars($assignment['furniture_name'] ?? 'N/A'); ?></td>
                                <td><?php echo date('M j, Y', strtotime($assignment['completed_at'])); ?></td>
                                <td><?php echo $assignment['actual_hours'] ?? 'N/A'; ?> hours</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function startTask(taskId) {
            if (confirm('Start working on this task?')) {
                window.location.href = '<?php echo BASE_URL; ?>/public/employee/tasks?action=start&id=' + taskId;
            }
        }

        function completeTask(taskId) {
            const actualHours = prompt('Enter actual hours worked:', '0');
            if (actualHours !== null && actualHours.trim() !== '') {
                window.location.href = '<?php echo BASE_URL; ?>/public/employee/tasks?action=complete&id=' + taskId + '&hours=' + encodeURIComponent(actualHours);
            }
        }
    </script>

    <!-- Mobile Menu Script -->
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>