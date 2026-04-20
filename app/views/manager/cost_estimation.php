<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}

require_once __DIR__ . '/../../../config/db_config.php';

$managerName = $_SESSION['user_name'] ?? 'Manager User';
$managerId = $_SESSION['user_id'];

// Ensure CSRF token exists
if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION[CSRF_TOKEN_NAME];

// Fetch orders needing cost estimation (any status that needs review)
$orders = [];
// Ensure estimated_cost column exists
try {
    $pdo->exec("ALTER TABLE furn_orders ADD COLUMN IF NOT EXISTS estimated_cost DECIMAL(12,2) DEFAULT NULL");
} catch (PDOException $e) {
    // Column might already exist
}

try {
    $stmt = $pdo->query("
        SELECT o.*, 
               u.first_name, u.last_name, u.email, u.phone,
               CONCAT(u.first_name, ' ', u.last_name) as customer_name
        FROM furn_orders o
        LEFT JOIN furn_users u ON o.customer_id = u.id
        WHERE (o.status = 'pending_review' OR o.status IS NULL OR o.status = '' OR o.status = 'pending' OR o.status = 'pending_cost_approval')
        ORDER BY o.created_at DESC
    ");
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Orders fetch error: " . $e->getMessage());
}

$pageTitle = 'Cost Estimation - Review Orders';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - SmartWorkshop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/css/admin-responsive.css">
    <style>
        .order-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border-left: 5px solid #3498DB;
            transition: all 0.3s ease;
        }
        .order-card:hover {
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 3px solid #f0f0f0;
        }
        .order-number {
            font-size: 22px;
            font-weight: 700;
            color: #2c3e50;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .order-date {
            color: #7f8c8d;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-top: 5px;
        }
        .order-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            margin-bottom: 25px;
        }
        .detail-item {
            background: linear-gradient(135deg, #f8f9fa 0%, #f0f2f5 100%);
            padding: 16px;
            border-radius: 12px;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
        }
        .detail-item:hover {
            background: linear-gradient(135deg, #f0f2f5 0%, #e8eaed 100%);
            border-color: #d4a574;
        }
        .detail-label {
            font-size: 11px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            font-weight: 700;
        }
        .detail-value {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
        }
        .estimation-form {
            background: linear-gradient(135deg, #f8f9fa 0%, #f0f2f5 100%);
            padding: 28px;
            border-radius: 14px;
            margin-top: 25px;
            border: 2px solid #e9ecef;
        }
        .estimation-form h4 {
            font-size: 18px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 14px;
            letter-spacing: 0.3px;
        }
        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #d4a574;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
            font-family: 'Poppins', sans-serif;
        }
        .form-control:focus {
            border-color: #4A2C2A;
            outline: none;
            box-shadow: 0 0 0 4px rgba(74, 44, 42, 0.1);
            background: #fafbfc;
        }
        .form-control::placeholder {
            color: #bdc3c7;
        }
        .cost-summary {
            background: linear-gradient(135deg, #4a2c2a 0%, #3d1f1d 100%);
            color: white;
            padding: 28px;
            border-radius: 14px;
            margin: 25px 0;
            box-shadow: 0 6px 20px rgba(74, 44, 42, 0.2);
        }
        .cost-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 0;
            border-bottom: 1px solid rgba(255,255,255,0.15);
            font-size: 15px;
        }
        .cost-item:last-child {
            border-bottom: none;
            font-size: 22px;
            font-weight: 700;
            padding-top: 18px;
            margin-top: 10px;
            border-top: 2px solid rgba(255,255,255,0.2);
        }
        .btn-submit-estimate {
            background: linear-gradient(135deg, #27AE60 0%, #229954 100%);
            color: white;
            padding: 16px 40px;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            font-size: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.2);
        }
        .btn-submit-estimate:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(39, 174, 96, 0.35);
            background: linear-gradient(135deg, #229954 0%, #1e8449 100%);
        }
        .btn-submit-estimate:active {
            transform: translateY(-1px);
        }
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: #7f8c8d;
        }
        .empty-state i {
            font-size: 80px;
            margin-bottom: 25px;
            opacity: 0.2;
            color: #3498DB;
        }
        .empty-state h3 {
            font-size: 24px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .empty-state p {
            font-size: 16px;
            color: #7f8c8d;
        }
        .page-header {
            margin-bottom: 35px;
        }
        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-description {
            font-size: 15px;
            color: #7f8c8d;
        }
        .btn-view-design {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, #3498DB 0%, #2980B9 100%);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            font-size: 14px;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.2);
        }
        .btn-view-design:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.35);
            background: linear-gradient(135deg, #2980B9 0%, #2471A3 100%);
            text-decoration: none;
            color: white;
        }
        .btn-view-design:active {
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>
    <div class="sidebar-overlay"></div>
    
    <?php include_once __DIR__ . '/../../includes/manager_sidebar.php'; ?>
    
    <!-- Top Header -->
    <?php 
    $pageTitle = 'Cost Estimation';
    include_once __DIR__ . '/../../includes/manager_header.php'; 
    ?>
        <div class="header-left">
            <div class="system-status"><i class="fas fa-circle"></i> Workshop Manager</div>
        </div>
        <div class="header-right">
            <div class="admin-profile">
                <div class="admin-avatar"><?php echo strtoupper(substr($managerName, 0, 1)); ?></div>
                <div>
                    <div style="font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($managerName); ?></div>
                    <div class="admin-role-badge">MANAGER</div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="main-content">
        <?php
        // Cost estimation stats cards
        $ceCards = [];
        try {
            $ceWaiting  = (int)$pdo->query("SELECT COUNT(*) FROM furn_orders WHERE status IN ('pending_review','pending_cost_approval')")->fetchColumn();
            $ceMonth    = (int)$pdo->query("SELECT COUNT(*) FROM furn_orders WHERE status NOT IN ('pending_review','pending_cost_approval') AND estimated_cost IS NOT NULL AND MONTH(updated_at)=MONTH(CURDATE()) AND YEAR(updated_at)=YEAR(CURDATE())")->fetchColumn();
            $ceTotal    = floatval($pdo->query("SELECT COALESCE(SUM(estimated_cost),0) FROM furn_orders WHERE estimated_cost IS NOT NULL")->fetchColumn());
            $ceCards = [
                [$ceWaiting,                         'Waiting for Estimate',  '#F39C12','fa-clock'],
                [$ceMonth,                           'Estimated This Month',  '#27AE60','fa-check-circle'],
                ['ETB '.number_format($ceTotal,0),   'Total Orders Value',    '#3498DB','fa-dollar-sign'],
            ];
        } catch(PDOException $e){}
        if (!empty($ceCards)):
        ?>
        <div class="stats-grid" style="margin-bottom:20px;">
            <?php foreach($ceCards as [$v,$l,$c,$i]): ?>
            <div class="stat-card" style="border-left:4px solid <?php echo $c; ?>;">
                <div style="display:flex;justify-content:space-between;align-items:start;">
                    <div><div class="stat-value" style="font-size:<?php echo strpos((string)$v,'ETB')!==false?'16px':'28px';?>"><?php echo $v; ?></div><div class="stat-label"><?php echo $l; ?></div></div>
                    <div style="font-size:28px;color:<?php echo $c; ?>;opacity:.25;"><i class="fas <?php echo $i; ?>"></i></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="page-header">
            <h1 class="page-title"><i class="fas fa-calculator me-2"></i>Cost Estimation - Review Orders</h1>
            <p class="page-description">Review pending orders and provide cost estimations to customers</p>
        </div>

        <div id="messageContainer"></div>

        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <i class="fas fa-clipboard-check"></i>
                <h3>No Orders Pending Review</h3>
                <p>All orders have been reviewed. New orders will appear here.</p>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
                <div class="order-card" id="order-<?php echo $order['id']; ?>">
                    <div class="order-header">
                        <div>
                            <div class="order-number">
                                <i class="fas fa-file-invoice"></i> <?php echo htmlspecialchars($order['order_number']); ?>
                            </div>
                            <div class="order-date">
                                <i class="far fa-calendar"></i> <?php echo date('F j, Y', strtotime($order['created_at'])); ?>
                            </div>
                        </div>
                        <span class="badge badge-warning">Pending Review</span>
                    </div>

                    <div class="order-details">
                        <div class="detail-item">
                            <div class="detail-label">Customer</div>
                            <div class="detail-value"><?php echo htmlspecialchars($order['customer_name']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Contact</div>
                            <div class="detail-value"><?php echo htmlspecialchars($order['phone'] ?? $order['email']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Furniture Type</div>
                            <div class="detail-value"><?php echo htmlspecialchars($order['furniture_type']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Furniture Name</div>
                            <div class="detail-value"><?php echo htmlspecialchars($order['furniture_name']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Dimensions (L×W×H)</div>
                            <div class="detail-value">
                                <?php echo $order['length']; ?> × <?php echo $order['width']; ?> × <?php echo $order['height']; ?> m
                            </div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Quantity</div>
                            <div class="detail-value"><?php echo $order['quantity'] ?? 1; ?> items</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Material</div>
                            <div class="detail-value"><?php echo htmlspecialchars($order['material']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Color/Finish</div>
                            <div class="detail-value"><?php echo htmlspecialchars($order['color']); ?></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Preferred Delivery</div>
                            <div class="detail-value">
                                <?php echo $order['preferred_delivery_date'] ? date('M j, Y', strtotime($order['preferred_delivery_date'])) : 'Flexible'; ?>
                            </div>
                        </div>
                    </div>

                    <?php if ($order['design_description']): ?>
                        <div class="detail-item" style="margin-bottom: 15px;">
                            <div class="detail-label">Design Description</div>
                            <div class="detail-value" style="white-space: pre-wrap;"><?php echo htmlspecialchars($order['design_description']); ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($order['design_image']): ?>
                        <div class="detail-item" style="margin-bottom: 15px;">
                            <div class="detail-label">Design Image</div>
                            <a href="<?php echo BASE_URL; ?>/public/<?php echo htmlspecialchars($order['design_image']); ?>" target="_blank" class="btn-view-design">
                                <i class="fas fa-image"></i> View Design Image
                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- Cost Estimation Form -->
                    <div class="estimation-form">
                        <h4>
                            <i class="fas fa-calculator"></i> Provide Cost Estimation
                        </h4>

                        <?php if (!empty($order['budget_range'])): ?>
                        <div style="background:#fff8e1;border:1.5px solid #ffc107;border-radius:8px;padding:10px 16px;margin-bottom:16px;display:flex;align-items:center;gap:10px;">
                            <i class="fas fa-wallet" style="color:#f39c12;font-size:18px;"></i>
                            <div>
                                <div style="font-size:11px;color:#856404;font-weight:700;text-transform:uppercase;">Customer's Budget Range</div>
                                <div style="font-size:15px;font-weight:700;color:#2c3e50;"><?php echo htmlspecialchars($order['budget_range']); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <form class="estimation-form-data" data-order-id="<?php echo $order['id']; ?>">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label"><i class="fas fa-money-bill-wave"></i> Estimated Cost (ETB) *</label>
                                    <input type="number" name="estimated_cost" class="form-control estimated-cost" 
                                           step="0.01" min="0" required 
                                           placeholder="e.g., 5000.00"
                                           onchange="calculateDeposit(<?php echo $order['id']; ?>)">
                                </div>
                                <div class="form-group">
                                    <label class="form-label"><i class="fas fa-calendar-days"></i> Production Days *</label>
                                    <input type="number" name="estimated_production_days" class="form-control" 
                                           min="1" required 
                                           placeholder="e.g., 14">
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label"><i class="fas fa-sticky-note"></i> Manager Notes</label>
                                <textarea name="manager_notes" class="form-control" rows="4" 
                                          placeholder="Add any special notes or requirements for the customer (optional)"></textarea>
                            </div>

                            <div class="cost-summary" id="cost-summary-<?php echo $order['id']; ?>">
                                <div class="cost-item">
                                    <span><i class="fas fa-receipt"></i> Estimated Total Cost:</span>
                                    <span class="total-cost">ETB 0.00</span>
                                </div>
                                <div class="cost-item">
                                    <span><i class="fas fa-hand-holding-usd"></i> Deposit Required (40%):</span>
                                    <span class="deposit-amount">ETB 0.00</span>
                                </div>
                                <div class="cost-item">
                                    <span><i class="fas fa-balance-scale"></i> Remaining Balance (60%):</span>
                                    <span class="remaining-balance">ETB 0.00</span>
                                </div>
                            </div>

                            <button type="submit" class="btn-submit-estimate">
                                <i class="fas fa-paper-plane"></i> Submit Cost Estimation
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="<?php echo BASE_URL; ?>/public/assets/js/jquery-3.6.0.min.js"></script>
    <script>
        function calculateDeposit(orderId) {
            const form = document.querySelector(`form[data-order-id="${orderId}"]`);
            const estimatedCost = parseFloat(form.querySelector('.estimated-cost').value) || 0;
            const depositAmount = estimatedCost * 0.40;
            const remainingBalance = estimatedCost * 0.60;

            const summary = document.getElementById(`cost-summary-${orderId}`);
            summary.querySelector('.total-cost').textContent = 'ETB ' + estimatedCost.toFixed(2);
            summary.querySelector('.deposit-amount').textContent = 'ETB ' + depositAmount.toFixed(2);
            summary.querySelector('.remaining-balance').textContent = 'ETB ' + remainingBalance.toFixed(2);
        }

        $(document).ready(function() {
            $('.estimation-form-data').on('submit', function(e) {
                e.preventDefault();
                
                const form = $(this);
                const orderId = form.data('order-id');
                const estimatedCost = parseFloat(form.find('[name="estimated_cost"]').val());
                const estimatedDays = parseInt(form.find('[name="estimated_production_days"]').val());
                const managerNotes = form.find('[name="manager_notes"]').val();
                
                if (!estimatedCost || estimatedCost <= 0) {
                    showMessage('Please enter a valid estimated cost', 'error');
                    return;
                }
                
                if (!estimatedDays || estimatedDays <= 0) {
                    showMessage('Please enter estimated production days', 'error');
                    return;
                }
                
                const depositAmount = estimatedCost * 0.40;
                const submitBtn = form.find('button[type="submit"]');
                
                submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Submitting...');
                
                $.ajax({
                    url: '<?php echo BASE_URL; ?>/public/api/submit_cost_estimation.php',
                    method: 'POST',
                    data: {
                        order_id: orderId,
                        estimated_cost: estimatedCost,
                        deposit_amount: depositAmount,
                        estimated_production_days: estimatedDays,
                        manager_notes: managerNotes,
                        manager_id: <?php echo $managerId; ?>,
                        csrf_token: '<?php echo htmlspecialchars($csrfToken); ?>'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showMessage('Cost estimation submitted successfully!', 'success');
                            $('#order-' + orderId).fadeOut(500, function() {
                                $(this).remove();
                                if ($('.order-card').length === 0) {
                                    location.reload();
                                }
                            });
                        } else {
                            showMessage('Error: ' + response.message, 'error');
                            submitBtn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Submit Cost Estimation');
                        }
                    },
                    error: function() {
                        showMessage('Error submitting estimation. Please try again.', 'error');
                        submitBtn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Submit Cost Estimation');
                    }
                });
            });
        });

        function showMessage(message, type) {
            const bgColor = type === 'success' ? '#d4edda' : '#f8d7da';
            const textColor = type === 'success' ? '#155724' : '#721c24';
            
            const messageHtml = `
                <div style="padding: 15px; background: ${bgColor}; color: ${textColor}; border-radius: 8px; margin-bottom: 20px;">
                    ${message}
                </div>
            `;
            
            $('#messageContainer').html(messageHtml);
            
            setTimeout(() => {
                $('#messageContainer').fadeOut(500, function() {
                    $(this).html('').show();
                });
            }, 5000);
        }
    </script>
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>