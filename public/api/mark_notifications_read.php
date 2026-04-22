<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/db_config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}
$csrf = $_POST['csrf_token'] ?? '';
$sess = $_SESSION[CSRF_TOKEN_NAME] ?? $_SESSION['csrf_token'] ?? '';
if (!$csrf || !$sess || !hash_equals($sess, $csrf)) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']); exit;
}
$userId = (int)$_SESSION['user_id'];
try {
    $pdo->prepare("UPDATE furn_notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0")
        ->execute([$userId]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
