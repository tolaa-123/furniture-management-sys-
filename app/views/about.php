<?php
// about.php - About Us page
require_once '../config/config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Custom Furniture ERP</title>
    <?php include '../app/includes/header_links.php'; ?>
</head>
<body>
    <?php include '../app/includes/header.php'; ?>

    <!-- About Page Content -->
    <div class="container my-5">
        <div class="row">
            <div class="col-lg-12">
                <h1 class="display-4 fw-bold mb-4">About <span class="text-primary">Us</span></h1>
                <p class="lead">Learn more about our custom furniture company and our commitment to excellence.</p>
                
                <div class="row mt-5">
                    <div class="col-md-6">
                        <img src="https://images.unsplash.com/photo-1556911220-e15b29be8c8f?auto=format&fit=crop&w=600&h=400&q=80" alt="Our Workshop" class="img-fluid rounded shadow">
                    </div>
                    <div class="col-md-6">
                        <h3>Our Story</h3>
                        <p>Founded in 2015, Custom Furniture ERP began as a small workshop with a passion for creating beautiful, functional furniture. Today, we've grown into a leading provider of custom furniture solutions, serving clients nationwide.</p>
                        
                        <h3>Our Mission</h3>
                        <p>We believe that every piece of furniture tells a story. Our mission is to craft unique, sustainable, and beautiful pieces that enhance your living spaces and reflect your personal style.</p>
                        
                        <h3>Our Values</h3>
                        <ul>
                            <li>Quality craftsmanship</li>
                            <li>Environmental responsibility</li>
                            <li>Exceptional customer service</li>
                            <li>Innovation in design</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../app/includes/footer.php'; ?>
    <?php include '../app/includes/footer_scripts.php'; ?>
</body>
</html>