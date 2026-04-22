<?php
/**
 * Add/Remove Product from Wishlist
 * API Endpoint for wishlist functionality
 */

session_start();
require_once __DIR__ . '/../../config/db_config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

$customerId = $_SESSION['user_id'];
$productId = $_POST['product_id'] ?? null;

if (!$productId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Product ID is required']);
    exit;
}

try {
    // Check if already in wishlist (don't require product to exist for removal)
    $wishlistStmt = $pdo->prepare("SELECT id FROM furn_wishlist WHERE customer_id = ? AND product_id = ?");
    $wishlistStmt->execute([$customerId, $productId]);
    $existing = $wishlistStmt->fetch();
    
    if ($existing) {
        // Remove from wishlist — always allow removal even if product deleted
        $deleteStmt = $pdo->prepare("DELETE FROM furn_wishlist WHERE customer_id = ? AND product_id = ?");
        $deleteStmt->execute([$customerId, $productId]);
        echo json_encode(['success' => true, 'action' => 'removed', 'message' => 'Removed from wishlist']);
    } else {
        // Add to wishlist — only allow if product exists and is active
        $checkStmt = $pdo->prepare("SELECT id FROM furn_products WHERE id = ? AND is_active = 1");
        $checkStmt->execute([$productId]);
        if (!$checkStmt->fetch()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Product not found']);
            exit;
        }
        $insertStmt = $pdo->prepare("INSERT INTO furn_wishlist (customer_id, product_id) VALUES (?, ?)");
        $insertStmt->execute([$customerId, $productId]);
        echo json_encode(['success' => true, 'action' => 'added', 'message' => 'Added to wishlist']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
