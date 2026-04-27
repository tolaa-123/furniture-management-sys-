<?php
session_start();
require_once '../config/config.php';
require_once '../config/db_config.php';

// Check database connection
if (!$pdo) {
    die('<div style="padding:20px;background:#f8d7da;color:#721c24;border-radius:8px;margin:20px;text-align:center;"><h3>Database Connection Error</h3><p>Unable to connect to the database. Please check your configuration.</p></div>');
}

$token = trim($_GET['token'] ?? '');
$error = '';
$success = '';
$employee = null;

if (!$token) {
    $error = 'Invalid or missing invite link.';
} else {
    try {
        $stmt = $pdo->prepare("
            SELECT id, full_name, email FROM furn_users
            WHERE invite_token = ? AND invite_expires_at > NOW() AND status = 'pending'
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$employee) {
            $error = 'This invite link is invalid or has expired. Please ask your admin to resend the invitation.';
        }
    } catch (PDOException $e) {
        $error = 'A database error occurred. Please try again.';
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $employee) {
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("
                UPDATE furn_users
                SET password_hash = ?, status = 'active', is_active = 1,
                    invite_token = NULL, invite_expires_at = NULL
                WHERE id = ? AND invite_token = ?
            ");
            $stmt->execute([$hash, $employee['id'], $token]);
            $success = 'Your account is ready! You can now log in.';
            $employee = null; // hide form
        } catch (PDOException $e) {
            $error = 'Could not save your password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Up Your Account - SmartWorkshop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #f5f1ed, #e8dfd5); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .card { border: none; border-radius: 16px; box-shadow: 0 10px 40px rgba(74,52,40,.15); max-width: 440px; width: 100%; overflow: hidden; }
        .card-header { background: linear-gradient(135deg, #4a3428, #6b4e3d); color: white; text-align: center; padding: 2rem; }
        .card-header .icon { width: 60px; height: 60px; background: rgba(255,255,255,.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 1.8rem; }
        .form-control { border-radius: 25px; padding: .75rem 1rem .75rem 2.8rem; border: 1px solid #d4c5b9; background: #f9f6f3; }
        .form-control:focus { border-color: #4a3428; box-shadow: 0 0 0 .15rem rgba(74,52,40,.1); background: white; }
        .input-wrap { position: relative; margin-bottom: 1.2rem; }
        .input-wrap i.icon-left { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #6b4e3d; }
        .input-wrap i.toggle-pw { position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); cursor: pointer; color: #aaa; }
        .btn-primary { background: linear-gradient(135deg, #4a3428, #6b4e3d); border: none; border-radius: 25px; padding: .85rem; font-weight: 600; width: 100%; }
        .btn-primary:hover { background: linear-gradient(135deg, #3a2618, #4a3428); }
        .strength-bar { height: 4px; background: #e2e8f0; border-radius: 2px; margin-top: .4rem; overflow: hidden; }
        .strength-fill { height: 100%; width: 0; transition: all .3s; }
    </style>
</head>
<body>
<div class="card">
    <div class="card-header">
        <div class="icon"><i class="fas fa-user-check"></i></div>
        <h4 class="mb-1">Set Up Your Account</h4>
        <p class="mb-0 opacity-75" style="font-size:14px;">SmartWorkshop Employee Portal</p>
    </div>
    <div class="p-4">
        <?php if ($error): ?>
            <div class="alert alert-danger rounded-3"><i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success rounded-3"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?></div>
            <div class="text-center mt-3">
                <a href="<?php echo BASE_URL; ?>/public/login" class="btn btn-primary">Go to Login</a>
            </div>
        <?php elseif ($employee): ?>
            <p class="text-muted mb-3" style="font-size:14px;">
                Welcome, <strong><?php echo htmlspecialchars($employee['full_name']); ?></strong>!<br>
                Set a password for <strong><?php echo htmlspecialchars($employee['email']); ?></strong>
            </p>
            <form method="POST">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <div class="input-wrap">
                    <i class="fas fa-lock icon-left"></i>
                    <input type="password" name="password" id="pw" class="form-control" placeholder="New password" required minlength="8" autocomplete="new-password">
                    <i class="fas fa-eye toggle-pw" id="togglePw"></i>
                </div>
                <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                <div class="input-wrap mt-3">
                    <i class="fas fa-lock icon-left"></i>
                    <input type="password" name="confirm_password" id="cpw" class="form-control" placeholder="Confirm password" required autocomplete="new-password">
                    <i class="fas fa-eye toggle-pw" id="toggleCpw"></i>
                </div>
                <button type="submit" class="btn btn-primary mt-3">Activate My Account</button>
            </form>
        <?php elseif (!$success): ?>
            <p class="text-center text-muted">Please contact your administrator for a new invite link.</p>
        <?php endif; ?>
    </div>
</div>
<script>
document.getElementById('togglePw')?.addEventListener('click', function() {
    const i = document.getElementById('pw');
    i.type = i.type === 'password' ? 'text' : 'password';
    this.classList.toggle('fa-eye-slash');
});
document.getElementById('toggleCpw')?.addEventListener('click', function() {
    const i = document.getElementById('cpw');
    i.type = i.type === 'password' ? 'text' : 'password';
    this.classList.toggle('fa-eye-slash');
});
document.getElementById('pw')?.addEventListener('input', function() {
    const v = this.value, bar = document.getElementById('strengthFill');
    let s = 0;
    if (v.length >= 8) s++;
    if (/[A-Z]/.test(v)) s++;
    if (/[0-9]/.test(v)) s++;
    if (/[^A-Za-z0-9]/.test(v)) s++;
    const colors = ['', '#ef4444', '#f59e0b', '#10b981', '#10b981'];
    bar.style.width = (s * 25) + '%';
    bar.style.background = colors[s] || '#e2e8f0';
});
</script>
</body>
</html>
