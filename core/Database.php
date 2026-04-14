<?php
/**
 * Database Connection Class
 * Implements singleton pattern for database connectivity
 * Uses PDO with prepared statements for security
 */

require_once dirname(__DIR__) . '/config/db_config.php';

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $hostsToTry = [];
        $hostsToTry[] = DB_HOST;
        if (strtolower(DB_HOST) === 'localhost') {
            $hostsToTry[] = '127.0.0.1';
        }
        $lastError = null;
        foreach ($hostsToTry as $host) {
            try {
                $dsn = "mysql:host={$host};port=" . (defined('DB_PORT') ? DB_PORT : 3306) . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
                return;
            } catch (PDOException $e) {
                $lastError = $e;
            }
        }
        $message = "Database connection failed. Please verify MySQL is running and credentials are correct.\n";
        $message .= "Tried hosts: " . implode(', ', $hostsToTry) . " on port " . (defined('DB_PORT') ? DB_PORT : 3306) . ".\n";
        $message .= "Error: " . ($lastError ? $lastError->getMessage() : 'Unknown error');
        die(nl2br($message));
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    // Prevent cloning
    private function __clone() {}

    // Prevent unserialization
    public function __wakeup() { throw new Exception('Cannot unserialize singleton'); }
}
