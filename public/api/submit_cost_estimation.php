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

    // Fetch order's budget_range and validate estimated cost against it
    $stmtOrder = $pdo->prepare("SELECT budget_range FROM furn_orders WHERE id = ?");
    $stmtOrder->execute([$orderId]);
    $orderRow = $stmtOrder->fetch(PDO::FETCH_ASSOC);

    if ($orderRow && !empty($orderRow['budget_range'])) {
        $budgetRange = $orderRow['budget_range'];
        $maxBudget = null;

        if ($budgetRange === 'Under ETB 5,000') {
            $maxBudget = 5000;
        } elseif ($budgetRange === 'ETB 5,000 - ETB 10,000') {
            $maxBudget = 10000;
        } elseif ($budgetRange === 'ETB 10,000 - ETB 20,000') {
            $maxBudget = 20000;
        }
        // 'Above ETB 20,000' has no upper limit

        if ($maxBudget !== null && $estimatedCost > $maxBudget) {
            throw new Exception("Cost exceeds customer's budget limit of ETB " . number_format($maxBudget, 2) . ".");
        }
    }
    
    // Get deposit percentage from settings (default 40%)
    $depositPercentage = 40;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM furn_settings WHERE setting_key = 'default_deposit_percentage' LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetchColumn();
        if ($result !== false && floatval($result) > 0) {
            $depositPercentage = floatval($result);
        }
    } catch (PDOException $e) {
        error_log("Error fetching deposit percentage: " . $e->getMessage());
    }
    
    // Verify deposit amount matches configured percentage
    $calculatedDeposit = $estimatedCost * ($depositPercentage / 100);
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
    
    $pdo->commit();

    // Notify customer AFTER commit (outside transaction)
    try {
        require_once __DIR__ . '/../../app/includes/notification_helper.php';
        $notifMessage = "Your order {$order['order_number']} has been reviewed. Estimated cost: ETB " . number_format($estimatedCost, 2) . ". Deposit required: ETB " . number_format($depositAmount, 2);
        insertNotification($pdo, $order['customer_id'], 'payment', 'Cost Estimation Ready — Pay Deposit',
            $notifMessage, $orderId, '/customer/pay-deposit', 'high');
    } catch (Exception $e) {
        error_log("Notification error: " . $e->getMessage());
    }
    
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