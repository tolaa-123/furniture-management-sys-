<?php
/**
 * Attendance Check-in API Endpoint
 */
require_once '../config/config.php';
require_once '../core/Database.php';
require_once '../core/SecurityUtil.php';
require_once '../app/models/UserModel.php';
require_once '../app/models/AttendanceModel.php';
require_once '../app/models/AuditLogModel.php';

header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    $security = new SecurityUtil();
    $attendanceModel = new AttendanceModel();
    $auditLogModel = new AuditLogModel();
    
    $userId = $_SESSION['user_id'];
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    // Validate CSRF token
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit;
    }
    
    // Check if user can check in
    if (!$attendanceModel->canCheckIn($userId)) {
        echo json_encode(['success' => false, 'message' => 'You cannot check in at this time. Check-in is only allowed between 7:00 AM and 9:00 AM.']);
        exit;
    }
    
    // Record check-in
    if ($attendanceModel->checkIn($userId)) {
        // Log the check-in action
        $auditLogModel->logAction(
            $userId,
            'employee_check_in',
            'furn_attendance',
            null,
            null,
            [
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'check_in_time' => date('Y-m-d H:i:s')
            ]
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Check-in successful!',
            'check_in_time' => date('g:i A')
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to record check-in. Please try again.']);
    }
    
} catch (Exception $e) {
    error_log('Attendance check-in error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}