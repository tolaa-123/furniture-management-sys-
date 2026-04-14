<?php
/**
 * Mark all notifications as read for a user
 */
session_start();
require_once '../../config/config.php';
require_once '../../config/db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']); exit;
}

$csrf = $_POST['csrf_token'] ?? '';
if (empty($csrf) || !isset($_SESSION[CSRF_TOKEN_NAME]) || $csrf !== $_SESSION[CSRF_TOKEN_NAME]) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']); exit;
}

$userId = $_SESSION['user_id'];
$marked = 0;
try {
    $s = $pdo->prepare("UPDATE furn_notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
    $s->execute([$userId]);
    $marked += $s->rowCount();
    // Also mark messages
    try {
        $s2 = $pdo->prepare("UPDATE furn_messages SET is_read = 1 WHERE receiver_id = ? AND is_read = 0");
        $s2->execute([$userId]);
        $marked += $s2->rowCount();
    } catch (PDOException $e) {}
    echo json_encode(['success' => true, 'marked' => $marked]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
