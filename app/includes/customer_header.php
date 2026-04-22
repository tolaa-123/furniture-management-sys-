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
        <div style="position: relative; cursor: pointer;" id="notifWrap">
            <div onclick="toggleNotifDropdown()" style="position:relative;padding:6px;">
                <i class="fas fa-bell" style="font-size: 20px; color: rgba(255,255,255,0.9);" id="bellIcon"></i>
                <span id="notifBadge" style="display:<?php echo $totalNotifications > 0 ? 'flex' : 'none'; ?>;position:absolute;top:0;right:0;background:#e74c3c;color:white;border-radius:50%;width:18px;height:18px;font-size:10px;align-items:center;justify-content:center;font-weight:700;border:2px solid #2c1810;"><?php echo $totalNotifications > 9 ? '9+' : $totalNotifications; ?></span>
            </div>

            <!-- Notification Dropdown -->
            <div id="notifDropdown" style="display:none;position:absolute;top:42px;right:-10px;background:white;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,0.18);width:360px;z-index:99999;overflow:hidden;">
                <!-- Header -->
                <div style="padding:14px 18px;background:linear-gradient(135deg,#2c1810,#4a2c2a);display:flex;justify-content:space-between;align-items:center;">
                    <div style="color:white;font-weight:700;font-size:14px;"><i class="fas fa-bell" style="margin-right:8px;color:#d4a574;"></i>Notifications <span id="notifHeaderCount" style="background:rgba(255,255,255,0.2);padding:2px 8px;border-radius:10px;font-size:11px;margin-left:4px;"><?php echo $totalNotifications; ?> unread</span></div>
                    <button onclick="markAllRead()" style="background:rgba(255,255,255,0.15);border:none;color:white;padding:4px 10px;border-radius:6px;font-size:11px;cursor:pointer;font-family:inherit;">Mark all read</button>
                </div>

                <!-- Filter tabs -->
                <div style="display:flex;border-bottom:1px solid #f0f0f0;background:#fafafa;">
                    <button onclick="filterNotifs('all')" id="tab_all" style="flex:1;padding:8px;border:none;background:none;font-size:12px;font-weight:600;cursor:pointer;color:#8B4513;border-bottom:2px solid #8B4513;font-family:inherit;">All</button>
                    <button onclick="filterNotifs('unread')" id="tab_unread" style="flex:1;padding:8px;border:none;background:none;font-size:12px;font-weight:600;cursor:pointer;color:#888;border-bottom:2px solid transparent;font-family:inherit;">Unread</button>
                    <button onclick="filterNotifs('order')" id="tab_order" style="flex:1;padding:8px;border:none;background:none;font-size:12px;font-weight:600;cursor:pointer;color:#888;border-bottom:2px solid transparent;font-family:inherit;">Orders</button>
                    <button onclick="filterNotifs('payment')" id="tab_payment" style="flex:1;padding:8px;border:none;background:none;font-size:12px;font-weight:600;cursor:pointer;color:#888;border-bottom:2px solid transparent;font-family:inherit;">Payments</button>
                </div>

                <!-- Notification list -->
                <div id="notifList" style="max-height:320px;overflow-y:auto;">
                    <?php if(count($notifications) > 0): ?>
                        <?php foreach($notifications as $notif):
                            $icons = ['order'=>['fa-shopping-cart','#3498db'],'payment'=>['fa-credit-card','#27ae60'],'production'=>['fa-hammer','#e67e22'],'material'=>['fa-boxes','#f39c12'],'rating'=>['fa-star','#f39c12'],'message'=>['fa-envelope','#9b59b6'],'system'=>['fa-cog','#7f8c8d']];
                            [$ico,$col] = $icons[$notif['type']] ?? ['fa-bell','#3498db'];
                            $href = !empty($notif['link']) ? BASE_URL.'/public'.$notif['link'] : '#';
                        ?>
                        <div class="notif-item" data-id="<?php echo $notif['id']; ?>" data-type="<?php echo $notif['type']; ?>" data-read="<?php echo $notif['is_read']; ?>"
                             style="display:flex;align-items:flex-start;gap:12px;padding:12px 16px;border-bottom:1px solid #f5f5f5;cursor:pointer;transition:background .15s;<?php echo !$notif['is_read'] ? 'background:#f0f8ff;' : ''; ?>"
                             onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='<?php echo !$notif['is_read'] ? '#f0f8ff' : 'white'; ?>'"
                             onclick="handleNotifClick(<?php echo $notif['id']; ?>, '<?php echo addslashes($href); ?>', this)">
                            <div style="width:36px;height:36px;border-radius:50%;background:<?php echo $col; ?>18;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px;">
                                <i class="fas <?php echo $ico; ?>" style="color:<?php echo $col; ?>;font-size:14px;"></i>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div style="font-size:13px;font-weight:<?php echo !$notif['is_read'] ? '700' : '500'; ?>;color:#2c3e50;line-height:1.3;"><?php echo htmlspecialchars($notif['title']); ?></div>
                                <?php if(!empty($notif['message'])): ?>
                                <div style="font-size:11px;color:#7f8c8d;margin-top:3px;line-height:1.4;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($notif['message']); ?></div>
                                <?php endif; ?>
                                <div style="font-size:10px;color:#aaa;margin-top:4px;"><i class="fas fa-clock" style="margin-right:3px;"></i><?php
                                    $diff = time() - strtotime($notif['created_at']);
                                    if($diff < 60) echo 'just now';
                                    elseif($diff < 3600) echo floor($diff/60).'m ago';
                                    elseif($diff < 86400) echo floor($diff/3600).'h ago';
                                    else echo date('M j', strtotime($notif['created_at']));
                                ?></div>
                            </div>
                            <?php if(!$notif['is_read']): ?>
                            <span style="width:8px;height:8px;background:#e74c3c;border-radius:50%;flex-shrink:0;margin-top:6px;"></span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                    <div style="padding:40px 20px;text-align:center;color:#aaa;">
                        <i class="fas fa-bell-slash" style="font-size:36px;margin-bottom:12px;display:block;color:#ddd;"></i>
                        <div style="font-size:13px;">No notifications yet</div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Footer -->
                <div style="padding:10px 16px;border-top:1px solid #f0f0f0;text-align:center;background:#fafafa;">
                    <a href="<?php echo BASE_URL; ?>/public/customer/notifications" style="color:#8B4513;font-size:12px;font-weight:600;text-decoration:none;"><i class="fas fa-list" style="margin-right:4px;"></i>View all notifications</a>
                </div>
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
@keyframes bellShake {
    0%,100%{transform:rotate(0)}15%{transform:rotate(15deg)}30%{transform:rotate(-12deg)}45%{transform:rotate(10deg)}60%{transform:rotate(-8deg)}75%{transform:rotate(5deg)}
}
#bellIcon:hover { animation: bellShake .5s ease; }
.notif-item[data-read="0"] { position: relative; }
</style>

<script>
const NOTIF_API = '<?php echo BASE_URL; ?>/public/api/notifications.php';
const CSRF = '<?php echo urlencode($csrf_token); ?>';
let currentFilter = 'all';

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    const wrap = document.getElementById('notifWrap');
    if (wrap && !wrap.contains(e.target)) {
        const dd = document.getElementById('notifDropdown');
        if (dd) dd.style.display = 'none';
    }
});

function toggleNotifDropdown() {
    const dd = document.getElementById('notifDropdown');
    if (!dd) return;
    const isOpen = dd.style.display !== 'none';
    dd.style.display = isOpen ? 'none' : 'block';
}

function filterNotifs(type) {
    currentFilter = type;
    // Update tab styles
    ['all','unread','order','payment'].forEach(t => {
        const btn = document.getElementById('tab_' + t);
        if (!btn) return;
        btn.style.color = t === type ? '#8B4513' : '#888';
        btn.style.borderBottom = t === type ? '2px solid #8B4513' : '2px solid transparent';
    });
    // Filter items
    document.querySelectorAll('.notif-item').forEach(item => {
        const itemType = item.dataset.type;
        const isRead = item.dataset.read === '1';
        let show = true;
        if (type === 'unread') show = !isRead;
        else if (type !== 'all') show = itemType === type;
        item.style.display = show ? 'flex' : 'none';
    });
}

function handleNotifClick(id, href, el) {
    // Mark as read visually immediately
    el.dataset.read = '1';
    el.style.background = 'white';
    el.style.fontWeight = '500';
    const dot = el.querySelector('span[style*="e74c3c"]');
    if (dot) dot.remove();
    const title = el.querySelector('div[style*="font-weight"]');
    if (title) title.style.fontWeight = '500';

    // Update badge count
    const unread = document.querySelectorAll('.notif-item[data-read="0"]').length;
    updateBadge(unread);

    // Call API
    fetch(NOTIF_API, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=mark_read&notification_id=' + id + '&csrf_token=' + CSRF
    }).catch(() => {});

    // Navigate
    if (href && href !== '#' && href !== 'javascript:void(0)') {
        setTimeout(() => { window.location.href = href; }, 150);
    }
}

function markAllRead() {
    fetch(NOTIF_API, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=mark_all_read&csrf_token=' + CSRF
    }).then(r => r.json()).then(d => {
        if (d.success) {
            document.querySelectorAll('.notif-item').forEach(item => {
                item.dataset.read = '1';
                item.style.background = 'white';
                const dot = item.querySelector('span[style*="e74c3c"]');
                if (dot) dot.remove();
                const title = item.querySelector('div[style*="font-weight: 700"]');
                if (title) title.style.fontWeight = '500';
            });
            updateBadge(0);
            const hdr = document.getElementById('notifHeaderCount');
            if (hdr) hdr.textContent = '0 unread';
        }
    }).catch(() => {});
}

function updateBadge(count) {
    const badge = document.getElementById('notifBadge');
    const hdr = document.getElementById('notifHeaderCount');
    if (badge) {
        if (count > 0) { badge.textContent = count > 9 ? '9+' : count; badge.style.display = 'flex'; }
        else badge.style.display = 'none';
    }
    if (hdr) hdr.textContent = count + ' unread';
}

// Toggle profile dropdown
function toggleProfileDropdown() {
    window.location.href = '<?php echo BASE_URL; ?>/public/customer/profile';
}

// Auto-refresh badge every 30 seconds
setInterval(function() {
    fetch(NOTIF_API + '?action=unread_count', {credentials: 'same-origin'})
    .then(r => r.json()).then(d => {
        if (d.count !== undefined) updateBadge(d.count);
    }).catch(() => {});
}, 30000);
</script>
