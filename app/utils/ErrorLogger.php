<?php
/**
 * ErrorLogger - Comprehensive error logging system
 * Logs all errors and exceptions for debugging and monitoring
 */

class ErrorLogger {
    private static $logDir = __DIR__ . '/../../logs/';
    private static $errorLogFile = 'error.log';
    private static $debugLogFile = 'debug.log';
    
    /**
     * Initialize log directory
     */
    public static function init() {
        if (!is_dir(self::$logDir)) {
            mkdir(self::$logDir, 0755, true);
        }
    }
    
    /**
     * Log an error with context
     * 
     * @param string $message Error message
     * @param int $code Error code
     * @param array $context Additional context data
     * @param string $severity Error severity (ERROR, WARNING, INFO)
     */
    public static function logError($message, $code = 0, $context = [], $severity = 'ERROR') {
        self::init();
        
        $timestamp = date('Y-m-d H:i:s');
        $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'anonymous';
        $userRole = isset($_SESSION['role']) ? $_SESSION['role'] : 'guest';
        $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'CLI';
        $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'N/A';
        
        $logEntry = "[$timestamp] [$severity] ";
        $logEntry .= "User: $userId | Role: $userRole | ";
        $logEntry .= "Code: $code | ";
        $logEntry .= "Method: $method | ";
        $logEntry .= "URI: $requestUri | ";
        $logEntry .= "Message: $message";
        
        if (!empty($context)) {
            $logEntry .= " | Context: " . json_encode($context);
        }
        
        $logEntry .= "\n";
        
        $logFile = self::$logDir . self::$errorLogFile;
        error_log($logEntry, 3, $logFile);
        
        // Also log to system error log for critical errors
        if ($severity === 'ERROR' || $code >= 500) {
            error_log($logEntry);
        }
    }
    
    /**
     * Log debug information (only in debug mode)
     * 
     * @param string $message Debug message
     * @param array $data Debug data
     */
    public static function logDebug($message, $data = []) {
        // Only log debug if DEBUG_MODE is enabled
        if (!defined('DEBUG_MODE') || !DEBUG_MODE) {
            return;
        }
        
        self::init();
        
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] DEBUG: $message\n";
        
        if (!empty($data)) {
            $logEntry .= json_encode($data, JSON_PRETTY_PRINT) . "\n";
        }
        
        $logFile = self::$logDir . self::$debugLogFile;
        error_log($logEntry, 3, $logFile);
    }
    
    /**
     * Log a warning
     * 
     * @param string $message Warning message
     * @param array $context Additional context
     */
    public static function logWarning($message, $context = []) {
        self::logError($message, 0, $context, 'WARNING');
    }
    
    /**
     * Log info message
     * 
     * @param string $message Info message
     * @param array $context Additional context
     */
    public static function logInfo($message, $context = []) {
        self::logError($message, 0, $context, 'INFO');
    }
    
    /**
     * Get recent error logs
     * 
     * @param int $limit Number of recent logs to retrieve
     * @return array Array of log entries
     */
    public static function getRecentErrors($limit = 50) {
        self::init();
        
        $logFile = self::$logDir . self::$errorLogFile;
        
        if (!file_exists($logFile)) {
            return [];
        }
        
        $lines = file($logFile);
        if ($lines === false) {
            return [];
        }
        
        // Get last $limit lines
        $recentLines = array_slice($lines, -$limit);
        
        return array_map('trim', $recentLines);
    }
    
    /**
     * Get recent debug logs
     * 
     * @param int $limit Number of recent logs to retrieve
     * @return array Array of log entries
     */
    public static function getRecentDebug($limit = 50) {
        self::init();
        
        $logFile = self::$logDir . self::$debugLogFile;
        
        if (!file_exists($logFile)) {
            return [];
        }
        
        $lines = file($logFile);
        if ($lines === false) {
            return [];
        }
        
        // Get last $limit lines
        $recentLines = array_slice($lines, -$limit);
        
        return array_map('trim', $recentLines);
    }
    
    /**
     * Get error logs by date range
     * 
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return array Filtered log entries
     */
    public static function getErrorsByDateRange($startDate, $endDate) {
        self::init();
        
        $logFile = self::$logDir . self::$errorLogFile;
        
        if (!file_exists($logFile)) {
            return [];
        }
        
        $lines = file($logFile);
        if ($lines === false) {
            return [];
        }
        
        $filtered = [];
        $startTime = strtotime($startDate);
        $endTime = strtotime($endDate . ' 23:59:59');
        
        foreach ($lines as $line) {
            // Extract timestamp from log line
            if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
                $logTime = strtotime($matches[1]);
                if ($logTime >= $startTime && $logTime <= $endTime) {
                    $filtered[] = trim($line);
                }
            }
        }
        
        return $filtered;
    }
    
    /**
     * Get error logs by severity
     * 
     * @param string $severity Severity level (ERROR, WARNING, INFO)
     * @param int $limit Number of logs to retrieve
     * @return array Filtered log entries
     */
    public static function getErrorsBySeverity($severity, $limit = 50) {
        self::init();
        
        $logFile = self::$logDir . self::$errorLogFile;
        
        if (!file_exists($logFile)) {
            return [];
        }
        
        $lines = file($logFile);
        if ($lines === false) {
            return [];
        }
        
        $filtered = [];
        $count = 0;
        
        // Search from end to get most recent first
        for ($i = count($lines) - 1; $i >= 0 && $count < $limit; $i--) {
            if (strpos($lines[$i], "[$severity]") !== false) {
                $filtered[] = trim($lines[$i]);
                $count++;
            }
        }
        
        return array_reverse($filtered);
    }
    
    /**
     * Clear old log files (older than specified days)
     * 
     * @param int $days Number of days to keep
     */
    public static function clearOldLogs($days = 30) {
        self::init();
        
        $logFile = self::$logDir . self::$errorLogFile;
        
        if (!file_exists($logFile)) {
            return;
        }
        
        $lines = file($logFile);
        if ($lines === false) {
            return;
        }
        
        $cutoffTime = strtotime("-$days days");
        $newLines = [];
        
        foreach ($lines as $line) {
            if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
                $logTime = strtotime($matches[1]);
                if ($logTime > $cutoffTime) {
                    $newLines[] = $line;
                }
            }
        }
        
        file_put_contents($logFile, implode('', $newLines));
    }
    
    /**
     * Get log file statistics
     * 
     * @return array Statistics about log files
     */
    public static function getStatistics() {
        self::init();
        
        $errorLogFile = self::$logDir . self::$errorLogFile;
        $debugLogFile = self::$logDir . self::$debugLogFile;
        
        $stats = [
            'error_log_size' => file_exists($errorLogFile) ? filesize($errorLogFile) : 0,
            'debug_log_size' => file_exists($debugLogFile) ? filesize($debugLogFile) : 0,
            'error_log_lines' => 0,
            'debug_log_lines' => 0,
            'error_count' => 0,
            'warning_count' => 0,
            'info_count' => 0,
        ];
        
        if (file_exists($errorLogFile)) {
            $lines = file($errorLogFile);
            $stats['error_log_lines'] = count($lines);
            
            foreach ($lines as $line) {
                if (strpos($line, '[ERROR]') !== false) {
                    $stats['error_count']++;
                } elseif (strpos($line, '[WARNING]') !== false) {
                    $stats['warning_count']++;
                } elseif (strpos($line, '[INFO]') !== false) {
                    $stats['info_count']++;
                }
            }
        }
        
        if (file_exists($debugLogFile)) {
            $lines = file($debugLogFile);
            $stats['debug_log_lines'] = count($lines);
        }
        
        return $stats;
    }
}

// Initialize error logger
ErrorLogger::init();
?>
