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
    
    if ($action === 'add_customer') {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM furn_users WHERE email = ?");
            $stmt->execute([$_POST['email']]);
            
            if ($stmt->fetch()) {
                $error = "A customer with this email already exists!";
            } else {
                // Generate username from email
                $username = explode('@', $_POST['email'])[0] . rand(100, 999);
                
                // Generate random password
                $randomPassword = bin2hex(random_bytes(4)); // 8 character password
                $hashedPassword = password_hash($randomPassword, PASSWORD_DEFAULT);
                
                // Insert customer
                $stmt = $pdo->prepare("
                    INSERT INTO furn_users (
                        username, email, password, first_name, last_name, 
                        phone, address, role, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'customer', 'active', NOW())
                ");
                $stmt->execute([
                    $username,
                    $_POST['email'],
                    $hashedPassword,
                    $_POST['first_name'],
                    $_POST['last_name'],
                    $_POST['phone'],
                    $_POST['address'] ?? ''
                ]);
                
                $success = "Customer added successfully! Username: $username, Password: $randomPassword (Please share with customer)";
            }
        } catch (PDOException $e) {
            $error = "Error adding customer: " . $e->getMessage();
        }
    }
}

// Get search parameter
$searchQuery = $_GET['search'] ?? '';

// Fetch customers
$customers = [];
try {
    $query = "
        SELECT u.*, 
               COUNT(DISTINCT o.id) as total_orders,
               COALESCE(SUM(o.total_amount), 0) as total_spent,
               MAX(o.created_at) as last_order_date
        FROM furn_users u
        LEFT JOIN furn_orders o ON u.id = o.customer_id
        WHERE u.role = 'customer'
    ";
    
    $params = [];
    
    if ($searchQuery) {
        $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
        $searchParam = "%$searchQuery%";
        $params = [$searchParam, $searchParam, $searchParam, $searchParam];
    }
    
    $query .= " GROUP BY u.id ORDER BY u.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching customers: " . $e->getMessage();
}

$pageTitle = 'Customers';
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
    $pageTitle = 'Customers';
    include_once __DIR__ . '/../../includes/employee_header.php'; 
    ?>
        <div class="header-left">
            <div class="system-status">
                <i class="fas fa-circle"></i> Customers
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

        <!-- Statistics Cards -->
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));">
            <div class="stat-card" style="border-left: 4px solid #3498DB;">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <div>
                        <div class="stat-value"><?php echo count($customers); ?></div>
                        <div class="stat-label">Total Customers</div>
                    </div>
                    <div style="font-size: 32px; color: #3498DB;"><i class="fas fa-users"></i></div>
                </div>
            </div>

            <div class="stat-card" style="border-left: 4px solid #27AE60;">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <div>
                        <div class="stat-value">
                            <?php 
                            $activeCustomers = array_filter($customers, function($c) { 
                                return $c['status'] === 'active'; 
                            });
                            echo count($activeCustomers);
                            ?>
                        </div>
                        <div class="stat-label">Active Customers</div>
                    </div>
                    <div style="font-size: 32px; color: #27AE60;"><i class="fas fa-user-check"></i></div>
                </div>
            </div>

            <div class="stat-card" style="border-left: 4px solid #F39C12;">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <div>
                        <div class="stat-value">
                            <?php 
                            $totalOrders = array_sum(array_column($customers, 'total_orders'));
                            echo $totalOrders;
                            ?>
                        </div>
                        <div class="stat-label">Total Orders</div>
                    </div>
                    <div style="font-size: 32px; color: #F39C12;"><i class="fas fa-shopping-cart"></i></div>
                </div>
            </div>
        </div>

        <!-- Customers Section -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-users"></i> Customer Directory</h2>
                <button class="btn-action btn-primary-custom" onclick="showAddCustomerModal()">
                    <i class="fas fa-user-plus"></i> Add Customer
                </button>
            </div>
            
            <!-- Search -->
            <div class="filter-section" style="margin-bottom: 20px;">
                <form method="GET" action="" style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <div class="form-group" style="margin: 0; flex: 1; min-width: 250px;">
                        <label>Search Customers</label>
                        <input type="text" name="search" class="form-control" placeholder="Name, email, phone..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                    </div>
                    
                    <div style="display: flex; align-items: flex-end; gap: 10px;">
                        <button type="submit" class="btn-action btn-primary-custom">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <?php if ($searchQuery): ?>
                        <a href="<?php echo BASE_URL; ?>/public/employee/customers" class="btn-action btn-secondary-custom">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <?php if (empty($customers)): ?>
                <p style="text-align: center; color: #7f8c8d; padding: 40px;">No customers found.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table mobile-cards">
                        <thead>
                            <tr>
                                <th>Customer Name</th>
                                <th>Email</th>
                                <th>Phone Number</th>
                                <th>Address</th>
                                <th>Total Orders</th>
                                <th>Total Spent</th>
                                <th>Last Order</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></strong>
                                    </td>
                                    <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['phone'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($customer['address'] ?? 'N/A'); ?></td>
                                    <td><?php echo $customer['total_orders']; ?></td>
                                    <td><strong>ETB <?php echo number_format($customer['total_spent'], 2); ?></strong></td>
                                    <td>
                                        <?php 
                                        if ($customer['last_order_date']) {
                                            echo date('M d, Y', strtotime($customer['last_order_date']));
                                        } else {
                                            echo '<span style="color: #95A5A6;">No orders yet</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $customer['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($customer['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn-action btn-primary-custom" onclick="viewCustomerDetails(<?php echo htmlspecialchars(json_encode($customer), ENT_QUOTES); ?>)" title="View Details">
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

    <!-- Add Customer Modal -->
    <div id="addCustomerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Add New Customer</h3>
                <span class="close" onclick="closeAddCustomerModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_customer">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? $_SESSION['csrf_token'] ?? ''); ?>">
                
                <div style="padding: 20px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                        <div class="form-group">
                            <label>First Name <span style="color: #E74C3C;">*</span></label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Last Name <span style="color: #E74C3C;">*</span></label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Email <span style="color: #E74C3C;">*</span></label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Phone Number <span style="color: #E74C3C;">*</span></label>
                            <input type="tel" name="phone" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="Customer address..."></textarea>
                    </div>
                    
                    <div style="background: #FFF3CD; border: 1px solid #FFE69C; border-radius: 8px; padding: 15px; margin-top: 15px;">
                        <p style="margin: 0; color: #856404; font-size: 14px;">
                            <i class="fas fa-info-circle"></i> 
                            A random password will be generated for this customer. Please share the credentials with them.
                        </p>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn-action btn-secondary-custom" onclick="closeAddCustomerModal()">Cancel</button>
                    <button type="submit" class="btn-action btn-success-custom">
                        <i class="fas fa-check"></i> Add Customer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Customer Details Modal -->
    <div id="customerDetailsModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h3><i class="fas fa-user"></i> Customer Details</h3>
                <span class="close" onclick="closeCustomerDetailsModal()">&times;</span>
            </div>
            <div id="customer_details_content"></div>
            <div class="modal-footer">
                <button type="button" class="btn-action btn-secondary-custom" onclick="closeCustomerDetailsModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        function showAddCustomerModal() {
            document.getElementById('addCustomerModal').style.display = 'block';
        }

        function closeAddCustomerModal() {
            document.getElementById('addCustomerModal').style.display = 'none';
        }

        function viewCustomerDetails(customer) {
            const content = `
                <div style="padding: 20px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <div>
                            <strong>Full Name:</strong><br>${customer.first_name} ${customer.last_name}
                        </div>
                        <div>
                            <strong>Email:</strong><br>${customer.email}
                        </div>
                        <div>
                            <strong>Phone:</strong><br>${customer.phone || 'N/A'}
                        </div>
                        <div>
                            <strong>Status:</strong><br>
                            <span class="badge badge-${customer.status === 'active' ? 'success' : 'secondary'}">
                                ${customer.status.toUpperCase()}
                            </span>
                        </div>
                        <div>
                            <strong>Total Orders:</strong><br>${customer.total_orders}
                        </div>
                        <div>
                            <strong>Total Spent:</strong><br>ETB ${parseFloat(customer.total_spent).toFixed(2)}
                        </div>
                        <div>
                            <strong>Last Order:</strong><br>${customer.last_order_date ? new Date(customer.last_order_date).toLocaleDateString() : 'No orders yet'}
                        </div>
                        <div>
                            <strong>Registered:</strong><br>${new Date(customer.created_at).toLocaleDateString()}
                        </div>
                    </div>
                    ${customer.address ? `
                        <div style="margin-top: 20px;">
                            <strong>Address:</strong><br>${customer.address}
                        </div>
                    ` : ''}
                </div>
            `;
            document.getElementById('customer_details_content').innerHTML = content;
            document.getElementById('customerDetailsModal').style.display = 'block';
        }

        function closeCustomerDetailsModal() {
            document.getElementById('customerDetailsModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addCustomerModal');
            const detailsModal = document.getElementById('customerDetailsModal');
            if (event.target === addModal) {
                closeAddCustomerModal();
            }
            if (event.target === detailsModal) {
                closeCustomerDetailsModal();
            }
        }
    </script>

    <!-- Mobile Menu Script -->
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
