<?php
/**
 * Database Backup API
 * Actions: save, download_now, download (existing file), delete
 */
ob_start();
require_once '../../config/db_config.php';
ob_end_clean();

if (session_status() === PHP_SESSION_NONE) session_start();

// Auth — admin only
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$backupDir = __DIR__ . '/../../backups/';
if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);

// Protect backup directory from direct web access
if (!file_exists($backupDir . '.htaccess')) {
    file_put_contents($backupDir . '.htaccess', "Order deny,allow\nDeny from all\n");
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// CSRF check for POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    $sess = $_SESSION[CSRF_TOKEN_NAME] ?? '';
    if (!$csrf || !$sess || !hash_equals($sess, $csrf)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']); exit;
    }
}

// ── Generate SQL dump ──────────────────────────────────────────────────────
function generateSqlDump($pdo, $dbName) {
    $sql  = "-- FurnitureCraft ERP Database Backup\n";
    $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Database: $dbName\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
    $sql .= "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";
    $sql .= "SET NAMES utf8mb4;\n\n";

    // Get all tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        // DROP + CREATE
        $createStmt = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
        $sql .= "DROP TABLE IF EXISTS `$table`;\n";
        $sql .= $createStmt[1] . ";\n\n";

        // Data
        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_NUM);
        if (!empty($rows)) {
            $sql .= "INSERT INTO `$table` VALUES\n";
            $rowSqls = [];
            foreach ($rows as $row) {
                $vals = array_map(function($v) use ($pdo) {
                    if ($v === null) return 'NULL';
                    return "'" . addslashes($v) . "'";
                }, $row);
                $rowSqls[] = '(' . implode(',', $vals) . ')';
            }
            $sql .= implode(",\n", $rowSqls) . ";\n\n";
        }
    }

    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $sql;
}

// ── Actions ────────────────────────────────────────────────────────────────

if ($action === 'save') {
    header('Content-Type: application/json');
    try {
        $filename = 'backup_' . DB_NAME . '_' . date('Y-m-d_H-i-s') . '.sql';
        $sql = generateSqlDump($pdo, DB_NAME);
        file_put_contents($backupDir . $filename, $sql);
        $size = round(filesize($backupDir . $filename) / 1024, 1);
        echo json_encode(['success' => true, 'message' => "Backup saved: $filename ($size KB)"]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Backup failed: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'download_now') {
    // CSRF via GET param for direct link
    $csrf = $_GET['csrf_token'] ?? '';
    $sess = $_SESSION[CSRF_TOKEN_NAME] ?? '';
    if (!$csrf || !$sess || !hash_equals($sess, $csrf)) {
        die('Invalid CSRF token');
    }
    try {
        $filename = 'backup_' . DB_NAME . '_' . date('Y-m-d_H-i-s') . '.sql';
        $sql = generateSqlDump($pdo, DB_NAME);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($sql));
        header('Cache-Control: no-cache');
        echo $sql;
    } catch (Exception $e) {
        die('Backup failed: ' . $e->getMessage());
    }
    exit;
}

if ($action === 'download') {
    $file = basename($_GET['file'] ?? '');
    $path = $backupDir . $file;
    if (!$file || !file_exists($path) || pathinfo($file, PATHINFO_EXTENSION) !== 'sql') {
        die('File not found');
    }
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: no-cache');
    readfile($path);
    exit;
}

if ($action === 'delete') {
    header('Content-Type: application/json');
    $file = basename($_POST['file'] ?? '');
    $path = $backupDir . $file;
    if (!$file || pathinfo($file, PATHINFO_EXTENSION) !== 'sql' || !file_exists($path)) {
        echo json_encode(['success' => false, 'message' => 'File not found']); exit;
    }
    unlink($path);
    echo json_encode(['success' => true, 'message' => 'Backup deleted']);
    exit;
}

header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Unknown action']);
