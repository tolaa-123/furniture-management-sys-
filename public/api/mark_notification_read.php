<?php
/**
 * Mark single notification as read
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

$notificationId = intval($_POST['notification_id'] ?? 0);
$userId = $_SESSION['user_id'];

if (!$notificationId) {
    echo json_encode(['success' => false, 'message' => 'Missing notification_id']); exit;
}

try {
    $pdo->prepare("UPDATE furn_notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?")
        ->execute([$notificationId, $userId]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
