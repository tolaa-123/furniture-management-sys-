<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'manager') {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}

require_once __DIR__ . '/../../../config/db_config.php';

$managerName = $_SESSION['user_name'] ?? 'Manager User';
$managerId = $_SESSION['user_id'];

// Handle complaint resolution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'resolve_complaint') {
        $complaintId = intval($_POST['complaint_id']);
        $response = trim($_POST['manager_response'] ?? '');
        
        try {
            $pdo->prepare("UPDATE furn_complaints SET status='resolved', manager_response=?, resolved_by=?, resolved_at=NOW() WHERE id=?")
                ->execute([$response, $managerId, $complaintId]);
            
            // Get complaint details for notification
            $stmt = $pdo->prepare("SELECT customer_id, subject FROM furn_complaints WHERE id=?");
            $stmt->execute([$complaintId]);
            $complaint = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Notify customer
            require_once __DIR__ . '/../../includes/notification_helper.php';
            insertNotification($pdo, $complaint['customer_id'], 'complaint', 'Complaint Resolved',
                'Your complaint "' . ($complaint['subject'] ?? '') . '" has been resolved.' . ($response ? ' Response: ' . $response : ''),
                $complaintId, '/customer/my-orders', 'normal');
            
            $_SESSION['success_message'] = 'Complaint resolved successfully.';
            header('Location: ' . BASE_URL . '/public/manager/complaints');
            exit();
        } catch (PDOException $e) {
            $error = 'Error resolving complaint: ' . $e->getMessage();
        }
    }
}

// Filter
$statusFilter = $_GET['status'] ?? 'all';

// Fetch complaints
try {
    $sql = "
        SELECT c.*,
               CONCAT(u.first_name, ' ', u.last_name) as customer_name,
               u.email as customer_email,
               o.order_number,
               o.furniture_type,
               CONCAT(mgr.first_name, ' ', mgr.last_name) as resolved_by_name
        FROM furn_complaints c
        LEFT JOIN furn_users u ON c.customer_id = u.id
        LEFT JOIN furn_orders o ON c.order_id = o.id
        LEFT JOIN furn_users mgr ON c.resolved_by = mgr.id
    ";
    
    if ($statusFilter !== 'all') {
        $sql .= " WHERE c.status = ?";
        $params = [$statusFilter];
    } else {
        $params = [];
    }
    
    $sql .= " ORDER BY c.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error fetching complaints: ' . $e->getMessage();
    $complaints = [];
}

// Stats
$totalComplaints = count($complaints);
$openComplaints = count(array_filter($complaints, fn($c) => $c['status'] === 'open'));
$resolvedComplaints = count(array_filter($complaints, fn($c) => $c['status'] === 'resolved'));

$pageTitle = 'Complaint Management';
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
    <style>
        .complaint-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid #e74c3c;
        }
        .complaint-card.resolved {
            border-left-color: #27ae60;
            opacity: 0.8;
        }
        .complaint-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f0f0f0;
        }
        .badge-open {
            background: #fee;
            color: #e74c3c;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-resolved {
            background: #e8f5e9;
            color: #27ae60;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle" aria-label="Toggle Menu">
        <i class="fas fa-bars"></i>
    </button>

    <div class="sidebar-overlay"></div>

    <?php include_once __DIR__ . '/../../includes/manager_sidebar.php'; ?>

    <?php 
    $pageTitle = 'Complaint Management';
    include_once __DIR__ . '/../../includes/manager_header.php'; 
    ?>
        <div class="header-left">
            <div class="system-status">
                <i class="fas fa-comments"></i> Customer Complaints
            </div>
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
        <h2 style="margin-bottom: 20px; color: #2c3e50;">
            <i class="fas fa-comments" style="color: #8B4513;"></i> Complaint Management
        </h2>

        <?php if (isset($_SESSION['success_message'])): ?>
        <div style="background: #e8f5e9; border-left: 4px solid #27ae60; padding: 12px 16px; margin-bottom: 20px; border-radius: 4px; color: #27ae60;">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success_message']); ?>
        </div>
        <?php unset($_SESSION['success_message']); endif; ?>

        <!-- Stats Cards -->
        <div class="stats-grid" style="margin-bottom: 24px;">
            <div class="stat-card" style="border-left: 4px solid #e74c3c;">
                <div class="stat-value" style="color: #e74c3c;"><?php echo $openComplaints; ?></div>
                <div class="stat-label">Open Complaints</div>
            </div>
            <div class="stat-card" style="border-left: 4px solid #27ae60;">
                <div class="stat-value" style="color: #27ae60;"><?php echo $resolvedComplaints; ?></div>
                <div class="stat-label">Resolved</div>
            </div>
            <div class="stat-card" style="border-left: 4px solid #3498db;">
                <div class="stat-value" style="color: #3498db;"><?php echo $totalComplaints; ?></div>
                <div class="stat-label">Total</div>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div style="display: flex; gap: 8px; margin-bottom: 20px;">
            <a href="?status=all" style="padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; text-decoration: none;
               background: <?php echo $statusFilter === 'all' ? '#8B4513' : '#fff'; ?>;
               color: <?php echo $statusFilter === 'all' ? '#fff' : '#555'; ?>;
               border: 2px solid <?php echo $statusFilter === 'all' ? '#8B4513' : '#e0e0e0'; ?>;">
                All (<?php echo $totalComplaints; ?>)
            </a>
            <a href="?status=open" style="padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; text-decoration: none;
               background: <?php echo $statusFilter === 'open' ? '#e74c3c' : '#fff'; ?>;
               color: <?php echo $statusFilter === 'open' ? '#fff' : '#555'; ?>;
               border: 2px solid <?php echo $statusFilter === 'open' ? '#e74c3c' : '#e0e0e0'; ?>;">
                Open (<?php echo $openComplaints; ?>)
            </a>
            <a href="?status=resolved" style="padding: 8px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; text-decoration: none;
               background: <?php echo $statusFilter === 'resolved' ? '#27ae60' : '#fff'; ?>;
               color: <?php echo $statusFilter === 'resolved' ? '#fff' : '#555'; ?>;
               border: 2px solid <?php echo $statusFilter === 'resolved' ? '#27ae60' : '#e0e0e0'; ?>;">
                Resolved (<?php echo $resolvedComplaints; ?>)
            </a>
        </div>

        <!-- Complaints List -->
        <?php if (empty($complaints)): ?>
            <div style="background: white; border-radius: 12px; padding: 40px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                <i class="fas fa-check-circle" style="font-size: 48px; color: #27ae60; margin-bottom: 15px;"></i>
                <h3 style="color: #2c3e50;">No complaints found</h3>
                <p style="color: #888;">All clear! No customer complaints to display.</p>
            </div>
        <?php else: ?>
            <?php foreach ($complaints as $complaint): ?>
            <div class="complaint-card <?php echo $complaint['status'] === 'resolved' ? 'resolved' : ''; ?>">
                <!-- Header -->
                <div class="complaint-header">
                    <div>
                        <h3 style="margin: 0 0 8px; color: #2c3e50;">
                            <i class="fas fa-exclamation-circle" style="color: <?php echo $complaint['status'] === 'open' ? '#e74c3c' : '#27ae60'; ?>;"></i>
                            <?php echo htmlspecialchars($complaint['subject']); ?>
                        </h3>
                        <p style="margin: 0; font-size: 13px; color: #888;">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($complaint['customer_name']); ?>
                            &nbsp;|&nbsp;
                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($complaint['customer_email']); ?>
                            &nbsp;|&nbsp;
                            <i class="fas fa-calendar"></i> <?php echo date('M d, Y H:i', strtotime($complaint['created_at'])); ?>
                        </p>
                        <?php if ($complaint['order_number']): ?>
                        <p style="margin: 5px 0 0; font-size: 13px; color: #888;">
                            <i class="fas fa-box"></i> Order: <?php echo htmlspecialchars($complaint['order_number']); ?>
                            <?php if ($complaint['furniture_type']): ?>
                            (<?php echo htmlspecialchars($complaint['furniture_type']); ?>)
                            <?php endif; ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <?php if ($complaint['status'] === 'open'): ?>
                            <span class="badge-open"><i class="fas fa-clock"></i> Open</span>
                        <?php else: ?>
                            <span class="badge-resolved"><i class="fas fa-check"></i> Resolved</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Message -->
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                    <p style="margin: 0; color: #555; line-height: 1.6;">
                        <?php echo nl2br(htmlspecialchars($complaint['message'])); ?>
                    </p>
                </div>

                <!-- Resolved Info -->
                <?php if ($complaint['status'] === 'resolved'): ?>
                <div style="background: #e8f5e9; padding: 12px; border-radius: 6px; margin-bottom: 15px;">
                    <p style="margin: 0 0 5px; font-size: 12px; color: #27ae60; font-weight: 600;">
                        <i class="fas fa-check-circle"></i> Resolved by <?php echo htmlspecialchars($complaint['resolved_by_name'] ?? 'Unknown'); ?>
                        on <?php echo date('M d, Y H:i', strtotime($complaint['resolved_at'])); ?>
                    </p>
                    <?php if ($complaint['manager_response']): ?>
                    <p style="margin: 0; color: #555; font-size: 13px;">
                        <strong>Response:</strong> <?php echo nl2br(htmlspecialchars($complaint['manager_response'])); ?>
                    </p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Resolve Button -->
                <?php if ($complaint['status'] === 'open'): ?>
                <button onclick="showResolveModal(<?php echo $complaint['id']; ?>, '<?php echo htmlspecialchars($complaint['subject'], ENT_QUOTES); ?>')"
                        style="padding: 10px 20px; background: #27ae60; color: white; border: none; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">
                    <i class="fas fa-check"></i> Resolve Complaint
                </button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Resolve Modal -->
    <div id="resolveModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:white; border-radius:12px; width:100%; max-width:500px; margin:20px; overflow:hidden; box-shadow:0 8px 30px rgba(0,0,0,0.2);">
            <div style="background:linear-gradient(135deg,#27ae60,#219653); padding:16px 20px; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0; color:white; font-size:16px;">
                    <i class="fas fa-check-circle"></i> Resolve Complaint
                </h3>
                <button onclick="closeResolveModal()" style="background:none; border:none; color:white; font-size:20px; cursor:pointer; line-height:1;">&times;</button>
            </div>
            <form method="POST" style="padding:20px;">
                <input type="hidden" name="action" value="resolve_complaint">
                <input type="hidden" name="complaint_id" id="modal_complaint_id">
                
                <div style="margin-bottom:15px;">
                    <label style="font-weight:600; font-size:13px; color:#2c3e50; display:block; margin-bottom:6px;">
                        Complaint Subject
                    </label>
                    <div id="modal_subject" style="padding:10px; background:#f8f9fa; border-radius:6px; color:#555; font-size:14px;"></div>
                </div>

                <div style="margin-bottom:20px;">
                    <label style="font-weight:600; font-size:13px; color:#2c3e50; display:block; margin-bottom:6px;">
                        Your Response <span style="color:#e74c3c;">*</span>
                    </label>
                    <textarea name="manager_response" rows="5" required
                              style="width:100%; padding:10px 12px; border:1.5px solid #ddd; border-radius:8px; font-size:14px; font-family:inherit; resize:vertical; box-sizing:border-box;"
                              placeholder="Explain how you resolved the issue..."></textarea>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px;">
                    <button type="button" onclick="closeResolveModal()"
                            style="padding:9px 20px; border:1.5px solid #ddd; background:white; border-radius:8px; font-size:14px; cursor:pointer; font-family:inherit;">
                        Cancel
                    </button>
                    <button type="submit"
                            style="padding:9px 20px; background:#27ae60; color:white; border:none; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; font-family:inherit;">
                        <i class="fas fa-check"></i> Mark as Resolved
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function showResolveModal(complaintId, subject) {
        document.getElementById('modal_complaint_id').value = complaintId;
        document.getElementById('modal_subject').textContent = subject;
        document.getElementById('resolveModal').style.display = 'flex';
    }

    function closeResolveModal() {
        document.getElementById('resolveModal').style.display = 'none';
    }
    </script>

    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
