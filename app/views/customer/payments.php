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
                <div class="section-title"><i class="fas fa-clock me-2"></i>Orders Awaiting Payment</div>
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

            <!-- Payment Form (Hidden by default) -->
            <div class="section-card payment-form" id="paymentForm">
                <div class="section-title"><i class="fas fa-money-bill-wave me-2"></i>Make Payment</div>
                
                <!-- Order Information -->
                <div class="order-info-card" id="orderInfo"></div>

                <!-- Payment Progress -->
                <div class="progress-bar-custom">
                    <div class="progress-fill" id="progressBar" style="width: 0%">0% Paid</div>
                </div>

                <form id="paymentSubmitForm" enctype="multipart/form-data">
                    <input type="hidden" name="order_id" id="order_id">
                    <input type="hidden" name="customer_id" value="<?php echo $customerId; ?>">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Type <span class="required">*</span></label>
                            <select class="form-select" name="payment_type" id="payment_type" required onchange="onPaymentTypeChange()">
                                <option value="">Select Payment Type...</option>
                                <option value="prepayment">Pre Payment (Deposit 40%)</option>
                                <option value="postpayment">Post Payment (Remaining 60%)</option>
                                <option value="full_payment">Full Payment (100% at once)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Method <span class="required">*</span></label>
                            <select class="form-select" name="payment_method" id="payment_method" required onchange="onPaymentMethodChange()">
                                <option value="">Select Method...</option>
                                <option value="bank">Bank Transfer</option>
                                <option value="cash">Cash Payment</option>
                            </select>
                        </div>
                    </div>

                    <!-- Bank Transfer Fields -->
                    <div id="bankFields" style="display: none;">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Bank Name <span class="required">*</span></label>
                                <select class="form-select" name="bank_name" id="bankNameSelect">
                                    <option value="">Select Bank...</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Transaction Reference <span class="required">*</span></label>
                                <input type="text" class="form-control" name="transaction_reference" placeholder="e.g., TXN123456789">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Transfer Date <span class="required">*</span></label>
                                <input type="date" class="form-control" name="transfer_date" max="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Upload Receipt <span class="required">*</span></label>
                                <div class="file-upload-area" onclick="document.getElementById('receiptFile').click()">
                                    <i class="fas fa-cloud-upload-alt" style="font-size: 24px; color: #d4a574;"></i>
                                    <p class="mb-0 mt-2">Click to upload receipt (JPG, PNG, PDF)</p>
                                    <p class="file-name text-success" id="receiptFileName"></p>
                                </div>
                                <input type="file" id="receiptFile" name="receipt_file" accept=".jpg,.jpeg,.png,.pdf" style="display: none;" onchange="showReceiptName()">
                            </div>
                        </div>

                        <!-- Bank Account Details Display -->
                        <div id="bankDetailsDisplay" style="display: none; background: linear-gradient(135deg, #4a2c2a 0%, #3d1f1d 100%); padding: 25px; border-radius: 14px; margin: 25px 0; box-shadow: 0 6px 20px rgba(74, 44, 42, 0.2);">
                            <h5 style="color: #d4a574; margin-bottom: 20px; font-size: 18px; font-weight: 700;"><i class="fas fa-university me-2"></i>Bank Account Details for Transfer</h5>
                            <div style="background: white; padding: 20px; border-radius: 10px; border-left: 5px solid #d4a574;">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px;">
                                    <div>
                                        <p style="margin: 0 0 8px 0; font-size: 12px; color: #7f8c8d; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Bank Name</p>
                                        <p style="margin: 0; color: #2c3e50; font-weight: 700; font-size: 16px;" id="displayBankName">-</p>
                                    </div>
                                    <div>
                                        <p style="margin: 0 0 8px 0; font-size: 12px; color: #7f8c8d; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Account Holder</p>
                                        <p style="margin: 0; color: #2c3e50; font-weight: 700; font-size: 16px;" id="displayAccountHolder">-</p>
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px;">
                                    <div>
                                        <p style="margin: 0 0 8px 0; font-size: 12px; color: #7f8c8d; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Account Number</p>
                                        <p style="margin: 0; color: #2c3e50; font-weight: 700; font-size: 18px; font-family: 'Courier New', monospace; letter-spacing: 1px;" id="displayAccountNumber">-</p>
                                    </div>
                                    <div>
                                        <p style="margin: 0 0 8px 0; font-size: 12px; color: #7f8c8d; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">SWIFT Code</p>
                                        <p style="margin: 0; color: #2c3e50; font-weight: 700; font-size: 16px; font-family: 'Courier New', monospace;" id="displaySwiftCode">-</p>
                                    </div>
                                </div>
                                <div style="margin-bottom: 15px;">
                                    <p style="margin: 0 0 8px 0; font-size: 12px; color: #7f8c8d; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Bank Address</p>
                                    <p style="margin: 0; color: #2c3e50; font-weight: 600; font-size: 14px; line-height: 1.6;" id="displayBankAddress">-</p>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; padding-top: 15px; border-top: 2px solid #e9ecef;">
                                    <div>
                                        <p style="margin: 0 0 8px 0; font-size: 12px; color: #7f8c8d; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Phone</p>
                                        <p style="margin: 0; color: #2c3e50; font-weight: 600; font-size: 14px;" id="displayPhone">-</p>
                                    </div>
                                    <div>
                                        <p style="margin: 0 0 8px 0; font-size: 12px; color: #7f8c8d; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Email</p>
                                        <p style="margin: 0; color: #2c3e50; font-weight: 600; font-size: 14px;" id="displayEmail">-</p>
                                    </div>
                                </div>
                            </div>
                            <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-top: 15px; border-left: 4px solid #ffc107;">
                                <p style="margin: 0; color: #856404; font-size: 13px;"><i class="fas fa-info-circle me-2"></i><strong>Important:</strong> Please ensure you transfer the exact amount to this account and keep the receipt for verification.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Cash Payment Info (shown for cash, no receipt needed) -->
                    <div id="cashFields" style="display:none;">
                    <div id="cashInfoBox" style="background:#d1ecf1;padding:14px;border-radius:8px;border-left:4px solid #17a2b8;margin-bottom:16px;">
                        <h6 style="color:#0c5460;margin-bottom:6px;"><i class="fas fa-money-bill-wave me-2"></i>Cash Payment</h6>
                        <p style="margin:4px 0;font-size:13px;">Visit our office to pay in cash. No receipt upload needed — our staff will confirm on-site.</p>
                        <p style="margin:4px 0;font-size:13px;"><strong>Amount to bring:</strong> <span id="cashAmountDisplay" style="color:#27AE60;font-weight:700;font-size:16px;">ETB 0.00</span></p>
                    </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Amount Paid <span class="required">*</span></label>
                                <input type="number" class="form-control" name="amount_paid" id="amount_paid" step="0.01" min="0" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Date <span class="required">*</span></label>
                                <input type="date" class="form-control" name="payment_date" max="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>
                    </div><!-- end #cashFields -->
                        <label class="form-label">Payment Notes (Optional)</label>
                        <textarea class="form-control" name="payment_notes" rows="3" placeholder="Any additional notes about this payment..."></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-pay"><i class="fas fa-check-circle me-2"></i>Submit Payment</button>
                        <button type="button" class="btn btn-secondary" onclick="cancelPayment()"><i class="fas fa-arrow-left me-2"></i>Back to Orders</button>
                    </div>
                </form>
            </div>

            <!-- Payment History -->
            <div class="section-card">
                <div class="section-title"><i class="fas fa-history me-2"></i>Payment History</div>
                <div class="table-responsive">
                    <table class="data-table" id="historyTable">
                        <thead>
                            <tr>
                                <th>Payment ID</th>
                                <th>Order Number</th>
                                <th>Payment Type</th>
                                <th>Method</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="7" class="text-center py-4"><div class="spinner-border text-primary"></div><p class="mt-2">Loading payment history...</p></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div><!-- end main-content -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
            loadPaymentHistory();
            
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
                    $('#displaySwiftCode').text(bank.swift_code || 'N/A');
                    $('#displayBankAddress').text(bank.bank_address || 'N/A');
                    $('#displayPhone').text(bank.phone || 'N/A');
                    $('#displayEmail').text(bank.email || 'N/A');
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
                            ${remainingBalance > 0 ? `<button class="btn btn-pay btn-sm" onclick='showPaymentForm(${JSON.stringify(order)})'>Pay</button>` : '<span class="text-success">✓ Paid</span>'}
                        </td>
                    </tr>
                `;
                tbody.append(row);
            });
        }

        function showPaymentForm(order) {
            currentOrder = order;
            $('#ordersSection').hide();
            $('#paymentForm').show();

            const totalCost = parseFloat(order.estimated_cost || 0);
            const depositRequired = parseFloat(order.deposit_amount || 0);
            const amountPaid = parseFloat(order.amount_paid || 0);
            const remainingBalance = totalCost - amountPaid;
            const progressPercent = (amountPaid / totalCost * 100).toFixed(0);

            $('#order_id').val(order.id);
            $('#progressBar').css('width', progressPercent + '%').text(progressPercent + '% Paid');

            const orderInfoHTML = `
                <div class="info-row"><span class="info-label">Order Number:</span><span class="info-value">${order.order_number}</span></div>
                <div class="info-row"><span class="info-label">Furniture:</span><span class="info-value">${order.furniture_name || 'N/A'}</span></div>
                <div class="info-row"><span class="info-label">Total Cost:</span><span class="info-value">ETB ${totalCost.toFixed(2)}</span></div>
                <div class="info-row"><span class="info-label">Deposit Required:</span><span class="info-value">ETB ${depositRequired.toFixed(2)}</span></div>
                <div class="info-row"><span class="info-label">Amount Paid:</span><span class="info-value">ETB ${amountPaid.toFixed(2)}</span></div>
                <div class="info-row"><span class="info-label">Remaining Balance:</span><span class="info-value">ETB ${remainingBalance.toFixed(2)}</span></div>
            `;
            $('#orderInfo').html(orderInfoHTML);

            // Set payment type based on current status
            if (amountPaid === 0) {
                $('#payment_type').val('prepayment');
                $('#amount_paid').val(depositRequired.toFixed(2));
            } else if (amountPaid >= depositRequired) {
                $('#payment_type').val('postpayment');
                $('#amount_paid').val(remainingBalance.toFixed(2));
            }
        }

        function onPaymentTypeChange() {
            if (!currentOrder) return;
            const type = $('#payment_type').val();
            const totalCost      = parseFloat(currentOrder.estimated_cost || 0);
            const depositRequired = parseFloat(currentOrder.deposit_amount || 0);
            const amountPaid     = parseFloat(currentOrder.amount_paid || 0);
            const remaining      = totalCost - amountPaid;

            let amount = 0;
            if (type === 'prepayment')   amount = depositRequired;
            else if (type === 'postpayment') amount = remaining;
            else if (type === 'full_payment') amount = totalCost;

            $('#amount_paid').val(amount.toFixed(2));
            $('#cashAmountDisplay').text('ETB ' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','));
        }

        function onPaymentMethodChange() {
            const method = $('#payment_method').val();
            if (method === 'bank') {
                $('#bankFields').show();
                $('#cashFields').hide();
                $('#cashInfoBox').hide();
                $('#receiptFile').prop('required', true);
            } else if (method === 'cash') {
                $('#bankFields').hide();
                $('#cashFields').show();
                $('#cashInfoBox').show();
                $('#receiptFile').prop('required', false);
                onPaymentTypeChange();
            } else {
                $('#bankFields').hide();
                $('#cashFields').hide();
                $('#cashInfoBox').hide();
            }
        }

        $('#payment_method').change(function() {
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

        // Handle bank selection to show account details - using ID selector
        $(document).on('change', '#bankNameSelect', function() {
            const bankName = $(this).val();
            if (bankName && bankAccounts[bankName]) {
                const bank = bankAccounts[bankName];
                $('#displayBankName').text(bank.bank_name);
                $('#displayAccountHolder').text(bank.account_holder);
                $('#displayAccountNumber').text(bank.account_number);
                $('#displaySwiftCode').text(bank.swift_code || 'N/A');
                $('#displayBankAddress').text(bank.bank_address || 'N/A');
                $('#displayPhone').text(bank.phone || 'N/A');
                $('#displayEmail').text(bank.email || 'N/A');
                $('#bankDetailsDisplay').slideDown();
            } else {
                $('#bankDetailsDisplay').slideUp();
            }
        });

        function showReceiptName() {
            const input = document.getElementById('receiptFile');
            const fileName = document.getElementById('receiptFileName');
            if (input.files.length > 0) {
                fileName.textContent = '✓ ' + input.files[0].name;
            }
        }

        $('#paymentSubmitForm').submit(function(e) {
            e.preventDefault();
            
            const paymentMethod = $('#payment_method').val();
            
            // Validate bank transfer fields
            if (paymentMethod === 'bank') {
                const bankName = $('input[name="bank_name"]').val() || $('#bankNameSelect').val();
                const transactionRef = $('input[name="transaction_reference"]').val();
                const transferDate = $('input[name="transfer_date"]').val();
                const receiptFile = document.getElementById('receiptFile').files.length;
                
                if (!bankName) {
                    alert('Please select a bank');
                    return false;
                }
                if (!transactionRef) {
                    alert('Please enter transaction reference number');
                    return false;
                }
                if (!transferDate) {
                    alert('Please enter transfer date');
                    return false;
                }
                if (receiptFile === 0) {
                    alert('Please upload receipt file');
                    return false;
                }
            }
            
            // Validate cash payment fields
            if (paymentMethod === 'cash') {
                const paymentDate = $('input[name="payment_date"]').val();
                if (!paymentDate) {
                    alert('Please enter payment date');
                    return false;
                }
            }
            
            const formData = new FormData(this);
            const submitBtn = $(this).find('button[type="submit"]');
            
            submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Processing...');
            
            $.ajax({
                url: BASE_URL + '/public/api/submit_payment.php',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        alert('✓ Payment submitted successfully!\n\nYour payment is pending verification by the manager.');
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                        submitBtn.prop('disabled', false).html('<i class="fas fa-check-circle me-2"></i>Submit Payment');
                    }
                },
                error: function() {
                    alert('Error submitting payment. Please try again.');
                    submitBtn.prop('disabled', false).html('<i class="fas fa-check-circle me-2"></i>Submit Payment');
                }
            });
        });

        function cancelPayment() {
            $('#paymentForm').hide();
            $('#ordersSection').show();
            $('#paymentSubmitForm')[0].reset();
        }

        function loadPaymentHistory() {
            $.ajax({
                url: BASE_URL + '/public/api/get_payment_history.php',
                success: function(response) {
                    if (response.success) {
                        renderPaymentHistory(response.payments);
                    }
                },
                error: function() {
                    $('#historyTable tbody').html('<tr><td colspan="7" class="text-center text-danger">Failed to load payment history</td></tr>');
                }
            });
        }

        function renderPaymentHistory(payments) {
            const tbody = $('#historyTable tbody');
            tbody.empty();

            if (payments.length === 0) {
                tbody.html('<tr><td colspan="7" class="text-center text-muted py-4">No payment history</td></tr>');
                return;
            }

            payments.forEach(payment => {
                const statusBadge = payment.status === 'approved' ? '<span class="badge badge-approved">Approved</span>' :
                                  payment.status === 'rejected' ? '<span class="badge badge-rejected">Rejected</span>' :
                                  '<span class="badge badge-pending">Pending</span>';

                const row = `
                    <tr>
                        <td><strong>PAY-${String(payment.id).padStart(4, '0')}</strong></td>
                        <td>${payment.order_number}</td>
                        <td>${payment.payment_type === 'prepayment' ? 'Deposit' : payment.payment_type === 'full_payment' ? 'Full Payment' : 'Final Payment'}</td>
                        <td>${payment.payment_method === 'bank' ? 'Bank Transfer' : 'Cash'}</td>
                        <td>ETB ${parseFloat(payment.amount).toFixed(2)}</td>
                        <td>${statusBadge}</td>
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