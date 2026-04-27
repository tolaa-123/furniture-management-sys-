<?php
session_start();
require_once '../../config/db_config.php';

header('Content-Type: application/json');

// Employee only
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    $employeeId = $_SESSION['user_id'];

    // Required fields
    $orderNumber        = $_POST['order_number']    ?? '';
    $customerId         = intval($_POST['customer_id'] ?? 0);
    $furnitureType      = $_POST['furniture_type']  ?? '';
    $furnitureName      = $_POST['furniture_name']  ?? '';
    $length             = floatval($_POST['length']  ?? 0);
    $width              = floatval($_POST['width']   ?? 0);
    $height             = floatval($_POST['height']  ?? 0);
    $quantity           = intval($_POST['quantity']  ?? 1);
    $budgetRange        = $_POST['budget_range']     ?? '';
    $preferredDelivery  = $_POST['preferred_delivery_date'] ?? null;
    $material           = $_POST['material']         ?? '';
    $color              = $_POST['color']            ?? '';
    $designDescription  = $_POST['design_description'] ?? '';
    $specialNotes       = $_POST['special_notes']    ?? '';

    // Validation
    if (!$customerId)       throw new Exception('Please select a customer');
    if (!$furnitureType)    throw new Exception('Furniture type is required');
    if (!$color)            throw new Exception('Color/finish is required');
    if ($length <= 0 || $width <= 0 || $height <= 0) throw new Exception('All dimensions must be greater than 0');
    if ($quantity < 1)      throw new Exception('Quantity must be at least 1');
    if (!$budgetRange)      throw new Exception('Budget range is required');

    if ($preferredDelivery) {
        $minDate = date('Y-m-d', strtotime('+7 days'));
        if ($preferredDelivery < $minDate) throw new Exception('Delivery date must be at least 7 days from today');
    }

    // File upload
    $designImage = null;
    if (isset($_FILES['design_image']) && $_FILES['design_image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $ext = strtolower(pathinfo($_FILES['design_image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) throw new Exception('Invalid file type. Allowed: JPG, PNG, PDF');
        if ($_FILES['design_image']['size'] > 5 * 1024 * 1024) throw new Exception('File too large. Max 5MB');

        $uploadDir = __DIR__ . '/../uploads/designs/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $newFilename = preg_replace('/[^a-zA-Z0-9]/', '_', $orderNumber) . '_' . time() . '.' . $ext;
        if (!move_uploaded_file($_FILES['design_image']['tmp_name'], $uploadDir . $newFilename)) {
            throw new Exception('File upload failed');
        }
        $designImage = 'uploads/designs/' . $newFilename;
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO furn_orders (
            customer_id, order_number, furniture_type,
            length, width, height,
            quantity, budget_range, preferred_delivery_date,
            color, design_description, design_image, special_notes,
            status, created_by_employee_id, assigned_employee_id, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_review', ?, ?, NOW())
    ");

    $stmt->execute([
        $customerId, $orderNumber, $furnitureType,
        $length, $width, $height,
        $quantity, $budgetRange, $preferredDelivery ?: null,
        $color, $designDescription, $designImage, $specialNotes,
        $employeeId, $employeeId
    ]);

    $orderId = $pdo->lastInsertId();

    // Notify manager
    try {
        $empName = $_SESSION['user_name'] ?? 'Employee';
        $stmtManagers = $pdo->query("SELECT id FROM furn_users WHERE role = 'manager' AND (is_active IS NULL OR is_active = 1)");
        $stmtN = $pdo->prepare("
            INSERT INTO furn_notifications (user_id, type, title, message, related_id, link, created_at)
            VALUES (?, 'order', 'New Order Pending Review', ?, ?, '/manager/cost-estimation', NOW())
        ");
        foreach ($stmtManagers->fetchAll(PDO::FETCH_COLUMN) as $mid) {
            $stmtN->execute([$mid, 'New order created by employee ' . $empName, $orderId]);
        }
    } catch (Exception $e) { /* notifications table optional */ }

    $pdo->commit();

    echo json_encode([
        'success'      => true,
        'message'      => 'Order created successfully! Manager will review and provide cost estimation.',
        'order_id'     => $orderId,
        'order_number' => $orderNumber
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log("Employee order error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
