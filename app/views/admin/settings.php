<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . BASE_URL . '/public/login');
    exit();
}

require_once __DIR__ . '/../../../config/db_config.php';
require_once __DIR__ . '/../../../core/SecurityUtil.php';
$adminName = $_SESSION['user_name'] ?? 'Admin User';
$adminId = $_SESSION['user_id'];

$message = '';
$messageType = '';

// Handle all POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!SecurityUtil::validateCsrfToken($csrfToken)) {
        $message = 'Invalid CSRF token.';
        $messageType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            switch ($action) {
            case 'update_general':
                $settings = [
                    'site_name' => $_POST['site_name'] ?? 'FurnitureCraft Workshop',
                    'currency' => $_POST['currency'] ?? 'ETB',
                    'timezone' => $_POST['timezone'] ?? 'Africa/Addis_Ababa',
                    'date_format' => $_POST['date_format'] ?? 'Y-m-d',
                    'language' => $_POST['language'] ?? 'en'
                ];
                foreach ($settings as $key => $value) {
                    $stmt = $pdo->prepare("INSERT INTO furn_settings (setting_key, setting_value, setting_type, category) VALUES (?, ?, 'text', 'general') ON DUPLICATE KEY UPDATE setting_value = ?");
                    $stmt->execute([$key, $value, $value]);
                }
                $message = '✅ General settings updated successfully!';
                $messageType = 'success';
                break;
                
            case 'update_company':
                $companyData = [
                    'company_name' => $_POST['company_name'],
                    'address_line1' => $_POST['address_line1'],
                    'city' => $_POST['city'],
                    'country' => $_POST['country'],
                    'phone_primary' => $_POST['phone_primary'],
                    'email_primary' => $_POST['email_primary'],
                    'website' => $_POST['website']
                ];
                $stmt = $pdo->prepare("
                    INSERT INTO furn_company_info (id, company_name, address_line1, city, country, phone_primary, email_primary, website)
                    VALUES (1, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    company_name = VALUES(company_name),
                    address_line1 = VALUES(address_line1),
                    city = VALUES(city),
                    country = VALUES(country),
                    phone_primary = VALUES(phone_primary),
                    email_primary = VALUES(email_primary),
                    website = VALUES(website)
                ");
                $stmt->execute(array_values($companyData));
                $message = '✅ Company information updated successfully!';
                $messageType = 'success';
                break;
                
            case 'update_notifications':
                $notifSettings = [
                    'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0,
                    'sms_notifications' => isset($_POST['sms_notifications']) ? 1 : 0,
                    'order_confirmation_email' => isset($_POST['order_confirmation_email']) ? 1 : 0,
                    'order_status_updates' => isset($_POST['order_status_updates']) ? 1 : 0,
                    'payment_received_email' => isset($_POST['payment_received_email']) ? 1 : 0,
                    'low_stock_alert' => isset($_POST['low_stock_alert']) ? 1 : 0,
                    'new_order_alert' => isset($_POST['new_order_alert']) ? 1 : 0
                ];
                foreach ($notifSettings as $key => $value) {
                    $stmt = $pdo->prepare("INSERT INTO furn_settings (setting_key, setting_value, setting_type, category) VALUES (?, ?, 'boolean', 'notifications') ON DUPLICATE KEY UPDATE setting_value = ?");
                    $stmt->execute([$key, $value, $value]);
                }
                $message = '✅ Notification settings updated successfully!';
                $messageType = 'success';
                break;
                
            case 'update_system':
                $systemSettings = [
                    'maintenance_mode' => isset($_POST['maintenance_mode']) ? 1 : 0,
                    'cache_enabled' => isset($_POST['cache_enabled']) ? 1 : 0,
                    'debug_mode' => isset($_POST['debug_mode']) ? 1 : 0,
                    'log_retention_days' => $_POST['log_retention_days'] ?? 30
                ];
                foreach ($systemSettings as $key => $value) {
                    $type = $key === 'log_retention_days' ? 'number' : 'boolean';
                    $stmt = $pdo->prepare("INSERT INTO furn_settings (setting_key, setting_value, setting_type, category) VALUES (?, ?, ?, 'system') ON DUPLICATE KEY UPDATE setting_value = ?");
                    $stmt->execute([$key, $value, $type, $value]);
                }
                $message = '✅ System settings updated successfully!';
                $messageType = 'success';
                break;
                
            case 'update_business':
                $businessSettings = [
                    'fiscal_year_start'          => $_POST['fiscal_year_start'] ?? '01-01',
                    'default_deposit_percentage' => $_POST['default_deposit_percentage'] ?? 50,
                    'low_stock_threshold'        => $_POST['low_stock_threshold'] ?? 20,
                    'overhead_rate'              => $_POST['overhead_rate'] ?? 10,
                    'vat_rate'                   => $_POST['vat_rate'] ?? 15,
                    'income_tax_rate'            => $_POST['income_tax_rate'] ?? 0,
                    'allow_backorders'           => isset($_POST['allow_backorders']) ? 1 : 0,
                    'auto_approve_orders'        => isset($_POST['auto_approve_orders']) ? 1 : 0
                ];
                foreach ($businessSettings as $key => $value) {
                    $type = in_array($key, ['fiscal_year_start']) ? 'text' : 'number';
                    $stmt = $pdo->prepare("INSERT INTO furn_settings (setting_key, setting_value, setting_type, category) VALUES (?, ?, ?, 'business') ON DUPLICATE KEY UPDATE setting_value = ?");
                    $stmt->execute([$key, $value, $type, $value]);
                }
                $message = '✅ Business settings updated successfully!';
                $messageType = 'success';
                break;
                
            case 'update_security':
                $securitySettings = [
                    'session_timeout' => $_POST['session_timeout'] ?? 3600,
                    'password_min_length' => $_POST['password_min_length'] ?? 8,
                    'require_special_char' => isset($_POST['require_special_char']) ? 1 : 0,
                    'max_login_attempts' => $_POST['max_login_attempts'] ?? 5,
                    'lockout_duration' => $_POST['lockout_duration'] ?? 900
                ];
                foreach ($securitySettings as $key => $value) {
                    $type = 'number';
                    $stmt = $pdo->prepare("INSERT INTO furn_settings (setting_key, setting_value, setting_type, category) VALUES (?, ?, 'number', 'security') ON DUPLICATE KEY UPDATE setting_value = ?");
                    $stmt->execute([$key, $value, $value]);
                }
                $message = '✅ Security settings updated successfully!';
                $messageType = 'success';
                break;
                
            case 'update_email_config':
                $emailConfig = [
                    'smtp_host' => $_POST['smtp_host'],
                    'smtp_port' => $_POST['smtp_port'] ?? 587,
                    'smtp_username' => $_POST['smtp_username'],
                    'smtp_password' => !empty($_POST['smtp_password']) ? $_POST['smtp_password'] : null,
                    'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls',
                    'from_email' => $_POST['from_email'],
                    'from_name' => $_POST['from_name'] ?? 'FurnitureCraft Workshop'
                ];
                if (!empty($emailConfig['smtp_password'])) {
                    $stmt = $pdo->prepare("
                        INSERT INTO furn_email_config (id, smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption, from_email, from_name, is_active)
                        VALUES (1, ?, ?, ?, ?, ?, ?, ?, 1)
                        ON DUPLICATE KEY UPDATE 
                        smtp_host = VALUES(smtp_host),
                        smtp_port = VALUES(smtp_port),
                        smtp_username = VALUES(smtp_username),
                        smtp_encryption = VALUES(smtp_encryption),
                        from_email = VALUES(from_email),
                        from_name = VALUES(from_name)
                    ");
                    $stmt->execute([
                        $emailConfig['smtp_host'],
                        $emailConfig['smtp_port'],
                        $emailConfig['smtp_username'],
                        $emailConfig['smtp_password'],
                        $emailConfig['smtp_encryption'],
                        $emailConfig['from_email'],
                        $emailConfig['from_name']
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE furn_email_config SET 
                        smtp_host = ?, smtp_port = ?, smtp_username = ?, 
                        smtp_encryption = ?, from_email = ?, from_name = ?
                        WHERE id = 1
                    ");
                    $stmt->execute([
                        $emailConfig['smtp_host'],
                        $emailConfig['smtp_port'],
                        $emailConfig['smtp_username'],
                        $emailConfig['smtp_encryption'],
                        $emailConfig['from_email'],
                        $emailConfig['from_name']
                    ]);
                }
                $message = '✅ Email configuration updated successfully!';
                $messageType = 'success';
                break;
                
            case 'update_tax':
                try {
                    $pdo->beginTransaction();
                    foreach ($_POST['tax_configs'] as $taxId => $taxData) {
                        if ($taxId === 'new') {
                            $stmt = $pdo->prepare("
                                INSERT INTO furn_tax_config (tax_name, tax_rate, tax_type, is_active)
                                VALUES (?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $taxData['tax_name'],
                                $taxData['tax_rate'],
                                $taxData['tax_type'],
                                isset($taxData['is_active']) ? 1 : 0
                            ]);
                        } else {
                            $stmt = $pdo->prepare("
                                UPDATE furn_tax_config SET 
                                tax_name = ?, tax_rate = ?, tax_type = ?, is_active = ?
                                WHERE id = ?
                            ");
                            $stmt->execute([
                                $taxData['tax_name'],
                                $taxData['tax_rate'],
                                $taxData['tax_type'],
                                isset($taxData['is_active']) ? 1 : 0,
                                $taxId
                            ]);
                        }
                    }
                    $pdo->commit();
                    $message = '✅ Tax configuration updated successfully!';
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    throw $e;
                }
                break;
                
            case 'update_payment_methods':
                try {
                    $cashEnabled = isset($_POST['payment_method_cash']) ? '1' : '0';
                    $bankEnabled = isset($_POST['payment_method_bank_transfer']) ? '1' : '0';

                    // Save toggles to furn_settings
                    foreach (['payment_method_cash' => $cashEnabled, 'payment_method_bank_transfer' => $bankEnabled] as $key => $value) {
                        $pdo->prepare("INSERT INTO furn_settings (setting_key, setting_value, setting_type, category) VALUES (?, ?, 'text', 'payments') ON DUPLICATE KEY UPDATE setting_value = ?")
                            ->execute([$key, $value, $value]);
                    }

                    // Save bank accounts to furn_bank_accounts (the real table customers read from)
                    $names     = $_POST['bank_name']    ?? [];
                    $numbers   = $_POST['bank_account'] ?? [];
                    $holders   = $_POST['bank_holder']  ?? [];
                    $addresses = $_POST['bank_address'] ?? [];
                    $swifts    = $_POST['bank_swift']   ?? [];
                    $phones    = $_POST['bank_phone']   ?? [];
                    $emails    = $_POST['bank_email']   ?? [];

                    $pdo->exec("DELETE FROM furn_bank_accounts");
                    $stmt = $pdo->prepare("INSERT INTO furn_bank_accounts (bank_name, account_number, account_holder, bank_address, swift_code, phone, email, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
                    foreach ($names as $i => $name) {
                        $name = trim($name);
                        if ($name === '' || empty(trim($numbers[$i] ?? '')) || empty(trim($holders[$i] ?? ''))) continue;
                        $stmt->execute([
                            $name,
                            trim($numbers[$i]),
                            trim($holders[$i]),
                            trim($addresses[$i] ?? ''),
                            trim($swifts[$i] ?? ''),
                            trim($phones[$i] ?? ''),
                            trim($emails[$i] ?? ''),
                        ]);
                    }

                    $message = '✅ Payment methods updated successfully!';
                    $messageType = 'success';
                } catch (PDOException $e) {
                    throw $e;
                }
                break;
        }
    } catch (PDOException $e) {
        $message = '⚠️ Tables not created yet. Run the SQL schema first! Error: ' . $e->getMessage();
        $messageType = 'warning';
    }
    } // end else (CSRF valid)
} // end if POST

// Fetch all settings grouped by category
$settingsByCategory = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value, setting_type, category FROM furn_settings ORDER BY category, setting_key");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (isset($row['category']) && isset($row['setting_key'])) {
            $settingsByCategory[$row['category']][$row['setting_key']] = $row['setting_value'];
        }
    }
} catch (PDOException $e) {}

// Fetch company info
$companyInfo = null;
try {
    $stmt = $pdo->query("SELECT * FROM furn_company_info LIMIT 1");
    $companyInfo = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Fetch email config
$emailConfig = null;
try {
    $stmt = $pdo->query("SELECT * FROM furn_email_config WHERE is_active = 1 LIMIT 1");
    $emailConfig = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Fetch tax configs — clean up empty/duplicate records first
$taxConfigs = [];
try {
    // Delete rows with empty tax names (duplicates from accidental clicks)
    $pdo->exec("DELETE FROM furn_tax_config WHERE tax_name IS NULL OR TRIM(tax_name) = ''");

    // If no records remain, insert one default
    $count = (int)$pdo->query("SELECT COUNT(*) FROM furn_tax_config")->fetchColumn();
    if ($count === 0) {
        $pdo->exec("INSERT INTO furn_tax_config (tax_name, tax_rate, tax_type, is_active) VALUES ('VAT', 15.00, 'percentage', 1)");
    }

    $stmt = $pdo->query("SELECT * FROM furn_tax_config ORDER BY id");
    $taxConfigs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Ensure payment methods table exists and fetch payment methods
$paymentMethods = [];
try {
    // Create table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS furn_payment_methods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        method_name VARCHAR(100) NOT NULL,
        method_type ENUM('cash','bank_transfer','card','mobile_money','check','other') NOT NULL DEFAULT 'cash',
        account_details TEXT,
        instructions TEXT,
        is_active TINYINT(1) DEFAULT 1,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_method_name (method_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Hard reset — truncate and re-insert only the 2 needed methods
    $count = (int)$pdo->query("SELECT COUNT(*) FROM furn_payment_methods")->fetchColumn();
    if ($count !== 2) {
        $pdo->exec("TRUNCATE TABLE furn_payment_methods");
        $pdo->exec("INSERT INTO furn_payment_methods (method_name, method_type, is_active, display_order) VALUES
            ('Cash', 'cash', 1, 1),
            ('Bank Transfer', 'bank_transfer', 1, 2)");
    }

    // If nothing left, insert clean defaults
    $count = (int)$pdo->query("SELECT COUNT(*) FROM furn_payment_methods")->fetchColumn();
    if ($count === 0) {
        $pdo->exec("INSERT INTO furn_payment_methods (method_name, method_type, is_active, display_order) VALUES
            ('Cash', 'cash', 1, 1),
            ('Bank Transfer', 'bank_transfer', 1, 2)");
    }

    $stmt = $pdo->query("SELECT * FROM furn_payment_methods ORDER BY display_order, id");
    $paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Check if tables exist
$tablesExist = true;
$setupRequired = false;
try {
    $pdo->query("SELECT COUNT(*) FROM furn_settings LIMIT 1");
} catch (PDOException $e) {
    $tablesExist = false;
    $setupRequired = true;
}

// Helper function to get setting value
function getSetting($key, $default = '', $category = 'general') {
    global $settingsByCategory;
    if (isset($settingsByCategory[$category][$key])) {
        return $settingsByCategory[$category][$key];
    }
    return $default;
}

// Get database and system info
$dbInfo = [
    'name' => DB_NAME,
    'host' => DB_HOST,
    'tables' => $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'")->fetchColumn(),
    'size' => 'N/A'
];

$stats = [
    'pending_orders' => $pdo->query("SELECT COUNT(*) FROM furn_orders WHERE status = 'pending_cost_approval'")->fetchColumn(),
    'low_stock_materials' => $pdo->query("SELECT COUNT(*) FROM furn_materials WHERE current_stock < minimum_stock")->fetchColumn()
];

$pageTitle = 'Settings';
// Remove dbInfo — no longer needed (database tab removed)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - FurnitureCraft Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/css/admin-responsive.css">
    <style>
        .settings-tabs{display:flex;gap:8px;background:#F8F9FA;padding:8px;border-radius:12px;margin-bottom:28px;overflow-x:auto;}
        .tab-btn{flex:1;min-width:110px;padding:12px 16px;border:none;background:transparent;color:#6C757D;font-weight:600;cursor:pointer;border-radius:8px;transition:all .3s;white-space:nowrap;font-size:13px;}
        .tab-btn:hover{background:rgba(230,126,34,.1);color:#e67e22;}
        .tab-btn.active{background:linear-gradient(135deg,#e67e22,#d35400);color:white;box-shadow:0 4px 15px rgba(230,126,34,.3);}
        .tab-content{display:none;animation:fadeIn .3s;}
        .tab-content.active{display:block;}
        @keyframes fadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
        @keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.02)}}
        .section-card-modern{background:white;border-radius:14px;padding:28px;margin-bottom:22px;box-shadow:0 4px 20px rgba(0,0,0,.08);border-left:4px solid #e67e22;}
        .section-title-modern{font-size:18px;font-weight:700;color:#2c3e50;margin-bottom:22px;display:flex;align-items:center;gap:10px;}
        .section-title-modern i{color:#e67e22;font-size:20px;}
        .form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:18px;}
        .form-group-modern{margin-bottom:18px;}
        .form-group-modern label{display:block;font-weight:600;color:#2c3e50;margin-bottom:7px;font-size:13px;}
        .form-control-modern{width:100%;padding:11px 14px;border:2px solid #E9ECEF;border-radius:9px;font-family:inherit;font-size:14px;transition:all .3s;box-sizing:border-box;}
        .form-control-modern:focus{outline:none;border-color:#e67e22;box-shadow:0 0 0 3px rgba(230,126,34,.1);}
        .checkbox-group-modern{display:flex;align-items:center;gap:10px;padding:13px;background:#F8F9FA;border-radius:9px;margin-bottom:10px;cursor:pointer;transition:all .3s;}
        .checkbox-group-modern:hover{background:#E9ECEF;}
        .checkbox-group-modern input[type="checkbox"]{width:18px;height:18px;cursor:pointer;accent-color:#e67e22;}
        .btn-save-modern{background:linear-gradient(135deg,#e67e22,#d35400);color:white;border:none;padding:13px 30px;border-radius:9px;font-weight:700;font-size:14px;cursor:pointer;transition:all .3s;box-shadow:0 4px 15px rgba(230,126,34,.3);}
        .btn-save-modern:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(230,126,34,.4);}
    </style>
</head>
<body>
    <?php include_once __DIR__ . '/../../includes/admin_sidebar.php'; ?>
    
    <!-- Top Header -->
    <?php 
    $pageTitle = 'System Settings';
    include_once __DIR__ . '/../../includes/admin_header.php'; 
    ?>

    <div class="main-content">
        <?php if ($setupRequired): ?>
        <div style="padding: 24px; background: linear-gradient(135deg, #f39c12, #e67e22); color: white; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 4px 20px rgba(230, 126, 34, 0.3);">
            <h3 style="margin: 0 0 12px 0; font-size: 20px;"><i class="fas fa-exclamation-triangle"></i> Database Setup Required</h3>
            <p style="margin: 0 0 16px 0; opacity: 0.95;">The settings tables haven't been created yet. Click the button below to set up automatically:</p>
            <a href="<?php echo BASE_URL; ?>/public/setup-settings.php" style="display: inline-block; padding: 12px 24px; background: white; color: #e67e22; text-decoration: none; border-radius: 8px; font-weight: bold; transition: all 0.3s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 15px rgba(0,0,0,0.2)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                <i class="fas fa-magic"></i> Run Automatic Setup
            </a>
            <p style="margin: 12px 0 0 0; font-size: 13px; opacity: 0.8;">
                <small>This will create 5 tables and insert default settings • Takes ~2 seconds</small>
            </p>
        </div>
        <?php endif; ?>

        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>" style="background: <?php echo $messageType === 'success' ? '#d4edda' : ($messageType === 'warning' ? '#fff3cd' : '#f8d7da'); ?>; color: <?php echo $messageType === 'success' ? '#155724' : ($messageType === 'warning' ? '#856404' : '#721c24'); ?>; border: 1px solid <?php echo $messageType === 'success' ? '#c3e6cb' : ($messageType === 'warning' ? '#ffeaa7' : '#f5c6cb'); ?>; border-radius: 10px; padding: 16px; margin-bottom: 24px;">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <h2 style="margin-bottom: 10px; color: #2c3e50;"><i class="fas fa-cog"></i> System Configuration</h2>
        <p style="color: #7f8c8d; margin-bottom: 30px;">Manage all system settings, company information, and preferences</p>

        <!-- Tabs — essential only -->
        <div class="settings-tabs">
            <button class="tab-btn active" onclick="switchTab('general', this)"><i class="fas fa-cogs"></i> General</button>
            <button class="tab-btn" onclick="switchTab('company', this)"><i class="fas fa-building"></i> Company</button>
            <button class="tab-btn" onclick="switchTab('business', this)"><i class="fas fa-briefcase"></i> Business</button>
            <button class="tab-btn" onclick="switchTab('security', this)"><i class="fas fa-shield-alt"></i> Security</button>
            <button class="tab-btn" onclick="switchTab('notifications', this)"><i class="fas fa-bell"></i> Notifications</button>
            <button class="tab-btn" onclick="switchTab('tax', this)"><i class="fas fa-percent"></i> Tax</button>
            <button class="tab-btn" onclick="switchTab('payments', this)"><i class="fas fa-credit-card"></i> Payments</button>
        </div>

        <!-- General Settings Tab -->
        <div id="general" class="tab-content active">
            <div class="section-card-modern">
                <div class="section-title-modern"><i class="fas fa-globe"></i> General Settings</div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SecurityUtil::getCsrfToken()); ?>">
                    <input type="hidden" name="action" value="update_general">
                    <div class="form-grid">
                        <div class="form-group-modern">
                            <label><i class="fas fa-signature"></i> Site Name</label>
                            <input type="text" name="site_name" class="form-control-modern" value="<?php echo htmlspecialchars(getSetting('site_name', 'FurnitureCraft Workshop')); ?>" placeholder="Enter site name">
                        </div>
                        <div class="form-group-modern">
                            <label><i class="fas fa-money-bill-wave"></i> Currency</label>
                            <select name="currency" class="form-control-modern">
                                <option value="ETB" <?php echo getSetting('currency') === 'ETB' ? 'selected' : ''; ?>>Ethiopian Birr (ETB)</option>
                                <option value="USD" <?php echo getSetting('currency') === 'USD' ? 'selected' : ''; ?>>US Dollar (USD)</option>
                                <option value="EUR" <?php echo getSetting('currency') === 'EUR' ? 'selected' : ''; ?>>Euro (EUR)</option>
                            </select>
                        </div>
                        <div class="form-group-modern">
                            <label><i class="fas fa-clock"></i> Timezone</label>
                            <select name="timezone" class="form-control-modern">
                                <option value="Africa/Addis_Ababa" <?php echo getSetting('timezone') === 'Africa/Addis_Ababa' ? 'selected' : ''; ?>>Africa/Addis Ababa</option>
                                <option value="UTC" <?php echo getSetting('timezone') === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                            </select>
                        </div>
                        <div class="form-group-modern">
                            <label><i class="fas fa-calendar"></i> Date Format</label>
                            <select name="date_format" class="form-control-modern">
                                <option value="Y-m-d" <?php echo getSetting('date_format') === 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                                <option value="d/m/Y" <?php echo getSetting('date_format') === 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                                <option value="m/d/Y" <?php echo getSetting('date_format') === 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                            </select>
                        </div>
                        <div class="form-group-modern">
                            <label><i class="fas fa-language"></i> Language</label>
                            <select name="language" class="form-control-modern">
                                <option value="en" <?php echo getSetting('language') === 'en' ? 'selected' : ''; ?>>English</option>
                                <option value="am" <?php echo getSetting('language') === 'am' ? 'selected' : ''; ?>>Amharic</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn-save-modern"><i class="fas fa-save"></i> Save General Settings</button>
                </form>
            </div>
        </div>

        <!-- Company Info Tab -->
        <div id="company" class="tab-content">
            <div class="section-card-modern">
                <div class="section-title-modern"><i class="fas fa-building"></i> Company Information</div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SecurityUtil::getCsrfToken()); ?>">
                    <input type="hidden" name="action" value="update_company">
                    <div class="form-grid">
                        <div class="form-group-modern">
                            <label><i class="fas fa-building"></i> Company Name</label>
                            <input type="text" name="company_name" class="form-control-modern" value="<?php echo htmlspecialchars($companyInfo['company_name'] ?? 'FurnitureCraft Workshop'); ?>">
                        </div>
                        <div class="form-group-modern">
                            <label><i class="fas fa-map-marker-alt"></i> Address Line 1</label>
                            <input type="text" name="address_line1" class="form-control-modern" value="<?php echo htmlspecialchars($companyInfo['address_line1'] ?? ''); ?>">
                        </div>
                        <div class="form-group-modern">
                            <label><i class="fas fa-city"></i> City</label>
                            <input type="text" name="city" class="form-control-modern" value="<?php echo htmlspecialchars($companyInfo['city'] ?? ''); ?>">
                        </div>
                        <div class="form-group-modern">
                            <label><i class="fas fa-flag"></i> Country</label>
                            <input type="text" name="country" class="form-control-modern" value="<?php echo htmlspecialchars($companyInfo['country'] ?? 'Ethiopia'); ?>">
                        </div>
                        <div class="form-group-modern">
                            <label><i class="fas fa-phone"></i> Phone Primary</label>
                            <input type="text" name="phone_primary" class="form-control-modern" value="<?php echo htmlspecialchars($companyInfo['phone_primary'] ?? ''); ?>">
                        </div>
                        <div class="form-group-modern">
                            <label><i class="fas fa-envelope"></i> Email Primary</label>
                            <input type="email" name="email_primary" class="form-control-modern" value="<?php echo htmlspecialchars($companyInfo['email_primary'] ?? ''); ?>">
                        </div>
                        <div class="form-group-modern">
                            <label><i class="fas fa-globe"></i> Website</label>
                            <input type="url" name="website" class="form-control-modern" value="<?php echo htmlspecialchars($companyInfo['website'] ?? ''); ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn-save-modern"><i class="fas fa-save"></i> Save Company Information</button>
                </form>
            </div>
        </div>

        <!-- Business Settings Tab -->
        <div id="business" class="tab-content">
            <div class="section-card-modern">
                <div class="section-title-modern"><i class="fas fa-briefcase"></i> Business Rules & Policies</div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SecurityUtil::getCsrfToken()); ?>">
                    <input type="hidden" name="action" value="update_business">
                    <div class="form-grid">
                        <div class="form-group-modern">
                            <label><i class="fas fa-calendar-alt"></i> Fiscal Year Start (MM-DD)</label>
                            <input type="text" name="fiscal_year_start" class="form-control-modern" value="<?php echo htmlspecialchars(getSetting('fiscal_year_start', '01-01')); ?>" placeholder="MM-DD" pattern="(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])">
                            <small style="color: #7f8c8d; display: block; margin-top: 6px;">Start date of your fiscal year</small>
                        </div>
                        <div class="form-group-modern">
                            <label><i class="fas fa-percentage"></i> Default Deposit Percentage</label>
                            <input type="number" name="default_deposit_percentage" class="form-control-modern" value="<?php echo htmlspecialchars(getSetting('default_deposit_percentage', '50')); ?>" min="0" max="100">
                            <small style="color: #7f8c8d; display: block; margin-top: 6px;">Required deposit for custom orders (%)</small>
                        </div>

                        <div class="form-group-modern">
                            <label><i class="fas fa-exclamation-triangle"></i> Default Low Stock Threshold</label>
                            <input type="number" name="low_stock_threshold" class="form-control-modern" value="<?php echo htmlspecialchars(getSetting('low_stock_threshold', '20')); ?>" min="0" step="0.01">
                            <small style="color: #7f8c8d; display: block; margin-top: 6px;">Applied to all new materials automatically. Can be overridden per material in Edit.</small>
                        </div>

                        <div class="form-group-modern">
                            <label><i class="fas fa-building"></i> Overhead Rate (%)</label>
                            <input type="number" name="overhead_rate" class="form-control-modern" value="<?php echo htmlspecialchars(getSetting('overhead_rate', '10')); ?>" min="0" max="100" step="0.1">
                            <small style="color: #7f8c8d; display: block; margin-top: 6px;">Used in profit calculation: overhead = revenue × this rate. Default 10%.</small>
                        </div>
                        <div class="form-group-modern">
                            <label><i class="fas fa-boxes"></i> Allow Backorders</label>
                            <select name="allow_backorders" class="form-control-modern">
                                <option value="0" <?php echo getSetting('allow_backorders', '0') === '0' ? 'selected' : ''; ?>>No - Prevent sales when out of stock</option>
                                <option value="1" <?php echo getSetting('allow_backorders', '0') === '1' ? 'selected' : ''; ?>>Yes - Allow backorders</option>
                            </select>
                        </div>
                        <div class="form-group-modern">
                            <label><i class="fas fa-check-double"></i> Auto-approve Orders</label>
                            <select name="auto_approve_orders" class="form-control-modern">
                                <option value="0" <?php echo getSetting('auto_approve_orders', '0') === '0' ? 'selected' : ''; ?>>No - Manual approval required</option>
                                <option value="1" <?php echo getSetting('auto_approve_orders', '0') === '1' ? 'selected' : ''; ?>>Yes - Auto-approve standard orders</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn-save-modern"><i class="fas fa-save"></i> Save Business Settings</button>
                </form>
            </div>
        </div>

        <!-- Security Settings Tab -->
        <div id="security" class="tab-content">
            <div class="section-card-modern">
                <div class="section-title-modern"><i class="fas fa-shield-alt"></i> Security Policies</div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SecurityUtil::getCsrfToken()); ?>">
                    <input type="hidden" name="action" value="update_security">
                    <div class="form-grid">
                        <div class="form-group-modern">
                            <label><i class="fas fa-clock"></i> Session Timeout (seconds)</label>
                            <input type="number" name="session_timeout" class="form-control-modern" value="<?php echo htmlspecialchars(getSetting('session_timeout', '3600')); ?>" min="300" max="86400" step="300">
                            <small style="color: #7f8c8d; display: block; margin-top: 6px;">Auto-logout after inactivity (5min - 24hrs)</small>
                        </div>
                        <div class="form-group-modern">
                            <label><i class="fas fa-key"></i> Minimum Password Length</label>
                            <input type="number" name="password_min_length" class="form-control-modern" value="<?php echo htmlspecialchars(getSetting('password_min_length', '8')); ?>" min="6" max="128">
                            <small style="color: #7f8c8d; display: block; margin-top: 6px;">Minimum characters required</small>
                        </div>
                        <div class="form-group-modern">
                            <label><i class="fas fa-exclamation-triangle"></i> Require Special Characters</label>
                            <select name="require_special_char" class="form-control-modern">
                                <option value="0" <?php echo getSetting('require_special_char', '0') === '0' ? 'selected' : ''; ?>>No - Letters and numbers only</option>
                                <option value="1" <?php echo getSetting('require_special_char', '0') === '1' ? 'selected' : ''; ?>>Yes - Must include special chars (!@#$%^&*)</option>
                            </select>
                        </div>
                        <div class="form-group-modern">
                            <label><i class="fas fa-lock"></i> Max Login Attempts</label>
                            <input type="number" name="max_login_attempts" class="form-control-modern" value="<?php echo htmlspecialchars(getSetting('max_login_attempts', '5')); ?>" min="3" max="10">
                            <small style="color: #7f8c8d; display: block; margin-top: 6px;">Attempts before account lockout</small>
                        </div>
                        <div class="form-group-modern">
                            <label><i class="fas fa-hourglass-half"></i> Lockout Duration (seconds)</label>
                            <input type="number" name="lockout_duration" class="form-control-modern" value="<?php echo htmlspecialchars(getSetting('lockout_duration', '900')); ?>" min="60" max="86400" step="60">
                            <small style="color: #7f8c8d; display: block; margin-top: 6px;">How long account stays locked (1min - 24hrs)</small>
                        </div>
                    </div>
                    <button type="submit" class="btn-save-modern"><i class="fas fa-save"></i> Save Security Settings</button>
                </form>
            </div>
        </div>

        <!-- Notifications Tab -->
        <div id="notifications" class="tab-content">
            <div class="section-card-modern">
                <div class="section-title-modern"><i class="fas fa-bell"></i> Notification Preferences</div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SecurityUtil::getCsrfToken()); ?>">
                    <input type="hidden" name="action" value="update_notifications">
                    <div class="checkbox-group-modern">
                        <input type="checkbox" name="email_notifications" id="email_notif" <?php echo getSetting('email_notifications', '1') === '1' ? 'checked' : ''; ?>>
                        <label for="email_notif" style="margin: 0; cursor: pointer; flex: 1;"><i class="fas fa-envelope"></i> Enable Email Notifications</label>
                    </div>
                    <div class="checkbox-group-modern">
                        <input type="checkbox" name="sms_notifications" id="sms_notif" <?php echo getSetting('sms_notifications', '0') === '1' ? 'checked' : ''; ?>>
                        <label for="sms_notif" style="margin: 0; cursor: pointer; flex: 1;"><i class="fas fa-sms"></i> Enable SMS Notifications</label>
                    </div>
                    <div class="checkbox-group-modern">
                        <input type="checkbox" name="order_confirmation_email" id="order_conf" <?php echo getSetting('order_confirmation_email', '1') === '1' ? 'checked' : ''; ?>>
                        <label for="order_conf" style="margin: 0; cursor: pointer; flex: 1;"><i class="fas fa-check-circle"></i> Send Order Confirmation Emails</label>
                    </div>
                    <div class="checkbox-group-modern">
                        <input type="checkbox" name="order_status_updates" id="order_status" <?php echo getSetting('order_status_updates', '1') === '1' ? 'checked' : ''; ?>>
                        <label for="order_status" style="margin: 0; cursor: pointer; flex: 1;"><i class="fas fa-shipping-fast"></i> Send Order Status Updates</label>
                    </div>
                    <div class="checkbox-group-modern">
                        <input type="checkbox" name="payment_received_email" id="payment_email" <?php echo getSetting('payment_received_email', '1') === '1' ? 'checked' : ''; ?>>
                        <label for="payment_email" style="margin: 0; cursor: pointer; flex: 1;"><i class="fas fa-credit-card"></i> Send Payment Received Emails</label>
                    </div>
                    <div class="checkbox-group-modern">
                        <input type="checkbox" name="low_stock_alert" id="low_stock" <?php echo getSetting('low_stock_alert', '1') === '1' ? 'checked' : ''; ?>>
                        <label for="low_stock" style="margin: 0; cursor: pointer; flex: 1;"><i class="fas fa-exclamation-triangle"></i> Low Stock Alerts</label>
                    </div>
                    <div class="checkbox-group-modern">
                        <input type="checkbox" name="new_order_alert" id="new_order" <?php echo getSetting('new_order_alert', '1') === '1' ? 'checked' : ''; ?>>
                        <label for="new_order" style="margin: 0; cursor: pointer; flex: 1;"><i class="fas fa-shopping-cart"></i> New Order Alerts</label>
                    </div>
                    <button type="submit" class="btn-save-modern"><i class="fas fa-save"></i> Save Notification Settings</button>
                </form>
            </div>
        </div>

        <!-- Email Configuration Tab -->
        <div id="email" class="tab-content">
            <div class="section-card-modern">
                <div class="section-title-modern"><i class="fas fa-envelope"></i> SMTP Email Configuration</div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SecurityUtil::getCsrfToken()); ?>">
                    <input type="hidden" name="action" value="update_email_config">
                    <div class="form-grid">
                        <div class="form-group-modern" style="grid-column: 1 / -1;">
                            <label><i class="fas fa-server"></i> SMTP Host</label>
                            <input type="text" name="smtp_host" class="form-control-modern" value="<?php echo htmlspecialchars($emailConfig['smtp_host'] ?? 'smtp.gmail.com'); ?>" placeholder="smtp.example.com">
                        </div>
                        <div class="form-group-modern">
                            <label><i class="fas fa-network-wired"></i> SMTP Port</label>
                            <input type="number" name="smtp_port" class="form-control-modern" value="<?php echo htmlspecialchars($emailConfig['smtp_port'] ?? '587'); ?>" min="1" max="65535">
                        </div>
                        <div class="form-group-modern">
                            <label><i class="fas fa-lock"></i> Encryption</label>
                            <select name="smtp_encryption" class="form-control-modern">
                                <option value="none" <?php echo ($emailConfig['smtp_encryption'] ?? 'tls') === 'none' ? 'selected' : ''; ?>>None</option>
                                <option value="tls" <?php echo ($emailConfig['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS (Recommended)</option>
                                <option value="ssl" <?php echo ($emailConfig['smtp_encryption'] ?? 'tls') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                            </select>
                        </div>
                        <div class="form-group-modern">
                            <label><i class="fas fa-user"></i> SMTP Username</label>
                            <input type="text" name="smtp_username" class="form-control-modern" value="<?php echo htmlspecialchars($emailConfig['smtp_username'] ?? ''); ?>" placeholder="your@email.com">
                        </div>
                        <div class="form-group-modern">
                            <label><i class="fas fa-key"></i> SMTP Password</label>
                            <input type="password" name="smtp_password" class="form-control-modern" placeholder="Leave blank to keep current password">
                            <small style="color: #7f8c8d; display: block; margin-top: 6px;">Enter new password only if you want to change it</small>
                        </div>
                        <div class="form-group-modern">
                            <label><i class="fas fa-at"></i> From Email</label>
                            <input type="email" name="from_email" class="form-control-modern" value="<?php echo htmlspecialchars($emailConfig['from_email'] ?? 'noreply@furniturecraft.com'); ?>">
                        </div>
                        <div class="form-group-modern">
                            <label><i class="fas fa-signature"></i> From Name</label>
                            <input type="text" name="from_name" class="form-control-modern" value="<?php echo htmlspecialchars($emailConfig['from_name'] ?? 'FurnitureCraft Workshop'); ?>">
                        </div>
                    </div>
                    <div class="info-badge" style="margin-top: 20px;">
                        <i class="fas fa-info-circle"></i>
                        <span>Common SMTP settings: Gmail uses smtp.gmail.com:587 with TLS. Yahoo uses smtp.mail.yahoo.com:465 with SSL.</span>
                    </div>
                    <button type="submit" class="btn-save-modern" style="margin-top: 20px;"><i class="fas fa-save"></i> Save Email Configuration</button>
                </form>
            </div>
        </div>

        <!-- Tax Configuration Tab -->
        <div id="tax" class="tab-content">
            <div class="section-card-modern">
                <div class="section-title-modern"><i class="fas fa-percent"></i> Tax Rates</div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SecurityUtil::getCsrfToken()); ?>">
                    <input type="hidden" name="action" value="update_business">
                    <div class="form-grid">
                        <div class="form-group-modern">
                            <label><i class="fas fa-percent"></i> VAT Rate (%)</label>
                            <input type="number" name="vat_rate" class="form-control-modern"
                                value="<?php echo htmlspecialchars(getSetting('vat_rate', '15')); ?>"
                                step="0.01" min="0" max="100">
                            <small style="color:#7f8c8d;display:block;margin-top:6px;">Applied to customer invoices (e.g. 15 for 15% VAT)</small>
                        </div>
                        <div class="form-group-modern">
                            <label><i class="fas fa-file-invoice-dollar"></i> Income Tax Rate (%)</label>
                            <input type="number" name="income_tax_rate" class="form-control-modern"
                                value="<?php echo htmlspecialchars(getSetting('income_tax_rate', '0')); ?>"
                                step="0.01" min="0" max="100">
                            <small style="color:#7f8c8d;display:block;margin-top:6px;">Deducted from net profit in reports (0 if not applicable)</small>
                        </div>
                    </div>
                    <!-- preserve other business fields as hidden so they don't get reset -->
                    <input type="hidden" name="fiscal_year_start"          value="<?php echo htmlspecialchars(getSetting('fiscal_year_start','01-01')); ?>">
                    <input type="hidden" name="default_deposit_percentage" value="<?php echo htmlspecialchars(getSetting('default_deposit_percentage','40')); ?>">
                    <input type="hidden" name="low_stock_threshold"        value="<?php echo htmlspecialchars(getSetting('low_stock_threshold','20')); ?>">
                    <input type="hidden" name="overhead_rate"              value="<?php echo htmlspecialchars(getSetting('overhead_rate','10')); ?>">
                    <?php if(getSetting('allow_backorders','0')==='1'): ?><input type="hidden" name="allow_backorders" value="1"><?php endif; ?>
                    <?php if(getSetting('auto_approve_orders','0')==='1'): ?><input type="hidden" name="auto_approve_orders" value="1"><?php endif; ?>
                    <button type="submit" class="btn-save-modern"><i class="fas fa-save"></i> Save Tax Rates</button>
                </form>
            </div>
        </div>

        <!-- Payment Methods Tab -->
        <div id="payments" class="tab-content">
            <div class="section-card-modern">
                <div class="section-title-modern"><i class="fas fa-credit-card"></i> Payment Methods</div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SecurityUtil::getCsrfToken()); ?>">
                    <input type="hidden" name="action" value="update_payment_methods">

                    <!-- Cash -->
                    <div class="checkbox-group-modern" style="margin-bottom:16px;">
                        <input type="checkbox" name="payment_method_cash" id="pm_cash" value="1"
                            <?php echo getSetting('payment_method_cash','1')==='1'?'checked':''; ?>
                            style="width:20px;height:20px;accent-color:#e67e22;">
                        <label for="pm_cash" style="margin:0;cursor:pointer;flex:1;font-weight:600;">
                            <i class="fas fa-money-bill-wave" style="color:#27ae60;margin-right:6px;"></i> Cash
                        </label>
                    </div>

                    <!-- Bank Transfer toggle -->
                    <div class="checkbox-group-modern" style="margin-bottom:16px;">
                        <input type="checkbox" name="payment_method_bank_transfer" id="pm_bank" value="1"
                            <?php echo getSetting('payment_method_bank_transfer','1')==='1'?'checked':''; ?>
                            style="width:20px;height:20px;accent-color:#e67e22;">
                        <label for="pm_bank" style="margin:0;cursor:pointer;flex:1;font-weight:600;">
                            <i class="fas fa-university" style="color:#3498db;margin-right:6px;"></i> Bank Transfer
                        </label>
                    </div>

                    <!-- Bank accounts list -->
                    <div id="bankDetailsSection" style="<?php echo getSetting('payment_method_bank_transfer','1')==='1'?'':'display:none;'; ?>margin-bottom:20px;">
                        <div style="font-weight:600;color:#2c3e50;margin-bottom:12px;font-size:14px;">
                            <i class="fas fa-university" style="color:#3498db;margin-right:6px;"></i>Bank Transfer Accounts
                        </div>
                        <div id="bankList">
                        <?php
                        // Load from furn_bank_accounts table (the real source customers read from)
                        $savedBanks = [];
                        try {
                            $stmt = $pdo->query("SELECT * FROM furn_bank_accounts ORDER BY bank_name");
                            $savedBanks = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        } catch (PDOException $e) {}
                        if (empty($savedBanks)) $savedBanks = [['bank_name'=>'','account_number'=>'','account_holder'=>'','bank_address'=>'','swift_code'=>'','phone'=>'','email'=>'']];
                        foreach ($savedBanks as $idx => $bank):
                        ?>
                        <div class="bank-entry" style="background:#f8f9fa;border:1.5px solid #e0e0e0;border-radius:10px;padding:16px;margin-bottom:12px;position:relative;">
                            <?php if ($idx > 0): ?>
                            <button type="button" onclick="this.closest('.bank-entry').remove()"
                                style="position:absolute;top:10px;right:10px;background:#e74c3c;color:white;border:none;border-radius:6px;padding:4px 10px;cursor:pointer;font-size:12px;">
                                <i class="fas fa-times"></i> Remove
                            </button>
                            <?php endif; ?>
                            <div class="form-grid">
                                <div class="form-group-modern">
                                    <label><i class="fas fa-university"></i> Bank Name <span style="color:#e74c3c;">*</span></label>
                                    <input type="text" name="bank_name[]" class="form-control-modern"
                                        value="<?php echo htmlspecialchars($bank['bank_name'] ?? ''); ?>"
                                        placeholder="e.g. Commercial Bank of Ethiopia">
                                </div>
                                <div class="form-group-modern">
                                    <label><i class="fas fa-hashtag"></i> Account Number <span style="color:#e74c3c;">*</span></label>
                                    <input type="text" name="bank_account[]" class="form-control-modern"
                                        value="<?php echo htmlspecialchars($bank['account_number'] ?? ''); ?>"
                                        placeholder="e.g. 1000123456789">
                                </div>
                                <div class="form-group-modern">
                                    <label><i class="fas fa-user"></i> Account Holder <span style="color:#e74c3c;">*</span></label>
                                    <input type="text" name="bank_holder[]" class="form-control-modern"
                                        value="<?php echo htmlspecialchars($bank['account_holder'] ?? ''); ?>"
                                        placeholder="e.g. SmartWorkshop PLC">
                                </div>
                                <div class="form-group-modern">
                                    <label><i class="fas fa-map-marker-alt"></i> Bank Address</label>
                                    <input type="text" name="bank_address[]" class="form-control-modern"
                                        value="<?php echo htmlspecialchars($bank['bank_address'] ?? ''); ?>"
                                        placeholder="e.g. Bole Road, Addis Ababa">
                                </div>
                                <div class="form-group-modern">
                                    <label><i class="fas fa-code"></i> SWIFT Code</label>
                                    <input type="text" name="bank_swift[]" class="form-control-modern"
                                        value="<?php echo htmlspecialchars($bank['swift_code'] ?? ''); ?>"
                                        placeholder="e.g. CBETETAA">
                                </div>
                                <div class="form-group-modern">
                                    <label><i class="fas fa-phone"></i> Phone</label>
                                    <input type="text" name="bank_phone[]" class="form-control-modern"
                                        value="<?php echo htmlspecialchars($bank['phone'] ?? ''); ?>"
                                        placeholder="e.g. +251-11-123-4567">
                                </div>
                                <div class="form-group-modern">
                                    <label><i class="fas fa-envelope"></i> Email</label>
                                    <input type="email" name="bank_email[]" class="form-control-modern"
                                        value="<?php echo htmlspecialchars($bank['email'] ?? ''); ?>"
                                        placeholder="e.g. info@bank.com">
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        </div>

                        <button type="button" onclick="addBankEntry()"
                            style="margin-top:4px;background:#3498db;color:white;border:none;padding:10px 18px;border-radius:8px;cursor:pointer;font-weight:600;display:inline-flex;align-items:center;gap:8px;">
                            <i class="fas fa-plus"></i> Add Another Bank
                        </button>
                    </div>

                    <button type="submit" class="btn-save-modern"><i class="fas fa-save"></i> Save Payment Methods</button>
                </form>
            </div>
        </div>

        <script>
        document.getElementById('pm_bank').addEventListener('change', function() {
            document.getElementById('bankDetailsSection').style.display = this.checked ? '' : 'none';
        });
        function addBankEntry() {
            const tpl = `<div class="bank-entry" style="background:#f8f9fa;border:1.5px solid #e0e0e0;border-radius:10px;padding:16px;margin-bottom:12px;position:relative;">
                <button type="button" onclick="this.closest('.bank-entry').remove()"
                    style="position:absolute;top:10px;right:10px;background:#e74c3c;color:white;border:none;border-radius:6px;padding:4px 10px;cursor:pointer;font-size:12px;">
                    <i class="fas fa-times"></i> Remove
                </button>
                <div class="form-grid">
                    <div class="form-group-modern">
                        <label><i class="fas fa-university"></i> Bank Name <span style="color:#e74c3c;">*</span></label>
                        <input type="text" name="bank_name[]" class="form-control-modern" placeholder="e.g. Awash Bank">
                    </div>
                    <div class="form-group-modern">
                        <label><i class="fas fa-hashtag"></i> Account Number <span style="color:#e74c3c;">*</span></label>
                        <input type="text" name="bank_account[]" class="form-control-modern" placeholder="e.g. 0123456789">
                    </div>
                    <div class="form-group-modern">
                        <label><i class="fas fa-user"></i> Account Holder <span style="color:#e74c3c;">*</span></label>
                        <input type="text" name="bank_holder[]" class="form-control-modern" placeholder="e.g. SmartWorkshop PLC">
                    </div>
                    <div class="form-group-modern">
                        <label><i class="fas fa-map-marker-alt"></i> Bank Address</label>
                        <input type="text" name="bank_address[]" class="form-control-modern" placeholder="e.g. Bole Road, Addis Ababa">
                    </div>
                    <div class="form-group-modern">
                        <label><i class="fas fa-code"></i> SWIFT Code</label>
                        <input type="text" name="bank_swift[]" class="form-control-modern" placeholder="e.g. CBETETAA">
                    </div>
                    <div class="form-group-modern">
                        <label><i class="fas fa-phone"></i> Phone</label>
                        <input type="text" name="bank_phone[]" class="form-control-modern" placeholder="e.g. +251-11-123-4567">
                    </div>
                    <div class="form-group-modern">
                        <label><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" name="bank_email[]" class="form-control-modern" placeholder="e.g. info@bank.com">
                    </div>
                </div>
            </div>`;
            document.getElementById('bankList').insertAdjacentHTML('beforeend', tpl);
        }
        </script>

        <!-- System Tab -->
        <div id="system" class="tab-content">
            <div class="section-card-modern">
                <div class="section-title-modern"><i class="fas fa-server"></i> System Configuration</div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(SecurityUtil::getCsrfToken()); ?>">
                    <input type="hidden" name="action" value="update_system">
                    <div class="checkbox-group-modern">
                        <input type="checkbox" name="maintenance_mode" id="maint_mode" <?php echo getSetting('maintenance_mode', '0') === '1' ? 'checked' : ''; ?>>
                        <label for="maint_mode" style="margin: 0; cursor: pointer; flex: 1;"><i class="fas fa-tools"></i> Maintenance Mode (Disable public access)</label>
                    </div>
                    <div class="checkbox-group-modern">
                        <input type="checkbox" name="cache_enabled" id="cache_en" <?php echo getSetting('cache_enabled', '1') === '1' ? 'checked' : ''; ?>>
                        <label for="cache_en" style="margin: 0; cursor: pointer; flex: 1;"><i class="fas fa-bolt"></i> Enable Caching</label>
                    </div>
                    <div class="checkbox-group-modern">
                        <input type="checkbox" name="debug_mode" id="debug_en" <?php echo getSetting('debug_mode', '0') === '1' ? 'checked' : ''; ?>>
                        <label for="debug_en" style="margin: 0; cursor: pointer; flex: 1;"><i class="fas fa-bug"></i> Debug Mode (Show errors)</label>
                    </div>
                    <div class="form-group-modern" style="margin-top: 20px;">
                        <label><i class="fas fa-archive"></i> Log Retention Days</label>
                        <input type="number" name="log_retention_days" class="form-control-modern" value="<?php echo htmlspecialchars(getSetting('log_retention_days', '30')); ?>" min="1" max="365">
                        <small style="color: #7f8c8d; display: block; margin-top: 6px;">Number of days to keep system logs</small>
                    </div>
                    <button type="submit" class="btn-save-modern"><i class="fas fa-save"></i> Save System Settings</button>
                </form>
            </div>

            <!-- System Information -->
            <div class="section-card-modern" style="border-left-color: #3498db;">
                <div class="section-title-modern"><i class="fas fa-info-circle" style="color: #3498db;"></i> System Information</div>
                <div class="form-grid">
                    <div class="form-group-modern">
                        <label>PHP Version</label>
                        <div style="padding: 12px; background: #F8F9FA; border-radius: 8px;"><?php echo phpversion(); ?></div>
                    </div>
                    <div class="form-group-modern">
                        <label>Server Software</label>
                        <div style="padding: 12px; background: #F8F9FA; border-radius: 8px;"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></div>
                    </div>
                    <div class="form-group-modern">
                        <label>Max Upload Size</label>
                        <div style="padding: 12px; background: #F8F9FA; border-radius: 8px;"><?php echo ini_get('upload_max_filesize'); ?></div>
                    </div>
                    <div class="form-group-modern">
                        <label>Memory Limit</label>
                        <div style="padding: 12px; background: #F8F9FA; border-radius: 8px;"><?php echo ini_get('memory_limit'); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Database Tab -->
        <div id="database" class="tab-content">
            <div class="section-card-modern" style="border-left-color: #27ae60;">
                <div class="section-title-modern"><i class="fas fa-database" style="color: #27ae60;"></i> Database Information</div>
                <div class="info-badge">
                    <i class="fas fa-info-circle"></i>
                    <span>Database connection is active and functioning properly</span>
                </div>
                <div class="form-grid">
                    <div class="form-group-modern">
                        <label>Database Name</label>
                        <div style="padding: 12px; background: #F8F9FA; border-radius: 8px;"><?php echo htmlspecialchars($dbInfo['name']); ?></div>
                    </div>
                    <div class="form-group-modern">
                        <label>Host</label>
                        <div style="padding: 12px; background: #F8F9FA; border-radius: 8px;"><?php echo htmlspecialchars($dbInfo['host']); ?></div>
                    </div>
                    <div class="form-group-modern">
                        <label>Total Tables</label>
                        <div style="padding: 12px; background: #F8F9FA; border-radius: 8px;"><?php echo $dbInfo['tables']; ?></div>
                    </div>
                    <div class="form-group-modern">
                        <label>Status</label>
                        <div style="padding: 12px; background: #d4edda; border-radius: 8px; color: #155724; font-weight: 600;"><i class="fas fa-check-circle"></i> Connected</div>
                    </div>
                </div>

                <div class="stat-grid">
                    <div class="stat-box">
                        <div class="stat-value"><?php echo $stats['pending_orders']; ?></div>
                        <div class="stat-label">Pending Orders</div>
                    </div>
                    <div class="stat-box" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
                        <div class="stat-value"><?php echo $stats['low_stock_materials']; ?></div>
                        <div class="stat-label">Low Stock Alerts</div>
                    </div>
                </div>
            </div>

            <!-- SQL Setup Instructions -->
            <div class="sql-instructions">
                <h4><i class="fas fa-exclamation-triangle"></i> Important: Database Setup Required</h4>
                <p>To enable full settings functionality, run this SQL command in your database:</p>
                <div class="sql-code">mysql -u root furniturecraft_db &lt; c:\xampp\htdocs\NEWkoder\database\settings_schema.sql</div>
                <p style="margin-top: 12px; font-size: 13px;">This will create tables for: Settings, Company Info, Email Config, Tax Config, and Payment Methods</p>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName, btn) {
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById(tabName).classList.add('active');
            if (btn) btn.classList.add('active');
            localStorage.setItem('adminSettingsTab', tabName);
        }

        // Restore last active tab
        document.addEventListener('DOMContentLoaded', function() {
            const savedTab = localStorage.getItem('adminSettingsTab') || 'general';
            const tabBtn = document.querySelector(`.tab-btn[onclick="switchTab('${savedTab}', this)"]`);
            if (tabBtn && document.getElementById(savedTab)) {
                document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.getElementById(savedTab).classList.add('active');
                tabBtn.classList.add('active');
            }
        });

        // Add new tax row
        function addNewTax() {
            const container = document.getElementById('newTaxContainer');
            const newRow = document.createElement('div');
            newRow.style.cssText = 'display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 80px; gap: 12px; align-items: center; padding: 12px; background: #fff; border: 2px solid #27ae60; border-radius: 8px; margin-top: 12px; animation: fadeIn 0.3s;';
            newRow.innerHTML = `
                <input type="hidden" name="tax_configs[new][id]" value="new">
                <input type="text" name="tax_configs[new][tax_name]" class="form-control-modern" placeholder="Tax name" required>
                <input type="number" name="tax_configs[new][tax_rate]" class="form-control-modern" placeholder="Rate" step="0.01" min="0" max="100" required>
                <select name="tax_configs[new][tax_type]" class="form-control-modern">
                    <option value="percentage">Percentage</option>
                    <option value="fixed">Fixed Amount</option>
                </select>
                <div style="text-align: center;">
                    <input type="checkbox" name="tax_configs[new][is_active]" value="1" checked> Active
                </div>
                <button type="button" onclick="this.closest('div').remove()" style="background: #e74c3c; color: white; border: none; padding: 8px; border-radius: 6px; cursor: pointer;"><i class="fas fa-trash"></i></button>
            `;
            container.appendChild(newRow);
        }

        // Remove payment method with visual feedback
        function removePaymentMethod(btn) {
            const methodDiv = btn.closest('div').parentElement;
            methodDiv.style.transition = 'all 0.3s';
            methodDiv.style.opacity = '0.5';
            methodDiv.style.transform = 'translateX(-20px)';
            setTimeout(() => {
                methodDiv.remove();
                showSaveReminder();
            }, 300);
        }
        
        // Show reminder to save changes
        function showSaveReminder() {
            const saveBtn = document.querySelector('#paymentForm .btn-save-modern');
            if (saveBtn) {
                saveBtn.style.animation = 'pulse 1s infinite';
                saveBtn.innerHTML = '<i class="fas fa-exclamation-circle"></i> Save Changes to Apply Removal';
                saveBtn.style.background = '#e74c3c';
            }
        }
        
        // Add new payment method
        function addNewPayment() {
            const container = document.getElementById('newPaymentContainer');
            const newMethod = document.createElement('div');
            newMethod.style.cssText = 'background: white; border: 2px solid #27ae60; border-radius: 12px; padding: 20px; margin-bottom: 16px; animation: fadeIn 0.3s;';
            newMethod.innerHTML = `
                <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 100px; gap: 16px; margin-bottom: 16px;">
                    <div class="form-group-modern" style="margin: 0;">
                        <label><i class="fas fa-tag"></i> Method Name</label>
                        <input type="text" name="payment_methods[new][method_name]" class="form-control-modern" placeholder="Payment method name" required>
                    </div>
                    <div class="form-group-modern" style="margin: 0;">
                        <label><i class="fas fa-list"></i> Type</label>
                        <select name="payment_methods[new][method_type]" class="form-control-modern">
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                        </select>
                    </div>
                    <div class="form-group-modern" style="margin: 0;">
                        <label><i class="fas fa-sort-numeric-down"></i> Display Order</label>
                        <input type="number" name="payment_methods[new][display_order]" class="form-control-modern" value="0" min="0">
                    </div>
                    <div class="form-group-modern" style="margin: 0; display: flex; align-items: center; padding-top: 28px;">
                        <input type="checkbox" name="payment_methods[new][is_active]" value="1" checked style="width: 20px; height: 20px; accent-color: #e67e22;">
                        <label style="margin-left: 8px; cursor: pointer;">Active</label>
                    </div>
                </div>
                <div class="form-group-modern">
                    <label><i class="fas fa-info-circle"></i> Account Details / Instructions</label>
                    <textarea name="payment_methods[new][account_details]" class="form-control-modern" rows="2" placeholder="Bank account number, mobile money number, or payment instructions"></textarea>
                </div>
                <div style="text-align: right;">
                    <button type="button" onclick="this.closest('div').parentElement.remove()" style="background: #e74c3c; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600;"><i class="fas fa-trash"></i> Remove</button>
                </div>
            `;
            container.appendChild(newMethod);
        }
    </script>
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
