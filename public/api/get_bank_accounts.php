<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db_config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Only logged-in users can fetch bank accounts
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

try {
    $stmt = $pdo->query("SELECT id, bank_name, account_number, account_holder FROM furn_bank_accounts ORDER BY bank_name");
    $banks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode([
        'success' => true,
        'banks' => $banks
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
