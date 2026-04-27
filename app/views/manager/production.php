<?php
// Start output buffering to allow redirects after HTML output
ob_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}
require_once __DIR__ . '/../../../config/db_config.php';
$csrf_token = $_SESSION['csrf_token'] ?? null;
if (!$csrf_token) {
    $csrf_token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrf_token;
}
$managerName = $_SESSION['user_name'] ?? 'Manager User';
$pageTitle = 'Production Management';

// Handle stage update — MUST be before any HTML output
$updateSuccess = '';
$updateError   = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stage'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $updateError = 'Invalid CSRF token.';
    } else {
        $taskId   = intval($_POST['task_id']);
        $stage    = trim($_POST['stage']);
        $progress = intval($_POST['progress']);
        $allowedStages = ['pending', 'in_progress', 'completed', 'cancelled'];
        if (!in_array($stage, $allowedStages)) {
            $updateError = 'Invalid stage value.';
        } elseif (!$taskId) {
            $updateError = 'Invalid task ID.';
        } else {
            try {
                $pdo->prepare("UPDATE furn_production_tasks SET status=?, progress=? WHERE id=?")
                    ->execute([$stage, $progress, $taskId]);
                // Also sync order status if task completed
                if ($stage === 'completed') {
                    $pdo->prepare("
                        UPDATE furn_orders o
                        JOIN furn_production_tasks t ON t.order_id = o.id
                        SET o.status = 'ready_for_delivery'
                        WHERE t.id = ? AND o.status IN ('in_production','payment_verified','deposit_paid')
                    ")->execute([$taskId]);
                } elseif ($stage === 'in_progress') {
                    // Get order_id from task
                    $taskStmt = $pdo->prepare("SELECT order_id FROM furn_production_tasks WHERE id = ?");
                    $taskStmt->execute([$taskId]);
                    $taskOrderId = $taskStmt->fetchColumn();
                    
                    if ($taskOrderId) {
                        // Use OrderModel to start production (this will automatically deduct reserved materials)
                        require_once __DIR__ . '/../../../app/models/OrderModel.php';
                        $orderModel = new OrderModel();
                        
                        try {
                            // Check if order is in correct status before starting production
                            $orderStmt = $pdo->prepare("SELECT status FROM furn_orders WHERE id = ?");
                            $orderStmt->execute([$taskOrderId]);
                            $currentStatus = $orderStmt->fetchColumn();
                            
                            if (in_array($currentStatus, ['payment_verified', 'deposit_paid'])) {
                                // Start production - this will deduct reserved stock automatically
                                $orderModel->startProduction($taskOrderId);
                            }
                        } catch (Exception $e) {
                            error_log("Production start error: " . $e->getMessage());
                            // Continue anyway - status will be updated
                        }
                    }
                }
                $_SESSION['prod_success'] = 'Task #' . $taskId . ' updated to ' . ucfirst(str_replace('_',' ',$stage)) . '.';
                header('Location: ' . BASE_URL . '/public/manager/production');
                exit();
            } catch (PDOException $e) {
                $updateError = 'DB Error: ' . $e->getMessage();
            }
        }
    }
}
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
</head>
<body>
    <button class="mobile-menu-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>
    <div class="sidebar-overlay"></div>
    
    <?php include_once __DIR__ . '/../../includes/manager_sidebar.php'; ?>
    
    <!-- Top Header -->
    <?php 
    $pageTitle = 'Production';
    include_once __DIR__ . '/../../includes/manager_header.php'; 
    ?>

    <!-- Main Content -->
    <div class="main-content">
        <?php if (!empty($_SESSION['prod_success'])): ?>
            <div style="background:#d4edda;color:#155724;padding:12px 18px;border-radius:8px;margin-bottom:16px;border:1px solid #c3e6cb;">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($_SESSION['prod_success']); unset($_SESSION['prod_success']); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($updateError)): ?>
            <div style="background:#f8d7da;color:#721c24;padding:12px 18px;border-radius:8px;margin-bottom:16px;">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($updateError); ?>
            </div>
        <?php endif; ?>

        <?php
        // Ensure progress column exists
        try { $pdo->exec("ALTER TABLE furn_production_tasks ADD COLUMN IF NOT EXISTS progress INT DEFAULT 0"); } catch (PDOException $e) {}

        // Fetch production tasks from the correct table
        $productionOrders = [];
        try {
            $stmt = $pdo->query("
                SELECT pt.*,
                    o.order_number, o.furniture_type, o.furniture_name, o.estimated_cost,
                    CONCAT(cu.first_name,' ',cu.last_name) AS customer_name,
                    CONCAT(eu.first_name,' ',eu.last_name) AS employee_name
                FROM furn_production_tasks pt
                JOIN furn_orders o ON pt.order_id = o.id
                LEFT JOIN furn_users cu ON o.customer_id = cu.id
                LEFT JOIN furn_users eu ON pt.employee_id = eu.id
                ORDER BY pt.created_at DESC
            ");
            $productionOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            echo '<div style="background:#fff3cd;color:#856404;padding:12px 18px;border-radius:8px;margin-bottom:16px;">Could not load production data: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }

        // Stats
        $total      = count($productionOrders);
        $inProgress = count(array_filter($productionOrders, fn($r) => $r['status'] === 'in_progress'));
        $completed  = count(array_filter($productionOrders, fn($r) => $r['status'] === 'completed'));
        $pending    = count(array_filter($productionOrders, fn($r) => $r['status'] === 'pending'));
        ?>

        <!-- Stats -->
        <div class="stats-grid" style="margin-bottom:20px;">
            <div class="stat-card" style="border-left:4px solid #3498db;">
                <div class="stat-value"><?php echo $total; ?></div>
                <div class="stat-label">Total Tasks</div>
            </div>
            <div class="stat-card" style="border-left:4px solid #f39c12;">
                <div class="stat-value"><?php echo $pending; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card" style="border-left:4px solid #e67e22;">
                <div class="stat-value"><?php echo $inProgress; ?></div>
                <div class="stat-label">In Progress</div>
            </div>
            <div class="stat-card" style="border-left:4px solid #27ae60;">
                <div class="stat-value"><?php echo $completed; ?></div>
                <div class="stat-label">Completed</div>
            </div>
        </div>
        
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-industry"></i> Production Tasks</h2>
            </div>
            
            <div class="table-responsive">
                <table class="data-table mobile-cards">
                    <thead>
                        <tr>
                            <th>Task ID</th>
                            <th>Customer</th>
                            <th>Furniture</th>
                            <th>Assigned Employee</th>
                            <th>Status</th>
                            <th>Progress</th>
                            <th>Assigned Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($productionOrders)): ?>
                            <tr><td colspan="8" style="text-align:center;padding:40px;color:#aaa;">
                                <i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:10px;"></i>
                                No production tasks yet. Assign employees to orders from the <a href="<?php echo BASE_URL; ?>/public/manager/assign-employees">Assign Employees</a> page.
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($productionOrders as $task): ?>
                                <?php
                                $statusColors = [
                                    'pending'     => '#f39c12',
                                    'in_progress' => '#3498db',
                                    'completed'   => '#27ae60',
                                    'cancelled'   => '#e74c3c',
                                ];
                                $sc = $statusColors[$task['status']] ?? '#95a5a6';
                                ?>
                                <tr>
                                    <td>#<?php echo $task['id']; ?></td>
                                    <td><?php echo htmlspecialchars($task['customer_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($task['furniture_name'] ?? $task['furniture_type'] ?? 'Other'); ?></td>
                                    <td><?php echo htmlspecialchars($task['employee_name'] ?? 'Unassigned'); ?></td>
                                    <td>
                                        <span style="background:<?php echo $sc; ?>;color:white;padding:3px 10px;border-radius:10px;font-size:12px;font-weight:600;">
                                            <?php echo ucfirst(str_replace('_', ' ', $task['status'] ?? 'pending')); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="background:#ecf0f1;border-radius:10px;height:16px;overflow:hidden;min-width:80px;">
                                            <div style="background:<?php echo $sc; ?>;height:100%;width:<?php echo min(100, (int)($task['progress'] ?? 0)); ?>%;"></div>
                                        </div>
                                        <small><?php echo (int)($task['progress'] ?? 0); ?>%</small>
                                    </td>
                                    <td><?php echo $task['created_at'] ? date('M d, Y', strtotime($task['created_at'])) : 'N/A'; ?></td>
                                    <td>
                                        <button class="btn-action btn-primary-custom btn-update"
                                            data-id="<?php echo (int)$task['id']; ?>"
                                            data-order="<?php echo htmlspecialchars($task['order_number'] ?? '#'.$task['order_id'], ENT_QUOTES); ?>"
                                            data-customer="<?php echo htmlspecialchars($task['customer_name'] ?? 'N/A', ENT_QUOTES); ?>"
                                            data-furniture="<?php echo htmlspecialchars($task['furniture_name'] ?? $task['furniture_type'] ?? 'Other', ENT_QUOTES); ?>"
                                            data-employee="<?php echo htmlspecialchars($task['employee_name'] ?? 'Unassigned', ENT_QUOTES); ?>"
                                            data-status="<?php echo htmlspecialchars($task['status'] ?? 'pending', ENT_QUOTES); ?>"
                                            data-progress="<?php echo (int)($task['progress'] ?? 0); ?>">
                                            <i class="fas fa-edit"></i> Update
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    </div>
    
    <!-- Update Stage Modal -->
    <div id="stageModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
        <div style="background:white;border-radius:14px;padding:0;max-width:480px;width:90%;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,.2);">
            <div style="background:linear-gradient(135deg,#3D1F14,#5D4037);padding:18px 24px;display:flex;justify-content:space-between;align-items:center;">
                <h3 style="margin:0;color:white;font-size:16px;"><i class="fas fa-industry me-2"></i>Update Production Stage</h3>
                <button onclick="closeStageModal()" style="background:none;border:none;color:white;font-size:22px;cursor:pointer;opacity:.8;">&times;</button>
            </div>
            <div style="padding:24px;">
                <form method="POST">
                    <input type="hidden" name="update_stage" value="1">
                    <input type="hidden" name="task_id" id="modal_task_id">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? $csrf_token); ?>">

                    <div id="modal_order_info" style="background:#f8f4e9;border-radius:8px;padding:14px;margin-bottom:18px;font-size:13px;line-height:1.7;border-left:4px solid #8B4513;"></div>

                    <div style="margin-bottom:16px;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:6px;">Production Stage / Status</label>
                        <select name="stage" id="modal_stage" onchange="updateProgressSuggestion()"
                            style="width:100%;padding:10px 12px;border:1.5px solid #ddd;border-radius:8px;font-size:14px;background:white;cursor:pointer;">
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>

                    <div style="margin-bottom:20px;">
                        <label style="display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:6px;">Progress (%)</label>
                        <input type="number" name="progress" id="modal_progress" min="0" max="100" required
                            style="width:100%;padding:10px 12px;border:1.5px solid #ddd;border-radius:8px;font-size:14px;box-sizing:border-box;">
                        <small style="color:#aaa;font-size:12px;">0 = not started, 100 = fully completed</small>
                    </div>

                    <div style="display:flex;gap:10px;justify-content:flex-end;">
                        <button type="button" onclick="closeStageModal()"
                            style="padding:10px 20px;background:#95a5a6;color:white;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;">
                            Cancel
                        </button>
                        <button type="submit"
                            style="padding:10px 24px;background:linear-gradient(135deg,#27ae60,#1e8449);color:white;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;">
                            <i class="fas fa-save me-1"></i> Update Stage
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-update');
        if (!btn) return;
        document.getElementById('modal_task_id').value   = btn.dataset.id;
        document.getElementById('modal_stage').value     = btn.dataset.status;
        document.getElementById('modal_progress').value  = btn.dataset.progress;
        document.getElementById('modal_order_info').innerHTML =
            '<strong>Task #' + btn.dataset.id + ' — Order ' + btn.dataset.order + '</strong><br>' +
            'Customer: ' + btn.dataset.customer + '<br>' +
            'Furniture: ' + btn.dataset.furniture + '<br>' +
            'Employee: ' + btn.dataset.employee;
        document.getElementById('stageModal').style.display = 'flex';
    });

    function closeStageModal() {
        document.getElementById('stageModal').style.display = 'none';
    }

    function updateProgressSuggestion() {
        const map = { assigned:0, pending:0, in_progress:50, completed:100, cancelled:0 };
        const v = document.getElementById('modal_stage').value;
        if (map[v] !== undefined) document.getElementById('modal_progress').value = map[v];
    }

    document.getElementById('stageModal').addEventListener('click', function(e) {
        if (e.target === this) closeStageModal();
    });
    </script>
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
<?php
// Flush output buffer
ob_end_flush();
?>
