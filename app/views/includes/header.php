<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /NEWkoder/public/login');
    exit();
}

// Get user info
$userRole = $_SESSION['user_role'] ?? 'guest';
$userName = $_SESSION['user_name'] ?? 'Guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'Custom Furniture ERP'; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link href="/NEWkoder/public/assets/css/style.css" rel="stylesheet">
    
    <style>
        body {
            font-size: .875rem;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
        }
        
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 48px);
            padding-top: .5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        
        .navbar-brand {
            padding-top: .75rem;
            padding-bottom: .75rem;
            font-size: 1rem;
            background-color: rgba(0, 0, 0, .25);
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .25);
        }
        
        .navbar .navbar-toggler {
            top: .25rem;
            right: 1rem;
        }
        
        .navbar .form-control {
            padding: .75rem 1rem;
            border-width: 0;
            border-radius: 0;
        }
        
        .form-control-dark {
            color: #fff;
            background-color: rgba(255, 255, 255, .1);
            border-color: rgba(255, 255, 255, .1);
        }
        
        .form-control-dark:focus {
            border-color: transparent;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, .25);
        }
        
        .user-info {
            padding: 1rem 1rem 0;
            border-bottom: 1px solid rgba(0, 0, 0, .1);
        }
        
        .user-info h6 {
            margin-bottom: 0;
            color: #fff;
        }
        
        .user-info small {
            color: rgba(255, 255, 255, .7);
        }
    </style>
</head>
<body>
    <header class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 shadow">
        <div class="d-flex align-items-center col-12">
            <button class="navbar-toggler d-md-none ms-2" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <a class="navbar-brand px-3 d-flex align-items-center gap-2" href="#">
                <i class="fas fa-couch"></i>
                <span>Furniture ERP</span>
            </a>
            <div class="flex-grow-1 d-none d-md-flex justify-content-center">
                <span class="text-white-50"><?php echo htmlspecialchars($pageTitle ?? 'Dashboard'); ?></span>
            </div>
            <div class="ms-auto d-flex align-items-center pe-2">
                <button class="btn btn-link text-white position-relative me-2" title="Notifications">
                    <i class="fas fa-bell"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">•</span>
                </button>
                <div class="dropdown">
                    <a class="nav-link px-2 text-white dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="/NEWkoder/public/assets/img/sample-user.svg" alt="User" style="width:32px;height:32px;border-radius:50%;background:#fff;padding:2px">
                        <div class="d-none d-md-block">
                            <div><?php echo htmlspecialchars($userName); ?></div>
                            <small class="badge bg-warning text-dark text-uppercase"><?php echo htmlspecialchars($userRole); ?></small>
                        </div>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="/profile"><i class="fas fa-user me-2"></i>My Profile</a></li>
                        <li><a class="dropdown-item" href="/settings"><i class="fas fa-sliders-h me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="/NEWkoder/public/logout"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </header>
