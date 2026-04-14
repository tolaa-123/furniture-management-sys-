<?php
/**
 * Employee Header Component
 * Reusable top header for all employee/production pages
 * Includes: System status, Notifications, User Profile
 */

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
    return; // Don't render if not logged in as employee
}

$employeeId = $_SESSION['user_id'];
$employeeName = $_SESSION['first_name'] ?? $_SESSION['user_name'] ?? 'Employee';
$initials = strtoupper(substr($employeeName, 0, 1));
$pageTitle = $pageTitle ?? 'Employee Portal';

// Get database connection
require_once __DIR__ . '/../../config/db_config.php';

// Fetch user profile image
$profileImage = null;
try {
    $stmt = $pdo->prepare("SELECT profile_image FROM furn_users WHERE id = ?");
    $stmt->execute([$employeeId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($userData && !empty($userData['profile_image'])) {
        $profileImage = BASE_URL . '/public/uploads/profile_images/' . $userData['profile_image'];
    }
} catch (PDOException $e) {
    // Ignore error
}

// Get notification counts
$notificationCounts = [
    'pending_tasks' => 0,
    'in_progress_tasks' => 0,
    'unread_messages' => 0
];

try {
    // Pending tasks count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM furn_production_tasks WHERE employee_id = ? AND status = 'pending'");
    $stmt->execute([$employeeId]);
    $notificationCounts['pending_tasks'] = $stmt->fetchColumn();

    // In-progress tasks count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM furn_production_tasks WHERE employee_id = ? AND status = 'in_progress'");
    $stmt->execute([$employeeId]);
    $notificationCounts['in_progress_tasks'] = $stmt->fetchColumn();

    // Unread messages
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM furn_messages WHERE receiver_id = ? AND is_read = 0");
        $stmt->execute([$employeeId]);
        $notificationCounts['unread_messages'] = $stmt->fetchColumn();
    } catch (PDOException $e) {
        // Messages table may not exist
    }
} catch (PDOException $e) {
    error_log("Employee notification counts error: " . $e->getMessage());
}

$totalNotifications = array_sum($notificationCounts);
$initials = strtoupper(substr($employeeName, 0, 1));
$csrf_token = $_SESSION[CSRF_TOKEN_NAME] ?? '';

// Also fetch furn_notifications unread count for mark-all-read
$notifTableCount = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM furn_notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$employeeId]);
    $notifTableCount = (int)$stmt->fetchColumn();
} catch (PDOException $e) {}

$totalNotifications = array_sum($notificationCounts);
$initials = strtoupper(substr($employeeName, 0, 1));
$csrf_token = $_SESSION[CSRF_TOKEN_NAME] ?? '';

// Fetch furn_notifications for the bell badge (these are the ones that can be marked as read)
$empNotifications = [];
$empUnreadCount = 0;
try {
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

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM furn_notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$employeeId]);
    $empUnreadCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT * FROM furn_notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$employeeId]);
    $empNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Add unread messages to badge
$unreadMessages = $notificationCounts['unread_messages'];
$bellBadgeCount = $empUnreadCount + $unreadMessages;
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
            <?php if($bellBadgeCount > 0): ?>
            <span style="position: absolute; top: -6px; right: -6px; background: #e74c3c; color: white; border-radius: 50%; width: 18px; height: 18px; font-size: 10px; display: flex; align-items: center; justify-content: center; font-weight: 700;" id="notifBadge"><?php echo $bellBadgeCount > 9 ? '9+' : $bellBadgeCount; ?></span>
            <?php endif; ?>

            <!-- Notification Dropdown -->
            <div id="notifDropdown" style="display: none; position: absolute; top: 35px; right: 0; background: white; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.2); min-width: 300px; max-width: 320px; z-index: 9999; overflow: hidden;">
                <div style="padding: 15px; border-bottom: 1px solid #e9ecef; display:flex; justify-content:space-between; align-items:center;">
                    <strong style="color: #2c3e50; font-size: 15px;">Notifications</strong>
                    <?php if($bellBadgeCount > 0): ?>
                    <span style="background:#e74c3c;color:white;border-radius:10px;padding:2px 8px;font-size:11px;font-weight:700;"><?php echo $bellBadgeCount; ?> unread</span>
                    <?php endif; ?>
                </div>
                <div style="max-height: 300px; overflow-y: auto;">
                    <?php if($notificationCounts['pending_tasks'] > 0 || $notificationCounts['in_progress_tasks'] > 0): ?>
                    <!-- Task summary — informational only, not markable -->
                    <div style="padding: 10px 15px; background:#fffbf0; border-bottom:1px solid #f0f0f0;">
                        <div style="font-size:11px;color:#7f8c8d;font-weight:600;text-transform:uppercase;margin-bottom:6px;">Active Tasks</div>
                        <?php if($notificationCounts['pending_tasks'] > 0): ?>
                        <a href="<?php echo BASE_URL; ?>/public/employee/tasks" style="display:flex;justify-content:space-between;align-items:center;text-decoration:none;color:#2c3e50;padding:4px 0;">
                            <span style="font-size:13px;"><i class="fas fa-tasks" style="color:#F39C12;margin-right:8px;"></i>Pending Tasks</span>
                            <span style="background:#F39C12;color:white;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;"><?php echo $notificationCounts['pending_tasks']; ?></span>
                        </a>
                        <?php endif; ?>
                        <?php if($notificationCounts['in_progress_tasks'] > 0): ?>
                        <a href="<?php echo BASE_URL; ?>/public/employee/tasks" style="display:flex;justify-content:space-between;align-items:center;text-decoration:none;color:#2c3e50;padding:4px 0;">
                            <span style="font-size:13px;"><i class="fas fa-cog" style="color:#3498DB;margin-right:8px;"></i>In Progress</span>
                            <span style="background:#3498DB;color:white;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;"><?php echo $notificationCounts['in_progress_tasks']; ?></span>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if(count($empNotifications) > 0): ?>
                        <?php foreach($empNotifications as $notif):
                            $icon = 'fa-bell'; $color = '#3498DB';
                            switch($notif['type']) {
                                case 'order': $icon='fa-shopping-cart'; $color='#3498DB'; break;
                                case 'payment': $icon='fa-credit-card'; $color='#E74C3C'; break;
                                case 'message': $icon='fa-envelope'; $color='#F39C12'; break;
                                case 'production': $icon='fa-hammer'; $color='#8B4513'; break;
                            }
                            $notifLink = BASE_URL . '/public/employee/tasks';
                            if (!empty($notif['link'])) $notifLink = BASE_URL . '/public' . $notif['link'];
                        ?>
                        <a href="<?php echo htmlspecialchars($notifLink); ?>"
                           onclick="markSingleRead(<?php echo (int)$notif['id']; ?>, this, event)"
                           style="display:block;padding:12px 15px;border-bottom:1px solid #f0f0f0;text-decoration:none;color:#2c3e50;<?php echo $notif['is_read'] ? 'opacity:.7;' : 'background:#f0f8ff;'; ?>"
                           onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='<?php echo $notif['is_read'] ? 'white' : '#f0f8ff'; ?>'">
                            <div style="display:flex;align-items:flex-start;gap:10px;">
                                <i class="fas <?php echo $icon; ?>" style="color:<?php echo $color; ?>;font-size:15px;margin-top:2px;"></i>
                                <div style="flex:1;">
                                    <div style="font-size:13px;font-weight:<?php echo $notif['is_read'] ? '400' : '600'; ?>;"><?php echo htmlspecialchars($notif['title']); ?></div>
                                    <div style="font-size:11px;color:#7f8c8d;"><?php echo htmlspecialchars($notif['message'] ?? ''); ?></div>
                                    <div style="font-size:10px;color:#95a5a6;margin-top:3px;"><?php echo date('M d, H:i', strtotime($notif['created_at'])); ?></div>
                                </div>
                                <?php if(!$notif['is_read']): ?>
                                <span style="width:8px;height:8px;background:#e74c3c;border-radius:50%;flex-shrink:0;margin-top:4px;"></span>
                                <?php endif; ?>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    <?php elseif($notificationCounts['unread_messages'] > 0): ?>
                    <a href="<?php echo BASE_URL; ?>/public/employee/messages" style="display:block;padding:12px 15px;border-bottom:1px solid #f0f0f0;text-decoration:none;color:#2c3e50;background:#f0f8ff;">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <i class="fas fa-envelope" style="color:#E74C3C;font-size:15px;"></i>
                            <div>
                                <div style="font-size:13px;font-weight:600;">Unread Messages</div>
                                <div style="font-size:11px;color:#7f8c8d;"><?php echo $notificationCounts['unread_messages']; ?> new message(s)</div>
                            </div>
                        </div>
                    </a>
                    <?php elseif($bellBadgeCount === 0 && $totalNotifications === 0): ?>
                    <div style="padding:30px 15px;text-align:center;color:#7f8c8d;">
                        <i class="fas fa-check-circle" style="font-size:32px;color:#27AE60;margin-bottom:10px;display:block;"></i>
                        <div style="font-size:13px;">All caught up!</div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if($bellBadgeCount > 0): ?>
                <div style="padding:12px 15px;border-top:1px solid #e9ecef;display:flex;justify-content:space-between;align-items:center;">
                    <a href="#" onclick="markAllRead(); return false;" style="color:#e74c3c;text-decoration:none;font-size:13px;font-weight:600;">
                        <i class="fas fa-check-double me-1"></i>Mark all as read
                    </a>
                    <a href="<?php echo BASE_URL; ?>/public/employee/notifications" style="color:#3498DB;text-decoration:none;font-size:13px;font-weight:600;">View All →</a>
                </div>
                <?php else: ?>
                <div style="padding:10px 15px;border-top:1px solid #e9ecef;text-align:center;">
                    <a href="<?php echo BASE_URL; ?>/public/employee/notifications" style="color:#3498DB;text-decoration:none;font-size:13px;">View All Notifications</a>
                </div>
                <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- User Profile -->
        <div class="admin-profile" style="display: flex; align-items: center; gap: 10px; cursor: pointer;" onclick="window.location.href='<?php echo BASE_URL; ?>/public/employee/profile';" title="My Profile">
            <div class="admin-avatar" style="background: #27AE60; width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: white; font-size: 16px; overflow: hidden;">
                <?php if ($profileImage): ?>
                    <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="<?php echo htmlspecialchars($employeeName); ?>" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <?php echo $initials; ?>
                <?php endif; ?>
            </div>
            <div>
                <div style="font-size: 13px; font-weight: 600; color: white;"><?php echo htmlspecialchars($employeeName); ?></div>
                <div class="admin-role-badge" style="background: #27AE60; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: 700; color: white;">EMPLOYEE</div>
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
function toggleNotificationDropdown() {
    const dropdown = document.getElementById('notifDropdown');
    if (dropdown) dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
}

function markSingleRead(id, el, event) {
    if (event) event.preventDefault();
    const href = el ? el.getAttribute('href') : null;
    const csrfToken = '<?php echo addslashes($csrf_token); ?>';
    fetch('<?php echo BASE_URL; ?>/public/api/mark_notification_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'notification_id=' + id + '&csrf_token=' + encodeURIComponent(csrfToken)
    }).finally(function() {
        if (href && href !== '#' && href !== 'javascript:void(0)') window.location.href = href;
    });
    const badge = document.getElementById('notifBadge');
    if (badge) { const c = parseInt(badge.textContent) || 0; if (c <= 1) badge.style.display = 'none'; else badge.textContent = c - 1; }
}

function markAllRead() {
    const csrfToken = '<?php echo addslashes($csrf_token); ?>';
    fetch('<?php echo BASE_URL; ?>/public/api/mark_employee_notifications_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'csrf_token=' + encodeURIComponent(csrfToken)
    }).finally(function() {
        const badge = document.getElementById('notifBadge');
        if (badge) badge.style.display = 'none';
        const dropdown = document.getElementById('notifDropdown');
        if (dropdown) dropdown.style.display = 'none';
        location.reload();
    });
}

document.addEventListener('click', function(e) {
    const dropdown = document.getElementById('notifDropdown');
    if (dropdown && !e.target.closest('[onclick*="toggleNotificationDropdown"]') && !e.target.closest('#notifDropdown')) {
        dropdown.style.display = 'none';
    }
});
</script>
