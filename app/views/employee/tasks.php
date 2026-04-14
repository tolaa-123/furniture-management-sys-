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

// Ensure CSRF token exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // CSRF check for all POST actions
    $csrfPost = $_POST['csrf_token'] ?? '';
    $csrfSess = $_SESSION['csrf_token'] ?? $_SESSION[CSRF_TOKEN_NAME] ?? '';
    if ($csrfPost && $csrfSess && !hash_equals($csrfSess, $csrfPost)) {
        $error = 'Invalid CSRF token. Please refresh and try again.';
        $action = ''; // block all actions
    }

    if ($action === 'start_task') {
        try {
            $stmt = $pdo->prepare("
                UPDATE furn_production_tasks 
                SET status = 'in_progress', started_at = NOW() 
                WHERE id = ? AND employee_id = ? AND status = 'pending'
            ");
            $stmt->execute([$_POST['task_id'], $employeeId]);
            $success = "Task started successfully!";
        } catch (PDOException $e) {
            $error = "Error starting task: " . $e->getMessage();
        }
    } elseif ($action === 'update_progress') {
        try {
            $stmt = $pdo->prepare("
                UPDATE furn_production_tasks 
                SET progress = ?, notes = ?, updated_at = NOW() 
                WHERE id = ? AND employee_id = ?
            ");
            $stmt->execute([
                $_POST['progress'],
                $_POST['notes'],
                $_POST['task_id'],
                $employeeId
            ]);
            $success = "Progress updated successfully!";
        } catch (PDOException $e) {
            $error = "Error updating progress: " . $e->getMessage();
        }
    } elseif ($action === 'complete_task') {
        // Ensure tables exist BEFORE starting transaction (DDL causes implicit commit in MySQL)
        try { $pdo->exec("CREATE TABLE IF NOT EXISTS furn_material_usage (
            id INT AUTO_INCREMENT PRIMARY KEY,
            employee_id INT NOT NULL, task_id INT DEFAULT NULL, material_id INT NOT NULL,
            quantity_used DECIMAL(10,2) NOT NULL, waste_amount DECIMAL(10,2) DEFAULT 0,
            notes TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(employee_id), INDEX(material_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (PDOException $e2) {}
        try { $pdo->exec("CREATE TABLE IF NOT EXISTS furn_order_materials (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL, task_id INT NOT NULL, material_id INT NOT NULL,
            material_name VARCHAR(255) NOT NULL, quantity_used DECIMAL(10,3) NOT NULL DEFAULT 0,
            unit VARCHAR(50) NOT NULL DEFAULT '', unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            total_cost DECIMAL(10,2) NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(order_id), INDEX(task_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (PDOException $e2) {}
        try { $pdo->exec("CREATE TABLE IF NOT EXISTS furn_gallery (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            image_path VARCHAR(255) NOT NULL,
            category ENUM('finished_products','customer_inspiration','showcase') DEFAULT 'finished_products',
            furniture_type VARCHAR(100) DEFAULT NULL,
            material VARCHAR(100) DEFAULT NULL,
            dimensions VARCHAR(100) DEFAULT NULL,
            employee_id INT DEFAULT NULL,
            employee_name VARCHAR(255) DEFAULT NULL,
            order_id INT DEFAULT NULL,
            materials_used TEXT DEFAULT NULL,
            production_hours DECIMAL(5,2) DEFAULT NULL,
            views INT DEFAULT 0,
            likes INT DEFAULT 0,
            status ENUM('active','inactive','featured') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX(category), INDEX(status), INDEX(order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (PDOException $e2) {}

        try {
            $pdo->beginTransaction();

            // Handle finished product image upload
            $finishedImagePath = null;
            if (isset($_FILES['finished_image']) && $_FILES['finished_image']['error'] === UPLOAD_ERR_OK) {
                $allowed = ['jpg', 'jpeg', 'png'];
                $ext = strtolower(pathinfo($_FILES['finished_image']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, $allowed) && $_FILES['finished_image']['size'] <= 5 * 1024 * 1024) {
                    $uploadDir = __DIR__ . '/../../../public/uploads/finished_products/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $newFilename = 'FINISHED_' . $_POST['task_id'] . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['finished_image']['tmp_name'], $uploadDir . $newFilename)) {
                        $finishedImagePath = 'uploads/finished_products/' . $newFilename;
                    }
                }
            }

            $materialsUsed  = $_POST['materials_used'] ?? '';
            $completionNotes = $_POST['completion_notes'] ?? '';
            $actualHours    = $_POST['actual_hours'] ?? null;

            // Mark task as completed — try full update, fall back to minimal columns
            $updated = false;
            foreach ([
                "UPDATE furn_production_tasks SET status='completed', progress=100, completed_at=NOW(),
                    finished_image=?, materials_used=?, completion_notes=?, actual_hours=?,
                    notes=CONCAT(COALESCE(notes,''),'\n\n[COMPLETION] ',?)
                  WHERE id=? AND employee_id=?" => [
                    $finishedImagePath, $materialsUsed, $completionNotes,
                    $actualHours, $completionNotes, $_POST['task_id'], $employeeId
                ],
                "UPDATE furn_production_tasks SET status='completed', progress=100, completed_at=NOW(),
                    notes=CONCAT(COALESCE(notes,''),'\n\n[COMPLETION] ',?)
                  WHERE id=? AND employee_id=?" => [
                    $completionNotes, $_POST['task_id'], $employeeId
                ],
            ] as $sql => $params) {
                if ($updated) break;
                try {
                    $pdo->prepare($sql)->execute($params);
                    $updated = true;
                } catch (PDOException $eCol) {
                    // column missing — try next fallback
                }
            }

            // Get task + order details
            $stmt = $pdo->prepare("
                SELECT t.*, o.*,
                       CONCAT(u.first_name,' ',u.last_name) as employee_name
                FROM furn_production_tasks t
                LEFT JOIN furn_orders o ON t.order_id = o.id
                LEFT JOIN furn_users u ON t.employee_id = u.id
                WHERE t.id = ?
            ");
            $stmt->execute([$_POST['task_id']]);
            $taskData = $stmt->fetch(PDO::FETCH_ASSOC);

            // Save structured material usage for profit calculation
            // AND record in furn_material_usage + reduce approved request quantities
            $materialIds  = $_POST['mat_id']  ?? [];
            $materialQtys = $_POST['mat_qty'] ?? [];
            if (!empty($materialIds)) {
                $pdo->prepare("DELETE FROM furn_order_materials WHERE task_id = ?")->execute([$_POST['task_id']]);
                $stmtMat = $pdo->prepare("
                    INSERT INTO furn_order_materials
                        (order_id, task_id, material_id, material_name, quantity_used, unit, unit_price, total_cost)
                    SELECT ?, ?, id, name, ?, unit, cost_per_unit, (? * cost_per_unit)
                    FROM furn_materials WHERE id = ?
                ");

                $stmtUsage = $pdo->prepare("
                    INSERT INTO furn_material_usage (employee_id, task_id, material_id, quantity_used, waste_amount, notes, created_at)
                    VALUES (?, ?, ?, ?, 0, ?, NOW())
                ");

                // Reduce approved request quantity for this employee+material
                $stmtReduceReq = $pdo->prepare("
                    UPDATE furn_material_requests
                    SET quantity_requested = GREATEST(0, quantity_requested - ?)
                    WHERE employee_id = ? AND material_id = ? AND status = 'approved'
                    ORDER BY approved_at DESC
                    LIMIT 1
                ");

                foreach ($materialIds as $i => $matId) {
                    $qty = floatval($materialQtys[$i] ?? 0);
                    if ($matId && $qty > 0) {
                        // 1. Save to order materials (profit tracking)
                        $stmtMat->execute([$taskData['order_id'], $_POST['task_id'], $qty, $qty, (int)$matId]);

                        // 2. Save to material usage history (employee view)
                        $stmtUsage->execute([
                            $employeeId,
                            $_POST['task_id'],
                            (int)$matId,
                            $qty,
                            "Used in task #{$_POST['task_id']} — order #{$taskData['order_id']}"
                        ]);

                        // 3. Reduce the approved request quantity
                        $stmtReduceReq->execute([$qty, $employeeId, (int)$matId]);

                        // 4. Deduct actual stock used + release reservation
                        try {
                            $pdo->prepare("UPDATE furn_materials
                                SET current_stock = GREATEST(0, current_stock - ?),
                                    reserved_stock = GREATEST(0, COALESCE(reserved_stock,0) - ?),
                                    updated_at = NOW()
                                WHERE id = ?")
                                ->execute([$qty, $qty, (int)$matId]);
                        } catch (PDOException $eStock) {
                            error_log("Stock deduct error: " . $eStock->getMessage());
                        }
                    }
                }
            }

            // Update order status
            $pdo->prepare("UPDATE furn_orders SET status='ready_for_delivery', production_completed_at=NOW() WHERE id=?")
                ->execute([$taskData['order_id']]);

            // Notify all managers that task is completed
            require_once __DIR__ . '/../../../app/includes/notification_helper.php';
            $furnitureName = $taskData['furniture_name'] ?? $taskData['furniture_type'] ?? 'Furniture';
            $orderNum = $taskData['order_number'] ?? '#'.$taskData['order_id'];
            notifyRole($pdo, 'manager', 'production', 'Task Completed — Ready for Delivery',
                $furnitureName . ' (Order ' . $orderNum . ') has been completed and is ready for delivery.',
                $_POST['task_id'], '/manager/production', 'high');

            // Commit core transaction before gallery/DDL work
            $pdo->commit();

            if ($finishedImagePath && $taskData) {
                $furnitureType = strtolower($taskData['furniture_type'] ?? 'custom');
                $categoryMap = [
                    'sofa'=>'sofa','couch'=>'sofa','chair'=>'chair','armchair'=>'chair',
                    'bed'=>'bed','bedroom'=>'bed','table'=>'table','dining'=>'table',
                    'coffee'=>'table','cabinet'=>'cabinet','cupboard'=>'cabinet',
                    'wardrobe'=>'wardrobe','closet'=>'wardrobe','shelf'=>'shelf',
                    'bookshelf'=>'shelf','shelving'=>'shelf','office'=>'office','desk'=>'office',
                ];
                $productCategory = 'custom';
                foreach ($categoryMap as $key => $val) {
                    if (strpos($furnitureType, $key) !== false) { $productCategory = $val; break; }
                }

                $dimensions   = sprintf('%s × %s × %s cm', $taskData['length'] ?? 'N/A', $taskData['width'] ?? 'N/A', $taskData['height'] ?? 'N/A');
                $productTitle = $taskData['furniture_name'] ?? ($taskData['furniture_type'] . ' - Custom');
                $productDesc  = trim(($taskData['design_description'] ?? '') . "\n\n" . $completionNotes);
                $estimatedPrice = $taskData['estimated_cost'] ?? 0;

                try {
                    // Ensure columns exist (DDL — must be outside transaction)
                    $pdo->exec("ALTER TABLE furn_products
                        ADD COLUMN IF NOT EXISTS image_main VARCHAR(255) DEFAULT NULL,
                        ADD COLUMN IF NOT EXISTS order_id INT DEFAULT NULL,
                        ADD COLUMN IF NOT EXISTS dimensions VARCHAR(100) DEFAULT NULL");
                } catch (PDOException $e2) {}

                try {
                    $catStmt = $pdo->prepare("SELECT id FROM furn_categories WHERE LOWER(name) = ? LIMIT 1");
                    $catStmt->execute([$productCategory]);
                    $catRow = $catStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$catRow) {
                        $pdo->prepare("INSERT IGNORE INTO furn_categories (name) VALUES (?)")->execute([$productCategory]);
                        $catStmt->execute([$productCategory]);
                        $catRow = $catStmt->fetch(PDO::FETCH_ASSOC);
                    }
                    $categoryId = $catRow ? $catRow['id'] : 1;

                    $exists = $pdo->prepare("SELECT id FROM furn_products WHERE order_id = ? LIMIT 1");
                    $exists->execute([$taskData['order_id']]);
                    if (!$exists->fetch()) {
                        $pdo->prepare("
                            INSERT INTO furn_products
                                (name, category_id, materials_used, dimensions, description,
                                 base_price, image_main, order_id, is_active, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
                        ")->execute([
                            $productTitle, $categoryId,
                            $taskData['material'] ?? 'Various',
                            $dimensions, $productDesc, $estimatedPrice,
                            $finishedImagePath, $taskData['order_id'],
                        ]);
                    }
                } catch (PDOException $e) {
                    error_log("Gallery product insert error: " . $e->getMessage());
                }

                try {
                    $chk = $pdo->query("SHOW TABLES LIKE 'furn_gallery'");
                    if ($chk->rowCount() > 0) {
                        $pdo->prepare("
                            INSERT INTO furn_gallery (
                                title, description, image_path, category,
                                furniture_type, material, dimensions,
                                employee_id, employee_name, order_id,
                                materials_used, production_hours, status, created_at
                            ) VALUES (?, ?, ?, 'finished_products', ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
                        ")->execute([
                            $productTitle, $productDesc, $finishedImagePath,
                            $taskData['furniture_type'] ?? 'Custom',
                            $taskData['material'] ?? 'Various',
                            $dimensions, $employeeId,
                            $taskData['employee_name'] ?? 'Employee',
                            $taskData['order_id'], $materialsUsed, $actualHours,
                        ]);
                    }
                } catch (PDOException $e) {
                    error_log("Gallery insert error: " . $e->getMessage());
                }
            }

            $success = "Task completed successfully! Product added to gallery.";

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = "Error completing task: " . $e->getMessage();
        }
    }
}

// Auto-migrate: ensure completion columns exist
try {
    $pdo->exec("ALTER TABLE furn_production_tasks
        ADD COLUMN IF NOT EXISTS finished_image VARCHAR(255) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS materials_used TEXT DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS completion_notes TEXT DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS actual_hours DECIMAL(5,2) DEFAULT NULL");
} catch (PDOException $e) {}

// Auto-create furn_order_materials table for structured cost tracking
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS furn_order_materials (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        task_id INT NOT NULL,
        material_id INT NOT NULL,
        material_name VARCHAR(255) NOT NULL,
        quantity_used DECIMAL(10,3) NOT NULL DEFAULT 0,
        unit VARCHAR(50) NOT NULL DEFAULT '',
        unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
        total_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(order_id), INDEX(task_id)
    )");
} catch (PDOException $e) {}

// Get filter parameters
$filterStatus = $_GET['status'] ?? '';

// Fetch tasks with COMPLETE order information for production
$tasks = [];
try {
    $query = "
        SELECT t.*, 
               o.order_number,
               o.furniture_type,
               o.furniture_name,
               o.length,
               o.width,
               o.height,
               o.material,
               o.color,
               o.design_description,
               o.design_image,
               o.special_notes,
               o.estimated_cost,
               o.preferred_delivery_date,
               o.manager_notes,
               CONCAT(u.first_name, ' ', u.last_name) as customer_name,
               u.email as customer_email,
               u.phone as customer_phone,
               COALESCE(p.name, o.furniture_name, 'Product') as product_name,
               r.rating as customer_rating,
               r.review as customer_review
        FROM furn_production_tasks t
        LEFT JOIN furn_orders o ON t.order_id = o.id
        LEFT JOIN furn_users u ON o.customer_id = u.id
        LEFT JOIN furn_products p ON t.product_id = p.id
        LEFT JOIN furn_ratings r ON r.order_id = t.order_id
        WHERE t.employee_id = ?
    ";

    $params = [$employeeId];

    if ($filterStatus) {
        $query .= " AND t.status = ?";
        $params[] = $filterStatus;
    }

    $query .= " ORDER BY 
        CASE t.status 
            WHEN 'in_progress' THEN 1 
            WHEN 'pending' THEN 2 
            WHEN 'completed' THEN 3 
        END,
        t.deadline ASC,
        t.created_at DESC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching tasks: " . $e->getMessage();
    error_log("Tasks fetch error: " . $e->getMessage());
}

// Calculate statistics
$stats = [
    'total' => count($tasks),
    'pending' => 0,
    'in_progress' => 0,
    'completed' => 0
];

foreach ($tasks as $task) {
    if ($task['status'] === 'pending') $stats['pending']++;
    elseif ($task['status'] === 'in_progress') $stats['in_progress']++;
    elseif ($task['status'] === 'completed') $stats['completed']++;
}

$pageTitle = 'My Tasks';

// Fetch materials for the complete task modal — show only those with available stock
$allMaterials = [];
try {
    $stmt = $pdo->query("SELECT id, name as material_name, unit, cost_per_unit as unit_price,
        current_stock, COALESCE(reserved_stock,0) as reserved_stock,
        (current_stock - COALESCE(reserved_stock,0)) as available_stock
        FROM furn_materials
        WHERE (current_stock - COALESCE(reserved_stock,0)) > 0 AND is_active = 1
        ORDER BY name ASC");
    $allMaterials = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Materials fetch error: " . $e->getMessage());
}

// Fetch this employee's approved material requests grouped by order_id
// So when completing a task we can pre-fill the materials
$approvedRequestsByOrder = [];
try {
    $stmt = $pdo->prepare("
        SELECT mr.order_id, mr.material_id, mr.quantity_requested,
               m.name as material_name, m.unit, m.cost_per_unit as unit_price
        FROM furn_material_requests mr
        JOIN furn_materials m ON m.id = mr.material_id
        WHERE mr.employee_id = ? AND mr.status = 'approved' AND mr.quantity_requested > 0
        ORDER BY mr.approved_at DESC
    ");
    $stmt->execute([$employeeId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $approvedRequestsByOrder[$row['order_id']][] = $row;
    }
} catch (PDOException $e) {
    error_log("Approved requests fetch error: " . $e->getMessage());
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
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" aria-label="Toggle Menu">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay"></div>

    <?php include_once __DIR__ . '/../../includes/employee_sidebar.php'; ?>

    <!-- Top Header -->
    <?php 
    $pageTitle = 'My Tasks';
    include_once __DIR__ . '/../../includes/employee_header.php'; 
    ?>
        <div class="header-left">
            <div class="system-status">
                <i class="fas fa-circle"></i> My Tasks
            </div>
        </div>
        <div class="header-right">
            <div class="admin-profile">
                <div class="admin-avatar"><?php echo strtoupper(substr($employeeName, 0, 1)); ?></div>
                <div>
                    <div style="font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($employeeName); ?></div>
                    <div class="admin-role-badge" style="background: #27AE60;">EMPLOYEE</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card" style="border-left: 4px solid #3498DB;">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <div>
                        <div class="stat-value"><?php echo $stats['total']; ?></div>
                        <div class="stat-label">Total Tasks</div>
                    </div>
                    <div style="font-size: 32px; color: #3498DB;"><i class="fas fa-tasks"></i></div>
                </div>
            </div>

            <div class="stat-card" style="border-left: 4px solid #F39C12;">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <div>
                        <div class="stat-value"><?php echo $stats['pending']; ?></div>
                        <div class="stat-label">Pending Tasks</div>
                    </div>
                    <div style="font-size: 32px; color: #F39C12;"><i class="fas fa-hourglass-half"></i></div>
                </div>
            </div>

            <div class="stat-card" style="border-left: 4px solid #9B59B6;">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <div>
                        <div class="stat-value"><?php echo $stats['in_progress']; ?></div>
                        <div class="stat-label">In Progress</div>
                    </div>
                    <div style="font-size: 32px; color: #9B59B6;"><i class="fas fa-spinner"></i></div>
                </div>
            </div>

            <div class="stat-card" style="border-left: 4px solid #27AE60;">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <div>
                        <div class="stat-value"><?php echo $stats['completed']; ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                    <div style="font-size: 32px; color: #27AE60;"><i class="fas fa-check-circle"></i></div>
                </div>
            </div>
        </div>

        <!-- Tasks Section -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-tasks"></i> Production Tasks</h2>
            </div>
            
            <!-- Filters -->
            <div class="filter-section" style="margin-bottom: 20px;">
                <form method="GET" action="" style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <div class="form-group" style="margin: 0;">
                        <label>Filter by Status</label>
                        <select name="status" class="form-control" onchange="this.form.submit()">
                            <option value="">All Tasks</option>
                            <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="in_progress" <?php echo $filterStatus === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $filterStatus === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                    
                    <?php if ($filterStatus): ?>
                    <div style="display: flex; align-items: flex-end;">
                        <a href="<?php echo BASE_URL; ?>/public/employee/tasks" class="btn-action btn-secondary-custom">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
            
            <?php if (empty($tasks)): ?>
                <p style="text-align: center; color: #7f8c8d; padding: 40px;">No tasks found.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table mobile-cards">
                        <thead>
                            <tr>
                                <th>Task ID</th>
                                <th>Order ID</th>
                                <th>Product</th>
                                <th>Customer</th>
                                <th>Assigned Date</th>
                                <th>Deadline</th>
                                <th>Progress</th>
                                <th>Status</th>
                                <th>Rating</th>
                                <th>Suggestion</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tasks as $task): ?>
                                <?php
                                // Fetch rating once per completed row
                                $taskRating = null;
                                if ($task['status'] === 'completed') {
                                    try {
                                        $stmtTR = $pdo->prepare("SELECT rating, review_text FROM furn_ratings WHERE order_id = ?");
                                        $stmtTR->execute([$task['order_id']]);
                                        $taskRating = $stmtTR->fetch(PDO::FETCH_ASSOC);
                                    } catch (PDOException $e) {}
                                }
                                ?>
                                <tr>
                                    <td>#<?php echo str_pad($task['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                    <td>#<?php echo str_pad($task['order_id'], 4, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo htmlspecialchars($task['product_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($task['customer_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($task['created_at'])); ?></td>
                                    <td>
                                        <?php if ($task['deadline']): ?>
                                            <?php 
                                            $deadline = strtotime($task['deadline']);
                                            $now = time();
                                            $isOverdue = $deadline < $now && $task['status'] !== 'completed';
                                            ?>
                                            <span style="color: <?php echo $isOverdue ? '#E74C3C' : '#2c3e50'; ?>;">
                                                <?php echo date('M d, Y', $deadline); ?>
                                                <?php if ($isOverdue): ?>
                                                    <i class="fas fa-exclamation-triangle" style="color: #E74C3C;"></i>
                                                <?php endif; ?>
                                            </span>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <div style="flex: 1; background: #ecf0f1; border-radius: 10px; height: 8px; overflow: hidden;">
                                                <div style="width: <?php echo $task['progress']; ?>%; height: 100%; background: #27AE60; transition: width 0.3s;"></div>
                                            </div>
                                            <span style="font-size: 12px; font-weight: 600;"><?php echo $task['progress']; ?>%</span>
                                        </div>
                                    </td>
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

                                    <!-- Rating column -->
                                    <td>
                                        <?php if ($taskRating): ?>
                                            <div style="white-space: nowrap;">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star" style="color: <?php echo $i <= $taskRating['rating'] ? '#f39c12' : '#ddd'; ?>; font-size: 14px;"></i>
                                                <?php endfor; ?>
                                                <strong style="font-size: 12px; color: #2c3e50; margin-left: 4px;"><?php echo $taskRating['rating']; ?>/5</strong>
                                            </div>
                                        <?php elseif ($task['status'] === 'completed'): ?>
                                            <span style="color: #aaa; font-size: 12px;"><i class="fas fa-clock"></i> Awaiting</span>
                                        <?php else: ?>
                                            <span style="color: #ccc;">—</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Suggestion column -->
                                    <td style="max-width: 180px;">
                                        <?php if ($taskRating && !empty($taskRating['review_text'])): ?>
                                            <div style="font-size: 12px; color: #e67e22; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($taskRating['review_text']); ?>">
                                                <i class="fas fa-comment-dots"></i> <?php echo htmlspecialchars($taskRating['review_text']); ?>
                                            </div>
                                        <?php elseif ($taskRating): ?>
                                            <span style="color: #aaa; font-size: 12px;"><i class="fas fa-minus-circle"></i> No suggestion</span>
                                        <?php else: ?>
                                            <span style="color: #ccc;">—</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Actions — View Details always shown -->
                                    <td>
                                        <?php if ($task['status'] === 'pending'): ?>
                                            <button class="btn-action btn-success-custom" onclick="startTask(<?php echo $task['id']; ?>)" title="Start Task">
                                                <i class="fas fa-play"></i>
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($task['status'] === 'in_progress'): ?>
                                            <button class="btn-action btn-info-custom" onclick="updateProgress(<?php echo $task['id']; ?>, <?php echo $task['progress']; ?>, '<?php echo htmlspecialchars($task['notes'] ?? '', ENT_QUOTES); ?>')" title="Update Progress">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-action btn-success-custom" onclick="showCompleteModal(<?php echo $task['id']; ?>, <?php echo htmlspecialchars(json_encode($task), ENT_QUOTES); ?>)" title="Mark Complete">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn-action btn-primary-custom" onclick="viewDetails(<?php echo htmlspecialchars(json_encode($task), ENT_QUOTES); ?>)" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Update Progress Modal -->
    <div id="progressModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Update Task Progress</h3>
                <span class="close" onclick="closeProgressModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_progress">
                <input type="hidden" name="task_id" id="progress_task_id">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                
                <div class="form-group">
                    <label>Progress (%)</label>
                    <input type="range" name="progress" id="progress_slider" class="form-control" min="0" max="100" value="0" oninput="updateProgressValue(this.value)">
                    <div style="text-align: center; font-size: 24px; font-weight: 700; color: #27AE60; margin-top: 10px;">
                        <span id="progress_value">0</span>%
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" id="progress_notes" class="form-control" rows="3" placeholder="Add any notes about the progress..."></textarea>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-action btn-secondary-custom" onclick="closeProgressModal()">Cancel</button>
                    <button type="submit" class="btn-action btn-primary-custom">Update Progress</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Complete Task Modal -->
    <div id="completeModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3><i class="fas fa-check-circle"></i> Complete Task & Submit to Manager</h3>
                <span class="close" onclick="closeCompleteModal()">&times;</span>
            </div>
            <form method="POST" action="" enctype="multipart/form-data" id="completeTaskForm">
                <input type="hidden" name="action" value="complete_task">
                <input type="hidden" name="task_id" id="complete_task_id_input">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                
                <div style="padding: 20px;">
                    <div style="background: #e8f5e9; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #27AE60;">
                        <p style="margin: 0; color: #2c3e50;">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Complete this task by uploading the finished product image and providing production details.</strong>
                            This information will be submitted to the manager and automatically added to the gallery.
                        </p>
                    </div>
                    
                    <div id="complete_task_info" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <!-- Task info will be inserted here -->
                    </div>
                    
                    <div class="form-group">
                        <label style="font-weight: 600; color: #2c3e50;">
                            <i class="fas fa-image"></i> Finished Product Image <span style="color: #E74C3C;">*</span>
                        </label>
                        <div style="border: 2px dashed #d4a574; border-radius: 8px; padding: 20px; text-align: center; background: #fafafa; cursor: pointer;" onclick="document.getElementById('finishedImage').click()">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 48px; color: #d4a574; margin-bottom: 10px;"></i>
                            <p style="margin: 10px 0; color: #2c3e50; font-weight: 600;">Click to upload finished product image</p>
                            <p style="margin: 0; color: #7f8c8d; font-size: 13px;">JPG, JPEG, PNG (Max 5MB)</p>
                            <p class="file-name" id="finishedFileName" style="margin-top: 10px; color: #27AE60; font-weight: 600;"></p>
                        </div>
                        <input type="file" id="finishedImage" name="finished_image" accept=".jpg,.jpeg,.png" style="display: none;" required onchange="showFinishedFileName()">
                    </div>
                    
                    <div class="form-group">
                        <label style="font-weight: 600; color: #2c3e50;">
                            <i class="fas fa-tools"></i> Materials Used <span style="color: #E74C3C;">*</span>
                        </label>
                        <div id="matAutoNote" style="display:none;background:#d4edda;color:#155724;padding:10px 14px;border-radius:7px;font-size:13px;margin-bottom:10px;border:1px solid #c3e6cb;">
                            <i class="fas fa-check-circle"></i> Pre-filled from your approved material requests. Adjust quantities if needed.
                        </div>
                        <div id="materialRows" style="margin-bottom:8px;"></div>
                        <button type="button" onclick="addMaterialRow()" style="padding:7px 14px;background:#3498DB;color:#fff;border:none;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;">
                            <i class="fas fa-plus"></i> Add Material
                        </button>
                        <small style="display:block;color:#7f8c8d;margin-top:6px;">Select each material from inventory and enter the quantity used.</small>
                        <textarea name="materials_used" id="materials_used_text" style="display:none;"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label style="font-weight: 600; color: #2c3e50;">
                            <i class="fas fa-comment-alt"></i> Completion Notes <span style="color: #E74C3C;">*</span>
                        </label>
                        <textarea name="completion_notes" class="form-control" rows="4" required placeholder="Describe the finished product, any challenges faced, quality notes, etc."></textarea>
                        <small style="color: #7f8c8d;">This will be visible to the manager and in the gallery</small>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-action btn-secondary-custom" onclick="closeCompleteModal()">Cancel</button>
                    <button type="submit" class="btn-action btn-success-custom">
                        <i class="fas fa-check-circle"></i> Complete Task & Submit
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Task Details Modal -->
    <div id="detailsModal" class="modal">
        <div class="modal-content" style="max-width: 900px; max-height: 90vh; overflow-y: auto;">
            <div class="modal-header">
                <h3><i class="fas fa-info-circle"></i> Complete Order & Task Details</h3>
                <span class="close" onclick="closeDetailsModal()">&times;</span>
            </div>
            <div id="task_details_content"></div>
            <div class="modal-footer">
                <button type="button" class="btn-action btn-secondary-custom" onclick="closeDetailsModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Hidden Forms -->
    <form id="startTaskForm" method="POST" action="" style="display: none;">
        <input type="hidden" name="action" value="start_task">
        <input type="hidden" name="task_id" id="start_task_id">
    </form>

    <form id="completeTaskForm" method="POST" action="" style="display: none;">
        <input type="hidden" name="action" value="complete_task">
        <input type="hidden" name="task_id" id="complete_task_id">
    </form>

    <script>
        function startTask(taskId) {
            if (confirm('Are you sure you want to start this task?')) {
                document.getElementById('start_task_id').value = taskId;
                document.getElementById('startTaskForm').submit();
            }
        }

        function updateProgress(taskId, currentProgress, notes) {
            document.getElementById('progress_task_id').value = taskId;
            document.getElementById('progress_slider').value = currentProgress;
            document.getElementById('progress_value').textContent = currentProgress;
            document.getElementById('progress_notes').value = notes;
            document.getElementById('progressModal').style.display = 'block';
        }

        function closeProgressModal() {
            document.getElementById('progressModal').style.display = 'none';
        }

        function updateProgressValue(value) {
            document.getElementById('progress_value').textContent = value;
        }

        function showCompleteModal(taskId, task) {
            document.getElementById('complete_task_id_input').value = taskId;
            
            // Display task information
            const taskInfo = `
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <div><strong>Order Number:</strong><br>${task.order_number || 'N/A'}</div>
                    <div><strong>Furniture:</strong><br>${task.furniture_name || task.product_name || 'N/A'}</div>
                    <div><strong>Customer:</strong><br>${task.customer_name || 'N/A'}</div>
                </div>
            `;
            document.getElementById('complete_task_info').innerHTML = taskInfo;

            // Clear existing material rows
            document.getElementById('materialRows').innerHTML = '';

            // Auto-populate from approved material requests for this order
            const orderId = task.order_id;
            const approved = APPROVED_BY_ORDER[orderId] || [];
            if (approved.length > 0) {
                approved.forEach(r => {
                    addMaterialRow(r.material_id, r.material_name, r.unit, r.unit_price, r.quantity_requested);
                });
                document.getElementById('matAutoNote').style.display = 'block';
            } else {
                // No approved requests — let employee add manually
                addMaterialRow();
                document.getElementById('matAutoNote').style.display = 'none';
            }

            document.getElementById('completeModal').style.display = 'block';
        }

        function closeCompleteModal() {
            document.getElementById('completeModal').style.display = 'none';
            document.getElementById('completeTaskForm').reset();
            document.getElementById('finishedFileName').textContent = '';
        }

        function showFinishedFileName() {
            const input = document.getElementById('finishedImage');
            const fileNameDisplay = document.getElementById('finishedFileName');
            if (input.files && input.files[0]) {
                fileNameDisplay.textContent = '✓ ' + input.files[0].name;
            }
        }

        function viewDetails(task) {
            // Build comprehensive order details with design image
            let dimensionsHtml = '';
            if (task.length || task.width || task.height) {
                const dims = [];
                if (task.length) dims.push(`L: ${task.length}cm`);
                if (task.width) dims.push(`W: ${task.width}cm`);
                if (task.height) dims.push(`H: ${task.height}cm`);
                dimensionsHtml = `
                    <div>
                        <strong>Dimensions:</strong><br>${dims.join(' × ')}
                    </div>
                `;
            }
            
            const content = `
                <div style="padding: 20px;">
                    <h4 style="color: #2c3e50; margin-bottom: 20px; border-bottom: 2px solid #3498DB; padding-bottom: 10px;">
                        <i class="fas fa-info-circle"></i> Task & Order Information
                    </h4>
                    
                    <!-- Task Information -->
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <h5 style="color: #2c3e50; margin-bottom: 15px;"><i class="fas fa-tasks"></i> Task Details</h5>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                            <div>
                                <strong>Task ID:</strong><br>#${String(task.id).padStart(4, '0')}
                            </div>
                            <div>
                                <strong>Order Number:</strong><br>${task.order_number || 'N/A'}
                            </div>
                            <div>
                                <strong>Assigned Date:</strong><br>${new Date(task.created_at).toLocaleDateString()}
                            </div>
                            <div>
                                <strong>Deadline:</strong><br>${task.deadline ? new Date(task.deadline).toLocaleDateString() : 'N/A'}
                            </div>
                            <div>
                                <strong>Progress:</strong><br>
                                <div style="display: flex; align-items: center; gap: 10px; margin-top: 5px;">
                                    <div style="flex: 1; background: #ecf0f1; border-radius: 10px; height: 8px; overflow: hidden;">
                                        <div style="width: ${task.progress}%; height: 100%; background: #27AE60;"></div>
                                    </div>
                                    <span style="font-weight: 600;">${task.progress}%</span>
                                </div>
                            </div>
                            <div>
                                <strong>Status:</strong><br>
                                <span style="display: inline-block; padding: 4px 12px; border-radius: 12px; background: ${task.status === 'completed' ? '#27AE60' : task.status === 'in_progress' ? '#9B59B6' : '#F39C12'}; color: white; font-size: 12px; font-weight: 600;">
                                    ${task.status.replace('_', ' ').toUpperCase()}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Customer Information -->
                    <div style="background: #e8f5e9; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <h5 style="color: #2c3e50; margin-bottom: 15px;"><i class="fas fa-user"></i> Customer Information</h5>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                            <div>
                                <strong>Customer Name:</strong><br>${task.customer_name || 'N/A'}
                            </div>
                            <div>
                                <strong>Email:</strong><br>${task.customer_email || 'N/A'}
                            </div>
                            <div>
                                <strong>Phone:</strong><br>${task.customer_phone || 'N/A'}
                            </div>
                        </div>
                    </div>
                    
                    <!-- Furniture Specifications -->
                    <div style="background: #fff3e0; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <h5 style="color: #2c3e50; margin-bottom: 15px;"><i class="fas fa-couch"></i> Furniture Specifications</h5>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                            <div>
                                <strong>Furniture Type:</strong><br>${task.furniture_type || 'N/A'}
                            </div>
                            <div>
                                <strong>Furniture Name:</strong><br>${task.furniture_name || task.product_name || 'N/A'}
                            </div>
                            ${dimensionsHtml}
                            ${task.material ? `<div><strong>Material:</strong><br>${task.material}</div>` : ''}
                            ${task.color ? `<div><strong>Color:</strong><br>${task.color}</div>` : ''}
                            ${task.estimated_cost ? `<div><strong>Estimated Cost:</strong><br>ETB ${parseFloat(task.estimated_cost).toFixed(2)}</div>` : ''}
                            ${task.preferred_delivery_date ? `<div><strong>Delivery Date:</strong><br>${new Date(task.preferred_delivery_date).toLocaleDateString()}</div>` : ''}
                        </div>
                    </div>
                    
                    <!-- Design Image -->
                    ${task.design_image ? `
                        <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                            <h5 style="color: #2c3e50; margin-bottom: 15px;"><i class="fas fa-image"></i> Design Image</h5>
                            <div style="text-align: center;">
                                <a href="<?php echo BASE_URL; ?>/public/${task.design_image}" target="_blank" style="display: inline-block;">
                                    <img src="<?php echo BASE_URL; ?>/public/${task.design_image}" 
                                         alt="Design Image" 
                                         style="max-width: 100%; max-height: 400px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); cursor: pointer;"
                                         onerror="this.parentElement.innerHTML='<p style=color:#E74C3C;><i class=fas fa-exclamation-triangle></i> Image not found</p>'">
                                </a>
                                <p style="margin-top: 10px; color: #7f8c8d; font-size: 13px;">
                                    <i class="fas fa-info-circle"></i> Click image to view full size
                                </p>
                            </div>
                        </div>
                    ` : ''}
                    
                    <!-- Design Description -->
                    ${task.design_description ? `
                        <div style="background: #f3e5f5; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                            <h5 style="color: #2c3e50; margin-bottom: 10px;"><i class="fas fa-align-left"></i> Design Description</h5>
                            <p style="color: #2c3e50; line-height: 1.6; margin: 0;">${task.design_description}</p>
                        </div>
                    ` : ''}
                    
                    <!-- Special Notes -->
                    ${task.special_notes ? `
                        <div style="background: #ffebee; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                            <h5 style="color: #2c3e50; margin-bottom: 10px;"><i class="fas fa-sticky-note"></i> Special Notes</h5>
                            <p style="color: #2c3e50; line-height: 1.6; margin: 0;">${task.special_notes}</p>
                        </div>
                    ` : ''}
                    
                    <!-- Manager Notes -->
                    ${task.manager_notes ? `
                        <div style="background: #e0f2f1; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                            <h5 style="color: #2c3e50; margin-bottom: 10px;"><i class="fas fa-user-tie"></i> Manager Notes</h5>
                            <p style="color: #2c3e50; line-height: 1.6; margin: 0;">${task.manager_notes}</p>
                        </div>
                    ` : ''}
                    
                    <!-- Task Notes -->
                    ${task.notes ? `
                        <div style="background: #fafafa; padding: 15px; border-radius: 8px;">
                            <h5 style="color: #2c3e50; margin-bottom: 10px;"><i class="fas fa-comment"></i> Task Notes</h5>
                            <p style="color: #2c3e50; line-height: 1.6; margin: 0;">${task.notes}</p>
                        </div>
                    ` : ''}

                    <!-- Customer Rating & Suggestion -->
                    ${task.status === 'completed' ? `
                        <div style="background: ${task.customer_rating ? '#fff8e1' : '#f8f9fa'}; padding: 18px; border-radius: 10px; margin-top: 20px; border: 2px solid ${task.customer_rating ? '#f39c12' : '#e9ecef'};">
                            <h5 style="color: ${task.customer_rating ? '#e67e22' : '#7f8c8d'}; margin-bottom: 12px;"><i class="fas fa-star"></i> Customer Rating &amp; Suggestion</h5>
                            ${task.customer_rating ? `
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                    <div style="font-size: 26px;">
                                        ${'<i class="fas fa-star" style="color: #f39c12;"></i>'.repeat(parseInt(task.customer_rating))}${'<i class="fas fa-star" style="color: #ddd;"></i>'.repeat(5 - parseInt(task.customer_rating))}
                                    </div>
                                    <strong style="font-size: 18px; color: #2c3e50;">${task.customer_rating}/5</strong>
                                </div>
                                ${task.customer_review && task.customer_review.trim() ? `
                                    <div style="background: white; border-left: 4px solid #f39c12; padding: 12px 15px; border-radius: 6px;">
                                        <div style="font-size: 12px; color: #e67e22; font-weight: 600; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px;">
                                            <i class="fas fa-comment-dots"></i> Customer Suggestion / Review
                                        </div>
                                        <p style="margin: 0; color: #2c3e50; line-height: 1.6; font-style: italic;">"${task.customer_review}"</p>
                                    </div>
                                ` : `<p style="margin: 0; color: #aaa; font-size: 13px;"><i class="fas fa-minus-circle"></i> No written suggestion from customer.</p>`}
                            ` : `<p style="margin: 0; color: #aaa; font-size: 13px;"><i class="fas fa-hourglass-half"></i> Customer has not submitted a rating yet.</p>`}
                        </div>
                    ` : ''}
                </div>
            `;
            document.getElementById('task_details_content').innerHTML = content;
            document.getElementById('detailsModal').style.display = 'block';
        }

        function closeDetailsModal() {
            document.getElementById('detailsModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const progressModal = document.getElementById('progressModal');
            const detailsModal = document.getElementById('detailsModal');
            const completeModal = document.getElementById('completeModal');
            if (event.target === progressModal) {
                closeProgressModal();
            }
            if (event.target === detailsModal) {
                closeDetailsModal();
            }
            if (event.target === completeModal) {
                closeCompleteModal();
            }
        }
    </script>

    <!-- Hidden Forms -->
    <form id="startTaskForm" method="POST" action="" style="display: none;">
        <input type="hidden" name="action" value="start_task">
        <input type="hidden" name="task_id" id="start_task_id">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
    </form>

    <!-- Mobile Menu Script -->
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
    <script>
    // ── Material picker for complete task modal ──
    const MATERIALS = <?php echo json_encode($allMaterials); ?>;
    // Approved requests keyed by order_id — pre-fill materials on task completion
    const APPROVED_BY_ORDER = <?php echo json_encode($approvedRequestsByOrder); ?>;

    function addMaterialRow(matId, matName, unit, unitPrice, qty) {
        const container = document.getElementById('materialRows');
        const opts = MATERIALS.map(m =>
            `<option value="${m.id}" data-unit="${m.unit}" data-price="${m.unit_price}" ${m.id == matId ? 'selected' : ''}>
                ${m.material_name} (${m.unit}) — Available: ${parseFloat(m.available_stock||0).toFixed(2)} — ETB ${parseFloat(m.unit_price).toFixed(2)}
            </option>`
        ).join('');
        const row = document.createElement('div');
        row.style.cssText = 'display:grid;grid-template-columns:2fr 1fr auto;gap:8px;margin-bottom:8px;align-items:center;';
        row.innerHTML = `
            <select name="mat_id[]" class="form-control mat-select" required onchange="updateMatUnit(this)">
                <option value="">— Select Material —</option>${opts}
            </select>
            <div style="display:flex;align-items:center;gap:4px;">
                <input type="number" name="mat_qty[]" class="form-control" step="0.01" min="0.01" required placeholder="Qty" style="width:80px;" value="${qty || ''}">
                <span class="mat-unit" style="font-size:12px;color:#7f8c8d;white-space:nowrap;">${unit || ''}</span>
            </div>
            <button type="button" onclick="this.closest('div').remove(); syncMaterialsText();" style="background:#e74c3c;color:#fff;border:none;border-radius:6px;padding:6px 10px;cursor:pointer;"><i class="fas fa-trash"></i></button>
        `;
        container.appendChild(row);
        syncMaterialsText();
    }

    function updateMatUnit(sel) {
        const opt = sel.selectedOptions[0];
        sel.closest('div').querySelector('.mat-unit').textContent = opt ? opt.dataset.unit : '';
        syncMaterialsText();
    }

    function syncMaterialsText() {
        const rows = document.querySelectorAll('#materialRows > div');
        const lines = [];
        rows.forEach(row => {
            const sel = row.querySelector('select');
            const qty = row.querySelector('input[type=number]');
            if (sel && sel.value && qty && qty.value) {
                const opt = sel.selectedOptions[0];
                lines.push(`${opt.text.split(' (')[0]}: ${qty.value} ${opt.dataset.unit}`);
            }
        });
        const txt = document.getElementById('materials_used_text');
        if (txt) txt.value = lines.join('\n');
    }

    // Sync text before form submit + validate at least one material row
    document.getElementById('completeTaskForm').addEventListener('submit', function(e) {
        syncMaterialsText();
        const rows = document.querySelectorAll('#materialRows > div');
        if (rows.length === 0) {
            e.preventDefault();
            alert('Please add at least one material used.');
            return;
        }
        // Validate all rows filled
        let valid = true;
        rows.forEach(row => {
            const sel = row.querySelector('select');
            const qty = row.querySelector('input[type=number]');
            if (!sel.value || !qty.value || parseFloat(qty.value) <= 0) valid = false;
        });
        if (!valid) {
            e.preventDefault();
            alert('Please fill in all material rows (select material and enter quantity).');
        }
    });
    </script>
</body>
</html>
