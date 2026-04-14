<?php
// Customer authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}

require_once __DIR__ . '/../../../config/db_config.php';

$customerId = $_SESSION['user_id'];
$customerName = $_SESSION['first_name'] ?? $_SESSION['user_name'] ?? 'Customer';
$message = '';
$messageType = '';

// Fetch customer data
$customerData = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM furn_users WHERE id = ?");
    $stmt->execute([$customerId]);
    $customerData = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Customer data fetch error: " . $e->getMessage());
}

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    
    try {
        $stmt = $pdo->prepare("UPDATE furn_users SET email = ?, phone = ?, address = ? WHERE id = ?");
        $stmt->execute([$email, $phone, $address, $customerId]);
        $message = 'Settings updated successfully!';
        $messageType = 'success';
        
        // Refresh customer data
        $stmt = $pdo->prepare("SELECT * FROM furn_users WHERE id = ?");
        $stmt->execute([$customerId]);
        $customerData = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $message = 'Error updating settings: ' . $e->getMessage();
        $messageType = 'error';
    }
}

$pageTitle = 'Customer Settings';
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
        .settings-container { max-width: 600px; margin: 0 auto; }
        .settings-card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50; }
        .form-input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
        .form-input:focus { outline: none; border-color: #d4a574; box-shadow: 0 0 0 3px rgba(212, 165, 116, 0.1); }
        .btn-save { background: #3D1F14; color: white; padding: 12px 30px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .btn-save:hover { background: #2a1610; }
        .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
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
    <?php include_once __DIR__ . '/../../includes/customer_sidebar.php'; ?>

    <!-- Top Header -->
    <?php 
    $pageTitle = 'Settings';
    include_once __DIR__ . '/../../includes/customer_header.php'; 
    ?>
        <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
            <span style="font-size: 20px;">🔨</span>
            <span style="font-weight: 700; font-size: 16px; color: white;"><span style="color: #e67e22;">Smart</span>Workshop</span>
            <span style="color: rgba(255,255,255,0.4); margin: 0 5px;">|</span>
            <span style="font-size: 14px; color: rgba(255,255,255,0.85);">Furniture ERP &nbsp;<strong style="color:white;">Settings</strong></span>
        </div>
        <div class="header-right" style="display: flex; align-items: center; gap: 15px;">
            <div class="system-status" style="background: #27AE60; color: white; padding: 5px 12px; border-radius: 20px; font-size: 12px; display: flex; align-items: center; gap: 6px;">
                <span style="width: 8px; height: 8px; background: white; border-radius: 50%; display: inline-block;"></span> Operational
            </div>
            <div style="position: relative; cursor: pointer;">
                <i class="fas fa-bell" style="font-size: 18px; color: rgba(255,255,255,0.85);"></i>
            </div>
            <div class="admin-profile" style="display: flex; align-items: center; gap: 10px;">
                <div class="admin-avatar" style="background: #9B59B6;"><?php echo strtoupper(substr($customerName, 0, 1)); ?></div>
                <div>
                    <div style="font-size: 13px; font-weight: 600; color: white;"><?php echo htmlspecialchars($customerName); ?></div>
                    <div class="admin-role-badge" style="background: #9B59B6;">CUSTOMER</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <h2 style="margin-bottom: 30px; color: #2c3e50;">Settings</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="settings-container">
            <div class="settings-card">
                <h3 style="margin-top: 0; color: #2c3e50;">Account Settings</h3>
                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-input" value="<?php echo htmlspecialchars($customerData['email'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phone" class="form-input" value="<?php echo htmlspecialchars($customerData['phone'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-input" style="resize: vertical; min-height: 100px;"><?php echo htmlspecialchars($customerData['address'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" class="btn-save">Save Changes</button>
                </form>
            </div>

            <div class="settings-card">
                <h3 style="margin-top: 0; color: #2c3e50;">Account Information</h3>
                <div style="display: grid; gap: 15px;">
                    <div>
                        <strong style="color: #7f8c8d;">Full Name</strong>
                        <p style="margin: 5px 0 0 0; color: #2c3e50;"><?php echo htmlspecialchars($customerData['full_name'] ?? 'N/A'); ?></p>
                    </div>
                    <div>
                        <strong style="color: #7f8c8d;">Member Since</strong>
                        <p style="margin: 5px 0 0 0; color: #2c3e50;"><?php echo date('M j, Y', strtotime($customerData['created_at'] ?? 'now')); ?></p>
                    </div>
                    <div>
                        <strong style="color: #7f8c8d;">Account Status</strong>
                        <p style="margin: 5px 0 0 0; color: #2c3e50;"><span style="background: #d4edda; color: #155724; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">Active</span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Menu Script -->
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
