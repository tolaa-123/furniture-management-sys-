<?php
// contact_page.php - Complete contact page with mobile-friendly design
require_once '../config/config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Custom Furniture ERP</title>
    <?php include '../app/includes/header_links.php'; ?>
    <style>
        .contact-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 0;
            margin: 40px 0;
            border-radius: 15px;
        }
        .contact-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 10px;
            padding: 30px;
            margin: 20px 0;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        .info-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 25px;
            margin: 20px 0;
        }
        .contact-form {
            margin-top: 30px;
        }
        .form-label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 16px;
            transition: border-color 0.3s;
            width: 100%;
            margin-bottom: 20px;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            outline: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            padding: 12px 30px;
            font-size: 16px;
            font-weight: 600;
            transition: transform 0.3s;
            width: 100%;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        .map-container {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .info-item {
            margin-bottom: 18px;
            display: flex;
            align-items: flex-start;
        }
        .info-item i {
            font-size: 18px;
            color: #667eea;
            margin-right: 14px;
            width: 24px;
            flex-shrink: 0;
            margin-top: 3px;
        }
        .info-text {
            flex: 1;
            min-width: 0;
        }
        .info-label {
            font-weight: 600;
            color: #666;
            margin-bottom: 4px;
            font-size: 13px;
        }
        .info-value {
            color: #333;
            font-size: 14px;
            word-break: break-all;
            overflow-wrap: break-word;
        }
        .info-value a {
            word-break: break-all;
            overflow-wrap: break-word;
        }
        @media (max-width: 768px) {
            .contact-section {
                padding: 40px 0;
                margin: 20px 0;
            }
            .contact-card, .info-card {
                margin: 15px 0;
                padding: 20px;
            }
            .form-control {
                font-size: 14px;
                padding: 10px 12px;
            }
            .btn-primary {
                padding: 10px 20px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <?php include '../app/includes/header.php'; ?>
    
    <!-- Contact Section -->
    <div class="contact-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <h1 class="text-center mb-4">Contact Us</h1>
                    <p class="text-center mb-5">We'd love to hear from you! Get in touch with our team.</p>
                </div>
            </div>
            
            <div class="row">
                <div class="col-lg-8">
                    <!-- Contact Form -->
                    <div class="contact-card">
                        <div class="card-body">
                            <h3 class="mb-4">Send Us a Message</h3>
                            
                            <form id="contactForm" onsubmit="sendContactMessage(event)">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="firstName" class="form-label">First Name</label>
                                        <input type="text" class="form-control" id="firstName" name="firstName" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="lastName" class="form-label">Last Name</label>
                                        <input type="text" class="form-control" id="lastName" name="lastName" required>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label for="subject" class="form-label">Subject</label>
                                        <input type="text" class="form-control" id="subject" name="subject" required>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label for="message" class="form-label">Message</label>
                                        <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-paper-plane me-2"></i>
                                            Send Message
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <!-- Contact Information -->
                    <div class="info-card">
                        <div class="card-body">
                            <h4 class="mb-4">Contact Information</h4>
                            
                            <div class="info-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <div class="info-text">
                                    <div class="info-label">Address</div>
                                    <div class="info-value">123 Furniture Street<br>Jima, Ethiopia</div>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <i class="fas fa-phone"></i>
                                <div class="info-text">
                                    <div class="info-label">Phone</div>
                                    <div class="info-value"><a href="tel:+251943778192" style="color:inherit;text-decoration:none;">+251 943 778 192</a><br><a href="tel:+251710766709" style="color:inherit;text-decoration:none;">+251 710 766 709</a></div>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <i class="fas fa-envelope"></i>
                                <div class="info-text">
                                    <div class="info-label">Email</div>
                                    <div class="info-value"><a href="https://mail.google.com/mail/?view=cm&to=derejeayele292@gmail.com" target="_blank" style="color:inherit;text-decoration:none;">derejeayele292@gmail.com</a></div>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <i class="fas fa-clock"></i>
                                <div class="info-text">
                                    <div class="info-label">Working Hours</div>
                                    <div class="info-value">Mon-Fri: 9am-6pm<br>Sat: 10am-4pm</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function sendContactMessage(event) {
            event.preventDefault();
            
            // Get form values
            const form = document.getElementById('contactForm');
            const firstName = form.querySelector('[name="firstName"]').value;
            const lastName = form.querySelector('[name="lastName"]').value;
            const email = form.querySelector('[name="email"]').value;
            const subject = form.querySelector('[name="subject"]').value;
            const message = form.querySelector('[name="message"]').value;
            
            // Validate form
            if (!firstName || !lastName || !email || !subject || !message) {
                alert('Please fill in all required fields.');
                return;
            }
            
            // Validate email format
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(email)) {
                alert('Please enter a valid email address.');
                return;
            }
            
            // Use XMLHttpRequest
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo BASE_URL; ?>/public/api/send_contact.php', true);
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                alert('Thank you for your message! We will get back to you soon.');
                                document.getElementById('contactForm').reset();
                            } else {
                                alert('Error: ' + response.message);
                            }
                        } catch (e) {
                            alert('Response error. Please try again.');
                        }
                    } else {
                        alert('Server error. Please try again.');
                    }
                }
            };
            
            // Send as form data
            const formData = new FormData();
            formData.append('firstName', firstName);
            formData.append('lastName', lastName);
            formData.append('email', email);
            formData.append('subject', subject);
            formData.append('message', message);
            
            xhr.send(formData);
        }
    </script>
    
    <?php include '../app/includes/footer.php'; ?>
</body>
</html>
