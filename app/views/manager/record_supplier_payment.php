<?php
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['manager', 'admin'])) {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}
require_once __DIR__ . '/../../../config/db_config.php';

$csrf_token = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;
$managerId = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error_message'] = 'Invalid CSRF token.';
        header('Location: ' . BASE_URL . '/public/manager/record-supplier-payment');
        exit();
    }
    
    $invoice_id = intval($_POST['invoice_id']);
    $amount = floatval($_POST['amount']);
    $payment_date = trim($_POST['payment_date']);
    $payment_method = trim($_POST['payment_method']);
    $reference_number = trim($_POST['reference_number'] ?? '');
    $bank_name = trim($_POST['bank_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    // Validate
    if ($invoice_id <= 0 || $amount <= 0 || empty($payment_date) || empty($payment_method)) {
        $_SESSION['error_message'] = 'Please fill all required fields.';
        header('Location: ' . BASE_URL . '/public/manager/record-supplier-payment');
        exit();
    }
    
    try {
        // Get invoice details
        $stmt = $pdo->prepare("SELECT * FROM furn_supplier_invoices WHERE id = ?");
        $stmt->execute([$invoice_id]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$invoice) {
            $_SESSION['error_message'] = 'Invoice not found.';
            header('Location: ' . BASE_URL . '/public/manager/record-supplier-payment');
            exit();
        }
        
        if ($amount > $invoice['balance_due']) {
            $_SESSION['error_message'] = 'Payment amount cannot exceed balance due (ETB ' . number_format($invoice['balance_due'], 2) . ').';
            header('Location: ' . BASE_URL . '/public/manager/record-supplier-payment?invoice_id=' . $invoice_id);
            exit();
        }
        
        $pdo->beginTransaction();
        
        // Insert payment
        $stmt = $pdo->prepare("
            INSERT INTO furn_supplier_payments 
            (invoice_id, supplier_name, payment_date, amount, payment_method, reference_number, bank_name, account_number, notes, paid_by, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'verified')
        ");
        $stmt->execute([$invoice_id, $invoice['supplier_name'], $payment_date, $amount, $payment_method, $reference_number, $bank_name, $account_number, $notes, $managerId]);
        
        // Update invoice
        $new_paid = $invoice['paid_amount'] + $amount;
        $new_balance = $invoice['total_amount'] - $new_paid;
        $new_status = ($new_balance <= 0.01) ? 'paid' : $invoice['status'];
        
        $stmt = $pdo->prepare("
            UPDATE furn_supplier_invoices 
            SET paid_amount = ?, balance_due = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$new_paid, $new_balance, $new_status, $invoice_id]);
        
        $pdo->commit();
        $_SESSION['success_message'] = "Payment of ETB " . number_format($amount, 2) . " recorded successfully!";
        header('Location: ' . BASE_URL . '/public/manager/supplier-payments');
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error recording payment: " . $e->getMessage();
        header('Location: ' . BASE_URL . '/public/manager/record-supplier-payment');
        exit();
    }
}

// Get invoice_id from query string
$selected_invoice_id = intval($_GET['invoice_id'] ?? 0);

// Fetch outstanding invoices
$invoices = [];
try {
    $stmt = $pdo->query("
        SELECT id, invoice_number, supplier_name, total_amount, paid_amount, balance_due, due_date
        FROM furn_supplier_invoices
        WHERE status IN ('pending', 'approved', 'overdue') AND balance_due > 0
        ORDER BY due_date ASC
    ");
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Invoices error: " . $e->getMessage());
}

$pageTitle = 'Record Supplier Payment';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - FurnitureCraft</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/css/admin-responsive.css">
</head>
<body>
<button class="mobile-menu-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>
<div class="sidebar-overlay"></div>
<?php include_once __DIR__ . '/../../includes/manager_sidebar.php'; ?>
<?php include_once __DIR__ . '/../../includes/manager_header.php'; ?>

<div class="main-content">
    <?php if (isset($_SESSION['error_message'])): ?>
        <div style="background:#f8d7da;color:#721c24;padding:15px;border-radius:8px;margin-bottom:20px;border:1px solid #f5c6cb;">
            <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>

    <div class="section-card">
        <div class="section-header">
            <h2 class="section-title"><i class="fas fa-money-bill-wave"></i> Record Supplier Payment</h2>
            <a href="<?php echo BASE_URL; ?>/public/manager/supplier-payments" class="btn-action btn-secondary-custom">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>

        <?php if (empty($invoices)): ?>
            <p style="text-align:center;padding:40px;color:#aaa;">
                <i class="fas fa-check-circle" style="font-size:48px;margin-bottom:20px;display:block;color:#27AE60;"></i>
                No outstanding invoices to pay.
            </p>
        <?php else: ?>
        <form method="POST" action="" style="max-width:700px;">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="form-group" style="margin-bottom:20px;">
                <label>Select Invoice <span style="color:red;">*</span></label>
                <select name="invoice_id" id="invoiceSelect" class="form-control" required onchange="updateInvoiceDetails()">
                    <option value="">-- Select Invoice --</option>
                    <?php foreach ($invoices as $inv): ?>
                        <option value="<?php echo $inv['id']; ?>" 
                                data-supplier="<?php echo htmlspecialchars($inv['supplier_name']); ?>"
                                data-balance="<?php echo $inv['balance_due']; ?>"
                                data-total="<?php echo $inv['total_amount']; ?>"
                                data-paid="<?php echo $inv['paid_amount']; ?>"
                                <?php echo ($selected_invoice_id == $inv['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($inv['invoice_number']); ?> - <?php echo htmlspecialchars($inv['supplier_name']); ?> 
                            (Balance: ETB <?php echo number_format($inv['balance_due'], 2); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="invoiceDetails" style="background:#f8f9fa;padding:15px;border-radius:8px;margin-bottom:20px;display:none;">
                <h4 style="margin:0 0 10px 0;">Invoice Details</h4>
                <p style="margin:5px 0;"><strong>Supplier:</strong> <span id="detailSupplier">-</span></p>
                <p style="margin:5px 0;"><strong>Total Amount:</strong> <span id="detailTotal">-</span></p>
                <p style="margin:5px 0;"><strong>Paid Amount:</strong> <span id="detailPaid">-</span></p>
                <p style="margin:5px 0;"><strong>Balance Due:</strong> <span id="detailBalance" style="color:#E74C3C;font-weight:600;">-</span></p>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
                <div class="form-group">
                    <label>Payment Amount (ETB) <span style="color:red;">*</span></label>
                    <input type="number" step="0.01" name="amount" id="paymentAmount" class="form-control" placeholder="0.00" required>
                </div>
                
                <div class="form-group">
                    <label>Payment Date <span style="color:red;">*</span></label>
                    <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:20px;">
                <label>Payment Method <span style="color:red;">*</span></label>
                <select name="payment_method" id="paymentMethod" class="form-control" required onchange="toggleBankFields()">
                    <option value="">-- Select Method --</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="cash">Cash</option>
                    <option value="check">Check</option>
                    <option value="mobile_money">Mobile Money</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <div id="bankFields" style="display:none;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
                    <div class="form-group">
                        <label>Bank Name</label>
                        <input type="text" name="bank_name" class="form-control" placeholder="e.g., Commercial Bank of Ethiopia">
                    </div>
                    
                    <div class="form-group">
                        <label>Account Number</label>
                        <input type="text" name="account_number" class="form-control" placeholder="Account number">
                    </div>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:20px;">
                <label>Reference Number</label>
                <input type="text" name="reference_number" class="form-control" placeholder="Transaction reference or check number">
            </div>

            <div class="form-group" style="margin-bottom:20px;">
                <label>Notes</label>
                <textarea name="notes" class="form-control" rows="3" placeholder="Additional notes or comments"></textarea>
            </div>

            <div style="display:flex;gap:10px;">
                <button type="submit" name="record_payment" class="btn-action btn-success-custom">
                    <i class="fas fa-save"></i> Record Payment
                </button>
                <a href="<?php echo BASE_URL; ?>/public/manager/supplier-payments" class="btn-action btn-secondary-custom">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
function updateInvoiceDetails() {
    const select = document.getElementById('invoiceSelect');
    const option = select.options[select.selectedIndex];
    const details = document.getElementById('invoiceDetails');
    
    if (option.value) {
        document.getElementById('detailSupplier').textContent = option.dataset.supplier;
        document.getElementById('detailTotal').textContent = 'ETB ' + parseFloat(option.dataset.total).toFixed(2);
        document.getElementById('detailPaid').textContent = 'ETB ' + parseFloat(option.dataset.paid).toFixed(2);
        document.getElementById('detailBalance').textContent = 'ETB ' + parseFloat(option.dataset.balance).toFixed(2);
        document.getElementById('paymentAmount').value = parseFloat(option.dataset.balance).toFixed(2);
        document.getElementById('paymentAmount').max = parseFloat(option.dataset.balance).toFixed(2);
        details.style.display = 'block';
    } else {
        details.style.display = 'none';
        document.getElementById('paymentAmount').value = '';
        document.getElementById('paymentAmount').removeAttribute('max');
    }
}

function toggleBankFields() {
    const method = document.getElementById('paymentMethod').value;
    const bankFields = document.getElementById('bankFields');
    bankFields.style.display = (method === 'bank_transfer' || method === 'check') ? 'block' : 'none';
}

// Initialize on page load
if (document.getElementById('invoiceSelect').value) {
    updateInvoiceDetails();
}
</script>
<script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
