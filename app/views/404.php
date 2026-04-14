<?php
// 404.php - Not Found page
require_once '../config/config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found - Custom Furniture ERP</title>
    <?php include '../app/includes/header_links.php'; ?>
</head>
<body>
    <?php include '../app/includes/header.php'; ?>

    <!-- 404 Page Content -->
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-6 text-center">
                <div class="error-content">
                    <h1 class="display-1 fw-bold text-primary">404</h1>
                    <h2 class="mb-4">Oops! Page Not Found</h2>
                    <p class="lead mb-4">The page you're looking for doesn't exist or has been moved.</p>
                    <a href="<?php echo BASE_URL; ?>/public/" class="btn btn-primary btn-lg">Back to Home</a>
                </div>
            </div>
        </div>
    </div>

    <?php include '../app/includes/footer.php'; ?>
    <?php include '../app/includes/footer_scripts.php'; ?>
</body>
</html>