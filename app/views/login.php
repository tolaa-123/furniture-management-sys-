<?php
// login.php - Login page
require_once '../config/config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Custom Furniture ERP</title>
    <?php include '../app/includes/header_links.php'; ?>
</head>
<body>
    <?php include '../app/includes/header.php'; ?>

    <!-- Login Page Content -->
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card border-0 shadow">
                    <div class="card-header bg-primary text-white text-center py-4">
                        <h3 class="mb-0">Sign In to Your Account</h3>
                        <p class="mb-0">Welcome back! Please login to your account</p>
                    </div>
                    <div class="card-body p-5">
                        <form id="loginForm">
                            <div class="mb-4">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control form-control-lg" id="email" name="email" placeholder="Enter your email" required>
                            </div>
                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control form-control-lg" id="password" name="password" placeholder="Enter your password" required>
                            </div>
                            <div class="mb-4 form-check">
                                <input type="checkbox" class="form-check-input" id="rememberMe">
                                <label class="form-check-label" for="rememberMe">Remember me</label>
                                <a href="#" class="float-end">Forgot Password?</a>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">Sign In</button>
                            </div>
                        </form>
                        <div class="text-center mt-4">
                            <p>Don't have an account? <a href="<?php echo BASE_URL; ?>/public/register">Sign Up</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../app/includes/footer.php'; ?>
    <?php include '../app/includes/footer_scripts.php'; ?>
    
    <script>
        // Login form submission handling
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Get form data
            const formData = new FormData(this);
            const email = formData.get('email');
            const password = formData.get('password');
            // Basic validation
            if (!email || !isValidEmail(email)) {
                alert('Please enter a valid email address.');
                return false;
            }
            
            if (!password || password.length < 6) {
                alert('Please enter a password with at least 6 characters.');
                return false;
            }
            
            // Add CSRF token
            const csrfToken = '<?php echo $_SESSION["csrf_token"] ?? (session_start() ? $_SESSION["csrf_token"] = bin2hex(random_bytes(32)) : bin2hex(random_bytes(32))); ?>';
            formData.append('csrf_token', csrfToken);
            
            // Submit to server
            fetch('<?php echo BASE_URL; ?>/public/api/login_standalone.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert('Login successful! Redirecting...');
                    window.location.href = data.redirect;
                } else {
                    alert(data.message || 'Login failed. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again. Error: ' + error.message);
            });
            
            return false;
        });
        
        function isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }
    </script>
</body>
</html>