<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}
require_once __DIR__ . '/../../../config/db_config.php';

$employeeId   = $_SESSION['user_id'];
$employeeName = $_SESSION['user_name'] ?? 'Employee';
$feedbackId   = intval($_GET['id'] ?? 0);

if (!$feedbackId) {
    header('Location: ' . BASE_URL . '/public/employee/reports');
    exit();
}

// Fetch feedback + related usage report + manager info
try {
    $stmt = $pdo->prepare("
        SELECT f.*,
               CONCAT(mgr.first_name, ' ', mgr.last_name) as manager_name,
               mu.quantity_used, mu.waste_amount, mu.notes as usage_notes, mu.created_at as usage_date,
               m.material_name, m.unit,
               o.order_number,
               t.id as task_id
        FROM furn_report_feedback f
        LEFT JOIN furn_users mgr ON f.manager_id = mgr.id
        LEFT JOIN furn_material_usage mu ON f.usage_report_id = mu.id
        LEFT JOIN furn_materials m ON mu.material_id = m.id
        LEFT JOIN furn_production_tasks t ON mu.task_id = t.id
        LEFT JOIN furn_orders o ON t.order_id = o.id
        WHERE f.id = ? AND f.employee_id = ?
    ");
    $stmt->execute([$feedbackId, $employeeId]);
    $feedback = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $feedback = null;
}

if (!$feedback) {
    header('Location: ' . BASE_URL . '/public/employee/reports');
    exit();
}

// Mark as read
$pdo->prepare("UPDATE furn_report_feedback SET is_read = 1 WHERE id = ?")->execute([$feedbackId]);

// Handle reply POST
$replySuccess = false;
$replyError   = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply'])) {
    $reply = trim($_POST['reply']);
    if ($reply) {
        try {
            $pdo->prepare("UPDATE furn_report_feedback SET employee_reply = ?, replied_at = NOW() WHERE id = ? AND employee_id = ?")
                ->execute([$reply, $feedbackId, $employeeId]);
            // Refresh feedback data
            $stmt->execute([$feedbackId, $employeeId]);
            $feedback = $stmt->fetch(PDO::FETCH_ASSOC);
            $replySuccess = true;
        } catch (PDOException $e) {
            $replyError = "Failed to send reply. Please try again.";
        }
    } else {
        $replyError = "Please write something before sending.";
    }
}

$fbColors = ['praise' => '#27AE60', 'warning' => '#E74C3C', 'note' => '#3498DB'];
$fbBg     = ['praise' => '#EAFAF1', 'warning' => '#FDEDEC', 'note' => '#EBF5FB'];
$fbBorder = ['praise' => '#A9DFBF', 'warning' => '#F1948A', 'note' => '#AED6F1'];
$fbIcons  = ['praise' => 'fa-thumbs-up', 'warning' => 'fa-exclamation-triangle', 'note' => 'fa-comment-alt'];
$fbLabels = ['praise' => 'Praise', 'warning' => 'Warning', 'note' => 'Note'];
$t        = $feedback['feedback_type'] ?? 'note';
$color    = $fbColors[$t] ?? '#3498DB';
$bg       = $fbBg[$t] ?? '#EBF5FB';
$border   = $fbBorder[$t] ?? '#AED6F1';
$icon     = $fbIcons[$t] ?? 'fa-comment-alt';
$label    = $fbLabels[$t] ?? 'Note';

$pageTitle = 'Manager Feedback';
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
        .feedback-card {
            background: <?php echo $bg; ?>;
            border: 1.5px solid <?php echo $border; ?>;
            border-left: 5px solid <?php echo $color; ?>;
            border-radius: 12px;
            padding: 28px 30px;
            margin-bottom: 24px;
        }
        .feedback-type-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: <?php echo $color; ?>;
            color: #fff;
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: .4px;
            margin-bottom: 16px;
        }
        .feedback-text {
            font-size: 16px;
            color: #2C3E50;
            line-height: 1.8;
            white-space: pre-wrap;
        }
        .usage-summary {
            background: #fff;
            border: 1px solid #E8E8E8;
            border-radius: 10px;
            padding: 18px 22px;
            margin-bottom: 24px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 14px;
        }
        .usage-item label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: #95A5A6;
            text-transform: uppercase;
            letter-spacing: .6px;
            margin-bottom: 4px;
        }
        .usage-item span {
            font-size: 15px;
            font-weight: 600;
            color: #2C3E50;
        }
        .reply-box {
            background: #fff;
            border: 1.5px solid #E0E0E0;
            border-radius: 12px;
            padding: 24px;
        }
        .reply-box textarea {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #E0E0E0;
            border-radius: 8px;
            font-family: 'Poppins', sans-serif;
            font-size: 14px;
            line-height: 1.6;
            resize: vertical;
            outline: none;
            transition: border-color .2s;
            color: #2C3E50;
        }
        .reply-box textarea:focus { border-color: <?php echo $color; ?>; }
        .btn-send {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 28px;
            background: <?php echo $color; ?>;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity .2s;
            font-family: 'Poppins', sans-serif;
        }
        .btn-send:hover { opacity: .88; }
        .replied-card {
            background: #F8F9FA;
            border: 1.5px solid #DEE2E6;
            border-left: 5px solid #7F8C8D;
            border-radius: 12px;
            padding: 22px 26px;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #7F8C8D;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            margin-bottom: 24px;
            transition: color .2s;
        }
        .back-link:hover { color: #2C3E50; }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>
    <div class="sidebar-overlay"></div>
    <?php include_once __DIR__ . '/../../includes/employee_sidebar.php'; ?>

    <!-- Top Header -->
    <?php 
    $pageTitle = 'Feedback Details';
    include_once __DIR__ . '/../../includes/employee_header.php'; 
    ?>
        <div class="header-left"><div class="system-status"><i class="fas fa-circle"></i> Manager Feedback</div></div>
        <div class="header-right">
            <div class="admin-profile">
                <div class="admin-avatar"><?php echo strtoupper(substr($employeeName, 0, 1)); ?></div>
                <div>
                    <div style="font-weight:600;font-size:14px;"><?php echo htmlspecialchars($employeeName); ?></div>
                    <div class="admin-role-badge" style="background:#27AE60;">EMPLOYEE</div>
                </div>
            </div>
        </div>
    </div>

    <div class="main-content" style="max-width:760px;">

        <a href="<?php echo BASE_URL; ?>/public/employee/reports" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Reports
        </a>

        <!-- Usage Report Summary -->
        <div class="section-card" style="margin-bottom:20px;">
            <div style="font-size:12px;font-weight:700;color:#95A5A6;text-transform:uppercase;letter-spacing:.6px;margin-bottom:14px;">
                <i class="fas fa-boxes"></i> Usage Report Details
            </div>
            <div class="usage-summary">
                <div class="usage-item">
                    <label>Material</label>
                    <span><?php echo htmlspecialchars($feedback['material_name']); ?></span>
                </div>
                <div class="usage-item">
                    <label>Qty Used</label>
                    <span><?php echo $feedback['quantity_used']; ?> <?php echo $feedback['unit']; ?></span>
                </div>
                <div class="usage-item">
                    <label>Waste</label>
                    <span style="color:<?php echo $feedback['waste_amount'] > 0 ? '#E74C3C' : '#27AE60'; ?>">
                        <?php echo $feedback['waste_amount'] > 0 ? $feedback['waste_amount'] . ' ' . $feedback['unit'] : '0'; ?>
                    </span>
                </div>
                <?php if ($feedback['order_number']): ?>
                <div class="usage-item">
                    <label>Order</label>
                    <span><?php echo htmlspecialchars($feedback['order_number']); ?></span>
                </div>
                <?php endif; ?>
                <div class="usage-item">
                    <label>Date</label>
                    <span><?php echo date('M d, Y', strtotime($feedback['usage_date'])); ?></span>
                </div>
                <?php if ($feedback['usage_notes']): ?>
                <div class="usage-item" style="grid-column:1/-1;">
                    <label>Your Notes</label>
                    <span style="font-weight:400;font-size:14px;"><?php echo htmlspecialchars($feedback['usage_notes']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Manager Feedback -->
        <div class="feedback-card">
            <div class="feedback-type-badge">
                <i class="fas <?php echo $icon; ?>"></i> <?php echo $label; ?> from <?php echo htmlspecialchars($feedback['manager_name']); ?>
            </div>
            <div class="feedback-text"><?php echo htmlspecialchars($feedback['feedback']); ?></div>
            <div style="margin-top:14px;font-size:12px;color:#95A5A6;">
                <i class="fas fa-clock"></i> <?php echo date('M d, Y \a\t h:i A', strtotime($feedback['created_at'])); ?>
            </div>
        </div>

        <!-- Reply Section -->
        <?php if ($replySuccess): ?>
            <div style="background:#d4edda;color:#155724;padding:14px 18px;border-radius:8px;margin-bottom:20px;border:1px solid #c3e6cb;">
                <i class="fas fa-check-circle"></i> Your reply was sent successfully.
            </div>
        <?php endif; ?>

        <?php if (!empty($feedback['employee_reply'])): ?>
            <!-- Already replied -->
            <div class="replied-card">
                <div style="font-size:12px;font-weight:700;color:#7F8C8D;text-transform:uppercase;letter-spacing:.6px;margin-bottom:10px;">
                    <i class="fas fa-reply"></i> Your Reply
                </div>
                <div style="font-size:15px;color:#2C3E50;line-height:1.7;white-space:pre-wrap;"><?php echo htmlspecialchars($feedback['employee_reply']); ?></div>
                <div style="margin-top:12px;font-size:12px;color:#95A5A6;">
                    <i class="fas fa-clock"></i> Sent <?php echo date('M d, Y \a\t h:i A', strtotime($feedback['replied_at'])); ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Reply form -->
            <div class="reply-box">
                <div style="font-size:15px;font-weight:600;color:#2C3E50;margin-bottom:16px;">
                    <i class="fas fa-reply" style="color:<?php echo $color; ?>;"></i> Write Your Reply
                </div>

                <?php if ($replyError): ?>
                    <div style="background:#f8d7da;color:#721c24;padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:13px;">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($replyError); ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? $_SESSION['csrf_token'] ?? ''); ?>">
                    <textarea name="reply" rows="5" placeholder="Write your response to the manager's feedback here...&#10;&#10;Be clear and professional."><?php echo isset($_POST['reply']) ? htmlspecialchars($_POST['reply']) : ''; ?></textarea>
                    <div style="display:flex;justify-content:flex-end;margin-top:16px;">
                        <button type="submit" class="btn-send">
                            <i class="fas fa-paper-plane"></i> Send Reply
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

    </div>
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
