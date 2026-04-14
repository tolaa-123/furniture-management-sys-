<?php
/**
 * AJAX Attendance Update Handler
 * Handles instant attendance status updates from dropdown
 */

// Start session and include config
session_start();
require_once __DIR__ . '/../../config/db_config.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in as manager
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit();
}

// Check if POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit();
}

// Get POST data
$recordId = $_POST['record_id'] ?? null;
$employeeId = $_POST['employee_id'] ?? null;
$date = $_POST['date'] ?? null;
$status = $_POST['status'] ?? null;
$managerId = $_POST['manager_id'] ?? $_SESSION['user_id'];

// Validate required fields
if (!$employeeId || !$date || !$status) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields'
    ]);
    exit();
}

// Validate status value
$validStatuses = ['present', 'absent', 'late'];
if (!in_array($status, $validStatuses)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid status value'
    ]);
    exit();
}

try {
    // Update or insert attendance record
    $stmt = $pdo->prepare("
        INSERT INTO furn_attendance (employee_id, date, status, marked_by, updated_at)
        VALUES (?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE 
            status = VALUES(status),
            marked_by = VALUES(marked_by),
            updated_at = NOW()
    ");
    
    $result = $stmt->execute([$employeeId, $date, $status, $managerId]);
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Attendance updated successfully',
            'data' => [
                'employee_id' => $employeeId,
                'date' => $date,
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update attendance'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Attendance update error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
