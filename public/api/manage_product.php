<?php
/**
 * API: Manage Products (Add, Edit, Delete)
 * Manager-only endpoint for product CRUD operations
 */

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/db_config.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized access'
    ]);
    exit;
}

try {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add':
            // Add new product
            $sql = "INSERT INTO furn_products 
                    (product_name, category, material, dimensions, description, estimated_price, created_by, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'active')";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['product_name'],
                $_POST['category'],
                $_POST['material'],
                $_POST['dimensions'],
                $_POST['description'],
                $_POST['estimated_price'],
                $_SESSION['user_id']
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Product added successfully',
                'product_id' => $pdo->lastInsertId()
            ]);
            break;
            
        case 'edit':
            // Update existing product
            $sql = "UPDATE furn_products 
                    SET product_name = ?,
                        category = ?,
                        material = ?,
                        dimensions = ?,
                        description = ?,
                        estimated_price = ?,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE product_id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $_POST['product_name'],
                $_POST['category'],
                $_POST['material'],
                $_POST['dimensions'],
                $_POST['description'],
                $_POST['estimated_price'],
                $_POST['product_id']
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Product updated successfully'
            ]);
            break;
            
        case 'delete':
            // Soft delete (set status to inactive)
            $sql = "UPDATE furn_products 
                    SET status = 'inactive',
                        updated_at = CURRENT_TIMESTAMP
                    WHERE product_id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$_POST['product_id']]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Product deleted successfully'
            ]);
            break;
            
        case 'get':
            // Get single product details
            $sql = "SELECT * FROM furn_products WHERE product_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$_GET['product_id']]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($product) {
                echo json_encode([
                    'success' => true,
                    'product' => $product
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Product not found'
                ]);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action'
            ]);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
