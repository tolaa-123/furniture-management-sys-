<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    header('Location: ' . BASE_URL . '/public/login'); exit();
}
require_once __DIR__ . '/../../../config/db_config.php';
require_once __DIR__ . '/../../includes/notification_helper.php';

$userId = $_SESSION['user_id'];
$pageTitle = 'Notifications';
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20; $offset = ($page-1)*$perPage;
$csrf_token = $_SESSION[CSRF_TOKEN_NAME] ?? $_SESSION['csrf_token'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'mark_all') {
    if (hash_equals($csrf_token, $_POST['csrf_token']??''))
        $pdo->prepare("UPDATE furn_notifications SET is_read=1,read_at=NOW() WHERE user_id=? AND is_read=0")->execute([$userId]);
    header('Location: '.BASE_URL.'/public/customer/notifications'); exit();
}

$where=['user_id=?']; $params=[$userId];
if($filter==='unread'){$where[]='is_read=0';} if($filter==='read'){$where[]='is_read=1';}
if($search!==''){$where[]='(title LIKE ? OR message LIKE ?)';$params[]="%$search%";$params[]="%$search%";}
$ws=implode(' AND ',$where);
$cS=$pdo->prepare("SELECT COUNT(*) FROM furn_notifications WHERE $ws");$cS->execute($params);$total=(int)$cS->fetchColumn();
$pages=max(1,ceil($total/$perPage));
$nS=$pdo->prepare("SELECT * FROM furn_notifications WHERE $ws ORDER BY is_read ASC,created_at DESC LIMIT $perPage OFFSET $offset");
$nS->execute($params);$notifications=$nS->fetchAll(PDO::FETCH_ASSOC);
$uS=$pdo->prepare("SELECT COUNT(*) FROM furn_notifications WHERE user_id=? AND is_read=0");$uS->execute([$userId]);$unread=(int)$uS->fetchColumn();
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Notifications - Customer</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/css/admin-responsive.css">
<style>
.notif-item{display:flex;align-items:flex-start;gap:14px;padding:16px 20px;border-bottom:1px solid #f0f0f0;text-decoration:none;color:#2c3e50;transition:background .2s;}
.notif-item:hover{background:#f8f9fa;}.notif-item.unread{background:#f0f8ff;border-left:3px solid #9b59b6;}.notif-item.unread .notif-title{font-weight:700;}
.notif-icon{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.filter-btn{padding:7px 16px;border-radius:20px;border:1.5px solid #ddd;background:white;cursor:pointer;font-size:13px;font-weight:600;color:#555;text-decoration:none;}
.filter-btn.active{background:#9b59b6;color:white;border-color:#9b59b6;}
</style>
</head><body>
<button class="mobile-menu-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>
<div class="sidebar-overlay"></div>
<?php include_once __DIR__ . '/../../includes/customer_sidebar.php'; ?>
<?php include_once __DIR__ . '/../../includes/customer_header.php'; ?>
<div class="main-content" style="padding:30px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
        <div><h2 style="margin:0;color:#2c3e50;"><i class="fas fa-bell me-2"></i>My Notifications</h2>
        <p style="margin:4px 0 0;color:#7f8c8d;font-size:13px;"><?php echo $total;?> total &nbsp;|&nbsp; <span style="color:#e74c3c;"><?php echo $unread;?> unread</span></p></div>
        <?php if($unread>0):?><form method="POST"><input type="hidden" name="action" value="mark_all"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token);?>"><button type="submit" style="padding:9px 18px;background:#e74c3c;color:white;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;"><i class="fas fa-check-double me-1"></i>Mark All Read</button></form><?php endif;?>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;align-items:center;">
        <a href="?filter=all" class="filter-btn <?php echo $filter==='all'?'active':'';?>">All (<?php echo $total;?>)</a>
        <a href="?filter=unread" class="filter-btn <?php echo $filter==='unread'?'active':'';?>">Unread (<?php echo $unread;?>)</a>
        <a href="?filter=read" class="filter-btn <?php echo $filter==='read'?'active':'';?>">Read</a>
        <form method="GET" style="display:flex;gap:8px;margin-left:auto;"><input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter);?>"><input type="text" name="search" value="<?php echo htmlspecialchars($search);?>" placeholder="Search..." style="padding:7px 12px;border:1.5px solid #ddd;border-radius:8px;font-size:13px;width:200px;"><button type="submit" style="padding:7px 14px;background:#2c3e50;color:white;border:none;border-radius:8px;cursor:pointer;"><i class="fas fa-search"></i></button></form>
    </div>
    <div class="section-card" style="padding:0;overflow:hidden;">
        <?php if(empty($notifications)):?><div style="text-align:center;padding:60px 20px;color:#7f8c8d;"><i class="fas fa-bell-slash" style="font-size:48px;opacity:.3;display:block;margin-bottom:16px;"></i><div style="font-size:16px;font-weight:600;">No notifications yet</div></div>
        <?php else: foreach($notifications as $n): [$icon,$color]=notifIcon($n['type']); $link=!empty($n['link'])?BASE_URL.'/public'.$n['link']:'#'; $link.=(strpos($link,'?')===false?'?':'&').'notif='.$n['id']; if($n['related_id'])$link.='&focus='.$n['type'].'&id='.$n['related_id']; ?>
        <a href="<?php echo htmlspecialchars($link);?>" onclick="markRead(<?php echo (int)$n['id'];?>,this)" class="notif-item <?php echo $n['is_read']?'':'unread';?>">
            <div class="notif-icon" style="background:<?php echo $color;?>22;"><i class="fas <?php echo $icon;?>" style="color:<?php echo $color;?>;font-size:16px;"></i></div>
            <div style="flex:1;">
                <div class="notif-title" style="font-size:14px;color:#2c3e50;margin-bottom:3px;"><?php echo htmlspecialchars($n['title']);?></div>
                <?php if(!empty($n['message'])):?><div style="font-size:12px;color:#7f8c8d;margin-bottom:4px;"><?php echo htmlspecialchars($n['message']);?></div><?php endif;?>
                <div style="font-size:11px;color:#aaa;"><?php echo timeAgo($n['created_at']);?> &nbsp;·&nbsp; <span style="background:<?php echo $color;?>22;color:<?php echo $color;?>;padding:1px 7px;border-radius:8px;font-size:10px;font-weight:700;"><?php echo strtoupper($n['type']);?></span></div>
            </div>
            <?php if(!$n['is_read']):?><span style="width:10px;height:10px;background:#e74c3c;border-radius:50%;flex-shrink:0;margin-top:4px;"></span><?php endif;?>
        </a>
        <?php endforeach; endif;?>
    </div>
    <?php if($pages>1):?><div style="display:flex;justify-content:center;gap:6px;margin-top:20px;"><?php for($i=1;$i<=$pages;$i++):?><a href="?filter=<?php echo $filter;?>&page=<?php echo $i;?>" style="padding:7px 13px;border-radius:7px;text-decoration:none;font-size:13px;font-weight:600;<?php echo $i===$page?'background:#9b59b6;color:white;':'background:white;color:#555;border:1.5px solid #ddd;';?>"><?php echo $i;?></a><?php endfor;?></div><?php endif;?>
</div>
<script>
const CSRF='<?php echo htmlspecialchars($csrf_token);?>';const BASE='<?php echo BASE_URL;?>';
function markRead(id,el){fetch(BASE+'/public/api/notifications.php?action=mark_read',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=mark_read&notification_id='+id+'&csrf_token='+encodeURIComponent(CSRF)});el.classList.remove('unread');}
</script>
<script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body></html>
