<?php
ob_start();
require_once '../../config/db_config.php';
ob_end_clean();

if (session_status() === PHP_SESSION_NONE) session_start();

// Check database connection
if (!$pdo) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Define CSRF token name if not defined
if (!defined('CSRF_TOKEN_NAME')) define('CSRF_TOKEN_NAME', 'csrf_token');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Download actions send file headers — everything else sends JSON
if (!in_array($action, ['download', 'download_now'])) {
    header('Content-Type: application/json');
}

// Auth
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

// CSRF for POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    $sess = $_SESSION[CSRF_TOKEN_NAME] ?? '';
    if (!$csrf || !$sess || !hash_equals($sess, $csrf)) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']); exit;
    }
}

$backupDir = __DIR__ . '/../../backups/';
if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
if (!file_exists($backupDir . '.htaccess')) {
    file_put_contents($backupDir . '.htaccess', "Order deny,allow\nDeny from all\n");
}

function generateSqlDump($pdo) {
    $sql  = "-- FurnitureCraft ERP Database Backup\n";
    $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Database: " . DB_NAME . "\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
    $sql .= "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";
    $sql .= "SET NAMES utf8mb4;\n\n";

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
        $sql .= "DROP TABLE IF EXISTS `$table`;\n";
        $sql .= $create[1] . ";\n\n";

        // Use unbuffered query for large tables to reduce memory usage
        $stmt = $pdo->query("SELECT * FROM `$table`");
        $rowCount = 0;
        $parts = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $vals = array_map(function($v) {
                if ($v === null) return 'NULL';
                return "'" . addslashes($v) . "'";
            }, $row);
            $parts[] = '(' . implode(',', $vals) . ')';
            $rowCount++;
            
            // Write in batches of 100 rows to manage memory
            if ($rowCount % 100 === 0) {
                $sql .= "INSERT INTO `$table` VALUES\n" . implode(",\n", $parts) . ";\n\n";
                $parts = [];
            }
        }
        
        // Write remaining rows
        if (!empty($parts)) {
            $sql .= "INSERT INTO `$table` VALUES\n" . implode(",\n", $parts) . ";\n\n";
        }
    }
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $sql;
}

// ── save ──────────────────────────────────────────────────────────────────
if ($action === 'save') {
    try {
        $filename = 'backup_' . DB_NAME . '_' . date('Y-m-d_H-i-s') . '.sql';
        $sql = generateSqlDump($pdo);
        file_put_contents($backupDir . $filename, $sql);
        $size = round(filesize($backupDir . $filename) / 1024, 1);
        echo json_encode(['success' => true, 'message' => "Backup saved: $filename ($size KB)"]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Backup failed: ' . $e->getMessage()]);
    }
    exit;
}

// ── download_now ──────────────────────────────────────────────────────────
if ($action === 'download_now') {
    $csrf = $_GET['csrf_token'] ?? '';
    $sess = $_SESSION[CSRF_TOKEN_NAME] ?? '';
    if (!$csrf || !$sess || !hash_equals($sess, $csrf)) { die('Invalid CSRF token'); }
    try {
        $filename = 'backup_' . DB_NAME . '_' . date('Y-m-d_H-i-s') . '.sql';
        $sql = generateSqlDump($pdo);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($sql));
        header('Cache-Control: no-cache');
        echo $sql;
    } catch (Exception $e) { die('Backup failed: ' . $e->getMessage()); }
    exit;
}

// ── download existing ─────────────────────────────────────────────────────
if ($action === 'download') {
    $file = basename($_GET['file'] ?? '');
    $path = $backupDir . $file;
    if (!$file || !file_exists($path) || pathinfo($file, PATHINFO_EXTENSION) !== 'sql') { die('File not found'); }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: no-cache');
    readfile($path);
    exit;
}

// ── delete ────────────────────────────────────────────────────────────────
if ($action === 'delete') {
    $file = basename($_POST['file'] ?? '');
    $path = $backupDir . $file;
    if (!$file || pathinfo($file, PATHINFO_EXTENSION) !== 'sql' || !file_exists($path)) {
        echo json_encode(['success' => false, 'message' => 'File not found']); exit;
    }
    unlink($path);
    echo json_encode(['success' => true, 'message' => 'Backup deleted']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
