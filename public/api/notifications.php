<?php
/**
 * Unified Notifications API
 * GET  ?action=list&page=1&filter=all|unread|read&search=keyword
 * GET  ?action=unread_count
 * POST action=mark_read   + notification_id
 * POST action=mark_all_read
 */
session_start();
require_once '../../config/config.php';
require_once '../../config/db_config.php';
require_once '../../app/includes/notification_helper.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}
$userId = (int)$_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── GET requests ──────────────────────────────────────────────────────────────
if ($method === 'GET') {
    if ($action === 'unread_count') {
        try {
            $s = $pdo->prepare("SELECT COUNT(*) FROM furn_notifications WHERE user_id = ? AND is_read = 0");
            $s->execute([$userId]);
            echo json_encode(['success' => true, 'count' => (int)$s->fetchColumn()]);
        } catch (PDOException $e) {
            echo json_encode(['success' => true, 'count' => 0]);
        }
        exit;
    }

    if ($action === 'list') {
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $filter = $_GET['filter'] ?? 'all';
        $search = trim($_GET['search'] ?? '');
        $limit  = 20;
        $offset = ($page - 1) * $limit;

        $where = ['user_id = ?'];
        $params = [$userId];
        if ($filter === 'unread') { $where[] = 'is_read = 0'; }
        if ($filter === 'read')   { $where[] = 'is_read = 1'; }
        if ($search !== '') {
            $where[] = '(title LIKE ? OR message LIKE ?)';
            $params[] = '%'.$search.'%';
            $params[] = '%'.$search.'%';
        }
        $whereStr = implode(' AND ', $where);

        try {
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM furn_notifications WHERE $whereStr");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            $listStmt = $pdo->prepare("SELECT * FROM furn_notifications WHERE $whereStr ORDER BY is_read ASC, created_at DESC LIMIT $limit OFFSET $offset");
            $listStmt->execute($params);
            $rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);

            // Add time_ago and icon
            foreach ($rows as &$r) {
                $r['time_ago'] = timeAgo($r['created_at']);
                [$r['icon'], $r['color']] = notifIcon($r['type']);
            }

            echo json_encode([
                'success' => true,
                'data'    => $rows,
                'total'   => $total,
                'page'    => $page,
                'pages'   => ceil($total / $limit),
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// ── POST requests ─────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    $sess = $_SESSION[CSRF_TOKEN_NAME] ?? $_SESSION['csrf_token'] ?? '';
    if (!$csrf || !$sess || !hash_equals($sess, $csrf)) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']); exit;
    }

    if ($action === 'mark_read') {
        $notifId = (int)($_POST['notification_id'] ?? 0);
        if (!$notifId) { echo json_encode(['success' => false, 'message' => 'Missing notification_id']); exit; }
        try {
            $pdo->prepare("UPDATE furn_notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?")
                ->execute([$notifId, $userId]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'mark_all_read') {
        try {
            $pdo->prepare("UPDATE furn_notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0")
                ->execute([$userId]);
            // Also mark messages
            try {
                $pdo->prepare("UPDATE furn_messages SET is_read = 1 WHERE receiver_id = ? AND is_read = 0")->execute([$userId]);
            } catch (PDOException $e2) {}
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
