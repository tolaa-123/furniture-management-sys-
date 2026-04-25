<?php
session_start();
require_once '../../config/db_config.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
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
    
    $managerId = $_SESSION['user_id'];
    $orderId = intval($_POST['order_id'] ?? 0);
    
    if (!$orderId) {
        throw new Exception('Invalid order ID');
    }
    
    // Fetch order to verify it exists
    $stmt = $pdo->prepare("SELECT * FROM furn_orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Order not found');
    }
    
    // Check if order can be edited by manager
    $editableStatuses = ['pending_review', 'pending_cost_approval', 'cost_estimated', 'waiting_for_deposit'];
    if (!in_array($order['status'], $editableStatuses)) {
        throw new Exception('This order cannot be edited. It has already progressed beyond editing stage.');
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
        $newFilename = preg_replace('/[^a-zA-Z0-9]/', '_', $order['order_number']) . '_manager_edit_' . time() . '.' . $ext;
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
            WHERE id = ?
        ");
        
        $stmt->execute([
            $furnitureType, $color, $length, $width, $height,
            $quantity, $budgetRange, $preferredDeliveryDate,
            $designDescription, $designImage,
            $orderId
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
            WHERE id = ?
        ");
        
        $stmt->execute([
            $furnitureType, $color, $length, $width, $height,
            $quantity, $budgetRange, $preferredDeliveryDate,
            $designDescription,
            $orderId
        ]);
    }
    
    // If cost was already estimated, reset to pending_review so manager must re-estimate
    if (in_array($order['status'], ['cost_estimated', 'waiting_for_deposit'])) {
        $stmt = $pdo->prepare("UPDATE furn_orders SET status = 'pending_review', estimated_cost = NULL WHERE id = ?");
        $stmt->execute([$orderId]);
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Log the edit action (if audit log table exists)
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS furn_audit_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            action VARCHAR(100) NOT NULL,
            table_name VARCHAR(100) DEFAULT NULL,
            record_id INT DEFAULT NULL,
            old_values TEXT DEFAULT NULL,
            new_values TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_action (action)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $oldValues = json_encode([
            'furniture_type' => $order['furniture_type'],
            'color' => $order['color'],
            'length' => $order['length'],
            'width' => $order['width'],
            'height' => $order['height'],
            'quantity' => $order['quantity'],
            'budget_range' => $order['budget_range'],
            'design_description' => $order['design_description']
        ]);
        
        $newValues = json_encode([
            'furniture_type' => $furnitureType,
            'color' => $color,
            'length' => $length,
            'width' => $width,
            'height' => $height,
            'quantity' => $quantity,
            'budget_range' => $budgetRange,
            'design_description' => $designDescription
        ]);
        
        $stmtLog = $pdo->prepare("
            INSERT INTO furn_audit_logs (user_id, action, table_name, record_id, old_values, new_values)
            VALUES (?, 'manager_order_edit', 'furn_orders', ?, ?, ?)
        ");
        $stmtLog->execute([$managerId, $orderId, $oldValues, $newValues]);
        
    } catch (Exception $e) {
        error_log("Audit log error: " . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Order updated successfully by manager.',
        'order_id' => $orderId,
        'order_number' => $order['order_number']
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log error for debugging
    error_log("Manager order update error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
