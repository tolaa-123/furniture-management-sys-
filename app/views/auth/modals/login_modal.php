<!-- Login Modal -->
<div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content auth-modal-content">
            <div class="modal-header auth-modal-header">
                <div class="w-100 text-center">
                    <div class="auth-modal-icon">
                        <i class="fas fa-couch"></i>
                    </div>
                    <h5 class="modal-title" id="loginModalLabel">Welcome Back</h5>
                    <p class="auth-modal-subtitle">Sign in to continue to SmartWorkshop</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body auth-modal-body">
                <div id="loginModalAlert" class="alert d-none" role="alert"></div>
                
                <form id="loginModalForm" method="POST" action="<?php echo BASE_URL; ?>/public/login" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? ''); ?>">
                    <input type="hidden" name="modal_submit" value="1">
                    <input type="hidden" name="redirect" id="login_redirect_field" value="">
                    
                    <div class="mb-3">
                        <label for="modal_login_email" class="form-label">Email Address</label>
                        <div class="input-group-custom">
                            <i class="fas fa-envelope input-icon"></i>
                            <input type="email" 
                                   class="form-control auth-modal-input" 
                                   id="modal_login_email" 
                                   name="email" 
                                   placeholder="Enter your email" 
                                   required 
                                   autocomplete="off">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="modal_login_password" class="form-label">Password</label>
                        <div class="input-group-custom">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password" 
                                   class="form-control auth-modal-input" 
                                   id="modal_login_password" 
                                   name="password" 
                                   placeholder="Enter your password" 
                                   required 
                                   autocomplete="new-password">
                            <i class="fas fa-eye password-toggle" data-target="modal_login_password"></i>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="modal_remember_me" name="remember_me">
                            <label class="form-check-label" for="modal_remember_me">Remember me</label>
                        </div>
                        <a href="#" class="auth-link" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal" data-bs-dismiss="modal">
                            Forgot Password?
                        </a>
                    </div>

                    <button type="submit" class="btn btn-auth-primary w-100 mb-3" id="loginModalBtn">
                        <span class="btn-text">Sign In</span>
                        <span class="btn-spinner spinner-border spinner-border-sm d-none" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </span>
                    </button>

                    <div class="text-center">
                        <small class="text-muted">
                            Don't have an account? 
                            <a href="#" class="auth-link fw-bold" data-bs-toggle="modal" data-bs-target="#registerModal" data-bs-dismiss="modal">
                                Create Account
                            </a>
                        </small>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
