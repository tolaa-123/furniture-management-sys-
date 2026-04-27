<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    header('Location: ' . BASE_URL . '/public/login'); exit();
}
require_once __DIR__ . '/../../../config/db_config.php';
$managerName = $_SESSION['user_name'] ?? 'Manager';
$managerId   = $_SESSION['user_id'];
$csrf_token  = $_SESSION['csrf_token'] ?? null;
if (!$csrf_token) { $csrf_token = bin2hex(random_bytes(32)); $_SESSION['csrf_token'] = $csrf_token; }

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS furn_supplier_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        manager_id INT NOT NULL,
        supplier_name VARCHAR(255) NOT NULL,
        supplier_email VARCHAR(255) NOT NULL,
        material_name VARCHAR(255) NOT NULL,
        quantity DECIMAL(10,2) NOT NULL,
        unit VARCHAR(50) NOT NULL,
        urgency ENUM('normal','urgent','critical') NOT NULL DEFAULT 'normal',
        delivery_date DATE DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        email_sent TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(manager_id), INDEX(created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    try { $pdo->exec("ALTER TABLE furn_supplier_requests ADD COLUMN IF NOT EXISTS delivery_date DATE DEFAULT NULL"); } catch(PDOException $e2){}
} catch (PDOException $e) {}

$success = $error = '';

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    header('Content-Type: application/json');
    
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit();
    }
    
    $requestId = intval($_POST['request_id'] ?? 0);
    
    if ($requestId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid request ID']);
        exit();
    }
    
    try {
        // Verify the request belongs to this manager or allow any manager to delete
        $stmt = $pdo->prepare("DELETE FROM furn_supplier_requests WHERE id = ?");
        $stmt->execute([$requestId]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Request deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Request not found']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Invalid CSRF token.';
    } else {
        $supplierName  = trim($_POST['supplier_name'] ?? '');
        $supplierEmail = trim($_POST['supplier_email'] ?? '');
        $urgency       = in_array($_POST['urgency']??'',['normal','urgent','critical']) ? $_POST['urgency'] : 'normal';
        $deliveryDate  = trim($_POST['delivery_date'] ?? '') ?: null;
        $notes         = trim($_POST['notes'] ?? '');
        $matNames      = $_POST['material_name'] ?? [];
        $matQtys       = $_POST['quantity']      ?? [];
        $matUnits      = $_POST['unit']          ?? [];

        if (!$supplierName || !$supplierEmail) {
            $error = 'Supplier name and email are required.';
        } elseif (!filter_var($supplierEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid supplier email address.';
        } else {
            $validMaterials = [];
            foreach ($matNames as $i => $mName) {
                $mName = trim($mName); $mQty = floatval($matQtys[$i]??0); $mUnit = trim($matUnits[$i]??'');
                if ($mName && $mQty > 0 && $mUnit) $validMaterials[] = ['name'=>$mName,'qty'=>$mQty,'unit'=>$mUnit];
            }
            if (empty($validMaterials)) $error = 'Please fill in all material rows correctly.';
        }

        if (!$error) {
            try {
                $urgencyLabel = ['normal'=>'Normal','urgent'=>'Urgent','critical'=>'Critical'][$urgency];
                $urgencyColor = ['normal'=>'#27AE60','urgent'=>'#F39C12','critical'=>'#E74C3C'][$urgency];

                $stmt = $pdo->prepare("INSERT INTO furn_supplier_requests
                    (manager_id,supplier_name,supplier_email,material_name,quantity,unit,urgency,delivery_date,notes,created_at)
                    VALUES (?,?,?,?,?,?,?,?,?,NOW())");
                foreach ($validMaterials as $m) {
                    $stmt->execute([$managerId,$supplierName,$supplierEmail,$m['name'],$m['qty'],$m['unit'],$urgency,$deliveryDate,$notes?:null]);
                }

                $matRows = '';
                foreach ($validMaterials as $idx => $m) {
                    $bg = $idx % 2 === 0 ? '#f8f4e9' : '#fff';
                    $matRows .= "<tr style='background:{$bg};'><td style='padding:9px 14px;border:1px solid #e0d5c5;'>" . htmlspecialchars($m['name']) . "</td><td style='padding:9px 14px;border:1px solid #e0d5c5;text-align:center;font-weight:600;'>" . number_format($m['qty'],2) . "</td><td style='padding:9px 14px;border:1px solid #e0d5c5;text-align:center;'>" . htmlspecialchars($m['unit']) . "</td></tr>";
                }
                $deliveryLine = $deliveryDate ? "<p style='margin:4px 0;font-size:14px;'><strong>Required Delivery Date:</strong> <span style='color:#E74C3C;font-weight:700;'>" . date('F j, Y', strtotime($deliveryDate)) . "</span></p>" : '';
                $subject = "[{$urgencyLabel}] Material Request from SmartWorkshop (" . count($validMaterials) . " item" . (count($validMaterials)>1?'s':'') . ")";
                $body = "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:0;'><div style='max-width:640px;margin:30px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.12);'><div style='background:linear-gradient(135deg,#3d1f14,#6b3a2a);color:#fff;padding:28px 32px;text-align:center;'><div style='font-size:28px;margin-bottom:6px;'>&#128296;</div><h1 style='margin:0;font-size:22px;'>SmartWorkshop</h1><p style='margin:6px 0 0;font-size:14px;opacity:.85;'>Material Supply Request</p></div><div style='padding:32px;'><p style='color:#444;font-size:15px;'>Dear <strong>" . htmlspecialchars($supplierName) . "</strong>,</p><p style='color:#444;'>We would like to request the following material(s) from your company:</p><div style='background:#f8f4e9;border-left:4px solid #e67e22;border-radius:6px;padding:14px 18px;margin:16px 0;'><p style='margin:4px 0;font-size:14px;'><strong>Urgency:</strong> <span style='display:inline-block;padding:3px 12px;border-radius:20px;font-weight:700;font-size:12px;color:white;background:{$urgencyColor};'>{$urgencyLabel}</span></p>{$deliveryLine}</div><table style='width:100%;border-collapse:collapse;margin:16px 0;font-size:14px;'><thead><tr style='background:#3d1f14;color:#fff;'><th style='padding:11px 14px;text-align:left;'>Material Name</th><th style='padding:11px 14px;text-align:center;'>Quantity</th><th style='padding:11px 14px;text-align:center;'>Unit</th></tr></thead><tbody>{$matRows}</tbody></table>" . ($notes ? "<p style='color:#444;'><strong>Notes:</strong> " . nl2br(htmlspecialchars($notes)) . "</p>" : "") . "<p style='color:#444;margin-top:20px;'>Please confirm availability and provide a quotation at your earliest convenience.</p><hr style='border:none;border-top:1px solid #eee;margin:20px 0;'><p style='color:#888;font-size:13px;'>Requested by: <strong style='color:#444;'>" . htmlspecialchars($managerName) . "</strong><br>Date: <strong style='color:#444;'>" . date('F j, Y') . "</strong></p></div><div style='background:#f8f4e9;text-align:center;padding:16px;font-size:12px;color:#888;'>SmartWorkshop &mdash; Custom Furniture Crafted for You</div></div></body></html>";

                $emailSent = false;
                try {
                    require_once __DIR__ . '/../../services/EmailService.php';
                    $emailService = new EmailService();
                    $emailSent = $emailService->sendRaw($supplierEmail, $subject, $body);
                } catch (Exception $e) { error_log("Supplier email: " . $e->getMessage()); }

                if ($emailSent) {
                    $pdo->prepare("UPDATE furn_supplier_requests SET email_sent=1 WHERE manager_id=? AND supplier_email=? AND created_at >= DATE_SUB(NOW(), INTERVAL 5 SECOND)")->execute([$managerId,$supplierEmail]);
                }
                $success = count($validMaterials) . " material request(s) submitted!" . ($emailSent ? " Email sent to {$supplierEmail}." : " (Email could not be sent — check SMTP settings.)");
            } catch (PDOException $e) { $error = "Error: " . $e->getMessage(); }
        }
    }
}

$suppliers = [];
try {
    $s = $pdo->query("SELECT DISTINCT supplier as supplier, '' as supplier_email FROM furn_materials WHERE supplier IS NOT NULL AND supplier != '' ORDER BY supplier ASC");
    $suppliers = $s->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
try {
    $s = $pdo->query("SELECT name as supplier, COALESCE(email,'') as supplier_email FROM furn_suppliers WHERE is_active=1 ORDER BY name ASC");
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) $suppliers[] = $row;
} catch (PDOException $e) {}

$materials = [];
try {
    $s = $pdo->query("SELECT name, unit FROM furn_materials WHERE (is_active IS NULL OR is_active=1) ORDER BY name ASC");
    $materials = $s->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$history = [];
try {
    // Auto-delete records older than 90 days
    $pdo->exec("DELETE FROM furn_supplier_requests WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");

    $s = $pdo->prepare("SELECT sr.*, CONCAT(u.first_name,' ',u.last_name) as manager_name FROM furn_supplier_requests sr LEFT JOIN furn_users u ON sr.manager_id=u.id ORDER BY sr.created_at DESC LIMIT 30");
    $s->execute();
    $history = $s->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$pageTitle = 'Supplier Request';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Request - SmartWorkshop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/css/admin-responsive.css">
    <style>
        .sr-card { background:#fff; border-radius:14px; box-shadow:0 2px 16px rgba(0,0,0,.08); margin-bottom:24px; overflow:hidden; }
        .sr-card-header { background:linear-gradient(135deg,#3d1f14,#6b3a2a); color:#fff; padding:18px 24px; display:flex; align-items:center; gap:12px; }
        .sr-card-header h2 { margin:0; font-size:17px; font-weight:700; }
        .sr-card-body { padding:24px; }
        .field-label { font-weight:600; font-size:13px; color:#2c3e50; display:block; margin-bottom:6px; }
        .field-label span { color:#E74C3C; }
        .form-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:18px; margin-bottom:18px; }
        .form-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:18px; margin-bottom:18px; }
        @media(max-width:768px){ .form-grid-2,.form-grid-3{ grid-template-columns:1fr; } }
        .mat-table { width:100%; border-collapse:collapse; font-size:13px; }
        .mat-table thead tr { background:#f0f2f5; }
        .mat-table th { padding:10px 12px; text-align:left; border:1px solid #e0e0e0; font-weight:600; color:#555; font-size:12px; text-transform:uppercase; letter-spacing:.4px; }
        .mat-table td { padding:6px 8px; border:1px solid #e8e8e8; vertical-align:middle; }
        .mat-table tbody tr:hover { background:#fafbfc; }
        .urgency-badge { display:inline-flex; align-items:center; gap:5px; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:700; }
        .badge-normal   { background:#27AE6018; color:#27AE60; }
        .badge-urgent   { background:#F39C1218; color:#F39C12; }
        .badge-critical { background:#E74C3C18; color:#E74C3C; }
        .info-banner { background:#fff8f0; border-left:4px solid #e67e22; border-radius:8px; padding:12px 16px; font-size:13px; color:#856404; margin-bottom:18px; }
        .btn-add-mat { background:#3498db; color:#fff; border:none; padding:9px 18px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:6px; transition:background .2s; }
        .btn-add-mat:hover { background:#2980b9; }
        .btn-send { background:linear-gradient(135deg,#27AE60,#1e8449); color:#fff; border:none; padding:12px 28px; border-radius:8px; font-size:14px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:all .2s; }
        .btn-send:hover { transform:translateY(-1px); box-shadow:0 4px 12px rgba(39,174,96,.3); }
        .btn-reset { background:#f0f2f5; color:#555; border:none; padding:12px 22px; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:background .2s; }
        .btn-reset:hover { background:#e0e3e8; }
        .btn-request-again { background:#e67e22; color:#fff; border:none; padding:6px 14px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:5px; transition:background .2s; white-space:nowrap; width:100%; justify-content:center; }
        .btn-request-again:hover { background:#d35400; }
        .btn-delete-request { background:#e74c3c; color:#fff; border:none; padding:6px 14px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:5px; transition:background .2s; white-space:nowrap; width:100%; justify-content:center; }
        .btn-delete-request:hover { background:#c0392b; }
        .hist-table { width:100%; border-collapse:collapse; font-size:13px; }
        .hist-table thead tr { background:#f8f9fa; }
        .hist-table th { padding:11px 14px; text-align:left; border-bottom:2px solid #e9ecef; font-weight:600; color:#555; font-size:12px; text-transform:uppercase; letter-spacing:.4px; }
        .hist-table td { padding:11px 14px; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
        .hist-table tbody tr:hover { background:#fafbfc; }
        .email-sent   { color:#27AE60; font-weight:600; }
        .email-failed { color:#E74C3C; font-weight:600; }
    </style>
</head>
<body>
<button class="mobile-menu-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>
<div class="sidebar-overlay"></div>
<?php include_once __DIR__ . '/../../includes/manager_sidebar.php'; ?>
<?php $pageTitle = 'Supplier Request'; include_once __DIR__ . '/../../includes/manager_header.php'; ?>

<div class="main-content">

    <?php if ($success): ?>
    <div id="successMsg" style="background:#d4edda;color:#155724;padding:14px 18px;border-radius:10px;margin-bottom:20px;border:1px solid #c3e6cb;display:flex;align-items:center;gap:10px;font-weight:600;">
        <i class="fas fa-check-circle" style="font-size:20px;"></i> <?php echo htmlspecialchars($success); ?>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div style="background:#f8d7da;color:#721c24;padding:14px 18px;border-radius:10px;margin-bottom:20px;border:1px solid #f5c6cb;display:flex;align-items:center;gap:10px;font-weight:600;">
        <i class="fas fa-exclamation-circle" style="font-size:20px;"></i> <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <!-- ── REQUEST FORM ── -->
    <div class="sr-card" id="formSection">
        <div class="sr-card-header">
            <div style="width:40px;height:40px;background:rgba(255,255,255,.15);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                <i class="fas fa-truck" style="font-size:18px;"></i>
            </div>
            <div>
                <h2>Request Material from Supplier</h2>
                <p style="margin:2px 0 0;font-size:12px;opacity:.8;">Fill the form and send a material request email to your supplier</p>
            </div>
        </div>
        <div class="sr-card-body">
            <form method="POST" id="supplierForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <!-- Row 1: Supplier Name + Email -->
                <div class="form-grid-2">
                    <div>
                        <label class="field-label">Supplier Name <span>*</span></label>
                        <input type="text" name="supplier_name" id="supplierName" class="form-control"
                               placeholder="e.g. Addis Wood Supplies" required list="supplierList">
                        <datalist id="supplierList">
                            <?php foreach ($suppliers as $sup): ?>
                            <option value="<?php echo htmlspecialchars($sup['supplier']); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div>
                        <label class="field-label">Supplier Email <span>*</span></label>
                        <input type="email" name="supplier_email" id="supplierEmail" class="form-control"
                               placeholder="supplier@example.com" required>
                    </div>
                </div>

                <!-- Row 2: Urgency + Delivery Date + Notes -->
                <div class="form-grid-3">
                    <div>
                        <label class="field-label">Urgency <span>*</span></label>
                        <select name="urgency" id="urgencySelect" class="form-control" required onchange="updateUrgencyStyle()">
                            <option value="normal">🟢 Normal</option>
                            <option value="urgent">🟡 Urgent</option>
                            <option value="critical">🔴 Critical</option>
                        </select>
                    </div>
                    <div>
                        <label class="field-label">Required Delivery Date</label>
                        <input type="date" name="delivery_date" id="deliveryDate" class="form-control"
                               min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                        <small style="color:#7f8c8d;font-size:11px;">Leave blank if flexible</small>
                    </div>
                    <div>
                        <label class="field-label">Notes / Special Instructions</label>
                        <textarea name="notes" class="form-control" rows="2"
                                  placeholder="Quality specs, packaging, etc."></textarea>
                    </div>
                </div>

                <!-- Materials Table -->
                <div style="margin-bottom:10px;">
                    <label class="field-label">Materials to Request <span>*</span></label>
                </div>
                <div style="overflow-x:auto;margin-bottom:12px;border-radius:8px;border:1px solid #e0e0e0;">
                    <table class="mat-table" id="matTable">
                        <thead>
                            <tr>
                                <th style="width:40px;">#</th>
                                <th>Material Name</th>
                                <th style="width:130px;">Quantity</th>
                                <th style="width:110px;">Unit</th>
                                <th style="width:50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="matRows"></tbody>
                    </table>
                </div>
                <button type="button" onclick="addMatRow()" class="btn-add-mat" style="margin-bottom:20px;">
                    <i class="fas fa-plus"></i> Add Material
                </button>

                <div class="info-banner">
                    <i class="fas fa-info-circle"></i>
                    One email will be sent to the supplier listing all requested materials. Each item is saved in the history below.
                </div>

                <div style="display:flex;gap:12px;flex-wrap:wrap;">
                    <button type="submit" class="btn-send">
                        <i class="fas fa-paper-plane"></i> Send Request to Supplier
                    </button>
                    <button type="button" onclick="resetForm()" class="btn-reset">
                        <i class="fas fa-redo"></i> Reset Form
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── REQUEST HISTORY ── -->
    <div class="sr-card">
        <div class="sr-card-header">
            <div style="width:40px;height:40px;background:rgba(255,255,255,.15);border-radius:10px;display:flex;align-items:center;justify-content:center;">
                <i class="fas fa-history" style="font-size:18px;"></i>
            </div>
            <div>
                <h2>Request History</h2>
                <p style="margin:2px 0 0;font-size:12px;opacity:.8;">Click "Request Again" to pre-fill the supplier details &nbsp;|&nbsp; Records kept for 90 days</p>
            </div>
        </div>
        <div class="sr-card-body" style="padding:0;">
            <?php if (empty($history)): ?>
            <div style="text-align:center;padding:50px 20px;color:#aaa;">
                <i class="fas fa-inbox" style="font-size:40px;display:block;margin-bottom:12px;opacity:.4;"></i>
                No requests sent yet.
            </div>
            <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="hist-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Supplier</th>
                            <th>Material</th>
                            <th>Qty</th>
                            <th>Urgency</th>
                            <th>Delivery</th>
                            <th>Email</th>
                            <th>By</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($history as $r):
                        $uc = ['normal'=>'badge-normal','urgent'=>'badge-urgent','critical'=>'badge-critical'][$r['urgency']] ?? 'badge-normal';
                    ?>
                    <tr>
                        <td style="white-space:nowrap;color:#7f8c8d;font-size:12px;"><?php echo date('M d, Y', strtotime($r['created_at'])); ?></td>
                        <td>
                            <div style="font-weight:600;color:#2c3e50;"><?php echo htmlspecialchars($r['supplier_name']); ?></div>
                            <div style="font-size:11px;color:#7f8c8d;"><?php echo htmlspecialchars($r['supplier_email']); ?></div>
                        </td>
                        <td style="font-weight:500;"><?php echo htmlspecialchars($r['material_name']); ?></td>
                        <td style="font-weight:600;"><?php echo number_format($r['quantity'],2); ?> <span style="color:#7f8c8d;font-size:11px;"><?php echo htmlspecialchars($r['unit']); ?></span></td>
                        <td><span class="urgency-badge <?php echo $uc; ?>"><?php echo ucfirst($r['urgency']); ?></span></td>
                        <td style="font-size:12px;color:#7f8c8d;">
                            <?php echo $r['delivery_date'] ? '<span style="color:#E74C3C;font-weight:600;">'.date('M d, Y',strtotime($r['delivery_date'])).'</span>' : '<span style="color:#aaa;">—</span>'; ?>
                        </td>
                        <td>
                            <?php if ($r['email_sent']): ?>
                                <span class="email-sent"><i class="fas fa-check-circle"></i> Sent</span>
                            <?php else: ?>
                                <span class="email-failed"><i class="fas fa-times-circle"></i> Failed</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:12px;color:#7f8c8d;"><?php echo htmlspecialchars($r['manager_name'] ?? 'N/A'); ?></td>
                        <td style="min-width:140px;">
                            <div style="display:flex;flex-direction:column;gap:6px;">
                                <button class="btn-request-again"
                                    onclick="requestAgain('<?php echo htmlspecialchars(addslashes($r['supplier_name'])); ?>','<?php echo htmlspecialchars(addslashes($r['supplier_email'])); ?>')">
                                    <i class="fas fa-redo"></i> Request Again
                                </button>
                                <button class="btn-delete-request"
                                    onclick="deleteRequest(<?php echo $r['id']; ?>)">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
const MATERIALS = <?php echo json_encode(array_column($materials, 'unit', 'name')); ?>;
const SUPPLIERS = <?php echo json_encode(array_column($suppliers, 'supplier_email', 'supplier')); ?>;
let rowCount = 0;

function addMatRow(name='', qty='', unit='') {
    rowCount++;
    const tbody = document.getElementById('matRows');
    const tr = document.createElement('tr');
    tr.id = 'matRow_' + rowCount;
    const matOpts = Object.keys(MATERIALS).map(n => `<option value="${n.replace(/"/g,'&quot;')}">`).join('');
    tr.innerHTML = `
        <td style="text-align:center;color:#aaa;font-size:13px;font-weight:600;">${rowCount}</td>
        <td>
            <input type="text" name="material_name[]" class="form-control" style="min-width:200px;"
                   placeholder="Material name" required list="matList_${rowCount}" value="${name}"
                   oninput="autoUnit(this,${rowCount})">
            <datalist id="matList_${rowCount}">${matOpts}</datalist>
        </td>
        <td>
            <input type="number" name="quantity[]" class="form-control" style="width:100px;"
                   min="0.01" step="0.01" placeholder="0.00" value="${qty}" required>
        </td>
        <td>
            <input type="text" name="unit[]" id="unit_${rowCount}" class="form-control"
                   style="width:85px;" placeholder="kg/pcs" value="${unit}" required>
        </td>
        <td style="text-align:center;">
            <button type="button" onclick="removeRow(${rowCount})"
                style="background:#e74c3c;color:#fff;border:none;border-radius:6px;padding:5px 10px;cursor:pointer;font-size:13px;">
                <i class="fas fa-trash"></i>
            </button>
        </td>`;
    tbody.appendChild(tr);
}

function autoUnit(input, rowId) {
    const unit = MATERIALS[input.value];
    if (unit) document.getElementById('unit_' + rowId).value = unit;
}

function removeRow(id) {
    const row = document.getElementById('matRow_' + id);
    if (row) row.remove();
    if (document.getElementById('matRows').children.length === 0) addMatRow();
}

function resetForm() {
    document.getElementById('supplierForm').reset();
    document.getElementById('matRows').innerHTML = '';
    rowCount = 0;
    addMatRow();
}

function requestAgain(supplierName, supplierEmail) {
    document.getElementById('supplierName').value  = supplierName;
    document.getElementById('supplierEmail').value = supplierEmail;
    document.getElementById('matRows').innerHTML = '';
    rowCount = 0;
    addMatRow();
    document.getElementById('formSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
    // Highlight the form briefly
    const card = document.getElementById('formSection');
    card.style.boxShadow = '0 0 0 3px #e67e22';
    setTimeout(() => { card.style.boxShadow = '0 2px 16px rgba(0,0,0,.08)'; }, 1500);
}

function deleteRequest(requestId) {
    if (!confirm('Are you sure you want to delete this request? This action cannot be undone.')) {
        return;
    }
    
    fetch('<?php echo BASE_URL; ?>/public/manager/supplier-request', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=delete&request_id=' + requestId + '&csrf_token=<?php echo $csrf_token; ?>'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Request deleted successfully!');
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to delete request'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the request');
    });
}

document.getElementById('supplierName').addEventListener('change', function() {
    const email = SUPPLIERS[this.value];
    if (email) document.getElementById('supplierEmail').value = email;
});

window.addEventListener('DOMContentLoaded', function() {
    addMatRow();
    <?php if ($success): ?>
    resetForm();
    setTimeout(function() {
        const msg = document.getElementById('successMsg');
        if (msg) { msg.style.transition='opacity .5s'; msg.style.opacity='0'; setTimeout(()=>msg.remove(),500); }
    }, 5000);
    <?php endif; ?>
});
</script>
<script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
