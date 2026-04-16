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
    // Ensure required columns exist in furn_orders table (run before main transaction)
    try {
        $pdo->exec("ALTER TABLE furn_orders 
            ADD COLUMN IF NOT EXISTS furniture_type VARCHAR(100) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS furniture_name VARCHAR(255) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS length DECIMAL(10,2) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS width DECIMAL(10,2) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS height DECIMAL(10,2) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS material VARCHAR(100) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS color VARCHAR(50) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS design_description TEXT DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS design_image VARCHAR(255) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS special_notes TEXT DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS quantity INT DEFAULT 1,
            ADD COLUMN IF NOT EXISTS budget_range VARCHAR(50) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS preferred_delivery_date DATE DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS estimated_cost DECIMAL(12,2) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS total_amount DECIMAL(12,2) DEFAULT 0");
    } catch (PDOException $e) {
        // Columns might already exist, continue
        error_log("ALTER TABLE warning: " . $e->getMessage());
    }
    
    // CSRF Token Validation
    $csrfPost = $_POST['csrf_token'] ?? '';
    $csrfSess = $_SESSION['csrf_token'] ?? '';
    if (!$csrfPost || !$csrfSess || !hash_equals($csrfSess, $csrfPost)) {
        throw new Exception('Invalid CSRF token');
    }
    
    $customerId = $_SESSION['user_id'];
    
    // Get form data - Basic fields
    $orderNumber = $_POST['order_number'] ?? '';
    $furnitureType = $_POST['furniture_type'] ?? '';
    $furnitureName = $_POST['furniture_name'] ?? '';
    if (empty($furnitureName)) $furnitureName = $furnitureType; // default to type if not provided
    $length = floatval($_POST['length'] ?? 0);
    $width = floatval($_POST['width'] ?? 0);
    $height = floatval($_POST['height'] ?? 0);
    $material = $_POST['material'] ?? '';
    $color = $_POST['color'] ?? '';
    $designDescription = $_POST['design_description'] ?? '';
    $specialNotes = $_POST['special_notes'] ?? '';
    
    // NEW ERP FIELDS
    $quantity = intval($_POST['quantity'] ?? 1);
    $budgetRange = $_POST['budget_range'] ?? '';
    $preferredDeliveryDate = $_POST['preferred_delivery_date'] ?? null;
    
    // Validation - Basic fields
    if (!$furnitureType || !$material || !$color) {
        throw new Exception('Furniture type, material and color are required');
    }
    
    if ($length <= 0 || $width <= 0 || $height <= 0) {
        throw new Exception('Invalid dimensions - all must be greater than 0');
    }
    
    // NEW ERP VALIDATIONS
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
        
        // Generate secure filename: order_number + timestamp + extension
        $newFilename = preg_replace('/[^a-zA-Z0-9]/', '_', $orderNumber) . '_' . time() . '.' . $ext;
        $uploadPath = $uploadDir . $newFilename;
        
        if (!move_uploaded_file($_FILES['design_image']['tmp_name'], $uploadPath)) {
            throw new Exception('File upload failed');
        }
        
        $designImage = 'uploads/designs/' . $newFilename;
    } elseif (!empty($_POST['gallery_image_url'])) {
        $galleryUrl = $_POST['gallery_image_url'];
        $uploadDir  = __DIR__ . '/../uploads/designs/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        // Convert URL to local file path for direct copy (avoids HTTP self-request issues)
        $localPath = null;
        $baseUrl   = rtrim(BASE_URL, '/');
        if (strpos($galleryUrl, $baseUrl . '/public/') === 0) {
            $relativePath = substr($galleryUrl, strlen($baseUrl . '/public/'));
            $localPath    = __DIR__ . '/../' . ltrim($relativePath, '/');
        }

        $ext = strtolower(pathinfo($galleryUrl, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png'])) $ext = 'jpg';
        $newFilename = preg_replace('/[^a-zA-Z0-9]/', '_', $orderNumber) . '_gallery_' . time() . '.' . $ext;

        if ($localPath && file_exists($localPath)) {
            // Direct file copy — fast and reliable
            copy($localPath, $uploadDir . $newFilename);
            $designImage = 'uploads/designs/' . $newFilename;
        } else {
            // Fallback: store URL directly as reference
            $designImage = $galleryUrl;
        }
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Insert order with NEW ERP FIELDS
    $stmt = $pdo->prepare("
        INSERT INTO furn_orders (
            customer_id, order_number, furniture_type, furniture_name,
            length, width, height, 
            quantity, budget_range, preferred_delivery_date,
            material, color, design_description, design_image, special_notes,
            status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_review', NOW())
    ");
    
    $stmt->execute([
        $customerId, $orderNumber, $furnitureType, $furnitureName,
        $length, $width, $height,
        $quantity, $budgetRange, $preferredDeliveryDate,
        $material, $color, $designDescription, $designImage, $specialNotes
    ]);
    
    $orderId = $pdo->lastInsertId();
    
    // Commit transaction before creating notification (DDL causes implicit commit)
    $pdo->commit();
    
    // Create notification for manager (outside transaction)
    try {
        // Ensure notifications table exists
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
            SELECT id, 'order', 'New Order Pending Review', 
                   CONCAT('New custom furniture order from ', ?), 
                   ?, 
                   '/manager/cost-estimation',
                   NOW()
            FROM furn_users 
            WHERE role IN ('manager','admin')
        ");
        $stmtNotif->execute([$customerName, $orderId]);
        
        // Send SMS notification to customer
        try {
            // Check if SMS notifications are enabled
            $stmtSmsCheck = $pdo->prepare("SELECT setting_value FROM furn_settings WHERE setting_key = 'sms_notifications'");
            $stmtSmsCheck->execute();
            $smsEnabled = $stmtSmsCheck->fetch(PDO::FETCH_ASSOC);
            
            if ($smsEnabled && $smsEnabled['setting_value'] == '1') {
                require_once '../../app/services/SmsService.php';
                $smsService = new SmsService(); // Uses SMS_MODE constant from db_config.php
                
                // Get customer phone number
                $stmtPhone = $pdo->prepare("SELECT phone FROM furn_users WHERE id = ?");
                $stmtPhone->execute([$customerId]);
                $phone = $stmtPhone->fetchColumn();
                
                if ($phone) {
                    $smsService->sendOrderNotification($phone, $orderId, 'created', $customerName);
                }
                
                // Send SMS to all managers and admins
                $stmtManager = $pdo->prepare("SELECT phone FROM furn_users WHERE role IN ('manager','admin') AND phone IS NOT NULL");
                $stmtManager->execute();
                $managerPhones = $stmtManager->fetchAll(PDO::FETCH_COLUMN);
                
                foreach ($managerPhones as $managerPhone) {
                    if ($managerPhone) {
                        $smsService->sendManagerNotification($managerPhone, 'new_order', [
                            'order_id' => $orderId,
                            'customer_name' => $customerName
                        ]);
                    }
                }
            }
        } catch (Exception $e) {
            error_log("SMS error: " . $e->getMessage());
        }
        
    } catch (Exception $e) {
        // Notifications table might not exist yet, continue anyway
        error_log("Notification error: " . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Order submitted successfully! Manager will review and provide cost estimation.',
        'order_id' => $orderId,
        'order_number' => $orderNumber,
        'status' => 'pending_review'
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log error for debugging
    error_log("Order submission error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
