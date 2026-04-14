<?php
// Session and authentication already handled by index.php
require_once __DIR__ . '/../../../config/db_config.php';
require_once __DIR__ . '/../../../config/config.php';

// Generate CSRF token if not exists
if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}

$customerId = $_SESSION['user_id'] ?? 0;
$customerName = $_SESSION['first_name'] ?? $_SESSION['user_name'] ?? 'Customer';

// Ensure profile_image column exists
try {
    $pdo->exec("ALTER TABLE furn_users ADD COLUMN IF NOT EXISTS profile_image VARCHAR(255) DEFAULT NULL");
} catch (PDOException $e) {
    // Column might already exist
}

$stmt = $pdo->prepare("SELECT * FROM furn_users WHERE id = ?");
$stmt->execute([$customerId]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    echo '<div style="margin:40px auto;max-width:600px;padding:30px;background:#fff3cd;border:1px solid #ffeeba;border-radius:8px;color:#856404;text-align:center;">'
        . '<h2>Profile Not Found</h2>'
        . '<p>Your customer profile could not be loaded. Please contact support or try logging in again.</p>'
        . '</div>';
    require_once dirname(__DIR__) . '/includes/footer.php';
    exit;
}
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
        .logo-section { display: flex; align-items: center; gap: 15px; }
        .logo-icon { font-size: 28px; color: #d4a574; }
        .brand-name { font-size: 20px; font-weight: 600; }
        .header-title { font-size: 18px; color: #d4a574; }
        .header-right { display: flex; align-items: center; gap: 20px; }
        .status-badge { background: #28a745; padding: 6px 15px; border-radius: 20px; font-size: 13px; display: flex; align-items: center; gap: 5px; }
        .notification-icon { position: relative; font-size: 20px; cursor: pointer; }
        .notification-badge { position: absolute; top: -8px; right: -8px; background: #dc3545; color: white; border-radius: 50%; width: 18px; height: 18px; font-size: 11px; display: flex; align-items: center; justify-content: center; }
        .user-profile { display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.1); padding: 8px 15px; border-radius: 25px; cursor: pointer; }
        .user-avatar { width: 35px; height: 35px; border-radius: 50%; background: #d4a574; display: flex; align-items: center; justify-content: center; font-weight: 600; color: #4a2c2a; }
        .user-role { background: #ffc107; color: #000; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
        .dashboard-container { display: flex; min-height: calc(100vh - 70px); }
        
        /* Main Content */
        .page-header { background: white; padding: 25px; border-radius: 15px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .page-title { font-size: 28px; font-weight: 700; color: #2c3e50; margin-bottom: 10px; }
        .page-description { color: #7f8c8d; font-size: 15px; }
        .profile-card { background: white; padding: 30px; border-radius: 15px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .profile-header { display: flex; align-items: center; gap: 30px; padding-bottom: 25px; border-bottom: 2px solid #e9ecef; margin-bottom: 25px; }
        .profile-avatar-large { width: 120px; height: 120px; border-radius: 50%; background: linear-gradient(135deg, #4a2c2a, #d4a574); display: flex; align-items: center; justify-content: center; font-size: 48px; font-weight: 700; color: white; box-shadow: 0 5px 15px rgba(0,0,0,0.2); position: relative; overflow: hidden; }
        .profile-avatar-img { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .avatar-upload-btn { position: absolute; bottom: 0; left: 0; right: 0; background: rgba(74, 44, 42, 0.9); padding: 8px; text-align: center; opacity: 0; transition: all 0.3s; cursor: pointer; color: white; font-size: 12px; }
        .profile-avatar-large:hover .avatar-upload-btn { opacity: 1; }
        .user-avatar img { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; }
        .profile-info h2 { color: #4a2c2a; margin-bottom: 5px; }
        .profile-info p { color: #7f8c8d; margin: 0; }
        .profile-badge { display: inline-block; background: #ffc107; color: #000; padding: 5px 15px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-top: 10px; }
        .section-title { font-size: 20px; font-weight: 600; color: #4a2c2a; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #d4a574; }
        .form-label { font-weight: 600; color: #2c3e50; margin-bottom: 8px; }
        .form-control, .form-select { border: 2px solid #e9ecef; border-radius: 8px; padding: 10px 15px; }
        .form-control:focus, .form-select:focus { border-color: #4a2c2a; box-shadow: 0 0 0 0.2rem rgba(74, 44, 42, 0.15); }
        .btn-save { background: linear-gradient(135deg, #4a2c2a, #d4a574); color: white; border: none; padding: 12px 30px; border-radius: 8px; font-weight: 600; }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(74, 44, 42, 0.3); color: white; }
        .btn-cancel { background: #6c757d; color: white; border: none; padding: 12px 30px; border-radius: 8px; font-weight: 600; }
        .info-row { display: flex; justify-content: space-between; padding: 15px 0; border-bottom: 1px solid #e9ecef; }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: #7f8c8d; font-weight: 500; }
        .info-value { color: #2c3e50; font-weight: 600; }
        .edit-mode { display: none; }
        .view-mode { display: block; }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" aria-label="Toggle Menu">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay"></div>

    <!-- Sidebar -->
    <?php include_once __DIR__ . '/../../includes/customer_sidebar.php'; ?>

    <!-- Top Header -->
    <?php 
    $pageTitle = 'My Profile';
    include_once __DIR__ . '/../../includes/customer_header.php'; 
    ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h2 style="margin-bottom: 10px; color: #2c3e50;">My Profile</h2>
            <p style="color: #7f8c8d;">View and manage your personal information</p>
        </div>

            <!-- Profile Card -->
            <div class="section-card">
                <div class="profile-header">
                    <div class="profile-avatar-large" id="avatarContainer">
                        <?php if (!empty($customer['profile_image'])): ?>
                            <img src="<?php echo BASE_URL; ?>/public/uploads/profile_images/<?php echo htmlspecialchars($customer['profile_image']); ?>" 
                                 alt="Profile Picture" 
                                 class="profile-avatar-img" 
                                 id="avatarPreview">
                        <?php else: ?>
                            <span id="avatarInitials"><?php echo strtoupper(substr($customer['first_name'] ?? 'C', 0, 1) . substr($customer['last_name'] ?? 'U', 0, 1)); ?></span>
                        <?php endif; ?>
                        <div class="avatar-upload-btn" onclick="document.getElementById('profileImageInput').click()">
                            <i class="fas fa-camera"></i> Change Photo
                        </div>
                    </div>
                    <input type="file" id="profileImageInput" accept="image/*" style="display: none;" onchange="uploadProfileImage()">
                    <div class="profile-info">
                        <h2><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></h2>
                        <p><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($customer['email']); ?></p>
                        <p><i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($customer['phone'] ?? 'Not provided'); ?></p>
                        <span class="profile-badge"><i class="fas fa-user me-1"></i><?php echo strtoupper($customer['role']); ?></span>
                    </div>
                    <div class="ms-auto">
                        <button class="btn btn-save" onclick="toggleEditMode()"><i class="fas fa-edit me-2"></i>Edit Profile</button>
                    </div>
                </div>

                <!-- View Mode -->
                <div id="viewMode" class="view-mode">
                    <h3 class="section-title"><i class="fas fa-info-circle me-2"></i>Personal Information</h3>
                    <div class="info-row">
                        <span class="info-label">Username</span>
                        <span class="info-value"><?php echo htmlspecialchars($customer['username']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">First Name</span>
                        <span class="info-value"><?php echo htmlspecialchars($customer['first_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Last Name</span>
                        <span class="info-value"><?php echo htmlspecialchars($customer['last_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email Address</span>
                        <span class="info-value"><?php echo htmlspecialchars($customer['email']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Phone Number</span>
                        <span class="info-value"><?php echo htmlspecialchars($customer['phone'] ?? 'Not provided'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Address</span>
                        <span class="info-value"><?php echo htmlspecialchars($customer['address'] ?? 'Not provided'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Member Since</span>
                        <span class="info-value"><?php echo date('F d, Y', strtotime($customer['created_at'])); ?></span>
                    </div>
                </div>

                <!-- Edit Mode -->
                <div id="editMode" class="edit-mode">
                    <h3 class="section-title"><i class="fas fa-edit me-2"></i>Edit Personal Information</h3>
                    <form id="profileForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($customer['first_name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($customer['last_name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($customer['email']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($customer['phone'] ?? ''); ?>" placeholder="+251-XXX-XXXXXX">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" name="address" rows="3" placeholder="Enter your full address"><?php echo htmlspecialchars($customer['address'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-save"><i class="fas fa-save me-2"></i>Save Changes</button>
                            <button type="button" class="btn btn-cancel" onclick="toggleEditMode()"><i class="fas fa-times me-2"></i>Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Change Password Card -->
            <div class="section-card">
                <h3 class="section-title"><i class="fas fa-lock me-2"></i>Change Password</h3>
                <form id="passwordForm">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" class="form-control" name="new_password" required minlength="6">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" name="confirm_password" required minlength="6">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-save"><i class="fas fa-key me-2"></i>Update Password</button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
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
                    const headerAvatar = document.querySelector('.customer-avatar img');
                    if (headerAvatar) {
                        headerAvatar.src = data.image_url;
                        headerAvatar.alt = "<?php echo htmlspecialchars($customer['first_name']); ?>";
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

        // Profile form submission
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
            fetch(BASE_URL + '/public/api/update_profile.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert('✓ Profile updated successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-save me-2"></i>Save Changes';
                }
            })
            .catch(error => {
                console.error('Update error:', error);
                alert('Failed to update profile. Please try again. Error: ' + error.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-save me-2"></i>Save Changes';
            });
        });

        // Password form submission
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';
            fetch(BASE_URL + '/public/api/change_password.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert('✓ Password updated successfully!');
                    this.reset();
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-lock me-2"></i>Change Password';
                } else {
                    alert('Error: ' + data.message);
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-lock me-2"></i>Change Password';
                }
            })
            .catch(error => {
                console.error('Password update error:', error);
                alert('Failed to update password. Please try again. Error: ' + error.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-lock me-2"></i>Change Password';
            });
        });
    </script>
    <script src="<?php echo BASE_URL; ?>/public/assets/js/dark-mode.js"></script>
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
