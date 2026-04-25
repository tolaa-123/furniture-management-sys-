<?php
session_start();
require_once '../../config/db_config.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    // CSRF Token Validation
    $csrfPost = $_POST['csrf_token'] ?? '';
    $csrfSess = $_SESSION['csrf_token'] ?? '';
    if (!$csrfPost || !$csrfSess || !hash_equals($csrfSess, $csrfPost)) {
        throw new Exception('Invalid CSRF token');
    }
    
    $customerId = $_SESSION['user_id'];
    $orderId = intval($_POST['order_id'] ?? 0);
    
    if (!$orderId) {
        throw new Exception('Invalid order ID');
    }
    
    // Fetch order to verify ownership and status
    $stmt = $pdo->prepare("SELECT * FROM furn_orders WHERE id = ? AND customer_id = ?");
    $stmt->execute([$orderId, $customerId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Order not found or you do not have permission to edit it');
    }
    
    // Check if order can be edited
    $editableStatuses = ['pending_review', 'pending_cost_approval'];
    if (!in_array($order['status'], $editableStatuses)) {
        throw new Exception('This order cannot be edited anymore. It has already been processed.');
    }
    
    // Get form data
    $furnitureType = $_POST['furniture_type'] ?? '';
    $color = $_POST['color'] ?? '';
    $length = floatval($_POST['length'] ?? 0);
    $width = floatval($_POST['width'] ?? 0);
    $height = floatval($_POST['height'] ?? 0);
    $designDescription = $_POST['design_description'] ?? '';
    
    // NEW ERP FIELDS
    $quantity = intval($_POST['quantity'] ?? 1);
    $budgetRange = $_POST['budget_range'] ?? '';
    $preferredDeliveryDate = $_POST['preferred_delivery_date'] ?? null;
    
    // Validation
    if (!$furnitureType || !$color || !$designDescription) {
        throw new Exception('All required fields must be filled');
    }
    
    if ($length <= 0 || $width <= 0 || $height <= 0) {
        throw new Exception('Invalid dimensions - all must be greater than 0');
    }
    
    if ($quantity < 1) {
        throw new Exception('Quantity must be at least 1');
    }
    
    if (!$budgetRange) {
        throw new Exception('Budget range is required');
    }
    
    // Validate delivery date (must be at least 7 days from now)
    if ($preferredDeliveryDate) {
        $minDate = date('Y-m-d', strtotime('+7 days'));
        if ($preferredDeliveryDate < $minDate) {
            throw new Exception('Preferred delivery date must be at least 7 days from today');
        }
    }
    
    // Handle file upload with security
    $designImage = null;
    $updateImage = false;
    
    if (isset($_FILES['design_image']) && $_FILES['design_image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $filename = $_FILES['design_image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // Validate file type
        if (!in_array($ext, $allowed)) {
            throw new Exception('Invalid file type. Allowed: JPG, JPEG, PNG, PDF');
        }
        
        // Validate file size (max 5MB)
        if ($_FILES['design_image']['size'] > 5 * 1024 * 1024) {
            throw new Exception('File too large. Maximum size: 5MB');
        }
        
        // Create upload directory
        $uploadDir = __DIR__ . '/../uploads/designs/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate secure filename
        $newFilename = preg_replace('/[^a-zA-Z0-9]/', '_', $order['order_number']) . '_edit_' . time() . '.' . $ext;
        $uploadPath = $uploadDir . $newFilename;
        
        if (!move_uploaded_file($_FILES['design_image']['tmp_name'], $uploadPath)) {
            throw new Exception('File upload failed');
        }
        
        $designImage = 'uploads/designs/' . $newFilename;
        $updateImage = true;
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Update order
    if ($updateImage && $designImage) {
        // Update with new image
        $stmt = $pdo->prepare("
            UPDATE furn_orders SET
                furniture_type = ?,
                color = ?,
                length = ?,
                width = ?,
                height = ?,
                quantity = ?,
                budget_range = ?,
                preferred_delivery_date = ?,
                design_description = ?,
                design_image = ?,
                updated_at = NOW()
            WHERE id = ? AND customer_id = ?
        ");
        
        $stmt->execute([
            $furnitureType, $color, $length, $width, $height,
            $quantity, $budgetRange, $preferredDeliveryDate,
            $designDescription, $designImage,
            $orderId, $customerId
        ]);
    } else {
        // Update without changing image
        $stmt = $pdo->prepare("
            UPDATE furn_orders SET
                furniture_type = ?,
                color = ?,
                length = ?,
                width = ?,
                height = ?,
                quantity = ?,
                budget_range = ?,
                preferred_delivery_date = ?,
                design_description = ?,
                updated_at = NOW()
            WHERE id = ? AND customer_id = ?
        ");
        
        $stmt->execute([
            $furnitureType, $color, $length, $width, $height,
            $quantity, $budgetRange, $preferredDeliveryDate,
            $designDescription,
            $orderId, $customerId
        ]);
    }
    
    // If order was in pending_cost_approval, reset to pending_review so manager re-reviews
    if ($order['status'] === 'pending_cost_approval') {
        $stmt = $pdo->prepare("UPDATE furn_orders SET status = 'pending_review' WHERE id = ?");
        $stmt->execute([$orderId]);
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Create notification for manager about the edit
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
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_is_read (is_read)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $customerName = $_SESSION['first_name'] ?? $_SESSION['user_name'] ?? 'Customer';
        
        $stmtNotif = $pdo->prepare("
            INSERT INTO furn_notifications (user_id, type, title, message, related_id, link, created_at)
            SELECT id, 'order_edit', 'Order Edited by Customer', 
                   CONCAT('Order ', ?, ' has been edited by ', ?), 
                   ?, 
                   '/manager/cost_estimation',
                   NOW()
            FROM furn_users 
            WHERE role IN ('manager','admin')
        ");
        $stmtNotif->execute([$order['order_number'], $customerName, $orderId]);
        
    } catch (Exception $e) {
        error_log("Notification error: " . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Order updated successfully! Manager will review your changes.',
        'order_id' => $orderId,
        'order_number' => $order['order_number']
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log error for debugging
    error_log("Order update error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
