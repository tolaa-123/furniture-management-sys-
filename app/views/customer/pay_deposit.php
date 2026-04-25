<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'customer') {
    header('Location: ' . BASE_URL . '/public/login'); exit();
}
require_once __DIR__ . '/../../../config/db_config.php';
if (!defined('CSRF_TOKEN_NAME')) define('CSRF_TOKEN_NAME', 'csrf_token');

$orderId    = intval($_GET['order_id'] ?? 0);
$customerId = $_SESSION['user_id'];
$customerName = $_SESSION['first_name'] ?? $_SESSION['user_name'] ?? 'Customer';

$order = null;
if ($orderId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM furn_orders WHERE id = ? AND customer_id = ? AND status IN ('cost_estimated','deposit_paid','payment_verified','in_production','production_started','ready_for_delivery')");
    $stmt->execute([$orderId, $customerId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
}
if (!$order) { header('Location: ' . BASE_URL . '/public/customer/my-orders'); exit(); }

if (!isset($_SESSION[CSRF_TOKEN_NAME])) $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
$csrfToken = $_SESSION[CSRF_TOKEN_NAME];

$totalCost   = floatval($order['estimated_cost'] ?? 0);
$depositAmt  = floatval($order['deposit_amount'] ?? ($totalCost * 0.4));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pay – SmartWorkshop</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/css/admin-responsive.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
.pay-card{background:#fff;padding:28px;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.08);margin-bottom:20px;}
.amount-display{font-size:32px;font-weight:800;color:#27AE60;}
.field-label{font-weight:600;font-size:13px;color:#444;margin-bottom:6px;display:block;}
.pay-input{border:2px solid #e9ecef;border-radius:8px;padding:10px 14px;font-family:inherit;font-size:14px;width:100%;transition:border-color .2s;}
.pay-input:focus{border-color:#27AE60;outline:none;}
.bank-details{background:linear-gradient(135deg,#f8f9fa,#e9ecef);padding:18px;border-radius:10px;border-left:4px solid #27AE60;margin-bottom:16px;}
.cash-info{background:#d1ecf1;padding:15px;border-radius:8px;border-left:4px solid #17a2b8;margin-bottom:16px;}
.summary-box{background:linear-gradient(135deg,#2c3e50,#3d1f14);color:#fff;border-radius:12px;padding:20px;}
.sum-row{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid rgba(255,255,255,.15);}
.sum-row:last-child{border-bottom:none;font-size:18px;font-weight:700;padding-top:12px;}
.btn-pay{background:linear-gradient(135deg,#27AE60,#229954);color:#fff;padding:13px 30px;border:none;border-radius:8px;font-weight:700;font-size:15px;width:100%;cursor:pointer;transition:all .25s;}
.btn-pay:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(39,174,96,.4);}
.btn-pay:disabled{opacity:.6;cursor:not-allowed;transform:none;}
</style>
</head>
<body>
<button class="mobile-menu-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>
<div class="sidebar-overlay"></div>
<?php include_once __DIR__ . '/../../includes/customer_sidebar.php'; ?>
<?php $pageTitle = 'Make Payment'; include_once __DIR__ . '/../../includes/customer_header.php'; ?>

<div class="main-content" style="padding:28px;">
    <div class="pay-card">
        <h1 style="font-size:24px;font-weight:700;color:#2c3e50;margin-bottom:6px;"><i class="fas fa-credit-card me-2"></i>Make Payment</h1>
        <p style="color:#7f8c8d;">Order: <strong><?php echo htmlspecialchars($order['order_number']); ?></strong> — <?php echo htmlspecialchars($order['furniture_name']); ?></p>
    </div>

    <div class="row g-4">
    <div class="col-lg-8">
    <form id="paymentForm" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <input type="hidden" name="order_id"   value="<?php echo $order['id']; ?>">
        <input type="hidden" name="amount"      id="hiddenAmount" value="<?php echo $depositAmt; ?>">

        <!-- Step 1: Payment Type -->
        <div class="pay-card">
            <h4 style="color:#2c3e50;margin-bottom:18px;"><i class="fas fa-list-ul me-2" style="color:#27AE60;"></i>Payment Type</h4>
            <label class="field-label">What would you like to pay? *</label>
            <select name="payment_type_choice" id="paymentTypeChoice" class="pay-input" required onchange="onTypeChange()">
                <option value="">Select payment type...</option>
                <option value="deposit">Deposit (40%) — ETB <?php echo number_format($depositAmt, 2); ?></option>
                <option value="full">Full Payment (100%) — ETB <?php echo number_format($totalCost, 2); ?></option>
            </select>
            <div id="typeInfo" style="margin-top:12px;display:none;padding:12px;border-radius:8px;font-size:13px;"></div>
        </div>

        <!-- Step 2: Payment Method -->
        <div class="pay-card">
            <h4 style="color:#2c3e50;margin-bottom:18px;"><i class="fas fa-wallet me-2" style="color:#27AE60;"></i>Payment Method</h4>
            <label class="field-label">How will you pay? *</label>
            <select name="payment_method" id="paymentMethod" class="pay-input" required onchange="onMethodChange()">
                <option value="">Select method...</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="cash">Cash Payment</option>
            </select>

            <!-- Bank Transfer -->
            <div id="bankSection" style="display:none;margin-top:16px;">
                <label class="field-label">Select Bank *</label>
                <select name="bank_name" id="bankName" class="pay-input mb-3">
                    <option value="">Choose bank...</option>
                </select>
                <div id="bankDetails" class="bank-details" style="display:none;">
                    <h6 style="color:#27AE60;margin-bottom:12px;"><i class="fas fa-university me-2"></i>Transfer To</h6>
                    <div style="background:#fff;padding:12px;border-radius:8px;">
                        <p style="margin:6px 0;"><strong>Bank:</strong> <span id="dBank">—</span></p>
                        <p style="margin:6px 0;"><strong>Account Holder:</strong> <span id="dHolder">—</span></p>
                        <p style="margin:6px 0;"><strong>Account Number:</strong> <span id="dAccNum" style="font-size:18px;font-weight:700;color:#2c3e50;">—</span></p>
                    </div>
                    <small style="color:#856404;display:block;margin-top:8px;"><i class="fas fa-info-circle me-1"></i>Transfer the exact amount shown and upload your receipt below.</small>
                </div>
                <label class="field-label">Transaction Reference</label>
                <input type="text" name="transaction_reference" class="pay-input" placeholder="e.g., FT2504202612345">
            </div>

            <!-- Cash -->
            <div id="cashSection" style="display:none;margin-top:16px;">
                <div class="cash-info">
                    <h6 style="color:#0c5460;"><i class="fas fa-money-bill-wave me-2"></i>Cash Payment Instructions</h6>
                    <p style="margin:4px 0;">Visit our office to pay in cash:</p>
                    <p style="margin:4px 0;"><strong>Address:</strong> Addis Ababa, Bole Road, Building 123</p>
                    <p style="margin:4px 0;"><strong>Hours:</strong> Mon–Fri 8:00 AM–5:00 PM, Sat 9:00 AM–1:00 PM</p>
                    <p style="margin:8px 0 0;"><strong>Amount to bring:</strong> <span id="cashAmount" style="font-size:20px;color:#27AE60;font-weight:700;">ETB 0.00</span></p>
                </div>
                <div style="background:#fff3cd;padding:12px;border-radius:8px;font-size:13px;color:#856404;">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    For cash payments, no receipt upload is needed. Our staff will confirm your payment on-site.
                </div>
            </div>
        </div>

        <!-- Receipt Upload (hidden for cash) -->
        <div class="pay-card" id="receiptSection">
            <h4 style="color:#2c3e50;margin-bottom:18px;"><i class="fas fa-file-upload me-2" style="color:#27AE60;"></i>Upload Receipt</h4>
            <label class="field-label">Proof of Payment *</label>
            <input type="file" name="receipt_image" id="receiptImage" class="pay-input" accept=".jpg,.jpeg,.png,.pdf" style="padding:8px;">
            <small class="text-muted">JPG, PNG, PDF — Max 5MB</small>
        </div>

        <!-- Notes -->
        <div class="pay-card">
            <label class="field-label">Additional Notes (optional)</label>
            <textarea name="transaction_notes" class="pay-input" rows="3" placeholder="Any notes about your payment..."></textarea>
        </div>

        <button type="submit" id="submitBtn" class="btn-pay" disabled>
            <i class="fas fa-paper-plane me-2"></i>Submit Payment
        </button>
        <a href="<?php echo BASE_URL; ?>/public/customer/my-orders" style="display:block;text-align:center;margin-top:10px;background:#6c757d;color:#fff;padding:12px;border-radius:8px;font-weight:600;text-decoration:none;">
            <i class="fas fa-arrow-left me-2"></i>Back to Orders
        </a>
    </form>
    </div>

    <!-- Summary Sidebar -->
    <div class="col-lg-4">
        <div class="summary-box">
            <h5 style="color:#d4a574;margin-bottom:16px;"><i class="fas fa-receipt me-2"></i>Payment Summary</h5>
            <div class="sum-row"><span style="opacity:.8;">Total Cost</span><span>ETB <?php echo number_format($totalCost, 2); ?></span></div>
            <div class="sum-row"><span style="opacity:.8;">Deposit (40%)</span><span>ETB <?php echo number_format($depositAmt, 2); ?></span></div>
            <div class="sum-row"><span style="opacity:.8;">Remaining (60%)</span><span>ETB <?php echo number_format($totalCost - $depositAmt, 2); ?></span></div>
            <div class="sum-row"><span>You are paying</span><span class="amount-display" id="summaryAmount">—</span></div>
        </div>
        <div style="background:#fff;border-radius:12px;padding:16px;margin-top:16px;box-shadow:0 2px 8px rgba(0,0,0,.08);">
            <h6 style="color:#2c3e50;margin-bottom:10px;"><i class="fas fa-route me-1"></i> After payment:</h6>
            <div style="font-size:12px;color:#555;line-height:1.8;">
                <?php if ($order['status'] === 'cost_estimated'): ?>
                <div>✓ Manager verifies your payment</div>
                <div>✓ Production begins</div>
                <div>✓ You pay remaining 60% on delivery</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    </div>
</div>

<script src="<?php echo BASE_URL; ?>/public/assets/js/jquery-3.6.0.min.js"></script>
<script>
const TOTAL    = <?php echo $totalCost; ?>;
const DEPOSIT  = <?php echo $depositAmt; ?>;
let bankAccounts = {};
let currentMethod = '';
let currentType   = '';

// Load banks
$.ajax({ url: '<?php echo BASE_URL; ?>/public/api/get_bank_accounts.php', success: function(r) {
    if (r.success && r.banks) {
        r.banks.forEach(b => {
            bankAccounts[b.bank_name] = b;
            $('#bankName').append(`<option value="${b.bank_name}">${b.bank_name}</option>`);
        });
    }
}});

function onTypeChange() {
    currentType = $('#paymentTypeChoice').val();
    const amt = currentType === 'full' ? TOTAL : DEPOSIT;
    $('#hiddenAmount').val(amt);
    $('#summaryAmount').text('ETB ' + amt.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','));
    $('#cashAmount').text('ETB ' + amt.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','));

    const info = document.getElementById('typeInfo');
    if (currentType === 'full') {
        info.style.display = 'block';
        info.style.background = '#d4edda';
        info.style.color = '#155724';
        info.innerHTML = '<i class="fas fa-check-circle me-1"></i><strong>Full Payment:</strong> Pay the entire ETB ' + TOTAL.toFixed(2) + ' now. No further payment needed.';
    } else if (currentType === 'deposit') {
        info.style.display = 'block';
        info.style.background = '#fff3cd';
        info.style.color = '#856404';
        info.innerHTML = '<i class="fas fa-info-circle me-1"></i><strong>Deposit (40%):</strong> Pay ETB ' + DEPOSIT.toFixed(2) + ' now. Remaining ETB ' + (TOTAL - DEPOSIT).toFixed(2) + ' due on delivery.';
    } else {
        info.style.display = 'none';
    }
    checkReady();
}

function onMethodChange() {
    currentMethod = $('#paymentMethod').val();
    $('#bankSection').hide();
    $('#cashSection').hide();
    $('#bankName').prop('required', false);

    if (currentMethod === 'bank_transfer') {
        $('#bankSection').show();
        $('#bankName').prop('required', true);
        $('#receiptSection').show();
        $('#receiptImage').prop('required', true);
    } else if (currentMethod === 'cash') {
        $('#cashSection').show();
        $('#receiptSection').hide();
        $('#receiptImage').prop('required', false).val('');
        const amt = currentType === 'full' ? TOTAL : DEPOSIT;
        $('#cashAmount').text('ETB ' + amt.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','));
    }
    checkReady();
}

$('#bankName').on('change', function() {
    const b = bankAccounts[$(this).val()];
    if (b) {
        $('#dBank').text(b.bank_name);
        $('#dHolder').text(b.account_holder);
        $('#dAccNum').text(b.account_number);
        $('#bankDetails').slideDown();
    } else {
        $('#bankDetails').slideUp();
    }
    checkReady();
});

function checkReady() {
    const ready = currentType && currentMethod;
    $('#submitBtn').prop('disabled', !ready);
}

$('#paymentForm').on('submit', function(e) {
    e.preventDefault();
    const method = currentMethod;
    const type   = currentType;
    if (!type || !method) { alert('Please select payment type and method.'); return; }

    // Receipt required only for bank transfer
    if (method === 'bank_transfer') {
        const f = $('#receiptImage')[0].files[0];
        if (!f) { alert('Please upload your payment receipt.'); return; }
        if (f.size > 5 * 1024 * 1024) { alert('File must be under 5MB.'); return; }
        
        // Validate transaction reference format
        const ref = $('input[name="transaction_reference"]').val().trim();
        if (!ref) {
            alert('Please enter transaction reference number.');
            return;
        }
        const refPattern = /^[A-Za-z]{2,}[0-9]{6,}$/;
        if (!refPattern.test(ref)) {
            alert('Invalid transaction reference format. Must be letters followed by numbers (e.g., FT2504202612345)');
            return;
        }
    }

    const btn = $('#submitBtn');
    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Processing...');

    // Choose correct API based on payment type
    const apiUrl = type === 'full'
        ? '<?php echo BASE_URL; ?>/public/api/submit_full_payment.php'
        : '<?php echo BASE_URL; ?>/public/api/submit_deposit_payment.php';

    $.ajax({
        url: apiUrl, method: 'POST',
        data: new FormData(this),
        processData: false, contentType: false, dataType: 'json',
        success: function(r) {
            if (r.success) {
                alert('Payment submitted! Manager will verify shortly.');
                window.location.href = '<?php echo BASE_URL; ?>/public/customer/my-orders';
            } else {
                alert('Error: ' + r.message);
                btn.prop('disabled', false).html('<i class="fas fa-paper-plane me-2"></i>Submit Payment');
            }
        },
        error: function() {
            alert('Network error. Please try again.');
            btn.prop('disabled', false).html('<i class="fas fa-paper-plane me-2"></i>Submit Payment');
        }
    });
});
</script>
<script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
