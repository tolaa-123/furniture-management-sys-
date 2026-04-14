<?php
// Admin authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}

// Get database connection
require_once __DIR__ . '/../../../config/db_config.php';

$adminName = $_SESSION['user_name'] ?? 'Admin User';

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle user actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_user') {
        // CSRF token check
        if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $message = 'Invalid CSRF token.';
            $messageType = 'danger';
            return;
        }
        try {
            // Validate inputs
            $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
            $full_name = trim($_POST['full_name']);
            $role = $_POST['role'];
            $password = $_POST['password'];
            
            if (!$email) {
                throw new Exception('Invalid email address');
            }
            
            if (strlen($password) < 6) {
                throw new Exception('Password must be at least 6 characters');
            }
            
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM furn_users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                throw new Exception('Email already exists');
            }
            
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Split full name into first and last name
            $nameParts = explode(' ', $full_name, 2);
            $first_name = $nameParts[0];
            $last_name = $nameParts[1] ?? '';
            
            // Generate username from email (part before @)
            $username = explode('@', $email)[0];
            
            // Ensure username is unique by appending a number if needed
            $baseUsername = $username;
            $counter = 1;
            while (true) {
                $checkStmt = $pdo->prepare("SELECT id FROM furn_users WHERE username = ?");
                $checkStmt->execute([$username]);
                if (!$checkStmt->fetch()) {
                    break;
                }
                $username = $baseUsername . $counter;
                $counter++;
            }
            
            // Insert new user
            $stmt = $pdo->prepare("
                INSERT INTO furn_users (username, email, password_hash, full_name, first_name, last_name, role, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$username, $email, $hashed_password, $full_name, $first_name, $last_name, $role]);
            
            $message = 'User created successfully!';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Error creating user: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
    
    if ($action === 'delete' && isset($_POST['user_id'])) {
        // CSRF token check
        if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $message = 'Invalid CSRF token.';
            $messageType = 'danger';
            return;
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM furn_users WHERE id = ? AND role != 'admin'");
            $stmt->execute([$_POST['user_id']]);
            $message = 'User deleted successfully';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error deleting user: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
    
    if ($action === 'update_role' && isset($_POST['user_id']) && isset($_POST['role'])) {
        // CSRF token check
        if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $message = 'Invalid CSRF token.';
            $messageType = 'danger';
            return;
        }
        try {
            $stmt = $pdo->prepare("UPDATE furn_users SET role = ? WHERE id = ?");
            $stmt->execute([$_POST['role'], $_POST['user_id']]);
            $message = 'User role updated successfully';
            $messageType = 'success';
        } catch (PDOException $e) {
            $message = 'Error updating role: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Fetch statistics
$stats = [
    'total_users' => 0,
    'pending_orders' => 0,
    'low_stock_materials' => 0,
    'customers' => 0,
    'employees' => 0,
    'managers' => 0
];

try {
    $stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM furn_users")->fetchColumn();
    $stats['pending_orders'] = $pdo->query("SELECT COUNT(*) FROM furn_orders WHERE status = 'pending_cost_approval'")->fetchColumn();
    $stats['low_stock_materials'] = $pdo->query("SELECT COUNT(*) FROM furn_materials WHERE (current_stock - COALESCE(reserved_stock,0)) < minimum_stock AND is_active = 1")->fetchColumn();
    $stats['customers'] = $pdo->query("SELECT COUNT(*) FROM furn_users WHERE role = 'customer'")->fetchColumn();
    $stats['employees'] = $pdo->query("SELECT COUNT(*) FROM furn_users WHERE role = 'employee'")->fetchColumn();
    $stats['managers'] = $pdo->query("SELECT COUNT(*) FROM furn_users WHERE role = 'manager'")->fetchColumn();
} catch (PDOException $e) {
    error_log("Stats error: " . $e->getMessage());
}

// Fetch all users
$users = [];
try {
    $stmt = $pdo->query("SELECT * FROM furn_users ORDER BY created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Users fetch error: " . $e->getMessage());
}

$pageTitle = 'Users Management';
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
        .role-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .role-admin {
            background: #e74c3c;
            color: white;
        }
        .role-manager {
            background: #3498db;
            color: white;
        }
        .role-employee {
            background: #f39c12;
            color: white;
        }
        .role-customer {
            background: #9b59b6;
            color: white;
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

    <!-- Sidebar -->
    <?php include_once __DIR__ . '/../../includes/admin_sidebar.php'; ?>

    <!-- Header -->
    <!-- Top Header -->
    <?php 
    $pageTitle = 'Users';
    include_once __DIR__ . '/../../includes/admin_header.php'; 
    ?>
        <div class="system-status">
            <span style="width: 10px; height: 10px; background: white; border-radius: 50%; display: inline-block;"></span>
            <span>Operational</span>
        </div>
        <div style="display: flex; align-items: center; gap: 20px;">
            <div class="notification-bell">
                <i class="fas fa-bell"></i>
                <?php if($stats['pending_orders'] > 0): ?>
                <span class="notification-badge"><?php echo $stats['pending_orders']; ?></span>
                <?php endif; ?>
            </div>
            <div class="admin-profile">
                <div class="admin-avatar"><?php echo strtoupper(substr($adminName, 0, 1)); ?></div>
                <div>
                    <div style="font-size: 14px; font-weight: 600;"><?php echo htmlspecialchars($adminName); ?></div>
                    <div class="admin-role-badge">Administrator</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <h2 style="margin-bottom: 30px; color: #2c3e50;">Users Management</h2>
        
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['customers']); ?></div>
                <div class="stat-label">Customers</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['employees']); ?></div>
                <div class="stat-label">Employees</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['managers']); ?></div>
                <div class="stat-label">Managers</div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="section-card">
            <div class="section-header">
                <div class="section-title"><i class="fas fa-users me-2"></i>All Users</div>
                <button class="btn-action btn-success-custom" onclick="openAddModal()">
                    <i class="fas fa-plus me-1"></i>Add New User
                </button>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px;">
                <input type="text" id="userSearch" placeholder="Search by name or email..."
                    style="flex:1; min-width:200px; padding:9px 14px; border:1px solid #ddd; border-radius:8px; font-size:13px; outline:none;">
                <select id="roleFilter" style="padding:9px 14px; border:1px solid #ddd; border-radius:8px; font-size:13px; outline:none;">
                    <option value="">All Roles</option>
                    <option value="admin">Admin</option>
                    <option value="manager">Manager</option>
                    <option value="employee">Employee</option>
                    <option value="customer">Customer</option>
                </select>
                <button onclick="document.getElementById('userSearch').value=''; document.getElementById('roleFilter').value=''; filterUsers();"
                    style="padding:9px 16px; background:#f0f0f0; border:1px solid #ddd; border-radius:8px; font-size:13px; cursor:pointer;">
                    <i class="fas fa-times"></i> Clear
                </button>
                <span id="userCount" style="padding:9px 0; font-size:13px; color:#7f8c8d; align-self:center;"></span>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="userBody">
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id'] ?? 'N/A'; ?></td>
                            <td><strong><?php echo htmlspecialchars($user['full_name'] ?: 'N/A'); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="role-badge role-<?php echo $user['role']; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <?php if ($user['role'] !== 'admin'): ?>
                                <button class="btn-action btn-primary-custom" onclick="editUser(<?php echo $user['id'] ?? ''; ?>, '<?php echo htmlspecialchars($user['full_name']); ?>', '<?php echo $user['role']; ?>')">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id'] ?? ''; ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                                    <button type="submit" class="btn-action btn-danger-custom">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                                <?php else: ?>
                                <span style="color: #7f8c8d; font-size: 13px;">Protected</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div id="addModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: white; padding: 30px; border-radius: 12px; width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto;">
            <h3 style="margin-bottom: 20px; color: #2c3e50;"><i class="fas fa-user-plus"></i> Add New User</h3>
            <form method="POST" id="addUserForm">
                <input type="hidden" name="action" value="add_user">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? (($_SESSION['csrf_token'] = bin2hex(random_bytes(32))) ? $_SESSION['csrf_token'] : bin2hex(random_bytes(32)))); ?>">
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Full Name <span style="color: red;">*</span></label>
                    <input type="text" name="full_name" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;" placeholder="John Doe">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Email <span style="color: red;">*</span></label>
                    <input type="email" name="email" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;" placeholder="user@example.com">
                </div>
                
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Password <span style="color: red;">*</span></label>
                    <input type="password" name="password" required minlength="6" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;" placeholder="Minimum 6 characters">
                    <small style="color: #7f8c8d; font-size: 12px;">Password must be at least 6 characters</small>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Role <span style="color: red;">*</span></label>
                    <select name="role" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                        <option value="">Select Role...</option>
                        <option value="customer">Customer</option>
                        <option value="employee">Employee</option>
                        <option value="manager">Manager</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="closeAddModal()" style="padding: 10px 20px; border: 1px solid #ddd; background: white; border-radius: 6px; cursor: pointer;">Cancel</button>
                    <button type="submit" class="btn-action btn-success-custom" style="padding: 10px 20px;">
                        <i class="fas fa-save"></i> Create User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: white; padding: 30px; border-radius: 12px; width: 90%; max-width: 500px;">
            <h3 style="margin-bottom: 20px;">Edit User</h3>
            <form method="POST">
                <input type="hidden" name="action" value="update_role">
                <input type="hidden" name="user_id" id="edit_user_id">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? (($_SESSION['csrf_token'] = bin2hex(random_bytes(32))) ? $_SESSION['csrf_token'] : bin2hex(random_bytes(32)))); ?>">
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">User Name</label>
                    <input type="text" id="edit_user_name" readonly style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; background: #f8f9fa;">
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Role</label>
                    <select name="role" id="edit_user_role" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px;">
                        <option value="customer">Customer</option>
                        <option value="employee">Employee</option>
                        <option value="manager">Manager</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="closeModal()" style="padding: 10px 20px; border: 1px solid #ddd; background: white; border-radius: 6px; cursor: pointer;">Cancel</button>
                    <button type="submit" class="btn-action btn-success-custom" style="padding: 10px 20px;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Users search + role filter
        function filterUsers() {
            const q      = document.getElementById('userSearch').value.toLowerCase().trim();
            const role   = document.getElementById('roleFilter').value.toLowerCase();
            const rows   = document.getElementById('userBody').querySelectorAll('tr');
            let visible  = 0;
            rows.forEach(row => {
                const text     = row.textContent.toLowerCase();
                const roleCell = row.querySelector('[class*="role-"]');
                const rowRole  = roleCell ? roleCell.textContent.trim().toLowerCase() : '';
                const match    = (!q || text.includes(q)) && (!role || rowRole === role);
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            document.getElementById('userCount').textContent = visible + ' of ' + rows.length + ' users';
        }
        document.getElementById('userSearch').addEventListener('input', filterUsers);
        document.getElementById('roleFilter').addEventListener('change', filterUsers);
        filterUsers();

        function openAddModal() {
            document.getElementById('addModal').style.display = 'flex';
        }
        
        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
            document.getElementById('addUserForm').reset();
        }
        
        function editUser(id, name, role) {
            document.getElementById('edit_user_id').value = id;
            document.getElementById('edit_user_name').value = name;
            document.getElementById('edit_user_role').value = role;
            document.getElementById('editModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addModal');
            const editModal = document.getElementById('editModal');
            if (event.target === addModal) {
                closeAddModal();
            }
            if (event.target === editModal) {
                closeModal();
            }
        }
    </script>
    <!-- Mobile Menu Script -->
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
