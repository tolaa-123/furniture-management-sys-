<?php
// contact.php - Contact page
require_once '../config/config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Custom Furniture ERP</title>
    <?php include '../app/includes/header_links.php'; ?>
</head>
<body>
    <?php include '../app/includes/header.php'; ?>

    <!-- Contact Page Content -->
    <div class="container my-5">
        <div class="row">
            <div class="col-lg-12">
                <h1 class="display-4 fw-bold mb-4">Get In <span class="text-primary">Touch</span></h1>
                <p class="lead">We'd love to hear from you. Send us a message and we'll respond as soon as possible.</p>
            </div>
        </div>

        <div class="row mt-5">
            <div class="col-lg-6 mb-5 mb-lg-0">
                <h2 class="fw-bold mb-4">Send Us a Message</h2>
                <form id="contactForm" novalidate>
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
                    <div class="mb-3">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="subject" class="form-label">Subject</label>
                        <input type="text" class="form-control" id="subject" name="subject" required>
                    </div>
                    <div class="mb-3">
                        <label for="message" class="form-label">Message</label>
                        <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Send Message</button>
                    <div id="contactAlert" class="d-none" role="alert"></div>
                </form>
            </div>
            <div class="col-lg-6">
                <h2 class="fw-bold mb-4">Contact <span class="text-primary">Information</span></h2>

                <style>
                .contact-info { display:flex; flex-direction:column; gap:1rem; }
                .ci-item { display:flex; gap:1rem; align-items:flex-start; background:#fff; border-radius:10px; padding:14px 16px; box-shadow:0 1px 6px rgba(0,0,0,.08); }
                .ci-icon { flex-shrink:0; width:36px; height:36px; border-radius:50%; background:#f3e8d8; display:flex; align-items:center; justify-content:center; margin-top:2px; }
                .ci-icon i { color:#8B4513; font-size:14px; }
                .ci-body { flex:1; min-width:0; }
                .ci-label { font-weight:700; font-size:.85rem; margin-bottom:3px; color:#333; }
                .ci-value { font-size:.84rem; line-height:1.6; color:#555; word-break:break-word; overflow-wrap:break-word; }
                .ci-value a { color:inherit; text-decoration:none; word-break:break-word; overflow-wrap:break-word; }
                @media (max-width: 768px) {
                  .ci-item { padding: 12px; gap: 0.75rem; }
                  .ci-icon { width: 32px; height: 32px; font-size: 0.9rem; }
                  .ci-value { font-size: 0.8rem; }
                }
                </style>

                <div class="contact-info">
                    <div class="ci-item">
                        <div class="ci-icon"><i class="fas fa-map-marker-alt"></i></div>
                        <div class="ci-body">
                            <div class="ci-label">Address</div>
                            <div class="ci-value">123 Furniture Street, Jima, Ethiopia</div>
                        </div>
                    </div>
                    <div class="ci-item">
                        <div class="ci-icon"><i class="fas fa-phone-alt"></i></div>
                        <div class="ci-body">
                            <div class="ci-label">Phone</div>
                            <div class="ci-value">
                                <a href="tel:+251943778192">+251 943 778 192</a><br>
                                <a href="tel:+251710766709">+251 710 766 709</a>
                            </div>
                        </div>
                    </div>
                    <div class="ci-item">
                        <div class="ci-icon"><i class="fas fa-envelope"></i></div>
                        <div class="ci-body">
                            <div class="ci-label">Email</div>
                            <div class="ci-value">
                                <a href="https://mail.google.com/mail/?view=cm&to=derejeayele292@gmail.com" target="_blank">derejeayele292@gmail.com</a>
                            </div>
                        </div>
                    </div>
                    <div class="ci-item">
                        <div class="ci-icon"><i class="fas fa-clock"></i></div>
                        <div class="ci-body">
                            <div class="ci-label">Working Hours</div>
                            <div class="ci-value">Mon-Fri: 9am-6pm<br>Sat: 10am-4pm</div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <?php include '../app/includes/footer.php'; ?>
    <?php include '../app/includes/footer_scripts.php'; ?>
    
    <script>
        document.getElementById('contactForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const btn = this.querySelector('button[type="submit"]');
            const alertBox = document.getElementById('contactAlert');
            const emailInput = document.getElementById('email');
            const email = emailInput.value.trim();
            
            // Debug: log everything
            console.log('=== DEBUG CONTACT FORM ===');
            console.log('Email value:', email);
            console.log('Email length:', email.length);
            console.log('Email charCodes:', Array.from(email).map(c => c.charCodeAt(0)));
            
            // Validate email with regex
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const regexTest = emailRegex.test(email);
            console.log('Regex test result:', regexTest);
            
            if (!regexTest) {
                console.log('Email failed validation');
                console.log('Testing parts:');
                const parts = email.split('@');
                console.log('Parts before @:', parts[0]);
                console.log('Parts after @:', parts[1]);
                if (parts.length === 2) {
                    const domainParts = parts[1].split('.');
                    console.log('Domain parts:', domainParts);
                }
                
                alertBox.className = 'alert alert-danger mt-3';
                alertBox.textContent = 'Please enter a valid email address. Current value: "' + email + '"';
                emailInput.focus();
                return;
            }
            
            console.log('Email passed validation, proceeding...');

            btn.disabled = true;
            btn.textContent = 'Sending...';
            alertBox.className = 'd-none';

            try {
                const res = await fetch('<?= BASE_URL ?>/public/api/send_contact.php', {
                    method: 'POST',
                    body: new FormData(this)
                });
                const data = await res.json();
                console.log('Server response:', data);

                alertBox.className = data.success
                    ? 'alert alert-success mt-3'
                    : 'alert alert-danger mt-3';
                alertBox.textContent = data.message;

                if (data.success) {
                    this.reset();
                    setTimeout(() => { alertBox.className = 'd-none'; }, 4000);
                }
            } catch (err) {
                console.error('Fetch error:', err);
                alertBox.className = 'alert alert-danger mt-3';
                alertBox.textContent = 'Network error. Please try again.';
                setTimeout(() => { alertBox.className = 'd-none'; }, 4000);
            } finally {
                btn.disabled = false;
                btn.textContent = 'Send Message';
            }
        });
    </script>
</body>
</html>