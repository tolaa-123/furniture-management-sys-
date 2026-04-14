<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'employee') {
    header('Location: ' . BASE_URL . '/public/login'); exit();
}
require_once __DIR__ . '/../../../config/db_config.php';
$employeeName = $_SESSION['user_name'] ?? 'Employee User';
$employeeId   = $_SESSION['user_id'];

// Fetch material usage (with manager feedback)
$materials = []; $pendingReplies = 0;
try {
    $stmt = $pdo->prepare("
        SELECT mu.*, m.material_name, m.unit, o.order_number,
               f.id as feedback_id, f.feedback, f.feedback_type,
               f.employee_reply, f.replied_at,
               CONCAT(mgr.first_name,' ',mgr.last_name) as manager_name
        FROM furn_material_usage mu
        LEFT JOIN furn_materials m ON mu.material_id = m.id
        LEFT JOIN furn_production_tasks t2 ON mu.task_id = t2.id
        LEFT JOIN furn_orders o ON t2.order_id = o.id
        LEFT JOIN furn_report_feedback f ON f.usage_report_id = mu.id
        LEFT JOIN furn_users mgr ON f.manager_id = mgr.id
        WHERE mu.employee_id = ? ORDER BY mu.created_at DESC
    ");
    $stmt->execute([$employeeId]);
    $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $pdo->prepare("UPDATE furn_report_feedback SET is_read=1 WHERE employee_id=? AND usage_report_id IS NOT NULL AND is_read=0")->execute([$employeeId]);
    $pendingReplies = count(array_filter($materials, fn($m) => !empty($m['feedback_id']) && empty($m['employee_reply'])));
} catch (PDOException $e) { error_log("materials: ".$e->getMessage()); }

// Fetch submitted reports by type (with manager feedback joined)
$submitted = ['task_progress'=>[],'incident'=>[],'daily_summary'=>[],'leave_request'=>[]];
$pendingReportReplies = 0;
try {
    $stmt = $pdo->prepare("
        SELECT r.*,
               f.id as feedback_id, f.feedback, f.feedback_type,
               f.employee_reply, f.replied_at,
               CONCAT(mgr.first_name,' ',mgr.last_name) as manager_name
        FROM furn_employee_reports r
        LEFT JOIN furn_report_feedback f ON f.report_id = r.id
        LEFT JOIN furn_users mgr ON f.manager_id = mgr.id
        WHERE r.employee_id = ? ORDER BY r.created_at DESC
    ");
    $stmt->execute([$employeeId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $r['report_data'] = json_decode($r['report_data'], true) ?? [];
        if (isset($submitted[$r['report_type']])) {
            $submitted[$r['report_type']][] = $r;
            if (!empty($r['feedback_id']) && empty($r['employee_reply'])) $pendingReportReplies++;
        }
    }
    $pdo->prepare("UPDATE furn_report_feedback SET is_read=1 WHERE employee_id=? AND report_id IS NOT NULL AND is_read=0")->execute([$employeeId]);
} catch (PDOException $e) { error_log("submitted: ".$e->getMessage()); }

// Fetch manager reports sent to this specific employee
$managerReports = [];
$managerPendingCount = 0;
try {
    // Ensure correct schema
    $pdo->exec("ALTER TABLE furn_manager_reports MODIFY report_type VARCHAR(50) NOT NULL");
    $pdo->exec("ALTER TABLE furn_manager_reports MODIFY report_to_role ENUM('admin','manager','employee') DEFAULT 'admin'");
    
    $stmt = $pdo->prepare("
        SELECT mr.*, CONCAT(u.first_name,' ',u.last_name) as manager_name
        FROM furn_manager_reports mr
        LEFT JOIN furn_users u ON mr.manager_id = u.id
        WHERE mr.report_to_role = 'employee' AND mr.report_to_id = ?
        ORDER BY mr.created_at DESC
    ");
    $stmt->execute([$employeeId]);
    $managerReports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $managerPendingCount = count(array_filter($managerReports, fn($r) => $r['status'] === 'submitted'));
} catch (PDOException $e) {
    error_log("manager to employee reports error: " . $e->getMessage());
}

// Helper: build feedback button HTML for a report row
function feedbackBtn($r) {
    if (empty($r['feedback_id'])) return '<span style="color:#BDC3C7;font-size:13px;">No feedback yet</span>';
    $fbc=['praise'=>'#27AE60','warning'=>'#E74C3C','note'=>'#3498DB'];
    $fbi=['praise'=>'fa-thumbs-up','warning'=>'fa-exclamation-triangle','note'=>'fa-comment'];
    $ft=$r['feedback_type']; $fc=$fbc[$ft]??'#3498DB'; $fi=$fbi[$ft]??'fa-comment';
    $hasReply=!empty($r['employee_reply']);
    // Use same encoding pattern as Material Usage: json_encode then htmlspecialchars for the attribute value
    $fbData = htmlspecialchars(json_encode([
        'feedback_id'   => $r['feedback_id'],
        'feedback_type' => $ft,
        'feedback'      => $r['feedback'],
        'manager_name'  => $r['manager_name'],
        'created_at'    => $r['created_at'],
        'employee_reply'=> $r['employee_reply'] ?? '',
        'replied_at'    => $r['replied_at'] ?? '',
        'report_title'  => $r['title'] ?? '',
        'report_type'   => $r['report_type'] ?? ''
    ], JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES);
    $replied = $hasReply ? '✓ Replied' : 'Reply';
    return "<button onclick='openFeedbackPanel(JSON.parse(this.dataset.fb))' data-fb='{$fbData}' data-fb-id=\"{$r['feedback_id']}\"
        style=\"display:inline-flex;align-items:center;gap:7px;padding:7px 14px;background:{$fc};color:#fff;border:none;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;\">
        <i class=\"fas {$fi}\"></i> See Feedback
        <span style=\"background:rgba(255,255,255,0.3);border-radius:10px;padding:1px 7px;font-size:11px;\">{$replied}</span>
    </button>";
}

$pageTitle = 'Reports';
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
        .report-header { background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;padding:28px 30px;border-radius:10px;margin-bottom:24px; }
        .rpt-section-header { display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px; }
        .report-section { display:none; }
        .report-section.visible { display:block !important; transition:none !important; animation:none !important; }
        @media print { .no-print { display:none !important; } }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle no-print" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>
    <div class="sidebar-overlay no-print"></div>
    <div class="no-print"><?php include_once __DIR__.'/../../includes/employee_sidebar.php'; ?></div>
    <!-- Top Header -->
    <?php 
    $pageTitle = 'Reports';
    include_once __DIR__ . '/../../includes/employee_header.php'; 
    ?>
        <div class="header-left"><div class="system-status"><i class="fas fa-circle"></i> My Reports</div></div>
        <div class="header-right">
            <div class="admin-profile">
                <div class="admin-avatar"><?php echo strtoupper(substr($employeeName,0,1)); ?></div>
                <div>
                    <div style="font-weight:600;font-size:14px;"><?php echo htmlspecialchars($employeeName); ?></div>
                    <div class="admin-role-badge" style="background:#27AE60;">EMPLOYEE</div>
                </div>
            </div>
        </div>
    </div>

    <div class="main-content">

        <!-- Debug Info -->
        <div style="background:#fff3e0;border:1px solid #ffcc80;color:#e65100;padding:10px 16px;border-radius:8px;margin:16px 0;font-size:13px;">
            <i class="fas fa-info-circle"></i> 
            Manager Reports: <?php echo count($managerReports); ?> | 
            Pending: <?php echo $managerPendingCount; ?>
        </div>

        <?php
        function statusBadge($s) {
            $c=['submitted'=>'#F39C12','reviewed'=>'#3498DB','acknowledged'=>'#27AE60'];
            $col=$c[$s]??'#7F8C8D';
            return "<span style='background:{$col}22;color:{$col};border:1px solid {$col}55;border-radius:12px;padding:2px 10px;font-size:11px;font-weight:600;'>".ucfirst($s)."</span>";
        }
        ?>

        <!-- Page Header -->
        <div class="report-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
            <div>
                <h1 style="margin:0 0 6px;font-size:26px;"><i class="fas fa-chart-line"></i> My Reports</h1>
                <p style="margin:0;opacity:.9;">Employee: <strong><?php echo htmlspecialchars($employeeName); ?></strong></p>
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">

                <!-- Nav: Reports Sent by Manager -->
                <div style="position:relative;" id="mgrNavWrapper">
                    <button onclick="toggleMenu('mgrNavMenu')"
                        style="display:inline-flex;align-items:center;gap:9px;padding:12px 18px;background:rgba(255,255,255,0.15);color:#fff;border:2px solid rgba(255,255,255,0.4);border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;backdrop-filter:blur(4px);">
                        <i class="fas fa-user-tie"></i> Reports Sent by Manager
                        <?php if ($managerPendingCount > 0): ?>
                        <span style="background:#E74C3C;color:#fff;border-radius:10px;padding:1px 7px;font-size:11px;font-weight:700;"><?php echo $managerPendingCount; ?></span>
                        <?php endif; ?>
                        <i class="fas fa-chevron-down" style="font-size:10px;opacity:.8;"></i>
                    </button>
                    <div id="mgrNavMenu" style="display:none;position:absolute;right:0;top:calc(100% + 8px);background:#fff;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,0.18);min-width:240px;z-index:1000;overflow:hidden;border:1px solid #F0F0F0;">
                        <div style="padding:10px 16px 8px;font-size:11px;font-weight:700;color:#95A5A6;text-transform:uppercase;letter-spacing:.6px;border-bottom:1px solid #F5F5F5;">Jump to Report Type</div>
                        <?php
                        $mgrNavItems = [
                            ['type'=>'production_update', 'icon'=>'fa-industry',            'color'=>'#3498DB','label'=>'Production Update'],
                            ['type'=>'inventory_summary', 'icon'=>'fa-boxes',               'color'=>'#9B59B6','label'=>'Inventory Summary'],
                            ['type'=>'team_performance',  'icon'=>'fa-users',               'color'=>'#27AE60','label'=>'Team Performance'],
                            ['type'=>'incident',          'icon'=>'fa-exclamation-triangle','color'=>'#E74C3C','label'=>'Incident / Problem'],
                            ['type'=>'daily_summary',     'icon'=>'fa-clipboard-list',      'color'=>'#F39C12','label'=>'Daily Summary'],
                            ['type'=>'leave_request',     'icon'=>'fa-calendar-times',      'color'=>'#E67E22','label'=>'Leave Request'],
                            ['type'=>'other',             'icon'=>'fa-file-alt',            'color'=>'#7F8C8D','label'=>'General'],
                        ];
                        foreach ($mgrNavItems as $ni): ?>
                        <a href="#sec-manager-reports" onclick="showManagerType('<?php echo $ni['type']; ?>')"
                            style="display:flex;align-items:center;gap:12px;padding:11px 16px;text-decoration:none;color:#2C3E50;font-size:13px;"
                            onmouseover="this.style.background='#F8F9FA'" onmouseout="this.style.background='transparent'">
                            <div style="width:30px;height:30px;border-radius:7px;background:<?php echo $ni['color']; ?>18;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas <?php echo $ni['icon']; ?>" style="color:<?php echo $ni['color']; ?>;font-size:13px;"></i>
                            </div>
                            <span style="font-weight:500;"><?php echo $ni['label']; ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Nav: Reports You Sent to Manager -->
                <div style="position:relative;" id="myNavWrapper">
                    <button onclick="toggleMenu('myNavMenu')"
                        style="display:inline-flex;align-items:center;gap:9px;padding:12px 18px;background:rgba(255,255,255,0.15);color:#fff;border:2px solid rgba(255,255,255,0.4);border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;backdrop-filter:blur(4px);">
                        <i class="fas fa-paper-plane"></i> My Submitted Reports
                        <i class="fas fa-chevron-down" style="font-size:10px;opacity:.8;"></i>
                    </button>
                    <div id="myNavMenu" style="display:none;position:absolute;right:0;top:calc(100% + 8px);background:#fff;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,0.18);min-width:240px;z-index:1000;overflow:hidden;border:1px solid #F0F0F0;">
                        <div style="padding:10px 16px 8px;font-size:11px;font-weight:700;color:#95A5A6;text-transform:uppercase;letter-spacing:.6px;border-bottom:1px solid #F5F5F5;">Jump to Report Type</div>
                        <?php
                        $myNavItems = [
                            ['sec'=>'sec-task-progress', 'icon'=>'fa-tasks',               'color'=>'#3498DB','label'=>'Task Progress',     'count'=>count($submitted['task_progress'])],
                            ['sec'=>'sec-material-usage','icon'=>'fa-boxes',               'color'=>'#9B59B6','label'=>'Material Usage',     'count'=>count($materials)],
                            ['sec'=>'sec-incident',      'icon'=>'fa-exclamation-triangle','color'=>'#E74C3C','label'=>'Incident / Problem', 'count'=>count($submitted['incident'])],
                            ['sec'=>'sec-daily-summary', 'icon'=>'fa-clipboard-list',      'color'=>'#27AE60','label'=>'Daily Work Summary', 'count'=>count($submitted['daily_summary'])],
                            ['sec'=>'sec-leave-request', 'icon'=>'fa-calendar-times',      'color'=>'#F39C12','label'=>'Leave Request',      'count'=>count($submitted['leave_request'])],
                        ];
                        foreach ($myNavItems as $ni): ?>
                        <a href="#<?php echo $ni['sec']; ?>" onclick="showSection('<?php echo $ni['sec']; ?>')"
                            style="display:flex;align-items:center;gap:12px;padding:11px 16px;text-decoration:none;color:#2C3E50;font-size:13px;"
                            onmouseover="this.style.background='#F8F9FA'" onmouseout="this.style.background='transparent'">
                            <div style="width:30px;height:30px;border-radius:7px;background:<?php echo $ni['color']; ?>18;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas <?php echo $ni['icon']; ?>" style="color:<?php echo $ni['color']; ?>;font-size:13px;"></i>
                            </div>
                            <span style="font-weight:500;flex:1;"><?php echo $ni['label']; ?></span>
                            <span style="background:<?php echo $ni['color']; ?>22;color:<?php echo $ni['color']; ?>;border-radius:10px;padding:1px 8px;font-size:11px;font-weight:700;"><?php echo $ni['count']; ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Write Report -->
                <div style="position:relative;" id="writeReportWrapper">
                    <button onclick="toggleReportMenu()"
                        style="display:inline-flex;align-items:center;gap:9px;padding:12px 20px;background:#fff;color:#764ba2;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;box-shadow:0 2px 10px rgba(0,0,0,0.15);">
                        <i class="fas fa-pen-to-square"></i> Write Report <i class="fas fa-chevron-down" style="font-size:11px;opacity:.7;"></i>
                    </button>
                    <div id="reportTypeMenu" style="display:none;position:absolute;right:0;top:calc(100% + 8px);background:#fff;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,0.18);min-width:260px;z-index:999;overflow:hidden;border:1px solid #F0F0F0;">
                    <div style="padding:10px 16px 8px;font-size:11px;font-weight:700;color:#95A5A6;text-transform:uppercase;letter-spacing:.6px;border-bottom:1px solid #F5F5F5;">Select Report Type</div>
                    <?php
                    $menuItems=[
                        ['type'=>'task_progress', 'icon'=>'fa-tasks',               'color'=>'#3498DB','label'=>'Task Progress',    'desc'=>'Update progress on a task'],
                        ['type'=>'material_usage','icon'=>'fa-boxes',               'color'=>'#9B59B6','label'=>'Material Usage',    'desc'=>'Log materials used & waste'],
                        ['type'=>'incident',      'icon'=>'fa-exclamation-triangle','color'=>'#E74C3C','label'=>'Incident / Problem','desc'=>'Report an issue or accident'],
                        ['type'=>'daily_summary', 'icon'=>'fa-clipboard-list',      'color'=>'#27AE60','label'=>'Daily Work Summary','desc'=>'End-of-day work summary'],
                        ['type'=>'leave_request', 'icon'=>'fa-calendar-times',      'color'=>'#F39C12','label'=>'Leave Request',     'desc'=>'Request time off'],
                    ];
                    foreach ($menuItems as $mi): ?>
                    <a href="<?php echo BASE_URL; ?>/public/employee/submit-report?type=<?php echo $mi['type']; ?>"
                        style="display:flex;align-items:center;gap:13px;padding:12px 16px;text-decoration:none;color:#2C3E50;"
                        onmouseover="this.style.background='#F8F9FA'" onmouseout="this.style.background='transparent'">
                        <div style="width:36px;height:36px;border-radius:8px;background:<?php echo $mi['color']; ?>18;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="fas <?php echo $mi['icon']; ?>" style="color:<?php echo $mi['color']; ?>;font-size:15px;"></i>
                        </div>
                        <div>
                            <div style="font-weight:600;font-size:13px;"><?php echo $mi['label']; ?></div>
                            <div style="font-size:11px;color:#95A5A6;margin-top:1px;"><?php echo $mi['desc']; ?></div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                </div><!-- end writeReportWrapper -->
            </div><!-- end buttons flex -->
        </div>

        <!-- ── 1. TASK PROGRESS ── -->
        <div id="sec-task-progress" class="section-card report-section">
            <div class="section-header rpt-section-header">
                <h2 class="section-title"><i class="fas fa-tasks" style="color:#3498DB;"></i> Task Progress Reports
                    <span style="background:#3498DB22;color:#3498DB;border-radius:12px;padding:2px 10px;font-size:12px;font-weight:600;margin-left:8px;"><?php echo count($submitted['task_progress']); ?></span>
                </h2>
            </div>
            <?php $pendingTP = count(array_filter($submitted['task_progress'], fn($r)=>!empty($r['feedback_id'])&&empty($r['employee_reply']))); ?>
            <?php if ($pendingTP > 0): ?>
            <div style="background:#FFF3CD;border:1px solid #FFE69C;border-radius:8px;padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:10px;">
                <i class="fas fa-bell" style="color:#F39C12;font-size:18px;"></i>
                <span style="color:#856404;font-weight:500;">You have <strong><?php echo $pendingTP; ?></strong> manager feedback(s) waiting for your reply. Click <em>See Feedback</em> on the row to respond.</span>
            </div>
            <?php endif; ?>
            <?php if (empty($submitted['task_progress'])): ?>
                <p style="text-align:center;color:#95A5A6;padding:30px 0;">No task progress reports submitted yet.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Title</th><th>Progress</th><th>Submitted</th><th>Status</th><th>Manager Feedback</th></tr></thead>
                    <tbody>
                    <?php foreach ($submitted['task_progress'] as $r): $d=$r['report_data']; ?>
                        <tr>
                            <td><?php echo htmlspecialchars($r['title']); ?></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px;min-width:120px;">
                                    <div style="flex:1;background:#ECF0F1;border-radius:4px;height:8px;">
                                        <div style="width:<?php echo min(100,(int)($d['progress']??0)); ?>%;background:#3498DB;height:8px;border-radius:4px;"></div>
                                    </div>
                                    <strong><?php echo $d['progress']??0; ?>%</strong>
                                </div>
                            </td>
                            <td style="font-size:13px;color:#7F8C8D;"><?php echo date('M d, Y',strtotime($r['created_at'])); ?></td>
                            <td><?php echo statusBadge($r['status']); ?></td>
                            <td><?php echo feedbackBtn($r); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- ── 2. MATERIAL USAGE ── -->
        <div id="sec-material-usage" class="section-card report-section">
            <div class="section-header rpt-section-header">
                <h2 class="section-title"><i class="fas fa-boxes" style="color:#9B59B6;"></i> Material Usage
                    <span style="background:#9B59B622;color:#9B59B6;border-radius:12px;padding:2px 10px;font-size:12px;font-weight:600;margin-left:8px;"><?php echo count($materials); ?></span>
                </h2>
            </div>
            <?php if ($pendingReplies > 0): ?>
            <div style="background:#FFF3CD;border:1px solid #FFE69C;border-radius:8px;padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:10px;">
                <i class="fas fa-bell" style="color:#F39C12;font-size:18px;"></i>
                <span style="color:#856404;font-weight:500;">You have <strong><?php echo $pendingReplies; ?></strong> manager feedback(s) waiting for your reply. Click <em>See Feedback</em> on the row to respond.</span>
            </div>
            <?php endif; ?>
            <?php if (empty($materials)): ?>
                <p style="text-align:center;color:#95A5A6;padding:30px 0;">No material usage records yet.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Material</th><th>Qty Used</th><th>Waste</th><th>Date</th><th>Manager Feedback</th></tr></thead>
                    <tbody>
                    <?php foreach ($materials as $mat): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($mat['material_name']); ?></td>
                            <td><strong><?php echo $mat['quantity_used'].' '.$mat['unit']; ?></strong></td>
                            <td><?php echo $mat['waste_amount']>0 ? '<span style="color:#E74C3C;">'.$mat['waste_amount'].' '.$mat['unit'].'</span>' : '<span style="color:#27AE60;">0</span>'; ?></td>
                            <td><?php echo date('M d, Y',strtotime($mat['created_at'])); ?></td>
                            <td>
                                <?php if (!empty($mat['feedback_id'])): ?>
                                    <?php
                                    $fbc=['praise'=>'#27AE60','warning'=>'#E74C3C','note'=>'#3498DB'];
                                    $fbi=['praise'=>'fa-thumbs-up','warning'=>'fa-exclamation-triangle','note'=>'fa-comment'];
                                    $ft=$mat['feedback_type']; $fc=$fbc[$ft]??'#3498DB'; $fi=$fbi[$ft]??'fa-comment';
                                    $hasReply=!empty($mat['employee_reply']);
                                    $fbData=htmlspecialchars(json_encode(['feedback_id'=>$mat['feedback_id'],'feedback_type'=>$ft,'feedback'=>$mat['feedback'],'manager_name'=>$mat['manager_name'],'created_at'=>$mat['created_at'],'employee_reply'=>$mat['employee_reply']??'','replied_at'=>$mat['replied_at']??'','report_title'=>$mat['material_name'].' usage','report_type'=>'material_usage'], JSON_HEX_APOS|JSON_HEX_QUOT),ENT_QUOTES);
                                    ?>
                                    <button onclick='openFeedbackPanel(JSON.parse(this.dataset.fb))' data-fb='<?php echo $fbData; ?>'
                                        data-fb-id="<?php echo $mat['feedback_id']; ?>"
                                        style="display:inline-flex;align-items:center;gap:7px;padding:7px 14px;background:<?php echo $fc; ?>;color:#fff;border:none;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;">
                                        <i class="fas <?php echo $fi; ?>"></i> See Feedback
                                        <span style="background:rgba(255,255,255,0.3);border-radius:10px;padding:1px 7px;font-size:11px;"><?php echo $hasReply?'✓ Replied':'Reply'; ?></span>
                                    </button>
                                <?php else: ?>
                                    <span style="color:#BDC3C7;font-size:13px;">No feedback yet</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- ── 3. INCIDENT / PROBLEM ── -->
        <div id="sec-incident" class="section-card report-section">
            <div class="section-header rpt-section-header">
                <h2 class="section-title"><i class="fas fa-exclamation-triangle" style="color:#E74C3C;"></i> Incident / Problem Reports
                    <span style="background:#E74C3C22;color:#E74C3C;border-radius:12px;padding:2px 10px;font-size:12px;font-weight:600;margin-left:8px;"><?php echo count($submitted['incident']); ?></span>
                </h2>
            </div>
            <?php $pendingINC = count(array_filter($submitted['incident'], fn($r)=>!empty($r['feedback_id'])&&empty($r['employee_reply']))); ?>
            <?php if ($pendingINC > 0): ?>
            <div style="background:#FFF3CD;border:1px solid #FFE69C;border-radius:8px;padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:10px;">
                <i class="fas fa-bell" style="color:#F39C12;font-size:18px;"></i>
                <span style="color:#856404;font-weight:500;">You have <strong><?php echo $pendingINC; ?></strong> manager feedback(s) waiting for your reply.</span>
            </div>
            <?php endif; ?>
            <?php if (empty($submitted['incident'])): ?>
                <p style="text-align:center;color:#95A5A6;padding:30px 0;">No incident reports submitted yet.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Title</th><th>Type</th><th>Severity</th><th>Submitted</th><th>Status</th><th>Manager Feedback</th></tr></thead>
                    <tbody>
                    <?php foreach ($submitted['incident'] as $r): $d=$r['report_data'];
                        $sevC=['low'=>'#27AE60','medium'=>'#F39C12','high'=>'#E74C3C']; $sc=$sevC[$d['severity']??'medium']??'#F39C12'; ?>
                        <tr>
                            <td><?php echo htmlspecialchars($d['incident_title']??$r['title']); ?></td>
                            <td style="font-size:13px;"><?php echo ucwords(str_replace('_',' ',$d['incident_type']??'')); ?></td>
                            <td><span style="background:<?php echo $sc; ?>22;color:<?php echo $sc; ?>;border-radius:12px;padding:2px 10px;font-size:12px;font-weight:600;"><?php echo ucfirst($d['severity']??'medium'); ?></span></td>
                            <td style="font-size:13px;color:#7F8C8D;"><?php echo date('M d, Y',strtotime($r['created_at'])); ?></td>
                            <td><?php echo statusBadge($r['status']); ?></td>
                            <td><?php echo feedbackBtn($r); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- ── 4. DAILY WORK SUMMARY ── -->
        <div id="sec-daily-summary" class="section-card report-section">
            <div class="section-header rpt-section-header">
                <h2 class="section-title"><i class="fas fa-clipboard-list" style="color:#27AE60;"></i> Daily Work Summaries
                    <span style="background:#27AE6022;color:#27AE60;border-radius:12px;padding:2px 10px;font-size:12px;font-weight:600;margin-left:8px;"><?php echo count($submitted['daily_summary']); ?></span>
                </h2>
            </div>
            <?php $pendingDS = count(array_filter($submitted['daily_summary'], fn($r)=>!empty($r['feedback_id'])&&empty($r['employee_reply']))); ?>
            <?php if ($pendingDS > 0): ?>
            <div style="background:#FFF3CD;border:1px solid #FFE69C;border-radius:8px;padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:10px;">
                <i class="fas fa-bell" style="color:#F39C12;font-size:18px;"></i>
                <span style="color:#856404;font-weight:500;">You have <strong><?php echo $pendingDS; ?></strong> manager feedback(s) waiting for your reply.</span>
            </div>
            <?php endif; ?>
            <?php if (empty($submitted['daily_summary'])): ?>
                <p style="text-align:center;color:#95A5A6;padding:30px 0;">No daily summaries submitted yet.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Date</th><th>Summary</th><th>Submitted</th><th>Status</th><th>Manager Feedback</th></tr></thead>
                    <tbody>
                    <?php foreach ($submitted['daily_summary'] as $r): $d=$r['report_data']; ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($d['report_date']??''); ?></strong></td>
                            <td style="max-width:240px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:13px;color:#555;">
                                <?php echo htmlspecialchars(substr($d['summary']??'',0,70)).(strlen($d['summary']??'')>70?'…':''); ?>
                            </td>
                            <td style="font-size:13px;color:#7F8C8D;"><?php echo date('M d, Y',strtotime($r['created_at'])); ?></td>
                            <td><?php echo statusBadge($r['status']); ?></td>
                            <td><?php echo feedbackBtn($r); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- ── 5. LEAVE REQUESTS ── -->
        <div id="sec-leave-request" class="section-card report-section">
            <div class="section-header rpt-section-header">
                <h2 class="section-title"><i class="fas fa-calendar-times" style="color:#F39C12;"></i> Leave Requests
                    <span style="background:#F39C1222;color:#F39C12;border-radius:12px;padding:2px 10px;font-size:12px;font-weight:600;margin-left:8px;"><?php echo count($submitted['leave_request']); ?></span>
                </h2>
            </div>
            <?php $pendingLR = count(array_filter($submitted['leave_request'], fn($r)=>!empty($r['feedback_id'])&&empty($r['employee_reply']))); ?>
            <?php if ($pendingLR > 0): ?>
            <div style="background:#FFF3CD;border:1px solid #FFE69C;border-radius:8px;padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:10px;">
                <i class="fas fa-bell" style="color:#F39C12;font-size:18px;"></i>
                <span style="color:#856404;font-weight:500;">You have <strong><?php echo $pendingLR; ?></strong> manager feedback(s) waiting for your reply.</span>
            </div>
            <?php endif; ?>
            <?php if (empty($submitted['leave_request'])): ?>
                <p style="text-align:center;color:#95A5A6;padding:30px 0;">No leave requests submitted yet.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Leave Type</th><th>From</th><th>To</th><th>Days</th><th>Submitted</th><th>Status</th><th>Manager Feedback</th></tr></thead>
                    <tbody>
                    <?php foreach ($submitted['leave_request'] as $r): $d=$r['report_data'];
                        $ltC=['sick'=>'#E74C3C','personal'=>'#3498DB','family'=>'#E74C3C','vacation'=>'#27AE60','other'=>'#7F8C8D'];
                        $lc=$ltC[$d['leave_type']??'other']??'#7F8C8D'; ?>
                        <tr>
                            <td><span style="background:<?php echo $lc; ?>22;color:<?php echo $lc; ?>;border-radius:12px;padding:2px 10px;font-size:12px;font-weight:600;"><?php echo ucfirst($d['leave_type']??''); ?></span></td>
                            <td><?php echo htmlspecialchars($d['leave_from']??''); ?></td>
                            <td><?php echo htmlspecialchars($d['leave_to']??''); ?></td>
                            <td><strong><?php echo $d['days']??''; ?></strong></td>
                            <td style="font-size:13px;color:#7F8C8D;"><?php echo date('M d, Y',strtotime($r['created_at'])); ?></td>
                            <td><?php echo statusBadge($r['status']); ?></td>
                            <td><?php echo feedbackBtn($r); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- ── MANAGER SUBMITTED REPORTS ── -->
        <?php if (!empty($managerReports)): ?>
        <div id="sec-manager-reports" class="section-card report-section">
            <div class="section-header rpt-section-header">
                <h2 class="section-title">
                    <i class="fas fa-user-tie" style="color:#8E44AD;"></i> Manager Submitted Reports
                    <?php if ($managerPendingCount > 0): ?>
                    <span style="background:#E74C3C;color:#fff;border-radius:12px;padding:2px 10px;font-size:12px;font-weight:700;margin-left:8px;"><?php echo $managerPendingCount; ?> new</span>
                    <?php endif; ?>
                </h2>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Type</th><th>Manager</th><th>Title</th><th>Submitted</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php 
                    $mgrTypeLabels=[
                        'production_update'=>['label'=>'Production Update','icon'=>'fa-industry','color'=>'#3498DB'],
                        'inventory_summary'=>['label'=>'Inventory Summary','icon'=>'fa-boxes','color'=>'#9B59B6'],
                        'team_performance'=>['label'=>'Team Performance','icon'=>'fa-users','color'=>'#27AE60'],
                        'incident'=>['label'=>'Incident','icon'=>'fa-exclamation-triangle','color'=>'#E74C3C'],
                        'daily_summary'=>['label'=>'Daily Summary','icon'=>'fa-clipboard-list','color'=>'#F39C12'],
                        'leave_request'=>['label'=>'Leave Request','icon'=>'fa-calendar-times','color'=>'#E67E22'],
                        'other'=>['label'=>'General','icon'=>'fa-file-alt','color'=>'#7F8C8D']
                    ];
                    foreach ($managerReports as $rpt):
                        $rt=$rpt['report_type']; $meta=$mgrTypeLabels[$rt]??['label'=>ucfirst(str_replace('_',' ',$rt)),'icon'=>'fa-file','color'=>'#7F8C8D'];
                        $sc=['submitted'=>'#F39C12','reviewed'=>'#3498DB','acknowledged'=>'#27AE60'][$rpt['status']]??'#7F8C8D';
                    ?>
                    <tr data-type="<?php echo $rt; ?>">
                        <td><span style="display:inline-flex;align-items:center;gap:6px;background:<?php echo $meta['color']; ?>18;color:<?php echo $meta['color']; ?>;border-radius:20px;padding:4px 10px;font-size:12px;font-weight:600;"><i class="fas <?php echo $meta['icon']; ?>"></i> <?php echo $meta['label']; ?></span></td>
                        <td><strong><?php echo htmlspecialchars($rpt['manager_name']); ?></strong></td>
                        <td style="max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($rpt['title']); ?></td>
                        <td style="font-size:13px;color:#7F8C8D;"><?php echo date('M d, Y',strtotime($rpt['created_at'])); ?></td>
                        <td><span style="background:<?php echo $sc; ?>22;color:<?php echo $sc; ?>;border:1px solid <?php echo $sc; ?>55;border-radius:12px;padding:3px 10px;font-size:12px;font-weight:600;"><?php echo ucfirst($rpt['status']); ?></span></td>
                        <td>
                            <button onclick='openReportDetail(<?php echo htmlspecialchars(json_encode(['id'=>$rpt['id'],'type'=>$rt,'type_label'=>$meta['label'],'type_color'=>$meta['color'],'type_icon'=>$meta['icon'],'employee_name'=>$rpt['manager_name'],'title'=>$rpt['title'],'status'=>$rpt['status'],'created_at'=>$rpt['created_at'],'data'=>json_decode($rpt['report_data'],true),'manager_note'=>''],JSON_HEX_APOS|JSON_HEX_QUOT),ENT_QUOTES); ?>)'
                                style="background:#EBF5FB;color:#3498DB;border:none;border-radius:6px;padding:7px 12px;cursor:pointer;font-size:13px;font-weight:600;">
                                <i class="fas fa-eye"></i> View
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- end main-content -->

    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>

    <!-- Report Detail Panel -->
    <div id="rdOverlay" onclick="closeReportDetail()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:9998;"></div>
    <div id="rdPanel" style="display:none;position:fixed;top:0;right:0;width:480px;max-width:100%;height:100%;background:#fff;z-index:9999;box-shadow:-4px 0 28px rgba(0,0,0,0.16);flex-direction:column;overflow:hidden;">
        <div id="rdHeader" style="padding:22px 24px 18px;color:#fff;flex-shrink:0;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <div style="font-size:17px;font-weight:700;" id="rdTitle"></div>
                    <div style="font-size:12px;opacity:.8;margin-top:3px;" id="rdSub"></div>
                </div>
                <button onclick="closeReportDetail()" style="background:rgba(255,255,255,0.18);border:none;color:#fff;width:34px;height:34px;border-radius:50%;cursor:pointer;font-size:15px;">✕</button>
            </div>
            <div style="margin-top:12px;" id="rdStatusBadge"></div>
        </div>
        <div style="flex:1;overflow-y:auto;overflow-x:hidden;padding:20px 24px 28px;">
            <div id="rdBody"></div>
            <hr style="border:none;border-top:1.5px solid #F0F0F0;margin:20px 0;">
            <div style="font-size:13px;font-weight:700;color:#2C3E50;margin-bottom:8px;"><i class="fas fa-comment-dots"></i> Manager Note (optional)</div>
            <textarea id="rdNoteText" rows="3" placeholder="Add a note or response for the manager..."
                style="width:100%;padding:11px 13px;border:2px solid #E0E0E0;border-radius:8px;font-family:inherit;font-size:13px;resize:vertical;outline:none;box-sizing:border-box;"
                onfocus="this.style.borderColor='#3498DB'" onblur="this.style.borderColor='#E0E0E0'"></textarea>
            <div id="rdNoteError" style="display:none;background:#f8d7da;color:#721c24;padding:9px 13px;border-radius:7px;margin-top:10px;font-size:13px;"></div>
            <div id="rdNoteSuccess" style="display:none;background:#d4edda;color:#155724;padding:9px 13px;border-radius:7px;margin-top:10px;font-size:13px;"></div>
            <button id="rdAckBtn" onclick="acknowledgeManagerReport()"
                style="margin-top:14px;width:100%;padding:13px;background:#27AE60;color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;font-family:inherit;">
                <i class="fas fa-check-circle"></i> Acknowledge
            </button>
            <button id="rdFeedbackBtn" onclick="openFeedbackForManagerReport()"
                style="margin-top:10px;width:100%;padding:13px;background:#7B3F2A;color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;font-family:inherit;">
                <i class="fas fa-comment-alt"></i> Send Feedback
            </button>
        </div>
    </div>

    <!-- Feedback/Report Slide-in Panel -->
    <div id="fbOverlay" onclick="closeFeedbackPanel()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:9998;"></div>
    <div id="fbPanel" style="display:none;position:fixed;top:0;right:0;width:460px;max-width:100%;height:100%;background:#fff;z-index:9999;box-shadow:-4px 0 28px rgba(0,0,0,0.16);flex-direction:column;overflow:hidden;">
        <div id="fbPanelHeader" style="padding:24px 24px 20px;color:#fff;flex-shrink:0;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <div style="font-size:18px;font-weight:700;" id="fbPanelTitle"><i class="fas fa-comment-alt"></i> Manager Feedback</div>
                    <div style="font-size:12px;opacity:.8;margin-top:4px;" id="fbPanelSub"></div>
                </div>
                <button onclick="closeFeedbackPanel()" style="background:rgba(255,255,255,0.18);border:none;color:#fff;width:36px;height:36px;border-radius:50%;cursor:pointer;font-size:16px;line-height:1;">✕</button>
            </div>
        </div>
        <div style="flex:1;overflow-y:auto;overflow-x:hidden;padding:0 24px 28px;" id="fbPanelContent">
            <div id="fbReportSummary" style="background:#f8f4ee;border-radius:10px;padding:14px 16px;margin-bottom:20px;font-size:13px;color:#555;line-height:1.9;border-left:4px solid #C0A882;"></div>
            <div id="fbBubble" style="border-radius:10px;padding:18px 20px;margin-bottom:20px;border-left:5px solid #ccc;">
                <div id="fbTypeLabel" style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;margin-bottom:10px;"></div>
                <div id="fbText" style="font-size:15px;color:#2C3E50;line-height:1.8;white-space:pre-wrap;"></div>
                <div id="fbDate" style="margin-top:10px;font-size:12px;color:#95A5A6;"></div>
            </div>
            <div id="fbRepliedBox" style="display:none;background:#F8F9FA;border:1.5px solid #DEE2E6;border-left:5px solid #7F8C8D;border-radius:10px;padding:16px 18px;margin-bottom:20px;">
                <div style="font-size:11px;font-weight:700;color:#7F8C8D;text-transform:uppercase;letter-spacing:.6px;margin-bottom:8px;"><i class="fas fa-reply"></i> Your Reply</div>
                <div id="fbRepliedText" style="font-size:14px;color:#2C3E50;line-height:1.7;white-space:pre-wrap;"></div>
                <div id="fbRepliedDate" style="margin-top:8px;font-size:12px;color:#95A5A6;"></div>
            </div>
            <div id="fbReplyForm">
                <div style="font-size:14px;font-weight:600;color:#2C3E50;margin-bottom:10px;"><i id="fbReplyIcon" class="fas fa-reply"></i> Write Your Reply</div>
                <textarea id="fbReplyText" rows="5" placeholder="Write your response to the manager's feedback here..."
                    style="width:100%;padding:13px 15px;border:2px solid #E0E0E0;border-radius:8px;font-family:inherit;font-size:14px;line-height:1.6;resize:vertical;outline:none;transition:border-color .2s;color:#2C3E50;box-sizing:border-box;"
                    onfocus="this.style.borderColor=currentColor" onblur="this.style.borderColor='#E0E0E0'"></textarea>
                <div id="fbReplyError" style="display:none;background:#f8d7da;color:#721c24;padding:9px 13px;border-radius:7px;margin-top:10px;font-size:13px;"></div>
                <div id="fbReplySuccess" style="display:none;background:#d4edda;color:#155724;padding:9px 13px;border-radius:7px;margin-top:10px;font-size:13px;"></div>
                <button id="fbSendBtn" onclick="submitFeedbackReply()"
                    style="margin-top:14px;width:100%;padding:13px;color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;font-family:inherit;transition:opacity .2s;">
                    <i class="fas fa-paper-plane"></i> Send Reply
                </button>
            </div>
        </div>
    </div>

    <script>
    let currentFeedbackId = null, currentColor = '#3498DB';
    const fbColors={praise:'#27AE60',warning:'#E74C3C',note:'#3498DB'};
    const fbBg={praise:'#EAFAF1',warning:'#FDEDEC',note:'#EBF5FB'};
    const fbIcons={praise:'fa-thumbs-up',warning:'fa-exclamation-triangle',note:'fa-comment-alt'};
    const fbLabels={praise:'Praise',warning:'Warning',note:'Note'};
    const reportTypeLabels={task_progress:'Task Progress Report',material_usage:'Material Usage Report',incident:'Incident Report',daily_summary:'Daily Work Summary',leave_request:'Leave Request'};

    function openFeedbackPanel(data) {
        currentFeedbackId = data.feedback_id;
        const t = data.feedback_type || 'note';
        currentColor = fbColors[t] || '#3498DB';
        document.getElementById('fbPanelHeader').style.background = 'linear-gradient(135deg,#3D1F14,'+currentColor+')';
        document.getElementById('fbPanelTitle').innerHTML = '<i class="fas '+(fbIcons[t]||'fa-comment-alt')+'"></i> Manager Feedback';
        document.getElementById('fbPanelSub').textContent = reportTypeLabels[data.report_type] || 'Report';
        // Summary box
        let summary = '<strong style="color:#3D1F14;">Report:</strong> '+esc(data.report_title||'')+'<br>';
        if (data.report_type==='material_usage') {
            summary = '<strong style="color:#3D1F14;">Material:</strong> '+esc(data.report_title||'')+'<br>';
        }
        document.getElementById('fbReportSummary').innerHTML = summary;
        const bubble = document.getElementById('fbBubble');
        bubble.style.background = fbBg[t]||'#EBF5FB'; bubble.style.borderColor = currentColor;
        document.getElementById('fbTypeLabel').style.color = currentColor;
        document.getElementById('fbTypeLabel').innerHTML = '<i class="fas '+(fbIcons[t]||'fa-comment-alt')+'"></i> '+(fbLabels[t]||'Note')+' — '+esc(data.manager_name);
        document.getElementById('fbText').textContent = data.feedback;
        document.getElementById('fbDate').innerHTML = '<i class="fas fa-clock"></i> '+formatDate(data.created_at);
        document.getElementById('fbReplyIcon').style.color = currentColor;
        document.getElementById('fbSendBtn').style.background = currentColor;
        document.getElementById('fbReplyText').value = '';
        document.getElementById('fbReplyError').style.display = 'none';
        document.getElementById('fbReplySuccess').style.display = 'none';
        if (data.employee_reply) {
            document.getElementById('fbRepliedBox').style.display = 'block';
            document.getElementById('fbRepliedText').textContent = data.employee_reply;
            document.getElementById('fbRepliedDate').innerHTML = '<i class="fas fa-clock"></i> Sent '+formatDate(data.replied_at);
            document.getElementById('fbReplyForm').style.display = 'none';
        } else {
            document.getElementById('fbRepliedBox').style.display = 'none';
            document.getElementById('fbReplyForm').style.display = 'block';
        }
        document.getElementById('fbOverlay').style.display = 'block';
        document.getElementById('fbPanel').style.display = 'flex';
        setTimeout(()=>{ const el=document.getElementById('fbReplyText'); if(el) el.focus(); },300);
    }
    function closeFeedbackPanel() {
        document.getElementById('fbOverlay').style.display = 'none';
        document.getElementById('fbPanel').style.display = 'none';
        currentFeedbackId = null;
    }
    function submitFeedbackReply() {
        const reply=document.getElementById('fbReplyText').value.trim();
        const errEl=document.getElementById('fbReplyError'), okEl=document.getElementById('fbReplySuccess'), btn=document.getElementById('fbSendBtn');
        errEl.style.display='none'; okEl.style.display='none';
        if (!reply) { errEl.textContent='Please write your reply before sending.'; errEl.style.display='block'; return; }
        btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Sending...';
        fetch('<?php echo BASE_URL; ?>/public/api/submit_feedback_reply.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({feedback_id:currentFeedbackId,reply:reply})})
        .then(r=>r.json()).then(data=>{
            btn.disabled=false; btn.innerHTML='<i class="fas fa-paper-plane"></i> Send Reply';
            if (data.success) {
                okEl.innerHTML='<i class="fas fa-check-circle"></i> Reply sent!'; okEl.style.display='block';
                document.getElementById('fbRepliedText').textContent=reply;
                document.getElementById('fbRepliedDate').innerHTML='<i class="fas fa-clock"></i> Sent just now';
                document.getElementById('fbRepliedBox').style.display='block';
                document.getElementById('fbReplyForm').style.display='none';
                document.querySelectorAll('button[data-fb-id="'+currentFeedbackId+'"] span').forEach(s=>s.textContent='✓ Replied');
                setTimeout(closeFeedbackPanel,1800);
            } else { errEl.textContent=data.message||'Failed.'; errEl.style.display='block'; }
        }).catch(()=>{ btn.disabled=false; btn.innerHTML='<i class="fas fa-paper-plane"></i> Send Reply'; errEl.textContent='Network error.'; errEl.style.display='block'; });
    }
    function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
    function formatDate(s){ if(!s)return''; const d=new Date(s.replace(' ','T')); return d.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'})+' at '+d.toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'}); }
    const allSections = ['sec-task-progress','sec-material-usage','sec-incident','sec-daily-summary','sec-leave-request','sec-manager-reports'];
    function showSection(id) {
        allSections.forEach(s => { const el=document.getElementById(s); if(el) el.classList.remove('visible'); });
        closeAllMenus();
        const el=document.getElementById(id);
        if(el){ el.classList.add('visible'); setTimeout(()=>el.scrollIntoView({behavior:'smooth',block:'start'}),50); }
    }
    function showManagerType(type) {
        closeAllMenus();
        allSections.forEach(s => { const el=document.getElementById(s); if(el) el.classList.remove('visible'); });
        const sec=document.getElementById('sec-manager-reports');
        if(sec){ sec.classList.add('visible'); setTimeout(()=>sec.scrollIntoView({behavior:'smooth',block:'start'}),50); }
        document.querySelectorAll('#sec-manager-reports tbody tr').forEach(row => {
            row.style.display = (row.dataset.type === type) ? '' : 'none';
        });
    }
    function toggleReportMenu(){ const m=document.getElementById('reportTypeMenu'); m.style.display=m.style.display==='none'?'block':'none'; }
    function toggleMenu(menuId) {
        closeAllMenus();
        const m = document.getElementById(menuId);
        if (m) m.style.display = 'block';
    }
    function closeAllMenus() {
        ['reportTypeMenu','mgrNavMenu','myNavMenu'].forEach(id => {
            const el = document.getElementById(id); if(el) el.style.display='none';
        });
    }
    document.addEventListener('click', e => {
        const wrappers = ['writeReportWrapper','mgrNavWrapper','myNavWrapper'];
        const clickedInside = wrappers.some(id => { const w=document.getElementById(id); return w&&w.contains(e.target); });
        if (!clickedInside) closeAllMenus();
    });
    document.addEventListener('keydown',e=>{ if(e.key==='Escape'){closeFeedbackPanel();closeReportDetail();closeMgrFeedbackPanel();closeAllMenus();} });

    // Report Detail Functions (for manager reports sent to employee)
    function openReportDetail(rpt) {
        currentManagerReportId = rpt.id;
        currentManagerName = rpt.employee_name;
        currentManagerReportTitle = rpt.title;
        // Stamp ID directly on the feedback button so it survives any variable reset
        document.getElementById('rdFeedbackBtn').dataset.reportId = rpt.id;
        document.getElementById('rdFeedbackBtn').dataset.reportName = rpt.employee_name;
        document.getElementById('rdFeedbackBtn').dataset.reportTitle = rpt.title;
        const color = rpt.type_color;
        document.getElementById('rdHeader').style.background = 'linear-gradient(135deg,#2C3E50,'+color+')';
        document.getElementById('rdTitle').innerHTML = '<i class="fas '+rpt.type_icon+'"></i> '+rpt.type_label;
        document.getElementById('rdSub').textContent = rpt.employee_name+' — '+formatDate(rpt.created_at);
        
        let html=''; const d=rpt.data||{};
        
        // Field label map for clean display names
        const labelMap={
            order_ref:'Order / Project',progress:'Progress',update:'Update',blockers:'Blockers',
            est_completion:'Est. Completion',resources_needed:'Resources Needed',
            emp_task_ref:'Task Reference',emp_instructions:'Instructions',emp_priority:'Priority',
            emp_deadline:'Deadline',emp_notes:'Notes',
            summary:'Summary',low_stock:'Low Stock Items',action_needed:'Action Needed',restock_cost:'Restock Cost',
            emp_check_instructions:'Check Instructions',emp_materials_list:'Materials to Check',
            emp_due_date:'Due Date',emp_special_notes:'Special Notes',
            period:'Period',highlights:'Highlights',concerns:'Concerns',recommendations:'Recommendations',
            team_rating:'Team Rating',emp_performance_feedback:'Performance Feedback',
            emp_period:'Period',emp_rating:'Rating',emp_improvement:'Areas for Improvement',
            emp_action_required:'Action Required',
            incident_title:'Incident',incident_datetime:'Date & Time',incident_type:'Type',
            severity:'Severity',description:'Description',action_taken:'Action Taken',
            injuries:'Injuries',damage_estimate:'Damage Estimate',
            emp_incident_title:'Incident',emp_incident_datetime:'Date & Time',
            emp_incident_description:'Description',emp_incident_role:'Employee Role',
            emp_incident_action:'Action Required',emp_incident_deadline:'Deadline',
            report_date:'Date',challenges:'Challenges',tomorrow_plan:'Plan for Tomorrow',
            admin_attention:'Admin Attention',emp_report_date:'Date',
            emp_task_summary:'Task Summary',
            leave_type:'Leave Type',leave_from:'From',leave_to:'To',days:'Duration',
            reason:'Reason',coverage:'Coverage',
            title:'Title',details:'Details',emp_title:'Title',emp_details:'Details'
        };
        const sevC={low:'#27AE60',medium:'#F39C12',high:'#E74C3C'};
        const ltC={sick:'#E74C3C',personal:'#3498DB',family:'#E74C3C',vacation:'#27AE60',other:'#7F8C8D'};
        
        for(const[k,v] of Object.entries(d)){
            if(v===null||v===undefined||v==='')continue;
            const label=labelMap[k]||(k.replace(/^emp_/,'').replace(/_/g,' ').replace(/\b\w/g,l=>l.toUpperCase()));
            if(k==='progress'){
                html+=field(label,'<div style="display:flex;align-items:center;gap:10px;"><div style="flex:1;background:#ECF0F1;border-radius:4px;height:10px;"><div style="width:'+v+'%;background:'+color+';height:10px;border-radius:4px;"></div></div><strong>'+v+'%</strong></div>',true);
            } else if(k==='severity'){
                html+=field(label,'<span style="background:'+sevC[v]+'22;color:'+sevC[v]+';border-radius:12px;padding:3px 12px;font-weight:700;">'+esc(v)+'</span>',true);
            } else if(k==='leave_type'){
                html+=field(label,'<span style="background:'+(ltC[v]||'#7F8C8D')+'22;color:'+(ltC[v]||'#7F8C8D')+';border-radius:12px;padding:3px 12px;font-weight:700;">'+esc(v)+'</span>',true);
            } else if(k==='days'){
                html+=field(label,v+' day'+(v>1?'s':''));
            } else if(k==='blockers'){
                html+=field(label,'<span style="color:#E74C3C;">'+esc(String(v))+'</span>',true);
            } else {
                html+=field(label,String(v));
            }
        }
        
        document.getElementById('rdBody').innerHTML = html;
        document.getElementById('rdNoteText').value = rpt.manager_note||'';
        document.getElementById('rdNoteError').style.display='none';
        document.getElementById('rdNoteSuccess').style.display='none';
        const stC={submitted:'#F39C12',reviewed:'#3498DB',acknowledged:'#27AE60'};
        const sc=stC[rpt.status]||'#7F8C8D';
        document.getElementById('rdStatusBadge').innerHTML='<span style="background:'+sc+'22;color:'+sc+';border:1px solid '+sc+'55;border-radius:12px;padding:4px 12px;font-size:13px;font-weight:600;">'+rpt.status.toUpperCase()+'</span>';
        document.getElementById('rdOverlay').style.display='block';
        document.getElementById('rdPanel').style.display='flex';
    }
    
    function closeReportDetail() {
        document.getElementById('rdOverlay').style.display='none';
        document.getElementById('rdPanel').style.display='none';
        currentManagerReportId = null;
    }
    
    let currentManagerReportId = null, currentManagerName = '', currentManagerReportTitle = '';
    
    function acknowledgeManagerReport() {
        const note=document.getElementById('rdNoteText').value.trim();
        const errEl=document.getElementById('rdNoteError'),okEl=document.getElementById('rdNoteSuccess'),btn=document.getElementById('rdAckBtn');
        errEl.style.display='none'; okEl.style.display='none';
        if(!currentManagerReportId){errEl.textContent='No report selected.';errEl.style.display='block';return;}
        btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Saving...';
        fetch('<?php echo BASE_URL; ?>/public/api/acknowledge_manager_report.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({report_id:currentManagerReportId,note:note})})
        .then(r=>r.json()).then(res=>{
            btn.disabled=false; btn.innerHTML='<i class="fas fa-check-circle"></i> Acknowledge';
            if(res.success){okEl.innerHTML='<i class="fas fa-check-circle"></i> Acknowledged.';okEl.style.display='block';setTimeout(()=>location.reload(),1500);}
            else{errEl.textContent=res.message||'Failed.';errEl.style.display='block';}
        }).catch(()=>{btn.disabled=false; btn.innerHTML='<i class="fas fa-check-circle"></i> Acknowledge';errEl.textContent='Network error.';errEl.style.display='block';});
    }
    
    function openFeedbackForManagerReport() {
        const btn = document.getElementById('rdFeedbackBtn');
        const reportId = parseInt(btn.dataset.reportId || currentManagerReportId);
        const reportName = btn.dataset.reportName || currentManagerName;
        const reportTitle = btn.dataset.reportTitle || currentManagerReportTitle;
        if(!reportId){alert('No report selected.');return;}
        currentManagerReportId = reportId;
        // Also store on the submit button so submitMgrFeedback always has it
        document.getElementById('mgrFbSubmitBtn').dataset.reportId = reportId;
        document.getElementById('rdOverlay').style.display='none';
        document.getElementById('rdPanel').style.display='none';
        document.getElementById('mgrFbPanelSub').textContent = reportTitle;
        document.getElementById('mgrFbReportSummary').innerHTML =
            '<strong style="color:#3D1F14;">From:</strong> '+esc(reportName)+'<br>'
            +'<strong style="color:#3D1F14;">Report:</strong> '+esc(reportTitle);
        document.getElementById('mgrFbText').value = '';
        document.getElementById('mgrFbError').style.display = 'none';
        document.getElementById('mgrFbSuccess').style.display = 'none';
        document.querySelector('input[name="mgr_fb_type"][value="note"]').checked = true;
        updateMgrFbType();
        document.getElementById('mgrFbOverlay').style.display = 'block';
        document.getElementById('mgrFbPanel').style.display = 'flex';
    }
    function closeMgrFeedbackPanel() {
        document.getElementById('mgrFbOverlay').style.display = 'none';
        document.getElementById('mgrFbPanel').style.display = 'none';
    }
    function updateMgrFbType() {
        const typeColors={praise:'#27AE60',note:'#3498DB',warning:'#E74C3C'};
        const selected=document.querySelector('input[name="mgr_fb_type"]:checked').value;
        document.querySelectorAll('.mgr-fb-type-btn').forEach(btn=>{
            const t=btn.dataset.type;
            btn.style.border=t===selected?'2px solid '+typeColors[t]:'2px solid #e0e0e0';
            btn.style.background=t===selected?typeColors[t]+'18':'';
        });
    }
    function submitMgrFeedback() {
        const feedback=document.getElementById('mgrFbText').value.trim();
        const fbType=document.querySelector('input[name="mgr_fb_type"]:checked').value;
        const errEl=document.getElementById('mgrFbError'),okEl=document.getElementById('mgrFbSuccess'),btn=document.getElementById('mgrFbSubmitBtn');
        const reportId = parseInt(btn.dataset.reportId || currentManagerReportId || 0);
        errEl.style.display='none'; okEl.style.display='none';
        if(!feedback){errEl.textContent='Please write your feedback.';errEl.style.display='block';return;}
        if(!reportId){errEl.textContent='Error: no report selected. Please close and try again.';errEl.style.display='block';return;}
        btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Sending...';
        fetch('<?php echo BASE_URL; ?>/public/api/submit_manager_report_feedback.php',{
            method:'POST',headers:{'Content-Type':'application/json'},
            body:JSON.stringify({report_id:reportId,feedback:feedback,feedback_type:fbType})
        })
        .then(r=>r.json()).then(data=>{
            btn.disabled=false; btn.innerHTML='<i class="fas fa-paper-plane"></i> Send Feedback';
            if(data.success){okEl.innerHTML='<i class="fas fa-check-circle"></i> Feedback sent!';okEl.style.display='block';setTimeout(()=>location.reload(),1500);}
            else{errEl.textContent=(data.message||'Failed.')+(data.debug?' ['+JSON.stringify(data.debug)+']':'');errEl.style.display='block';}
        }).catch(()=>{btn.disabled=false;btn.innerHTML='<i class="fas fa-paper-plane"></i> Send Feedback';errEl.textContent='Network error.';errEl.style.display='block';});
    }
    
    function field(label,value,raw){return'<div style="margin-bottom:14px;"><div style="font-size:11px;font-weight:700;color:#95A5A6;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px;">'+label+'</div><div style="font-size:14px;color:#2C3E50;line-height:1.7;">'+(raw?value:esc(value))+'</div></div>';}
    </script>

    <!-- Manager Report Feedback Panel -->
    <div id="mgrFbOverlay" onclick="closeMgrFeedbackPanel()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:10000;"></div>
    <div id="mgrFbPanel" style="display:none;position:fixed;top:0;right:0;width:420px;max-width:100%;height:100%;background:#fff;z-index:10001;box-shadow:-4px 0 24px rgba(0,0,0,0.18);flex-direction:column;overflow-y:auto;">
        <div style="background:linear-gradient(135deg,#3D1F14,#7B3F2A);padding:24px 24px 20px;color:#fff;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <div style="font-size:18px;font-weight:700;"><i class="fas fa-comment-alt"></i> Send Feedback</div>
                    <div id="mgrFbPanelSub" style="font-size:12px;opacity:.8;margin-top:4px;"></div>
                </div>
                <button onclick="closeMgrFeedbackPanel()" style="background:rgba(255,255,255,0.15);border:none;color:#fff;width:34px;height:34px;border-radius:50%;cursor:pointer;font-size:16px;">✕</button>
            </div>
        </div>
        <div id="mgrFbReportSummary" style="margin:20px 20px 0;background:#f8f4ee;border-radius:10px;padding:16px;border-left:4px solid #7B3F2A;font-size:13px;color:#555;line-height:1.8;"></div>
        <div style="padding:20px;">
            <div style="margin-bottom:16px;">
                <label style="display:block;font-weight:600;margin-bottom:8px;color:#3D1F14;">Feedback Type</label>
                <div style="display:flex;gap:10px;">
                    <label style="flex:1;cursor:pointer;">
                        <input type="radio" name="mgr_fb_type" value="praise" style="display:none;" onchange="updateMgrFbType()">
                        <div class="mgr-fb-type-btn" data-type="praise" style="text-align:center;padding:10px 6px;border-radius:8px;border:2px solid #e0e0e0;">
                            <i class="fas fa-thumbs-up" style="font-size:20px;color:#27AE60;display:block;margin-bottom:4px;"></i>
                            <span style="font-size:12px;font-weight:600;color:#27AE60;">Praise</span>
                        </div>
                    </label>
                    <label style="flex:1;cursor:pointer;">
                        <input type="radio" name="mgr_fb_type" value="note" checked style="display:none;" onchange="updateMgrFbType()">
                        <div class="mgr-fb-type-btn" data-type="note" style="text-align:center;padding:10px 6px;border-radius:8px;border:2px solid #3498DB;background:#EBF5FB;">
                            <i class="fas fa-comment" style="font-size:20px;color:#3498DB;display:block;margin-bottom:4px;"></i>
                            <span style="font-size:12px;font-weight:600;color:#3498DB;">Note</span>
                        </div>
                    </label>
                    <label style="flex:1;cursor:pointer;">
                        <input type="radio" name="mgr_fb_type" value="warning" style="display:none;" onchange="updateMgrFbType()">
                        <div class="mgr-fb-type-btn" data-type="warning" style="text-align:center;padding:10px 6px;border-radius:8px;border:2px solid #e0e0e0;">
                            <i class="fas fa-exclamation-triangle" style="font-size:20px;color:#E74C3C;display:block;margin-bottom:4px;"></i>
                            <span style="font-size:12px;font-weight:600;color:#E74C3C;">Warning</span>
                        </div>
                    </label>
                </div>
            </div>
            <div style="margin-bottom:20px;">
                <label style="display:block;font-weight:600;margin-bottom:8px;color:#3D1F14;">Your Feedback <span style="color:#E74C3C;">*</span></label>
                <textarea id="mgrFbText" rows="5" placeholder="Write your feedback to the manager..."
                    style="width:100%;padding:12px;border:2px solid #e0e0e0;border-radius:8px;font-family:inherit;font-size:14px;resize:vertical;outline:none;box-sizing:border-box;"
                    onfocus="this.style.borderColor='#7B3F2A'" onblur="this.style.borderColor='#e0e0e0'"></textarea>
            </div>
            <div id="mgrFbError" style="display:none;background:#f8d7da;color:#721c24;padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:13px;"></div>
            <div id="mgrFbSuccess" style="display:none;background:#d4edda;color:#155724;padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:13px;"></div>
            <button onclick="submitMgrFeedback()" id="mgrFbSubmitBtn"
                style="width:100%;padding:13px;background:linear-gradient(135deg,#3D1F14,#7B3F2A);color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;">
                <i class="fas fa-paper-plane"></i> Send Feedback
            </button>
        </div>
    </div>
</body>
</html>
