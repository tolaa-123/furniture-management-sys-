<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}

require_once __DIR__ . '/../../../config/db_config.php';

// Ensure CSRF constant is available (defined in config.php, fallback here)
if (!defined('CSRF_TOKEN_NAME')) define('CSRF_TOKEN_NAME', 'csrf_token');

$orderId = intval($_GET['order_id'] ?? 0);
$customerId = $_SESSION['user_id'];
$customerName = $_SESSION['first_name'] ?? $_SESSION['user_name'] ?? 'Customer';

// Fetch order details
$order = null;
if ($orderId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM furn_orders WHERE id = ? AND customer_id = ?");
    $stmt->execute([$orderId, $customerId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$order) {
    header('Location: ' . BASE_URL . '/public/customer/my-orders');
    exit();
}

// Calculate remaining balance — use MAX to avoid double-counting duplicate rows
$totalAmount = floatval($order['estimated_cost'] ?? $order['total_amount'] ?? 0);

// Deposit: prefer order column, fall back to MAX single payment row
$depositPaid = floatval($order['deposit_paid'] ?? 0);
if ($depositPaid <= 0) {
    $stmtDep = $pdo->prepare("SELECT COALESCE(MAX(amount), 0) as paid FROM furn_payments WHERE order_id = ? AND payment_type IN ('deposit','prepayment') AND status IN ('approved','verified')");
    $stmtDep->execute([$orderId]);
    $depositPaid = floatval($stmtDep->fetch(PDO::FETCH_ASSOC)['paid']);
}
$depositPaid = min($depositPaid, $totalAmount);
$remainingBalance = $totalAmount - $depositPaid;

// Only redirect if total amount is unknown (not yet estimated)
if ($totalAmount <= 0) {
    header('Location: ' . BASE_URL . '/public/customer/my-orders');
    exit();
}

// If remaining is 0 or less but order is ready_for_delivery, use 60% of total as fallback
if ($remainingBalance <= 0 && ($order['status'] ?? '') === 'ready_for_delivery') {
    $remainingBalance = $totalAmount * 0.6;
}

// Generate CSRF token
if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION[CSRF_TOKEN_NAME];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay Remaining Balance - SmartWorkshop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/css/admin-responsive.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <button class="mobile-menu-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>
    <div class="sidebar-overlay"></div>
    <?php include_once __DIR__ . '/../../includes/customer_sidebar.php'; ?>

    <!-- Top Header -->
    <div class="top-header">
        <div class="header-left">
            <div class="system-status"><i class="fas fa-circle"></i> Customer Portal</div>
        </div>
        <div class="header-right">
            <div class="admin-profile">
                <div class="admin-avatar"><?php echo strtoupper(substr($customerName, 0, 1)); ?></div>
                <div>
                    <div style="font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($customerName); ?></div>
                    <div class="admin-role-badge">CUSTOMER</div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="main-content" style="padding: 30px;">
        <div class="page-header" style="background: white; padding: 25px; border-radius: 15px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
            <h1 style="font-size: 28px; font-weight: 700; color: #2c3e50; margin-bottom: 10px;">
                <i class="fas fa-credit-card me-2"></i>Pay Remaining Balance
            </h1>
            <p style="color: #7f8c8d; font-size: 15px;">Complete your final payment for order <?php echo htmlspecialchars($order['order_number']); ?></p>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div style="background: white; padding: 25px; border-radius: 15px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
                    <h3 style="margin-bottom: 20px; color: #2c3e50;"><i class="fas fa-info-circle me-2"></i>Order Details</h3>
                    <p><strong>Furniture:</strong> <?php echo htmlspecialchars($order['furniture_name']); ?></p>
                    <p><strong>Total Cost:</strong> ETB <?php echo number_format($totalAmount, 2); ?></p>
                    <p><strong>Deposit Paid:</strong> ETB <?php echo number_format($depositPaid, 2); ?></p>
                    <p><strong>Remaining Balance (60%):</strong> <span style="font-size: 24px; color: #e74c3c; font-weight: 700;">ETB <?php echo number_format($remainingBalance, 2); ?></span></p>
                </div>

                <form id="paymentForm" enctype="multipart/form-data" style="background: white; padding: 25px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                    <input type="hidden" name="amount" value="<?php echo $remainingBalance; ?>">
                    <input type="hidden" name="payment_type" value="remaining">
                    
                    <h3 style="margin-bottom: 20px; color: #2c3e50;"><i class="fas fa-money-check me-2"></i>Payment Information</h3>
                    
                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600;">Payment Method *</label>
                        <select name="payment_method" id="paymentMethod" class="form-control" required style="padding: 10px; border: 2px solid #e9ecef; border-radius: 8px;">
                            <option value="">Select Method...</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cash">Cash Payment</option>
                        </select>
                    </div>

                    <!-- Bank Transfer Section -->
                    <div id="bankTransferSection" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label" style="font-weight: 600;">Select Bank *</label>
                            <select name="bank_name" id="bankName" class="form-control" style="padding: 10px; border: 2px solid #e9ecef; border-radius: 8px;">
                                <option value="">Choose Bank...</option>
                            </select>
                            <small class="text-muted d-block mt-2">Available banks will appear in the dropdown above</small>
                        </div>

                        <div id="bankAccountDetails" style="display: none; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 20px; border-radius: 10px; margin-bottom: 20px; border-left: 4px solid #e74c3c;">
                            <h5 style="color: #e74c3c; margin-bottom: 15px;"><i class="fas fa-university me-2"></i>Our Bank Account Details</h5>
                            <div style="background: white; padding: 15px; border-radius: 8px;">
                                <p style="margin: 8px 0;"><strong>Bank Name:</strong> <span id="displayBankName" style="color: #2c3e50; font-weight: 600;">-</span></p>
                                <p style="margin: 8px 0;"><strong>Account Holder:</strong> <span id="displayAccountHolder" style="color: #2c3e50; font-weight: 600;">-</span></p>
                                <p style="margin: 8px 0;"><strong>Account Number:</strong> <span id="displayAccountNumber" style="color: #2c3e50; font-weight: 600; font-size: 18px;">-</span></p>
                            </div>
                            <div style="background: #fff3cd; padding: 10px; border-radius: 5px; margin-top: 10px;">
                                <small><i class="fas fa-info-circle me-1"></i>Please transfer the exact amount and upload the receipt below</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" style="font-weight: 600;">Transaction Reference Number</label>
                            <input type="text" name="transaction_reference" class="form-control" placeholder="Enter transaction/reference number" style="padding: 10px; border: 2px solid #e9ecef; border-radius: 8px;">
                        </div>
                    </div>

                    <!-- Cash Payment Section -->
                    <div id="cashPaymentSection" style="display: none;">
                        <div style="background: #d1ecf1; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #17a2b8;">
                            <h5 style="color: #0c5460;"><i class="fas fa-money-bill-wave me-2"></i>Cash Payment Instructions</h5>
                            <p style="margin: 5px 0;">Please visit our office to make cash payment:</p>
                            <p style="margin: 5px 0;"><strong>Address:</strong> Addis Ababa, Bole Road, Building 123</p>
                            <p style="margin: 5px 0;"><strong>Working Hours:</strong> Mon-Fri: 8:00 AM - 5:00 PM, Sat: 9:00 AM - 1:00 PM</p>
                            <p style="margin: 5px 0;"><strong>Amount:</strong> <span style="font-size: 20px; color: #e74c3c; font-weight: 700;">ETB <?php echo number_format($remainingBalance, 2); ?></span></p>
                        </div>
                    </div>

                    <div class="mb-3" id="receiptUploadSection">
                        <label class="form-label" style="font-weight: 600;">Upload Receipt/Proof of Payment *</label>
                        <input type="file" name="receipt_image" id="receiptImage" class="form-control" accept=".jpg,.jpeg,.png,.pdf" style="padding: 10px; border: 2px solid #e9ecef; border-radius: 8px;">
                        <small class="text-muted">Required: Upload receipt (JPG, PNG, PDF - Max 5MB)</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" style="font-weight: 600;">Additional Notes</label>
                        <textarea name="transaction_notes" class="form-control" rows="3" placeholder="Add any notes about your payment..." style="padding: 10px; border: 2px solid #e9ecef; border-radius: 8px;"></textarea>
                    </div>

                    <button type="submit" id="submitBtn" style="background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); color: white; padding: 12px 30px; border: none; border-radius: 8px; font-weight: 600; width: 100%; cursor: pointer;">
                        <i class="fas fa-paper-plane me-2"></i>Submit Payment
                    </button>
                    <a href="<?php echo BASE_URL; ?>/public/customer/my-orders" style="display: inline-block; width: 100%; margin-top: 10px; background: #6c757d; color: white; padding: 12px 30px; border-radius: 8px; font-weight: 600; text-align: center; text-decoration: none;">
                        <i class="fas fa-arrow-left me-2"></i>Back to Orders
                    </a>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        let bankAccounts = {};

        // Hide receipt upload initially — shown only for bank transfer
        $('#receiptUploadSection').hide();

        // Load bank accounts from database
        $.ajax({
            url: '<?php echo BASE_URL; ?>/public/api/get_bank_accounts.php',
            success: function(response) {
                if (response.success && response.banks) {
                    response.banks.forEach(bank => {
                        bankAccounts[bank.bank_name] = bank;
                    });
                    
                    // Populate bank dropdown
                    const bankSelect = $('#bankName');
                    response.banks.forEach(bank => {
                        bankSelect.append(`<option value="${bank.bank_name}">${bank.bank_name}</option>`);
                    });
                }
            }
        });

        $('#paymentMethod').on('change', function() {
            const method = $(this).val();
            $('#bankTransferSection').hide();
            $('#cashPaymentSection').hide();
            $('#bankAccountDetails').hide();
            $('#bankName').prop('required', false);
            
            if (method === 'bank_transfer') {
                $('#bankTransferSection').show();
                $('#bankName').prop('required', true);
                $('#receiptUploadSection').show();
                $('#receiptImage').prop('required', true);
            } else if (method === 'cash') {
                $('#cashPaymentSection').show();
                $('#receiptUploadSection').hide();
                $('#receiptImage').prop('required', false).val('');
            } else {
                $('#receiptUploadSection').hide();
                $('#receiptImage').prop('required', false);
            }
        });

        $('#bankName').on('change', function() {
            const bankName = $(this).val();
            if (bankName && bankAccounts[bankName]) {
                const bank = bankAccounts[bankName];
                $('#displayBankName').text(bank.bank_name);
                $('#displayAccountHolder').text(bank.account_holder);
                $('#displayAccountNumber').text(bank.account_number);
                $('#bankAccountDetails').slideDown();
            } else {
                $('#bankAccountDetails').slideUp();
            }
        });

        $('#paymentForm').on('submit', function(e) {
            e.preventDefault();
            
            const method = $('#paymentMethod').val();
            if (!method) { alert('Please select a payment method!'); return; }

            const receiptFile = $('#receiptImage')[0].files[0];
            if (method === 'bank_transfer' && !receiptFile) {
                alert('Please upload payment receipt/proof!');
                return;
            }
            
            if (receiptFile && receiptFile.size > 5 * 1024 * 1024) {
                alert('File size must be less than 5MB!');
                return;
            }
            
            const formData = new FormData(this);
            const submitBtn = $('#submitBtn');
            
            submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Processing...');
            
            $.ajax({
                url: '<?php echo BASE_URL; ?>/public/api/submit_remaining_payment.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Payment submitted successfully! Manager will verify your payment shortly.');
                        window.location.href = '<?php echo BASE_URL; ?>/public/customer/my-orders';
                    } else {
                        alert('Error: ' + response.message);
                        submitBtn.prop('disabled', false).html('<i class="fas fa-paper-plane me-2"></i>Submit Payment');
                    }
                },
                error: function(xhr) {
                    let errorMsg = 'Error submitting payment. Please try again.';
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.message) errorMsg = response.message;
                    } catch(e) {}
                    alert(errorMsg);
                    submitBtn.prop('disabled', false).html('<i class="fas fa-paper-plane me-2"></i>Submit Payment');
                }
            });
        });
    </script>
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
