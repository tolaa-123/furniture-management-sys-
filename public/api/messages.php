<?php
/**
 * Messages API — handles all AJAX message actions for all roles
 * POST: action = send | reply | mark_read | delete
 * GET:  action = inbox | sent | unread_count
 */
session_start();
require_once __DIR__ . '/../../config/db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'error' => 'Unauthenticated']); exit();
}

$userId = (int)$_SESSION['user_id'];
$role   = $_SESSION['user_role'] ?? '';

// Auto-create table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS furn_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        subject VARCHAR(255) NOT NULL DEFAULT '',
        message TEXT NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        read_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(sender_id), INDEX(receiver_id), INDEX(is_read)
    )");
} catch (PDOException $e) {}

$method = $_SERVER['REQUEST_METHOD'];

// ── GET requests ──────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'inbox') {
        $stmt = $pdo->prepare("
            SELECT m.*, CONCAT(u.first_name,' ',u.last_name) as sender_name, u.role as sender_role
            FROM furn_messages m
            LEFT JOIN furn_users u ON u.id = m.sender_id
            WHERE m.receiver_id = ?
            ORDER BY m.created_at DESC
        ");
        $stmt->execute([$userId]);
        echo json_encode(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]); exit();
    }

    if ($action === 'sent') {
        $stmt = $pdo->prepare("
            SELECT m.*, CONCAT(u.first_name,' ',u.last_name) as receiver_name, u.role as receiver_role
            FROM furn_messages m
            LEFT JOIN furn_users u ON u.id = m.receiver_id
            WHERE m.sender_id = ?
            ORDER BY m.created_at DESC
        ");
        $stmt->execute([$userId]);
        echo json_encode(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]); exit();
    }

    if ($action === 'unread_count') {
        $count = $pdo->prepare("SELECT COUNT(*) FROM furn_messages WHERE receiver_id = ? AND is_read = 0");
        $count->execute([$userId]);
        echo json_encode(['ok' => true, 'count' => (int)$count->fetchColumn()]); exit();
    }

    if ($action === 'recipients') {
        $recipients = [];

        // Helper: check if a column exists in a table
        $hasStatus = false;
        try {
            $chk = $pdo->query("SHOW COLUMNS FROM furn_users LIKE 'status'");
            $hasStatus = ($chk->rowCount() > 0);
        } catch (PDOException $e) {}
        $activeFilter = $hasStatus ? "AND status='active'" : "";

        $hasTasksTable = false;
        try {
            $chk2 = $pdo->query("SHOW TABLES LIKE 'furn_production_tasks'");
            $hasTasksTable = ($chk2->rowCount() > 0);
        } catch (PDOException $e) {}

        try {
            if ($role === 'admin') {
                // Admin → all managers
                $st = $pdo->query("SELECT id, CONCAT(first_name,' ',last_name) as name, role
                    FROM furn_users WHERE role='manager' $activeFilter ORDER BY first_name");
                $recipients = $st->fetchAll(PDO::FETCH_ASSOC);

            } elseif ($role === 'manager') {
                // Manager → admins + employees + all customers
                $st = $pdo->query("SELECT id, CONCAT(first_name,' ',last_name) as name, role
                    FROM furn_users
                    WHERE role IN ('admin','employee','customer') AND id != $userId $activeFilter
                    ORDER BY FIELD(role,'admin','employee','customer'), first_name");
                $recipients = $st->fetchAll(PDO::FETCH_ASSOC);

            } elseif ($role === 'employee') {
                // Employee → all managers
                $st = $pdo->query("SELECT id, CONCAT(first_name,' ',last_name) as name, role
                    FROM furn_users WHERE role='manager' $activeFilter ORDER BY first_name");
                $recipients = $st->fetchAll(PDO::FETCH_ASSOC);

                // + customers whose orders have tasks assigned to this employee
                if ($hasTasksTable) {
                    $st2 = $pdo->prepare("
                        SELECT DISTINCT u.id, CONCAT(u.first_name,' ',u.last_name) as name, u.role
                        FROM furn_production_tasks pt
                        JOIN furn_orders o ON o.id = pt.order_id
                        JOIN furn_users u ON u.id = o.customer_id
                        WHERE pt.employee_id = ?
                        ORDER BY u.first_name
                    ");
                    $st2->execute([$userId]);
                    $customers = $st2->fetchAll(PDO::FETCH_ASSOC);
                    // Deduplicate by id
                    $seen = array_column($recipients, 'id');
                    foreach ($customers as $c) {
                        if (!in_array($c['id'], $seen)) { $recipients[] = $c; $seen[] = $c['id']; }
                    }
                }

            } elseif ($role === 'customer') {
                // Customer → all managers
                $st = $pdo->query("SELECT id, CONCAT(first_name,' ',last_name) as name, role
                    FROM furn_users WHERE role='manager' $activeFilter ORDER BY first_name");
                $recipients = $st->fetchAll(PDO::FETCH_ASSOC);

                // + employees assigned to this customer's orders
                if ($hasTasksTable) {
                    $st2 = $pdo->prepare("
                        SELECT DISTINCT u.id, CONCAT(u.first_name,' ',u.last_name) as name, u.role
                        FROM furn_production_tasks pt
                        JOIN furn_orders o ON o.id = pt.order_id
                        JOIN furn_users u ON u.id = pt.employee_id
                        WHERE o.customer_id = ?
                        ORDER BY u.first_name
                    ");
                    $st2->execute([$userId]);
                    $employees = $st2->fetchAll(PDO::FETCH_ASSOC);
                    $seen = array_column($recipients, 'id');
                    foreach ($employees as $e) {
                        if (!in_array($e['id'], $seen)) { $recipients[] = $e; $seen[] = $e['id']; }
                    }
                }
            }
        } catch (PDOException $e) {
            echo json_encode(['ok' => false, 'error' => 'DB error: ' . $e->getMessage()]); exit();
        }

        echo json_encode(['ok' => true, 'data' => $recipients]); exit();
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action']); exit();
}

// ── POST requests ─────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action = $body['action'] ?? '';

    // CSRF check
    $token = $body['csrf_token'] ?? '';
    if (!$token || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']); exit();
    }

    if ($action === 'send' || $action === 'reply') {
        $to      = (int)($body['receiver_id'] ?? 0);
        $subject = trim($body['subject'] ?? '');
        $msg     = trim($body['message'] ?? '');
        if (!$to || !$subject || !$msg) {
            echo json_encode(['ok' => false, 'error' => 'All fields required']); exit();
        }
        try {
            $pdo->prepare("INSERT INTO furn_messages (sender_id,receiver_id,subject,message) VALUES (?,?,?,?)")
                ->execute([$userId, $to, $subject, $msg]);
            $newId = $pdo->lastInsertId();
            // If reply, mark original as read
            if ($action === 'reply' && !empty($body['original_id'])) {
                $pdo->prepare("UPDATE furn_messages SET is_read=1, read_at=NOW() WHERE id=? AND receiver_id=?")
                    ->execute([(int)$body['original_id'], $userId]);
            }
            // Notify recipient
            require_once __DIR__ . '/../../app/includes/notification_helper.php';
            $senderName = $_SESSION['user_name'] ?? $_SESSION['first_name'] ?? 'Someone';
            $recipientRole = $pdo->prepare("SELECT role FROM furn_users WHERE id=?");
            $recipientRole->execute([$to]); $rRole = $recipientRole->fetchColumn() ?: 'customer';
            $msgLink = '/' . $rRole . '/messages';
            insertNotification($pdo, $to, 'message', 'New Message from ' . $senderName,
                $subject, $newId, $msgLink, 'normal');
            echo json_encode(['ok' => true, 'id' => $newId]); exit();
        } catch (PDOException $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]); exit();
        }
    }

    if ($action === 'mark_read') {
        $id = (int)($body['id'] ?? 0);
        $pdo->prepare("UPDATE furn_messages SET is_read=1, read_at=NOW() WHERE id=? AND receiver_id=?")
            ->execute([$id, $userId]);
        echo json_encode(['ok' => true]); exit();
    }

    if ($action === 'delete') {
        $id = (int)($body['id'] ?? 0);
        $pdo->prepare("DELETE FROM furn_messages WHERE id=? AND (sender_id=? OR receiver_id=?)")
            ->execute([$id, $userId, $userId]);
        echo json_encode(['ok' => true]); exit();
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action']); exit();
}

echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
