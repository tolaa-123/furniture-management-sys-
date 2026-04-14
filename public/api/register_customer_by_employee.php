<?php
session_start();
require_once '../../config/db_config.php';
header('Content-Type: application/json');

// Employee only
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name']  ?? '');
    $email     = trim($_POST['email']      ?? '');
    $phone     = trim($_POST['phone']      ?? '');
    $address   = trim($_POST['address']    ?? '');
    $password  = $_POST['password']        ?? '';

    if (!$firstName || !$lastName || !$email || !$password) {
        throw new Exception('First name, last name, email and password are required.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address.');
    }
    if (strlen($password) < 6) {
        throw new Exception('Password must be at least 6 characters.');
    }

    // Check email not already taken
    $check = $pdo->prepare("SELECT id FROM furn_users WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) {
        throw new Exception('A customer with this email already exists. Please select them from the dropdown.');
    }

    $username     = strtolower($firstName . '.' . $lastName . rand(10,99));
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO furn_users (username, email, password_hash, role, first_name, last_name, phone, address, is_active, created_at)
        VALUES (?, ?, ?, 'customer', ?, ?, ?, ?, 1, NOW())
    ");
    $stmt->execute([$username, $email, $passwordHash, $firstName, $lastName, $phone ?: null, $address ?: null]);
    $customerId = $pdo->lastInsertId();

    echo json_encode([
        'success'     => true,
        'customer_id' => $customerId,
        'message'     => 'Customer registered successfully.'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
