<?php
/**
 * Authentication API Endpoint
 * Handles AJAX requests from authentication modals
 */

// Disable error display to prevent HTML in JSON response
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

session_start();

// Set JSON response header BEFORE any output
header('Content-Type: application/json');

// Load configuration
try {
    require_once '../../config/config.php';
    require_once '../../core/BaseController.php';
    require_once '../../core/SecurityUtil.php';
    require_once '../../app/models/User.php';
    require_once '../../app/services/EmailService.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Configuration error: ' . $e->getMessage()]);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get action
$action = $_POST['action'] ?? '';

switch ($action) {
    case 'login':
        handleLogin();
        break;
    case 'register':
        handleRegister();
        break;
    case 'forgot_password':
        handleForgotPassword();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
}

function handleLogin() {
    // Verify CSRF token
    $csrf = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION[CSRF_TOKEN_NAME]) || !hash_equals($_SESSION[CSRF_TOKEN_NAME], $csrf)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request. Please try again.']);
        exit;
    }
    
    // Check rate limit
    if (!SecurityUtil::checkRateLimit($_SERVER['REMOTE_ADDR'] ?? 'unknown')) {
        echo json_encode(['success' => false, 'message' => 'Too many attempts. Please wait and try again.']);
        exit;
    }
    
    // Validate input
    $email = SecurityUtil::sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!$email || !$password) {
        echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
        exit;
    }
    
    // Authenticate user
    $userModel = new User();
    $user = $userModel->authenticate($email, $password);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        exit;
    }
    
    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = strtolower($user['role_name'] ?? '');
    $_SESSION['user_name'] = $user['full_name'] ?? (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    
    // Regenerate session ID for security
    session_regenerate_id(true);
    
    // Determine redirect URL based on role
    $redirectUrl = BASE_URL . '/public/';
    switch ($_SESSION['user_role']) {
        case 'admin':
            $redirectUrl = BASE_URL . '/public/admin/dashboard';
            break;
        case 'manager':
            $redirectUrl = BASE_URL . '/public/manager/dashboard';
            break;
        case 'employee':
            $redirectUrl = BASE_URL . '/public/employee/dashboard';
            break;
        case 'customer':
            $redirectUrl = BASE_URL . '/public/customer/dashboard';
            break;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Login successful!',
        'redirect' => $redirectUrl
    ]);
}

function handleRegister() {
    // Verify CSRF token
    $csrf = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION[CSRF_TOKEN_NAME]) || !hash_equals($_SESSION[CSRF_TOKEN_NAME], $csrf)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request. Please try again.']);
        exit;
    }
    
    // Collect and sanitize input
    $data = [
        'full_name' => SecurityUtil::sanitizeInput($_POST['full_name'] ?? ''),
        'email' => SecurityUtil::sanitizeInput($_POST['email'] ?? ''),
        'phone' => SecurityUtil::sanitizeInput($_POST['phone'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'role_id' => intval($_POST['role_id'] ?? 4) // Default to customer
    ];
    
    // Validate input
    $errors = [];
    if (!$data['full_name']) $errors[] = 'Full name is required.';
    if (!$data['email'] || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (!$data['password'] || strlen($data['password']) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($data['password'] !== $data['confirm_password']) $errors[] = 'Passwords do not match.';
    
    // Check if email exists
    $userModel = new User();
    if ($userModel->emailExists($data['email'])) {
        $errors[] = 'Email is already registered.';
    }
    
    if (!empty($errors)) {
        echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
        exit;
    }
    
    // Create user
    $newId = $userModel->create([
        'full_name' => $data['full_name'],
        'email' => $data['email'],
        'phone' => $data['phone'],
        'password' => $data['password'],
        'role_id' => $data['role_id']
    ]);
    
    if ($newId) {
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful! Please sign in.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
    }
}

function handleForgotPassword() {
    // Verify CSRF token
    $csrf = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION[CSRF_TOKEN_NAME]) || !hash_equals($_SESSION[CSRF_TOKEN_NAME], $csrf)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request. Please try again.']);
        exit;
    }
    
    $email = $_POST['email'] ?? '';
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
        exit;
    }
    
    try {
        // Check if db_config.php exists
        $dbConfigPath = __DIR__ . '/../../config/db_config.php';
        if (!file_exists($dbConfigPath)) {
            echo json_encode(['success' => false, 'message' => 'Database configuration file not found at: ' . $dbConfigPath]);
            exit;
        }
        
        // Include database config (use include not require_once to allow re-include if needed)
        include $dbConfigPath;
        
        // Check if $pdo is defined and is a valid PDO object
        if (!isset($pdo) || !is_object($pdo) || !($pdo instanceof PDO)) {
            echo json_encode(['success' => false, 'message' => 'Database connection failed. Please check that MySQL is running. isset($pdo)=' . (isset($pdo) ? 'true' : 'false')]);
            exit;
        }
        
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id, email, first_name FROM furn_users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            // For security, return same message even if email doesn't exist
            echo json_encode([
                'success' => true,
                'message' => 'If the email exists in our system, a password reset link has been sent.'
            ]);
            exit;
        }
        
        // Generate secure token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Ensure columns exist
        try {
            $pdo->exec("ALTER TABLE furn_users 
                ADD COLUMN IF NOT EXISTS password_reset_token VARCHAR(255) NULL DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS password_reset_expires DATETIME NULL DEFAULT NULL");
        } catch (PDOException $e) {
            // Columns might already exist
        }
        
        // Save token to database
        $stmt = $pdo->prepare("UPDATE furn_users SET password_reset_token = ?, password_reset_expires = ? WHERE id = ?");
        $stmt->execute([$token, $expires, $user['id']]);
        
        // Create reset link
        $resetLink = BASE_URL . '/public/reset-password?token=' . $token;
        
        require_once '../../app/services/EmailService.php';
        $emailService = new EmailService();
        $emailSent = $emailService->sendPasswordReset($user['email'], $resetLink);

        if ($emailSent) {
            echo json_encode([
                'success' => true,
                'message' => 'If the email exists in our system, a password reset link has been sent.'
            ]);
        } else {
            $errorDetails = $emailService->getLastError();
            error_log("Password reset email failed to send to {$user['email']} | Error: {$errorDetails}");
            $responseMessage = 'Unable to send password reset email. Please check the SMTP settings and server log.';
            if (defined('DEBUG_MODE') && DEBUG_MODE && !empty($errorDetails)) {
                $responseMessage .= ' Error: ' . $errorDetails;
            }
            echo json_encode([
                'success' => false,
                'message' => $responseMessage
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Forgot password error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred: ' . $e->getMessage()
        ]);
    }
}
