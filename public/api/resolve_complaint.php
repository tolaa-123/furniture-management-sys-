<?php
session_start();
require_once '../../config/db_config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['manager', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$complaintId     = intval($_POST['complaint_id'] ?? 0);
$managerResponse = trim($_POST['manager_response'] ?? '');

if (!$complaintId) {
    echo json_encode(['success' => false, 'message' => 'Invalid complaint ID']); exit;
}

try {
    $pdo->prepare("UPDATE furn_complaints SET status='resolved', manager_response=?, resolved_by=?, resolved_at=NOW() WHERE id=?")
        ->execute([$managerResponse, $_SESSION['user_id'], $complaintId]);
    // Notify customer
    require_once __DIR__ . '/../../app/includes/notification_helper.php';
    $c = $pdo->prepare("SELECT customer_id, order_id, subject FROM furn_complaints WHERE id=?");
    $c->execute([$complaintId]); $comp = $c->fetch(PDO::FETCH_ASSOC);
    if ($comp) {
        insertNotification($pdo, $comp['customer_id'], 'complaint', 'Complaint Resolved',
            'Your complaint "' . ($comp['subject'] ?? '') . '" has been resolved.' . ($managerResponse ? ' Response: ' . $managerResponse : ''),
            $complaintId, '/customer/my-orders', 'normal');
    }
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
