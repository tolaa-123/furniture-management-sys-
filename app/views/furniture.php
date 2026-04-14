<?php
// furniture.php - Furniture catalog page
require_once '../config/config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Furniture Collection - Custom Furniture ERP</title>
    <?php include '../app/includes/header_links.php'; ?>
</head>
<body>
    <?php include '../app/includes/header.php'; ?>

    <!-- Furniture Page Content -->
    <div class="container my-5">
        <div class="row">
            <div class="col-lg-12">
                <!-- Removed broken/duplicate contact info markup above -->
                <h2 class="fw-bold mb-4">Featured <span class="text-primary">Products</span></h2>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100">
                    <img src="https://images.unsplash.com/photo-1556911220-e15b29be8c8f?auto=format&fit=crop&w=600&h=400&q=80" class="card-img-top" alt="Featured Product 1">
                    <div class="card-body">
                        <h5 class="card-title">Modern Sofa</h5>
                        <p class="card-text">A sleek and comfortable sofa perfect for modern living rooms.</p>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="h5 text-primary">ETB 25,000</span>
                            <button class="btn btn-primary" onclick="addToCart('Modern Sofa')">Add to Cart</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <img src="https://images.unsplash.com/photo-1505691938895-1758d7feb511?auto=format&fit=crop&w=600&h=400&q=80" class="card-img-top" alt="Featured Product 2">
                    <div class="card-body">
                        <h5 class="card-title">Wooden Dining Table</h5>
                        <p class="card-text">Handcrafted dining table made from premium hardwood.</p>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="h5 text-primary">ETB 35,000</span>
                            <button class="btn btn-primary" onclick="addToCart('Wooden Dining Table')">Add to Cart</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100">
                    <img src="https://images.unsplash.com/photo-1493663284031-b7e3aefcae8e?auto=format&fit=crop&w=600&h=400&q=80" class="card-img-top" alt="Featured Product 3">
                    <div class="card-body">
                        <h5 class="card-title">King Size Bed</h5>
                        <p class="card-text">Comfortable king-size bed frame with elegant headboard.</p>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="h5 text-primary">ETB 40,000</span>
                            <button class="btn btn-primary" onclick="addToCart('King Size Bed')">Add to Cart</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Contact Information Section -->
    <div class="container my-5">
        <div class="row">
            <div class="col-lg-6 mb-4">
                <h2 class="fw-bold mb-4">Get In <span class="text-primary">Touch</span></h2>
                <form id="contactForm">
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
                <div class="row g-3">
                    <div class="col-12">
                        <div style="display:flex;align-items:center;gap:10px;background:#fff;border-radius:10px;padding:12px;box-shadow:0 1px 6px rgba(0,0,0,.08);">
                            <span style="flex-shrink:0;width:34px;height:34px;border-radius:50%;background:#f3e8d8;display:inline-flex;align-items:center;justify-content:center;">
                                <i class="fas fa-map-marker-alt" style="color:#8B4513;font-size:13px;"></i>
                            </span>
                            <div style="min-width:0;">
                                <div style="font-weight:700;font-size:.82rem;margin-bottom:2px;">Address</div>
                                <div style="font-size:.75rem;line-height:1.4;color:#555;">123 Furniture Street<br>Jima, Ethiopia</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div style="display:flex;align-items:center;gap:10px;background:#fff;border-radius:10px;padding:12px;box-shadow:0 1px 6px rgba(0,0,0,.08);">
                            <span style="flex-shrink:0;width:34px;height:34px;border-radius:50%;background:#f3e8d8;display:inline-flex;align-items:center;justify-content:center;">
                                <i class="fas fa-phone-alt" style="color:#8B4513;font-size:13px;"></i>
                            </span>
                            <div style="min-width:0;">
                                <div style="font-weight:700;font-size:.82rem;margin-bottom:2px;">Phone</div>
                                <div style="font-size:.75rem;line-height:1.4;color:#555;"><a href="tel:+251943778192" style="color:inherit;text-decoration:none;">+251 943 778 192</a><br><a href="tel:+251710766709" style="color:inherit;text-decoration:none;">+251 710 766 709</a></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div style="display:flex;align-items:center;gap:10px;background:#fff;border-radius:10px;padding:12px;box-shadow:0 1px 6px rgba(0,0,0,.08);">
                            <span style="flex-shrink:0;width:34px;height:34px;border-radius:50%;background:#f3e8d8;display:inline-flex;align-items:center;justify-content:center;">
                                <i class="fas fa-envelope" style="color:#8B4513;font-size:13px;"></i>
                            </span>
                            <div style="min-width:0;">
                                <div style="font-weight:700;font-size:.82rem;margin-bottom:2px;">Email</div>
                                <div style="font-size:.95rem;line-height:1.6;color:#555;word-break:break-word;white-space:pre-line;">
                                    <a href="https://mail.google.com/mail/?view=cm&to=derejeayele292@gmail.com" style="color:inherit;text-decoration:none;">derejeayele292@gmail.com</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div style="display:flex;align-items:center;gap:10px;background:#fff;border-radius:10px;padding:12px;box-shadow:0 1px 6px rgba(0,0,0,.08);">
                            <span style="flex-shrink:0;width:34px;height:34px;border-radius:50%;background:#f3e8d8;display:inline-flex;align-items:center;justify-content:center;">
                                <i class="fas fa-clock" style="color:#8B4513;font-size:13px;"></i>
                            </span>
                            <div style="min-width:0;">
                                <div style="font-weight:700;font-size:.82rem;margin-bottom:2px;">Working Hours</div>
                                <div style="font-size:.75rem;line-height:1.4;color:#555;">Mon-Fri: 9am-6pm<br>Sat: 10am-4pm</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../app/includes/footer.php'; ?>
    <?php include '../app/includes/footer_scripts.php'; ?>
    
    <script>
        function addToCart(productName) {
             // Debug line
            
            // Get current cart from localStorage or initialize empty array
            let cart = JSON.parse(localStorage.getItem('cart')) || [];
            
            // Check if product already in cart
            const existingItem = cart.find(item => item.name === productName);
            
            if (existingItem) {
                // Update quantity if already exists
                existingItem.quantity = (existingItem.quantity || 1) + 1;
            } else {
                // Add new item to cart
                cart.push({
                    name: productName,
                    price: 'ETB 25,000', // Default price
                    quantity: 1,
                    addedAt: new Date().toISOString()
                });
            }
            
            // Save cart to localStorage
            localStorage.setItem('cart', JSON.stringify(cart));
            
            // Show success message
            alert(productName + ' has been added to your cart!');
            
            // Update cart count if cart display exists
            updateCartCount();
        }
        
        function updateCartCount() {
            const cart = JSON.parse(localStorage.getItem('cart')) || [];
            const totalItems = cart.reduce((total, item) => total + (item.quantity || 1), 0);
            
            // Update cart count display if element exists
            const cartCountElements = document.querySelectorAll('.cart-count');
            cartCountElements.forEach(element => {
                element.textContent = totalItems;
            });
        }
        
        // Initialize cart count on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateCartCount();
        });
    </script>
</body>
</html>