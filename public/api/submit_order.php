<?php
/**
 * Submit Order API
 * Creates new order with items
 */

header('Content-Type: application/json');
session_start();

require_once '../../config/config.php';
require_once '../../core/Database.php';
require_once '../../core/SecurityUtil.php';
require_once '../../app/includes/order_functions.php';
require_once '../../app/utils/ErrorLogger.php';
require_once '../../app/utils/Validator.php';

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        ErrorLogger::logWarning('Unauthorized order submission attempt', [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);
        http_response_code(401);
        throw new Exception('Please login to place an order');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data');
    }
    
    // Validate order data
    $validator = new Validator();
    $rules = [
        'items' => ['required'],
        'delivery_address' => ['required', 'min:5', 'max:500'],
        'payment_method' => ['required', 'in:cash,bank,online'],
    ];
    
    if (!$validator->validate($input, $rules)) {
        ErrorLogger::logWarning('Order validation failed', [
            'user_id' => $_SESSION['user_id'],
            'errors' => $validator->getErrors(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);
        throw new Exception('Validation failed: ' . implode(', ', $validator->getErrors()));
    }
    
    // Validate order data
    $errors = validateOrderData($input);
    if (!empty($errors)) {
        ErrorLogger::logWarning('Order data validation failed', [
            'user_id' => $_SESSION['user_id'],
            'errors' => $errors,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);
        throw new Exception(implode(', ', $errors));
    }
    
    // Sanitize input
    $sanitized = sanitizeOrderInput($input);
    
    $db = Database::getInstance()->getConnection();
    $db->beginTransaction();
    
    // Generate order number
    $orderNumber = generateOrderNumber();
    
    // Calculate totals
    $total = 0;
    $validItems = [];
    
    foreach ($input['items'] as $item) {
        $productId = intval($item['product_id']);
        $quantity = intval($item['quantity']);
        
        if ($quantity < 1) continue;
        
        $stmt = $db->prepare("SELECT id, name, base_price, is_active FROM furn_products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product && $product['is_active']) {
            $itemTotal = $product['base_price'] * $quantity;
            $total += $itemTotal;
            
            $validItems[] = [
                'product_id' => $product['id'],
                'name' => $product['name'],
                'quantity' => $quantity,
                'unit_price' => $product['base_price'],
                'total_price' => $itemTotal
            ];
        }
    }
    
    if (empty($validItems)) {
        throw new Exception('No valid items in cart');
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
    
    $depositAmount = $total * ($depositPercentage / 100);
    $balanceAmount = $total * ((100 - $depositPercentage) / 100);
    
    // Insert order
    $stmt = $db->prepare("
        INSERT INTO furn_orders 
        (customer_id, order_number, status, total_amount, deposit_amount, remaining_balance, special_instructions, created_at)
        VALUES (?, ?, 'cost_estimated', ?, ?, ?, ?, NOW())
    ");
    
    $specialInstructions = json_encode([
        'delivery_address' => $sanitized['delivery_address'],
        'alternate_phone' => $sanitized['alternate_phone'],
        'order_notes' => $sanitized['order_notes'],
        'preferred_delivery_date' => $sanitized['preferred_delivery_date'],
        'payment_method' => $sanitized['payment_method']
    ]);
    
    $stmt->execute([
        $_SESSION['user_id'],
        $orderNumber,
        $total,
        $depositAmount,
        $balanceAmount,
        $specialInstructions
    ]);
    
    $orderId = $db->lastInsertId();
    
    // Insert order items
    $stmt = $db->prepare("
        INSERT INTO furn_order_customizations 
        (order_id, product_id, quantity, base_price, adjusted_price, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    foreach ($validItems as $item) {
        $stmt->execute([
            $orderId,
            $item['product_id'],
            $item['quantity'],
            $item['unit_price'],
            $item['total_price']
        ]);
    }
    
    // Log order creation
    logOrderActivity($orderId, 'order_created', 'Order created with ' . count($validItems) . ' items');
    
    $db->commit();
    
    // Log successful order creation
    ErrorLogger::logInfo('Order created successfully', [
        'order_id' => $orderId,
        'order_number' => $orderNumber,
        'customer_id' => $_SESSION['user_id'],
        'total_amount' => $total,
        'item_count' => count($validItems),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    ]);
    
    // Send notification (if implemented)
    sendOrderNotification($orderId, 'created');
    
    echo json_encode([
        'success' => true,
        'message' => 'Order placed successfully!',
        'order_id' => intval($orderId),
        'order_number' => $orderNumber,
        'total_amount' => round($total, 2),
        'deposit_amount' => round($depositAmount, 2),
        'balance_amount' => round($balanceAmount, 2),
        'payment_method' => $sanitized['payment_method']
    ]);
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    ErrorLogger::logError('Order submission failed', 400, [
        'user_id' => $_SESSION['user_id'] ?? 'unknown',
        'message' => $e->getMessage(),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    ]);
    
    $statusCode = http_response_code();
    if ($statusCode === 200) {
        http_response_code(400);
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
