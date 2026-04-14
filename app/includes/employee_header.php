<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') { return; }
$employeeId   = $_SESSION['user_id'];
$employeeName = $_SESSION['first_name'] ?? $_SESSION['user_name'] ?? 'Employee';
$pageTitle    = $pageTitle ?? 'Employee Portal';
require_once __DIR__ . '/../../config/db_config.php';
require_once __DIR__ . '/notification_helper.php';
$profileImage = null;
try {
    $s = $pdo->prepare("SELECT profile_image FROM furn_users WHERE id=?");
    $s->execute([$employeeId]);
    $ud = $s->fetch(PDO::FETCH_ASSOC);
    if ($ud && !empty($ud['profile_image']))
        $profileImage = BASE_URL . '/public/uploads/profile_images/' . $ud['profile_image'];
} catch (PDOException $e) {}
$dbNotifications = []; $unreadCount = 0;
try {
    $s = $pdo->prepare("SELECT COUNT(*) FROM furn_notifications WHERE user_id=? AND is_read=0");
    $s->execute([$employeeId]); $unreadCount = (int)$s->fetchColumn();
    $s = $pdo->prepare("SELECT * FROM furn_notifications WHERE user_id=? ORDER BY is_read ASC, created_at DESC LIMIT 10");
    $s->execute([$employeeId]); $dbNotifications = $s->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
$kpi = ['pending_tasks'=>0,'in_progress_tasks'=>0,'unread_messages'=>0,'pending_requests'=>0];
try {
    $s = $pdo->prepare("SELECT COUNT(*) FROM furn_production_tasks WHERE employee_id=? AND status='pending'");
    $s->execute([$employeeId]); $kpi['pending_tasks'] = (int)$s->fetchColumn();
    $s = $pdo->prepare("SELECT COUNT(*) FROM furn_production_tasks WHERE employee_id=? AND status='in_progress'");
    $s->execute([$employeeId]); $kpi['in_progress_tasks'] = (int)$s->fetchColumn();
    try { $s=$pdo->prepare("SELECT COUNT(*) FROM furn_messages WHERE receiver_id=? AND is_read=0"); $s->execute([$employeeId]); $kpi['unread_messages']=(int)$s->fetchColumn(); } catch(PDOException $e2){}
    try { $s=$pdo->prepare("SELECT COUNT(*) FROM furn_material_requests WHERE employee_id=? AND status='pending'"); $s->execute([$employeeId]); $kpi['pending_requests']=(int)$s->fetchColumn(); } catch(PDOException $e2){}
} catch (PDOException $e) {}
$csrf_token = $_SESSION[CSRF_TOKEN_NAME] ?? $_SESSION['csrf_token'] ?? '';
$badgeCount = $unreadCount;
$initials   = strtoupper(substr($employeeName, 0, 1));
$kpiTotal   = array_sum($kpi);
?>
<div class="top-header" style="background:#2c1810;color:white;padding:0 20px;height:60px;display:flex;align-items:center;justify-content:space-between;">
    <div style="display:flex;align-items:center;gap:15px;">
        <span style="font-size:20px;">🔨</span>
        <span style="font-weight:700;font-size:16px;color:white;"><span style="color:#e67e22;">Smart</span>Workshop</span>
        <span style="color:rgba(255,255,255,0.4);margin:0 5px;">|</span>
        <span style="font-size:14px;color:rgba(255,255,255,0.85);">Furniture ERP &nbsp;<strong style="color:white;"><?php echo htmlspecialchars($pageTitle); ?></strong></span>
    </div>
    <div style="display:flex;align-items:center;gap:15px;">
        <div style="background:#27AE60;color:white;padding:5px 12px;border-radius:20px;font-size:12px;display:flex;align-items:center;gap:6px;">
            <span style="width:8px;height:8px;background:white;border-radius:50%;display:inline-block;animation:pulse 2s infinite;"></span>Operational
        </div>
        <!-- Notification Bell -->
        <div style="position:relative;cursor:pointer;" onclick="toggleNotificationDropdown()" title="Notifications">
            <i class="fas fa-bell" style="font-size:18px;color:rgba(255,255,255,0.85);" id="bellIcon"></i>
            <?php if($badgeCount > 0): ?>
            <span id="notifBadge" style="position:absolute;top:-6px;right:-6px;background:#e74c3c;color:white;border-radius:50%;width:18px;height:18px;font-size:10px;display:flex;align-items:center;justify-content:center;font-weight:700;"><?php echo $badgeCount > 9 ? '9+' : $badgeCount; ?></span>
            <?php else: ?>
            <span id="notifBadge" style="display:none;position:absolute;top:-6px;right:-6px;background:#e74c3c;color:white;border-radius:50%;width:18px;height:18px;font-size:10px;align-items:center;justify-content:center;font-weight:700;"></span>
            <?php endif; ?>
            <div id="notifDropdown" style="display:none;position:absolute;top:35px;right:0;background:white;border-radius:10px;box-shadow:0 4px 24px rgba(0,0,0,0.18);min-width:340px;max-width:380px;z-index:9999;">
                <div style="padding:14px 16px;border-bottom:1px solid #e9ecef;display:flex;justify-content:space-between;align-items:center;">
                    <strong style="color:#2c3e50;font-size:15px;">Notifications <?php if($badgeCount>0): ?><span style="background:#e74c3c;color:white;border-radius:10px;padding:1px 7px;font-size:11px;margin-left:6px;"><?php echo $badgeCount; ?></span><?php endif; ?></strong>
                    <a href="<?php echo BASE_URL; ?>/public/employee/notifications" style="font-size:12px;color:#27ae60;text-decoration:none;">View All</a>
                </div>
                <?php if($kpiTotal > 0): ?>
                <div style="padding:8px 16px;background:#f0fff4;border-bottom:1px solid #d4edda;">
                    <div style="font-size:10px;font-weight:700;color:#27ae60;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;">Quick Alerts</div>
                    <div style="display:flex;flex-wrap:wrap;gap:6px;">
                        <?php if($kpi['pending_tasks']>0): ?><a href="<?php echo BASE_URL; ?>/public/employee/tasks" style="background:#fff3cd;color:#856404;padding:3px 9px;border-radius:10px;font-size:11px;font-weight:600;text-decoration:none;"><i class="fas fa-tasks"></i> <?php echo $kpi['pending_tasks']; ?> Pending Tasks</a><?php endif; ?>
                        <?php if($kpi['in_progress_tasks']>0): ?><a href="<?php echo BASE_URL; ?>/public/employee/tasks" style="background:#e8f4fd;color:#2980b9;padding:3px 9px;border-radius:10px;font-size:11px;font-weight:600;text-decoration:none;"><i class="fas fa-cog"></i> <?php echo $kpi['in_progress_tasks']; ?> In Progress</a><?php endif; ?>
                        <?php if($kpi['unread_messages']>0): ?><a href="<?php echo BASE_URL; ?>/public/employee/messages" style="background:#fdecea;color:#e74c3c;padding:3px 9px;border-radius:10px;font-size:11px;font-weight:600;text-decoration:none;"><i class="fas fa-envelope"></i> <?php echo $kpi['unread_messages']; ?> Messages</a><?php endif; ?>
                        <?php if($kpi['pending_requests']>0): ?><a href="<?php echo BASE_URL; ?>/public/employee/materials" style="background:#fff8f0;color:#e67e22;padding:3px 9px;border-radius:10px;font-size:11px;font-weight:600;text-decoration:none;"><i class="fas fa-boxes"></i> <?php echo $kpi['pending_requests']; ?> Material Requests</a><?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <div style="max-height:280px;overflow-y:auto;">
                    <?php if(empty($dbNotifications)): ?>
                    <div style="padding:30px 15px;text-align:center;color:#7f8c8d;">
                        <i class="fas fa-check-circle" style="font-size:28px;color:#27AE60;display:block;margin-bottom:8px;"></i>
                        <div style="font-size:13px;">No notifications yet</div>
                    </div>
                    <?php else: foreach($dbNotifications as $n):
                        [$icon,$color] = notifIcon($n['type']);
                        $bg = $n['is_read'] ? 'white' : '#f0fff4';
                        $fw = $n['is_read'] ? '400' : '600';
                        $link = !empty($n['link']) ? BASE_URL.'/public'.$n['link'] : '#';
                        $link .= (strpos($link,'?')===false?'?':'&').'notif='.$n['id'];
                    ?>
                    <a href="<?php echo htmlspecialchars($link); ?>"
                       onclick="markNotifRead(<?php echo (int)$n['id']; ?>, this, event)"
                       style="display:block;padding:11px 14px;border-bottom:1px solid #f0f0f0;text-decoration:none;color:#2c3e50;background:<?php echo $bg; ?>;transition:background .2s;"
                       onmouseover="this.style.background='#f8f9fa'" onmouseout="this.style.background='<?php echo $bg; ?>'">
                        <div style="display:flex;align-items:flex-start;gap:10px;">
                            <div style="width:32px;height:32px;border-radius:50%;background:<?php echo $color; ?>22;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas <?php echo $icon; ?>" style="color:<?php echo $color; ?>;font-size:13px;"></i>
                            </div>
                            <div style="flex:1;min-width:0;">
                                <div style="font-size:13px;font-weight:<?php echo $fw; ?>;color:#2c3e50;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($n['title']); ?></div>
                                <?php if(!empty($n['message'])): ?><div style="font-size:11px;color:#7f8c8d;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($n['message']); ?></div><?php endif; ?>
                                <div style="font-size:10px;color:#aaa;margin-top:3px;"><?php echo timeAgo($n['created_at']); ?></div>
                            </div>
                            <?php if(!$n['is_read']): ?><span style="width:8px;height:8px;background:#e74c3c;border-radius:50%;flex-shrink:0;margin-top:4px;"></span><?php endif; ?>
                        </div>
                    </a>
                    <?php endforeach; endif; ?>
                </div>
                <div style="padding:10px 14px;border-top:1px solid #e9ecef;display:flex;justify-content:space-between;align-items:center;">
                    <?php if($badgeCount > 0): ?>
                    <a href="#" onclick="markAllRead();return false;" style="color:#e74c3c;text-decoration:none;font-size:12px;font-weight:600;"><i class="fas fa-check-double"></i> Mark All Read</a>
                    <?php else: ?><span></span><?php endif; ?>
                    <a href="<?php echo BASE_URL; ?>/public/employee/notifications" style="color:#27ae60;text-decoration:none;font-size:12px;font-weight:600;">View All →</a>
                </div>
            </div>
        </div>
        <!-- Profile -->
        <div style="display:flex;align-items:center;gap:10px;cursor:pointer;" onclick="window.location.href='<?php echo BASE_URL; ?>/public/employee/profile';">
            <div style="background:#27AE60;width:35px;height:35px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;color:white;font-size:16px;overflow:hidden;">
                <?php if($profileImage): ?><img src="<?php echo htmlspecialchars($profileImage); ?>" style="width:100%;height:100%;object-fit:cover;"><?php else: echo $initials; endif; ?>
            </div>
            <div>
                <div style="font-size:13px;font-weight:600;color:white;"><?php echo htmlspecialchars($employeeName); ?></div>
                <div style="background:#27AE60;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;color:white;">EMPLOYEE</div>
            </div>
        </div>
    </div>
</div>
<style>@keyframes pulse{0%,100%{opacity:1}50%{opacity:.5}} #bellIcon:hover{transform:scale(1.2)}</style>
<script>
function toggleNotificationDropdown(){const d=document.getElementById('notifDropdown');d.style.display=d.style.display==='none'?'block':'none';}
function markNotifRead(id,el,event){
    if(event)event.preventDefault();
    const href=el?el.getAttribute('href'):null;
    fetch('<?php echo BASE_URL; ?>/public/api/mark_notification_read.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'notification_id='+id+'&csrf_token=<?php echo urlencode($csrf_token); ?>'}).finally(function(){if(href&&href!=='#')window.location.href=href;});
    const b=document.getElementById('notifBadge');if(b){const c=parseInt(b.textContent)||0;if(c<=1)b.style.display='none';else b.textContent=c-1;}
}
function markAllRead(){
    fetch('<?php echo BASE_URL; ?>/public/api/mark_notifications_read.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'csrf_token=<?php echo urlencode($csrf_token); ?>'}).finally(()=>{const b=document.getElementById('notifBadge');if(b)b.style.display='none';document.getElementById('notifDropdown').style.display='none';location.reload();});
}
document.addEventListener('click',function(e){const d=document.getElementById('notifDropdown');if(d&&!e.target.closest('[onclick*="toggleNotificationDropdown"]')&&!e.target.closest('#notifDropdown'))d.style.display='none';});
setInterval(function(){fetch('<?php echo BASE_URL; ?>/public/api/notifications.php?action=unread_count',{credentials:'same-origin'}).then(r=>r.json()).then(data=>{if(data.count!==undefined){const b=document.getElementById('notifBadge');if(b){if(data.count>0){b.textContent=data.count>9?'9+':data.count;b.style.display='flex';}else b.style.display='none';}}}).catch(()=>{});},30000);
</script>
