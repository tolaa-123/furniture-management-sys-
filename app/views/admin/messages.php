<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . BASE_URL . '/public/login'); exit();
}
require_once __DIR__ . '/../../../config/db_config.php';

// Mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $id = intval($_POST['id']);
    $pdo->prepare("UPDATE contact_messages SET status='read' WHERE id=?")->execute([$id]);
    header('Location: ' . BASE_URL . '/public/admin/messages'); exit();
}

// Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_msg'])) {
    $id = intval($_POST['id']);
    $pdo->prepare("DELETE FROM contact_messages WHERE id=?")->execute([$id]);
    header('Location: ' . BASE_URL . '/public/admin/messages'); exit();
}

// Ensure table exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS contact_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        email VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        status ENUM('new','read','replied') DEFAULT 'new',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {}

$messages = [];
try {
    $stmt = $pdo->query("SELECT * FROM contact_messages ORDER BY created_at DESC");
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

$newCount = count(array_filter($messages, fn($m) => $m['status'] === 'new'));
$pageTitle = 'Contact Messages';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/css/admin-responsive.css">
</head>
<body>
<button class="mobile-menu-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>
<div class="sidebar-overlay"></div>
<?php include_once __DIR__ . '/../../includes/admin_sidebar.php'; ?>
<?php $pageTitle = 'Contact Messages'; include_once __DIR__ . '/../../includes/admin_header.php'; ?>

<div class="main-content">

    <!-- Stats -->
    <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));margin-bottom:20px;">
        <div class="stat-card" style="border-left:4px solid #e74c3c;">
            <div style="display:flex;justify-content:space-between;align-items:start;">
                <div><div class="stat-value"><?php echo $newCount; ?></div><div class="stat-label">New Messages</div></div>
                <div style="font-size:28px;color:#e74c3c;opacity:.3;"><i class="fas fa-envelope"></i></div>
            </div>
        </div>
        <div class="stat-card" style="border-left:4px solid #3498DB;">
            <div style="display:flex;justify-content:space-between;align-items:start;">
                <div><div class="stat-value"><?php echo count($messages); ?></div><div class="stat-label">Total Messages</div></div>
                <div style="font-size:28px;color:#3498DB;opacity:.3;"><i class="fas fa-inbox"></i></div>
            </div>
        </div>
    </div>

    <div class="section-card">
        <div class="section-header">
            <h2 class="section-title"><i class="fas fa-envelope"></i> Contact Messages</h2>
        </div>
        <?php if (empty($messages)): ?>
            <p style="text-align:center;color:#7f8c8d;padding:40px;">No messages yet.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Subject</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($messages as $msg): ?>
                    <tr style="<?php echo $msg['status']==='new' ? 'background:#fff8f0;font-weight:600;' : ''; ?>">
                        <td><?php echo htmlspecialchars($msg['first_name'].' '.$msg['last_name']); ?></td>
                        <td><a href="mailto:<?php echo htmlspecialchars($msg['email']); ?>"><?php echo htmlspecialchars($msg['email']); ?></a></td>
                        <td><?php echo htmlspecialchars($msg['subject']); ?></td>
                        <td style="max-width:250px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?php echo htmlspecialchars($msg['message']); ?>">
                            <?php echo htmlspecialchars($msg['message']); ?>
                        </td>
                        <td>
                            <?php
                            $colors = ['new'=>'#e74c3c','read'=>'#3498db','replied'=>'#27ae60'];
                            $c = $colors[$msg['status']] ?? '#aaa';
                            ?>
                            <span style="background:<?php echo $c; ?>;color:white;padding:3px 10px;border-radius:10px;font-size:12px;">
                                <?php echo ucfirst($msg['status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y H:i', strtotime($msg['created_at'])); ?></td>
                        <td>
                            <button onclick="viewMessage(<?php echo htmlspecialchars(json_encode($msg), ENT_QUOTES); ?>)" class="btn-action btn-primary-custom" style="padding:5px 10px;font-size:12px;">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <?php if ($msg['status'] === 'new'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="id" value="<?php echo $msg['id']; ?>">
                                <button type="submit" name="mark_read" class="btn-action btn-success-custom" style="padding:5px 10px;font-size:12px;">
                                    <i class="fas fa-check"></i> Mark Read
                                </button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this message?');">
                                <input type="hidden" name="id" value="<?php echo $msg['id']; ?>">
                                <button type="submit" name="delete_msg" class="btn-action btn-danger-custom" style="padding:5px 10px;font-size:12px;">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- View Message Modal -->
<div id="msgModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:white;border-radius:12px;width:100%;max-width:520px;margin:20px;overflow:hidden;box-shadow:0 8px 30px rgba(0,0,0,0.2);">
        <div style="background:linear-gradient(135deg,#2c1810,#3d1f14);padding:16px 20px;display:flex;justify-content:space-between;align-items:center;">
            <h3 style="margin:0;color:white;font-size:16px;"><i class="fas fa-envelope"></i> Message Details</h3>
            <button onclick="document.getElementById('msgModal').style.display='none'" style="background:none;border:none;color:white;font-size:20px;cursor:pointer;">&times;</button>
        </div>
        <div style="padding:20px;" id="msgContent"></div>
        <div style="padding:14px 20px;border-top:1px solid #eee;text-align:right;">
            <button onclick="document.getElementById('msgModal').style.display='none'" style="padding:9px 20px;border:1.5px solid #ddd;background:white;border-radius:8px;cursor:pointer;">Close</button>
        </div>
    </div>
</div>

<script>
function viewMessage(msg) {
    document.getElementById('msgContent').innerHTML = `
        <div style="margin-bottom:12px;"><strong>From:</strong> ${msg.first_name} ${msg.last_name}</div>
        <div style="margin-bottom:12px;"><strong>Email:</strong> <a href="mailto:${msg.email}">${msg.email}</a></div>
        <div style="margin-bottom:12px;"><strong>Subject:</strong> ${msg.subject}</div>
        <div style="margin-bottom:12px;"><strong>Date:</strong> ${msg.created_at}</div>
        <div style="background:#f8f9fa;padding:14px;border-radius:8px;border-left:4px solid #3498db;white-space:pre-wrap;font-size:14px;line-height:1.6;margin-bottom:16px;">${msg.message}</div>
        <div style="border-top:1px solid #eee;padding-top:14px;">
            <strong style="font-size:14px;"><i class="fas fa-reply" style="color:#27ae60;"></i> Reply to ${msg.first_name}</strong>
            <textarea id="replyText" rows="4" placeholder="Type your reply here..." 
                style="width:100%;margin-top:8px;padding:10px;border:1.5px solid #ddd;border-radius:8px;font-family:inherit;font-size:13px;resize:vertical;box-sizing:border-box;"></textarea>
            <div style="display:flex;gap:10px;margin-top:10px;">
                <button onclick="sendReply('${msg.id}','${msg.email.replace(/'/g,"\\'")}','${msg.subject.replace(/'/g,"\\'")}','${msg.first_name.replace(/'/g,"\\'")}' )"
                    id="replyBtn"
                    style="background:#27ae60;color:white;border:none;padding:9px 20px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit;">
                    <i class="fas fa-paper-plane"></i> Send Reply
                </button>
                <div id="replyStatus" style="display:flex;align-items:center;font-size:13px;"></div>
            </div>
        </div>
    `;
    document.getElementById('msgModal').style.display = 'flex';
}
function sendReply(msgId, toEmail, subject, firstName) {
    const reply = document.getElementById('replyText').value.trim();
    if (!reply) { alert('Please type a reply message.'); return; }
    const btn = document.getElementById('replyBtn');
    const status = document.getElementById('replyStatus');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    status.innerHTML = '';
    fetch('<?php echo BASE_URL; ?>/public/api/reply_contact.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'msg_id=' + encodeURIComponent(msgId) +
              '&to_email=' + encodeURIComponent(toEmail) +
              '&subject=' + encodeURIComponent('Re: ' + subject) +
              '&first_name=' + encodeURIComponent(firstName) +
              '&reply=' + encodeURIComponent(reply) +
              '&csrf_token=<?php echo htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? '', ENT_QUOTES); ?>'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            status.innerHTML = '<span style="color:#27ae60;"><i class="fas fa-check-circle"></i> Reply sent!</span>';
            btn.innerHTML = '<i class="fas fa-check"></i> Sent';
            document.getElementById('replyText').value = '';
            setTimeout(() => document.getElementById('msgModal').style.display = 'none', 1500);
        } else {
            status.innerHTML = '<span style="color:#e74c3c;">' + data.message + '</span>';
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Reply';
        }
    })
    .catch(() => {
        status.innerHTML = '<span style="color:#e74c3c;">Network error.</span>';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Reply';
    });
}
</script>
<script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>

<script>
document.addEventListener('click', function(e) {
    const modal = document.getElementById('msgModal');
    if (e.target === modal) modal.style.display = 'none';
});
</script>
