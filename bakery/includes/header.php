<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if constants and functions are available, if not include them
if (!defined('SITE_NAME')) {
    require_once __DIR__ . '/../config/constants.php';
}

if (!function_exists('isLoggedIn')) {
    require_once __DIR__ . '/../includes/functions.php';
}

// Set default page title
$pageTitle = isset($pageTitle) ? $pageTitle : SITE_NAME;

// Get the current page for highlighting active nav items
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo SITE_NAME; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" href="<?php echo ASSETS_URL; ?>/favicon.ico" type="image/x-icon">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style.css">
    
    <?php if (function_exists('hasAdminPrivileges') && hasAdminPrivileges()): ?>
        <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/admin.css">
    <?php elseif (function_exists('hasCashierPrivileges') && hasCashierPrivileges()): ?>
        <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/cashier.css">
    <?php endif; ?>
    
    <style>
        /* Fallback styling if custom CSS files aren't loaded */
        body {
            font-family: 'Nunito', sans-serif;
            background-color: #f8f9fa;
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
        .sidebar .nav-link {
            font-weight: 500;
            color: #333;
            padding: 0.75rem 1rem;
            border-radius: 0.25rem;
            margin: 0 0.5rem;
        }
        .sidebar .nav-link.active {
            color: #2470dc;
            background-color: rgba(36, 112, 220, 0.1);
        }
        .sidebar .nav-link:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }
        .navbar-brand {
            padding-top: .75rem;
            padding-bottom: .75rem;
            font-size: 1rem;
            background-color: rgba(0, 0, 0, .25);
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .25);
        }
    </style>
</head>
<body>
    <!-- Top Navbar -->
    <header class="navbar navbar-dark sticky-top bg-primary flex-md-nowrap p-0 shadow">
        <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3" href="<?php echo SITE_URL; ?>">
            <i class="fas fa-bread-slice me-2"></i> <?php echo SITE_NAME; ?>
        </a>
        <button class="navbar-toggler d-md-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="w-100 d-none d-md-block"></div>
        
        <?php if (isLoggedIn()): ?>
            <div class="navbar-nav">
                <div class="nav-item text-nowrap">
                    <div class="dropdown">
                        <a class="nav-link px-3 dropdown-toggle text-white" href="#" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle"></i> 
                            <?php echo isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : htmlspecialchars($_SESSION['username']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/profile.php"><i class="fas fa-id-badge me-2"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/change_password.php"><i class="fas fa-key me-2"></i> Change Password</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="navbar-nav">
                <div class="nav-item text-nowrap">
                    <a class="nav-link px-3 text-white" href="<?php echo SITE_URL; ?>/auth/login.php"><i class="fas fa-sign-in-alt me-1"></i> Login</a>
                </div>
            </div>
        <?php endif; ?>
    </header>