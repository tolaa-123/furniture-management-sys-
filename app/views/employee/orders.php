<?php
// Employee authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}

// Get database connection
require_once __DIR__ . '/../../../config/db_config.php';

$employeeName = $_SESSION['user_name'] ?? 'Employee User';
$employeeId = $_SESSION['user_id'];

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_status') {
        try {
            // Allow update if employee has a production task for this order
            $stmt = $pdo->prepare("
                UPDATE furn_orders SET status = ?, updated_at = NOW()
                WHERE id = ? AND id IN (
                    SELECT order_id FROM furn_production_tasks WHERE employee_id = ?
                )
            ");
            $stmt->execute([$_POST['status'], $_POST['order_id'], $employeeId]);
            $success = "Order status updated successfully!";
        } catch (PDOException $e) {
            $error = "Error updating order: " . $e->getMessage();
        }
    }
}

// Get filter parameters
$filterStatus = $_GET['status'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Fetch orders created by this employee
$orders = [];
// Ensure column exists
try { $pdo->exec("ALTER TABLE furn_orders ADD COLUMN IF NOT EXISTS created_by_employee_id INT DEFAULT NULL"); } catch(PDOException $e2){}
try {
    $query = "
        SELECT o.*,
               COALESCE(o.estimated_cost, o.total_amount, 0) as display_total,
               CONCAT(u.first_name, ' ', u.last_name) as customer_name,
               u.email, u.phone,
               1 as item_count
        FROM furn_orders o
        LEFT JOIN furn_users u ON o.customer_id = u.id
        WHERE (
            o.created_by_employee_id = ?
            OR o.id IN (SELECT order_id FROM furn_production_tasks WHERE employee_id = ?)
        )
    ";
    $params = [$employeeId, $employeeId];
    
    if ($filterStatus) {
        $query .= " AND o.status = ?";
        $params[] = $filterStatus;
    }
    
    if ($searchQuery) {
        $query .= " AND (o.order_number LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ?)";
        $searchParam = "%$searchQuery%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $query .= " GROUP BY o.id ORDER BY o.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching orders: " . $e->getMessage();
}

// Get customers for dropdown
$customers = [];
try {
    $stmt = $pdo->query("
        SELECT id, CONCAT(first_name, ' ', last_name) as name, email, phone 
        FROM furn_users 
        WHERE role = 'customer' 
        ORDER BY first_name, last_name
    ");
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching customers: " . $e->getMessage());
}

$pageTitle = 'Manage Orders';
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
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" aria-label="Toggle Menu">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay"></div>

    <?php include_once __DIR__ . '/../../includes/employee_sidebar.php'; ?>

    <!-- Top Header -->
    <?php 
    $pageTitle = 'Manage Orders';
    include_once __DIR__ . '/../../includes/employee_header.php'; 
    ?>
        <div class="header-left">
            <div class="system-status">
                <i class="fas fa-circle"></i> Manage Orders
            </div>
        </div>
        <div class="header-right">
            <div class="admin-profile">
                <div class="admin-avatar"><?php echo strtoupper(substr($employeeName, 0, 1)); ?></div>
                <div>
                    <div style="font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($employeeName); ?></div>
                    <div class="admin-role-badge" style="background: #27AE60;">EMPLOYEE</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Create Order Section -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-plus-circle"></i> Create New Order</h2>
                <button class="btn-action btn-primary-custom" onclick="toggleCreateForm()">
                    <i class="fas fa-plus"></i> New Order
                </button>
            </div>

            <div id="createOrderForm" style="display: none; margin-top: 20px;">
                <div id="orderFormAlert" style="display:none; margin-bottom:15px;"></div>
                <form id="employeeOrderForm" enctype="multipart/form-data">
                    <input type="hidden" name="order_number" id="empOrderNumber" value="ORD-<?php echo date('Y'); ?>-<?php echo str_pad(rand(1,99999),5,'0',STR_PAD_LEFT); ?>">

                    <!-- Customer Selection (Employee Only) -->
                    <div class="form-group" style="margin-bottom:20px;">
                        <label>Customer <span style="color:#E74C3C;">*</span></label>
                        <div style="display:flex;gap:8px;align-items:center;">
                            <select name="customer_id" id="customerSelect" class="form-control" required style="flex:1;">
                                <option value="">-- Select Customer --</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?php echo $c['id']; ?>">
                                        <?php echo htmlspecialchars($c['name']); ?> (<?php echo htmlspecialchars($c['email']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn-action btn-primary-custom" onclick="showAddCustomerModal()" title="Register new customer" style="white-space:nowrap;">
                                <i class="fas fa-user-plus"></i> New Customer
                            </button>
                        </div>
                    </div>

                    <!-- Section 1: Furniture Information -->
                    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:15px; margin-top:15px;">
                        <div class="form-group">
                            <label>Furniture Type <span style="color:#E74C3C;">*</span></label>
                            <select name="furniture_type" class="form-control" required>
                                <option value="">Select Type...</option>
                                <option value="Table">Table</option>
                                <option value="Chair">Chair</option>
                                <option value="Bed">Bed</option>
                                <option value="Sofa">Sofa</option>
                                <option value="Desk">Desk</option>
                                <option value="Shelf">Shelf</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Color/Finish <span style="color:#E74C3C;">*</span></label>
                            <select name="color" id="color_select" class="form-control" required>
                                <option value="">Select Color...</option>
                                <option value="Natural Wood">Natural Wood</option>
                                <option value="Brown">Brown</option>
                                <option value="Dark Brown">Dark Brown</option>
                                <option value="Black">Black</option>
                                <option value="White">White</option>
                                <option value="Gray">Gray</option>
                                <option value="Custom Color">Custom Color</option>
                            </select>
                        </div>
                    </div>

                    <!-- Section 2: Dimensions -->
                    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:15px; margin-top:20px;">
                        <div class="form-group">
                            <label>Length (m) <span style="color:#E74C3C;">*</span></label>
                            <input type="number" name="length" class="form-control" placeholder="1.2" min="0.01" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label>Width (m) <span style="color:#E74C3C;">*</span></label>
                            <input type="number" name="width" class="form-control" placeholder="0.6" min="0.01" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label>Height (m) <span style="color:#E74C3C;">*</span></label>
                            <input type="number" name="height" class="form-control" placeholder="0.75" min="0.01" step="0.01" required>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:15px; margin-top:15px;">
                        <div class="form-group">
                            <label>Quantity <span style="color:#E74C3C;">*</span></label>
                            <input type="number" name="quantity" class="form-control" min="1" value="1" required>
                            <small style="color:#7f8c8d;">Number of items</small>
                        </div>
                        <div class="form-group">
                            <label>Budget Range <span style="color:#E74C3C;">*</span></label>
                            <select name="budget_range" class="form-control" required>
                                <option value="">Select Budget...</option>
                                <option value="Under ETB 5,000">Under ETB 5,000</option>
                                <option value="ETB 5,000 - ETB 10,000">ETB 5,000 - ETB 10,000</option>
                                <option value="ETB 10,000 - ETB 20,000">ETB 10,000 - ETB 20,000</option>
                                <option value="Above ETB 20,000">Above ETB 20,000</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Preferred Delivery Date</label>
                            <input type="date" name="preferred_delivery_date" class="form-control" min="<?php echo date('Y-m-d', strtotime('+7 days')); ?>">
                            <small style="color:#7f8c8d;">Minimum 7 days from today</small>
                        </div>
                    </div>

                    <!-- Section 3: Design Details -->
                    <div class="form-group" style="margin-top:20px;">
                        <label>Design Description (Optional)</label>
                        <textarea name="design_description" class="form-control" rows="5" placeholder="Describe your furniture design in detail. Example: I want a modern desk with two drawers and cable holes for computer wires."></textarea>
                        <small style="color:#7f8c8d;">Be as detailed as possible to help our craftsmen understand your vision.</small>
                    </div>

                    <!-- Section 4: Upload Design Image -->
                    <div class="form-group" style="margin-top:20px;">
                        <label>Upload Design Image</label>
                        <div style="border:2px dashed #d4a574; border-radius:8px; padding:20px; text-align:center; cursor:pointer; background:#fafafa;" onclick="document.getElementById('empDesignImage').click()">
                            <i class="fas fa-cloud-upload-alt" style="font-size:32px; color:#d4a574;"></i>
                            <h5 style="margin:10px 0 5px;">Click to Upload Design Image</h5>
                            <p style="margin:0; color:#7f8c8d;">Supported: JPG, PNG, PDF (Max 5MB)</p>
                            <p id="empFileName" style="margin:5px 0 0; color:#27AE60; font-weight:600;"></p>
                        </div>
                        <input type="file" id="empDesignImage" name="design_image" accept=".jpg,.jpeg,.png,.pdf" style="display:none;" onchange="document.getElementById('empFileName').textContent = this.files[0]?.name || ''">
                    </div>

                    <!-- Submit Buttons -->
                    <div style="display:flex; gap:10px; justify-content:flex-start; margin-top:25px;">
                        <button type="submit" class="btn-action btn-success-custom" id="empSubmitBtn">
                            <i class="fas fa-paper-plane" style="margin-right:8px;"></i>Submit Order
                        </button>
                        <button type="reset" class="btn-action btn-secondary-custom">
                            <i class="fas fa-redo" style="margin-right:8px;"></i>Reset Form
                        </button>
                        <button type="button" class="btn-action btn-secondary-custom" onclick="toggleCreateForm()">
                            <i class="fas fa-times" style="margin-right:8px;"></i>Cancel
                        </button>
                    </div>

                    <!-- What Happens Next -->
                    <div style="margin-top:25px; padding:20px; background:#f8f9fa; border-radius:10px; border-left:4px solid #3498db;">
                        <h6 style="font-weight:700; margin-bottom:10px;"><i class="fas fa-info-circle" style="margin-right:8px; color:#3498db;"></i>What Happens Next?</h6>
                        <ol style="font-size:13px; line-height:2; margin:0 0 0 16px; padding:0;">
                            <li>Manager reviews your order</li>
                            <li>Cost estimation provided</li>
                            <li>Customer pays 40% deposit</li>
                            <li>Production begins</li>
                            <li>Customer pays remaining 60%</li>
                            <li>Delivery arranged</li>
                        </ol>
                    </div>
                </form>
            </div>
        </div>

        <!-- Orders List -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-shopping-cart"></i> My Created Orders</h2>
            </div>
            
            <!-- Filters -->
            <div class="filter-section" style="margin-bottom: 20px;">
                <form method="GET" action="" style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <div class="form-group" style="margin: 0;">
                        <label>Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Order number, customer..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                    </div>
                    
                    <div class="form-group" style="margin: 0;">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="">All Status</option>
                            <option value="pending_review" <?php echo $filterStatus === 'pending_review' ? 'selected' : ''; ?>>Pending Review</option>
                            <option value="cost_estimated" <?php echo $filterStatus === 'cost_estimated' ? 'selected' : ''; ?>>Cost Estimated</option>
                            <option value="awaiting_deposit" <?php echo $filterStatus === 'awaiting_deposit' ? 'selected' : ''; ?>>Awaiting Deposit</option>
                            <option value="deposit_paid" <?php echo $filterStatus === 'deposit_paid' ? 'selected' : ''; ?>>Deposit Paid</option>
                            <option value="payment_verified" <?php echo $filterStatus === 'payment_verified' ? 'selected' : ''; ?>>Payment Verified</option>
                            <option value="in_production" <?php echo $filterStatus === 'in_production' ? 'selected' : ''; ?>>In Production</option>
                            <option value="awaiting_final_payment" <?php echo $filterStatus === 'awaiting_final_payment' ? 'selected' : ''; ?>>Awaiting Final Payment</option>
                            <option value="fully_paid" <?php echo $filterStatus === 'fully_paid' ? 'selected' : ''; ?>>Fully Paid</option>
                            <option value="ready_for_delivery" <?php echo $filterStatus === 'ready_for_delivery' ? 'selected' : ''; ?>>Ready for Delivery</option>
                            <option value="completed" <?php echo $filterStatus === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $filterStatus === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div style="display: flex; align-items: flex-end; gap: 10px;">
                        <button type="submit" class="btn-action btn-primary-custom">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <?php if ($filterStatus || $searchQuery): ?>
                        <a href="<?php echo BASE_URL; ?>/public/employee/orders" class="btn-action btn-secondary-custom">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <?php if (empty($orders)): ?>
                <p style="text-align: center; color: #7f8c8d; padding: 40px;">No orders found.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table mobile-cards">
                        <thead>
                            <tr>
                                <th>Order Number</th>
                                <th>Customer</th>
                                <th>Furniture</th>
                                <th>Status</th>
                                <th>Created Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                                    <td>
                                        <?php echo htmlspecialchars($order['customer_name']); ?><br>
                                        <small style="color: #7f8c8d;"><?php echo htmlspecialchars($order['email']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($order['furniture_name'] ?? $order['furniture_type'] ?? '—'); ?></td>
                                    <td>
                                        <?php
                                        $statusColors = [
                                            'pending_review'          => 'warning',
                                            'cost_estimated'          => 'info',
                                            'awaiting_deposit'        => 'info',
                                            'deposit_paid'            => 'primary',
                                            'payment_verified'        => 'primary',
                                            'in_production'           => 'info',
                                            'awaiting_final_payment'  => 'warning',
                                            'fully_paid'              => 'success',
                                            'ready_for_delivery'      => 'success',
                                            'completed'               => 'success',
                                            'cancelled'               => 'danger',
                                        ];
                                        $badgeClass = $statusColors[$order['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge badge-<?php echo $badgeClass; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
                                    <td>
                                        <button class="btn-action btn-primary-custom" onclick="viewOrderDetails(<?php echo htmlspecialchars(json_encode($order), ENT_QUOTES); ?>)" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div id="orderDetailsModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h3><i class="fas fa-info-circle"></i> Order Details</h3>
                <span class="close" onclick="closeOrderDetailsModal()">&times;</span>
            </div>
            <div id="order_details_content"></div>
            <div class="modal-footer">
                <button type="button" class="btn-action btn-secondary-custom" onclick="closeOrderDetailsModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Add Customer Modal -->
    <div id="addCustomerModal" class="modal">
        <div class="modal-content" style="max-width:520px;">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Register New Customer</h3>
                <span class="close" onclick="closeAddCustomerModal()">&times;</span>
            </div>
            <div style="padding:20px;">
                <div id="addCustAlert" style="display:none;margin-bottom:14px;"></div>
                <form id="addCustomerForm">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                        <div class="form-group" style="margin:0;">
                            <label>First Name <span style="color:#E74C3C;">*</span></label>
                            <input type="text" id="cust_first_name" class="form-control" placeholder="First name" required>
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label>Last Name <span style="color:#E74C3C;">*</span></label>
                            <input type="text" id="cust_last_name" class="form-control" placeholder="Last name" required>
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom:12px;">
                        <label>Email <span style="color:#E74C3C;">*</span></label>
                        <input type="email" id="cust_email" class="form-control" placeholder="customer@email.com" required>
                    </div>
                    <div class="form-group" style="margin-bottom:12px;">
                        <label>Phone</label>
                        <input type="text" id="cust_phone" class="form-control" placeholder="+251...">
                    </div>
                    <div class="form-group" style="margin-bottom:12px;">
                        <label>Address</label>
                        <input type="text" id="cust_address" class="form-control" placeholder="City, Area">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Password <span style="color:#E74C3C;">*</span></label>
                        <input type="password" id="cust_password" class="form-control" placeholder="Temporary password" required>
                        <small style="color:#7f8c8d;">Customer can change this after first login.</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-action btn-secondary-custom" onclick="closeAddCustomerModal()">Cancel</button>
                <button type="button" class="btn-action btn-success-custom" id="saveCustBtn" onclick="saveNewCustomer()">
                    <i class="fas fa-user-plus"></i> Register Customer
                </button>
            </div>
        </div>
    </div>

    <script>
        // Color options based on furniture type
        const furnitureColors = {
            'Table': ['Natural Wood', 'Oak', 'Walnut', 'Cherry', 'Mahogany', 'Espresso', 'Black', 'White', 'Gray', 'Brown'],
            'Chair': ['Natural Wood', 'Oak', 'Walnut', 'Brown', 'Black', 'White', 'Gray', 'Beige', 'Navy Blue'],
            'Bed': ['Natural Wood', 'Walnut', 'Cherry', 'White', 'Black', 'Gray', 'Brown', 'Espresso'],
            'Sofa': ['Beige', 'Gray', 'Brown', 'Black', 'Navy Blue', 'Cream', 'Burgundy', 'Teal', 'Olive Green', 'Dark Brown'],
            'Desk': ['Natural Wood', 'Oak', 'Walnut', 'Cherry', 'Black', 'White', 'Gray', 'Espresso', 'Brown'],
            'Shelf': ['Natural Wood', 'Oak', 'Walnut', 'Cherry', 'White', 'Black', 'Brown', 'Gray'],
            'Other': ['Natural Wood', 'Brown', 'Dark Brown', 'Black', 'White', 'Gray', 'Custom Color']
        };

        // Update color options when furniture type changes
        document.addEventListener('DOMContentLoaded', function() {
            const furnitureSelect = document.querySelector('select[name="furniture_type"]');
            const colorSelect = document.getElementById('color_select');
            
            if (furnitureSelect && colorSelect) {
                furnitureSelect.addEventListener('change', function() {
                    const furnitureType = this.value;
                    const currentColor = colorSelect.value;
                    
                    // Clear existing options
                    colorSelect.innerHTML = '<option value="">Select Color...</option>';
                    
                    // Add new color options based on furniture type
                    if (furnitureType && furnitureColors[furnitureType]) {
                        furnitureColors[furnitureType].forEach(function(color) {
                            const option = document.createElement('option');
                            option.value = color;
                            option.textContent = color;
                            if (color === currentColor) {
                                option.selected = true;
                            }
                            colorSelect.appendChild(option);
                        });
                    }
                });
            }
        });

        function toggleCreateForm() {
            const form = document.getElementById('createOrderForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }

        // Employee order form — AJAX submit
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('employeeOrderForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const btn = document.getElementById('empSubmitBtn');
                const alertBox = document.getElementById('orderFormAlert');
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
                alertBox.style.display = 'none';

                fetch('<?php echo BASE_URL; ?>/public/api/submit_employee_order.php', {
                    method: 'POST',
                    body: new FormData(this)
                })
                .then(r => r.json())
                .then(data => {
                    alertBox.style.display = 'block';
                    if (data.success) {
                        alertBox.className = 'alert alert-success';
                        alertBox.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
                        this.reset();
                        document.getElementById('empFileName').textContent = '';
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        alertBox.className = 'alert alert-danger';
                        alertBox.innerHTML = '<i class="fas fa-exclamation-circle"></i> ' + data.message;
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Order';
                    }
                })
                .catch(() => {
                    alertBox.style.display = 'block';
                    alertBox.className = 'alert alert-danger';
                    alertBox.innerHTML = 'Network error. Please try again.';
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Order';
                });
            });
        });

        function viewOrderDetails(order) {
            const content = `
                <div style="padding: 20px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <div>
                            <strong>Order Number:</strong><br>${order.order_number}
                        </div>
                        <div>
                            <strong>Customer:</strong><br>${order.customer_name}
                        </div>
                        <div>
                            <strong>Email:</strong><br>${order.email || 'N/A'}
                        </div>
                        <div>
                            <strong>Phone:</strong><br>${order.phone || 'N/A'}
                        </div>
                        <div>
                            <strong>Furniture:</strong><br>${order.furniture_name || order.furniture_type || '—'}
                        </div>
                        <div>
                            <strong>Status:</strong><br>${order.status.replace(/_/g, ' ').toUpperCase()}
                        </div>
                        <div>
                            <strong>Created Date:</strong><br>${new Date(order.created_at).toLocaleDateString()}
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('order_details_content').innerHTML = content;
            document.getElementById('orderDetailsModal').style.display = 'block';
        }

        function closeOrderDetailsModal() {
            document.getElementById('orderDetailsModal').style.display = 'none';
        }

        function showAddCustomerModal() {
            document.getElementById('addCustomerModal').style.display = 'block';
            document.getElementById('addCustAlert').style.display = 'none';
            document.getElementById('addCustomerForm').reset();
        }

        function closeAddCustomerModal() {
            document.getElementById('addCustomerModal').style.display = 'none';
        }

        function saveNewCustomer() {
            const firstName = document.getElementById('cust_first_name').value.trim();
            const lastName  = document.getElementById('cust_last_name').value.trim();
            const email     = document.getElementById('cust_email').value.trim();
            const phone     = document.getElementById('cust_phone').value.trim();
            const address   = document.getElementById('cust_address').value.trim();
            const password  = document.getElementById('cust_password').value;
            const alertEl   = document.getElementById('addCustAlert');
            const btn       = document.getElementById('saveCustBtn');

            if (!firstName || !lastName || !email || !password) {
                alertEl.style.display = 'block';
                alertEl.innerHTML = '<div class="alert alert-danger">Please fill all required fields.</div>';
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registering...';

            const data = new FormData();
            data.append('first_name', firstName);
            data.append('last_name',  lastName);
            data.append('email',      email);
            data.append('phone',      phone);
            data.append('address',    address);
            data.append('password',   password);
            data.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>');

            fetch('<?php echo BASE_URL; ?>/public/api/register_customer_by_employee.php', {
                method: 'POST', body: data
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    // Add new customer to dropdown and select them
                    const sel = document.getElementById('customerSelect');
                    const opt = document.createElement('option');
                    opt.value = res.customer_id;
                    opt.textContent = firstName + ' ' + lastName + ' (' + email + ')';
                    opt.selected = true;
                    sel.appendChild(opt);
                    alertEl.style.display = 'block';
                    alertEl.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Customer registered and selected!</div>';
                    setTimeout(() => closeAddCustomerModal(), 1500);
                } else {
                    alertEl.style.display = 'block';
                    alertEl.innerHTML = '<div class="alert alert-danger">' + (res.message || 'Registration failed') + '</div>';
                }
            })
            .catch(() => {
                alertEl.style.display = 'block';
                alertEl.innerHTML = '<div class="alert alert-danger">Network error. Please try again.</div>';
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-user-plus"></i> Register Customer';
            });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const orderModal = document.getElementById('orderDetailsModal');
            const customerModal = document.getElementById('addCustomerModal');
            if (event.target === orderModal) {
                closeOrderDetailsModal();
            }
            if (event.target === customerModal) {
                closeAddCustomerModal();
            }
        }
    </script>

    <!-- Mobile Menu Script -->
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
