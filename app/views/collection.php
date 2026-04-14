<?php
// collection.php - Dynamic furniture collection page
require_once '../config/config.php';

// Collection data
$collections = [
    'sofa' => [
        'title' => 'Sofa Collection',
        'description' => 'Discover our comfortable and stylish sofa collection',
        'headerImage' => 'https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=1200&q=80',
        'products' => [
            ['name' => 'Modern L-Shaped Sofa', 'price' => 'ETB 45,000', 'image' => 'https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=600&q=80'],
            ['name' => 'Classic Three-Seater', 'price' => 'ETB 38,000', 'image' => 'https://images.unsplash.com/photo-1519710164239-da123dc03ef4?auto=format&fit=crop&w=600&q=80'],
            ['name' => 'Luxury Recliner', 'price' => 'ETB 52,000', 'image' => 'https://images.unsplash.com/photo-1460518451285-97b6aa326961?auto=format&fit=crop&w=600&q=80'],
            ['name' => 'Corner Sofa', 'price' => 'ETB 42,000', 'image' => 'https://images.unsplash.com/photo-1515378791036-0648a3ef77b2?auto=format&fit=crop&w=600&q=80'],
            ['name' => 'Velvet Chesterfield', 'price' => 'ETB 48,000', 'image' => 'https://images.unsplash.com/photo-1512918728675-ed5a9ecdebfd?auto=format&fit=crop&w=600&q=80'],
            ['name' => 'Minimalist Sofa', 'price' => 'ETB 35,000', 'image' => 'https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=600&q=80'] // Repeats first for 6th item
        ]
    ],
    'bed' => [
        'title' => 'Bed Collection',
        'description' => 'Explore our luxurious and comfortable bed collection',
        'headerImage' => 'https://picsum.photos/1200/400?random=2',
        'products' => [
            ['name' => 'King Size Bed Frame', 'price' => 'ETB 65,000', 'image' => 'https://picsum.photos/600/400?random=201'],
            ['name' => 'Queen Size Platform', 'price' => 'ETB 48,000', 'image' => 'https://picsum.photos/600/400?random=202'],
            ['name' => 'Bunk Bed Set', 'price' => 'ETB 35,000', 'image' => 'https://picsum.photos/600/400?random=203'],
            ['name' => 'Upholstered Headboard', 'price' => 'ETB 28,000', 'image' => 'https://picsum.photos/600/400?random=204'],
            ['name' => 'Storage Bed Frame', 'price' => 'ETB 55,000', 'image' => 'https://picsum.photos/600/400?random=205'],
            ['name' => 'Four-Poster Bed', 'price' => 'ETB 72,000', 'image' => 'https://picsum.photos/600/400?random=206']
        ]
    ],
    'table' => [
        'title' => 'Table Collection',
        'description' => 'Browse our elegant dining and work tables',
        'headerImage' => 'https://picsum.photos/1200/400?random=3',
        'products' => [
            ['name' => 'Oak Dining Table', 'price' => 'ETB 38,000', 'image' => 'https://picsum.photos/600/400?random=301'],
            ['name' => 'Glass Coffee Table', 'price' => 'ETB 22,000', 'image' => 'https://picsum.photos/600/400?random=302'],
            ['name' => 'Executive Desk', 'price' => 'ETB 45,000', 'image' => 'https://picsum.photos/600/400?random=303'],
            ['name' => 'Round Dining Set', 'price' => 'ETB 58,000', 'image' => 'https://picsum.photos/600/400?random=304'],
            ['name' => 'Conference Table', 'price' => 'ETB 85,000', 'image' => 'https://picsum.photos/600/400?random=305'],
            ['name' => 'Side Table Set', 'price' => 'ETB 18,000', 'image' => 'https://picsum.photos/600/400?random=306']
        ]
    ],
    'chair' => [
        'title' => 'Chair Collection',
        'description' => 'Find your perfect seating solution in our chair collection',
        'headerImage' => 'https://picsum.photos/1200/400?random=4',
        'products' => [
            ['name' => 'Executive Office Chair', 'price' => 'ETB 28,000', 'image' => 'https://picsum.photos/600/400?random=401'],
            ['name' => 'Dining Chair Set', 'price' => 'ETB 15,000', 'image' => 'https://picsum.photos/600/400?random=402'],
            ['name' => 'Lounge Chair', 'price' => 'ETB 22,000', 'image' => 'https://picsum.photos/600/400?random=403'],
            ['name' => 'Bar Stool', 'price' => 'ETB 8,000', 'image' => 'https://picsum.photos/600/400?random=404'],
            ['name' => 'Rocking Chair', 'price' => 'ETB 18,000', 'image' => 'https://picsum.photos/600/400?random=405'],
            ['name' => 'Gaming Chair', 'price' => 'ETB 32,000', 'image' => 'https://picsum.photos/600/400?random=406']
        ]
    ]
];

// Get collection type from router
$collection = $collectionType ?? $_GET['type'] ?? 'sofa';

// Validate collection type
if (!isset($collections[$collection])) {
    $collection = 'sofa'; // Default to sofa
}

$currentCollection = $collections[$collection];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($currentCollection['title']); ?> - Custom Furniture ERP</title>
    <?php include '../app/includes/header_links.php'; ?>
    <style>
        .collection-header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 60px 0;
            margin-bottom: 40px;
        }
        .product-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            border-radius: 15px;
            overflow: hidden;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .product-image {
            height: 250px;
            object-fit: cover;
            width: 100%;
        }
        .price-tag {
            background: #e74c3c;
            color: white;
            padding: 8px 15px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 14px;
        }
        .breadcrumb {
            background: #f8f9fa;
            padding: 15px 0;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        /* Basic Dark Mode Styles */
        body.dark-mode {
            background-color: #1a1a1a;
            color: #ffffff;
        }
        body.dark-mode .collection-header {
            background: linear-gradient(135deg, #1a1a1a 0%, #2c2c2c 100%);
        }
        body.dark-mode .breadcrumb {
            background: #2c2c2c;
            color: #ffffff;
        }
        body.dark-mode .breadcrumb a {
            color: #3498db;
        }
        body.dark-mode .product-card {
            background-color: #2c2c2c;
            color: #ffffff;
        }
        body.dark-mode .card-body {
            background-color: #2c2c2c;
            color: #ffffff;
        }
        body.dark-mode .modal-content {
            background-color: #2c2c2c;
            color: #ffffff;
        }
        body.dark-mode .modal-header {
            background-color: #1a1a1a;
            border-bottom: 1px solid #444;
            color: #ffffff;
        }
        body.dark-mode .modal-footer {
            background-color: #1a1a1a;
            border-top: 1px solid #444;
        }
    </style>
</head>
<body>
    <?php include '../app/includes/header.php'; ?>

    <!-- Collection Header -->
    <div class="collection-header" style="background: linear-gradient(rgba(44, 62, 80, 0.8), rgba(52, 152, 219, 0.8)), url('<?php echo htmlspecialchars($currentCollection['headerImage']); ?>') center/cover; color: white; padding: 60px 0; margin-bottom: 40px;">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-4 fw-bold mb-3"><?php echo htmlspecialchars($currentCollection['title']); ?></h1>
                    <p class="lead mb-0"><?php echo htmlspecialchars($currentCollection['description']); ?></p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <div class="d-flex gap-2 justify-content-lg-end">
                        <a href="<?php echo BASE_URL; ?>/public/furniture.php" class="btn btn-outline-light">
                            <i class="fas fa-arrow-left me-2"></i>Back to Collections
                        </a>
                        <button class="btn btn-light" onclick="toggleDarkMode()">
                            <i class="fas fa-moon me-2"></i>Dark Mode
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Breadcrumb -->
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/public/">Home</a></li>
                <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/public/furniture.php">Collections</a></li>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars(ucfirst($collection)); ?></li>
            </ol>
        </nav>
    </div>

    <!-- Products Grid -->
    <div class="container">
        <div class="row g-4">
            <?php foreach ($currentCollection['products'] as $index => $product): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card product-card h-100">
                        <img src="<?php echo htmlspecialchars($product['image']); ?>" 
                             class="product-image" 
                             alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                            <div class="mt-auto">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="price-tag"><?php echo htmlspecialchars($product['price']); ?></span>
                                    <div class="text-warning">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <div class="d-grid gap-2">
                                    <button class="btn btn-outline-primary flex-fill" onclick="viewDetails('<?php echo htmlspecialchars($product['name']); ?>', '<?php echo htmlspecialchars($product['image']); ?>')">
                                        <i class="fas fa-eye me-2"></i>View Details
                                    </button>
                                    <button class="btn btn-primary flex-fill" onclick="addToCart('<?php echo htmlspecialchars($product['name']); ?>')">
                                        <i class="fas fa-shopping-cart me-2"></i>Add to Cart
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Contact Us Section -->
    <div class="container my-5">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-envelope me-2"></i>Contact Us</h4>
                    </div>
                    <div class="card-body">
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
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-paper-plane me-2"></i>Send Message
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Details Modal -->
    <div class="modal fade" id="productModal" tabindex="-1" aria-labelledby="productModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="productModalLabel">Product Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <img id="modalProductImage" src="" class="img-fluid rounded" alt="Product">
                        </div>
                        <div class="col-md-6">
                            <h4 id="modalProductName"></h4>
                            <div class="mb-3">
                                <span class="h4 text-primary" id="modalProductPrice"></span>
                                <div class="text-warning mb-3" id="modalProductRating">
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star"></i>
                                    <i class="fas fa-star-half-alt"></i>
                                    <span class="ms-2">(4.5)</span>
                                </div>
                            </div>
                            <p class="text-muted" id="modalProductDescription"></p>
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-outline-primary flex-fill" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-2"></i>Close
                                </button>
                                <button type="button" class="btn btn-primary flex-fill" onclick="addToCart(currentModalProduct)">
                                    <i class="fas fa-shopping-cart me-2"></i>Add to Cart
                                </button>
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
        let currentModalProduct = ''; // Global variable for current modal product
        
        function viewDetails(productName, productImage) {
            // Update modal with product details
            currentModalProduct = productName; // Set global variable
            document.getElementById('modalProductName').textContent = productName;
            document.getElementById('modalProductImage').src = productImage;
            document.getElementById('modalProductImage').alt = productName;
            document.getElementById('modalProductPrice').textContent = 'ETB 25,000'; // Sample price
            document.getElementById('modalProductDescription').textContent = 'High-quality furniture crafted with premium materials. Perfect for modern homes and offices.';
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('productModal'));
            modal.show();
        }

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
        function sendContactMessage(event) {
            event.preventDefault();
            
            // Get form values
            const firstName = document.getElementById('firstName').value;
            const lastName = document.getElementById('lastName').value;
            const email = document.getElementById('email').value;
            const subject = document.getElementById('subject').value;
            const message = document.getElementById('message').value;
            
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
            
            // Use XMLHttpRequest instead of fetch
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo BASE_URL; ?>/public/api/simple_contact.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    alert('Response: ' + xhr.responseText); // Debug line
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                alert('Thank you for your message! We will get back to you soon. Message ID: #' + response.data.message_id);
                                document.getElementById('contactForm').reset();
                            } else {
                                alert('Error: ' + response.message);
                            }
                        } catch (e) {
                            alert('Response error: ' + e.message);
                        }
                    } else {
                        alert('Server error: ' + xhr.status);
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
        
        document.addEventListener('DOMContentLoaded', function() {
            updateCartCount();
        });

        function toggleDarkMode() {
            if (document.body.classList.contains('dark-mode')) {
                document.body.classList.remove('dark-mode');
                localStorage.setItem('darkMode', 'false');
            } else {
                document.body.classList.add('dark-mode');
                localStorage.setItem('darkMode', 'true');
            }
        }

        // Load dark mode preference
        if (localStorage.getItem('darkMode') === 'true') {
            document.body.classList.add('dark-mode');
        }
    </script>
</body>
</html>
