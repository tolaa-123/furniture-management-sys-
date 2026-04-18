<?php
// No inline auth sections - using modals only
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartWorkshop - Premium Custom Furniture & ERP Platform</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .auth-modal .form-label{font-weight:600}
        .auth-modal .input-group-custom{position:relative;margin-bottom:1.2rem}
        .auth-modal .input-icon{position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:#6f4a3b;z-index:10}
        .auth-modal .form-control{padding:1rem 1.25rem 1rem 3.25rem;border:2px solid #d9c2a8;border-radius:999px;transition:all .25s ease;font-size:1.05rem;background:#faf6f1}
        .auth-modal .form-control:focus{border-color:#b88a65;box-shadow:0 0 0 .2rem rgba(184,138,101,.2);outline:none}
        .login-section .login-input{background:#e9eff9;border-color:#ced7ea}
        .login-section .login-input:focus{border-color:#b9c7e6;box-shadow:0 0 0 .2rem rgba(185,199,230,.25)}
        .auth-modal .password-toggle{position:absolute;right:1rem;top:50%;transform:translateY(-50%);cursor:pointer;color:#9ca3af;z-index:10}
        .auth-modal .btn-primary{background:linear-gradient(180deg,#7b3f1d 0%,#5f2f18 100%);border:none;border-radius:999px;box-shadow:0 10px 24px rgba(97,56,33,.35), inset 0 2px 0 rgba(255,255,255,.25);font-weight:600}
        .auth-modal .btn-primary:hover{transform:translateY(-1px);box-shadow:0 12px 28px rgba(97,56,33,.45)}
        .auth-card{border:none;border-radius:22px;background:#fbf3e7;box-shadow:0 25px 60px rgba(0,0,0,.15);overflow:hidden}
        .auth-header{background:transparent;color:#3b2a23;padding:1.5rem 1.25rem 0;text-align:center}
        .login-section .auth-header{background:#6b3f23;color:#fff;padding:1.25rem;border-top-left-radius:22px;border-top-right-radius:22px}
        .login-section .auth-sub{color:#f3e9dd}
        .register-section .auth-header{background:linear-gradient(180deg,#7b3f1d 0%,#5f2f18 100%);color:#fff;padding:1.25rem;border-top-left-radius:22px;border-top-right-radius:22px}
        .register-section .auth-sub{color:#f3e9dd}
        .register-section .register-input{background:#e9eff9;border-color:#ced7ea}
        .register-section .register-input:focus{border-color:#b9c7e6;box-shadow:0 0 0 .2rem rgba(185,199,230,.25)}
        .auth-header h5{margin:0 0 .25rem 0;font-weight:800;font-family:'Playfair Display',serif}
        .auth-sub{opacity:.9;margin-top:.25rem;font-size:.96rem;color:#7b6a5e}
        .auth-body{padding:1.25rem 1.75rem 1.25rem}
        .auth-foot{padding:.75rem;border-top:0;background:#fff}
        .auth-modal .alert{border:none;border-radius:12px}
        .auth-modal .alert-danger{background:#fee2e2;color:#991b1b}
        .auth-modal .alert-success{background:#d1fae5;color:#065f46}
        .password-strength{margin-top:.5rem;height:4px;background:#e5e7eb;border-radius:2px;overflow:hidden}
        .password-strength-bar{height:100%;width:0;transition:all .3s ease}
        .password-strength-bar.weak{width:33%;background:#ef4444}
        .password-strength-bar.medium{width:66%;background:#f59e0b}
        .password-strength-bar.strong{width:100%;background:#10b981}
        .auth-meta{display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem}
        .auth-meta a{color:#7b4f2e;font-weight:600;text-decoration:none}
        .auth-meta a:hover{text-decoration:underline}
        .login-section{background:#f8efe4}
        .login-container{max-width:680px;margin:auto}
        .auth-inline .form-control{background:#faf6f1}
        .remember-check .form-check-input{width:1.1rem;height:1.1rem;border:2px solid #d0b79d}
        .remember-check .form-check-input:checked{background-color:#b88a65;border-color:#b88a65}
        .register-section{background:#f8efe4}
        .register-container{max-width:820px;margin:auto}
        .terms-check .form-check-input{width:1.1rem;height:1.1rem;border:2px solid #d0b79d}
        .terms-check .form-check-input:checked{background-color:#b88a65;border-color:#b88a65}
        .forgot-section{background:#f8efe4}
        .forgot-container{max-width:680px;margin:auto}
        /* Overlay behavior for auth sections on home */
        .login-section,.register-section,.forgot-section{
            position:fixed; inset:0; z-index:1100;
            display:flex; align-items:center; justify-content:center;
            padding:24px 16px; background:rgba(0,0,0,.42); backdrop-filter:blur(2px);
        }
        /* Dim nav while overlay is active but keep links clickable */
        body.auth-overlay-active .site-header .navbar,
        body.auth-overlay-active .site-header .navbar *{transition:opacity .2s}
        body.auth-overlay-active .site-header .navbar .navbar-nav,
        body.auth-overlay-active .site-header .navbar .d-flex{opacity:.2}
        .forgot-section .auth-header{background:linear-gradient(180deg,#7b3f1d 0%,#5f2f18 100%);color:#fff;padding:1.25rem;border-top-left-radius:22px;border-top-right-radius:22px}
        .forgot-section .auth-sub{color:#f3e9dd}
        .forgot-section .forgot-input{background:#e9eff9;border-color:#ced7ea}
        .forgot-section .forgot-input:focus{border-color:#b9c7e6;box-shadow:0 0 0 .2rem rgba(185,199,230,.25)}
        @media (max-width: 992px){
            .login-container,.register-container,.forgot-container{max-width:92%}
        }
        @media (max-width: 576px){
            .auth-body{padding:1rem}
            .auth-header{padding:1rem .75rem 0}
            .auth-modal .form-control{padding:.9rem .9rem .9rem 3rem}
            .auth-meta{flex-direction:column;align-items:flex-start;gap:.5rem}
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <!-- Hero Section -->
    <section class="hero-section py-5" style="background: linear-gradient(135deg, #FFF8F0 0%, #F5F5DC 100%);">
        <div class="container py-5">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-5 mb-lg-0">
                    <h1 class="display-4 fw-bold mb-4">Custom Furniture Crafted for <span class="text-primary">Modern Living</span></h1>
                    <p class="lead mb-4">Design, Customize, and Order Furniture Built Exactly for You.</p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="<?php echo BASE_URL; ?>/public/furniture" class="btn btn-primary btn-lg px-4 py-3">Browse Furniture</a>
                        <?php if (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'customer'): ?>
                            <a href="<?php echo BASE_URL; ?>/public/customer/create-order" class="btn btn-outline-primary btn-lg px-4 py-3">Customize & Order</a>
                        <?php else: ?>
                            <button type="button" class="btn btn-outline-primary btn-lg px-4 py-3" onclick="showLoginForOrder()">Customize & Order</button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-6">
                    <!-- Hero Image Slideshow -->
                    <div class="hero-slideshow" style="position: relative; width: 100%; height: 500px; border-radius: 10px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
                        <div class="hero-slide active" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 1; transition: opacity 0.8s ease-in-out;">
                            <img src="<?php echo BASE_URL; ?>/public/assets/images/hero/hero1.jpg.jpg" alt="Premium Custom Furniture" class="img-fluid" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                        <div class="hero-slide" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; transition: opacity 0.8s ease-in-out;">
                            <img src="<?php echo BASE_URL; ?>/public/assets/images/hero/hero2.jpg.png" alt="Premium Custom Furniture" class="img-fluid" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                        <div class="hero-slide" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; transition: opacity 0.8s ease-in-out;">
                            <img src="<?php echo BASE_URL; ?>/public/assets/images/hero/hero3.jpg.png" alt="Premium Custom Furniture" class="img-fluid" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                        <div class="hero-slide" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; transition: opacity 0.8s ease-in-out;">
                            <img src="<?php echo BASE_URL; ?>/public/assets/images/hero/hero4.jpg.png" alt="Premium Custom Furniture" class="img-fluid" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <script>
    // Hero Image Slideshow
    (function() {
        const slides = document.querySelectorAll('.hero-slide');
        let currentSlide = 0;
        
        function showNextSlide() {
            // Hide current slide
            slides[currentSlide].style.opacity = '0';
            
            // Move to next slide
            currentSlide = (currentSlide + 1) % slides.length;
            
            // Show next slide
            slides[currentSlide].style.opacity = '1';
        }
        
        // Change slide every 4 seconds
        setInterval(showNextSlide, 4000);
    })();
    </script>

    <!-- About Section -->
    <section id="about" class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-6 mb-5 mb-lg-0">
                    <h2 class="fw-bold mb-4">Our <span class="text-primary">Mission</span></h2>
                    <p class="lead">We believe that every piece of furniture tells a story. Our mission is to craft unique, sustainable, and beautiful pieces that enhance your living spaces and reflect your personal style.</p>
                    <p>With over 10 years of experience in fine woodworking and furniture design, our artisans combine traditional techniques with modern innovation to create exceptional pieces that stand the test of time.</p>
                </div>
                <div class="col-lg-6">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="card border-0 text-center h-100">
                                <div class="card-body">
                                    <div class="icon-box bg-primary bg-opacity-10 rounded-circle d-inline-block p-4 mb-3">
                                        <i class="fas fa-leaf text-primary fa-2x"></i>
                                    </div>
                                    <h5 class="fw-bold">Quality Materials</h5>
                                    <p>Sustainably sourced wood and premium materials</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card border-0 text-center h-100">
                                <div class="card-body">
                                    <div class="icon-box bg-primary bg-opacity-10 rounded-circle d-inline-block p-4 mb-3">
                                        <i class="fas fa-user-tie text-primary fa-2x"></i>
                                    </div>
                                    <h5 class="fw-bold">Skilled Craftsmen</h5>
                                    <p>Expert artisans with decades of experience</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card border-0 text-center h-100">
                                <div class="card-body">
                                    <div class="icon-box bg-primary bg-opacity-10 rounded-circle d-inline-block p-4 mb-3">
                                        <i class="fas fa-pencil-ruler text-primary fa-2x"></i>
                                    </div>
                                    <h5 class="fw-bold">Custom Designs</h5>
                                    <p>Tailored to your unique preferences</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card border-0 text-center h-100">
                                <div class="card-body">
                                    <div class="icon-box bg-primary bg-opacity-10 rounded-circle d-inline-block p-4 mb-3">
                                        <i class="fas fa-shipping-fast text-primary fa-2x"></i>
                                    </div>
                                    <h5 class="fw-bold">On-Time Delivery</h5>
                                    <p>Punctual service guaranteed</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Sofa Slider -->
    <section id="featured-sofa" class="py-5" style="background: #f8f8f8;">
        <div class="container">
            <div class="text-center mb-4">
                <h2 class="fw-bold">Featured <span class="text-primary">Sofa</span> Collection</h2>
                <p class="lead">Explore our most popular and elegant sofas</p>
            </div>
            <div id="sofaSlider" class="carousel slide" data-bs-ride="carousel">
                <div class="carousel-inner rounded-4 shadow-lg">
                    <div class="carousel-item active">
                        <img src="https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=800&q=80" class="d-block w-100" alt="Modern Grey Sofa" loading="lazy" style="height:400px;object-fit:cover;">
                    </div>
                    <div class="carousel-item">
                        <img src="https://images.unsplash.com/photo-1540574163026-643ea20ade25?auto=format&fit=crop&w=800&q=80" class="d-block w-100" alt="Luxury Mahogany Sofa" loading="lazy" style="height:400px;object-fit:cover;">
                    </div>
                    <div class="carousel-item">
                        <img src="https://images.unsplash.com/photo-1493663284031-b7e3aefcae8e?auto=format&fit=crop&w=800&q=80" class="d-block w-100" alt="Minimalist Cream Sofa" loading="lazy" style="height:400px;object-fit:cover;">
                    </div>
                    <div class="carousel-item">
                        <img src="https://images.unsplash.com/photo-1583847268964-b28dc8f51f92?auto=format&fit=crop&w=800&q=80" class="d-block w-100" alt="Classic Leather Sofa" loading="lazy" style="height:400px;object-fit:cover;">
                    </div>
                    <div class="carousel-item">
                        <img src="https://images.unsplash.com/photo-1550254478-ead40cc54513?auto=format&fit=crop&w=800&q=80" class="d-block w-100" alt="Elegant Blue Velvet" loading="lazy" style="height:400px;object-fit:cover;">
                    </div>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#sofaSlider" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#sofaSlider" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Next</span>
                </button>
            </div>
        </div>
    </section>
    <section id="furniture" class="py-5" style="background-color: #FFF8F0;">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Our <span class="text-primary">Collections</span></h2>
                <p class="lead">Discover our range of handcrafted furniture</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="card border-0 rounded-4 overflow-hidden shadow-lg h-100 product-card">
                        <div class="position-relative">
                            <img src="<?php echo BASE_URL; ?>/public/assets/images/collections/sofa.jpg" class="card-img-top" alt="Sofa Collection" style="height:220px;object-fit:cover;">
                            <div class="product-badge bg-primary text-white position-absolute top-0 end-0 m-3 px-3 py-1 rounded-pill">Popular</div>
                        </div>
                        <div class="card-body text-center p-4">
                            <h5 class="card-title fw-bold mb-2">Sofa</h5>
                            <p class="card-text text-muted mb-3">Comfortable and stylish seating solutions</p>
                            <div class="d-grid">
                                <a href="<?php echo BASE_URL; ?>/public/collection/sofa" class="btn btn-primary">View Collection</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card border-0 rounded-4 overflow-hidden shadow-lg h-100 product-card">
                        <div class="position-relative">
                            <img src="<?php echo BASE_URL; ?>/public/assets/images/collections/bed.jpg" class="card-img-top" alt="Bed Collection" style="height:220px;object-fit:cover;">
                            <div class="product-badge bg-success text-white position-absolute top-0 end-0 m-3 px-3 py-1 rounded-pill">Best Seller</div>
                        </div>
                        <div class="card-body text-center p-4">
                            <h5 class="card-title fw-bold mb-2">Bed</h5>
                            <p class="card-text text-muted mb-3">Luxurious sleeping solutions</p>
                            <div class="d-grid">
                                <a href="<?php echo BASE_URL; ?>/public/collection/bed" class="btn btn-primary">View Collection</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card border-0 rounded-4 overflow-hidden shadow-lg h-100 product-card">
                        <div class="position-relative">
                            <img src="<?php echo BASE_URL; ?>/public/assets/images/collections/table.jpg" class="card-img-top" alt="Table Collection" style="height:220px;object-fit:cover;">
                            <div class="product-badge bg-warning text-dark position-absolute top-0 end-0 m-3 px-3 py-1 rounded-pill">New</div>
                        </div>
                        <div class="card-body text-center p-4">
                            <h5 class="card-title fw-bold mb-2">Table</h5>
                            <p class="card-text text-muted mb-3">Elegant dining and work surfaces</p>
                            <div class="d-grid">
                                <a href="<?php echo BASE_URL; ?>/public/collection/table" class="btn btn-primary">View Collection</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="card border-0 rounded-4 overflow-hidden shadow-lg h-100 product-card">
                        <div class="position-relative">
                            <img src="<?php echo BASE_URL; ?>/public/assets/images/collections/chair.jpg" class="card-img-top" alt="Chair Collection" style="height:220px;object-fit:cover;">
                            <div class="product-badge bg-info text-white position-absolute top-0 end-0 m-3 px-3 py-1 rounded-pill">Trending</div>
                        </div>
                        <div class="card-body text-center p-4">
                            <h5 class="card-title fw-bold mb-2">Chair</h5>
                            <p class="card-text text-muted mb-3">Comfortable and stylish seating</p>
                            <div class="d-grid">
                                <a href="<?php echo BASE_URL; ?>/public/collection/chair" class="btn btn-primary">View Collection</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Gallery -->
    <section id="gallery" class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">Featured <span class="text-primary">Gallery</span></h2>
                <p class="lead">Our finest craftsmanship showcased</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="position-relative overflow-hidden rounded-3 shadow-sm h-100">
                        <img src="<?php echo BASE_URL; ?>/public/assets/images/gallery/gallery1.jpg" class="img-fluid w-100" alt="Featured Furniture 1" style="height:280px;object-fit:cover;">
                        <div class="overlay position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-dark bg-opacity-50 opacity-0 hover-effect">
                            <a href="<?php echo BASE_URL; ?>/public/furniture" class="btn btn-light"><i class="fas fa-search-plus me-2"></i>View Details</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="position-relative overflow-hidden rounded-3 shadow-sm h-100">
                        <img src="<?php echo BASE_URL; ?>/public/assets/images/gallery/gallery2.jpg" class="img-fluid w-100" alt="Featured Furniture 2" style="height:280px;object-fit:cover;">
                        <div class="overlay position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-dark bg-opacity-50 opacity-0 hover-effect">
                            <a href="<?php echo BASE_URL; ?>/public/furniture" class="btn btn-light"><i class="fas fa-search-plus me-2"></i>View Details</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="position-relative overflow-hidden rounded-3 shadow-sm h-100">
                        <img src="<?php echo BASE_URL; ?>/public/assets/images/gallery/gallery3.jpg" class="img-fluid w-100" alt="Featured Furniture 3" style="height:280px;object-fit:cover;">
                        <div class="overlay position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-dark bg-opacity-50 opacity-0 hover-effect">
                            <a href="<?php echo BASE_URL; ?>/public/furniture" class="btn btn-light"><i class="fas fa-search-plus me-2"></i>View Details</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section id="how-it-works" class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">How It <span class="text-primary">Works</span></h2>
                <p class="lead">Simple 5-step process to get your custom furniture</p>
            </div>
            <div class="row g-4">
                <div class="col-md-2 mb-4">
                    <div class="text-center">
                        <div class="step-number d-flex align-items-center justify-content-center mx-auto bg-primary text-white rounded-circle mb-3" style="width: 50px; height: 50px;">1</div>
                        <h5 class="fw-bold">Choose Furniture</h5>
                    </div>
                </div>
                <div class="col-md-2 mb-4">
                    <div class="text-center">
                        <div class="step-number d-flex align-items-center justify-content-center mx-auto bg-primary text-white rounded-circle mb-3" style="width: 50px; height: 50px;">2</div>
                        <h5 class="fw-bold">Customize It</h5>
                    </div>
                </div>
                <div class="col-md-2 mb-4">
                    <div class="text-center">
                        <div class="step-number d-flex align-items-center justify-content-center mx-auto bg-primary text-white rounded-circle mb-3" style="width: 50px; height: 50px;">3</div>
                        <h5 class="fw-bold">Manager Approves Cost</h5>
                    </div>
                </div>
                <div class="col-md-2 mb-4">
                    <div class="text-center">
                        <div class="step-number d-flex align-items-center justify-content-center mx-auto bg-primary text-white rounded-circle mb-3" style="width: 50px; height: 50px;">4</div>
                        <h5 class="fw-bold">Pay Deposit</h5>
                    </div>
                </div>
                <div class="col-md-2 mb-4">
                    <div class="text-center">
                        <div class="step-number d-flex align-items-center justify-content-center mx-auto bg-primary text-white rounded-circle mb-3" style="width: 50px; height: 50px;">5</div>
                        <h5 class="fw-bold">Production & Delivery</h5>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Customization Promotion Section -->
    <section class="py-5 bg-primary text-white">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h2 class="fw-bold mb-3">Ready to Customize Your Own Furniture?</h2>
                    <p class="mb-0">Work with our designers to create a piece that perfectly fits your space and style.</p>
                </div>
                <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                    <?php if (isset($_SESSION['user_id']) && $_SESSION['user_role'] === 'customer'): ?>
                        <a href="<?php echo BASE_URL; ?>/public/customer/create-order" class="btn btn-light btn-lg px-4 py-3 fw-bold">Start Customizing</a>
                    <?php else: ?>
                        <button type="button" class="btn btn-light btn-lg px-4 py-3 fw-bold" onclick="showLoginForOrder()">Start Customizing</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section id="testimonials" class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="fw-bold">What Our <span class="text-primary">Clients Say</span></h2>
                <p class="lead">Hear from satisfied customers</p>
            </div>
            <?php
            $testimonials = [];
            try {
                require_once __DIR__ . '/../../config/db_config.php';
                $stmt = $pdo->query("
                    SELECT CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                           u.profile_image,
                           r.rating, r.review_text, r.created_at,
                           o.furniture_name
                    FROM furn_ratings r
                    JOIN furn_users u ON r.customer_id = u.id
                    JOIN furn_orders o ON r.order_id = o.id
                    WHERE r.rating IS NOT NULL
                      AND r.rating >= 3
                    ORDER BY r.rating DESC, r.created_at DESC
                    LIMIT 6
                ");
                $testimonials = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {}
            ?>
            <?php if (empty($testimonials)): ?>
            <div class="text-center py-5">
                <i class="fas fa-star fa-3x text-warning mb-3 d-block" style="opacity:.4;"></i>
                <h5 class="fw-bold">Be the first to leave a review</h5>
                <p class="text-muted">After you receive your furniture, come back and share your experience.</p>
            </div>
            <?php else: ?>
            <div class="row g-4">
                <?php foreach ($testimonials as $t):
                    $stars = (int)$t['rating'];
                    $hasReview = !empty($t['review_text']) && strlen(trim($t['review_text'])) >= 10;
                ?>
                <div class="col-md-4">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body p-4">
                            <div class="d-flex mb-3 align-items-center">
                                <div class="flex-shrink-0">
                                    <?php if (!empty($t['profile_image'])): ?>
                                    <img src="<?= BASE_URL ?>/public/uploads/profile_images/<?= htmlspecialchars($t['profile_image']) ?>"
                                         class="rounded-circle" width="52" height="52"
                                         style="object-fit:cover;border:2px solid #f3e8d8;"
                                         alt="<?= htmlspecialchars($t['customer_name']) ?>"
                                         onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                    <div style="display:none;width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,#8B4513,#d4a574);align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:20px;">
                                        <?php echo strtoupper(substr($t['customer_name'], 0, 1)); ?>
                                    </div>
                                    <?php else: ?>
                                    <div style="width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,#8B4513,#d4a574);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:20px;">
                                        <?php echo strtoupper(substr($t['customer_name'], 0, 1)); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h5 class="mb-0" style="font-size:.95rem;"><?php echo htmlspecialchars($t['customer_name']); ?></h5>
                                    <div class="text-warning" style="font-size:.85rem;">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star" style="<?php echo $i > $stars ? 'opacity:.25;' : ''; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </div>
                            <?php if (!empty($t['furniture_name'])): ?>
                            <div style="font-size:.75rem;color:#8B4513;font-weight:600;margin-bottom:8px;">
                                <i class="fas fa-couch me-1"></i><?php echo htmlspecialchars($t['furniture_name']); ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($hasReview): ?>
                            <p class="card-text" style="font-size:.88rem;color:#555;font-style:italic;">"<?php echo htmlspecialchars($t['review_text']); ?>"</p>
                            <?php endif; ?>
                            <small class="text-muted"><?php echo date('M j, Y', strtotime($t['created_at'])); ?></small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-lg-6 mb-5 mb-lg-0">
                    <h2 class="fw-bold mb-4">Get In <span class="text-primary">Touch</span></h2>
                    <form>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="firstName" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="firstName" placeholder="Enter your first name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="lastName" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="lastName" placeholder="Enter your last name">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" placeholder="Enter your email">
                        </div>
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject</label>
                            <input type="text" class="form-control" id="subject" placeholder="Enter subject">
                        </div>
                        <div class="mb-3">
                            <label for="message" class="form-label">Message</label>
                            <textarea class="form-control" id="message" rows="5" placeholder="Enter your message"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Send Message</button>
                    </form>
                </div>
                <div class="col-lg-6">
                    <h2 class="fw-bold mb-4">Contact <span class="text-primary">Information</span></h2>
                    <style>
                        .contact-info-list { display: flex; flex-direction: column; gap: 1rem; }
                        .contact-info-item { display: flex; align-items: flex-start; gap: 1rem; background: #fff; border-radius: 10px; padding: 1.25rem; box-shadow: 0 1px 6px rgba(0,0,0,0.08); }
                        .contact-info-icon { flex-shrink: 0; width: 48px; height: 48px; border-radius: 50%; background: rgba(139,69,19,0.1); display: flex; align-items: center; justify-content: center; }
                        .contact-info-icon i { color: #8B4513; font-size: 1.1rem; }
                        .contact-info-body { flex: 1; min-width: 0; }
                        .contact-info-label { font-weight: 700; font-size: 1rem; margin-bottom: 0.25rem; color: #333; }
                        .contact-info-value { font-size: 0.95rem; line-height: 1.5; color: #555; word-break: break-all; overflow-wrap: break-word; }
                        .contact-info-value a { color: inherit; text-decoration: none; word-break: break-all; overflow-wrap: break-word; }
                        @media (max-width: 768px) {
                            .contact-info-item { padding: 1rem; gap: 0.75rem; }
                            .contact-info-icon { width: 40px; height: 40px; }
                            .contact-info-icon i { font-size: 0.9rem; }
                            .contact-info-label { font-size: 0.95rem; }
                            .contact-info-value { font-size: 0.9rem; }
                        }
                    </style>
                    <div class="contact-info-list">
                        <div class="contact-info-item">
                            <div class="contact-info-icon"><i class="fas fa-map-marker-alt"></i></div>
                            <div class="contact-info-body">
                                <div class="contact-info-label">Address</div>
                                <div class="contact-info-value">123 Furniture Street<br>Jima, Ethiopia</div>
                            </div>
                        </div>
                        <div class="contact-info-item">
                            <div class="contact-info-icon"><i class="fas fa-phone-alt"></i></div>
                            <div class="contact-info-body">
                                <div class="contact-info-label">Phone</div>
                                <div class="contact-info-value">
                                    <a href="tel:+251943778192">+251 943 778 192</a><br>
                                    <a href="tel:+251710766709">+251 710 766 709</a>
                                </div>
                            </div>
                        </div>
                        <div class="contact-info-item">
                            <div class="contact-info-icon"><i class="fas fa-envelope"></i></div>
                            <div class="contact-info-body">
                                <div class="contact-info-label">Email</div>
                                <div class="contact-info-value">
                                    <a href="https://mail.google.com/mail/?view=cm&to=derejeayele292@gmail.com" target="_blank">derejeayele292@gmail.com</a>
                                </div>
                            </div>
                        </div>
                        <div class="contact-info-item">
                            <div class="contact-info-icon"><i class="fas fa-clock"></i></div>
                            <div class="contact-info-body">
                                <div class="contact-info-label">Working Hours</div>
                                <div class="contact-info-value">Mon-Fri: 9am-6pm<br>Sat: 10am-4pm</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-light py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-3 col-md-6 mb-4 mb-md-0">
                    <h5 class="fw-bold mb-4">About SmartWorkshop</h5>
                    <p>Premium Custom Furniture ERP & E-Commerce Platform dedicated to creating exceptional, personalized furniture solutions with modern craftsmanship.</p>
                    <div class="social-links">
                        <a href="https://www.facebook.com/Ayele.ayela.73" target="_blank" rel="noopener" class="text-light me-3"><i class="fab fa-facebook-f"></i></a>
                        <a href="https://t.me/der_292" target="_blank" rel="noopener" class="text-light me-3"><i class="fab fa-telegram-plane"></i></a>
                        <a href="https://wa.me/25143778192" target="_blank" rel="noopener" class="text-light"><i class="fab fa-whatsapp"></i></a>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4 mb-md-0">
                    <h5 class="fw-bold mb-4">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="<?php echo BASE_URL; ?>/public/" class="text-decoration-none text-light">Home</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/public/about" class="text-decoration-none text-light">About</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/public/furniture" class="text-decoration-none text-light">Furniture</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/public/#how-it-works" class="text-decoration-none text-light">How It Works</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/public/contact" class="text-decoration-none text-light">Contact</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6 mb-4 mb-md-0">
                    <h5 class="fw-bold mb-4">Services</h5>
                    <ul class="list-unstyled">
                        <li><a href="<?php echo BASE_URL; ?>/public/" class="text-decoration-none text-light">Custom Design</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/public/" class="text-decoration-none text-light">Furniture Repair</a></li>
                        <li><a href="#" class="text-decoration-none text-light">Installation</a></li>
                        <li><a href="#" class="text-decoration-none text-light">Consultation</a></li>
                        <li><a href="#" class="text-decoration-none text-light">Delivery</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6">
                    <h5 class="fw-bold mb-4">Contact Info</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="fas fa-map-marker-alt me-2"></i> 123 Furniture Street, Jima, Ethiopia</li>
                        <li class="mb-2"><i class="fas fa-phone-alt me-2"></i> <a href="tel:+251943778192" style="color:inherit;text-decoration:none;">+251 943 778 192</a> &nbsp;|&nbsp; <a href="tel:+251710766709" style="color:inherit;text-decoration:none;">+251 710 766 709</a></li>
                        <li class="mb-2"><i class="fas fa-envelope me-2"></i> <a href="https://mail.google.com/mail/?view=cm&to=derejeayele292@gmail.com" style="color:inherit;text-decoration:none;">derejeayele292@gmail.com</a></li>
                    </ul>
                </div>
            </div>
            <hr class="my-4 bg-secondary">
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0">&copy; 2026 SmartWorkshop - Premium Custom Furniture. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <a href="<?php echo BASE_URL; ?>/public/" class="text-decoration-none text-light me-3">Privacy Policy</a>
                    <a href="<?php echo BASE_URL; ?>/public/" class="text-decoration-none text-light">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="../assets/js/main.js"></script>
    <script>
        document.querySelectorAll('.password-toggle').forEach(function(el){
            el.addEventListener('click', function(){
                var target = document.querySelector(el.getAttribute('data-target'));
                if (!target) return;
                target.type = target.type === 'password' ? 'text' : 'password';
                el.classList.toggle('fa-eye');
                el.classList.toggle('fa-eye-slash');
            });
        });
        var pwd = document.getElementById('reg_password');
        if (pwd) {
            pwd.addEventListener('input', function(){
                var p = pwd.value;
                var bar = document.getElementById('strengthBar');
                var s = 0;
                if (p.length >= 8) s++;
                if (/[a-z]/.test(p)) s++;
                if (/[A-Z]/.test(p)) s++;
                if (/[0-9]/.test(p)) s++;
                bar.className = 'password-strength-bar';
                if (s <= 2) bar.classList.add('weak'); else if (s <= 3) bar.classList.add('medium'); else bar.classList.add('strong');
            });
        }
        var pwd2 = document.getElementById('reg_password2');
        if (pwd2) {
            pwd2.addEventListener('input', function(){
                var p = pwd2.value;
                var bar = document.getElementById('strengthBar2');
                var s = 0;
                if (p.length >= 8) s++;
                if (/[a-z]/.test(p)) s++;
                if (/[A-Z]/.test(p)) s++;
                if (/[0-9]/.test(p)) s++;
                bar.className = 'password-strength-bar';
                if (s <= 2) bar.classList.add('weak'); else if (s <= 3) bar.classList.add('medium'); else bar.classList.add('strong');
            });
        }
        var regForm = document.getElementById('inlineRegisterForm');
        if (regForm) {
            regForm.addEventListener('submit', function(){
                var fnEl = document.getElementById('first_name');
                var lnEl = document.getElementById('last_name');
                var fullEl = document.getElementById('full_name_hidden');
                var fn = fnEl ? fnEl.value.trim() : '';
                var ln = lnEl ? lnEl.value.trim() : '';
                if (fullEl) fullEl.value = (fn + ' ' + ln).trim();
            });
        }
        (function(){
            var params = new URLSearchParams(window.location.search);
            var auth = params.get('auth');
            var modal = params.get('modal');
            var redirect = params.get('redirect');
            
            // Populate redirect field if present
            if (redirect) {
                var redirectField = document.getElementById('login_redirect_field');
                if (redirectField) {
                    redirectField.value = redirect;
                }
            }
            
            // Auto-open modal based on URL parameter
            if (modal === 'login' || auth === 'login') {
                var loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
                loginModal.show();
            } else if (modal === 'register' || auth === 'register') {
                var registerModal = new bootstrap.Modal(document.getElementById('registerModal'));
                registerModal.show();
            } else if (modal === 'forgot' || auth === 'forgot') {
                var forgotModal = new bootstrap.Modal(document.getElementById('forgotPasswordModal'));
                forgotModal.show();
            }
        })();
        
        // Function to show login modal with redirect
        function showLoginForOrder() {
            // Set redirect value
            var redirectField = document.getElementById('login_redirect_field');
            if (redirectField) {
                redirectField.value = 'customer/create-order';
            }
            // Open login modal
            var loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
            loginModal.show();
        }
    </script>

    <!-- Authentication Modals -->
    <?php include __DIR__ . '/auth/modals/login_modal.php'; ?>
    <?php include __DIR__ . '/auth/modals/register_modal.php'; ?>
    <?php include __DIR__ . '/auth/modals/forgot_password_modal.php'; ?>

    <!-- Auth Modals CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/assets/css/auth-modals.css">
    
    <!-- Auth Modals JavaScript -->
    <script src="<?php echo BASE_URL; ?>/public/assets/js/auth-modals.js"></script>
</body>
</html>
