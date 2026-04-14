<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}
require_once __DIR__ . '/../../../config/db_config.php';

$employeeName = $_SESSION['user_name'] ?? 'Employee User';
$employeeId   = $_SESSION['user_id'];

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'request_material') {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO furn_material_requests
                    (employee_id, material_id, quantity_requested, purpose, order_id, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([
                $employeeId,
                intval($_POST['material_id']),
                floatval($_POST['quantity']),
                trim($_POST['purpose']),
                !empty($_POST['order_id']) ? intval($_POST['order_id']) : null
            ]);
            $success = "Material request submitted! Waiting for manager approval.";
        } catch (PDOException $e) {
            $error = "Error submitting request: " . $e->getMessage();
        }

    } elseif ($action === 'report_usage') {
        try {
            $taskId    = intval($_POST['task_id']);
            $notes     = trim($_POST['notes'] ?? '');
            $matIds    = $_POST['mat_id']  ?? [];
            $matQtys   = $_POST['mat_qty'] ?? [];
            $matWastes = $_POST['mat_waste'] ?? [];

            if (empty($matIds)) throw new Exception("Please add at least one material.");

            $stmtIns = $pdo->prepare("
                INSERT INTO furn_material_usage (employee_id, task_id, material_id, quantity_used, waste_amount, notes, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmtReduce = $pdo->prepare("
                UPDATE furn_material_requests
                SET quantity_requested = GREATEST(0, quantity_requested - ?)
                WHERE employee_id = ? AND material_id = ? AND status = 'approved'
                ORDER BY approved_at DESC LIMIT 1
            ");

            $count = 0;
            foreach ($matIds as $i => $matId) {
                $matId = intval($matId);
                $qty   = floatval($matQtys[$i] ?? 0);
                $waste = floatval($matWastes[$i] ?? 0);
                if (!$matId || $qty <= 0) continue;
                $stmtIns->execute([$employeeId, $taskId ?: null, $matId, $qty, $waste, $notes]);
                $stmtReduce->execute([$qty, $employeeId, $matId]);
                $count++;
            }
            if ($count === 0) throw new Exception("No valid materials entered.");
            $success = "$count material(s) usage reported successfully!";
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Auto-create tables if missing
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS furn_material_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        material_id INT NOT NULL,
        quantity_requested DECIMAL(10,2) NOT NULL,
        purpose TEXT,
        order_id INT DEFAULT NULL,
        status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        approved_by INT DEFAULT NULL,
        approved_at TIMESTAMP NULL DEFAULT NULL,
        rejection_reason TEXT DEFAULT NULL,
        manager_note TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(employee_id), INDEX(material_id), INDEX(status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Add rejection_reason column if missing (for older installs)
    try { $pdo->exec("ALTER TABLE furn_material_requests ADD COLUMN IF NOT EXISTS rejection_reason TEXT DEFAULT NULL"); } catch(PDOException $e2){}
    try { $pdo->exec("ALTER TABLE furn_material_requests ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP NULL DEFAULT NULL"); } catch(PDOException $e2){}
    try { $pdo->exec("ALTER TABLE furn_material_requests ADD COLUMN IF NOT EXISTS approved_by INT DEFAULT NULL"); } catch(PDOException $e2){}
} catch (PDOException $e) { error_log("Create requests table: " . $e->getMessage()); }

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS furn_material_usage (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        task_id INT DEFAULT NULL,
        material_id INT NOT NULL,
        quantity_used DECIMAL(10,2) NOT NULL,
        waste_amount DECIMAL(10,2) DEFAULT 0,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(employee_id), INDEX(material_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) { error_log("Create usage table: " . $e->getMessage()); }

// Fetch this employee's material requests
$requests = [];
try {
    $stmt = $pdo->prepare("
        SELECT mr.*,
               m.name as material_name, m.unit,
               o.order_number,
               CONCAT(a.first_name,' ',a.last_name) as approved_by_name
        FROM furn_material_requests mr
        LEFT JOIN furn_materials m ON mr.material_id = m.id
        LEFT JOIN furn_orders o ON mr.order_id = o.id
        LEFT JOIN furn_users a ON mr.approved_by = a.id
        WHERE mr.employee_id = ?
        ORDER BY mr.created_at DESC
    ");
    $stmt->execute([$employeeId]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("Requests error: " . $e->getMessage()); }

// Fetch usage history
$usageHistory = [];
try {
    $stmt = $pdo->prepare("
        SELECT mu.*, m.material_name, m.unit, t.id as task_number, o.order_number
        FROM furn_material_usage mu
        LEFT JOIN furn_materials m ON mu.material_id = m.id
        LEFT JOIN furn_production_tasks t ON mu.task_id = t.id
        LEFT JOIN furn_orders o ON t.order_id = o.id
        WHERE mu.employee_id = ?
        ORDER BY mu.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$employeeId]);
    $usageHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("Usage history error: " . $e->getMessage()); }

// Materials for dropdowns
$materials = [];
try {
    $stmt = $pdo->query("SELECT id, name as material_name, unit,
        current_stock, COALESCE(reserved_stock,0) as reserved_stock,
        (current_stock - COALESCE(reserved_stock,0)) as quantity
        FROM furn_materials WHERE is_active = 1 ORDER BY name ASC");
    $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("Materials error: " . $e->getMessage()); }

// Tasks assigned to this employee (pending or in_progress)
$tasks = [];
try {
    $stmt = $pdo->prepare("
        SELECT t.id, o.order_number,
               COALESCE(o.furniture_name, o.furniture_type, 'Custom Order') as product_name
        FROM furn_production_tasks t
        LEFT JOIN furn_orders o ON t.order_id = o.id
        WHERE t.employee_id = ? AND t.status IN ('pending','in_progress','assigned')
        ORDER BY t.created_at DESC
    ");
    $stmt->execute([$employeeId]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("Tasks error: " . $e->getMessage()); }

// Orders this employee is assigned to
$orders = [];
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT o.id, o.order_number, CONCAT(u.first_name, ' ', u.last_name) as customer_name
        FROM furn_orders o
        INNER JOIN furn_production_tasks t ON o.id = t.order_id
        LEFT JOIN furn_users u ON o.customer_id = u.id
        WHERE t.employee_id = ? AND o.status IN ('deposit_paid', 'in_production')
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$employeeId]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log("Orders error: " . $e->getMessage()); }

$pendingCount  = count(array_filter($requests, fn($r) => $r['status'] === 'pending'));
$approvedCount = count(array_filter($requests, fn($r) => $r['status'] === 'approved'));
$pageTitle = 'Materials';
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
    <?php include_once __DIR__ . '/../../includes/employee_sidebar.php'; ?>

    <!-- Top Header -->
    <?php 
    $pageTitle = 'Materials';
    include_once __DIR__ . '/../../includes/employee_header.php'; 
    ?>
        <div class="header-left"><div class="system-status"><i class="fas fa-circle"></i> Materials Management</div></div>
        <div class="header-right">
            <div class="admin-profile">
                <div class="admin-avatar"><?php echo strtoupper(substr($employeeName, 0, 1)); ?></div>
                <div>
                    <div style="font-weight:600;font-size:14px;"><?php echo htmlspecialchars($employeeName); ?></div>
                    <div class="admin-role-badge" style="background:#27AE60;">EMPLOYEE</div>
                </div>
            </div>
        </div>
    </div>

    <div class="main-content">
        <?php if (isset($success)): ?>
            <div style="background:#d4edda;color:#155724;padding:15px;border-radius:8px;margin-bottom:20px;border:1px solid #c3e6cb;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div style="background:#f8d7da;color:#721c24;padding:15px;border-radius:8px;margin-bottom:20px;border:1px solid #f5c6cb;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card" style="border-left:4px solid #3498DB;">
                <div style="display:flex;justify-content:space-between;align-items:start;">
                    <div><div class="stat-value"><?php echo count($requests); ?></div><div class="stat-label">Total Requests</div></div>
                    <div style="font-size:32px;color:#3498DB;"><i class="fas fa-clipboard-list"></i></div>
                </div>
            </div>
            <div class="stat-card" style="border-left:4px solid #F39C12;">
                <div style="display:flex;justify-content:space-between;align-items:start;">
                    <div><div class="stat-value"><?php echo $pendingCount; ?></div><div class="stat-label">Pending Approval</div></div>
                    <div style="font-size:32px;color:#F39C12;"><i class="fas fa-hourglass-half"></i></div>
                </div>
            </div>
            <div class="stat-card" style="border-left:4px solid #27AE60;">
                <div style="display:flex;justify-content:space-between;align-items:start;">
                    <div><div class="stat-value"><?php echo $approvedCount; ?></div><div class="stat-label">Approved</div></div>
                    <div style="font-size:32px;color:#27AE60;"><i class="fas fa-check-circle"></i></div>
                </div>
            </div>
            <div class="stat-card" style="border-left:4px solid #9B59B6;">
                <div style="display:flex;justify-content:space-between;align-items:start;">
                    <div><div class="stat-value"><?php echo count($usageHistory); ?></div><div class="stat-label">Usage Reports</div></div>
                    <div style="font-size:32px;color:#9B59B6;"><i class="fas fa-chart-bar"></i></div>
                </div>
            </div>
        </div>

        <!-- Request Material -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-plus-circle"></i> Request Materials</h2>
                <button class="btn-action btn-primary-custom" onclick="toggleRequestForm()"><i class="fas fa-plus"></i> New Request</button>
            </div>
            <div id="requestForm" style="display:none;margin-top:20px;">
                <form method="POST">
                    <input type="hidden" name="action" value="request_material">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? $_SESSION['csrf_token'] ?? ''); ?>">
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px;">
                        <div class="form-group">
                            <label>Material <span style="color:#E74C3C;">*</span></label>
                            <select name="material_id" class="form-control" required>
                                <option value="">-- Select Material --</option>
                                <?php foreach ($materials as $m):
                                    $avail = floatval($m['quantity']);
                                    $reserved = floatval($m['reserved_stock']);
                                    $disabled = $avail <= 0 ? 'disabled' : '';
                                ?>
                                    <option value="<?php echo $m['id']; ?>" <?php echo $disabled; ?>>
                                        <?php echo htmlspecialchars($m['material_name']); ?>
                                        — Available: <?php echo number_format($avail,2); ?> <?php echo $m['unit']; ?>
                                        <?php if ($reserved > 0): ?>(<?php echo number_format($reserved,2); ?> reserved)<?php endif; ?>
                                        <?php if ($avail <= 0): ?>[OUT OF STOCK]<?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Quantity <span style="color:#E74C3C;">*</span></label>
                            <input type="number" name="quantity" class="form-control" min="0.01" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label>Related Order</label>
                            <select name="order_id" class="form-control">
                                <option value="">-- Optional --</option>
                                <?php foreach ($orders as $o): ?>
                                    <option value="<?php echo $o['id']; ?>"><?php echo htmlspecialchars($o['order_number']); ?> - <?php echo htmlspecialchars($o['customer_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Purpose / Reason <span style="color:#E74C3C;">*</span></label>
                        <textarea name="purpose" class="form-control" rows="3" placeholder="Explain why you need this material..." required></textarea>
                    </div>
                    <div style="background:#FFF3CD;border:1px solid #FFE69C;border-radius:8px;padding:12px;margin-top:10px;">
                        <p style="margin:0;color:#856404;font-size:13px;"><i class="fas fa-info-circle"></i> Your request will be sent to the manager for approval. Materials are released only after approval.</p>
                    </div>
                    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
                        <button type="button" class="btn-action btn-secondary-custom" onclick="toggleRequestForm()"><i class="fas fa-times"></i> Cancel</button>
                        <button type="submit" class="btn-action btn-success-custom"><i class="fas fa-paper-plane"></i> Submit Request</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- My Requests List -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-clipboard-list"></i> My Material Requests</h2>
            </div>
            <?php if (empty($requests)): ?>
                <p style="text-align:center;color:#7f8c8d;padding:40px;">No material requests yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table mobile-cards">
                        <thead>
                            <tr><th>ID</th><th>Material</th><th>Qty</th><th>Order</th><th>Purpose</th><th>Status</th><th>Date</th><th>Approved By</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $req): ?>
                            <tr>
                                <td>#<?php echo str_pad($req['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                <td><strong><?php echo htmlspecialchars($req['material_name']); ?></strong></td>
                                <td><?php echo $req['quantity_requested']; ?> <?php echo $req['unit']; ?></td>
                                <td><?php echo $req['order_number'] ? htmlspecialchars($req['order_number']) : 'N/A'; ?></td>
                                <td><?php echo htmlspecialchars(mb_strimwidth($req['purpose'], 0, 40, '...')); ?></td>
                                <td>
                                    <?php $sc = ['pending'=>'warning','approved'=>'success','rejected'=>'danger','released'=>'info']; ?>
                                    <span class="badge badge-<?php echo $sc[$req['status']] ?? 'secondary'; ?>"><?php echo ucfirst($req['status']); ?></span>
                                    <?php if ($req['status'] === 'rejected' && $req['rejection_reason']): ?>
                                        <br><small style="color:#E74C3C;"><?php echo htmlspecialchars($req['rejection_reason']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($req['created_at'])); ?></td>
                                <td>
                                    <?php if (!empty($req['approved_by_name']) && trim($req['approved_by_name']) !== ''): ?>
                                        <?php echo htmlspecialchars($req['approved_by_name']); ?>
                                    <?php else: ?>
                                        <span style="color:#95A5A6;">Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Report Usage -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-chart-line"></i> Report Material Usage</h2>
                <button class="btn-action btn-primary-custom" onclick="toggleUsageForm()"><i class="fas fa-plus"></i> New Report</button>
            </div>
            <div id="usageForm" style="display:none;margin-top:20px;">
                <form method="POST" id="usageReportForm">
                    <input type="hidden" name="action" value="report_usage">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? $_SESSION['csrf_token'] ?? ''); ?>">

                    <!-- Task selector -->
                    <div class="form-group" style="margin-bottom:18px;">
                        <label style="font-weight:600;">Production Task <span style="color:#E74C3C;">*</span></label>
                        <select name="task_id" class="form-control" required>
                            <option value="">-- Select Task --</option>
                            <?php foreach ($tasks as $t): ?>
                                <option value="<?php echo $t['id']; ?>">
                                    Task #<?php echo str_pad($t['id'],4,'0',STR_PAD_LEFT); ?>
                                    — <?php echo htmlspecialchars($t['order_number'] ?? 'N/A'); ?>
                                    — <?php echo htmlspecialchars($t['product_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Dynamic materials table -->
                    <label style="font-weight:600;display:block;margin-bottom:8px;">Materials Used <span style="color:#E74C3C;">*</span></label>
                    <div style="overflow-x:auto;">
                        <table style="width:100%;border-collapse:collapse;margin-bottom:10px;" id="matTable">
                            <thead>
                                <tr style="background:#f8f9fa;font-size:13px;">
                                    <th style="padding:8px 10px;text-align:left;border:1px solid #dee2e6;">#</th>
                                    <th style="padding:8px 10px;text-align:left;border:1px solid #dee2e6;">Material</th>
                                    <th style="padding:8px 10px;text-align:left;border:1px solid #dee2e6;">Qty Used</th>
                                    <th style="padding:8px 10px;text-align:left;border:1px solid #dee2e6;">Waste</th>
                                    <th style="padding:8px 10px;border:1px solid #dee2e6;"></th>
                                </tr>
                            </thead>
                            <tbody id="matRows">
                                <!-- rows added by JS -->
                            </tbody>
                        </table>
                    </div>
                    <button type="button" onclick="addMatRow()" style="background:#3498db;color:white;border:none;padding:8px 16px;border-radius:6px;font-size:13px;cursor:pointer;margin-bottom:16px;">
                        <i class="fas fa-plus"></i> Add Material
                    </button>

                    <div class="form-group">
                        <label>General Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Any additional notes about this usage report..."></textarea>
                    </div>

                    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;">
                        <button type="button" class="btn-action btn-secondary-custom" onclick="toggleUsageForm()"><i class="fas fa-times"></i> Cancel</button>
                        <button type="submit" class="btn-action btn-success-custom"><i class="fas fa-check"></i> Submit Report</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Usage History -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-history"></i> Material Usage History</h2>
            </div>
            <?php if (empty($usageHistory)): ?>
                <p style="text-align:center;color:#7f8c8d;padding:40px;">No usage reports yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table mobile-cards">
                        <thead>
                            <tr><th>ID</th><th>Task</th><th>Order</th><th>Material</th><th>Qty Used</th><th>Waste</th><th>Date</th><th>Notes</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usageHistory as $u): ?>
                            <tr>
                                <td>#<?php echo str_pad($u['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                <td>#<?php echo str_pad($u['task_number'] ?? 0, 4, '0', STR_PAD_LEFT); ?></td>
                                <td><?php echo htmlspecialchars($u['order_number'] ?? 'N/A'); ?></td>
                                <td><strong><?php echo htmlspecialchars($u['material_name']); ?></strong></td>
                                <td><?php echo $u['quantity_used']; ?> <?php echo $u['unit']; ?></td>
                                <td>
                                    <?php if ($u['waste_amount'] > 0): ?>
                                        <span style="color:#E74C3C;"><?php echo $u['waste_amount']; ?> <?php echo $u['unit']; ?></span>
                                    <?php else: ?>
                                        <span style="color:#27AE60;">0</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($u['notes'] ?: '—'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div style="background:#EBF5FB;border:1px solid #AED6F1;border-radius:8px;padding:12px 16px;margin-top:12px;">
                    <i class="fas fa-info-circle" style="color:#3498DB;"></i>
                    <span style="color:#1A5276;font-size:13px;"> Manager feedback on your usage reports is visible in your <a href="<?php echo BASE_URL; ?>/public/employee/reports" style="color:#2980B9;font-weight:600;">Reports page</a>.</span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function toggleRequestForm() {
        const f = document.getElementById('requestForm');
        f.style.display = f.style.display === 'none' ? 'block' : 'none';
    }
    function toggleUsageForm() {
        const f = document.getElementById('usageForm');
        const isHidden = f.style.display === 'none' || f.style.display === '';
        f.style.display = isHidden ? 'block' : 'none';
        if (isHidden && document.getElementById('matRows').children.length === 0) {
            addMatRow(); // start with one empty row
        }
    }

    // Materials data for dropdowns — shows available (not total) stock
    const MATERIALS = <?php echo json_encode(array_map(fn($m) => [
        'id'       => $m['id'],
        'name'     => $m['material_name'],
        'unit'     => $m['unit'],
        'qty'      => $m['quantity'],        // available = current - reserved
        'reserved' => $m['reserved_stock'],
    ], $materials)); ?>;

    let rowCount = 0;

    function addMatRow() {
        rowCount++;
        const tbody = document.getElementById('matRows');
        const tr = document.createElement('tr');
        tr.id = 'matRow_' + rowCount;

        let opts = '<option value="">-- Select --</option>';
        MATERIALS.forEach(m => {
            const avail = parseFloat(m.qty);
            const reserved = parseFloat(m.reserved || 0);
            const label = `${m.name} (${m.unit}) — Available: ${avail.toFixed(2)}${reserved > 0 ? ' ('+reserved.toFixed(2)+' reserved)' : ''}${avail <= 0 ? ' [OUT OF STOCK]' : ''}`;
            opts += `<option value="${m.id}" data-unit="${m.unit}" data-avail="${avail}" ${avail <= 0 ? 'disabled' : ''}>${label}</option>`;
        });

        tr.innerHTML = `
            <td style="padding:8px 10px;border:1px solid #dee2e6;color:#aaa;font-size:13px;">${rowCount}</td>
            <td style="padding:6px 8px;border:1px solid #dee2e6;">
                <select name="mat_id[]" class="form-control mat-select" style="min-width:200px;" required onchange="updateUnit(this, ${rowCount})">
                    ${opts}
                </select>
            </td>
            <td style="padding:6px 8px;border:1px solid #dee2e6;">
                <div style="display:flex;align-items:center;gap:6px;">
                    <input type="number" name="mat_qty[]" class="form-control" style="width:100px;" min="0.01" step="0.01" placeholder="0.00" required>
                    <span id="unit_${rowCount}" style="color:#7f8c8d;font-size:12px;white-space:nowrap;"></span>
                </div>
            </td>
            <td style="padding:6px 8px;border:1px solid #dee2e6;">
                <div style="display:flex;align-items:center;gap:6px;">
                    <input type="number" name="mat_waste[]" class="form-control" style="width:100px;" min="0" step="0.01" placeholder="0.00" value="0">
                    <span style="color:#e74c3c;font-size:12px;">waste</span>
                </div>
            </td>
            <td style="padding:6px 8px;border:1px solid #dee2e6;text-align:center;">
                <button type="button" onclick="removeMatRow(${rowCount})" style="background:#e74c3c;color:white;border:none;border-radius:4px;padding:4px 10px;cursor:pointer;font-size:13px;">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    }

    function updateUnit(sel, rowId) {
        const opt = sel.options[sel.selectedIndex];
        document.getElementById('unit_' + rowId).textContent = opt.dataset.unit || '';
    }

    function removeMatRow(rowId) {
        const row = document.getElementById('matRow_' + rowId);
        if (row) row.remove();
    }

    document.getElementById('usageReportForm').addEventListener('submit', function(e) {
        const rows = document.querySelectorAll('#matRows tr');
        if (rows.length === 0) {
            e.preventDefault();
            alert('Please add at least one material.');
        }
    });
    </script>
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
