<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . BASE_URL . '/public/login'); exit();
}
require_once __DIR__ . '/../../../config/db_config.php';

// Check database connection
if (!$pdo) {
    die('<div style="padding:20px;background:#f8d7da;color:#721c24;border-radius:8px;margin:20px;text-align:center;"><h3>Database Connection Error</h3><p>Unable to connect to the database. Please check your configuration.</p></div>');
}

$pageTitle = 'Database Backup';

// Ensure CSRF token exists in session
if (!defined('CSRF_TOKEN_NAME')) define('CSRF_TOKEN_NAME', 'csrf_token');
if (empty($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}

// Get list of existing backup files
$backupDir = __DIR__ . '/../../../backups/';
if (!is_dir($backupDir)) { mkdir($backupDir, 0755, true); }
$backupFiles = [];
foreach (glob($backupDir . '*.sql') as $file) {
    $backupFiles[] = [
        'name' => basename($file),
        'size' => round(filesize($file) / 1024, 1) . ' KB',
        'date' => date('M d, Y H:i', filemtime($file)),
        'ts'   => filemtime($file),
    ];
}
usort($backupFiles, fn($a,$b) => $b['ts'] - $a['ts']);

// Get table stats
$tables = [];
try {
    $stmt = $pdo->query("SHOW TABLE STATUS");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $t) {
        $tables[] = ['name' => $t['Name'], 'rows' => $t['Rows'], 'size' => round(($t['Data_length'] + $t['Index_length']) / 1024, 1) . ' KB'];
    }
} catch (PDOException $e) {}
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
<?php $pageTitle = 'Database Backup'; include_once __DIR__ . '/../../includes/admin_header.php'; ?>

<div class="main-content">

    <!-- Stats -->
    <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-bottom:20px;">
        <div class="stat-card" style="border-left:4px solid #3498DB;">
            <div style="display:flex;justify-content:space-between;align-items:start;">
                <div><div class="stat-value"><?php echo count($tables); ?></div><div class="stat-label">Tables</div></div>
                <div style="font-size:28px;color:#3498DB;opacity:.3;"><i class="fas fa-table"></i></div>
            </div>
        </div>
        <div class="stat-card" style="border-left:4px solid #27AE60;">
            <div style="display:flex;justify-content:space-between;align-items:start;">
                <div><div class="stat-value"><?php echo count($backupFiles); ?></div><div class="stat-label">Saved Backups</div></div>
                <div style="font-size:28px;color:#27AE60;opacity:.3;"><i class="fas fa-archive"></i></div>
            </div>
        </div>
        <div class="stat-card" style="border-left:4px solid #F39C12;">
            <div style="display:flex;justify-content:space-between;align-items:start;">
                <div>
                    <div class="stat-value" style="font-size:16px;"><?php echo !empty($backupFiles) ? $backupFiles[0]['date'] : 'Never'; ?></div>
                    <div class="stat-label">Last Backup</div>
                </div>
                <div style="font-size:28px;color:#F39C12;opacity:.3;"><i class="fas fa-clock"></i></div>
            </div>
        </div>
    </div>

    <!-- Create Backup -->
    <div class="section-card" style="margin-bottom:20px;">
        <div class="section-header">
            <h2 class="section-title"><i class="fas fa-database"></i> Create Backup</h2>
        </div>
        <p style="color:#7f8c8d;margin-bottom:20px;font-size:14px;">Creates a full SQL dump of the <strong><?php echo DB_NAME; ?></strong> database including all tables and data.</p>
        <div style="display:flex;gap:12px;flex-wrap:wrap;">
            <button onclick="createBackup('save')" class="btn-action btn-success-custom" id="btnSave">
                <i class="fas fa-save"></i> Save Backup to Server
            </button>
            <button onclick="createBackup('download')" class="btn-action btn-primary-custom" id="btnDownload">
                <i class="fas fa-download"></i> Download Backup Now
            </button>
        </div>
        <div id="backupProgress" style="display:none;margin-top:16px;background:#f0fff4;border:1px solid #c3e6cb;border-radius:8px;padding:14px;">
            <i class="fas fa-spinner fa-spin" style="color:#27AE60;"></i>
            <span style="margin-left:8px;color:#155724;font-weight:600;">Creating backup, please wait...</span>
        </div>
        <div id="backupResult" style="display:none;margin-top:16px;"></div>
    </div>

    <!-- Saved Backups -->
    <div class="section-card" style="margin-bottom:20px;">
        <div class="section-header">
            <h2 class="section-title"><i class="fas fa-archive"></i> Saved Backups</h2>
        </div>
        <?php if (empty($backupFiles)): ?>
            <p style="text-align:center;color:#7f8c8d;padding:30px;">No backups yet. Create your first backup above.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>Filename</th><th>Size</th><th>Created</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($backupFiles as $f): ?>
                    <tr>
                        <td><i class="fas fa-file-code" style="color:#3498DB;margin-right:8px;"></i><strong><?php echo htmlspecialchars($f['name']); ?></strong></td>
                        <td><?php echo $f['size']; ?></td>
                        <td><?php echo $f['date']; ?></td>
                        <td>
                            <a href="<?php echo BASE_URL; ?>/public/api/backup.php?action=download&file=<?php echo urlencode($f['name']); ?>" class="btn-action btn-primary-custom" style="padding:5px 12px;font-size:12px;">
                                <i class="fas fa-download"></i> Download
                            </a>
                            <button onclick="deleteBackup('<?php echo htmlspecialchars($f['name']); ?>')" class="btn-action btn-danger-custom" style="padding:5px 12px;font-size:12px;margin-left:4px;">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Database Tables Info -->
    <div class="section-card">
        <div class="section-header">
            <h2 class="section-title"><i class="fas fa-table"></i> Database Tables</h2>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Table Name</th><th>Rows</th><th>Size</th></tr></thead>
                <tbody>
                    <?php foreach ($tables as $t): ?>
                    <tr>
                        <td><i class="fas fa-table" style="color:#7f8c8d;margin-right:8px;"></i><?php echo htmlspecialchars($t['name']); ?></td>
                        <td><?php echo number_format($t['rows']); ?></td>
                        <td><?php echo $t['size']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const BASE_URL = '<?php echo BASE_URL; ?>';
const CSRF = '<?php echo htmlspecialchars($_SESSION[CSRF_TOKEN_NAME], ENT_QUOTES); ?>';

function createBackup(type) {
    document.getElementById('backupProgress').style.display = 'block';
    document.getElementById('backupResult').style.display = 'none';
    document.getElementById('btnSave').disabled = true;
    document.getElementById('btnDownload').disabled = true;

    if (type === 'download') {
        // For download, redirect directly
        window.location.href = BASE_URL + '/public/api/backup.php?action=download_now&csrf_token=' + encodeURIComponent(CSRF);
        setTimeout(() => {
            document.getElementById('backupProgress').style.display = 'none';
            document.getElementById('btnSave').disabled = false;
            document.getElementById('btnDownload').disabled = false;
        }, 3000);
        return;
    }

    fetch(BASE_URL + '/public/api/backup.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=save&csrf_token=' + encodeURIComponent(CSRF)
    })
    .then(r => {
        if (!r.ok) return r.text().then(t => { throw new Error('HTTP ' + r.status + ': ' + t.substring(0,200)); });
        return r.json();
    })
    .then(data => {
        document.getElementById('backupProgress').style.display = 'none';
        document.getElementById('btnSave').disabled = false;
        document.getElementById('btnDownload').disabled = false;
        const res = document.getElementById('backupResult');
        res.style.display = 'block';
        if (data.success) {
            res.innerHTML = '<div style="background:#d4edda;color:#155724;padding:14px;border-radius:8px;border:1px solid #c3e6cb;"><i class="fas fa-check-circle"></i> ' + data.message + '</div>';
            setTimeout(() => location.reload(), 1500);
        } else {
            res.innerHTML = '<div style="background:#f8d7da;color:#721c24;padding:14px;border-radius:8px;border:1px solid #f5c6cb;"><i class="fas fa-exclamation-circle"></i> ' + data.message + '</div>';
        }
    })
    .catch((err) => {
        document.getElementById('backupProgress').style.display = 'none';
        document.getElementById('btnSave').disabled = false;
        document.getElementById('btnDownload').disabled = false;
        document.getElementById('backupResult').style.display = 'block';
        document.getElementById('backupResult').innerHTML = '<div style="background:#f8d7da;color:#721c24;padding:14px;border-radius:8px;">Error: ' + err.message + '</div>';
    });
}

function deleteBackup(filename) {
    if (!confirm('Delete backup: ' + filename + '?')) return;
    fetch(BASE_URL + '/public/api/backup.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=delete&file=' + encodeURIComponent(filename) + '&csrf_token=' + encodeURIComponent(CSRF)
    })
    .then(r => r.json())
    .then(data => { if (data.success) location.reload(); else alert(data.message); });
}
</script>
<script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
