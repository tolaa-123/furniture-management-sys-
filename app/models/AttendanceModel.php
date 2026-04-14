<?php
/**
 * Attendance Model
 * Handles employee attendance tracking
 */
require_once dirname(__DIR__) . '/../core/BaseModel.php';

class AttendanceModel extends BaseModel {
    
    public function __construct() {
        parent::__construct();
        $this->ensureAttendanceTable();
    }
    
    /**
     * Ensure the attendance table exists with correct schema
     */
    private function ensureAttendanceTable() {
        try {
            // Check if table exists
            $result = $this->db->query("SELECT 1 FROM furn_attendance LIMIT 1");
            
            // Check if check_in_time column exists
            $columns = $this->db->query("SHOW COLUMNS FROM furn_attendance")->fetchAll(PDO::FETCH_ASSOC);
            $columnNames = array_column($columns, 'Field');
            
            // If check_in_time doesn't exist, add it
            if (!in_array('check_in_time', $columnNames)) {
                $this->db->exec("ALTER TABLE furn_attendance ADD COLUMN check_in_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER employee_id");
            }
            
            // If ip_address doesn't exist, add it
            if (!in_array('ip_address', $columnNames)) {
                $this->db->exec("ALTER TABLE furn_attendance ADD COLUMN ip_address VARCHAR(45) NOT NULL DEFAULT '0.0.0.0'");
            }
            
            // If status doesn't exist, add it
            if (!in_array('status', $columnNames)) {
                $this->db->exec("ALTER TABLE furn_attendance ADD COLUMN status ENUM('present','late','absent') NOT NULL DEFAULT 'present'");
            }
            
        } catch (PDOException $e) {
            if ($e->getCode() === '42S02') { // table not found
                $sql = "
                    CREATE TABLE IF NOT EXISTS furn_attendance (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        employee_id INT NOT NULL,
                        check_in_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        ip_address VARCHAR(45) NOT NULL,
                        status ENUM('present','late','absent') NOT NULL DEFAULT 'present'
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ";
                $this->db->exec($sql);
            } else {
                throw $e;
            }
        }
    }
    
    /**
     * Record employee check-in
     */
    public function checkIn($userId) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO furn_attendance (employee_id, check_in_time, ip_address, status) 
                VALUES (?, NOW(), ?, 'present')
            ");
            return $stmt->execute([$userId, $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
        } catch (PDOException $e) {
            error_log("Attendance checkIn error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get today's attendance for user
     */
    public function getTodayAttendance($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM furn_attendance 
                WHERE employee_id = ? AND DATE(check_in_time) = CURDATE()
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Attendance getTodayAttendance error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get monthly attendance count
     */
    public function getMonthlyAttendanceCount($userId, $month, $year) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count FROM furn_attendance 
                WHERE employee_id = ? AND YEAR(check_in_time) = ? AND MONTH(check_in_time) = ?
            ");
            $stmt->execute([$userId, $year, $month]);
            $result = $stmt->fetch();
            return $result['count'] ?? 0;
        } catch (PDOException $e) {
            error_log("Attendance getMonthlyAttendanceCount error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get monthly working hours
     */
    public function getMonthlyHours($userId, $month, $year) {
        try {
            $stmt = $this->db->prepare("
                SELECT COALESCE(SUM(TIMESTAMPDIFF(HOUR, check_in_time, NOW())), 0) as total_hours
                FROM furn_attendance 
                WHERE employee_id = ? AND YEAR(check_in_time) = ? AND MONTH(check_in_time) = ?
            ");
            $stmt->execute([$userId, $year, $month]);
            $result = $stmt->fetch();
            return $result['total_hours'] ?? 0;
        } catch (PDOException $e) {
            error_log("Attendance getMonthlyHours error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get attendance records for a period
     */
    public function getAttendanceByPeriod($userId, $startDate, $endDate) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM furn_attendance 
                WHERE employee_id = ? AND check_in_time BETWEEN ? AND ?
                ORDER BY check_in_time DESC
            ");
            $stmt->execute([$userId, $startDate, $endDate]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Attendance getAttendanceByPeriod error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if user can check in (time and IP restrictions)
     */
    public function canCheckIn($userId) {
        try {
            // Check if already checked in today
            if ($this->getTodayAttendance($userId)) {
                return false;
            }
            
            // Check time restrictions (7AM-9AM)
            $currentHour = date('H');
            if ($currentHour < 7 || $currentHour > 9) {
                return false;
            }
            
            // Check IP address (company IP validation would go here)
            // For demo, we'll allow any IP
            return true;
        } catch (Exception $e) {
            error_log("Attendance canCheckIn error: " . $e->getMessage());
            return false;
        }
    }
}
