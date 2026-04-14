<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}

require_once __DIR__ . '/../../../config/db_config.php';

$managerName = $_SESSION['user_name'] ?? 'Manager User';

// Handle approve for delivery
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'approve_delivery') {
    // CSRF check
    $csrfPost = $_POST['csrf_token'] ?? '';
    $csrfSess = $_SESSION['csrf_token'] ?? '';
    if (!$csrfPost || !$csrfSess || !hash_equals($csrfSess, $csrfPost)) {
        $errorMsg = 'Invalid CSRF token.';
    } else {
    $orderId = (int)$_POST['order_id'];
    $taskId  = (int)$_POST['task_id'];
    try {
        $pdo->prepare("UPDATE furn_orders SET status = 'ready_for_delivery', production_completed_at = NOW() WHERE id = ?")
            ->execute([$orderId]);

        // --- Auto-add finished product to gallery ---
        // Fetch order + task details
        $stmtTask = $pdo->prepare("
            SELECT t.finished_image, t.completion_notes, t.materials_used as task_materials,
                   o.furniture_type, o.furniture_name, o.material, o.color,
                   o.length, o.width, o.height, o.design_description, o.estimated_cost
            FROM furn_production_tasks t
            JOIN furn_orders o ON o.id = t.order_id
            WHERE t.id = ? AND t.order_id = ?
            LIMIT 1
        ");
        $stmtTask->execute([$taskId, $orderId]);
        $taskData = $stmtTask->fetch(PDO::FETCH_ASSOC);

        if ($taskData && !empty($taskData['finished_image'])) {
            // Map furniture_type to category name
            $furnitureType = strtolower($taskData['furniture_type'] ?? '');
            $categoryMap = [
                'sofa' => 'Sofa', 'couch' => 'Sofa',
                'chair' => 'Chair', 'armchair' => 'Chair',
                'bed' => 'Bed', 'bedroom' => 'Bed',
                'table' => 'Table', 'dining' => 'Table', 'desk' => 'Table',
                'cabinet' => 'Cabinet', 'kitchen' => 'Cabinet',
                'wardrobe' => 'Wardrobe', 'closet' => 'Wardrobe',
                'shelf' => 'Shelf', 'bookshelf' => 'Shelf', 'rack' => 'Shelf',
                'office' => 'Office',
            ];
            $categoryName = 'Custom';
            foreach ($categoryMap as $keyword => $cat) {
                if (strpos($furnitureType, $keyword) !== false) {
                    $categoryName = $cat;
                    break;
                }
            }

            // Get or create category
            $stmtCat = $pdo->prepare("SELECT id FROM furn_categories WHERE LOWER(name) = LOWER(?) LIMIT 1");
            $stmtCat->execute([$categoryName]);
            $catRow = $stmtCat->fetch(PDO::FETCH_ASSOC);
            if (!$catRow) {
                $pdo->prepare("INSERT IGNORE INTO furn_categories (name, description) VALUES (?, ?)")
                    ->execute([$categoryName, $categoryName . ' furniture']);
                $stmtCat->execute([$categoryName]);
                $catRow = $stmtCat->fetch(PDO::FETCH_ASSOC);
            }
            $categoryId = $catRow['id'] ?? null;

            // Build product name and description
            $productName = !empty($taskData['furniture_name'])
                ? $taskData['furniture_name']
                : ucfirst($taskData['furniture_type'] ?? 'Custom Furniture');

            $dimensions = '';
            if ($taskData['length'] && $taskData['width'] && $taskData['height']) {
                $dimensions = $taskData['length'] . 'm × ' . $taskData['width'] . 'm × ' . $taskData['height'] . 'm';
            }

            $materials = !empty($taskData['task_materials'])
                ? $taskData['task_materials']
                : ($taskData['material'] ?? '');

            $description = trim(
                ($taskData['design_description'] ?? '') .
                ($taskData['completion_notes'] ? "\n" . $taskData['completion_notes'] : '') .
                ($dimensions ? "\nDimensions: $dimensions" : '') .
                ($taskData['color'] ? "\nColor: " . $taskData['color'] : '')
            );

            // Insert into furn_products (skip if already added for this order)
            // Ensure all required columns exist first
            try {
                $pdo->exec("ALTER TABLE furn_products 
                    ADD COLUMN IF NOT EXISTS order_id INT DEFAULT NULL,
                    ADD COLUMN IF NOT EXISTS image_main VARCHAR(255) DEFAULT NULL,
                    ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1
                ");
            } catch (PDOException $e2) {}

            $exists = $pdo->prepare("SELECT id FROM furn_products WHERE order_id = ? LIMIT 1");
            $exists->execute([$orderId]);
            if (!$exists->fetch()) {

                $pdo->prepare("
                    INSERT INTO furn_products
                        (name, category_id, materials_used, description, base_price, image_main, is_active, order_id, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 1, ?, NOW())
                ")->execute([
                    $productName,
                    $categoryId,
                    $materials,
                    $description,
                    $taskData['estimated_cost'] ?? 0,
                    $taskData['finished_image'],
                    $orderId,
                ]);
            }
        }
        // --- End auto-add to gallery ---

        $successMsg = "Order #$orderId approved for delivery. Customer will be notified.";
    } catch (PDOException $e) {
        $errorMsg = "Error: " . $e->getMessage();
    }
    }
}

// Fetch completed tasks with all details
$completedTasks = [];
try {
    $stmt = $pdo->query("
        SELECT t.*, 
               o.order_number,
               o.furniture_type,
               o.furniture_name,
               o.length, o.width, o.height,
               o.material, o.color,
               o.design_description,
               o.design_image,
               o.special_notes,
               o.estimated_cost,
               CONCAT(u.first_name, ' ', u.last_name) as customer_name,
               u.email as customer_email,
               u.phone as customer_phone,
               CONCAT(e.first_name, ' ', e.last_name) as employee_name,
               e.email as employee_email
        FROM furn_production_tasks t
        LEFT JOIN furn_orders o ON t.order_id = o.id
        LEFT JOIN furn_users u ON o.customer_id = u.id
        LEFT JOIN furn_users e ON t.employee_id = e.id
        WHERE t.status = 'completed'
        ORDER BY t.completed_at DESC
    ");
    $completedTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Completed tasks error: " . $e->getMessage());
}

$pageTitle = 'Completed Tasks';
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
        .task-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        .task-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        .task-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        .image-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .image-container {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .image-container img {
            width: 100%;
            height: 250px;
            object-fit: cover;
            transition: transform 0.3s;
        }
        .image-container:hover img {
            transform: scale(1.05);
        }
        .image-label {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
            color: white;
            padding: 10px;
            font-size: 13px;
            font-weight: 600;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        .info-item {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            border-left: 3px solid #3498DB;
        }
        .info-label {
            font-size: 11px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        .info-value {
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
        }
        .materials-box {
            background: #fff3e0;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #FF9800;
            margin: 15px 0;
        }
        .notes-box {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #2196F3;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle" aria-label="Toggle Menu">
        <i class="fas fa-bars"></i>
    </button>

    <div class="sidebar-overlay"></div>

    <?php include_once __DIR__ . '/../../includes/manager_sidebar.php'; ?>

    <!-- Top Header -->
    <?php 
    $pageTitle = 'Completed Tasks';
    include_once __DIR__ . '/../../includes/manager_header.php'; 
    ?>
        <div class="header-left">
            <div class="system-status">
                <i class="fas fa-circle"></i> Completed Tasks Review
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
        <?php
        // Completed tasks stats cards
        $ctCards = [];
        try {
            $ctTotal     = (int)$pdo->query("SELECT COUNT(*) FROM furn_production_tasks WHERE status='completed'")->fetchColumn();
            $ctDelivery  = (int)$pdo->query("SELECT COUNT(*) FROM furn_orders WHERE status='ready_for_delivery'")->fetchColumn();
            $ctRatingRaw = $pdo->query("SELECT ROUND(AVG(rating),1) FROM furn_ratings")->fetchColumn();
            $ctRating    = ($ctRatingRaw !== null && $ctRatingRaw !== false) ? $ctRatingRaw.' ★' : '—';
            $ctMonth     = (int)$pdo->query("SELECT COUNT(*) FROM furn_production_tasks WHERE status='completed' AND MONTH(completed_at)=MONTH(CURDATE()) AND YEAR(completed_at)=YEAR(CURDATE())")->fetchColumn();
            $ctMatUsed   = floatval($pdo->query("SELECT COALESCE(SUM(om.total_cost),0) FROM furn_order_materials om JOIN furn_orders o ON om.order_id=o.id WHERE o.status='completed'")->fetchColumn());
            $ctCards = [
                [$ctTotal,                           'Total Completed Tasks', '#27AE60','fa-check-double'],
                [$ctDelivery,                        'Pending Delivery',      '#1ABC9C','fa-truck'],
                [$ctRating,                          'Avg Rating',            '#F39C12','fa-star'],
                [$ctMonth,                           'Completed This Month',  '#3498DB','fa-calendar-check'],
                ['ETB '.number_format($ctMatUsed,0), 'Total Material Used',   '#9B59B6','fa-boxes'],
            ];
        } catch(PDOException $e){}
        if (!empty($ctCards)):
        ?>
        <div class="stats-grid" style="margin-bottom:20px;">
            <?php foreach($ctCards as [$v,$l,$c,$i]): ?>
            <div class="stat-card" style="border-left:4px solid <?php echo $c; ?>;">
                <div style="display:flex;justify-content:space-between;align-items:start;">
                    <div><div class="stat-value" style="font-size:<?php echo strpos((string)$v,'ETB')!==false?'16px':'28px';?>"><?php echo $v; ?></div><div class="stat-label"><?php echo $l; ?></div></div>
                    <div style="font-size:28px;color:<?php echo $c; ?>;opacity:.25;"><i class="fas <?php echo $i; ?>"></i></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (isset($successMsg)): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMsg); ?></div>
        <?php endif; ?>
        <?php if (isset($errorMsg)): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMsg); ?></div>
        <?php endif; ?>
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-check-circle"></i> Completed Production Tasks</h2>
                <span class="badge badge-success" style="font-size: 14px; padding: 6px 12px;"><?php echo count($completedTasks); ?> Completed</span>
            </div>
            
            <?php if (empty($completedTasks)): ?>
                <p style="text-align: center; padding: 40px 20px; color: #7f8c8d;">
                    <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; display: block; color: #bdc3c7;"></i>
                    No completed tasks yet
                </p>
            <?php else: ?>
                <?php foreach ($completedTasks as $task): ?>
                    <div class="task-card">
                        <div class="task-header">
                            <div>
                                <h3 style="margin: 0 0 5px 0; color: #2c3e50;">
                                    <?php echo htmlspecialchars($task['furniture_name'] ?? 'Custom Furniture'); ?>
                                </h3>
                                <div style="color: #7f8c8d; font-size: 14px;">
                                    Order #<?php echo htmlspecialchars($task['order_number']); ?> • 
                                    Completed: <?php echo date('M d, Y', strtotime($task['completed_at'])); ?>
                                </div>
                            </div>
                            <span class="badge badge-success" style="font-size: 14px;">
                                <i class="fas fa-check-circle"></i> COMPLETED
                            </span>
                        </div>

                        <!-- Image Gallery -->
                        <div class="image-gallery">
                            <?php if (!empty($task['design_image'])): ?>
                                <div class="image-container">
                                    <a href="<?php echo BASE_URL; ?>/public/<?php echo htmlspecialchars($task['design_image']); ?>" target="_blank">
                                        <img src="<?php echo BASE_URL; ?>/public/<?php echo htmlspecialchars($task['design_image']); ?>" 
                                             alt="Original Design"
                                             onerror="this.src='<?php echo BASE_URL; ?>/public/assets/images/no-image.png'">
                                        <div class="image-label">
                                            <i class="fas fa-pencil-ruler"></i> Original Design
                                        </div>
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($task['finished_image'])): ?>
                                <div class="image-container">
                                    <a href="<?php echo BASE_URL; ?>/public/<?php echo htmlspecialchars($task['finished_image']); ?>" target="_blank">
                                        <img src="<?php echo BASE_URL; ?>/public/<?php echo htmlspecialchars($task['finished_image']); ?>" 
                                             alt="Finished Product"
                                             onerror="this.src='<?php echo BASE_URL; ?>/public/assets/images/no-image.png'">
                                        <div class="image-label">
                                            <i class="fas fa-check-circle"></i> Finished Product
                                        </div>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Task Information -->
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Employee</div>
                                <div class="info-value"><?php echo htmlspecialchars($task['employee_name']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Customer</div>
                                <div class="info-value"><?php echo htmlspecialchars($task['customer_name']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Furniture Type</div>
                                <div class="info-value"><?php echo htmlspecialchars($task['furniture_type'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Material</div>
                                <div class="info-value"><?php echo htmlspecialchars($task['material'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Dimensions</div>
                                <div class="info-value">
                                    <?php 
                                    if ($task['length'] && $task['width'] && $task['height']) {
                                        echo number_format($task['length'], 1) . ' × ' . 
                                             number_format($task['width'], 1) . ' × ' . 
                                             number_format($task['height'], 1) . ' m';
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </div>
                            </div>
                            <?php if ($task['actual_hours']): ?>
                            <div class="info-item">
                                <div class="info-label">Production Hours</div>
                                <div class="info-value"><?php echo number_format($task['actual_hours'], 1); ?> hours</div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Materials Used -->
                        <?php if (!empty($task['materials_used'])): ?>
                        <div class="materials-box">
                            <h4 style="margin: 0 0 10px 0; color: #2c3e50; font-size: 14px;">
                                <i class="fas fa-tools"></i> Materials Used
                            </h4>
                            <p style="margin: 0; color: #2c3e50; line-height: 1.6; white-space: pre-wrap;"><?php echo htmlspecialchars($task['materials_used']); ?></p>
                        </div>
                        <?php endif; ?>

                        <!-- Completion Notes -->
                        <?php if (!empty($task['completion_notes'])): ?>
                        <div class="notes-box">
                            <h4 style="margin: 0 0 10px 0; color: #2c3e50; font-size: 14px;">
                                <i class="fas fa-comment-alt"></i> Completion Notes
                            </h4>
                            <p style="margin: 0; color: #2c3e50; line-height: 1.6; white-space: pre-wrap;"><?php echo htmlspecialchars($task['completion_notes']); ?></p>
                        </div>
                        <?php endif; ?>

                        <!-- Customer Rating & Suggestion -->
                        <?php
                        $taskRating = null;
                        try {
                            $stmtR = $pdo->prepare("SELECT rating, review_text, created_at FROM furn_ratings WHERE order_id = ?");
                            $stmtR->execute([$task['order_id']]);
                            $taskRating = $stmtR->fetch(PDO::FETCH_ASSOC);
                        } catch (PDOException $e) {}
                        ?>
                        <div style="background: <?php echo $taskRating ? '#fff8e1' : '#f8f9fa'; ?>; padding: 18px; border-radius: 10px; margin: 15px 0; border: 2px solid <?php echo $taskRating ? '#f39c12' : '#e9ecef'; ?>;">
                            <h4 style="margin: 0 0 12px; font-size: 15px; color: <?php echo $taskRating ? '#e67e22' : '#7f8c8d'; ?>;">
                                <i class="fas fa-star me-1"></i> Customer Rating &amp; Suggestion
                            </h4>
                            <?php if ($taskRating): ?>
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                    <div style="font-size: 26px;">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star" style="color: <?php echo $i <= $taskRating['rating'] ? '#f39c12' : '#ddd'; ?>;"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <strong style="font-size: 18px; color: #2c3e50;"><?php echo $taskRating['rating']; ?>/5</strong>
                                    <small style="color: #aaa;"><?php echo date('M j, Y', strtotime($taskRating['created_at'])); ?></small>
                                </div>
                                <?php if (!empty($taskRating['review_text'])): ?>
                                    <div style="background: white; border-left: 4px solid #f39c12; padding: 12px 15px; border-radius: 6px;">
                                        <div style="font-size: 12px; color: #e67e22; font-weight: 600; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px;">
                                            <i class="fas fa-comment-dots me-1"></i> Customer Suggestion / Review
                                        </div>
                                        <p style="margin: 0; color: #2c3e50; line-height: 1.6; font-style: italic;">"<?php echo htmlspecialchars($taskRating['review_text']); ?>"</p>
                                    </div>
                                <?php else: ?>
                                    <p style="margin: 0; color: #aaa; font-size: 13px;"><i class="fas fa-minus-circle me-1"></i> No written suggestion from customer.</p>
                                <?php endif; ?>
                            <?php else: ?>
                                <p style="margin: 0; color: #aaa; font-size: 13px;"><i class="fas fa-hourglass-half me-1"></i> Customer has not submitted a rating yet.</p>
                            <?php endif; ?>
                        </div>

                        <!-- Actions -->
                        <div style="display: flex; gap: 10px; margin-top: 15px; padding-top: 15px; border-top: 1px solid #f0f0f0; flex-wrap: wrap; align-items: center;">
                            <a href="<?php echo BASE_URL; ?>/public/manager/orders" class="btn-action btn-primary-custom">
                                <i class="fas fa-eye"></i> View Order
                            </a>
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="approve_delivery">
                                <input type="hidden" name="order_id" value="<?php echo $task['order_id']; ?>">
                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                                <button type="submit" class="btn-action btn-success-custom"
                                    onclick="return confirm('Approve this product for delivery to customer?')">
                                    <i class="fas fa-truck"></i> Approve for Delivery
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Finished Products Gallery -->
    <?php
    $galleryProducts = [];
    try {
        $galleryProducts = $pdo->query("
            SELECT p.*, c.name as category_name
            FROM furn_products p
            LEFT JOIN furn_categories c ON p.category_id = c.id
            WHERE p.order_id IS NOT NULL AND p.is_active = 1
            ORDER BY p.created_at DESC
            LIMIT 24
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}
    ?>
    <?php if (!empty($galleryProducts)): ?>
    <div class="main-content" style="padding-top: 0;">
        <div class="section-card">
            <div class="section-title" style="margin-bottom: 20px;">
                <i class="fas fa-images me-2"></i>Finished Products Gallery
                <small style="font-size: 13px; color: #7f8c8d; margin-left: 10px;"><?php echo count($galleryProducts); ?> products auto-added from completed orders</small>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px;">
                <?php foreach ($galleryProducts as $gp): ?>
                <div style="background: #f8f9fa; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                    <div style="height: 180px; overflow: hidden; background: #eee;">
                        <img src="<?php echo BASE_URL; ?>/public/<?php echo htmlspecialchars($gp['image_main']); ?>"
                             alt="<?php echo htmlspecialchars($gp['name']); ?>"
                             style="width: 100%; height: 100%; object-fit: cover;"
                             onerror="this.src='<?php echo BASE_URL; ?>/public/assets/images/no-image.png'">
                    </div>
                    <div style="padding: 12px;">
                        <span style="background: #8B4513; color: white; padding: 2px 8px; border-radius: 10px; font-size: 11px;"><?php echo htmlspecialchars($gp['category_name'] ?? 'Custom'); ?></span>
                        <div style="font-weight: 600; margin: 6px 0 4px; font-size: 14px;"><?php echo htmlspecialchars($gp['name']); ?></div>
                        <div style="font-size: 12px; color: #7f8c8d;"><?php echo htmlspecialchars($gp['materials_used'] ?? ''); ?></div>
                        <div style="font-weight: 700; color: #8B4513; margin-top: 6px;">ETB <?php echo number_format($gp['base_price'], 2); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
