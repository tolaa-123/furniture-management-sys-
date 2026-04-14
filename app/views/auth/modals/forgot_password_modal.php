<!-- Forgot Password Modal -->
<div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-labelledby="forgotPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content auth-modal-content">
            <div class="modal-header auth-modal-header">
                <div class="w-100 text-center">
                    <div class="auth-modal-icon">
                        <i class="fas fa-key"></i>
                    </div>
                    <h5 class="modal-title" id="forgotPasswordModalLabel">Forgot Password?</h5>
                    <p class="auth-modal-subtitle">No worries, we'll send you reset instructions</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body auth-modal-body">
                <div id="forgotPasswordModalAlert" class="alert d-none" role="alert"></div>
                
                <div class="alert alert-info border-0 mb-3">
                    <i class="fas fa-info-circle me-2"></i>
                    Enter your email address and we'll send you a link to reset your password.
                </div>
                
                <form id="forgotPasswordModalForm" method="POST" action="<?php echo BASE_URL; ?>/public/forgot-password">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? ''); ?>">
                    <input type="hidden" name="modal_submit" value="1">
                    
                    <div class="mb-3">
                        <label for="modal_forgot_email" class="form-label">Email Address</label>
                        <div class="input-group-custom">
                            <i class="fas fa-envelope input-icon"></i>
                            <input type="email" 
                                   class="form-control auth-modal-input" 
                                   id="modal_forgot_email" 
                                   name="email" 
                                   placeholder="Enter your registered email" 
                                   required 
                                   autocomplete="email">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-auth-primary w-100 mb-3" id="forgotPasswordModalBtn">
                        <span class="btn-text">Send Reset Link</span>
                        <span class="btn-spinner spinner-border spinner-border-sm d-none" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </span>
                    </button>

                    <div class="text-center mb-3">
                        <small class="text-muted">
                            Remembered your password? 
                            <a href="#" class="auth-link fw-bold" data-bs-toggle="modal" data-bs-target="#loginModal" data-bs-dismiss="modal">
                                Login Now
                            </a>
                        </small>
                    </div>

                    <div class="text-center">
                        <small class="text-muted">
                            Don't have an account? 
                            <a href="#" class="auth-link fw-bold" data-bs-toggle="modal" data-bs-target="#registerModal" data-bs-dismiss="modal">
                                Register Now
                            </a>
                        </small>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
