<?php
// Employee authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}

// Get database connection
require_once __DIR__ . '/../../../config/db_config.php';

$employeeName = $_SESSION['user_name'] ?? 'Employee User';
$employeeId = $_SESSION['user_id'];

// Get dashboard statistics
$stats = [
    'assigned_tasks' => 0,
    'completed_tasks' => 0,
    'hours_today' => 0,
    'attendance_status' => 'Not Checked In'
];

try {
    // Assigned tasks
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM furn_production_tasks WHERE employee_id = ? AND status IN ('pending', 'in_progress')");
    $stmt->execute([$employeeId]);
    $stats['assigned_tasks'] = $stmt->fetchColumn();
    
    // Completed tasks this month
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM furn_production_tasks WHERE employee_id = ? AND status = 'completed' AND MONTH(completed_at) = MONTH(CURRENT_DATE())");
    $stmt->execute([$employeeId]);
    $stats['completed_tasks'] = $stmt->fetchColumn();
    
    // Hours worked today
    $stmt = $pdo->prepare("SELECT TIMESTAMPDIFF(HOUR, check_in_time, COALESCE(clock_out, NOW())) FROM furn_attendance WHERE employee_id = ? AND date = CURDATE()");
    $stmt->execute([$employeeId]);
    $hours = $stmt->fetchColumn();
    $stats['hours_today'] = $hours ?: 0;
    
    // Attendance status
    $stmt = $pdo->prepare("SELECT status, clock_out FROM furn_attendance WHERE employee_id = ? AND date = CURDATE()");
    $stmt->execute([$employeeId]);
    $attendance = $stmt->fetch();
    if ($attendance) {
        $stats['attendance_status'] = $attendance['clock_out'] ? 'Checked Out' : 'Checked In';
    }
} catch (PDOException $e) {
    error_log("Stats error: " . $e->getMessage());
}

// Get recent tasks
$recentTasks = [];
try {
    $stmt = $pdo->prepare("
        SELECT t.*, 
               CONCAT(u.first_name, ' ', u.last_name) as customer_name, 
               p.name as product_name
        FROM furn_production_tasks t
        LEFT JOIN furn_orders o ON t.order_id = o.id
        LEFT JOIN furn_users u ON o.customer_id = u.id
        LEFT JOIN furn_products p ON t.product_id = p.id
        WHERE t.employee_id = ?
        ORDER BY t.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$employeeId]);
    $recentTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Recent tasks error: " . $e->getMessage());
}

// Get recent messages
$recentMessages = [];
try {
    $stmt = $pdo->prepare("
        SELECT m.*, u.first_name, u.last_name
        FROM furn_messages m
        LEFT JOIN furn_users u ON m.sender_id = u.id
        WHERE m.receiver_id = ?
        ORDER BY m.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$employeeId]);
    $recentMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Recent messages error: " . $e->getMessage());
}

// Get my ratings summary
$myRatingStats = ['total' => 0, 'avg' => 0];
$myRecentRatings = [];
try {
    $r = $pdo->prepare("SELECT COUNT(*) as total, ROUND(AVG(rating),1) as avg FROM furn_ratings WHERE employee_id = ?");
    $r->execute([$employeeId]);
    $myRatingStats = $r->fetch(PDO::FETCH_ASSOC) ?: $myRatingStats;

    $stmt = $pdo->prepare("
        SELECT r.rating, r.review_text, r.created_at,
               o.order_number, o.furniture_name,
               CONCAT(u.first_name, ' ', u.last_name) as customer_name
        FROM furn_ratings r
        LEFT JOIN furn_orders o ON r.order_id = o.id
        LEFT JOIN furn_users u ON r.customer_id = u.id
        WHERE r.employee_id = ?
        ORDER BY r.created_at DESC LIMIT 3
    ");
    $stmt->execute([$employeeId]);
    $myRecentRatings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* ratings table may not exist */ }

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
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" aria-label="Toggle Menu">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay"></div>

    <?php include_once __DIR__ . '/../../includes/employee_sidebar.php'; ?>

    <?php 
    $pageTitle = 'Employee Dashboard';
    include_once __DIR__ . '/../../includes/employee_header.php'; 
    ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Welcome Message -->
        <div style="margin-bottom: 30px;">
            <h1 style="font-size: 28px; color: #2c3e50; margin: 0 0 10px 0;">Welcome back, <?php echo htmlspecialchars(explode(' ', $employeeName)[0]); ?>!</h1>
            <p style="color: #7f8c8d; margin: 0;">Here's your work overview for today</p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <?php
            $empCards = [
                [$stats['assigned_tasks'],   'Assigned Tasks',        BASE_URL.'/public/employee/tasks',      '#3498DB', 'fa-tasks',      null],
                [$stats['completed_tasks'],  'Completed This Month',  BASE_URL.'/public/employee/tasks',      '#27AE60', 'fa-check-circle',null],
                [$stats['hours_today'].'h',  'Hours Worked Today',    BASE_URL.'/public/employee/attendance', '#F39C12', 'fa-clock',      null],
                [$stats['attendance_status'],'Attendance Status',     BASE_URL.'/public/employee/attendance', $stats['attendance_status']==='Checked In'?'#27AE60':'#95A5A6', 'fa-user-check', null],
                [($myRatingStats['avg']?:'—'),'My Avg Rating ('.$myRatingStats['total'].' reviews)', BASE_URL.'/public/employee/profile', '#f39c12', 'fa-star', null],
            ];
            foreach ($empCards as [$v,$l,$href,$c,$i,$sub]): ?>
            <a href="<?php echo $href; ?>" style="text-decoration:none;">
                <div class="stat-card" style="border-left:4px solid <?php echo $c; ?>;cursor:pointer;transition:transform .15s,box-shadow .15s;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(0,0,0,0.12)';" onmouseout="this.style.transform='';this.style.boxShadow='';">
                    <div style="display:flex;justify-content:space-between;align-items:start;">
                        <div>
                            <div class="stat-value" style="color:<?php echo $c; ?>;<?php echo strlen((string)$v) > 8 ? 'font-size:16px;' : ''; ?>"><?php echo $v; ?></div>
                            <div class="stat-label"><?php echo $l; ?></div>
                        </div>
                        <div style="font-size:32px;color:<?php echo $c; ?>;opacity:.25;"><i class="fas <?php echo $i; ?>"></i></div>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- My Recent Ratings -->
        <?php if (!empty($myRecentRatings)): ?>
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-star"></i> Recent Customer Ratings</h2>
                <a href="<?php echo BASE_URL; ?>/public/employee/profile" class="btn-action btn-primary-custom">View All</a>
            </div>
            <?php foreach ($myRecentRatings as $rev): ?>
            <div style="padding: 14px; border-bottom: 1px solid #f0f0f0;">
                <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 8px;">
                    <div>
                        <strong><?php echo htmlspecialchars($rev['customer_name']); ?></strong>
                        <span style="color: #7f8c8d; font-size: 13px; margin-left: 8px;">Order #<?php echo htmlspecialchars($rev['order_number']); ?> — <?php echo htmlspecialchars($rev['furniture_name'] ?? ''); ?></span>
                    </div>
                    <div>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star" style="color: <?php echo $i <= $rev['rating'] ? '#f39c12' : '#ddd'; ?>;"></i>
                        <?php endfor; ?>
                        <small style="color: #aaa; margin-left: 6px;"><?php echo date('M j, Y', strtotime($rev['created_at'])); ?></small>
                    </div>
                </div>
                <?php if (!empty($rev['review_text'])): ?>
                    <p style="margin: 6px 0 0; color: #555; font-style: italic;">"<?php echo htmlspecialchars($rev['review_text']); ?>"</p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Recent Tasks -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-tasks"></i> Recent Tasks</h2>
                <a href="<?php echo BASE_URL; ?>/public/employee/tasks" class="btn-action btn-primary-custom">
                    <i class="fas fa-eye"></i> View All
                </a>
            </div>
            
            <?php if (empty($recentTasks)): ?>
                <p style="text-align: center; color: #7f8c8d; padding: 40px;">No tasks assigned yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Task ID</th>
                                <th>Product</th>
                                <th>Customer</th>
                                <th>Assigned Date</th>
                                <th>Deadline</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentTasks as $task): ?>
                                <tr>
                                    <td>#<?php echo str_pad($task['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo htmlspecialchars($task['product_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($task['customer_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($task['created_at'])); ?></td>
                                    <td><?php echo $task['deadline'] ? date('M d, Y', strtotime($task['deadline'])) : 'N/A'; ?></td>
                                    <td>
                                        <?php
                                        $badgeClass = 'secondary';
                                        if ($task['status'] === 'pending') $badgeClass = 'warning';
                                        elseif ($task['status'] === 'in_progress') $badgeClass = 'info';
                                        elseif ($task['status'] === 'completed') $badgeClass = 'success';
                                        ?>
                                        <span class="badge badge-<?php echo $badgeClass; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Messages -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-envelope"></i> Recent Messages</h2>
                <a href="<?php echo BASE_URL; ?>/public/employee/messages" class="btn-action btn-primary-custom">
                    <i class="fas fa-eye"></i> View All
                </a>
            </div>
            
            <?php if (empty($recentMessages)): ?>
                <p style="text-align: center; color: #7f8c8d; padding: 40px;">No messages yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>From</th>
                                <th>Subject</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentMessages as $msg): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(($msg['first_name'] ?? '') . ' ' . ($msg['last_name'] ?? '')); ?></td>
                                    <td><?php echo htmlspecialchars($msg['subject'] ?? 'No Subject'); ?></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($msg['created_at'])); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $msg['is_read'] ? 'secondary' : 'info'; ?>">
                                            <?php echo $msg['is_read'] ? 'Read' : 'Unread'; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Mobile Menu Script -->
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
