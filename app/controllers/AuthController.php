<?php
require_once dirname(__DIR__) . '/../core/BaseController.php';
require_once dirname(__DIR__) . '/models/User.php';

class AuthController extends BaseController {
    public function showLogin() {
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = SecurityUtil::generateToken(32);
        }
        $csrf_token = $_SESSION[CSRF_TOKEN_NAME];
        include VIEWS_PATH . 'auth/login.php';
    }

    public function login() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->redirect('public/?auth=login');
            }
            
            // Validate CSRF token
            $csrf = $_POST['csrf_token'] ?? '';
            if (!isset($_SESSION[CSRF_TOKEN_NAME]) || !hash_equals($_SESSION[CSRF_TOKEN_NAME], $csrf)) {
                ErrorLogger::logWarning('CSRF token validation failed', [
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                ]);
                $this->setFlashMessage('danger', 'Invalid request. Please try again.');
                $this->redirect('public/?auth=login');
            }
            
            // Check rate limit
            if (!SecurityUtil::checkRateLimit($_SERVER['REMOTE_ADDR'] ?? 'unknown')) {
                ErrorLogger::logWarning('Rate limit exceeded for login', [
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]);
                $this->setFlashMessage('danger', 'Too many attempts. Please wait and try again.');
                $this->redirect('public/?auth=login');
            }
            
            // Get and sanitize input
            $email = SecurityUtil::sanitizeInput($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (!$email || !$password) {
                ErrorLogger::logWarning('Login attempt with missing credentials', [
                    'email' => $email,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]);
                $this->setFlashMessage('danger', 'Email and password are required.');
                $this->redirect('public/?auth=login');
            }
            
            // Authenticate user
            $userModel = new User();
            $user = $userModel->authenticate($email, $password);
            
            if (!$user) {
                ErrorLogger::logWarning('Failed login attempt', [
                    'email' => $email,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]);
                $this->setFlashMessage('danger', 'Invalid email or password.');
                
                // Check if modal submission
                if (isset($_POST['modal_submit'])) {
                    $this->redirect('public/');
                } else {
                    $this->redirect('public/?auth=login');
                }
            }
            
            // Log successful login
            ErrorLogger::logInfo('User logged in successfully', [
                'user_id' => $user['id'],
                'email' => $email,
                'role' => $user['role_name'] ?? 'unknown',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]);
            
            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = strtolower($user['role_name'] ?? '');
            $_SESSION['user_name'] = $user['full_name'] ?? (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            
            // Regenerate session ID for security
            session_regenerate_id(true);
        
            // Check for redirect parameter
            $redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? null;
            if ($redirect && $_SESSION['user_role'] === 'customer') {
                // Sanitize redirect URL
                $redirect = preg_replace('/[^a-zA-Z0-9\/_-]/', '', $redirect);
                $this->redirect('public/' . $redirect);
                return;
            }
            
            switch ($_SESSION['user_role']) {
                case 'admin':
                    $this->redirect('public/admin/dashboard');
                    break;
                case 'manager':
                    $this->redirect('public/manager/dashboard');
                    break;
                case 'employee':
                    $this->redirect('public/employee/dashboard');
                    break;
                case 'customer':
                    $this->redirect('public/customer/dashboard');
                    break;
                default:
                    $this->redirect('public/home');
            }
        } catch (Exception $e) {
            ErrorLogger::logError('Exception during login', 500, [
                'message' => $e->getMessage(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]);
            $this->setFlashMessage('danger', 'An error occurred. Please try again.');
            $this->redirect('public/?auth=login');
        }
    }

    public function showRegister() {
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = SecurityUtil::generateToken(32);
        }
        $csrf_token = $_SESSION[CSRF_TOKEN_NAME];
        $userModel = new User();
        $roles = $userModel->getAllRoles();
        include VIEWS_PATH . 'auth/register.php';
    }

    public function register() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->redirect('public/?auth=register');
            }
            
            // Validate CSRF token
            $csrf = $_POST['csrf_token'] ?? '';
            if (!isset($_SESSION[CSRF_TOKEN_NAME]) || !hash_equals($_SESSION[CSRF_TOKEN_NAME], $csrf)) {
                ErrorLogger::logWarning('CSRF token validation failed during registration', [
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                ]);
                $this->setFlashMessage('danger', 'Invalid request. Please try again.');
                $this->redirect('public/?auth=register');
            }
            
            // Get and sanitize input
            $data = [
                'full_name' => SecurityUtil::sanitizeInput($_POST['full_name'] ?? ''),
                'email' => SecurityUtil::sanitizeInput($_POST['email'] ?? ''),
                'phone' => SecurityUtil::sanitizeInput($_POST['phone'] ?? ''),
                'password' => $_POST['password'] ?? '',
                'confirm_password' => $_POST['confirm_password'] ?? '',
                'role_id' => intval($_POST['role_id'] ?? 0)
            ];
            
            // Validate input
            $errors = [];
            if (!$data['full_name']) $errors[] = 'Full name is required.';
            if (!$data['email'] || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
            if (!$data['password'] || strlen($data['password']) < 8) $errors[] = 'Password must be at least 8 characters.';
            if ($data['password'] !== $data['confirm_password']) $errors[] = 'Passwords do not match.';
            if ($data['role_id'] <= 0) $errors[] = 'Role is required.';
            
            // Check if email exists
            $userModel = new User();
            if ($userModel->emailExists($data['email'])) {
                $errors[] = 'Email is already registered.';
                ErrorLogger::logWarning('Registration attempt with existing email', [
                    'email' => $data['email'],
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]);
            }
            
            if (!empty($errors)) {
                ErrorLogger::logWarning('Registration validation failed', [
                    'email' => $data['email'],
                    'errors' => $errors,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]);
                
                $_SESSION['registration_errors'] = $errors;
                $_SESSION['old_input'] = [
                    'full_name' => $data['full_name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'role_id' => $data['role_id']
                ];
                
                // Check if modal submission
                if (isset($_POST['modal_submit'])) {
                    $this->redirect('public/');
                } else {
                    $this->redirect('public/?auth=register');
                }
            }
            
            // Create new user
            $newId = $userModel->create([
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'role_id' => $data['role_id']
            ]);
            
            if ($newId) {
                ErrorLogger::logInfo('User registered successfully', [
                    'user_id' => $newId,
                    'email' => $data['email'],
                    'role_id' => $data['role_id'],
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]);
                
                $this->setFlashMessage('success', 'Registration successful. Please sign in.');
                
                // Check if modal submission
                if (isset($_POST['modal_submit'])) {
                    $this->redirect('public/');
                } else {
                    $this->redirect('public/?auth=login');
                }
            } else {
                ErrorLogger::logError('User registration failed', 500, [
                    'email' => $data['email'],
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]);
                
                $_SESSION['registration_errors'] = ['Registration failed. Please try again.'];
                $_SESSION['old_input'] = [
                    'full_name' => $data['full_name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'role_id' => $data['role_id']
                ];
                
                // Check if modal submission
                if (isset($_POST['modal_submit'])) {
                    $this->redirect('public/');
                } else {
                    $this->redirect('public/?auth=register');
                }
            }
        } catch (Exception $e) {
            ErrorLogger::logError('Exception during registration', 500, [
                'message' => $e->getMessage(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]);
            
            $this->setFlashMessage('danger', 'An error occurred. Please try again.');
            $this->redirect('public/?auth=register');
        }
    }

    public function showForgotPassword() {
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = SecurityUtil::generateToken(32);
        }
        $csrf_token = $_SESSION[CSRF_TOKEN_NAME];
        include VIEWS_PATH . 'auth/forgot_password.php';
    }

    public function forgotPassword() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->redirect('public/?auth=forgot');
            }
            
            // Validate CSRF token
            $csrf = $_POST['csrf_token'] ?? '';
            if (!isset($_SESSION[CSRF_TOKEN_NAME]) || !hash_equals($_SESSION[CSRF_TOKEN_NAME], $csrf)) {
                ErrorLogger::logWarning('CSRF token validation failed in forgot password', [
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]);
                $this->setFlashMessage('danger', 'Invalid request. Please try again.');
                $this->redirect('public/?auth=forgot');
            }
            
            // Get and validate email
            $email = SecurityUtil::sanitizeInput($_POST['email'] ?? '');
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->setFlashMessage('danger', 'Please provide a valid email address.');
                $this->redirect('public/?auth=forgot');
            }
            
            // Check if user exists
            $userModel = new User();
            $user = $userModel->findByEmail($email);
            
            if ($user) {
                // Generate reset token
                $resetToken = bin2hex(random_bytes(32));
                
                // Set expiration (1 hour from now)
                $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Save token to database (using furn_users table)
                require_once __DIR__ . '/../core/Database.php';
                $pdo = Database::getInstance()->getConnection();
                
                // Ensure columns exist
                $pdo->exec("ALTER TABLE furn_users 
                    ADD COLUMN IF NOT EXISTS password_reset_token VARCHAR(255) NULL DEFAULT NULL,
                    ADD COLUMN IF NOT EXISTS password_reset_expires DATETIME NULL DEFAULT NULL");
                
                $stmt = $pdo->prepare("
                    UPDATE furn_users 
                    SET password_reset_token = ?, password_reset_expires = ?
                    WHERE id = ?
                ");
                $stmt->execute([$resetToken, $expiresAt, $user['id']]);
                
                // Generate reset link
                $resetLink = BASE_URL . '/public/reset-password?token=' . $resetToken;
                
                // Send email
                require_once __DIR__ . '/../services/EmailService.php';
                $emailService = new EmailService();
                
                // Override sender with admin settings if available
                try {
                    $stmt = $pdo->query("SELECT setting_value FROM furn_settings WHERE setting_key = 'site_name'");
                    $siteName = $stmt->fetchColumn();
                    if ($siteName) {
                        $emailService->setSenderName($siteName);
                    }
                } catch (Exception $e) {
                    // Use default sender name
                }
                
                $emailSent = $emailService->sendPasswordReset($email, $resetLink);

                if ($emailSent) {
                    $this->setFlashMessage('success', 'If an account exists with that email, a password reset link has been sent.');
                } else {
                    $errorDetails = $emailService->getLastError();
                    ErrorLogger::logError('Failed to send password reset email', 500, [
                        'email' => $email,
                        'user_id' => $user['id'],
                        'error' => $errorDetails,
                    ]);
                    $message = 'Unable to send password reset email. Please check SMTP settings and server log.';
                    if (defined('DEBUG_MODE') && DEBUG_MODE && !empty($errorDetails)) {
                        $message .= ' Error: ' . $errorDetails;
                    }
                    $this->setFlashMessage('danger', $message);
                }

                // Log password reset request regardless of email result
                ErrorLogger::logInfo('Password reset requested', [
                    'user_id' => $user['id'],
                    'email' => $email,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]);
            } else {
                $this->setFlashMessage('success', 'If an account exists with that email, a password reset link has been sent.');
            }
            
            $this->redirect('public/?auth=forgot');
            
        } catch (Exception $e) {
            ErrorLogger::logError('Exception in forgot password', 500, [
                'message' => $e->getMessage(),
                'email' => $_POST['email'] ?? 'unknown',
            ]);
            $this->setFlashMessage('danger', 'An error occurred. Please try again.');
            $this->redirect('public/?auth=forgot');
        }
    }

    public function showResetPassword() {
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = SecurityUtil::generateToken(32);
        }
        $csrf_token = $_SESSION[CSRF_TOKEN_NAME];
        
        // Validate token from URL
        $token = $_GET['token'] ?? '';
        $isValidToken = false;
        
        if ($token) {
            // Use db_config.php instead of Database class
            require_once __DIR__ . '/../../config/db_config.php';
            
            // Make $pdo available from global scope
            global $pdo;
            
            if (!isset($pdo) || !($pdo instanceof PDO)) {
                $isValidToken = false;
            } else {
                try {
                    // Use furn_users table with password_reset_token column
                    $stmt = $pdo->prepare("
                        SELECT id as user_id, email
                        FROM furn_users
                        WHERE password_reset_token = ? 
                        AND password_reset_expires > NOW()
                    ");
                    $stmt->execute([$token]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($result) {
                        $isValidToken = true;
                    }
                } catch (Exception $e) {
                    ErrorLogger::logError('Error validating reset token', 500, [
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }
        
        include VIEWS_PATH . 'auth/reset_password.php';
    }

    public function resetPassword() {
        try {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                $this->redirect('public/?auth=login');
            }
            
            // Validate CSRF token
            $csrf = $_POST['csrf_token'] ?? '';
            if (!isset($_SESSION[CSRF_TOKEN_NAME]) || !hash_equals($_SESSION[CSRF_TOKEN_NAME], $csrf)) {
                ErrorLogger::logWarning('CSRF token validation failed in reset password', [
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]);
                $this->setFlashMessage('danger', 'Invalid request. Please try again.');
                $this->redirect('public/?auth=login');
            }
            
            // Get and validate inputs
            $token = $_POST['token'] ?? '';
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            if (!$token) {
                $this->setFlashMessage('danger', 'Invalid reset link. Please request a new password reset.');
                $this->redirect('public/?auth=forgot');
            }
            
            if (!$password || strlen($password) < 8) {
                $this->setFlashMessage('danger', 'Password must be at least 8 characters long.');
                $this->redirect('public/reset-password?token=' . urlencode($token));
            }
            
            if ($password !== $confirmPassword) {
                $this->setFlashMessage('danger', 'Passwords do not match.');
                $this->redirect('public/reset-password?token=' . urlencode($token));
            }
            
            // Verify token
            require_once __DIR__ . '/../../config/db_config.php';
            
            // Make $pdo available from global scope
            global $pdo;
            
            if (!isset($pdo) || !($pdo instanceof PDO)) {
                $this->setFlashMessage('danger', 'Database connection failed.');
                $this->redirect('public/?auth=forgot');
            }
            
            $stmt = $pdo->prepare("
                SELECT id as user_id, email
                FROM furn_users
                WHERE password_reset_token = ? 
                AND password_reset_expires > NOW()
            ");
            $stmt->execute([$token]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                ErrorLogger::logWarning('Invalid or expired password reset token used', [
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]);
                $this->setFlashMessage('danger', 'This password reset link has expired or is invalid. Please request a new one.');
                $this->redirect('public/?auth=forgot');
            }
            
            $userId = $result['user_id'];
            $email = $result['email'];
            
            // Update password and clear token
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("UPDATE furn_users SET password_hash = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE id = ?");
            $stmt->execute([$passwordHash, $userId]);
            
            // Log success
            ErrorLogger::logInfo('Password reset successful', [
                'user_id' => $userId,
                'email' => $email,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]);
            
            $this->setFlashMessage('success', 'Your password has been reset successfully. You can now log in with your new password.');
            $this->redirect('public/?auth=login');
            
        } catch (Exception $e) {
            ErrorLogger::logError('Exception during password reset', 500, [
                'message' => $e->getMessage(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]);
            $this->setFlashMessage('danger', 'An error occurred. Please try again.');
            $this->redirect('public/?auth=login');
        }
    }

    public function logout() {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        $this->redirect('public/?auth=login');
    }
}
