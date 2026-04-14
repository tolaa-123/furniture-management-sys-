<?php
/**
 * Security Utility Class
 * Provides common security functions for the application
 */

class SecurityUtil {
    /**
     * Generate and store a CSRF token in session
     * @return string
     */
    public static function getCsrfToken() {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Validate CSRF token from POST/GET
     * @return bool
     */
    public static function validateCsrfToken($token) {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Log user activity globally
     * @param int $userId
     * @param string $action
     * @param string|null $details
     */
    public static function logActivity($userId, $action, $details = null) {
        global $pdo;
        if (!$userId || !$action) return;
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        try {
            $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $action, $details, $ip, $ua]);
        } catch (PDOException $e) {
            error_log('Activity log failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Generate a secure random token
     */
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Validate input against XSS attacks
     */
    public static function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([__CLASS__, 'sanitizeInput'], $input);
        }
        
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate file upload
     */
    public static function validateUpload($file, $allowedTypes = [], $maxSize = 0) {
        // Check if file was uploaded without errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        
        // Check file size
        if ($maxSize > 0 && $file['size'] > $maxSize) {
            return false;
        }
        
        // Get file extension
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Check allowed file types
        if (!empty($allowedTypes) && !in_array($fileExtension, $allowedTypes)) {
            return false;
        }
        
        // Generate secure filename
        $secureFilename = uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $fileExtension;
        
        return [
            'valid' => true,
            'original_name' => $file['name'],
            'secure_name' => $secureFilename,
            'size' => $file['size'],
            'extension' => $fileExtension
        ];
    }
    
    /**
     * Hash password securely
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * Verify password against hash
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Validate IP address
     */
    public static function validateIP($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP);
    }
    
    /**
     * Rate limiting function
     */
    public static function checkRateLimit($identifier, $maxAttempts = 50, $timeWindow = 900) {
        $key = 'rate_limit_' . md5($identifier);
        $attempts = $_SESSION[$key] ?? [];
        
        // Remove attempts older than time window
        $now = time();
        $attempts = array_filter($attempts, function($timestamp) use ($now, $timeWindow) {
            return ($now - $timestamp) < $timeWindow;
        });
        
        if (count($attempts) >= $maxAttempts) {
            return false; // Rate limit exceeded
        }
        
        // Add current attempt
        $attempts[] = $now;
        $_SESSION[$key] = $attempts;
        
        return true; // Within rate limit
    }
}