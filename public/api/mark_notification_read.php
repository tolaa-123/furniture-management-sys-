<?php
/**
 * Mark single notification as read
 */
require_once '../../config/db_config.php';

header('Content-Type: application/json');

// Verify CSRF token
$csrf_token = $_POST['csrf_token'] ?? '';
if (empty($csrf_token) || !isset($_SESSION[CSRF_TOKEN_NAME]) || $csrf_token !== $_SESSION[CSRF_TOKEN_NAME]) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$notificationId = $_POST['notification_id'] ?? 0;
$userId = $_SESSION['user_id'] ?? 0;

if (!$notificationId || !$userId) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE furn_notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$notificationId, $userId]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
