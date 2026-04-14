<?php
/**
 * View SMS Log (Admin only)
 * Shows all SMS that would be sent (test mode) or were sent (live mode)
 */
require_once '../../config/db_config.php';

header('Content-Type: application/json');

// Check if admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$logFile = __DIR__ . '/../../logs/sms.log';

if (!file_exists($logFile)) {
    echo json_encode(['success' => true, 'logs' => [], 'message' => 'No SMS logs yet']);
    exit;
}

$content = file_get_contents($logFile);
if (empty($content)) {
    echo json_encode(['success' => true, 'logs' => [], 'message' => 'SMS log is empty']);
    exit;
}

// Parse log entries
$entries = [];
$lines = explode("\n", $content);
$currentEntry = '';

foreach ($lines as $line) {
    if (strpos($line, '[') === 0 && strpos($line, ']') !== false) {
        // New entry starts
        if (!empty($currentEntry)) {
            $entries[] = trim($currentEntry);
        }
        $currentEntry = $line;
    } elseif (strpos($line, '---') === false) {
        $currentEntry .= "\n" . $line;
    }
}

if (!empty($currentEntry)) {
    $entries[] = trim($currentEntry);
}

// Get last 50 entries, most recent first
$entries = array_slice(array_reverse($entries), 0, 50);

echo json_encode([
    'success' => true,
    'logs' => $entries,
    'count' => count($entries),
    'mode' => 'TEST MODE (SMS logged to file, not actually sent)'
]);
