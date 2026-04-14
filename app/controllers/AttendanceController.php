<?php
/**
 * Attendance Controller
 * Handles employee attendance check-in and management
 */
require_once dirname(__DIR__) . '/../core/BaseController.php';
require_once dirname(__DIR__) . '/models/AttendanceModel.php';

class AttendanceController extends BaseController {
    private $attendanceModel;
    
    public function __construct() {
        parent::__construct();
        $this->attendanceModel = new AttendanceModel();
    }
    
    /**
     * Employee check-in page
     */
    public function checkIn() {
        // Must be logged in as employee or manager
        if (!$this->isLoggedIn() || ($this->getUserRole() !== 'employee' && $this->getUserRole() !== 'manager')) {
            $this->setFlashMessage('error', 'You must be logged in as an employee to check in');
            $this->redirect('/login');
            return;
        }
        
        $userId = $_SESSION['user_id'];
        $userRole = $this->getUserRole();
        
        // Check if already checked in today
        $hasCheckedIn = $this->attendanceModel->hasCheckedInToday($userId);
        $todaysAttendance = null;
        
        if ($hasCheckedIn) {
            $todaysAttendance = $this->attendanceModel->getTodaysAttendance($userId);
        }
        
        // Get attendance settings
        $settings = $this->attendanceModel->getAttendanceSettings();
        
        // Get current time and IP
        $currentTime = date('H:i:s');
        $currentDate = date('Y-m-d');
        $clientIP = $this->getClientIP();
        
        // Check if within allowed time window
        $isWithinTime = ($currentTime >= $settings['check_in_start_time'] && 
                        $currentTime <= $settings['check_in_end_time']);
        
        // Check if IP is authorized
        $companyIp = $settings['company_ip_address'];
        $isAuthorizedIP = $this->isAuthorizedIP($clientIP, $companyIp);
        
        // Check if can check in
        $canCheckIn = !$hasCheckedIn && $isWithinTime && $isAuthorizedIP;
        
        $data = [
            'hasCheckedIn' => $hasCheckedIn,
            'todaysAttendance' => $todaysAttendance,
            'settings' => $settings,
            'currentTime' => $currentTime,
            'currentDate' => $currentDate,
            'clientIP' => $clientIP,
            'isWithinTime' => $isWithinTime,
            'isAuthorizedIP' => $isAuthorizedIP,
            'canCheckIn' => $canCheckIn,
            'userRole' => $userRole
        ];
        
        $this->render('attendance/check_in', $data);
    }
    
    /**
     * Process check-in
     */
    public function processCheckIn() {
        if (!$this->isLoggedIn() || ($this->getUserRole() !== 'employee' && $this->getUserRole() !== 'manager')) {
            $this->setFlashMessage('error', 'Unauthorized access');
            $this->redirect('/login');
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->setFlashMessage('error', 'Invalid request method');
            $this->redirect('/attendance/check-in');
            return;
        }
        
        $userId = $_SESSION['user_id'];
        $clientIP = $this->getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        try {
            $attendanceId = $this->attendanceModel->checkIn($userId, $clientIP, $userAgent);
            
            if ($attendanceId) {
                // Log the attendance
                $this->logAudit('attendance_check_in', 'furn_attendance', $attendanceId, [
                    'ip_address' => $clientIP,
                    'user_agent' => $userAgent
                ]);
                
                $this->setFlashMessage('success', 'Check-in successful!');
                $this->redirect('/attendance/check-in');
            }
        } catch (Exception $e) {
            $this->setFlashMessage('error', $e->getMessage());
            $this->redirect('/attendance/check-in');
        }
    }
    
    /**
     * Attendance dashboard for managers
     */
    public function dashboard() {
        if (!$this->isManager()) {
            $this->setFlashMessage('error', 'Access denied. Manager access required.');
            $this->redirect('/login');
            return;
        }
        
        $date = $_GET['date'] ?? date('Y-m-d');
        $employeeId = $_GET['employee'] ?? null;
        
        // Get attendance statistics
        $stats = $this->attendanceModel->getAttendanceStats($date);
        
        // Get today's attendance records
        $todaysRecords = $this->attendanceModel->getAttendanceByDateRange($date, $date);
        
        // Get attendance settings
        $settings = $this->attendanceModel->getAttendanceSettings();
        
        // Get all employees for filter
        $stmt = $this->db->prepare("SELECT id, employee_id, first_name, last_name, department FROM furn_users WHERE role IN ('employee', 'manager') AND is_active = 1 ORDER BY first_name, last_name");
        $stmt->execute();
        $employees = $stmt->fetchAll();
        
        $data = [
            'stats' => $stats,
            'todaysRecords' => $todaysRecords,
            'settings' => $settings,
            'date' => $date,
            'employees' => $employees,
            'selectedEmployee' => $employeeId
        ];
        
        $this->render('attendance/dashboard', $data);
    }
    
    /**
     * View attendance reports
     */
    public function reports() {
        if (!$this->isManager()) {
            $this->setFlashMessage('error', 'Access denied. Manager access required.');
            $this->redirect('/login');
            return;
        }
        
        $employeeId = $_GET['employee'] ?? null;
        $month = $_GET['month'] ?? date('m');
        $year = $_GET['year'] ?? date('Y');
        
        $startDate = "$year-$month-01";
        $endDate = date('Y-m-t', strtotime($startDate));
        
        // Get attendance records for the period
        $records = $this->attendanceModel->getAttendanceByDateRange($startDate, $endDate, $employeeId);
        
        // Get employee summary
        $summary = null;
        if ($employeeId) {
            $summary = $this->attendanceModel->getEmployeeAttendanceSummary($employeeId, $month, $year);
        }
        
        // Get all employees
        $stmt = $this->db->prepare("SELECT id, employee_id, first_name, last_name, department FROM furn_users WHERE role IN ('employee', 'manager') AND is_active = 1 ORDER BY first_name, last_name");
        $stmt->execute();
        $employees = $stmt->fetchAll();
        
        $data = [
            'records' => $records,
            'summary' => $summary,
            'employees' => $employees,
            'selectedEmployee' => $employeeId,
            'month' => $month,
            'year' => $year,
            'startDate' => $startDate,
            'endDate' => $endDate
        ];
        
        $this->render('attendance/reports', $data);
    }
    
    /**
     * Settings management
     */
    public function settings() {
        if (!$this->isAdmin()) {
            $this->setFlashMessage('error', 'Access denied. Admin access required.');
            $this->redirect('/login');
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $settings = [
                'check_in_start_time' => $_POST['check_in_start_time'] ?? '07:00:00',
                'check_in_end_time' => $_POST['check_in_end_time'] ?? '09:00:00',
                'company_ip_address' => $_POST['company_ip_address'] ?? '192.168.1.100',
                'late_threshold_minutes' => $_POST['late_threshold_minutes'] ?? '30'
            ];
            
            foreach ($settings as $key => $value) {
                $this->attendanceModel->updateSetting($key, $value);
            }
            
            $this->logAudit('attendance_settings_updated', 'furn_attendance_settings', null, $settings);
            $this->setFlashMessage('success', 'Attendance settings updated successfully');
            $this->redirect('/attendance/settings');
        }
        
        $settings = $this->attendanceModel->getAttendanceSettings();
        
        $data = ['settings' => $settings];
        $this->render('attendance/settings', $data);
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        }
    }
    
    /**
     * Check if IP is authorized
     */
    private function isAuthorizedIP($clientIP, $companyIP) {
        $allowedIPs = [
            '127.0.0.1',
            '::1',
            'localhost',
            $companyIP
        ];
        
        return in_array($clientIP, $allowedIPs) || $this->isLocalIP($clientIP);
    }
    
    /**
     * Check if IP is local
     */
    private function isLocalIP($ip) {
        $ipLong = ip2long($ip);
        return (
            ($ipLong >= ip2long('10.0.0.0') && $ipLong <= ip2long('10.255.255.255')) ||
            ($ipLong >= ip2long('172.16.0.0') && $ipLong <= ip2long('172.31.255.255')) ||
            ($ipLong >= ip2long('192.168.0.0') && $ipLong <= ip2long('192.168.255.255'))
        );
    }
}