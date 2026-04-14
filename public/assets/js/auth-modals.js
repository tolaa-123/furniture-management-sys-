/**
 * Authentication Modals JavaScript
 * Handles form submissions, validation, and UI interactions
 */

(function() {
    'use strict';

    // Password Toggle Functionality
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('password-toggle')) {
            const targetId = e.target.getAttribute('data-target');
            const passwordInput = document.getElementById(targetId);
            
            if (passwordInput) {
                const type = passwordInput.type === 'password' ? 'text' : 'password';
                passwordInput.type = type;
                e.target.classList.toggle('fa-eye');
                e.target.classList.toggle('fa-eye-slash');
            }
        }
    });

    // Password Strength Indicator
    const modalPasswordInput = document.getElementById('modal_register_password');
    if (modalPasswordInput) {
        modalPasswordInput.addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('modal_strengthBar');
            
            if (!strengthBar) return;
            
            let strength = 0;
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            strengthBar.className = 'password-strength-bar';
            if (strength <= 2) {
                strengthBar.classList.add('weak');
            } else if (strength <= 3) {
                strengthBar.classList.add('medium');
            } else {
                strengthBar.classList.add('strong');
            }
        });
    }

    // Login Modal Form Submission
    const loginModalForm = document.getElementById('loginModalForm');
    if (loginModalForm) {
        loginModalForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('loginModalBtn');
            const btnText = btn.querySelector('.btn-text');
            const btnSpinner = btn.querySelector('.btn-spinner');
            const alertDiv = document.getElementById('loginModalAlert');
            
            // Show loading state
            btn.disabled = true;
            btnText.classList.add('d-none');
            btnSpinner.classList.remove('d-none');
            alertDiv.classList.add('d-none');
            
            // Submit form via AJAX to API endpoint
            const formData = new FormData(this);
            formData.append('action', 'login');
            
            fetch(window.location.origin + '/NEWkoder/public/api/auth.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    alertDiv.className = 'alert alert-success';
                    alertDiv.textContent = data.message;
                    alertDiv.classList.remove('d-none');
                    
                    // Redirect after short delay
                    setTimeout(function() {
                        window.location.href = data.redirect;
                    }, 500);
                } else {
                    // Show error message
                    alertDiv.className = 'alert alert-danger';
                    alertDiv.textContent = data.message;
                    alertDiv.classList.remove('d-none');
                    
                    // Reset button
                    btn.disabled = false;
                    btnText.classList.remove('d-none');
                    btnSpinner.classList.add('d-none');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Show error message
                alertDiv.className = 'alert alert-danger';
                alertDiv.textContent = 'An error occurred. Please try again.';
                alertDiv.classList.remove('d-none');
                
                // Reset button
                btn.disabled = false;
                btnText.classList.remove('d-none');
                btnSpinner.classList.add('d-none');
            });
        });
    }

    // Register Modal Form Submission
    const registerModalForm = document.getElementById('registerModalForm');
    if (registerModalForm) {
        registerModalForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validate passwords match
            const password = document.getElementById('modal_register_password').value;
            const confirmPassword = document.getElementById('modal_confirm_password').value;
            const alertDiv = document.getElementById('registerModalAlert');
            
            if (password !== confirmPassword) {
                alertDiv.className = 'alert alert-danger';
                alertDiv.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>Passwords do not match.';
                alertDiv.classList.remove('d-none');
                return;
            }
            
            // Validate password strength
            if (password.length < 8) {
                alertDiv.className = 'alert alert-danger';
                alertDiv.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>Password must be at least 8 characters.';
                alertDiv.classList.remove('d-none');
                return;
            }
            
            const btn = document.getElementById('registerModalBtn');
            const btnText = btn.querySelector('.btn-text');
            const btnSpinner = btn.querySelector('.btn-spinner');
            
            // Show loading state
            btn.disabled = true;
            btnText.classList.add('d-none');
            btnSpinner.classList.remove('d-none');
            alertDiv.classList.add('d-none');
            
            // Submit form via AJAX to API endpoint
            const formData = new FormData(this);
            formData.append('action', 'register');
            
            fetch(window.location.origin + '/NEWkoder/public/api/auth.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    alertDiv.className = 'alert alert-success';
                    alertDiv.innerHTML = '<i class="fas fa-check-circle me-2"></i>' + data.message;
                    alertDiv.classList.remove('d-none');
                    
                    // Reset form
                    registerModalForm.reset();
                    
                    // Switch to login modal after 2 seconds
                    setTimeout(function() {
                        const registerModal = bootstrap.Modal.getInstance(document.getElementById('registerModal'));
                        registerModal.hide();
                        
                        const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
                        loginModal.show();
                    }, 2000);
                } else {
                    // Show error message
                    alertDiv.className = 'alert alert-danger';
                    alertDiv.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>' + data.message;
                    alertDiv.classList.remove('d-none');
                    
                    // Reset button
                    btn.disabled = false;
                    btnText.classList.remove('d-none');
                    btnSpinner.classList.add('d-none');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Show error message
                alertDiv.className = 'alert alert-danger';
                alertDiv.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>An error occurred. Please try again.';
                alertDiv.classList.remove('d-none');
                
                // Reset button
                btn.disabled = false;
                btnText.classList.remove('d-none');
                btnSpinner.classList.add('d-none');
            });
        });
    }

    // Forgot Password Modal Form Submission
    const forgotPasswordModalForm = document.getElementById('forgotPasswordModalForm');
    if (forgotPasswordModalForm) {
        forgotPasswordModalForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('forgotPasswordModalBtn');
            const btnText = btn.querySelector('.btn-text');
            const btnSpinner = btn.querySelector('.btn-spinner');
            const alertDiv = document.getElementById('forgotPasswordModalAlert');
            
            // Show loading state
            btn.disabled = true;
            btnText.classList.add('d-none');
            btnSpinner.classList.remove('d-none');
            alertDiv.classList.add('d-none');
            
            // Submit form via AJAX to API endpoint
            const formData = new FormData(this);
            formData.append('action', 'forgot_password');
            
            fetch(window.location.origin + '/NEWkoder/public/api/auth.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Invalid JSON response:', text.substring(0, 500));
                        throw new Error('Server error. Please check the browser console for details.');
                    }
                });
            })
            .then(data => {
                // Reset button
                btn.disabled = false;
                btnText.classList.remove('d-none');
                btnSpinner.classList.add('d-none');
                
                if (data.success) {
                    // Show success message
                    alertDiv.className = 'alert alert-success';
                    alertDiv.innerHTML = '<i class="fas fa-check-circle me-2"></i>' + data.message;
                    alertDiv.classList.remove('d-none');
                    
                    // Reset form
                    forgotPasswordModalForm.reset();
                } else {
                    // Show error message from server
                    alertDiv.className = 'alert alert-danger';
                    alertDiv.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>' + (data.message || 'An error occurred. Please try again.');
                    alertDiv.classList.remove('d-none');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Show error message
                alertDiv.className = 'alert alert-danger';
                alertDiv.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>' + error.message;
                alertDiv.classList.remove('d-none');
                
                // Reset button
                btn.disabled = false;
                btnText.classList.remove('d-none');
                btnSpinner.classList.add('d-none');
            });
        });
    }

    // Auto-hide alerts after 5 seconds
    function autoHideAlerts() {
        const alerts = document.querySelectorAll('.auth-modal-body .alert:not(.d-none)');
        alerts.forEach(alert => {
            setTimeout(function() {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => {
                    alert.classList.add('d-none');
                    alert.style.opacity = '1';
                }, 500);
            }, 5000);
        });
    }

    // Call auto-hide on page load
    autoHideAlerts();

    // Call auto-hide when modals are shown
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('shown.bs.modal', autoHideAlerts);
    });

    // Reset forms when modals are hidden
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('hidden.bs.modal', function() {
            const form = this.querySelector('form');
            if (form) {
                // Reset button states
                const btn = form.querySelector('button[type="submit"]');
                if (btn) {
                    btn.disabled = false;
                    const btnText = btn.querySelector('.btn-text');
                    const btnSpinner = btn.querySelector('.btn-spinner');
                    if (btnText) btnText.classList.remove('d-none');
                    if (btnSpinner) btnSpinner.classList.add('d-none');
                }
                
                // Hide alerts
                const alert = this.querySelector('.alert');
                if (alert) {
                    alert.classList.add('d-none');
                }
            }
        });
    });

})();
