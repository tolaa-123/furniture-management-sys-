<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}

require_once __DIR__ . '/../../../config/db_config.php';
require_once __DIR__ . '/../../models/EmployeePerformanceModel.php';

$managerName = $_SESSION['user_name'] ?? 'Manager User';
$months = intval($_GET['months'] ?? 3);

$perfModel = new EmployeePerformanceModel();
$employeeScores = $perfModel->getAllEmployeeScores($months);

// Get summary stats
$totalEmployees = count($employeeScores);
$excellentCount = count(array_filter($employeeScores, fn($e) => $e['total_score'] >= 80));
$goodCount = count(array_filter($employeeScores, fn($e) => $e['total_score'] >= 60 && $e['total_score'] < 80));
$needsImprovementCount = count(array_filter($employeeScores, fn($e) => $e['total_score'] < 60));
$avgScore = $totalEmployees > 0 ? round(array_sum(array_column($employeeScores, 'total_score')) / $totalEmployees, 1) : 0;

$pageTitle = 'Employee Performance';
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
        .perf-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        .perf-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        .score-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 700;
            color: white;
            margin: 0 auto 15px;
        }
        .perf-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .breakdown-bar {
            height: 8px;
            background: #f0f0f0;
            border-radius: 4px;
            overflow: hidden;
            margin: 5px 0;
        }
        .breakdown-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s;
        }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle" aria-label="Toggle Menu">
        <i class="fas fa-bars"></i>
    </button>

    <div class="sidebar-overlay"></div>

    <?php include_once __DIR__ . '/../../includes/manager_sidebar.php'; ?>

    <?php 
    $pageTitle = 'Employee Performance';
    include_once __DIR__ . '/../../includes/manager_header.php'; 
    ?>
        <div class="header-left">
            <div class="system-status">
                <i class="fas fa-chart-line"></i> Performance Analytics
            </div>
        </div>
        <div class="header-right">
            <div class="admin-profile">
                <div class="admin-avatar"><?php echo strtoupper(substr($managerName, 0, 1)); ?></div>
                <div>
                    <div style="font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($managerName); ?></div>
                    <div class="admin-role-badge">MANAGER</div>
                </div>
            </div>
        </div>
    </div>

    <div class="main-content">
        <h2 style="margin-bottom: 20px; color: #2c3e50;">
            <i class="fas fa-chart-line" style="color: #8B4513;"></i> Employee Performance Dashboard
        </h2>

        <!-- Time Filter -->
        <div style="background: white; padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.07);">
            <form method="GET" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <label style="font-weight: 600; font-size: 13px; color: #555;">Period:</label>
                <select name="months" onchange="this.form.submit()" style="padding: 8px 12px; border: 1.5px solid #ddd; border-radius: 8px; font-size: 13px; font-family: inherit;">
                    <option value="1" <?php echo $months == 1 ? 'selected' : ''; ?>>Last Month</option>
                    <option value="3" <?php echo $months == 3 ? 'selected' : ''; ?>>Last 3 Months</option>
                    <option value="6" <?php echo $months == 6 ? 'selected' : ''; ?>>Last 6 Months</option>
                    <option value="12" <?php echo $months == 12 ? 'selected' : ''; ?>>Last Year</option>
                </select>
            </form>
        </div>

        <!-- Summary Cards -->
        <div class="stats-grid" style="margin-bottom: 24px;">
            <div class="stat-card" style="border-left: 4px solid #27ae60;">
                <div class="stat-value" style="color: #27ae60;"><?php echo $excellentCount; ?></div>
                <div class="stat-label">Excellent Performers</div>
            </div>
            <div class="stat-card" style="border-left: 4px solid #f39c12;">
                <div class="stat-value" style="color: #f39c12;"><?php echo $goodCount; ?></div>
                <div class="stat-label">Good Performers</div>
            </div>
            <div class="stat-card" style="border-left: 4px solid #e74c3c;">
                <div class="stat-value" style="color: #e74c3c;"><?php echo $needsImprovementCount; ?></div>
                <div class="stat-label">Needs Improvement</div>
            </div>
            <div class="stat-card" style="border-left: 4px solid #3498db;">
                <div class="stat-value" style="color: #3498db;"><?php echo $avgScore; ?></div>
                <div class="stat-label">Average Score</div>
            </div>
        </div>

        <!-- Employee Cards -->
        <?php if (empty($employeeScores)): ?>
            <div class="perf-card" style="text-align: center; padding: 40px;">
                <i class="fas fa-users" style="font-size: 48px; color: #ddd; margin-bottom: 15px;"></i>
                <h3 style="color: #888;">No employee data available</h3>
                <p style="color: #aaa;">Employee performance data will appear here once tasks are completed.</p>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px;">
                <?php foreach ($employeeScores as $emp): ?>
                <div class="perf-card">
                    <!-- Employee Header -->
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 20px;">
                        <div>
                            <h3 style="margin: 0 0 5px; color: #2c3e50;">
                                <?php echo htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?>
                            </h3>
                            <p style="margin: 0; font-size: 13px; color: #888;"><?php echo htmlspecialchars($emp['email']); ?></p>
                        </div>
                        <div class="score-circle" style="background: <?php echo $emp['color']; ?>;">
                            <?php echo $emp['total_score']; ?>
                        </div>
                    </div>

                    <!-- Grade & Status -->
                    <div style="display: flex; gap: 10px; margin-bottom: 20px; justify-content: center;">
                        <span class="perf-badge" style="background: <?php echo $emp['color']; ?>20; color: <?php echo $emp['color']; ?>;">
                            Grade: <?php echo $emp['grade']; ?>
                        </span>
                        <span class="perf-badge" style="background: <?php echo $emp['color']; ?>20; color: <?php echo $emp['color']; ?>;">
                            <?php echo $emp['status']; ?>
                        </span>
                    </div>

                    <!-- Performance Breakdown -->
                    <div style="font-size: 13px;">
                        <!-- Task Completion -->
                        <div style="margin-bottom: 12px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                <span style="color: #666;">Task Completion</span>
                                <span style="font-weight: 600; color: #2c3e50;">
                                    <?php echo $emp['details']['task_completion']['score']; ?>/30
                                    (<?php echo $emp['details']['task_completion']['rate']; ?>%)
                                </span>
                            </div>
                            <div class="breakdown-bar">
                                <div class="breakdown-fill" style="width: <?php echo ($emp['details']['task_completion']['score']/30)*100; ?>%; background: #3498db;"></div>
                            </div>
                            <div style="font-size: 11px; color: #888;">
                                <?php echo $emp['details']['task_completion']['completed']; ?>/<?php echo $emp['details']['task_completion']['total']; ?> tasks completed
                            </div>
                        </div>

                        <!-- Attendance -->
                        <div style="margin-bottom: 12px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                <span style="color: #666;">Attendance</span>
                                <span style="font-weight: 600; color: #2c3e50;">
                                    <?php echo $emp['details']['attendance']['score']; ?>/25
                                    (<?php echo $emp['details']['attendance']['rate']; ?>%)
                                </span>
                            </div>
                            <div class="breakdown-bar">
                                <div class="breakdown-fill" style="width: <?php echo ($emp['details']['attendance']['score']/25)*100; ?>%; background: #27ae60;"></div>
                            </div>
                            <div style="font-size: 11px; color: #888;">
                                <?php echo $emp['details']['attendance']['present']; ?>/<?php echo $emp['details']['attendance']['total']; ?> days present
                            </div>
                        </div>

                        <!-- Customer Rating -->
                        <div style="margin-bottom: 12px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                <span style="color: #666;">Customer Rating</span>
                                <span style="font-weight: 600; color: #2c3e50;">
                                    <?php echo $emp['details']['customer_rating']['score']; ?>/25
                                    (<?php echo $emp['details']['customer_rating']['avg_rating']; ?>/5 ★)
                                </span>
                            </div>
                            <div class="breakdown-bar">
                                <div class="breakdown-fill" style="width: <?php echo ($emp['details']['customer_rating']['score']/25)*100; ?>%; background: #f39c12;"></div>
                            </div>
                            <div style="font-size: 11px; color: #888;">
                                <?php echo $emp['details']['customer_rating']['total_ratings']; ?> reviews
                            </div>
                        </div>

                        <!-- On-Time Delivery -->
                        <div style="margin-bottom: 12px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                <span style="color: #666;">On-Time Delivery</span>
                                <span style="font-weight: 600; color: #2c3e50;">
                                    <?php echo $emp['details']['on_time_delivery']['score']; ?>/10
                                    (<?php echo $emp['details']['on_time_delivery']['rate']; ?>%)
                                </span>
                            </div>
                            <div class="breakdown-bar">
                                <div class="breakdown-fill" style="width: <?php echo ($emp['details']['on_time_delivery']['score']/10)*100; ?>%; background: #9b59b6;"></div>
                            </div>
                            <div style="font-size: 11px; color: #888;">
                                <?php echo $emp['details']['on_time_delivery']['on_time']; ?>/<?php echo $emp['details']['on_time_delivery']['total']; ?> on time
                            </div>
                        </div>

                        <!-- Waste Management -->
                        <div style="margin-bottom: 12px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                                <span style="color: #666;">Waste Management</span>
                                <span style="font-weight: 600; color: #2c3e50;">
                                    <?php echo $emp['details']['waste_management']['score']; ?>/10
                                    (<?php echo $emp['details']['waste_management']['rate']; ?>% waste)
                                </span>
                            </div>
                            <div class="breakdown-bar">
                                <div class="breakdown-fill" style="width: <?php echo ($emp['details']['waste_management']['score']/10)*100; ?>%; background: #e74c3c;"></div>
                            </div>
                            <div style="font-size: 11px; color: #888;">
                                <?php echo $emp['details']['waste_management']['waste']; ?>/<?php echo $emp['details']['waste_management']['used']; ?> units wasted
                            </div>
                        </div>
                    </div>

                    <!-- Alert for low performers -->
                    <?php if ($emp['total_score'] < 60): ?>
                    <div style="background: #fee; border-left: 4px solid #e74c3c; padding: 10px; margin-top: 15px; border-radius: 4px; font-size: 12px; color: #c0392b;">
                        <i class="fas fa-exclamation-triangle"></i> 
                        <strong>Performance Alert:</strong> This employee needs attention and improvement support.
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
