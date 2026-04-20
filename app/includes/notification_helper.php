<?php
/**
 * Shared Notification Helper
 * Ensures furn_notifications table has required columns and provides shared functions
 */

// Ensure table exists with all required columns
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS furn_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type VARCHAR(50) NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT,
        related_id INT DEFAULT NULL,
        link VARCHAR(255) DEFAULT NULL,
        is_read TINYINT(1) DEFAULT 0,
        read_at TIMESTAMP NULL DEFAULT NULL,
        priority ENUM('low','normal','high') DEFAULT 'normal',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_is_read (is_read),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Add missing columns to existing table
    try { $pdo->exec("ALTER TABLE furn_notifications ADD COLUMN IF NOT EXISTS read_at TIMESTAMP NULL DEFAULT NULL"); } catch(PDOException $e2){}
    try { $pdo->exec("ALTER TABLE furn_notifications ADD COLUMN IF NOT EXISTS priority ENUM('low','normal','high') DEFAULT 'normal'"); } catch(PDOException $e2){}

    // Auto-cleanup: delete read notifications older than 30 days
    // Unread notifications are kept forever until the user reads them
    try { $pdo->exec("DELETE FROM furn_notifications WHERE is_read = 1 AND read_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"); } catch(PDOException $e2){}

    // Fix any old underscore-style links stored in DB → convert to hyphen-style
    $linkFixes = [
        '/manager/cost_estimation'   => '/manager/cost-estimation',
        '/manager/assign_employees'  => '/manager/assign-employees',
        '/manager/completed_tasks'   => '/manager/completed-tasks',
        '/manager/material_report'   => '/manager/material-report',
        '/manager/profit_report'     => '/manager/profit-report',
        '/manager/payroll_details'   => '/manager/payroll-details',
        '/manager/create_payroll'    => '/manager/create-payroll',
        '/manager/manage_products'   => '/manager/manage-products',
        '/customer/my_orders'        => '/customer/my-orders',
        '/customer/order_details'    => '/customer/order-details',
        '/customer/order_tracking'   => '/customer/order-tracking',
        '/customer/create_order'     => '/customer/create-order',
        '/customer/pay_deposit'      => '/customer/pay-deposit',
        '/customer/pay_remaining'    => '/customer/pay-remaining',
        '/employee/feedback_detail'  => '/employee/feedback-detail',
        '/employee/submit_report'    => '/employee/submit-report',
        '/admin/profit_report'       => '/admin/profit-report',
        '/admin/submit_report'       => '/admin/submit-report',
    ];
    $fixStmt = $pdo->prepare("UPDATE furn_notifications SET link = ? WHERE link = ?");
    foreach ($linkFixes as $old => $new) {
        $fixStmt->execute([$new, $old]);
    }
} catch (PDOException $e) {}

/**
 * Insert a notification safely (no duplicates within 1 hour for same user+type+related_id)
 */
function insertNotification($pdo, $userId, $type, $title, $message, $relatedId = null, $link = null, $priority = 'normal') {
    try {
        // Prevent duplicate spam: skip if same notification inserted in last 1 hour
        if ($relatedId) {
            $chk = $pdo->prepare("SELECT id FROM furn_notifications WHERE user_id=? AND type=? AND related_id=? AND title=? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR) LIMIT 1");
            $chk->execute([$userId, $type, $relatedId, $title]);
            if ($chk->fetch()) return false; // duplicate
        }
        $pdo->prepare("INSERT INTO furn_notifications (user_id, type, title, message, related_id, link, priority, created_at) VALUES (?,?,?,?,?,?,?,NOW())")
            ->execute([$userId, $type, $title, $message, $relatedId, $link, $priority]);
        return true;
    } catch (PDOException $e) {
        error_log("insertNotification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Insert notification for all users of a given role
 */
function notifyRole($pdo, $role, $type, $title, $message, $relatedId = null, $link = null, $priority = 'normal') {
    try {
        $users = $pdo->prepare("SELECT id FROM furn_users WHERE role = ? AND (is_active IS NULL OR is_active = 1)");
        $users->execute([$role]);
        foreach ($users->fetchAll(PDO::FETCH_COLUMN) as $uid) {
            insertNotification($pdo, $uid, $type, $title, $message, $relatedId, $link, $priority);
        }
    } catch (PDOException $e) {
        error_log("notifyRole error: " . $e->getMessage());
    }
}

/**
 * Get time-ago string
 */
function timeAgo($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)    return 'just now';
    if ($diff < 3600)  return floor($diff/60) . 'm ago';
    if ($diff < 86400) return floor($diff/3600) . 'h ago';
    if ($diff < 604800) return floor($diff/86400) . 'd ago';
    return date('M d', strtotime($datetime));
}

/**
 * Get icon and color for notification type
 */
function notifIcon($type) {
    $map = [
        'order'      => ['fa-shopping-cart', '#3498db'],
        'payment'    => ['fa-credit-card',   '#27ae60'],
        'production' => ['fa-hammer',        '#e67e22'],
        'material'   => ['fa-boxes',         '#f39c12'],
        'complaint'  => ['fa-exclamation-circle', '#e74c3c'],
        'message'    => ['fa-envelope',      '#9b59b6'],
        'contact'    => ['fa-envelope-open', '#e67e22'],
        'payroll'    => ['fa-money-bill-wave','#1abc9c'],
        'rating'     => ['fa-star',          '#f39c12'],
        'system'     => ['fa-cog',           '#7f8c8d'],
    ];
    return $map[$type] ?? ['fa-bell', '#3498db'];
}
