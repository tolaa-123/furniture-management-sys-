<?php
// Session and config already loaded by BaseController
if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION[CSRF_TOKEN_NAME];

// Get errors and old input
$errors = $_SESSION['registration_errors'] ?? [];
unset($_SESSION['registration_errors']);
$oldInput = $_SESSION['old_input'] ?? [];
unset($_SESSION['old_input']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - SmartWorkshop</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #4a3428;
            --primary-dark: #3a2618;
            --secondary: #6b4e3d;
            --success: #10b981;
            --danger: #ef4444;
            --beige: #e8dfd5;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f1ed 0%, #e8dfd5 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        
        body::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            background: repeating-linear-gradient(
                45deg,
                transparent,
                transparent 10px,
                rgba(74, 52, 40, 0.02) 10px,
                rgba(74, 52, 40, 0.02) 20px
            );
            animation: moveBackground 30s linear infinite;
        }
        
        @keyframes moveBackground {
            0% { transform: translate(0, 0); }
            100% { transform: translate(20px, 20px); }
        }
        
        .auth-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 600px;
            animation: slideUp 0.6s ease-out;
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .auth-card {
            background: white;
            backdrop-filter: blur(20px);
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(74, 52, 40, 0.15);
            overflow: hidden;
        }
        
        .auth-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            padding: 2rem 2rem;
            text-align: center;
            color: white;
            position: relative;
        }
        
        .auth-header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 0;
        }
        
        .logo-icon {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        
        .auth-header h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .auth-body {
            padding: 2rem;
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .auth-body::-webkit-scrollbar {
            width: 8px;
        }
        
        .auth-body::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }
        
        .auth-body::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
        }
        
        .form-label {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .required {
            color: var(--danger);
        }
        
        .input-group-custom {
            position: relative;
            margin-bottom: 1.25rem;
        }
        
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--secondary);
            font-size: 1rem;
            z-index: 10;
        }
        
        .form-control {
            padding: 0.875rem 1rem 0.875rem 3rem;
            border: 1px solid #d4c5b9;
            border-radius: 25px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: #f9f6f3;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.15rem rgba(74, 52, 40, 0.1);
            outline: none;
            background: white;
        }
        
        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #94a3b8;
            z-index: 10;
        }
        
        .password-toggle:hover {
            color: var(--primary);
        }
        
        .password-strength {
            margin-top: 0.5rem;
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0;
            transition: all 0.3s ease;
        }
        
        .password-strength-bar.weak { width: 33%; background: var(--danger); }
        .password-strength-bar.medium { width: 66%; background: var(--warning); }
        .password-strength-bar.strong { width: 100%; background: var(--success); }
        
        .form-check {
            margin-bottom: 1.5rem;
        }
        
        .form-check-input {
            width: 1.25rem;
            height: 1.25rem;
            border: 2px solid #e2e8f0;
            cursor: pointer;
        }
        
        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-register {
            width: 100%;
            padding: 0.95rem;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border: none;
            border-radius: 25px;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(74, 52, 40, 0.3);
        }
        
        .btn-register:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(74, 52, 40, 0.4);
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
        }
        
        .alert {
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border: none;
        }
        
        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            color: #64748b;
        }
        
        .login-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }
        
        .spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="logo-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h1>Create Your Account</h1>
                <p>Join SmartWorkshop today</p>
            </div>
            
            <div class="auth-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Please fix the following:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form id="registerForm" method="POST" action="<?php echo BASE_URL; ?>/public/register">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                    <input type="hidden" name="full_name" id="full_name_hidden">
                    <input type="hidden" name="role_id" value="4">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">First Name <span class="required">*</span></label>
                            <div class="input-group-custom">
                                <i class="fas fa-user input-icon"></i>
                                <input type="text" class="form-control" id="first_name" placeholder="John" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name <span class="required">*</span></label>
                            <div class="input-group-custom">
                                <i class="fas fa-user input-icon"></i>
                                <input type="text" class="form-control" id="last_name" placeholder="Doe" required>
                            </div>
                        </div>
                    </div>

                    <label class="form-label">Email Address <span class="required">*</span></label>
                    <div class="input-group-custom">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" class="form-control" name="email" placeholder="your@email.com" required>
                    </div>

                    <label class="form-label">Phone Number</label>
                    <div class="input-group-custom">
                        <i class="fas fa-phone input-icon"></i>
                        <input type="tel" class="form-control" name="phone" placeholder="+251 XXX XXX XXX">
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Password <span class="required">*</span></label>
                            <div class="input-group-custom">
                                <i class="fas fa-lock input-icon"></i>
                                <input type="password" class="form-control" id="password" name="password" placeholder="Create password" required>
                                <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                            </div>
                            <div class="password-strength">
                                <div class="password-strength-bar" id="strengthBar"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm Password <span class="required">*</span></label>
                            <div class="input-group-custom">
                                <i class="fas fa-lock input-icon"></i>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm password" required>
                                <i class="fas fa-eye password-toggle" id="toggleConfirm"></i>
                            </div>
                        </div>
                    </div>

                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                        <label class="form-check-label" for="terms">
                            I agree to the <a href="#" class="text-primary">Terms & Conditions</a>
                        </label>
                    </div>

                    <button type="submit" class="btn-register" id="registerBtn">
                        <span id="btnText">Create Account</span>
                        <span id="btnSpinner" class="spinner" style="display: none;"></span>
                    </button>
                </form>

                <div class="login-link">
                    Already have an account? <a href="<?php echo BASE_URL; ?>/public/login">Sign In</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password toggles
        document.getElementById('togglePassword').addEventListener('click', function() {
            const input = document.getElementById('password');
            input.type = input.type === 'password' ? 'text' : 'password';
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        document.getElementById('toggleConfirm').addEventListener('click', function() {
            const input = document.getElementById('confirm_password');
            input.type = input.type === 'password' ? 'text' : 'password';
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        // Password strength
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('strengthBar');
            
            let strength = 0;
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            
            strengthBar.className = 'password-strength-bar';
            if (strength <= 2) strengthBar.classList.add('weak');
            else if (strength <= 3) strengthBar.classList.add('medium');
            else strengthBar.classList.add('strong');
        });

        // Form submission
        document.getElementById('registerForm').addEventListener('submit', function() {
            const fn = document.getElementById('first_name').value.trim();
            const ln = document.getElementById('last_name').value.trim();
            document.getElementById('full_name_hidden').value = (fn + ' ' + ln).trim();
            
            const btn = document.getElementById('registerBtn');
            btn.disabled = true;
            document.getElementById('btnText').style.display = 'none';
            document.getElementById('btnSpinner').style.display = 'inline-block';
        });
    </script>
</body>
</html>
