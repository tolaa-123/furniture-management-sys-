<?php
// Admin authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}

$csrf_token = $_SESSION['csrf_token'] ?? null;
if (!$csrf_token) {
    $csrf_token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrf_token;
}

require_once __DIR__ . '/../../../config/db_config.php';

// Check database connection
if (!$pdo) {
    die('<div style="padding:20px;background:#f8d7da;color:#721c24;border-radius:8px;margin:20px;text-align:center;"><h3>Database Connection Error</h3><p>Unable to connect to the database. Please check your configuration.</p></div>');
}

$adminName = $_SESSION['user_name'] ?? 'Admin User';

// Handle employee actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF token check for all POST actions
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $message = 'Invalid CSRF token.';
        $messageType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'add') {
            try {
                // Validate password is provided
                if (empty($_POST['password'])) {
                    $message = 'Password is required.';
                    $messageType = 'danger';
                } else {
                    // Get the employee role_id from roles table
                    $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE role_name = 'employee' LIMIT 1");
                    $roleStmt->execute();
                    $roleRow = $roleStmt->fetch(PDO::FETCH_ASSOC);
                    $roleId = $roleRow ? $roleRow['id'] : null;

                    $hashedPassword = password_hash($_POST['password'], PASSWORD_BCRYPT);
                    $fullName = trim($_POST['full_name']);
                    $nameParts = explode(' ', $fullName, 2);
                    $firstName = $nameParts[0];
                    $lastName = $nameParts[1] ?? '';
                    $username = strtolower(explode('@', $_POST['email'])[0]) . '_' . time();

                    $stmt = $pdo->prepare("
                        INSERT INTO furn_users 
                        (role_id, username, email, password_hash, full_name, first_name, last_name, phone, role, status, is_active, failed_attempts, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'employee', 'active', 1, 0, NOW())
                    ");
                    $stmt->execute([$roleId, $username, $_POST['email'], $hashedPassword, $fullName, $firstName, $lastName, $_POST['phone']]);
                    $message = 'Employee added successfully';
                    $messageType = 'success';
                }
            } catch (PDOException $e) {
                $message = 'Error adding employee: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
        if ($action === 'update') {
            try {
                $stmt = $pdo->prepare("UPDATE furn_users SET full_name = ?, email = ?, phone = ? WHERE id = ? AND role = 'employee'");
                $stmt->execute([$_POST['full_name'], $_POST['email'], $_POST['phone'], $_POST['employee_id']]);
                // Reset password if provided
                if (!empty($_POST['new_password'])) {
                    $newHash = password_hash($_POST['new_password'], PASSWORD_BCRYPT);
                    $pdo->prepare("UPDATE furn_users SET password_hash = ?, failed_attempts = 0 WHERE id = ? AND role = 'employee'")
                        ->execute([$newHash, $_POST['employee_id']]);
                }
                // Upsert salary config
                $empId    = (int)$_POST['employee_id'];
                $baseSal  = (float)($_POST['base_salary'] ?? 0);
                $workDays = max(1,(int)($_POST['working_days_per_month'] ?? 26));
                $otRate   = (float)($_POST['overtime_rate'] ?? 0);
                try {
                    $pdo->exec("CREATE TABLE IF NOT EXISTS furn_employee_salary (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        employee_id INT NOT NULL UNIQUE,
                        base_salary DECIMAL(10,2) NOT NULL DEFAULT 0,
                        working_days_per_month INT NOT NULL DEFAULT 26,
                        overtime_rate DECIMAL(10,2) NOT NULL DEFAULT 0,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    )");
                    $pdo->prepare("INSERT INTO furn_employee_salary (employee_id,base_salary,working_days_per_month,overtime_rate)
                        VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE base_salary=VALUES(base_salary),
                        working_days_per_month=VALUES(working_days_per_month),overtime_rate=VALUES(overtime_rate)")
                        ->execute([$empId,$baseSal,$workDays,$otRate]);
                } catch (PDOException $e2) {}
                $message = 'Employee updated successfully';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error updating employee: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
        if ($action === 'deactivate') {
            try {
                $pdo->prepare("UPDATE furn_users SET is_active = 0, status = 'inactive' WHERE id = ? AND role = 'employee'")
                    ->execute([$_POST['employee_id']]);
                $message = 'Employee deactivated successfully';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error deactivating employee: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
}

// Fetch statistics
$stats = [
    'total_employees'  => 0,
    'active_employees' => 0,
    'inactive_employees'=> 0,
    'total_payroll'    => 0,
    'avg_salary'       => 0,
    'present_today'    => 0,
    'pending_orders'   => 0,
    'low_stock_materials' => 0,
];

try {
    $stats['total_employees']   = $pdo->query("SELECT COUNT(*) FROM furn_users WHERE role = 'employee'")->fetchColumn();
    $stats['inactive_employees']= $pdo->query("SELECT COUNT(*) FROM furn_users WHERE role = 'employee' AND (is_active = 0 OR status = 'inactive')")->fetchColumn();
    $stats['active_employees']  = $stats['total_employees'];
    $stats['pending_orders']    = $pdo->query("SELECT COUNT(*) FROM furn_orders WHERE status = 'pending_cost_approval'")->fetchColumn();
    $stats['low_stock_materials']= $pdo->query("SELECT COUNT(*) FROM furn_materials WHERE current_stock < minimum_stock")->fetchColumn();
    // Monthly approved payroll
    try {
        $stats['total_payroll'] = $pdo->query("SELECT COALESCE(SUM(net_salary),0) FROM furn_payroll WHERE status='approved' AND month=MONTH(CURDATE()) AND year=YEAR(CURDATE())")->fetchColumn();
    } catch (PDOException $e) {}
    // Avg base salary
    try {
        $stats['avg_salary'] = $pdo->query("SELECT COALESCE(AVG(base_salary),0) FROM furn_employee_salary")->fetchColumn();
    } catch (PDOException $e) {}
    // Present today
    try {
        $stats['present_today'] = $pdo->query("SELECT COUNT(DISTINCT employee_id) FROM furn_attendance WHERE DATE(COALESCE(check_in_time, check_in, created_at)) = CURDATE()")->fetchColumn();
    } catch (PDOException $e) {}
} catch (PDOException $e) {
    error_log("Stats error: " . $e->getMessage());
}

// Fetch all employees
$employees = [];
try {
    $stmt = $pdo->query("
        SELECT u.*, COALESCE(s.base_salary,0) as base_salary,
               COALESCE(s.working_days_per_month,26) as working_days_per_month,
               COALESCE(s.overtime_rate,0) as overtime_rate
        FROM furn_users u
        LEFT JOIN furn_employee_salary s ON u.id = s.employee_id
        WHERE u.role = 'employee' ORDER BY u.created_at DESC
    ");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // furn_employee_salary may not exist yet — fallback
    try {
        $stmt = $pdo->query("SELECT * FROM furn_users WHERE role = 'employee' ORDER BY created_at DESC");
        $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) { error_log("Employees fetch error: " . $e2->getMessage()); }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees Management - FurnitureCraft</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/css/admin-responsive.css">
    <style>
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            animation: slideDown 0.3s ease;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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

    <?php include_once __DIR__ . '/../../includes/admin_sidebar.php'; ?>

    <!-- Top Header -->
    <?php 
    $pageTitle = 'Employees';
    include_once __DIR__ . '/../../includes/admin_header.php'; 
    ?>

    <div class="main-content">
        <h2 style="margin-bottom: 30px; color: #2c3e50;">Employees Management</h2>
        <?php if ($message): ?><div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-value"><?php echo number_format($stats['total_employees']); ?></div><div class="stat-label">Total Employees</div></div>
            <div class="stat-card"><div class="stat-value"><?php echo number_format($stats['active_employees']); ?></div><div class="stat-label">Active</div></div>
            <div class="stat-card"><div class="stat-value" style="color:#E74C3C;"><?php echo number_format($stats['inactive_employees']); ?></div><div class="stat-label">Inactive</div></div>
            <div class="stat-card"><div class="stat-value">ETB <?php echo number_format($stats['total_payroll'], 0); ?></div><div class="stat-label">Payroll This Month</div></div>
            <div class="stat-card"><div class="stat-value">ETB <?php echo number_format($stats['avg_salary'], 0); ?></div><div class="stat-label">Avg Salary</div></div>
            <div class="stat-card"><div class="stat-value" style="color:#27AE60;"><?php echo number_format($stats['present_today']); ?></div><div class="stat-label">Present Today</div></div>
        </div>

        <div class="section-card">
            <div class="section-header">
                <div class="section-title"><i class="fas fa-user-tie me-2"></i>All Employees</div>
                <button class="btn-action btn-success-custom" onclick="showAddModal()"><i class="fas fa-plus me-1"></i>Add New Employee</button>
            </div>
            <!-- Search bar -->
            <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px;">
                <input type="text" id="empSearch" placeholder="Search by name, email or phone..."
                    style="flex:1; min-width:200px; padding:9px 14px; border:1px solid #ddd; border-radius:8px; font-size:13px; outline:none;">
                <button onclick="document.getElementById('empSearch').value=''; filterEmployees();"
                    style="padding:9px 16px; background:#f0f0f0; border:1px solid #ddd; border-radius:8px; font-size:13px; cursor:pointer;">
                    <i class="fas fa-times"></i> Clear
                </button>
                <span id="empCount" style="padding:9px 0; font-size:13px; color:#7f8c8d; align-self:center;"></span>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Full Name</th><th>Email</th><th>Phone</th><th>Joined</th><th>Actions</th></tr></thead>
                    <tbody id="empBody">
                        <?php foreach ($employees as $emp): ?>
                        <tr>
                            <td><?php echo $emp['id'] ?? 'N/A'; ?></td>
                            <td><strong><?php echo htmlspecialchars($emp['full_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($emp['email']); ?></td>
                            <td><?php echo htmlspecialchars($emp['phone']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($emp['created_at'])); ?></td>
                            <td>
                                <button class="btn-action btn-primary-custom" onclick="editEmployee(<?php echo htmlspecialchars(json_encode($emp), ENT_QUOTES, 'UTF-8'); ?>)"><i class="fas fa-edit"></i> Edit</button>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Deactivate this employee?');">
                                    <input type="hidden" name="action" value="deactivate">
                                    <input type="hidden" name="employee_id" value="<?php echo intval($emp['id'] ?? 0); ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? $csrf_token); ?>">
                                    <button type="submit" class="btn-action btn-danger-custom"><i class="fas fa-ban"></i> Deactivate</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="addModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; overflow-y: auto;">
        <div style="background: white; padding: 30px; border-radius: 12px; width: 90%; max-width: 600px; margin: 20px;">
            <h3 style="margin-bottom: 20px;">Add New Employee</h3>
            <form method="POST" id="addEmpForm">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? $csrf_token); ?>">
                <div class="form-group"><label>Full Name *</label><input type="text" name="full_name" class="form-control" required></div>
                <div class="form-group"><label>Email *</label><input type="email" name="email" class="form-control" required></div>
                <div class="form-group"><label>Phone</label><input type="text" name="phone" class="form-control"></div>

                <!-- Method toggle -->
                <div style="display:flex;gap:0;margin-bottom:16px;border:1px solid #ddd;border-radius:8px;overflow:hidden;">
                    <button type="button" id="btnMethodPassword" onclick="setMethod('password')"
                        style="flex:1;padding:10px;border:none;background:#4a3428;color:white;font-weight:600;cursor:pointer;font-size:13px;">
                        <i class="fas fa-key me-1"></i> Set Password Now
                    </button>
                    <button type="button" id="btnMethodInvite" onclick="setMethod('invite')"
                        style="flex:1;padding:10px;border:none;background:#f0f0f0;color:#555;font-weight:600;cursor:pointer;font-size:13px;">
                        <i class="fas fa-envelope me-1"></i> Send Invite Link
                    </button>
                </div>

                <!-- Password section -->
                <div id="passwordSection">
                    <div class="form-group"><label>Password *</label><input type="password" name="password" id="addPasswordField" class="form-control"></div>
                </div>

                <!-- Invite section -->
                <div id="inviteSection" style="display:none;">
                    <div style="background:#e8f4fd;border:1px solid #bee3f8;border-radius:8px;padding:12px 14px;font-size:13px;color:#2c5282;">
                        <i class="fas fa-info-circle me-1"></i>
                        An email will be sent to the employee with a link to set their own password. The link expires in 48 hours.
                    </div>
                    <div id="inviteResult" style="display:none;margin-top:12px;"></div>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" onclick="closeAddModal()" style="padding: 10px 20px; border: 1px solid #ddd; background: white; border-radius: 6px; cursor: pointer;">Cancel</button>
                    <button type="submit" id="addEmpSubmitBtn" class="btn-action btn-success-custom" style="padding: 10px 20px;">Add Employee</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; overflow-y: auto;">
        <div style="background: white; padding: 30px; border-radius: 12px; width: 90%; max-width: 600px; margin: 20px;">
            <h3 style="margin-bottom: 20px;">Edit Employee</h3>
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="employee_id" id="edit_employee_id">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? $csrf_token); ?>">
                <div class="form-group"><label>Full Name *</label><input type="text" name="full_name" id="edit_full_name" class="form-control" required></div>
                <div class="form-group"><label>Email *</label><input type="email" name="email" id="edit_email" class="form-control" required></div>
                <div class="form-group"><label>Phone</label><input type="text" name="phone" id="edit_phone" class="form-control"></div>
                <!-- Salary Config -->
                <hr style="margin:16px 0;border-color:#F0F0F0;">
                <div style="font-size:13px;font-weight:700;color:#2C3E50;margin-bottom:12px;"><i class="fas fa-money-bill-wave" style="color:#27AE60;margin-right:6px;"></i> Salary Configuration</div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label style="font-size:12px;">Base Salary (ETB)</label>
                        <input type="number" name="base_salary" id="edit_base_salary" class="form-control" step="0.01" min="0" placeholder="e.g. 8000">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label style="font-size:12px;">Working Days/Month</label>
                        <input type="number" name="working_days_per_month" id="edit_work_days" class="form-control" min="1" max="31" value="26">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label style="font-size:12px;">Overtime Rate (ETB/hr)</label>
                        <input type="number" name="overtime_rate" id="edit_ot_rate" class="form-control" step="0.01" min="0" value="0">
                    </div>
                </div>
                <!-- Reset Password (optional) -->
                <hr style="margin:16px 0;border-color:#F0F0F0;">
                <div style="font-size:13px;font-weight:700;color:#2C3E50;margin-bottom:12px;"><i class="fas fa-key" style="color:#e74c3c;margin-right:6px;"></i> Reset Password <span style="font-weight:400;color:#7f8c8d;font-size:12px;">(leave blank to keep current)</span></div>
                <div class="form-group">
                    <input type="password" name="new_password" id="edit_new_password" class="form-control" placeholder="Enter new password to reset" autocomplete="new-password">
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" onclick="closeEditModal()" style="padding: 10px 20px; border: 1px solid #ddd; background: white; border-radius: 6px; cursor: pointer;">Cancel</button>
                    <button type="submit" class="btn-action btn-success-custom" style="padding: 10px 20px;">Update Employee</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Employee search filter
        function filterEmployees() {
            const q     = document.getElementById('empSearch').value.toLowerCase().trim();
            const rows  = document.getElementById('empBody').querySelectorAll('tr');
            let visible = 0;
            rows.forEach(row => {
                const match = !q || row.textContent.toLowerCase().includes(q);
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            document.getElementById('empCount').textContent = visible + ' of ' + rows.length + ' employees';
        }
        document.getElementById('empSearch').addEventListener('input', filterEmployees);
        filterEmployees();

        let currentMethod = 'password';

        function setMethod(method) {
            currentMethod = method;
            const pwSection  = document.getElementById('passwordSection');
            const invSection = document.getElementById('inviteSection');
            const btnPw      = document.getElementById('btnMethodPassword');
            const btnInv     = document.getElementById('btnMethodInvite');
            const pwField    = document.getElementById('addPasswordField');
            const submitBtn  = document.getElementById('addEmpSubmitBtn');
            if (method === 'password') {
                pwSection.style.display  = '';
                invSection.style.display = 'none';
                btnPw.style.background   = '#4a3428'; btnPw.style.color = 'white';
                btnInv.style.background  = '#f0f0f0'; btnInv.style.color = '#555';
                pwField.required         = true;
                submitBtn.textContent    = 'Add Employee';
            } else {
                pwSection.style.display  = 'none';
                invSection.style.display = '';
                btnPw.style.background   = '#f0f0f0'; btnPw.style.color = '#555';
                btnInv.style.background  = '#4a3428'; btnInv.style.color = 'white';
                pwField.required         = false; pwField.value = '';
                submitBtn.textContent    = 'Send Invite';
            }
        }

        function showAddModal() {
            document.getElementById('addModal').style.display = 'flex';
            document.getElementById('inviteResult').style.display = 'none';
            setMethod('password');
        }
        function closeAddModal() { document.getElementById('addModal').style.display = 'none'; }

        document.getElementById('addEmpForm').addEventListener('submit', function(e) {
            if (currentMethod !== 'invite') return;
            e.preventDefault();
            const btn    = document.getElementById('addEmpSubmitBtn');
            const result = document.getElementById('inviteResult');
            const data   = new FormData(this);
            btn.disabled = true; btn.textContent = 'Sending...';
            fetch('<?php echo BASE_URL; ?>/public/api/invite_employee.php', { method: 'POST', body: data })
            .then(r => r.json())
            .then(res => {
                result.style.display = 'block';
                if (res.success) {
                    let html = '<div style="background:#d4edda;color:#155724;padding:12px;border-radius:8px;font-size:13px;"><i class="fas fa-check-circle me-1"></i>' + res.message + '</div>';
                    if (res.invite_link) {
                        html += '<div style="margin-top:10px;background:#fff3cd;padding:10px;border-radius:8px;font-size:12px;word-break:break-all;"><strong>Share this link:</strong><br><a href="' + res.invite_link + '" target="_blank">' + res.invite_link + '</a></div>';
                    }
                    result.innerHTML = html;
                    setTimeout(() => location.reload(), 3500);
                } else {
                    result.innerHTML = '<div style="background:#f8d7da;color:#721c24;padding:12px;border-radius:8px;font-size:13px;"><i class="fas fa-exclamation-circle me-1"></i>' + res.message + '</div>';
                    btn.disabled = false; btn.textContent = 'Send Invite';
                }
            })
            .catch(() => {
                result.style.display = 'block';
                result.innerHTML = '<div style="background:#f8d7da;color:#721c24;padding:12px;border-radius:8px;font-size:13px;">Network error. Please try again.</div>';
                btn.disabled = false; btn.textContent = 'Send Invite';
            });
        });

        function editEmployee(emp) {
            document.getElementById('edit_employee_id').value   = emp.id;
            document.getElementById('edit_full_name').value     = emp.full_name;
            document.getElementById('edit_email').value         = emp.email;
            document.getElementById('edit_phone').value         = emp.phone || '';
            document.getElementById('edit_base_salary').value   = emp.base_salary || '';
            document.getElementById('edit_work_days').value     = emp.working_days_per_month || 26;
            document.getElementById('edit_ot_rate').value       = emp.overtime_rate || 0;
            document.getElementById('editModal').style.display  = 'flex';
        }
        function closeEditModal() { document.getElementById('editModal').style.display = 'none'; }
    </script>
    <!-- Mobile Menu Script -->
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
