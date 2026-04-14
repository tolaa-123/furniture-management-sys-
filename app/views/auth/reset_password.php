<?php
// Session and config already loaded by BaseController
if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION[CSRF_TOKEN_NAME];

// Get flash message
$flashMessage = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);

// Token validity is passed from controller
$isValidToken = $isValidToken ?? false;
$token = $_GET['token'] ?? '';

// Load home page dependencies so it renders correctly behind the modal
if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION[CSRF_TOKEN_NAME];
require_once __DIR__ . '/../../../core/Database.php';
require_once __DIR__ . '/../../../app/models/User.php';
$userModelForHome = new User();
$roles = $userModelForHome->getAllRoles();

// Flag so home.php knows to auto-open the reset modal
$autoOpenResetModal = true;
?>
<?php include __DIR__ . '/../home.php'; ?>

<!-- Reset Password Modal — auto-opened on page load -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content auth-modal-content">
            <div class="modal-header auth-modal-header">
                <div class="w-100 text-center">
                    <div class="auth-modal-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <h5 class="modal-title">Reset Password</h5>
                    <p class="auth-modal-subtitle">Enter your new password below</p>
                </div>
            </div>
            <div class="modal-body auth-modal-body">

                <?php if ($flashMessage): ?>
                <div class="alert alert-<?php echo $flashMessage['type'] === 'success' ? 'success' : 'danger'; ?> mb-3">
                    <i class="fas fa-<?php echo $flashMessage['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                    <?php echo htmlspecialchars($flashMessage['message']); ?>
                </div>
                <?php endif; ?>

                <?php if (!$isValidToken): ?>
                <div class="alert alert-danger mb-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    This password reset link is invalid or has expired. Please request a new one.
                </div>
                <a href="<?php echo BASE_URL; ?>/public/?modal=forgot" class="btn btn-auth-primary w-100">
                    <i class="fas fa-redo me-2"></i>Request New Reset Link
                </a>

                <?php else: ?>
                <div class="alert alert-info border-0 mb-3" style="font-size:.9rem;">
                    <i class="fas fa-info-circle me-2"></i>
                    Create a strong password with at least 8 characters.
                </div>

                <form id="resetPasswordModalForm" method="POST" action="<?php echo BASE_URL; ?>/public/reset-password">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">

                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <div class="input-group-custom">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password"
                                   class="form-control auth-modal-input"
                                   id="rp_password"
                                   name="password"
                                   placeholder="Enter new password"
                                   required minlength="8"
                                   autocomplete="new-password">
                            <i class="fas fa-eye password-toggle" data-target="rp_password"></i>
                        </div>
                        <div class="mt-1" style="font-size:.82rem;">
                            <span>Strength: </span>
                            <span id="rp_strengthText" style="font-weight:600;">Too short</span>
                            <div style="height:4px;background:#e2e8f0;border-radius:2px;margin-top:4px;">
                                <div id="rp_strengthBar" style="height:100%;width:0;border-radius:2px;background:#ef4444;transition:all .3s;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Confirm Password</label>
                        <div class="input-group-custom">
                            <i class="fas fa-check-circle input-icon"></i>
                            <input type="password"
                                   class="form-control auth-modal-input"
                                   id="rp_confirm"
                                   name="confirm_password"
                                   placeholder="Confirm new password"
                                   required minlength="8"
                                   autocomplete="new-password">
                        </div>
                        <div id="rp_matchMsg" style="font-size:.82rem;margin-top:4px;"></div>
                    </div>

                    <button type="submit" class="btn btn-auth-primary w-100" id="rp_submitBtn">
                        <span class="btn-text">Reset Password</span>
                        <span class="btn-spinner spinner-border spinner-border-sm d-none" role="status"></span>
                    </button>
                </form>
                <?php endif; ?>

                <div class="text-center mt-3" style="font-size:.88rem;color:#64748b;">
                    <a href="<?php echo BASE_URL; ?>/public/?modal=login" class="auth-link fw-bold">
                        <i class="fas fa-arrow-left me-1"></i>Back to Login
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-open the reset modal on page load
document.addEventListener('DOMContentLoaded', function() {
    var modal = new bootstrap.Modal(document.getElementById('resetPasswordModal'), { backdrop: 'static' });
    modal.show();
});

// Password strength
var rpPwd = document.getElementById('rp_password');
var rpBar = document.getElementById('rp_strengthBar');
var rpTxt = document.getElementById('rp_strengthText');
if (rpPwd) {
    rpPwd.addEventListener('input', function() {
        var v = this.value, s = 0;
        if (v.length >= 8) s += 25;
        if (v.length >= 12) s += 10;
        if (/[a-z]/.test(v) && /[A-Z]/.test(v)) s += 25;
        if (/\d/.test(v)) s += 20;
        if (/[^a-zA-Z0-9]/.test(v)) s += 20;
        rpBar.style.width = s + '%';
        if (s < 40) { rpBar.style.background = '#ef4444'; rpTxt.textContent = 'Weak'; }
        else if (s < 70) { rpBar.style.background = '#f59e0b'; rpTxt.textContent = 'Medium'; }
        else { rpBar.style.background = '#10b981'; rpTxt.textContent = 'Strong'; }
        checkMatch();
    });
}

// Password match
var rpConf = document.getElementById('rp_confirm');
var rpMsg  = document.getElementById('rp_matchMsg');
function checkMatch() {
    if (!rpConf || !rpConf.value) { rpMsg.textContent = ''; return; }
    if (rpPwd.value !== rpConf.value) {
        rpMsg.textContent = 'Passwords do not match'; rpMsg.style.color = '#ef4444';
    } else {
        rpMsg.textContent = 'Passwords match ✓'; rpMsg.style.color = '#10b981';
    }
}
if (rpConf) rpConf.addEventListener('input', checkMatch);

// Submit loading state
var rpForm = document.getElementById('resetPasswordModalForm');
if (rpForm) {
    rpForm.addEventListener('submit', function(e) {
        if (rpPwd.value !== rpConf.value) {
            e.preventDefault();
            rpMsg.textContent = 'Passwords do not match'; rpMsg.style.color = '#ef4444';
            return;
        }
        var btn = document.getElementById('rp_submitBtn');
        btn.querySelector('.btn-text').classList.add('d-none');
        btn.querySelector('.btn-spinner').classList.remove('d-none');
        btn.disabled = true;
    });
}
</script>
