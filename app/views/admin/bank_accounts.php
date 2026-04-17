<?php
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: ' . BASE_URL . '/public/login'); exit();
}
$csrf_token = $_SESSION[CSRF_TOKEN_NAME] ?? bin2hex(random_bytes(32));
$_SESSION[CSRF_TOKEN_NAME] = $csrf_token;
require_once __DIR__ . '/../../../config/db_config.php';

$message = ''; $messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_to'csrf_token'])) {
        $message = 'Invalid CSRF token.'; $messageType = 'danger';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'add') {
            $bn = trim($_POST['bank_name'] ?? '');
'account_number'] ?? '');
            $ah = trim($_POST['account_holder'] ?? '');
            if ($bn && $an && $ah) {
                try {
                    $pdo->prepare("INSERT$bn,$an,$ah]);
                    $message = 'Bank account added!'; $messageType = 'success';
             'danger'; }
            } else { $message = 'All fiel
        } elseif ($action === 'edit') {
            $id = intval($_POST['id'] ?? 0);
            $bn = trim($_POST['bank_name'] ?? '');
            $an = trim($_POST['account_number'] ?? '');
            $ah = trim($_POST['account_holder'] ?? '');
            if ($id && $bn && $an && $ah) {
                try {
                    $pdo->prepare("UPDATE furn_bank_accounts SET bank_name=?, accour=? WHERE id=?")->execute([$bn,$an,$ah,$id]);
                    $message = 'Bank account updated!'; $messageType = 'success';
                } catcessageType = 'success';
                } catch (Exception $e) {
                    $message = 'Error: ' . $e->getMessage();
                    $messageType = 'danger';
                }
            } else {
                $message = 'Please fill in all required fields';
                $messageType = 'warning';
            }
        } elseif ($action === 'edit') {
            $id = intval($_POST['id'] ?? 0);
            $bank_name = $_POST['bank_name'] ?? '';
            $account_number = $_POST['account_number'] ?? '';
            $account_holder = $_POST['account_holder'] ?? '';
            if ($id && $bank_name && $account_number && $account_holder) {
                try {
                    $stmt = $pdo->prepare("UPDATE furn_bank_accounts SET bank_name = ?, account_number = ?, account_holder = ? WHERE id = ?");
                    $stmt->execute([$bank_name, $account_number, $account_holder, $id]);
                    $message = 'Bank account updated successfully!';
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'Error: ' . $e->getMessage();
                    $messageType = 'danger';
                }
            }
        } elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM furn_bank_accounts WHERE id = ?");
                    $stmt->execute([$id]);
                    $message = 'Bank account deleted successfully!';
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'Error: ' . $e->getMessage();
                    $messageType = 'danger';
                }
            }
        }
    }
}

// Fetch all bank accounts
$stmt = $pdo->query("SELECT * FROM furn_bank_accounts ORDER BY bank_name");
$banks = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Accounts - SmartWorkshop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/css/admin-responsive.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <button class="mobile-menu-toggle" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>
    <div class="sidebar-overlay"></div>
    <?php include_once __DIR__ . '/../../includes/admin_sidebar.php'; ?>
    <?php $pageTitle = 'Bank Accounts'; include_once __DIR__ . '/../../includes/admin_header.php'; ?>
    
    <div class="main-content">
        <div class="page-header" style="background: white; padding: 25px; border-radius: 15px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
            <h1 style="font-size: 28px; font-weight: 700; color: #2c3e50; margin-bottom: 10px;">
                <i class="fas fa-university me-2"></i>Bank Accounts Management
            </h1>
            <p style="color: #7f8c8d; font-size: 15px;">Manage company bank accounts for customer payments</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-4">
                <div style="background: white; padding: 25px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
                    <h3 style="margin-bottom: 20px; color: #2c3e50;"><i class="fas fa-plus me-2"></i>Add Bank Account</h3>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? $csrf_token); ?>">
                        
                        <div class="mb-3">
                            <label class="form-label" style="font-weight: 600;">Bank Name *</label>
                            <input type="text" name="bank_name" class="form-control" required style="padding: 10px; border: 2px solid #e9ecef; border-radius: 8px;">
                        </div>

                        <div class="mb-3">
                            <label class="form-label" style="font-weight: 600;">Account Number *</label>
                            <input type="text" name="account_number" class="form-control" required style="padding: 10px; border: 2px solid #e9ecef; border-radius: 8px;">
                        </div>

                        <div class="mb-3">
                            <label class="form-label" style="font-weight: 600;">Account Holder *</label>
                            <input type="text" name="account_holder" class="form-control" required style="padding: 10px; border: 2px solid #e9ecef; border-radius: 8px;">
                        </div>

                        <button type="submit" style="background: linear-gradient(135deg, #4a2c2a 0%, #3d1f1d 100%); color: white; padding: 12px 30px; border: none; border-radius: 8px; font-weight: 600; width: 100%; cursor: pointer;">
                            <i class="fas fa-save me-2"></i>Add Bank Account
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-lg-8">
                <div style="background: white; padding: 25px; border-radius: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
                    <h3 style="margin-bottom: 20px; color: #2c3e50;"><i class="fas fa-list me-2"></i>Bank Accounts List</h3>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead style="background: #f8f9fa;">
                                <tr>
                                    <th>Bank Name</th>
                                    <th>Account Number</th>
                                    <th>Account Holder</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($banks)): ?>
                                    <tr><td colspan="5" class="text-center text-muted py-4">No bank accounts found</td></tr>
                                <?php else: ?>
                                    <?php foreach ($banks as $bank): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($bank['bank_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($bank['account_number']); ?></td>
                                            <td><?php echo htmlspecialchars($bank['account_holder']); ?></td>
                                            <td>
                                                <?php if ($bank['is_active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-primary" onclick="editBank(<?php echo htmlspecialchars(json_encode($bank)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this bank account?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?php echo $bank['id'] ?? ''; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? $csrf_token); ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Bank Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? $csrf_token); ?>">
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Bank Name *</label>
                            <input type="text" name="bank_name" id="edit_bank_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Account Number *</label>
                            <input type="text" name="account_number" id="edit_account_number" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Account Holder *</label>
                            <input type="text" name="account_holder" id="edit_account_holder" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editBank(bank) {
            document.getElementById('edit_id').value = bank.id;
            document.getElementById('edit_bank_name').value = bank.bank_name;
            document.getElementById('edit_account_number').value = bank.account_number;
            document.getElementById('edit_account_holder').value = bank.account_holder;
            new bootstrap.Modal(document.getElementById('editModal')).show();
        }
    </script>
    <script src="<?php echo BASE_URL; ?>/public/assets/js/admin-mobile.js"></script>
</body>
</html>
