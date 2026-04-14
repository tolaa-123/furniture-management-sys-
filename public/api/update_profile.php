<?php
session_start();
header('Content-Type: application/json');

require_once '../../config/db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'customer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');

if ($firstName === '' || $lastName === '' || $email === '') {
    echo json_encode(['success' => false, 'message' => 'First name, last name, and email are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

try {
    $emailCheck = $pdo->prepare('SELECT id FROM furn_users WHERE email = ? AND id != ? LIMIT 1');
    $emailCheck->execute([$email, $userId]);
    if ($emailCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email is already in use']);
        exit;
    }

    $fullName = trim($firstName . ' ' . $lastName);

    $stmt = $pdo->prepare('
        UPDATE furn_users
        SET first_name = ?,
            last_name = ?,
            full_name = ?,
            email = ?,
            phone = ?,
            address = ?
        WHERE id = ?
    ');

    $stmt->execute([$firstName, $lastName, $fullName, $email, $phone ?: null, $address ?: null, $userId]);

    // Keep session display values in sync after profile updates.
    $_SESSION['first_name'] = $firstName;
    $_SESSION['user_name'] = $fullName;

    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
} catch (Throwable $e) {
    error_log('update_profile API error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
}
