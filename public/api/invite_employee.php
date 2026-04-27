<?php
session_start();
require_once '../../config/db_config.php';
require_once '../../app/services/EmailService.php';

header('Content-Type: application/json');

// Check database connection
if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// CSRF check
$csrfPost = $_POST['csrf_token'] ?? '';
$csrfSess = $_SESSION['csrf_token'] ?? '';
if (!$csrfPost || !$csrfSess || !hash_equals($csrfSess, $csrfPost)) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$fullName = trim($_POST['full_name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$phone    = trim($_POST['phone'] ?? '');

if (!$fullName || !$email) {
    echo json_encode(['success' => false, 'message' => 'Name and email are required']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

try {
    // Check email not already taken
    $check = $pdo->prepare("SELECT id FROM furn_users WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'This email is already registered']);
        exit;
    }

    // Ensure invite_token column exists
    try {
        $pdo->exec("ALTER TABLE furn_users ADD COLUMN IF NOT EXISTS invite_token VARCHAR(64) DEFAULT NULL");
        $pdo->exec("ALTER TABLE furn_users ADD COLUMN IF NOT EXISTS invite_expires_at DATETIME DEFAULT NULL");
    } catch (PDOException $e) {}

    // Get employee role_id
    $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE role_name = 'employee' LIMIT 1");
    $roleStmt->execute();
    $roleRow = $roleStmt->fetch(PDO::FETCH_ASSOC);
    $roleId  = $roleRow ? $roleRow['id'] : null;

    $nameParts = explode(' ', $fullName, 2);
    $firstName = $nameParts[0];
    $lastName  = $nameParts[1] ?? '';
    $username  = strtolower(explode('@', $email)[0]) . '_' . time();

    // Generate secure token
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+48 hours'));

    // Insert employee with no password (pending setup)
    $stmt = $pdo->prepare("
        INSERT INTO furn_users
        (role_id, username, email, password_hash, full_name, first_name, last_name, phone,
         role, status, is_active, failed_attempts, invite_token, invite_expires_at, created_at)
        VALUES (?, ?, ?, '', ?, ?, ?, ?, 'employee', 'pending', 0, 0, ?, ?, NOW())
    ");
    $stmt->execute([$roleId, $username, $email, $fullName, $firstName, $lastName, $phone, $token, $expires]);

    // Build invite link
    $inviteLink = BASE_URL . '/public/employee-setup?token=' . $token;

    // Send email
    $emailService = new EmailService();
    $subject = 'You have been invited to SmartWorkshop';
    $body    = "<!DOCTYPE html><html><head><meta charset='UTF-8'>
    <style>
        body{font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:0}
        .wrapper{max-width:600px;margin:30px auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.1)}
        .header{background:#3d1f14;color:#fff;padding:24px 30px;text-align:center}
        .header h1{margin:0;font-size:22px}
        .body{padding:30px}
        .body p{color:#444;line-height:1.6;margin:0 0 12px}
        .detail-box{background:#f8f4e9;border-left:4px solid #3d1f14;padding:16px 20px;border-radius:6px;margin:16px 0}
        .btn{display:inline-block;background:#3d1f14;color:#fff;padding:12px 28px;border-radius:50px;text-decoration:none;font-weight:bold;margin-top:16px}
        .footer{background:#f8f4e9;text-align:center;padding:16px;font-size:12px;color:#888}
    </style></head><body>
    <div class='wrapper'>
        <div class='header'><h1>SmartWorkshop</h1><p style='margin:4px 0 0;font-size:14px;opacity:.85'>Employee Invitation</p></div>
        <div class='body'>
            <p>Hello <strong>" . htmlspecialchars($fullName) . "</strong>,</p>
            <p>You have been invited to join <strong>SmartWorkshop</strong> as an employee.</p>
            <p>Click the button below to set up your password and activate your account. This link expires in <strong>48 hours</strong>.</p>
            <div style='text-align:center;margin:24px 0;'>
                <a href='" . htmlspecialchars($inviteLink) . "' class='btn'>Set Up My Account</a>
            </div>
            <div class='detail-box'>
                <p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>
                <p><strong>Link expires:</strong> " . date('M j, Y g:i A', strtotime($expires)) . "</p>
            </div>
            <p style='font-size:13px;color:#888;margin-top:20px;'>If you cannot click the button, copy and paste this link into your browser:<br>
            <a href='" . htmlspecialchars($inviteLink) . "' style='color:#3d1f14;word-break:break-all;'>" . htmlspecialchars($inviteLink) . "</a></p>
        </div>
        <div class='footer'>SmartWorkshop &mdash; Custom Furniture Crafted for You<br>If you did not expect this invitation, you can ignore this email.</div>
    </div></body></html>";

    $sent = $emailService->send($email, $subject, $body);

    if ($sent) {
        echo json_encode(['success' => true, 'message' => 'Invitation sent to ' . $email]);
    } else {
        // Email failed — still created the account, return the link so admin can share manually
        echo json_encode([
            'success'      => true,
            'message'      => 'Employee created but email could not be sent. Share this link manually:',
            'invite_link'  => $inviteLink,
            'email_failed' => true
        ]);
    }

} catch (PDOException $e) {
    error_log("Invite employee error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
