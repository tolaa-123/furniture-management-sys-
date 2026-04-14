<?php
// register.php - Registration page
require_once '../config/config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Custom Furniture ERP</title>
    <?php include '../app/includes/header_links.php'; ?>
</head>
<body>
    <?php include '../app/includes/header.php'; ?>

    <!-- Register Page Content -->
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card border-0 shadow">
                    <div class="card-header bg-primary text-white text-center py-4">
                        <h3 class="mb-0">Create Your Account</h3>
                        <p class="mb-0">Join us today to start ordering custom furniture</p>
                    </div>
                    <div class="card-body p-5">
                        <form id="registerForm">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="firstName" class="form-label">First Name</label>
                                    <input type="text" class="form-control form-control-lg" id="firstName" name="firstName" placeholder="Enter your first name" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="lastName" class="form-label">Last Name</label>
                                    <input type="text" class="form-control form-control-lg" id="lastName" name="lastName" placeholder="Enter your last name" required>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control form-control-lg" id="email" name="email" placeholder="Enter your email" required>
                            </div>
                            <div class="mb-4">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control form-control-lg" id="phone" name="phone" placeholder="Enter your phone number" required>
                            </div>
                            <div class="mb-4">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control form-control-lg" id="password" name="password" placeholder="Create a password" required>
                            </div>
                            <div class="mb-4">
                                <label for="confirmPassword" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control form-control-lg" id="confirmPassword" name="confirmPassword" placeholder="Confirm your password" required>
                            </div>
                            <div class="mb-4 form-check">
                                <input type="checkbox" class="form-check-input" id="terms" name="terms" value="1" required>
                                <label class="form-check-label" for="terms">I agree to the <a href="#">Terms and Conditions</a> and <a href="#">Privacy Policy</a></label>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">Sign Up</button>
                            </div>
                        </form>
                        <div class="text-center mt-4">
                            <p>Already have an account? <a href="<?php echo BASE_URL; ?>/public/login">Sign In</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../app/includes/footer.php'; ?>
    <?php include '../app/includes/footer_scripts.php'; ?>
    
    <script>
        // Register form submission handling
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form data
            const formData = new FormData(this);
            const firstName = formData.get('firstName');
            const lastName = formData.get('lastName');
            const email = formData.get('email');
            const phone = formData.get('phone');
            const password = formData.get('password');
            const confirmPassword = formData.get('confirmPassword');
            const terms = formData.get('terms');
            
            // Basic validation
            if (!firstName || firstName.trim() === '') {
                alert('Please enter your first name.');
                return;
            }
            
            if (!lastName || lastName.trim() === '') {
                alert('Please enter your last name.');
                return;
            }
            
            if (!email || !isValidEmail(email)) {
                alert('Please enter a valid email address.');
                return;
            }
            
            if (!phone || phone.length < 10) {
                alert('Please enter a valid phone number.');
                return;
            }
            
            if (!password || password.length < 6) {
                alert('Please enter a password with at least 6 characters.');
                return;
            }
            
            if (password !== confirmPassword) {
                alert('Passwords do not match.');
                return;
            }
            
            if (!terms) {
                alert('You must agree to the Terms and Conditions and Privacy Policy.');
                return;
            }
            
            // Add CSRF token
            const csrfToken = '<?php echo $_SESSION["csrf_token"] ?? (session_start() ? $_SESSION["csrf_token"] = bin2hex(random_bytes(32)) : bin2hex(random_bytes(32))); ?>';
            formData.append('csrf_token', csrfToken);
            formData.append('terms', terms ? '1' : '0');
            // Submit to server
            fetch('<?php echo BASE_URL; ?>/public/api/register_standalone.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert('Registration successful! Please check your email to verify your account.');
                    window.location.href = '<?php echo BASE_URL; ?>/public/login';
                } else {
                    alert(data.message || 'Registration failed. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again. Error: ' + error.message);
            });
        });
    </script>
</body>
</html>