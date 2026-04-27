<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}
require_once '../../config/config.php';
require_once '../../config/db_config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'debug' => 'no session user_id']); exit;
}
$csrf = $_POST['csrf_token'] ?? '';
$sess = $_SESSION[CSRF_TOKEN_NAME] ?? $_SESSION['csrf_token'] ?? '';
if (!$csrf || !$sess || !hash_equals($sess, $csrf)) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token', 'debug_csrf_post' => substr($csrf,0,8), 'debug_csrf_sess' => substr($sess,0,8)]); exit;
}
$notifId = (int)($_POST['notification_id'] ?? 0);
$userId  = (int)$_SESSION['user_id'];
if (!$notifId) { echo json_encode(['success' => false, 'message' => 'Missing notification_id']); exit; }
try {
    $pdo->prepare("UPDATE furn_notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?")
        ->execute([$notifId, $userId]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
