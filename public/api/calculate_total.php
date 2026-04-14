<?php
/**
 * Calculate Order Total API
 * Calculates total, deposit (40%), and remaining balance
 */

header('Content-Type: application/json');
session_start();

require_once '../../config/config.php';
require_once '../../core/Database.php';
require_once '../../core/SecurityUtil.php';

try {
    // Get cart items from request
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['items']) || !is_array($input['items'])) {
        throw new Exception('Invalid cart items');
    }
    
    $db = Database::getInstance()->getConnection();
    $total = 0;
    $itemsDetails = [];
    
    foreach ($input['items'] as $item) {
        $productId = intval($item['product_id']);
        $quantity = intval($item['quantity']);
        
        if ($quantity < 1) continue;
        
        // Get product price
        $stmt = $db->prepare("SELECT id, name, base_price FROM furn_products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if ($product) {
            $subtotal = $product['base_price'] * $quantity;
            $total += $subtotal;
            
            $itemsDetails[] = [
                'product_id' => $product['id'],
                'name' => $product['name'],
                'price' => floatval($product['base_price']),
                'quantity' => $quantity,
                'subtotal' => $subtotal
            ];
        }
    }
    
    // Calculate deposit (40%) and balance (60%)
    $deposit = $total * 0.40;
    $balance = $total * 0.60;
    
    echo json_encode([
        'success' => true,
        'total' => round($total, 2),
        'deposit' => round($deposit, 2),
        'balance' => round($balance, 2),
        'items' => $itemsDetails
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
