<?php
session_start();
require_once '../../config/db_config.php';
require_once '../../app/utils/ErrorLogger.php';
require_once '../../app/utils/Validator.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    ErrorLogger::logWarning('Unauthorized payment submission attempt', [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_id' => $_SESSION['user_id'] ?? 'none',
    ]);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $customerId = $_SESSION['user_id'];
    $orderId = $_POST['order_id'] ?? null;
    $paymentType = $_POST['payment_type'] ?? '';
    $paymentMethod = $_POST['payment_method'] ?? '';
    $paymentNotes = $_POST['payment_notes'] ?? '';
    
    // Ensure ENUM supports full_payment (MUST be before transaction to avoid implicit commit)
    if ($paymentType === 'full_payment') {
        try { 
            $pdo->exec("ALTER TABLE furn_payments MODIFY COLUMN payment_type ENUM('prepayment','postpayment','deposit','final','final_payment','full_payment') NOT NULL DEFAULT 'prepayment'"); 
        } catch(PDOException $e2) {
            // Column might already have this ENUM value, continue
        }
    }
    
    // Validate input
    $validator = new Validator();
    $rules = [
        'order_id' => ['required', 'integer'],
        'payment_type' => ['required', 'in:prepayment,postpayment,final_payment,deposit,final,full_payment'],
        'payment_method' => ['required', 'in:cash,bank,mobile,bank_transfer'],
        'payment_notes' => ['max:500'],
    ];
    
    if (!$validator->validate($_POST, $rules)) {
        ErrorLogger::logWarning('Payment validation failed', [
            'customer_id' => $customerId,
            'order_id' => $orderId,
            'errors' => $validator->getErrors(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);
        throw new Exception('Validation failed: ' . implode(', ', $validator->getErrors()));
    }
    
    if (!$orderId || !$paymentType || !$paymentMethod) {
        throw new Exception('Missing required fields');
    }
    
    // Convert payment type to database format
    $dbPaymentType = $paymentType;
    if ($paymentType === 'deposit') {
        $dbPaymentType = 'prepayment';
    } elseif ($paymentType === 'final' || $paymentType === 'final_payment' || $paymentType === 'postpayment') {
        $dbPaymentType = 'postpayment';
    } elseif ($paymentType === 'full_payment') {
        $dbPaymentType = 'full_payment';
    }
    
    // Get order details
    $stmt = $pdo->prepare("SELECT * FROM furn_orders WHERE id = ? AND customer_id = ?");
    $stmt->execute([$orderId, $customerId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Order not found');
    }
    
    // Calculate amount based on payment type
    $totalCost = floatval($order['estimated_cost']);
    $depositAmount = floatval($order['deposit_amount']);
    
    // Get already paid amount
    $stmtPaid = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as paid FROM furn_payments WHERE order_id = ? AND status = 'approved'");
    $stmtPaid->execute([$orderId]);
    $amountPaid = floatval($stmtPaid->fetch(PDO::FETCH_ASSOC)['paid']);
    
    $amount = 0;
    if ($paymentType === 'prepayment') {
        $amount = $depositAmount;
    } else if ($paymentType === 'postpayment' || $paymentType === 'final_payment') {
        $amount = $totalCost - $amountPaid;
    } else if ($paymentType === 'full_payment') {
        $amount = $totalCost; // full amount regardless of what's been paid
    }
    
    // Handle file upload for bank transfer
    $receiptFile = null;
    $bankName = null;
    $transactionReference = null;
    $transferDate = null;
    
    if ($paymentMethod === 'bank') {
        $bankName = $_POST['bank_name'] ?? '';
        $transactionReference = $_POST['transaction_reference'] ?? '';
        $transferDate = $_POST['transfer_date'] ?? '';
        
        if (!$bankName) {
            throw new Exception('Please select a bank');
        }
        if (!$transactionReference) {
            throw new Exception('Please enter transaction reference number');
        }
        // Validate format: must be combination of letters and numbers (e.g., FT2504202612345)
        if (!preg_match('/^[A-Za-z]{2,}[0-9]{6,}$/', $transactionReference)) {
            throw new Exception('Invalid transaction reference format. Must be letters followed by numbers (e.g., FT2504202612345)');
        }
        if (!$transferDate) {
            throw new Exception('Please enter transfer date');
        }
        
        if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
            $filename = $_FILES['receipt_file']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (!in_array($ext, $allowed)) {
                throw new Exception('Invalid file type. Allowed: JPG, PNG, PDF');
            }
            
            if ($_FILES['receipt_file']['size'] > 5 * 1024 * 1024) {
                throw new Exception('File too large. Maximum 5MB allowed');
            }
            
            $uploadDir = __DIR__ . '/../uploads/receipts/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $newFilename = uniqid() . '_' . time() . '.' . $ext;
            $uploadPath = $uploadDir . $newFilename;
            
            if (!move_uploaded_file($_FILES['receipt_file']['tmp_name'], $uploadPath)) {
                throw new Exception('File upload failed');
            }
            
            $receiptFile = 'uploads/receipts/' . $newFilename;
        } else {
            throw new Exception('Please upload receipt file for bank transfer');
        }
    }
    
    $paymentDate = $paymentMethod === 'cash' ? ($_POST['payment_date'] ?? date('Y-m-d')) : $transferDate;
    
    // Insert payment
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("
        INSERT INTO furn_payments (
            order_id, customer_id, payment_type, payment_method,
            amount, transaction_reference, bank_name,
            payment_date, payment_notes, receipt_file, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    
    $stmt->execute([
        $orderId, $customerId, $dbPaymentType, $paymentMethod,
        $amount, $transactionReference, $bankName,
        $paymentDate, $paymentNotes, $receiptFile
    ]);
    
    $paymentId = $pdo->lastInsertId();
    
    // Update receipt file if it exists (already included in insert above)

    // Update order status to reflect payment submitted
    try {
        if ($dbPaymentType === 'prepayment') {
            $pdo->prepare("UPDATE furn_orders SET status = 'deposit_paid' WHERE id = ? AND status NOT IN ('in_production','production_started','production_completed','completed')")
               ->execute([$orderId]);
        } elseif ($dbPaymentType === 'postpayment') {
            $pdo->prepare("UPDATE furn_orders SET status = 'final_payment_paid' WHERE id = ? AND status NOT IN ('completed')")
               ->execute([$orderId]);
        } elseif ($dbPaymentType === 'full_payment') {
            $pdo->prepare("UPDATE furn_orders SET status = 'final_payment_paid' WHERE id = ? AND status NOT IN ('completed')")
               ->execute([$orderId]);
        }
    } catch (Exception $e) {
        error_log("Order status update after payment failed: " . $e->getMessage());
    }

    $pdo->commit();
    
    ErrorLogger::logInfo('Payment submitted successfully', [
        'payment_id' => $paymentId,
        'customer_id' => $customerId,
        'order_id' => $orderId,
        'amount' => $amount,
        'payment_method' => $paymentMethod,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment submitted successfully',
        'payment_id' => $paymentId
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    ErrorLogger::logError('Payment submission failed', 400, [
        'customer_id' => $customerId ?? 'unknown',
        'order_id' => $orderId ?? 'unknown',
        'message' => $e->getMessage(),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    ]);
    
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
