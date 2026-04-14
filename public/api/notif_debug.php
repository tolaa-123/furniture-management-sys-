<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/db_config.php';

// Admin-only access
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

header('Content-Type: text/plain');

$userId = $_SESSION['user_id'] ?? 0;
$role   = $_SESSION['user_role'] ?? 'none';

echo "=== SESSION ===\n";
echo "user_id: $userId\n";
echo "role: $role\n";
echo "csrf: " . ($_SESSION[CSRF_TOKEN_NAME] ?? 'NOT SET') . "\n\n";

echo "=== furn_notifications for user $userId ===\n";
try {
    $s = $pdo->prepare("SELECT id, type, title, is_read FROM furn_notifications WHERE user_id = ? ORDER BY id");
    $s->execute([$userId]);
    $rows = $s->fetchAll(PDO::FETCH_ASSOC);
    echo count($rows) . " total rows\n";
    foreach ($rows as $r) {
        echo "  id={$r['id']} is_read={$r['is_read']} type={$r['type']} title={$r['title']}\n";
    }
} catch (Exception $e) { echo "ERROR: " . $e->getMessage() . "\n"; }

echo "\n=== furn_messages unread for user $userId ===\n";
try {
    $s = $pdo->prepare("SELECT COUNT(*) FROM furn_messages WHERE receiver_id = ? AND is_read = 0");
    $s->execute([$userId]);
    echo "Unread: " . $s->fetchColumn() . "\n";
} catch (Exception $e) { echo "N/A\n"; }

echo "\n=== furn_production_tasks pending for user $userId ===\n";
try {
    $s = $pdo->prepare("SELECT COUNT(*) FROM furn_production_tasks WHERE employee_id = ? AND status = 'pending'");
    $s->execute([$userId]);
    echo "Pending tasks: " . $s->fetchColumn() . "\n";
} catch (Exception $e) { echo "N/A\n"; }

echo "\n=== bellBadgeCount calculation ===\n";
try {
    $s = $pdo->prepare("SELECT COUNT(*) FROM furn_notifications WHERE user_id = ? AND is_read = 0");
    $s->execute([$userId]);
    $unreadNotif = (int)$s->fetchColumn();
    echo "Unread furn_notifications: $unreadNotif\n";
} catch (Exception $e) { echo "N/A\n"; }
