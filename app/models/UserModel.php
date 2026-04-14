<?php
/**
 * User Model - Secure Authentication & User Management
 * Implements enterprise-level security standards
 * 
 * @author Custom Furniture ERP Team
 * @version 2.0
 */

require_once dirname(__DIR__) . '/../core/BaseModel.php';
require_once dirname(__DIR__) . '/../core/SecurityUtil.php';

class UserModel extends BaseModel {
    protected $table = 'furn_users';
    
    // Maximum failed login attempts before account lock
    const MAX_FAILED_ATTEMPTS = 5;
    
    // Account lock duration in minutes
    const LOCK_DURATION = 30;
    
    /**
     * Find user by email
     * @param string $email
     * @return array|false User data or false
     */
    public function findByEmail($email) {
        try {
            $stmt = $this->db->prepare("
                SELECT u.*, r.role_name 
                FROM {$this->table} u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.email = ?
                LIMIT 1
            ");
            $stmt->execute([$email]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database error in findByEmail: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Find user by ID
     * @param int $id
     * @return array|false
     */
    public function findById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT u.*, r.role_name 
                FROM {$this->table} u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.id = ?
                LIMIT 1
            ");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database error in findById: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Authenticate user with email and password
     * Implements security checks and failed attempt tracking
     * 
     * @param string $email
     * @param string $password
     * @return array|false User data on success, false on failure
     */
    public function authenticate($email, $password) {
        // Find user by email
        $user = $this->findByEmail($email);
        
        if (!$user) {
            // User not found - don't reveal this information
            return false;
        }
        
        // Check if account is locked
        if ($this->isAccountLocked($user)) {
            return false;
        }
        
        // Check if account is active
        if ($user['status'] !== 'active') {
            return false;
        }
        
        // Verify password
        if (!SecurityUtil::verifyPassword($password, $user['password_hash'])) {
            // Increment failed attempts
            $this->incrementFailedAttempts($user['id']);
            return false;
        }
        
        // Authentication successful - reset failed attempts
        $this->resetFailedAttempts($user['id']);
        
        // Update last login
        $this->updateLastLogin($user['id']);
        
        // Log login activity
        SecurityUtil::logActivity($user['id'], 'login_success', 'User logged in successfully');
        
        return $user;
    }
    
    /**
     * Check if account is locked due to failed attempts
     * @param array $user
     * @return bool
     */
    public function isAccountLocked($user) {
        // Check if locked_until is set and still in the future
        if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
            return true;
        }
        
        // Check if failed attempts exceed maximum
        if ($user['failed_attempts'] >= self::MAX_FAILED_ATTEMPTS) {
            // Lock the account
            $this->lockAccount($user['id']);
            return true;
        }
        
        return false;
    }
    
    /**
     * Lock user account for specified duration
     * @param int $userId
     */
    private function lockAccount($userId) {
        try {
            $lockUntil = date('Y-m-d H:i:s', strtotime('+' . self::LOCK_DURATION . ' minutes'));
            $stmt = $this->db->prepare("
                UPDATE {$this->table} 
                SET locked_until = ?, status = 'suspended'
                WHERE id = ?
            ");
            $stmt->execute([$lockUntil, $userId]);
            // Send email notification about account lock
            $user = $this->findById($userId);
            if ($user && !empty($user['email'])) {
                require_once dirname(__DIR__) . '/services/EmailService.php';
                $emailService = new \EmailService();
                $subject = 'Account Locked - Too Many Failed Logins';
                $body = '<p>Your account has been locked due to too many failed login attempts. It will be unlocked at: <strong>' . htmlspecialchars($lockUntil) . '</strong>.</p>';
                $body .= '<p>If this was not you, please contact support immediately.</p>';
                $emailService->send($user['email'], $subject, $body);
            }
            SecurityUtil::logActivity($userId, 'account_locked', 'Account locked due to multiple failed login attempts');
        } catch (PDOException $e) {
            error_log("Error locking account: " . $e->getMessage());
        }
    }
    
    /**
     * Increment failed login attempts
     * @param int $userId
     */
    private function incrementFailedAttempts($userId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE {$this->table} 
                SET failed_attempts = failed_attempts + 1
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            
            $this->logActivity($userId, 'login_failed', 'Failed login attempt');
        } catch (PDOException $e) {
            error_log("Error incrementing failed attempts: " . $e->getMessage());
        }
    }
    
    /**
     * Reset failed login attempts
     * @param int $userId
     */
    private function resetFailedAttempts($userId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE {$this->table} 
                SET failed_attempts = 0, locked_until = NULL, status = 'active'
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
        } catch (PDOException $e) {
            error_log("Error resetting failed attempts: " . $e->getMessage());
        }
    }
    
    /**
     * Update last login timestamp
     * @param int $userId
     */
    public function updateLastLogin($userId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE {$this->table} 
                SET last_login = NOW(),
                    ip_address = ?,
                    user_agent = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $userId
            ]);
        } catch (PDOException $e) {
            error_log("Error updating last login: " . $e->getMessage());
        }
    }
    
    /**
     * Create new user account
     * @param array $userData
     * @return int|false User ID on success, false on failure
     */
    public function createUser($userData) {
        try {
            // Validate email doesn't exist
            if ($this->emailExists($userData['email'])) {
                return false;
            }
            
            // Get role ID
            $roleId = $this->getRoleIdByName($userData['role'] ?? 'customer');
            
            // Hash password
            $passwordHash = SecurityUtil::hashPassword($userData['password']);
            
            // Prepare full name
            $fullName = $userData['full_name'] ?? ($userData['first_name'] . ' ' . $userData['last_name']);
            
            // Insert user
            $stmt = $this->db->prepare("
                INSERT INTO {$this->table} (
                    role_id, username, email, password_hash, 
                    first_name, last_name, full_name, phone, 
                    status, is_active, email_verified, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', 1, 0, NOW())
            ");
            
            $username = $userData['username'] ?? strtolower(str_replace(' ', '', $userData['email']));
            
            $stmt->execute([
                $roleId,
                $username,
                $userData['email'],
                $passwordHash,
                $userData['first_name'] ?? '',
                $userData['last_name'] ?? '',
                $fullName,
                $userData['phone'] ?? null
            ]);
            
            $userId = $this->db->lastInsertId();
            
            // Send email verification link here (to be implemented)
            SecurityUtil::logActivity($userId, 'user_registered', 'New user account created');
            
            return $userId;
            
        } catch (PDOException $e) {
            error_log("Error creating user: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if email already exists
     * @param string $email
     * @return bool
     */
    public function emailExists($email) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM {$this->table} WHERE email = ?
            ");
            $stmt->execute([$email]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("Error checking email existence: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get role ID by role name
     * @param string $roleName
     * @return int|null
     */
    private function getRoleIdByName($roleName) {
        try {
            $stmt = $this->db->prepare("SELECT id FROM roles WHERE role_name = ?");
            $stmt->execute([$roleName]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['id'] : null;
        } catch (PDOException $e) {
            error_log("Error getting role ID: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get all roles
     * @return array
     */
    public function getAllRoles() {
        try {
            $stmt = $this->db->query("SELECT * FROM roles ORDER BY role_name");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting roles: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Generate password reset token
     * @param string $email
     * @return string|false Token on success, false on failure
     */
    public function generatePasswordResetToken($email) {
        try {
            $user = $this->findByEmail($email);
            if (!$user) {
                return false;
            }
            
            // Generate secure token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $stmt = $this->db->prepare("
                UPDATE {$this->table} 
                SET password_reset_token = ?, password_reset_expires = ?
                WHERE id = ?
            ");
            $stmt->execute([$token, $expires, $user['id']]);
            
            // Send password reset email with token (to be implemented)
            SecurityUtil::logActivity($user['id'], 'password_reset_requested', 'Password reset token generated');
            
            return $token;
            
        } catch (PDOException $e) {
            error_log("Error generating reset token: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verify password reset token
     * @param string $token
     * @return array|false User data on success, false on failure
     */
    public function verifyResetToken($token) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM {$this->table}
                WHERE password_reset_token = ?
                AND password_reset_expires > NOW()
                LIMIT 1
            ");
            $stmt->execute([$token]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error verifying reset token: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Reset user password
     * @param string $token
     * @param string $newPassword
     * @return bool
     */
    public function resetPassword($token, $newPassword) {
        try {
            $user = $this->verifyResetToken($token);
            if (!$user) {
                return false;
            }
            $passwordHash = SecurityUtil::hashPassword($newPassword);
            $stmt = $this->db->prepare("
                UPDATE {$this->table} 
                SET password_hash = ?, 
                    password_reset_token = NULL, 
                    password_reset_expires = NULL,
                    failed_attempts = 0,
                    locked_until = NULL
                WHERE id = ?
            ");
            $stmt->execute([$passwordHash, $user['id']]);
            $this->logActivity($user['id'], 'password_reset', 'Password was reset successfully');
            // Send email notification about password reset
            if (!empty($user['email'])) {
                require_once dirname(__DIR__) . '/services/EmailService.php';
                $emailService = new \EmailService();
                $subject = 'Your Password Was Reset';
                $body = '<p>Your account password was successfully reset. If you did not perform this action, please contact support immediately.</p>';
                $emailService->send($user['email'], $subject, $body);
            }
            return true;
        } catch (PDOException $e) {
            error_log("Error resetting password: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log user activity
     * @param int $userId
     * @param string $activityType
     * @param string $description
     */
    private function logActivity($userId, $activityType, $description) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO furn_activity_logs (
                    user_id, activity_type, description, 
                    ip_address, user_agent, created_at
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $userId,
                $activityType,
                $description,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log("Error logging activity: " . $e->getMessage());
        }
    }
    
    /**
     * Get user's recent activity
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public function getUserActivity($userId, $limit = 10) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM furn_activity_logs
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting user activity: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update user profile
     * @param int $userId
     * @param array $data
     * @return bool
     */
    public function updateProfile($userId, $data) {
        try {
            $allowedFields = ['first_name', 'last_name', 'full_name', 'phone', 'address'];
            $updates = [];
            $values = [];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $values[] = $data[$field];
                }
            }
            
            if (empty($updates)) {
                return false;
            }
            
            $values[] = $userId;
            $sql = "UPDATE {$this->table} SET " . implode(', ', $updates) . " WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);
            
            $this->logActivity($userId, 'profile_updated', 'User profile information updated');
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Error updating profile: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Change user password
     * @param int $userId
     * @param string $currentPassword
     * @param string $newPassword
     * @return bool
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            $user = $this->findById($userId);
            if (!$user) {
                return false;
            }
            
            // Verify current password
            if (!SecurityUtil::verifyPassword($currentPassword, $user['password_hash'])) {
                return false;
            }
            
            // Hash new password
            $passwordHash = SecurityUtil::hashPassword($newPassword);
            
            $stmt = $this->db->prepare("
                UPDATE {$this->table} 
                SET password_hash = ?
                WHERE id = ?
            ");
            $stmt->execute([$passwordHash, $userId]);
            
            $this->logActivity($userId, 'password_changed', 'User changed their password');
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Error changing password: " . $e->getMessage());
            return false;
        }
    }
}
