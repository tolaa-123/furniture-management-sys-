<?php
session_start();
require_once '../../config/config.php';
require_once '../../config/db_config.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    // CSRF validation
    $csrfToken = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION[CSRF_TOKEN_NAME] ?? '';
    if (!$csrfToken || !$sessionToken || !hash_equals($sessionToken, $csrfToken)) {
        throw new Exception('Invalid CSRF token');
    }

    $managerId = $_SESSION['user_id'];
    
    // Get form data
    $orderId = intval($_POST['order_id'] ?? 0);
    $estimatedCost = floatval($_POST['estimated_cost'] ?? 0);
    $depositAmount = floatval($_POST['deposit_amount'] ?? 0);
    $estimatedProductionDays = intval($_POST['estimated_production_days'] ?? 0);
    $managerNotes = $_POST['manager_notes'] ?? '';
    
    // Validation
    if (!$orderId) {
        throw new Exception('Order ID is required');
    }
    
    if ($estimatedCost <= 0) {
        throw new Exception('Estimated cost must be greater than 0');
    }
    
    if ($estimatedProductionDays <= 0) {
        throw new Exception('Estimated production days must be greater than 0');
    }
    
    // Verify deposit amount is 40% of estimated cost
    $calculatedDeposit = $estimatedCost * 0.40;
    if (abs($depositAmount - $calculatedDeposit) > 0.01) {
        $depositAmount = $calculatedDeposit; // Recalculate to ensure accuracy
    }
    
    // Ensure required columns exist in furn_orders table (before transaction)
    try {
        $pdo->exec("ALTER TABLE furn_orders 
            ADD COLUMN IF NOT EXISTS estimated_cost DECIMAL(12,2) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS deposit_amount DECIMAL(12,2) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS estimated_production_days INT DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS manager_notes TEXT DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS reviewed_by INT DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS reviewed_at DATETIME DEFAULT NULL");
    } catch (PDOException $e) {
        // Columns might already exist
        error_log("ALTER TABLE warning: " . $e->getMessage());
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Update order with cost estimation
    $stmt = $pdo->prepare("
        UPDATE furn_orders 
        SET estimated_cost = ?,
            deposit_amount = ?,
            estimated_production_days = ?,
            manager_notes = ?,
            reviewed_by = ?,
            reviewed_at = NOW(),
            status = 'cost_estimated'
        WHERE id = ? AND (status IN ('pending_review', 'pending_cost_approval', 'pending') OR status IS NULL OR status = '')
    ");
    
    $updated = $stmt->execute([
        $estimatedCost,
        $depositAmount,
        $estimatedProductionDays,
        $managerNotes,
        $managerId,
        $orderId
    ]);
    
    if (!$updated || $stmt->rowCount() === 0) {
        throw new Exception('Order not found or already processed');
    }
    
    // Get order details for notification
    $stmt = $pdo->prepare("SELECT customer_id, order_number FROM furn_orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Order not found');
    }
    
    // Create notification for customer
    try {
        $managerName = $_SESSION['user_name'] ?? 'Manager';
        
        $stmtNotif = $pdo->prepare("
            INSERT INTO furn_notifications (user_id, type, title, message, related_id, link, created_at)
            VALUES (?, 'order', 'Cost Estimation Ready', ?, ?, '/customer/my-orders', NOW())
        ");
        
        $notifMessage = "Your order {$order['order_number']} has been reviewed. Estimated cost: $" . number_format($estimatedCost, 2) . ". Deposit required: $" . number_format($depositAmount, 2);
        
        $stmtNotif->execute([
            $order['customer_id'],
            $notifMessage,
            $orderId
        ]);
        
        // Send SMS to customer
        try {
            require_once '../../app/services/SmsService.php';
            $smsService = new SmsService(); // Uses SMS_MODE constant from db_config.php
            
            $stmtPhone = $pdo->prepare("SELECT phone, first_name FROM furn_users WHERE id = ?");
            $stmtPhone->execute([$order['customer_id']]);
            $customer = $stmtPhone->fetch(PDO::FETCH_ASSOC);
            
            if ($customer && $customer['phone']) {
                $smsService->sendOrderNotification($customer['phone'], $orderId, 'cost_estimated', $customer['first_name']);
            }
        } catch (Exception $e) {
            error_log("SMS error: " . $e->getMessage());
        }
        
    } catch (Exception $e) {
        // Notification failed but continue
        error_log("Notification error: " . $e->getMessage());
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Cost estimation submitted successfully',
        'order_id' => $orderId,
        'estimated_cost' => $estimatedCost,
        'deposit_amount' => $depositAmount
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Cost estimation error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}