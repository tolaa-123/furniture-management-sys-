<?php
/**
 * Mark all notifications as read for a user
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../../config/db_config.php';

header('Content-Type: application/json');

$csrf_token = $_POST['csrf_token'] ?? '';
if (empty($csrf_token) || !isset($_SESSION[CSRF_TOKEN_NAME]) || !hash_equals($_SESSION[CSRF_TOKEN_NAME], $csrf_token)) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']); exit;
}

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) { echo json_encode(['success' => false, 'message' => 'Not logged in']); exit; }

try {
    $stmt = $pdo->prepare("UPDATE furn_notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    echo json_encode(['success' => true, 'marked_count' => $stmt->rowCount()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
