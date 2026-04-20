<?php
/**
 * API: Admin reply to contact messages via email
 */

// Suppress all error output to prevent breaking JSON responses
error_reporting(0);
ini_set('display_errors', 0);

// Define CSRF_TOKEN_NAME if not already defined
if (!defined('CSRF_TOKEN_NAME')) {
    define('CSRF_TOKEN_NAME', 'csrf_token');
}

// Define BASE_URL if not already defined
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/NEWkoder');
}

ob_start();
require_once '../../config/db_config.php';
ob_end_clean();

header('Content-Type: application/json');

// Session check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auth check - admin only
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Method check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// CSRF check
$csrfToken = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION[CSRF_TOKEN_NAME] ?? '';
if (!$csrfToken || !$sessionToken || !hash_equals($sessionToken, $csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

// Get POST data
$msgId = (int) ($_POST['msg_id'] ?? 0);
$toEmail = trim($_POST['to_email'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$firstName = trim($_POST['first_name'] ?? '');
$reply = trim($_POST['reply'] ?? '');

// Validate required fields
if ($msgId <= 0 || !$toEmail || !$subject || !$reply) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit();
}

// Validate email format
if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit();
}

// Check if message exists
try {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, subject FROM contact_messages WHERE id = ?");
    $stmt->execute([$msgId]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$message) {
        echo json_encode(['success' => false, 'message' => 'Message not found']);
        exit();
    }
    
    // Prepare email content
    $customerName = $message['first_name'] . ' ' . $message['last_name'];
    $originalSubject = $message['subject'];
    $adminName = $_SESSION['first_name'] ?? $_SESSION['user_name'] ?? 'Admin';
    
    // Build HTML email body
    $emailBody = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
            .wrapper { max-width: 600px; margin: 30px auto; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,.1); }
            .header { background: #3d1f14; color: #fff; padding: 24px 30px; text-align: center; }
            .header h1 { margin: 0; font-size: 22px; }
            .body { padding: 30px; }
            .body p { color: #444; line-height: 1.6; margin: 0 0 12px; }
            .reply-box { background: #f8f4e9; border-left: 4px solid #3d1f14; padding: 16px 20px; border-radius: 6px; margin: 16px 0; }
            .reply-box p { margin: 4px 0; font-size: 14px; }
            .original-msg { background: #f8f9fa; border: 1px solid #dee2e6; padding: 16px; border-radius: 6px; margin: 16px 0; }
            .original-msg p { margin: 4px 0; font-size: 13px; color: #666; }
            .footer { background: #f8f4e9; text-align: center; padding: 16px; font-size: 12px; color: #888; }
        </style>
    </head>
    <body>
        <div class='wrapper'>
            <div class='header'>
                <h1>SmartWorkshop</h1>
                <p style='margin: 4px 0 0; font-size: 14px; opacity: .85;'>Response to Your Inquiry</p>
            </div>
            <div class='body'>
                <p>Dear {$customerName},</p>
                <p>Thank you for contacting us. We have reviewed your message and here is our response:</p>
                
                <div class='reply-box'>
                    <p><strong>From:</strong> {$adminName} (Admin Team)</p>
                    <p><strong>Subject:</strong> {$subject}</p>
                    <div style='margin-top: 12px; padding-top: 12px; border-top: 1px solid #ddd;'>
                        " . nl2br(htmlspecialchars($reply)) . "
                    </div>
                </div>
                
                <div class='original-msg'>
                    <p style='font-weight: 600; margin-bottom: 8px;'>Your Original Message:</p>
                    <p><strong>Subject:</strong> {$originalSubject}</p>
                    <p style='margin-top: 8px;'>" . nl2br(htmlspecialchars($message['subject'])) . "</p>
                </div>
                
                <p style='margin-top: 20px;'>If you have any further questions, please don't hesitate to contact us.</p>
                <p>Best regards,<br><strong>SmartWorkshop Team</strong></p>
            </div>
            <div class='footer'>
                SmartWorkshop &mdash; Custom Furniture Crafted for You<br>
                This is an automated response from our customer support system.
            </div>
        </div>
    </body>
    </html>";
    
    // Load EmailService
    require_once __DIR__ . '/../../app/services/EmailService.php';
    
    $emailService = new EmailService();
    
    // Send email
    $emailSent = $emailService->send($toEmail, $subject, $emailBody);
    
    if ($emailSent) {
        // Update message status to 'replied'
        $updateStmt = $pdo->prepare("UPDATE contact_messages SET status = 'replied' WHERE id = ?");
        $updateStmt->execute([$msgId]);
        
        error_log("Admin reply sent to: {$toEmail} for message #{$msgId}");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Reply sent successfully'
        ]);
    } else {
        $error = $emailService->getLastError() ?: 'Failed to send email';
        error_log("Email send failed: {$error}");
        
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to send email: ' . $error
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Reply contact error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
} catch (Exception $e) {
    error_log("Reply contact error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
