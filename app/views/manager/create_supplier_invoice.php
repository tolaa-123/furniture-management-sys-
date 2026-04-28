<?php
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['manager', 'admin'])) {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}
require_once __DIR__ . '/../../../config/db_config.php';

$csrf_token = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;
$managerId = $_SESSION['user_id'];

// Check if coming from restock
$from_restock = isset($_GET['from_restock']) && $_GET['from_restock'] == '1';
$restock_data = $_SESSION['restock_invoice_data'] ?? null;

// Pre-fill data if coming from restock
$prefill = [
    'supplier_name' => '',
    'invoice_number' => '',
    'invoice_date' => date('Y-m-d'),
    'due_date' => date('Y-m-d', strtotime('+30 days')),
    'material_name' => '',
    'quantity' => '',
    'unit' => '',
    'unit_price' => '',
    'description' => ''
];

if ($from_restock && $restock_data) {
    $prefill = [
        'supplier_name' => $restock_data['supplier_name'] ?? '',
        'invoice_number' => $restock_data['invoice_number'] ?? '',
        'invoice_date' => $restock_data['purchase_date'] ?? date('Y-m-d'),
        'due_date' => date('Y-m-d', strtotime('+30 days')),
        'material_name' => $restock_data['material_name'] ?? '',
        'quantity' => $restock_data['quantity'] ?? '',
        'unit' => $restock_data['unit'] ?? '',
        'unit_price' => $restock_data['unit_price'] ?? '',
        'description' => 'Material purchase from restock'
    ];
    // Clear the session data after using it
    unset($_SESSION['restock_invoice_data']);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_invoice'])) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['error_message'] = 'Invalid CSRF token.';
        header('Location: ' . BASE_URL . '/public/manager/create-supplier-invoice');
        exit();
    }
    
    $supplier_name = trim($_POST['supplier_name']);
    $invoice_number = trim($_POST['invoice_number']);
    $invoice_date = trim($_POST['invoice_date']);
    $due_date = trim($_POST['due_date']);
    $payment_terms = trim($_POST['payment_terms'] ?? 'Net 30');
    $description = trim($_POST['description'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    // Validate
    if (empty($supplier_name) || empty($invoice_number) || empty($invoice_date) || empty($due_date)) {
        $_SESSION['error_message'] = 'Please fill all required fields.';
        header('Location: ' . BASE_URL . '/public/manager/create-supplier-invoice');
        exit();
    }
    
    // Get line items
    $materials = $_POST['material_name'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $units = $_POST['unit'] ?? [];
    $unit_prices = $_POST['unit_price'] ?? [];
    
    if (empty($materials) || count($materials) === 0) {
        $_SESSION['error_message'] = 'Please add at least one line item.';
        header('Location: ' . BASE_URL . '/public/manager/create-supplier-invoice');
        exit();
    }
    
    // Calculate total
    $total_amount = 0;
    $line_items = [];
    for ($i = 0; $i < count($materials); $i++) {
        if (!empty($materials[$i])) {
            $qty = floatval($quantities[$i]);
            $price = floatval($unit_prices[$i]);
            $line_total = $qty * $price;
            $total_amount += $line_total;
            $line_items[] = [
                'material_name' => trim($materials[$i]),
                'quantity' => $qty,
                'unit' => trim($units[$i]),
                'unit_price' => $price,
                'total_price' => $line_total
            ];
        }
    }
    
    if ($total_amount <= 0) {
        $_SESSION['error_message'] = 'Invoice total must be greater than zero.';
        header('Location: ' . BASE_URL . '/public/manager/create-supplier-invoice');
        exit();
    }
    
    try {
        $pdo->beginTransaction();
        
        // Insert invoice
        $stmt = $pdo->prepare("
            INSERT INTO furn_supplier_invoices 
            (supplier_name, invoice_number, invoice_date, due_date, total_amount, balance_due, payment_terms, description, notes, created_by, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$supplier_name, $invoice_number, $invoice_date, $due_date, $total_amount, $total_amount, $payment_terms, $description, $notes, $managerId]);
        $invoice_id = $pdo->lastInsertId();
        
        // Insert line items
        $stmt = $pdo->prepare("
            INSERT INTO furn_supplier_invoice_items 
            (invoice_id, material_name, quantity, unit, unit_price, total_price)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        foreach ($line_items as $item) {
            $stmt->execute([$invoice_id, $item['material_name'], $item['quantity'], $item['unit'], $item['unit_price'], $item['total_price']]);
        }
        
        $pdo->commit();
        $_SESSION['success_message'] = "Invoice #{$invoice_number} created successfully!";
        header('Location: ' . BASE_URL . '/public/manager/supplier-payments');
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = "Error creating invoice: " . $e->getMessage();
        header('Location: ' . BASE_URL . '/public/manager/create-supplier-invoice');
        exit();
    }
}

$pageTitle = 'Create Supplier Invoice';
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
            <h2 class="section-title"><i class="fas fa-file-invoice"></i> Create Supplier Invoice
                <?php if ($from_restock): ?>
                    <span style="background:#2196F3;color:white;padding:4px 12px;border-radius:20px;font-size:12px;margin-left:10px;">
                        <i class="fas fa-box"></i> From Restock
                    </span>
                <?php endif; ?>
            </h2>
            <a href="<?php echo BASE_URL; ?>/public/manager/supplier-payments" class="btn-action btn-secondary-custom">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>

        <?php if ($from_restock): ?>
            <div style="background:#e3f2fd;border-left:4px solid #2196F3;padding:15px;margin-bottom:20px;border-radius:8px;">
                <i class="fas fa-info-circle" style="color:#2196F3;"></i>
                <strong>Auto-filled from restock:</strong> Review the pre-filled information below and add any additional line items if needed.
            </div>
        <?php endif; ?>

        <form method="POST" action="" style="max-width:900px;">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
                <div class="form-group">
                    <label>Supplier Name <span style="color:red;">*</span></label>
                    <input type="text" name="supplier_name" class="form-control" value="<?php echo htmlspecialchars($prefill['supplier_name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Invoice Number <span style="color:red;">*</span></label>
                    <input type="text" name="invoice_number" class="form-control" placeholder="INV-2024-001" value="<?php echo htmlspecialchars($prefill['invoice_number']); ?>" required>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-bottom:20px;">
                <div class="form-group">
                    <label>Invoice Date <span style="color:red;">*</span></label>
                    <input type="date" name="invoice_date" class="form-control" value="<?php echo $prefill['invoice_date']; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Due Date <span style="color:red;">*</span></label>
                    <input type="date" name="due_date" class="form-control" value="<?php echo $prefill['due_date']; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Payment Terms</label>
                    <select name="payment_terms" class="form-control">
                        <option value="Net 30">Net 30</option>
                        <option value="Net 15">Net 15</option>
                        <option value="Net 60">Net 60</option>
                        <option value="Due on Receipt">Due on Receipt</option>
                        <option value="Cash on Delivery">Cash on Delivery</option>
                    </select>
                </div>
            </div>

            <div class="form-group" style="margin-bottom:20px;">
                <label>Description</label>
                <input type="text" name="description" class="form-control" placeholder="Brief description of purchase" value="<?php echo htmlspecialchars($prefill['description']); ?>">
            </div>

            <hr style="margin:30px 0;">
            
            <h3 style="margin-bottom:15px;"><i class="fas fa-list"></i> Line Items</h3>
            <div id="lineItemsContainer">
                <div class="line-item" style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 40px;gap:10px;margin-bottom:10px;align-items:end;">
                    <div class="form-group" style="margin:0;">
                        <label>Material Name</label>
                        <input type="text" name="material_name[]" class="form-control" placeholder="e.g., Oak Wood" value="<?php echo htmlspecialchars($prefill['material_name']); ?>" required>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Quantity</label>
                        <input type="number" step="0.01" name="quantity[]" class="form-control qty-input" placeholder="0" value="<?php echo htmlspecialchars($prefill['quantity']); ?>" required>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Unit</label>
                        <input type="text" name="unit[]" class="form-control" placeholder="kg, m, pcs" value="<?php echo htmlspecialchars($prefill['unit']); ?>" required>
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Unit Price (ETB)</label>
                        <input type="number" step="0.01" name="unit_price[]" class="form-control price-input" placeholder="0.00" value="<?php echo htmlspecialchars($prefill['unit_price']); ?>" required>
                    </div>
                    <div style="padding-top:28px;">
                        <button type="button" class="btn-action btn-danger-custom" onclick="removeLineItem(this)" style="padding:8px 10px;">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <button type="button" class="btn-action btn-secondary-custom" onclick="addLineItem()" style="margin-bottom:20px;">
                <i class="fas fa-plus"></i> Add Line Item
            </button>

            <div style="background:#f8f9fa;padding:20px;border-radius:8px;margin-bottom:20px;">
                <h3 style="margin:0 0 10px 0;">Total: <span id="totalAmount" style="color:#27AE60;">ETB 0.00</span></h3>
            </div>

            <div class="form-group" style="margin-bottom:20px;">
                <label>Notes</label>
                <textarea name="notes" class="form-control" rows="3" placeholder="Additional notes or comments"></textarea>
            </div>

            <div style="display:flex;gap:10px;">
                <button type="submit" name="create_invoice" class="btn-action btn-success-custom">
                    <i class="fas fa-save"></i> Create Invoice
                </button>
                <a href="<?php echo BASE_URL; ?>/public/manager/supplier-payments" class="btn-action btn-secondary-custom">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
function addLineItem() {
    const container = document.getElementById('lineItemsContainer');
    const newItem = container.firstElementChild.cloneNode(true);
    newItem.querySelectorAll('input').forEach(input => input.value = '');
    container.appendChild(newItem);
    attachCalculateListeners();
}

function removeLineItem(btn) {
    const container = document.getElementById('lineItemsContainer');
    if (container.children.length > 1) {
        btn.closest('.line-item').remove();
        calculateTotal();
    } else {
        alert('At least one line item is required.');
    }
}

function calculateTotal() {
    let total = 0;
    document.querySelectorAll('.line-item').forEach(item => {
        const qty = parseFloat(item.querySelector('.qty-input').value) || 0;
        const price = parseFloat(item.querySelector('.price-input').value) || 0;
        total += qty * price;
    });
    document.getElementById('totalAmount').textContent = 'ETB ' + total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function attachCalculateListeners() {
    document.querySelectorAll('.qty-input, .price-input').forEach(input => {
        input.removeEventListener('input', calculateTotal);
        input.addEventListener('input', calculateTotal);
    });
}

attachCalculateListeners();
</script>
<script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
