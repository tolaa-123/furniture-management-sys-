<?php
/**
 * Mark all employee notifications as read
 * - Marks unread messages as read
 * - Marks furn_notifications as read
 */
session_start();
require_once '../../config/config.php';
require_once '../../config/db_config.php';

header('Content-Type: application/json');

// Verify session
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Verify CSRF
$csrf = $_POST['csrf_token'] ?? '';
if (empty($csrf) || !isset($_SESSION[CSRF_TOKEN_NAME]) || $csrf !== $_SESSION[CSRF_TOKEN_NAME]) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$employeeId = $_SESSION['user_id'];
$marked = 0;

try {
    // Mark unread messages as read
    try {
        $stmt = $pdo->prepare("UPDATE furn_messages SET is_read = 1 WHERE receiver_id = ? AND is_read = 0");
        $stmt->execute([$employeeId]);
        $marked += $stmt->rowCount();
    } catch (PDOException $e) {}

    // Mark furn_notifications as read
    try {
        $stmt = $pdo->prepare("UPDATE furn_notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$employeeId]);
        $marked += $stmt->rowCount();
    } catch (PDOException $e) {}

    echo json_encode(['success' => true, 'marked' => $marked]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
