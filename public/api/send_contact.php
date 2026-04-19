<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
}

// Capture any accidental output from config
ob_start();
require_once '../../config/db_config.php';
ob_end_clean();

$firstName = trim($_POST['firstName'] ?? '');
$lastName  = trim($_POST['lastName']  ?? '');
$email     = trim($_POST['email']     ?? '');
$subject   = trim($_POST['subject']   ?? '');
$message   = trim($_POST['message']   ?? '');

if (!$firstName || !$lastName || !$email || !$subject || !$message) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']); exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address: "' . htmlspecialchars($email) . '"']); exit;
}

$firstName = substr($firstName, 0, 100);
$lastName  = substr($lastName,  0, 100);
$email     = substr($email,     0, 255);
$subject   = substr($subject,   0, 255);

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

    $pdo->prepare("INSERT INTO contact_messages (first_name, last_name, email, subject, message) VALUES (?,?,?,?,?)")
        ->execute([$firstName, $lastName, $email, $subject, $message]);

    echo json_encode(['success' => true, 'message' => 'Your message has been sent. We will get back to you soon.']);
} catch (PDOException $e) {
    error_log("Contact form error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save your message. Please try again.']);
}
