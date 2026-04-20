<?php
/**
 * Customer Header Component
 * Reusable top header for all customer pages
 * Includes: System status, Notifications, User Profile
 */

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    return; // Don't render if not logged in as customer
}

$customerId = $_SESSION['user_id'];
$customerName = $_SESSION['first_name'] ?? $_SESSION['user_name'] ?? 'Customer';
$pageTitle = $pageTitle ?? 'Customer Portal';

// Get database connection
require_once __DIR__ . '/../../config/db_config.php';

// Fetch user profile image
$profileImage = null;
try {
    $stmt = $pdo->prepare("SELECT profile_image FROM furn_users WHERE id = ?");
    $stmt->execute([$customerId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($userData && !empty($userData['profile_image'])) {
        $profileImage = BASE_URL . '/public/uploads/profile_images/' . $userData['profile_image'];
    }
} catch (PDOException $e) {
    // Ignore error
}

// Get notification counts from furn_notifications table
$notificationCounts = [
    'unread' => 0
];
$notifications = [];

try {
    // Ensure notifications table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS furn_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type VARCHAR(50) NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT,
        related_id INT DEFAULT NULL,
        link VARCHAR(255) DEFAULT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_is_read (is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Get unread notification count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM furn_notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$customerId]);
    $notificationCounts['unread'] = $stmt->fetchColumn();
    
    // Get recent notifications
    $stmt = $pdo->prepare("SELECT * FROM furn_notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$customerId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Customer notification error: " . $e->getMessage());
}

$totalNotifications = $notificationCounts['unread'];
$initials = strtoupper(substr($customerName, 0, 1));
// Ensure CSRF token exists
if (empty($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION[CSRF_TOKEN_NAME];
?>

<!-- Top Header -->
<div class="top-header" style="background: #2c1810; color: white; padding: 0 20px; height: 60px; display: flex; align-items: center; justify-content: space-between;">
    <div class="header-left" style="display: flex; align-items: center; gap: 15px;">
        <span style="font-size: 20px;">🔨</span>
        <span style="font-weight: 700; font-size: 16px; color: white;"><span style="color: #e67e22;">Smart</span>Workshop</span>
        <span style="color: rgba(255,255,255,0.4); margin: 0 5px;">|</span>
        <span style="font-size: 14px; color: rgba(255,255,255,0.85);">Furniture ERP &nbsp;<strong style="color:white;"><?php echo htmlspecialchars($pageTitle); ?></strong></span>
    </div>
    <div class="header-right" style="display: flex; align-items: center; gap: 15px;">
        <!-- System Status -->
        <div class="system-status" id="systemStatus" style="background: #27AE60; color: white; padding: 5px 12px; border-radius: 20px; font-size: 12px; display: flex; align-items: center; gap: 6px; cursor: pointer;" title="System Status">
            <span style="width: 8px; height: 8px; background: white; border-radius: 50%; display: inline-block; animation: pulse 2s infinite;"></span> 
            <span id="statusText">Operational</span>
        </div>
        
        <!-- Notification Bell -->
        <div style="position: relative; cursor: pointer;" onclick="toggleNotificationDropdown()" title="Notifications">
            <i class="fas fa-bell" style="font-size: 18px; color: rgba(255,255,255,0.85); transition: transform 0.3s;" id="bellIcon"></i>
            <?php if($totalNotifications > 0): ?>
            <span style="position: absolute; top: -6px; right: -6px; background: #e74c3c; color: white; border-radius: 50%; width: 18px; height: 18px; font-size: 10px; display: flex; align-items: center; justify-content: center; font-weight: 700;" id="notifBadge"><?php echo $totalNotifications > 9 ? '9+' : $totalNotifications; ?></span>
            <?php endif; ?>
            
            <!-- Notification Dropdown -->
            <div id="notifDropdown" style="display: none; position: absolute; top: 35px; right: 0; background: white; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.2); min-width: 300px; z-index: 9999;">
                <div style="padding: 15px; border-bottom: 1px solid #e9ecef;">
                    <strong style="color: #2c3e50; font-size: 15px;">Notifications</strong>
                </div>
                <div style="max-height: 300px; overflow-y: auto;">
                    <?php if(count($notifications) > 0): ?>
                        <?php foreach($notifications as $notif): ?>
                        <a href="<?php echo !empty($notif['link']) ? BASE_URL . '/public' . $notif['link'] : 'javascript:void(0)'; ?>" 
                           onclick="markNotificationRead(<?php echo $notif['id']; ?>, this, event)"
                           style="display: block; padding: 12px 15px; border-bottom: 1px solid #f0f0f0; text-decoration: none; color: #2c3e50; transition: background 0.3s; <?php echo $notif['is_read'] ? 'opacity: 0.7;' : 'background: #f0f8ff;'; ?>" 
                           onmouseover="this.style.background='#f8f9fa'" 
                           onmouseout="this.style.background='<?php echo $notif['is_read'] ? 'white' : '#f0f8ff'; ?>'">
                            <div style="display: flex; align-items: flex-start; gap: 10px;">
                                <?php 
                                $icon = 'fa-bell';
                                $color = '#3498DB';
                                switch($notif['type']) {
                                    case 'order': $icon = 'fa-shopping-cart'; $color = '#3498DB'; break;
                                    case 'payment': $icon = 'fa-credit-card'; $color = '#E74C3C'; break;
                                    case 'message': $icon = 'fa-envelope'; $color = '#F39C12'; break;
                                    case 'production': $icon = 'fa-hammer'; $color = '#8B4513'; break;
                                }
                                ?>
                                <i class="fas <?php echo $icon; ?>" style="color: <?php echo $color; ?>; font-size: 16px; margin-top: 2px;"></i>
                                <div style="flex: 1;">
                                    <div style="font-size: 13px; font-weight: <?php echo $notif['is_read'] ? '400' : '600'; ?>;"><?php echo htmlspecialchars($notif['title']); ?></div>
                                    <div style="font-size: 11px; color: #7f8c8d; margin-top: 2px;"><?php echo htmlspecialchars($notif['message']); ?></div>
                                    <div style="font-size: 10px; color: #95a5a6; margin-top: 4px;"><?php echo date('M d, H:i', strtotime($notif['created_at'])); ?></div>
                                </div>
                                <?php if(!$notif['is_read']): ?>
                                <span style="width: 8px; height: 8px; background: #e74c3c; border-radius: 50%; flex-shrink: 0;"></span>
                                <?php endif; ?>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <div style="padding: 30px 15px; text-align: center; color: #7f8c8d;">
                        <i class="fas fa-check-circle" style="font-size: 32px; color: #27AE60; margin-bottom: 10px;"></i>
                        <div style="font-size: 13px;">No new notifications</div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if($totalNotifications > 0): ?>
                <div style="padding: 12px 15px; border-top: 1px solid #e9ecef; text-align: center;">
                    <a href="#" onclick="markAllRead(); return false;" style="color: #3498DB; text-decoration: none; font-size: 13px; font-weight: 600;">Mark all as read</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- User Profile -->
        <div class="admin-profile" style="display: flex; align-items: center; gap: 10px; cursor: pointer;" onclick="toggleProfileDropdown()" title="My Profile">
            <div class="admin-avatar" style="background: #9B59B6; width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: white; font-size: 16px; overflow: hidden;">
                <?php if ($profileImage): ?>
                    <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="<?php echo htmlspecialchars($customerName); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <?php echo $initials; ?>
                <?php endif; ?>
            </div>
            <div>
                <div style="font-size: 13px; font-weight: 600; color: white;"><?php echo htmlspecialchars($customerName); ?></div>
                <div class="admin-role-badge" style="background: #9B59B6; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 700; color: white;">CUSTOMER</div>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

#bellIcon:hover {
    transform: scale(1.2);
}
</style>

<script>
// Toggle notification dropdown
function toggleNotificationDropdown() {
    const dropdown = document.getElementById('notifDropdown');
    dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
}

// Toggle profile dropdown (can add more features later)
function toggleProfileDropdown() {
    window.location.href = '<?php echo BASE_URL; ?>/public/customer/profile';
}

// Mark all notifications as read
function markAllRead() {
    fetch('<?php echo BASE_URL; ?>/public/api/mark_notifications_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'csrf_token=<?php echo urlencode($csrf_token); ?>'
    }).finally(() => {
        const badge = document.getElementById('notifBadge');
        if (badge) badge.style.display = 'none';
        document.getElementById('notifDropdown').style.display = 'none';
        location.reload();
    });
}

// Mark single notification as read then navigate
function markNotificationRead(notificationId, el, event) {
    if (event) event.preventDefault();
    const href = el ? el.getAttribute('href') : null;
    fetch('<?php echo BASE_URL; ?>/public/api/mark_notification_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'notification_id=' + notificationId + '&csrf_token=<?php echo urlencode($csrf_token); ?>'
    }).finally(function() {
        if (href && href !== '#' && href !== 'javascript:void(0)') window.location.href = href;
    });
}

// Auto-refresh badge every 30 seconds
setInterval(function() {
    fetch('<?php echo BASE_URL; ?>/public/api/notifications.php?action=unread_count', { credentials: 'same-origin' })
    .then(r => r.json()).then(data => {
        if (data.count !== undefined) {
            const b = document.getElementById('notifBadge');
            if (b) { if (data.count > 0) { b.textContent = data.count > 9 ? '9+' : data.count; b.style.display = 'flex'; } else b.style.display = 'none'; }
        }
    }).catch(() => {});
}, 30000);
</script>
