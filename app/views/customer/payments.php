<?php
// Session and authentication already handled by index.php
$customerId = $_SESSION['user_id'] ?? 0;
$customerName = $_SESSION['first_name'] ?? $_SESSION['user_name'] ?? 'Customer';
$pageTitle = 'Order Payments';

// Check if order_id is passed in URL
$orderId = isset($_GET['order_id']) ? intval($_GET['order_id']) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - SmartWorkshop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/css/admin-responsive.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .order-info-card { background: linear-gradient(135deg, #4a2c2a 0%, #3d1f1d 100%); color: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; }
        .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid rgba(255,255,255,0.2); }
        .info-row:last-child { border-bottom: none; }
        .info-label { opacity: 0.8; }
        .info-value { font-weight: 600; }
        .progress-bar-custom { height: 30px; background: #e9ecef; border-radius: 15px; overflow: hidden; margin: 20px 0; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #28a745, #20c997); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; transition: width 1s ease; }
        .file-upload-area { border: 2px dashed #d4a574; border-radius: 10px; padding: 20px; text-align: center; background: #fafafa; cursor: pointer; }
        .file-upload-area:hover { background: #f0f0f0; border-color: #4a2c2a; }
        .btn-pay { background: linear-gradient(135deg, #28a745, #20c997); color: white; border: none; padding: 8px 20px; border-radius: 8px; font-weight: 600; }
        .btn-pay:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(40,167,69,0.3); color: white; }
        .payment-form { display: none; }
        .badge-waiting { background: #ffc107; color: #000; }
        .badge-paid { background: #28a745; color: white; }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" aria-label="Toggle Menu">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay"></div>

    <!-- Sidebar -->
    <?php include_once __DIR__ . '/../../includes/customer_sidebar.php'; ?>

    <!-- Top Header -->
    <?php 
    $pageTitle = 'Order Payments';
    include_once __DIR__ . '/../../includes/customer_header.php'; 
    ?>

    <!-- Main Content -->
    <div class="main-content">
            <h2 style="margin-bottom: 30px; color: #2c3e50;">Order Payments</h2>
            <p style="color: #7f8c8d; margin-bottom: 25px;">Manage payments for your furniture orders. Pay the required deposit to start production and complete the remaining payment after production is finished.</p>

            <!-- Orders Awaiting Payment -->
            <div class="section-card" id="ordersSection">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-clock"></i> Orders Awaiting Payment</h2>
                </div>
                <div class="table-responsive">
                    <table class="data-table" id="ordersTable">
                        <thead>
                            <tr>
                                <th>Order Number</th>
                                <th>Furniture Name</th>
                                <th>Total Cost</th>
                                <th>Deposit Required</th>
                                <th>Amount Paid</th>
                                <th>Remaining Balance</th>
                                <th>Payment Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="8" class="text-center py-4"><div class="spinner-border text-primary"></div><p class="mt-2">Loading orders...</p></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div><!-- end main-content -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo BASE_URL; ?>/public/assets/js/jquery-3.6.0.min.js"></script>
    <script>
        const BASE_URL = '<?php echo BASE_URL; ?>';
        let currentOrder = null;
        let bankAccounts = {};

        // Load bank accounts from database
        function loadBankAccounts() {
            $.ajax({
                url: BASE_URL + '/public/api/get_bank_accounts.php',
                success: function(response) {
                    if (response.success && response.banks) {
                        response.banks.forEach(bank => {
                            bankAccounts[bank.bank_name] = bank;
                        });
                        
                        // Populate bank dropdown
                        const bankSelect = $('select[name="bank_name"]');
                        bankSelect.find('option:not(:first)').remove();
                        response.banks.forEach(bank => {
                            bankSelect.append(`<option value="${bank.bank_name}">${bank.bank_name}</option>`);
                        });
                    }
                }
            });
        }

        $(document).ready(function() {
            loadBankAccounts();
            loadOrders();
            
            // Payment method change
            $('#payment_method').on('change', function() {
                const method = $(this).val();
                if (method === 'bank') {
                    $('#bankFields').show();
                    $('#cashFields').hide();
                } else if (method === 'cash') {
                    $('#bankFields').hide();
                    $('#cashFields').show();
                } else {
                    $('#bankFields').hide();
                    $('#cashFields').hide();
                }
            });
            
            // Bank selection change - using event delegation
            $(document).on('change', '#bankNameSelect', function() {
                const bankName = $(this).val();
                if (bankName && bankAccounts[bankName]) {
                    const bank = bankAccounts[bankName];
                    $('#displayBankName').text(bank.bank_name);
                    $('#displayAccountHolder').text(bank.account_holder);
                    $('#displayAccountNumber').text(bank.account_number);
                    $('#bankDetailsDisplay').slideDown();
                } else {
                    $('#bankDetailsDisplay').slideUp();
                }
            });
        });

        function loadOrders() {
            $.ajax({
                url: BASE_URL + '/public/api/get_payment_orders.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        renderOrders(response.orders);
                    } else {
                        $('#ordersTable tbody').html('<tr><td colspan="8" class="text-center text-danger">Error: ' + (response.message || 'Failed to load orders') + '</td></tr>');
                    }
                },
                error: function(xhr) {
                    let msg = 'Failed to load orders';
                    try { msg = JSON.parse(xhr.responseText).message || msg; } catch(e) {}
                    $('#ordersTable tbody').html('<tr><td colspan="8" class="text-center text-danger">' + msg + '</td></tr>');
                }
            });
        }

        function renderOrders(orders) {
            const tbody = $('#ordersTable tbody');
            tbody.empty();

            if (orders.length === 0) {
                tbody.html('<tr><td colspan="8" class="text-center text-muted py-4">No orders awaiting payment</td></tr>');
                return;
            }

            orders.forEach(order => {
                const totalCost = parseFloat(order.estimated_cost || 0);
                const depositRequired = parseFloat(order.deposit_amount || 0);
                const amountPaid = parseFloat(order.amount_paid || 0);
                const remainingBalance = totalCost - amountPaid;
                
                let statusBadge = '<span class="badge badge-waiting">Waiting Deposit</span>';
                if (amountPaid >= depositRequired && amountPaid < totalCost) {
                    statusBadge = '<span class="badge badge-paid">Deposit Paid</span>';
                } else if (amountPaid >= totalCost) {
                    statusBadge = '<span class="badge badge-paid">Fully Paid</span>';
                }

                const row = `
                    <tr>
                        <td><strong>${order.order_number}</strong></td>
                        <td>${order.furniture_name || 'N/A'}</td>
                        <td>ETB ${totalCost.toFixed(2)}</td>
                        <td>ETB ${depositRequired.toFixed(2)}</td>
                        <td class="text-success">ETB ${amountPaid.toFixed(2)}</td>
                        <td class="text-danger">ETB ${remainingBalance.toFixed(2)}</td>
                        <td>${statusBadge}</td>
                        <td>
                            ${remainingBalance > 0 ? `<button class="btn btn-pay btn-sm" onclick="window.location.href='${BASE_URL}/public/customer/${amountPaid >= depositRequired ? 'pay-remaining' : 'pay-deposit'}?order_id=${order.id}'">Pay</button>` : '<span class="text-success">✓ Paid</span>'}
                        </td>
                    </tr>
                `;
                tbody.append(row);
            });
        }

        function showPaymentForm(order) { /* removed - redirects to pay-deposit/pay-remaining */ }
        function cancelPayment() { /* removed */ }
        function loadPaymentHistory() { /* removed */ }
        function onPaymentTypeChange() { /* removed */ }
        function onPaymentMethodChange() { /* removed */ }
        function showReceiptName() { /* removed */ }

        function renderPaymentHistory(payments) {
            const tbody = $('#historyTable tbody');
            tbody.empty();

            if (payments.length === 0) {
                tbody.html('<tr><td colspan="7" class="text-center text-muted py-4">No payment history</td></tr>');
                return;
            }

            payments.forEach(payment => {
                const pid = payment.payment_id || payment.id || '';
                const statusColor = payment.status === 'approved' ? '#27ae60' : payment.status === 'rejected' ? '#e74c3c' : '#f39c12';
                const statusLabel = payment.status === 'approved' ? 'Approved' : payment.status === 'rejected' ? 'Rejected' : 'Pending';
                const typeLabel = payment.payment_type === 'prepayment' || payment.payment_type === 'deposit' ? 'Deposit'
                                : payment.payment_type === 'full_payment' ? 'Full Payment' : 'Final Payment';
                const methodLabel = payment.payment_method === 'bank' ? 'Bank Transfer' : 'Cash';

                const row = `
                    <tr>
                        <td><strong>PAY-${String(pid).padStart(4, '0')}</strong></td>
                        <td>${payment.order_number}</td>
                        <td>${typeLabel}</td>
                        <td>${methodLabel}</td>
                        <td><strong>ETB ${parseFloat(payment.amount || 0).toFixed(2)}</strong></td>
                        <td><span style="background:${statusColor};color:white;padding:3px 10px;border-radius:10px;font-size:12px;font-weight:600;">${statusLabel}</span></td>
                        <td>${new Date(payment.created_at).toLocaleDateString()}</td>
                    </tr>
                `;
                tbody.append(row);
            });
        }
    </script>
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>