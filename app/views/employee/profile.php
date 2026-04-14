<?php
session_start();

// Get database connection
require_once __DIR__ . '/../../../config/db_config.php';
require_once __DIR__ . '/../../../config/config.php';

// Generate CSRF token if not exists
if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}

// Employee authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}

$employeeName = $_SESSION['first_name'] ?? $_SESSION['user_name'] ?? 'Employee User';
$employeeId = $_SESSION['user_id'];

// Ensure profile_image column exists
try {
    $pdo->exec("ALTER TABLE furn_users ADD COLUMN IF NOT EXISTS profile_image VARCHAR(255) DEFAULT NULL");
} catch (PDOException $e) {
    // Column might already exist
}

// Fetch employee data with profile_image
$stmt = $pdo->prepare("SELECT * FROM furn_users WHERE id = ?");
$stmt->execute([$employeeId]);
$employeeData = $stmt->fetch(PDO::FETCH_ASSOC);

// Fallback: split full_name into first/last if they are empty
if (empty($employeeData['first_name']) && !empty($employeeData['full_name'])) {
    $parts = explode(' ', trim($employeeData['full_name']), 2);
    $employeeData['first_name'] = $parts[0];
    $employeeData['last_name']  = $parts[1] ?? '';
}

// Handle profile update POST
$profileMsg = '';
$profileMsgType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'update_profile') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION[CSRF_TOKEN_NAME]) || !hash_equals($_SESSION[CSRF_TOKEN_NAME], $csrf)) {
        $profileMsg = 'Invalid request.'; $profileMsgType = 'danger';
    } else {
        $fn = trim($_POST['first_name'] ?? '');
        $ln = trim($_POST['last_name'] ?? '');
        $em = trim($_POST['email'] ?? '');
        $ph = trim($_POST['phone'] ?? '');
        $full = trim($fn . ' ' . $ln);
        try {
            $pdo->prepare("UPDATE furn_users SET first_name=?, last_name=?, full_name=?, email=?, phone=? WHERE id=?")
                ->execute([$fn, $ln, $full, $em, $ph, $employeeId]);
            // Update session name
            $_SESSION['first_name'] = $fn;
            $_SESSION['user_name']  = $full;
            $profileMsg = 'Profile updated successfully.'; $profileMsgType = 'success';
            // Refresh data
            $stmt = $pdo->prepare("SELECT * FROM furn_users WHERE id = ?");
            $stmt->execute([$employeeId]);
            $employeeData = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $profileMsg = 'Error updating profile.'; $profileMsgType = 'danger';
        }
    }
}

// Handle password change POST
$pwMsg = ''; $pwMsgType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'change_password') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION[CSRF_TOKEN_NAME]) || !hash_equals($_SESSION[CSRF_TOKEN_NAME], $csrf)) {
        $pwMsg = 'Invalid request.'; $pwMsgType = 'danger';
    } else {
        $currentPw  = $_POST['current_password'] ?? '';
        $newPw      = $_POST['new_password'] ?? '';
        $confirmPw  = $_POST['confirm_password'] ?? '';
        // Fetch current hash
        $row = $pdo->prepare("SELECT password_hash FROM furn_users WHERE id = ?");
        $row->execute([$employeeId]);
        $hash = $row->fetchColumn();
        if (empty($hash) || !password_verify($currentPw, $hash)) {
            $pwMsg = 'Current password is incorrect.'; $pwMsgType = 'danger';
        } elseif (strlen($newPw) < 8) {
            $pwMsg = 'New password must be at least 8 characters.'; $pwMsgType = 'danger';
        } elseif ($newPw !== $confirmPw) {
            $pwMsg = 'New passwords do not match.'; $pwMsgType = 'danger';
        } else {
            $pdo->prepare("UPDATE furn_users SET password_hash=?, failed_attempts=0 WHERE id=?")
                ->execute([password_hash($newPw, PASSWORD_BCRYPT), $employeeId]);
            $pwMsg = 'Password changed successfully.'; $pwMsgType = 'success';
        }
    }
}

$pageTitle = 'My Profile';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - SmartWorkshop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/css/admin-responsive.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/css/dark-mode.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .top-header { background: linear-gradient(135deg, #4a2c2a 0%, #3d1f1d 100%); color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3498DB, #2980b9);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: 700;
            color: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            position: relative;
            overflow: hidden;
        }
        .profile-avatar-img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .avatar-upload-btn {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(52, 152, 219, 0.9);
            padding: 8px;
            text-align: center;
            opacity: 0;
            transition: all 0.3s;
            cursor: pointer;
            color: white;
            font-size: 12px;
        }
        .profile-avatar:hover .avatar-upload-btn {
            opacity: 1;
        }
        .dashboard-container { display: flex; min-height: calc(100vh - 70px); }
        .main-content { flex: 1; padding: 30px; background: #f5f5f5; }
        .page-header { background: white; padding: 25px; border-radius: 15px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .page-title { font-size: 28px; font-weight: 700; color: #2c3e50; margin-bottom: 10px; }
        .section-card { background: white; padding: 30px; border-radius: 15px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .section-title { font-size: 20px; font-weight: 600; color: #3498DB; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #f0f0f0; }
        .btn-save { background: linear-gradient(135deg, #3498DB, #2980b9); color: white; border: none; padding: 12px 30px; border-radius: 8px; font-weight: 600; }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(52,152,219,0.3); color: white; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../../app/includes/employee_sidebar.php'; ?>
    
    <?php include __DIR__ . '/../../../app/includes/employee_header.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-user-circle"></i> My Profile</h1>
            <p style="color: #7f8c8d;">View and manage your employee profile</p>
        </div>
        
        <!-- Profile Header with Avatar -->
        <div class="section-card">
            <div style="display: flex; align-items: center; gap: 30px; margin-bottom: 20px;">
                <div class="profile-avatar" id="avatarContainer">
                    <?php if (!empty($employeeData['profile_image'])): ?>
                        <img src="<?php echo BASE_URL; ?>/public/uploads/profile_images/<?php echo htmlspecialchars($employeeData['profile_image']); ?>" 
                             alt="Profile Picture" 
                             class="profile-avatar-img" 
                             id="avatarPreview">
                    <?php else: ?>
                        <span id="avatarInitials"><?php echo strtoupper(substr($employeeData['first_name'] ?? 'E', 0, 1) . substr($employeeData['last_name'] ?? 'U', 0, 1)); ?></span>
                    <?php endif; ?>
                    <div class="avatar-upload-btn" onclick="document.getElementById('profileImageInput').click()">
                        <i class="fas fa-camera"></i> Change Photo
                    </div>
                </div>
                <input type="file" id="profileImageInput" accept="image/*" style="display: none;" onchange="uploadProfileImage()">
                
                <div>
                    <h2 style="margin-bottom: 5px;"><?php echo htmlspecialchars($employeeData['first_name'] . ' ' . $employeeData['last_name']); ?></h2>
                    <p style="color: #7f8c8d; margin: 0;"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($employeeData['email']); ?></p>
                    <p style="color: #7f8c8d; margin: 0;"><i class="fas fa-user"></i> EMPLOYEE</p>
                    <span style="display: inline-block; background: #3498DB; color: white; padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: 600; margin-top: 8px;">ACTIVE</span>
                </div>
            </div>
        </div>
        
        <!-- Personal Information -->
        <div class="section-card">
            <h3 class="section-title"><i class="fas fa-info-circle"></i> Personal Information</h3>
            
            <?php if ($profileMsg): ?>
            <div style="padding:12px;border-radius:8px;margin-bottom:16px;background:<?php echo $profileMsgType==='success'?'#d4edda':'#f8d7da'; ?>;color:<?php echo $profileMsgType==='success'?'#155724':'#721c24'; ?>;">
                <i class="fas fa-<?php echo $profileMsgType==='success'?'check':'exclamation'; ?>-circle me-2"></i><?php echo htmlspecialchars($profileMsg); ?>
            </div>
            <?php endif; ?>

            <!-- View Mode -->
            <div id="viewMode">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <div>
                        <label style="font-weight: 600; color: #7f8c8d; font-size: 13px; margin-bottom: 5px; display: block;">Username</label>
                        <div style="padding: 10px; background: #f8f9fa; border-radius: 8px; font-weight: 500;"><?php echo htmlspecialchars($employeeData['username']); ?></div>
                    </div>
                    <div>
                        <label style="font-weight: 600; color: #7f8c8d; font-size: 13px; margin-bottom: 5px; display: block;">First Name</label>
                        <div style="padding: 10px; background: #f8f9fa; border-radius: 8px; font-weight: 500;"><?php echo htmlspecialchars($employeeData['first_name'] ?: '—'); ?></div>
                    </div>
                    <div>
                        <label style="font-weight: 600; color: #7f8c8d; font-size: 13px; margin-bottom: 5px; display: block;">Last Name</label>
                        <div style="padding: 10px; background: #f8f9fa; border-radius: 8px; font-weight: 500;"><?php echo htmlspecialchars($employeeData['last_name'] ?: '—'); ?></div>
                    </div>
                    <div>
                        <label style="font-weight: 600; color: #7f8c8d; font-size: 13px; margin-bottom: 5px; display: block;">Email Address</label>
                        <div style="padding: 10px; background: #f8f9fa; border-radius: 8px; font-weight: 500;"><?php echo htmlspecialchars($employeeData['email']); ?></div>
                    </div>
                    <div>
                        <label style="font-weight: 600; color: #7f8c8d; font-size: 13px; margin-bottom: 5px; display: block;">Phone Number</label>
                        <div style="padding: 10px; background: #f8f9fa; border-radius: 8px; font-weight: 500;"><?php echo htmlspecialchars($employeeData['phone'] ?? 'Not provided'); ?></div>
                    </div>
                    <div>
                        <label style="font-weight: 600; color: #7f8c8d; font-size: 13px; margin-bottom: 5px; display: block;">Member Since</label>
                        <div style="padding: 10px; background: #f8f9fa; border-radius: 8px; font-weight: 500;"><?php echo date('F d, Y', strtotime($employeeData['created_at'])); ?></div>
                    </div>
                </div>
                <div style="margin-top: 20px;">
                    <button type="button" class="btn btn-save" onclick="toggleEditMode()"><i class="fas fa-edit me-2"></i>Edit Profile</button>
                </div>
            </div>
            
            <!-- Edit Mode -->
            <div id="editMode" style="display: none;">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION[CSRF_TOKEN_NAME]); ?>">
                    <input type="hidden" name="form_action" value="update_profile">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                        <div>
                            <label style="font-weight: 600; color: #7f8c8d; font-size: 13px; margin-bottom: 5px; display: block;">Username</label>
                            <div style="padding: 10px; background: #f8f9fa; border-radius: 8px; font-weight: 500;"><?php echo htmlspecialchars($employeeData['username']); ?></div>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: #2c3e50; font-size: 13px; margin-bottom: 5px; display: block;">First Name</label>
                            <input type="text" name="first_name" value="<?php echo htmlspecialchars($employeeData['first_name']); ?>" style="width:100%;padding:10px;border:2px solid #e9ecef;border-radius:8px;" required>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: #2c3e50; font-size: 13px; margin-bottom: 5px; display: block;">Last Name</label>
                            <input type="text" name="last_name" value="<?php echo htmlspecialchars($employeeData['last_name']); ?>" style="width:100%;padding:10px;border:2px solid #e9ecef;border-radius:8px;">
                        </div>
                        <div>
                            <label style="font-weight: 600; color: #2c3e50; font-size: 13px; margin-bottom: 5px; display: block;">Email Address</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($employeeData['email']); ?>" style="width:100%;padding:10px;border:2px solid #e9ecef;border-radius:8px;" required>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: #2c3e50; font-size: 13px; margin-bottom: 5px; display: block;">Phone Number</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($employeeData['phone'] ?? ''); ?>" style="width:100%;padding:10px;border:2px solid #e9ecef;border-radius:8px;" placeholder="+251-XXX-XXXXXX">
                        </div>
                        <div>
                            <label style="font-weight: 600; color: #7f8c8d; font-size: 13px; margin-bottom: 5px; display: block;">Member Since</label>
                            <div style="padding: 10px; background: #f8f9fa; border-radius: 8px; font-weight: 500;"><?php echo date('F d, Y', strtotime($employeeData['created_at'])); ?></div>
                        </div>
                    </div>
                    <div style="margin-top: 20px; display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-save"><i class="fas fa-save me-2"></i>Save Changes</button>
                        <button type="button" class="btn btn-secondary" onclick="toggleEditMode()" style="background:#6c757d;color:white;border:none;padding:12px 30px;border-radius:8px;"><i class="fas fa-times me-2"></i>Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Change Password -->
        <div class="section-card">
            <h3 class="section-title"><i class="fas fa-key"></i> Change Password</h3>
            <?php if ($pwMsg): ?>
            <div style="padding:12px;border-radius:8px;margin-bottom:16px;background:<?php echo $pwMsgType==='success'?'#d4edda':'#f8d7da'; ?>;color:<?php echo $pwMsgType==='success'?'#155724':'#721c24'; ?>;">
                <i class="fas fa-<?php echo $pwMsgType==='success'?'check':'exclamation'; ?>-circle me-2"></i><?php echo htmlspecialchars($pwMsg); ?>
            </div>
            <?php endif; ?>
            <form method="POST" style="max-width:400px;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION[CSRF_TOKEN_NAME]); ?>">
                <input type="hidden" name="form_action" value="change_password">
                <div style="margin-bottom:14px;">
                    <label style="font-weight:600;color:#2c3e50;font-size:13px;display:block;margin-bottom:5px;">Current Password</label>
                    <input type="password" name="current_password" class="form-control" required autocomplete="current-password" placeholder="Enter current password">
                </div>
                <div style="margin-bottom:14px;">
                    <label style="font-weight:600;color:#2c3e50;font-size:13px;display:block;margin-bottom:5px;">New Password</label>
                    <input type="password" name="new_password" class="form-control" required autocomplete="new-password" placeholder="At least 8 characters" minlength="8">
                </div>
                <div style="margin-bottom:20px;">
                    <label style="font-weight:600;color:#2c3e50;font-size:13px;display:block;margin-bottom:5px;">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" required autocomplete="new-password" placeholder="Repeat new password">
                </div>
                <button type="submit" class="btn btn-save"><i class="fas fa-lock me-2"></i>Change Password</button>
            </form>
        </div>
        
        <!-- Quick Actions -->
        <div class="section-card">
            <h3 class="section-title"><i class="fas fa-briefcase"></i> Quick Actions</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <a href="<?php echo BASE_URL; ?>/public/employee/dashboard" style="padding: 15px; background: #f8f9fa; border-radius: 8px; text-decoration: none; color: #2c3e50; border-left: 4px solid #3498DB; transition: all 0.3s;" onmouseover="this.style.background='#3498DB'; this.style.color='white'" onmouseout="this.style.background='#f8f9fa'; this.style.color='#2c3e50'">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="<?php echo BASE_URL; ?>/public/employee/tasks" style="padding: 15px; background: #f8f9fa; border-radius: 8px; text-decoration: none; color: #2c3e50; border-left: 4px solid #27AE60; transition: all 0.3s;" onmouseover="this.style.background='#27AE60'; this.style.color='white'" onmouseout="this.style.background='#f8f9fa'; this.style.color='#2c3e50'">
                    <i class="fas fa-tasks"></i> My Tasks
                </a>
                <a href="<?php echo BASE_URL; ?>/public/logout" style="padding: 15px; background: #f8f9fa; border-radius: 8px; text-decoration: none; color: #2c3e50; border-left: 4px solid #e74c3c; transition: all 0.3s;" onmouseover="this.style.background='#e74c3c'; this.style.color='white'" onmouseout="this.style.background='#f8f9fa'; this.style.color='#2c3e50'">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>
    
    <!-- Profile Image Upload Script -->
    <script>
    // Define BASE_URL for JavaScript
    const BASE_URL = '<?php echo BASE_URL; ?>';
    const CSRF_TOKEN = '<?php echo $_SESSION[CSRF_TOKEN_NAME] ?? ''; ?>';
    
    function uploadProfileImage() {
        const fileInput = document.getElementById('profileImageInput');
        const file = fileInput.files[0];
        
        if (!file) return;
        
        // Validate file size (5MB max)
        if (file.size > 5 * 1024 * 1024) {
            alert('File size must not exceed 5MB');
            fileInput.value = '';
            return;
        }
        
        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
            alert('Only JPG, PNG, GIF, and WEBP images are allowed');
            fileInput.value = '';
            return;
        }
        
        // Show loading state
        const avatarContainer = document.getElementById('avatarContainer');
        const originalContent = avatarContainer.innerHTML;
        avatarContainer.innerHTML = '<div style="width:120px;height:120px;border-radius:50%;background:#f0f0f0;display:flex;align-items:center;justify-content:center;"><div class="spinner-border text-primary"></div></div>';
        
        // Create FormData
        const formData = new FormData();
        formData.append('profile_image', file);
        formData.append('csrf_token', CSRF_TOKEN);
        
        // Upload via fetch
        fetch(BASE_URL + '/public/api/upload_profile_image.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Update UI with new image
                avatarContainer.innerHTML = `
                    <img src="${data.image_url}" alt="Profile Picture" class="profile-avatar-img" id="avatarPreview">
                    <div class="avatar-upload-btn" onclick="document.getElementById('profileImageInput').click()">
                        <i class="fas fa-camera"></i> Change Photo
                    </div>
                `;
                
                // Update header avatar too
                const headerAvatar = document.querySelector('.employee-avatar img');
                if (headerAvatar) {
                    headerAvatar.src = data.image_url;
                    headerAvatar.alt = "<?php echo htmlspecialchars($employeeData['first_name']); ?>";
                }
                
                alert('✓ Profile picture updated successfully!');
            } else {
                alert('Error: ' + data.message);
                avatarContainer.innerHTML = originalContent;
            }
        })
        .catch(error => {
            console.error('Upload error:', error);
            alert('Failed to upload image. Please try again. Error: ' + error.message);
            avatarContainer.innerHTML = originalContent;
        });
    }

    function toggleEditMode() {
        const viewMode = document.getElementById('viewMode');
        const editMode = document.getElementById('editMode');
        
        if (viewMode.style.display === 'none') {
            viewMode.style.display = 'block';
            editMode.style.display = 'none';
        } else {
            viewMode.style.display = 'none';
            editMode.style.display = 'block';
        }
    }

    </script>
    
    <script src="<?php echo BASE_URL; ?>/public/assets/js/dark-mode.js"></script>
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
