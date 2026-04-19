<?php
ob_start();
require_once '../../config/db_config.php';
ob_end_clean();
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$csrf = $_POST['csrf_token'] ?? '';
$sess = $_SESSION[CSRF_TOKEN_NAME] ?? '';
if (!$csrf || !$sess || !hash_equals($sess, $csrf)) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']); exit;
}

$msgId    = intval($_POST['msg_id'] ?? 0);
$toEmail  = trim($_POST['to_email'] ?? '');
$subject  = trim($_POST['subject'] ?? '');
$firstName = trim($_POST['first_name'] ?? '');
$reply    = trim($_POST['reply'] ?? '');

if (!$msgId || !$toEmail || !$reply) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']); exit;
}

if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']); exit;
}

// Send email using PHP mail()
$adminName = $_SESSION['user_name'] ?? 'SmartWorkshop Admin';
$fromEmail = defined('SMTP_USER') ? SMTP_USER : 'noreply@smartworkshop.com';

$headers  = "From: $adminName <$fromEmail>\r\n";
$headers .= "Reply-To: $fromEmail\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headers .= "MIME-Version: 1.0\r\n";

$body  = "Dear $firstName,\r\n\r\n";
$body .= $reply . "\r\n\r\n";
$body .= "---\r\n";
$body .= "Best regards,\r\n";
$body .= "$adminName\r\n";
$body .= "SmartWorkshop Furniture\r\n";

$sent = mail($toEmail, $subject, $body, $headers);

if ($sent) {
    // Mark message as replied
    try {
        $pdo->prepare("UPDATE contact_messages SET status='replied' WHERE id=?")->execute([$msgId]);
    } catch (PDOException $e) {}
    echo json_encode(['success' => true, 'message' => 'Reply sent successfully']);
} else {
    // mail() failed — try to use SMTP via EmailService if available
    try {
        require_once __DIR__ . '/../../app/services/EmailService.php';
        $emailService = new EmailService();
        $result = $emailService->sendEmail($toEmail, $subject, nl2br(htmlspecialchars($reply)));
        if ($result) {
            $pdo->prepare("UPDATE contact_messages SET status='replied' WHERE id=?")->execute([$msgId]);
            echo json_encode(['success' => true, 'message' => 'Reply sent successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send email. Please check your email configuration in Settings.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Email service unavailable. Please configure SMTP in Settings.']);
    }
}
