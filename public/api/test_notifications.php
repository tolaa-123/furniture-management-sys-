<?php
/**
 * Test notification system
 * Run this to verify notifications are being created correctly
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/db_config.php';

header('Content-Type: application/json');

// Check if logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'Not logged in',
        'session_status' => session_status(),
        'session_data' => $_SESSION
    ]);
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 'unknown';

$result = [
    'success' => true,
    'user_id' => $userId,
    'user_role' => $userRole,
    'notifications' => []
];

try {
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'furn_notifications'");
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'furn_notifications table does not exist']);
        exit;
    }
    
    // Get all notifications for this user
    $stmt = $pdo->prepare("SELECT * FROM furn_notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $result['notification_count'] = count($notifications);
    $result['unread_count'] = count(array_filter($notifications, function($n) { return !$n['is_read']; }));
    $result['notifications'] = $notifications;
    
    // Check recent notifications for any user (to see if notifications are being created at all)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM furn_notifications");
    $result['total_notifications_in_system'] = $stmt->fetchColumn();
    
    // Get recent notifications with user info
    $stmt = $pdo->query("SELECT n.*, u.first_name, u.email 
                         FROM furn_notifications n 
                         LEFT JOIN furn_users u ON n.user_id = u.id 
                         ORDER BY n.created_at DESC LIMIT 5");
    $result['recent_notifications'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $result['error'] = $e->getMessage();
}

echo json_encode($result, JSON_PRETTY_PRINT);
