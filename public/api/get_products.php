<?php
/**
 * API: Get Products for Gallery
 * Handles fetching products with filters, search, and pagination
 */

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../config/db_config.php';

try {
    // Get parameters
    $category = $_GET['category'] ?? '';
    $search = $_GET['search'] ?? '';
    $material = $_GET['material'] ?? '';
    $minPrice = $_GET['min_price'] ?? 0;
    $maxPrice = $_GET['max_price'] ?? 999999;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $perPage = 12;
    $offset = ($page - 1) * $perPage;
    
    // Build query
    $sql = "SELECT 
                product_id,
                product_name,
                category,
                material,
                dimensions,
                description,
                estimated_price,
                image_main,
                created_at
            FROM furn_products 
            WHERE status = 'active'";
    
    $params = [];
    
    // Add filters
    if (!empty($category)) {
        $sql .= " AND category = ?";
        $params[] = $category;
    }
    
    if (!empty($search)) {
        $sql .= " AND (product_name LIKE ? OR description LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    if (!empty($material)) {
        $sql .= " AND material LIKE ?";
        $params[] = "%$material%";
    }
    
    if ($minPrice > 0) {
        $sql .= " AND estimated_price >= ?";
        $params[] = $minPrice;
    }
    
    if ($maxPrice < 999999) {
        $sql .= " AND estimated_price <= ?";
        $params[] = $maxPrice;
    }
    
    // Get total count
    $countSql = str_replace('SELECT product_id, product_name, category, material, dimensions, description, estimated_price, image_main, created_at', 'SELECT COUNT(*)', $sql);
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalProducts = $countStmt->fetchColumn();
    
    // Add pagination
    $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;
    
    // Execute query
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate pagination
    $totalPages = ceil($totalProducts / $perPage);
    
    // Return response
    echo json_encode([
        'success' => true,
        'products' => $products,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_products' => $totalProducts,
            'per_page' => $perPage
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
