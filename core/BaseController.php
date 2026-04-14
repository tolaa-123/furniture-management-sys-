<?php
/**
 * Base Controller Class
 * Provides common functionality for all controllers
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/core/SecurityUtil.php';

abstract class BaseController {
    
    protected $model;
    protected $security;
    protected $auth;
    
    /**
     * Constructor to initialize common properties
     */
    public function __construct() {
        // Initialize session if not already started
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        // Initialize security utility
        $this->security = new SecurityUtil();
        
        // Initialize auth helper
        $this->auth = new class {
            public function isLoggedIn() {
                return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
            }
            
            public function getUserRole() {
                return $_SESSION['user_role'] ?? null;
            }
            
            public function getUserId() {
                return $_SESSION['user_id'] ?? null;
            }
            
            public function getUserFullName() {
                return $_SESSION['user_name'] ?? 'Guest';
            }
        };
        
        // Set default timezone
        date_default_timezone_set('Africa/Addis_Ababa');
    }
    
    /**
     * Load a model by name
     */
    protected function loadModel($modelName) {
        $modelPath = MODELS_PATH . $modelName . '.php';
        if (file_exists($modelPath)) {
            require_once $modelPath;
            // Instantiate the model
            $this->model = new $modelName();
            return $this->model;
        } else {
            throw new Exception("Model {$modelName} not found at {$modelPath}");
        }
    }
    
    /**
     * Render a view with optional data
     */
    protected function renderView($viewName, $data = []) {
        $viewPath = VIEWS_PATH . $viewName . '.php';
        if (file_exists($viewPath)) {
            // Extract data to be used in the view
            extract($data);
            require_once $viewPath;
        } else {
            throw new Exception("View {$viewName} not found at {$viewPath}");
        }
    }
    
    /**
     * Redirect to a specific URL
     */
    protected function redirect($url) {
        header("Location: " . BASE_URL . "/" . $url);
        exit();
    }
    
    /**
     * Get current user session data
     */
    protected function getCurrentUser() {
        return isset($_SESSION['user']) ? $_SESSION['user'] : null;
    }
    
    /**
     * Check if user is logged in
     */
    protected function isLoggedIn() {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Check user role
     */
    protected function hasRole($role) {
        return isset($_SESSION['role']) && $_SESSION['role'] === $role;
    }
    
    /**
     * Validate CSRF token
     */
    protected function validateCsrfToken() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST[CSRF_TOKEN_NAME] ?? $_GET[CSRF_TOKEN_NAME] ?? '';
            return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
        }
        return true;
    }
    
    /**
     * Generate CSRF token
     */
    protected function generateCsrfToken() {
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }
    
    /**
     * Log an action for audit purposes
     */
    protected function logAction($action, $details = '') {
        $userId = $_SESSION['user_id'] ?? 'guest';
        $timestamp = date('Y-m-d H:i:s');
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        
        // Log to file (in a real system, you might store this in a database)
        $logEntry = "[{$timestamp}] User: {$userId}, IP: {$ipAddress}, Action: {$action}, Details: {$details}\n";
        file_put_contents(dirname(__DIR__) . '/logs/audit.log', $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Show error message and redirect
     */
    protected function showError($message) {
        $_SESSION['error_message'] = $message;
        $this->redirect('login');
    }
    
    /**
     * Set flash message
     */
    protected function setFlashMessage($type, $message) {
        $_SESSION['flash_message'] = [
            'type' => $type,
            'message' => $message
        ];
    }
    
    /**
     * Render view
     */
    protected function view($viewName, $data = []) {
        $viewPath = VIEWS_PATH . $viewName . '.php';
        if (file_exists($viewPath)) {
            extract($data);
            require_once $viewPath;
        } else {
            throw new Exception("View {$viewName} not found at {$viewPath}");
        }
    }
}