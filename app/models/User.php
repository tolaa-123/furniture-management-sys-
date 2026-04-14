<?php
/**
 * User Model
 * Handles all user-related database operations
 * 
 * @author Senior Full-Stack Engineer
 * @version 1.0.0
 */

require_once dirname(__DIR__) . '/../core/BaseModel.php';

class User extends BaseModel {
    protected $table = 'furn_users';
    
    const MAX_FAILED_ATTEMPTS = 5;
    
    /**
     * Find user by email
     * @param string $email
     * @return array|false
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
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            // Fallback: if role_id join missed, use the role column directly
            if ($user && empty($user['role_name']) && !empty($user['role'])) {
                $user['role_name'] = $user['role'];
            }
            return $user;
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
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && empty($user['role_name']) && !empty($user['role'])) {
                $user['role_name'] = $user['role'];
            }
            return $user;
        } catch (PDOException $e) {
            error_log("Database error in findById: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if email exists
     * @param string $email
     * @return bool
     */
    public function emailExists($email) {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE email = ?");
            $stmt->execute([$email]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("Database error in emailExists: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create new user
     * @param array $data
     * @return int|false User ID on success, false on failure
     */
    public function create($data) {
        try {
            // Hash password
            $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
            
            // Split full name into first and last name
            $nameParts = explode(' ', $data['full_name'], 2);
            $firstName = $nameParts[0];
            $lastName = $nameParts[1] ?? '';
            
            // Generate username from email
            $username = strtolower(explode('@', $data['email'])[0]);
            
            $stmt = $this->db->prepare("
                INSERT INTO {$this->table} 
                (role_id, username, email, password_hash, first_name, last_name, full_name, phone, status, is_active, failed_attempts, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', 1, 0, NOW())
            ");
            
            $stmt->execute([
                $data['role_id'],
                $username,
                $data['email'],
                $hashedPassword,
                $firstName,
                $lastName,
                $data['full_name'],
                $data['phone'] ?? null
            ]);
            
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Database error in create: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verify user credentials
     * @param string $email
     * @param string $password
     * @return array|false User data on success, false on failure
     */
    public function authenticate($email, $password) {
        $user = $this->findByEmail($email);
        
        if (!$user) {
            return false;
        }
        
        // Check if account is active
        if ($user['status'] !== 'active' || (int)$user['is_active'] !== 1) {
            return false;
        }
        
        // Check failed attempts
        if ($user['failed_attempts'] >= self::MAX_FAILED_ATTEMPTS) {
            return false;
        }
        
        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            $this->incrementFailedAttempts($user['id']);
            return false;
        }
        
        // Reset failed attempts on successful login
        $this->resetFailedAttempts($user['id']);
        
        // Update last login
        $this->updateLastLogin($user['id']);
        
        return $user;
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
                SET failed_attempts = 0 
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
    private function updateLastLogin($userId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE {$this->table} 
                SET last_login = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            
            // # TODO: Log login activity in activity_logs table
        } catch (PDOException $e) {
            error_log("Error updating last login: " . $e->getMessage());
        }
    }
    
    /**
     * Get role ID by role name
     * @param string $roleName
     * @return int|false
     */
    public function getRoleIdByName($roleName) {
        try {
            $stmt = $this->db->prepare("SELECT id FROM roles WHERE role_name = ?");
            $stmt->execute([$roleName]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['id'] : false;
        } catch (PDOException $e) {
            error_log("Error getting role ID: " . $e->getMessage());
            return false;
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
}
