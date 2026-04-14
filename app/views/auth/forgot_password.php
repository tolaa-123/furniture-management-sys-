<?php
// Session and config already loaded by BaseController
if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION[CSRF_TOKEN_NAME];

// Get flash message
$flashMessage = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - SmartWorkshop</title>
    
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
            --warning: #f59e0b;
            --dark: #2d1f16;
            --light: #f5f1ed;
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
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(74,52,40,0.06) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: moveBackground 20s linear infinite;
        }
        
        @keyframes moveBackground {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }
        
        .auth-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 480px;
            animation: slideUp 0.6s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
            padding: 2.5rem 2rem;
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
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .auth-header p {
            font-size: 0.95rem;
            opacity: 0.9;
        }
        
        .auth-body {
            padding: 2.5rem 2rem;
        }
        
        .info-box {
            background: linear-gradient(135deg, #f9f6f3 0%, #f5f1ed 100%);
            border-left: 4px solid var(--primary);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: start;
            gap: 0.75rem;
        }
        
        .info-box i {
            color: var(--primary);
            font-size: 1.25rem;
            margin-top: 0.125rem;
        }
        
        .info-box p {
            margin: 0;
            color: var(--dark);
            font-size: 0.9rem;
            line-height: 1.5;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .input-group-custom {
            position: relative;
            margin-bottom: 1.5rem;
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
        
        .btn-reset {
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
            margin-bottom: 1rem;
        }
        
        .btn-reset:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(74, 52, 40, 0.4);
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
        }
        
        .btn-reset:active {
            transform: translateY(0);
        }
        
        .btn-reset:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-back {
            width: 100%;
            padding: 0.95rem;
            background: white;
            border: 1px solid #d4c5b9;
            border-radius: 25px;
            color: var(--dark);
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-back:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: #f9f6f3;
        }
        
        .divider {
            text-align: center;
            margin: 1.5rem 0;
            position: relative;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 100%;
            height: 1px;
            background: #e2e8f0;
        }
        
        .divider span {
            background: rgba(255, 255, 255, 0.98);
            padding: 0 1rem;
            position: relative;
            color: #94a3b8;
            font-size: 0.9rem;
        }
        
        .register-link {
            text-align: center;
            margin-top: 1.5rem;
            color: #64748b;
        }
        
        .register-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }
        
        .register-link a:hover {
            color: var(--primary-dark);
        }
        
        .alert {
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border: none;
            animation: slideDown 0.3s ease-out;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
        
        @media (max-width: 576px) {
            .auth-header {
                padding: 2rem 1.5rem;
            }
            
            .auth-header h1 {
                font-size: 1.5rem;
            }
            
            .logo-icon {
                width: 70px;
                height: 70px;
                font-size: 2rem;
            }
            
            .auth-body {
                padding: 2rem 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="logo-icon">
                    <i class="fas fa-key"></i>
                </div>
                <h1>Forgot Password?</h1>
                <p>No worries, we'll send you reset instructions</p>
            </div>
            
            <div class="auth-body">
                <?php if ($flashMessage): ?>
                    <div class="alert alert-<?php echo $flashMessage['type'] === 'success' ? 'success' : 'danger'; ?>">
                        <i class="fas fa-<?php echo $flashMessage['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
                        <?php echo htmlspecialchars($flashMessage['message']); ?>
                    </div>
                <?php endif; ?>

                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <p>Enter your email address and we'll send you a link to reset your password.</p>
                </div>

                <form id="forgotForm" method="POST" action="<?php echo BASE_URL; ?>/public/forgot-password">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf); ?>">
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group-custom">
                            <i class="fas fa-envelope input-icon"></i>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   placeholder="Enter your registered email" 
                                   required 
                                   autocomplete="email">
                        </div>
                    </div>

                    <button type="submit" class="btn-reset" id="resetBtn">
                        <span id="btnText">Send Reset Link</span>
                        <span id="btnSpinner" class="spinner" style="display: none;"></span>
                    </button>

                    <a href="<?php echo BASE_URL; ?>/public/login" class="btn-back">
                        <i class="fas fa-arrow-left me-2"></i>Back to Login
                    </a>
                </form>

                <div class="divider">
                    <span>or</span>
                </div>

                <div class="register-link">
                    Don't have an account? <a href="<?php echo BASE_URL; ?>/public/register">Create Account</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form submission with loading state
        document.getElementById('forgotForm').addEventListener('submit', function(e) {
            const resetBtn = document.getElementById('resetBtn');
            const btnText = document.getElementById('btnText');
            const btnSpinner = document.getElementById('btnSpinner');
            
            resetBtn.disabled = true;
            btnText.style.display = 'none';
            btnSpinner.style.display = 'inline-block';
        });

        // Auto-hide alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>
