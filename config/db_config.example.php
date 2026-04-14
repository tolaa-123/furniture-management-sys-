<?php
/**
 * Database Configuration — EXAMPLE FILE
 * Copy this file to db_config.php and fill in your values.
 * NEVER commit db_config.php to version control.
 */

if (!defined('BASE_URL')) {
    define('BASE_URL', 'https://your-domain.com'); // change this
}

define('DB_HOST',    'localhost');
define('DB_PORT',    3306);
define('DB_USER',    'your_db_user');
define('DB_PASS',    'your_db_password');
define('DB_NAME',    'furniture_erp');
define('DB_CHARSET', 'utf8mb4');
define('TABLE_PREFIX', 'furn_');

date_default_timezone_set('Africa/Addis_Ababa');

try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    die("Database connection failed. Please check your configuration.");
}
