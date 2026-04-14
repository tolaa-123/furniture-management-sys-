<?php
// send_contact.php - Contact form API endpoint
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once '../../config/db_config.php';
require_once '../../core/Database.php';

// Collect and trim inputs
$firstName = trim($_POST['firstName'] ?? '');
$lastName  = trim($_POST['lastName']  ?? '');
$email     = trim($_POST['email']     ?? '');
$subject   = trim($_POST['subject']   ?? '');
$message   = trim($_POST['message']   ?? '');

// Validate
if (!$firstName || !$lastName || !$email || !$subject || !$message) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

// Sanitize lengths
$firstName = substr($firstName, 0, 100);
$lastName  = substr($lastName,  0, 100);
$email     = substr($email,     0, 255);
$subject   = substr($subject,   0, 255);

try {
    $pdo = Database::getInstance()->getConnection();

    // Ensure table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS `contact_messages` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `first_name` VARCHAR(100) NOT NULL,
        `last_name` VARCHAR(100) NOT NULL,
        `email` VARCHAR(255) NOT NULL,
        `subject` VARCHAR(255) NOT NULL,
        `message` TEXT NOT NULL,
        `status` ENUM('new','read','replied') DEFAULT 'new',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    $stmt = $pdo->prepare("
        INSERT INTO contact_messages (first_name, last_name, email, subject, message, status)
        VALUES (?, ?, ?, ?, ?, 'new')
    ");
    $stmt->execute([$firstName, $lastName, $email, $subject, $message]);

    echo json_encode([
        'success' => true,
        'message' => 'Your message has been sent. We will get back to you soon.'
    ]);

} catch (PDOException $e) {
    error_log("Contact form DB error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save your message. Please try again.']);
}
