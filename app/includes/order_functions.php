<?php
/**
 * Order Helper Functions
 * Utility functions for order processing
 */

/**
 * Calculate order totals
 */
function calculateOrderTotals($items) {
    $subtotal = 0;
    
    foreach ($items as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    
    $deposit = $subtotal * 0.40;
    $balance = $subtotal * 0.60;
    
    return [
        'subtotal' => round($subtotal, 2),
        'deposit' => round($deposit, 2),
        'balance' => round($balance, 2)
    ];
}

/**
 * Validate order data
 */
function validateOrderData($data) {
    $errors = [];
    
    if (empty($data['items']) || !is_array($data['items'])) {
        $errors[] = 'Cart is empty';
    }
    
    if (empty($data['delivery_address'])) {
        $errors[] = 'Delivery address is required';
    }
    
    if (empty($data['payment_method'])) {
        $errors[] = 'Payment method is required';
    }
    
    if (!in_array($data['payment_method'], ['cash', 'bank', 'mobile'])) {
        $errors[] = 'Invalid payment method';
    }
    
    return $errors;
}

/**
 * Generate unique order number
 */
function generateOrderNumber() {
    $prefix = 'ORD';
    $year = date('Y');
    $random = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    return $prefix . '-' . $year . '-' . $random;
}

/**
 * Get order status badge
 */
function getOrderStatusBadge($status) {
    $badges = [
        'pending_cost_approval' => '<span class="badge bg-warning">Pending Approval</span>',
        'waiting_for_deposit' => '<span class="badge bg-info">Waiting for Deposit</span>',
        'deposit_paid' => '<span class="badge bg-primary">Deposit Paid</span>',
        'in_production' => '<span class="badge bg-secondary">In Production</span>',
        'ready_for_delivery' => '<span class="badge bg-success">Ready for Delivery</span>',
        'completed' => '<span class="badge bg-success">Completed</span>',
        'cancelled' => '<span class="badge bg-danger">Cancelled</span>'
    ];
    
    return $badges[$status] ?? '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
}

/**
 * Format payment method
 */
function formatPaymentMethod($method) {
    $methods = [
        'cash' => 'Cash Payment',
        'bank' => 'Bank Transfer',
        'mobile' => 'Mobile Money'
    ];
    
    return $methods[$method] ?? ucfirst($method);
}

/**
 * Get payment instructions
 */
function getPaymentInstructions($method, $orderNumber) {
    $instructions = [
        'cash' => 'Please prepare the payment in cash. Our representative will contact you to arrange collection.',
        'bank' => 'Transfer to: Commercial Bank of Ethiopia, Account: 1000123456789, Reference: ' . $orderNumber,
        'mobile' => 'Send via M-Pesa/Telebirr to: 0911-234-567, Reference: ' . $orderNumber
    ];
    
    return $instructions[$method] ?? 'Please contact us for payment instructions.';
}

/**
 * Check if order can be edited
 */
function canEditOrder($status) {
    return in_array($status, ['pending_cost_approval', 'waiting_for_deposit']);
}

/**
 * Check if order can be cancelled
 */
function canCancelOrder($status) {
    return in_array($status, ['pending_cost_approval', 'waiting_for_deposit']);
}

/**
 * Calculate days until delivery
 */
function calculateDeliveryDays($orderDate, $estimatedDays = 14) {
    $orderTimestamp = strtotime($orderDate);
    $estimatedDelivery = strtotime("+{$estimatedDays} days", $orderTimestamp);
    $today = time();
    
    $daysRemaining = ceil(($estimatedDelivery - $today) / (60 * 60 * 24));
    
    return max(0, $daysRemaining);
}

/**
 * Format currency
 */
function formatCurrency($amount) {
    return 'ETB ' . number_format($amount, 2);
}

/**
 * Sanitize order input
 */
function sanitizeOrderInput($data) {
    return [
        'delivery_address' => htmlspecialchars(trim($data['delivery_address'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'alternate_phone' => htmlspecialchars(trim($data['alternate_phone'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'order_notes' => htmlspecialchars(trim($data['order_notes'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'preferred_delivery_date' => htmlspecialchars(trim($data['preferred_delivery_date'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'payment_method' => htmlspecialchars(trim($data['payment_method'] ?? ''), ENT_QUOTES, 'UTF-8')
    ];
}

/**
 * Log order activity
 */
function logOrderActivity($orderId, $action, $details = '') {
    // Optional logging - only if table exists
    try {
        require_once dirname(__DIR__) . '/../core/Database.php';
        $db = Database::getInstance()->getConnection();
        
        // Check if table exists
        $stmt = $db->query("SHOW TABLES LIKE 'furn_order_activity_log'");
        if ($stmt->rowCount() === 0) {
            // Table doesn't exist, just log to error log
            error_log("Order activity: {$action} for order {$orderId} - {$details}");
            return true;
        }
        
        $stmt = $db->prepare("
            INSERT INTO furn_order_activity_log 
            (order_id, action, details, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$orderId, $action, $details]);
        return true;
    } catch (Exception $e) {
        error_log('Failed to log order activity: ' . $e->getMessage());
        return false;
    }
}

/**
 * Send order notification SMS or email (placeholder / helper)
 */
function sendOrderNotification($orderId, $type = 'created') {
    try {
        require_once dirname(__DIR__) . '/../core/Database.php';
        require_once dirname(__DIR__) . '/services/SmsService.php';

        $db = Database::getInstance()->getConnection();
        
        // Check if SMS notifications are enabled
        $stmt = $db->prepare("SELECT setting_value FROM furn_settings WHERE setting_key = 'sms_notifications'");
        $stmt->execute();
        $smsEnabled = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$smsEnabled || $smsEnabled['setting_value'] != '1') {
            error_log("SMS notification skipped: SMS notifications are disabled");
            return false;
        }
        
        $stmt = $db->prepare(
            "SELECT o.order_number, u.phone, u.first_name
             FROM furn_orders o
             JOIN furn_users u ON o.customer_id = u.id
             WHERE o.id = ?"
        );
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order || empty($order['phone'])) {
            error_log("SMS notification skipped: order {$orderId} has no phone number");
            return false;
        }

        $smsService = new SmsService();
        return $smsService->sendOrderNotification(
            $order['phone'],
            $orderId,
            $type,
            $order['first_name'] ?? ''
        );
    } catch (Exception $e) {
        error_log('sendOrderNotification error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get order progress percentage
 */
function getOrderProgress($status) {
    $progress = [
        'pending_cost_approval' => 10,
        'waiting_for_deposit' => 25,
        'deposit_paid' => 40,
        'in_production' => 70,
        'ready_for_delivery' => 90,
        'completed' => 100,
        'cancelled' => 0
    ];
    
    return $progress[$status] ?? 0;
}
