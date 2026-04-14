<header class="sticky-top bg-white shadow-sm">
    <nav class="navbar navbar-expand-lg navbar-light bg-white py-3">
        <div class="container">
            <a class="navbar-brand fw-bold fs-3" href="<?php echo BASE_URL; ?>/public/">
                <i class="fas fa-hammer text-primary"></i>
                <span class="text-dark">Smart<span class="text-primary">Workshop</span></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>/public/">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>/public/about">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>/public/furniture">Furniture</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>/public/how-it-works">How It Works</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?php echo BASE_URL; ?>/public/contact">Contact</a></li>
                </ul>
                <style>
                    .btn-cream{background:#F8F4E9;color:#3D1F14;border:2px solid #3D1F14;border-radius:999px;font-weight:600}
                    .btn-cream:hover{background:#efe8d6}
                    .btn-mahogany{background:#3D1F14;color:#fff;border-radius:999px;font-weight:600;box-shadow:0 6px 16px rgba(61,31,20,.35)}
                    .btn-mahogany:hover{filter:brightness(.95)}
                </style>
                <div class="d-flex">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php $userRole = $_SESSION['user_role'] ?? 'customer'; ?>
                        <a href="<?php echo BASE_URL . '/public/' . $userRole . '/dashboard'; ?>" class="btn btn-success">Dashboard</a>
                        <a href="<?php echo BASE_URL; ?>/public/logout" class="btn btn-outline-secondary ms-2">Logout</a>
                    <?php else: ?>
                        <button type="button" class="btn btn-cream me-2" data-bs-toggle="modal" data-bs-target="#loginModal">Login</button>
                        <button type="button" class="btn btn-mahogany" data-bs-toggle="modal" data-bs-target="#registerModal">Register</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
</header>
