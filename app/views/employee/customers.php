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

// Get search parameter
$searchQuery = $_GET['search'] ?? '';

// Fetch only customers whose orders are assigned to this employee via production tasks
$customers = [];
try {
    $query = "
        SELECT DISTINCT
            u.id,
            u.first_name,
            u.last_name,
            u.phone,
            u.status,
            COUNT(DISTINCT o.id) as total_orders,
            MAX(o.created_at) as last_order_date
        FROM furn_users u
        INNER JOIN furn_orders o ON u.id = o.customer_id
        INNER JOIN furn_production_tasks t ON t.order_id = o.id AND t.employee_id = ?
        WHERE u.role = 'customer'
    ";

    $params = [$employeeId];

    if ($searchQuery) {
        $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.phone LIKE ?)";
        $searchParam = "%$searchQuery%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    $query .= " GROUP BY u.id, u.first_name, u.last_name, u.phone, u.status ORDER BY last_order_date DESC";

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
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
            <div class="stat-card" style="border-left: 4px solid #3498DB;">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <div>
                        <div class="stat-value"><?php echo count($customers); ?></div>
                        <div class="stat-label">My Customers</div>
                    </div>
                    <div style="font-size: 32px; color: #3498DB;"><i class="fas fa-users"></i></div>
                </div>
            </div>
            <div class="stat-card" style="border-left: 4px solid #F39C12;">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <div>
                        <div class="stat-value"><?php echo array_sum(array_column($customers, 'total_orders')); ?></div>
                        <div class="stat-label">Assigned Orders</div>
                    </div>
                    <div style="font-size: 32px; color: #F39C12;"><i class="fas fa-shopping-cart"></i></div>
                </div>
            </div>
        </div>

        <!-- Customers Section -->
        <div class="section-card">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-users"></i> My Assigned Customers</h2>
            </div>

            <!-- Search -->
            <div class="filter-section" style="margin-bottom: 20px;">
                <form method="GET" action="" style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <div class="form-group" style="margin: 0; flex: 1; min-width: 250px;">
                        <label>Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Name or phone..." value="<?php echo htmlspecialchars($searchQuery); ?>">
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
                <p style="text-align: center; color: #7f8c8d; padding: 40px;">No assigned customers found.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table mobile-cards">
                        <thead>
                            <tr>
                                <th>Customer Name</th>
                                <th>Phone</th>
                                <th>Assigned Orders</th>
                                <th>Last Order</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($customer['phone'] ?? 'N/A'); ?></td>
                                    <td><?php echo $customer['total_orders']; ?></td>
                                    <td>
                                        <?php echo $customer['last_order_date']
                                            ? date('M d, Y', strtotime($customer['last_order_date']))
                                            : '<span style="color:#95A5A6;">—</span>'; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $customer['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($customer['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Mobile Menu Script -->
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
