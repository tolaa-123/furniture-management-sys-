<?php 
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/public/?modal=login');
    exit;
}

// Get order details from query parameter
$orderId = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if ($orderId === 0) {
    header('Location: ' . BASE_URL . '/public/orders/my-orders');
    exit;
}

// Fetch order details
require_once dirname(__DIR__) . '/../../core/Database.php';
$db = Database::getInstance()->getConnection();

$stmt = $db->prepare("
    SELECT o.*, 
           u.first_name, u.last_name, u.email, u.phone
    FROM furn_orders o
    JOIN furn_users u ON o.customer_id = u.id
    WHERE o.id = ? AND o.customer_id = ?
");
$stmt->execute([$orderId, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: ' . BASE_URL . '/public/orders/my-orders');
    exit;
}

// Parse special instructions
$specialInstructions = json_decode($order['special_instructions'], true);
$paymentMethod = $specialInstructions['payment_method'] ?? 'cash';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Success - SmartWorkshop</title>
    <?php require_once dirname(__DIR__) . '/../includes/header_links.php'; ?>
</head>
<body>
<?php require_once dirname(__DIR__) . '/../includes/header.php'; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Success Message -->
            <div class="text-center mb-4">
                <div class="mb-3">
                    <i class="fas fa-check-circle text-success" style="font-size: 80px;"></i>
                </div>
                <h1 class="text-success mb-2">Order Placed Successfully!</h1>
                <p class="lead text-muted">Thank you for your order. We'll process it shortly.</p>
            </div>

            <!-- Order Details Card -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-receipt"></i> Order Details</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Order Number:</strong></p>
                            <h4 class="text-primary"><?php echo htmlspecialchars($order['order_number']); ?></h4>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <p class="mb-1"><strong>Order Date:</strong></p>
                            <p><?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?></p>
                        </div>
                    </div>

                    <hr>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <p class="mb-1"><strong>Customer Name:</strong></p>
                            <p><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <p class="mb-1"><strong>Phone:</strong></p>
                            <p><?php echo htmlspecialchars($order['phone']); ?></p>
                        </div>
                        <div class="col-12 mb-3">
                            <p class="mb-1"><strong>Delivery Address:</strong></p>
                            <p><?php echo htmlspecialchars($specialInstructions['delivery_address'] ?? 'N/A'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Information Card -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-money-bill-wave"></i> Payment Information</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center mb-3">
                        <div class="col-md-4 mb-3">
                            <div class="p-3 bg-light rounded">
                                <p class="mb-1 text-muted">Total Amount</p>
                                <h4 class="mb-0">ETB <?php echo number_format($order['total_amount'], 2); ?></h4>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="p-3 bg-warning bg-opacity-10 rounded">
                                <p class="mb-1 text-muted">Deposit Required (<?php 
                                    // Get deposit percentage from settings
                                    $dp = 40;
                                    try {
                                        $dpStmt = $pdo->prepare("SELECT setting_value FROM furn_settings WHERE setting_key = 'default_deposit_percentage' LIMIT 1");
                                        $dpStmt->execute();
                                        $dpResult = $dpStmt->fetchColumn();
                                        if ($dpResult !== false && floatval($dpResult) > 0) {
                                            $dp = floatval($dpResult);
                                        }
                                    } catch (PDOException $e) {}
                                    echo $dp;
                                ?>%)</p>
                                <h4 class="mb-0 text-warning">ETB <?php echo number_format($order['deposit_amount'], 2); ?></h4>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="p-3 bg-light rounded">
                                <p class="mb-1 text-muted">Balance (60%)</p>
                                <h4 class="mb-0">ETB <?php echo number_format($order['total_amount'] - $order['deposit_amount'], 2); ?></h4>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="alert alert-info">
                        <h6 class="alert-heading"><i class="fas fa-info-circle"></i> Payment Method: <?php echo ucfirst($paymentMethod); ?></h6>
                        
                        <?php if ($paymentMethod === 'cash'): ?>
                            <p class="mb-0">Please prepare the deposit amount in cash. Our representative will contact you to arrange collection.</p>
                        
                        <?php elseif ($paymentMethod === 'bank'): ?>
                            <p class="mb-2">Please transfer the deposit amount to our bank account:</p>
                            <ul class="mb-0">
                                <li><strong>Bank:</strong> Commercial Bank of Ethiopia</li>
                                <li><strong>Account Name:</strong> Koder Furniture Manufacturing</li>
                                <li><strong>Account Number:</strong> 1000123456789</li>
                                <li><strong>Reference:</strong> <?php echo htmlspecialchars($order['order_number']); ?></li>
                            </ul>
                            <p class="mt-2 mb-0"><small>After transfer, please upload the receipt in the <a href="<?php echo BASE_URL; ?>/public/payments">payment section</a>.</small></p>
                        
                        <?php elseif ($paymentMethod === 'mobile'): ?>
                            <p class="mb-2">Please send the deposit amount via mobile money:</p>
                            <ul class="mb-0">
                                <li><strong>Service:</strong> M-Pesa / Telebirr</li>
                                <li><strong>Number:</strong> 0911-234-567</li>
                                <li><strong>Name:</strong> Koder Furniture</li>
                                <li><strong>Reference:</strong> <?php echo htmlspecialchars($order['order_number']); ?></li>
                            </ul>
                            <p class="mt-2 mb-0"><small>After payment, please save the confirmation SMS and upload it in the payment section.</small></p>
                        
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Next Steps Card -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-tasks"></i> Next Steps</h5>
                </div>
                <div class="card-body">
                    <ol class="mb-0">
                        <li class="mb-2">
                            <strong>Pay Deposit:</strong> Complete the deposit payment using your selected method.
                        </li>
                        <li class="mb-2">
                            <strong>Upload Receipt:</strong> Go to <a href="<?php echo BASE_URL; ?>/public/payments">Payment Upload</a> and submit your payment proof.
                        </li>
                        <li class="mb-2">
                            <strong>Wait for Verification:</strong> Our team will verify your payment within 24 hours.
                        </li>
                        <li class="mb-2">
                            <strong>Production Starts:</strong> Once verified, we'll begin manufacturing your furniture.
                        </li>
                        <li class="mb-0">
                            <strong>Track Progress:</strong> Monitor your order status in <a href="<?php echo BASE_URL; ?>/public/orders/my-orders">My Orders</a>.
                        </li>
                    </ol>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex justify-content-between gap-2">
                <a href="<?php echo BASE_URL; ?>/public/" class="btn btn-outline-secondary">
                    <i class="fas fa-home"></i> Back to Home
                </a>
                <div>
                    <a href="<?php echo BASE_URL; ?>/public/orders/my-orders" class="btn btn-primary me-2">
                        <i class="fas fa-list"></i> View My Orders
                    </a>
                    <a href="<?php echo BASE_URL; ?>/public/payments/upload-deposit?order_id=<?php echo $orderId; ?>" class="btn btn-success">
                        <i class="fas fa-upload"></i> Upload Payment
                    </a>
                </div>
            </div>

            <!-- Print Button -->
            <div class="text-center mt-4">
                <button class="btn btn-outline-primary" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Order Details
                </button>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .btn, nav, .card-header {
        display: none !important;
    }
}
</style>

<?php require_once dirname(__DIR__) . '/../includes/footer.php'; ?>
</body>
</html>
