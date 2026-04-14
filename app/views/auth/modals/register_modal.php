<!-- Register Modal -->
<div class="modal fade" id="registerModal" tabindex="-1" aria-labelledby="registerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content auth-modal-content">
            <div class="modal-header auth-modal-header">
                <div class="w-100 text-center">
                    <div class="auth-modal-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <h5 class="modal-title" id="registerModalLabel">Create Account</h5>
                    <p class="auth-modal-subtitle">Join SmartWorkshop today</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body auth-modal-body">
                <div id="registerModalAlert" class="alert d-none" role="alert"></div>
                
                <form id="registerModalForm" method="POST" action="<?php echo BASE_URL; ?>/public/register">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION[CSRF_TOKEN_NAME] ?? ''); ?>">
                    <input type="hidden" name="modal_submit" value="1">
                    <input type="hidden" name="role_id" value="4">
                    
                    <div class="mb-3">
                        <label for="modal_full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                        <div class="input-group-custom">
                            <i class="fas fa-user input-icon"></i>
                            <input type="text" 
                                   class="form-control auth-modal-input" 
                                   id="modal_full_name" 
                                   name="full_name"
                                   placeholder="Enter your full name" 
                                   required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="modal_register_phone" class="form-label">Phone Number</label>
                        <div class="input-group-custom">
                            <i class="fas fa-phone input-icon"></i>
                            <input type="tel" 
                                   class="form-control auth-modal-input" 
                                   id="modal_register_phone" 
                                   name="phone" 
                                   placeholder="+251 XXX XXX XXX">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="modal_register_email" class="form-label">Email Address <span class="text-danger">*</span></label>
                        <div class="input-group-custom">
                            <i class="fas fa-envelope input-icon"></i>
                            <input type="email" 
                                   class="form-control auth-modal-input" 
                                   id="modal_register_email" 
                                   name="email" 
                                   placeholder="your@email.com" 
                                   required 
                                   autocomplete="email">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="modal_register_password" class="form-label">Password <span class="text-danger">*</span></label>
                                <div class="input-group-custom">
                                    <i class="fas fa-lock input-icon"></i>
                                    <input type="password" 
                                           class="form-control auth-modal-input" 
                                           id="modal_register_password" 
                                           name="password" 
                                           placeholder="Create password" 
                                           required>
                                    <i class="fas fa-eye password-toggle" data-target="modal_register_password"></i>
                                </div>
                                <div class="password-strength mt-2">
                                    <div class="password-strength-bar" id="modal_strengthBar"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="modal_confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                <div class="input-group-custom">
                                    <i class="fas fa-lock input-icon"></i>
                                    <input type="password" 
                                           class="form-control auth-modal-input" 
                                           id="modal_confirm_password" 
                                           name="confirm_password" 
                                           placeholder="Confirm password" 
                                           required>
                                    <i class="fas fa-eye password-toggle" data-target="modal_confirm_password"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="modal_terms" name="terms" required>
                        <label class="form-check-label" for="modal_terms">
                            I agree to the <a href="#" class="auth-link">Terms & Conditions</a>
                        </label>
                    </div>

                    <button type="submit" class="btn btn-auth-primary w-100 mb-3" id="registerModalBtn">
                        <span class="btn-text">Create Account</span>
                        <span class="btn-spinner spinner-border spinner-border-sm d-none" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </span>
                    </button>

                    <div class="text-center">
                        <small class="text-muted">
                            Already have an account? 
                            <a href="#" class="auth-link fw-bold" data-bs-toggle="modal" data-bs-target="#loginModal" data-bs-dismiss="modal">
                                Sign In
                            </a>
                        </small>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
