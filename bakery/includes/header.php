<?php
// If the session is not already started, start it
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?><?php echo SITE_NAME; ?></title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style.css">
    <?php if (hasAdminPrivileges()): ?>
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/admin.css">
    <?php elseif (function_exists('hasCashierPrivileges') && hasCashierPrivileges()): ?>
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/cashier.css">
    <?php endif; ?>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
                <img src="<?php echo ASSETS_URL; ?>/images/bakery-logo.png" alt="<?php echo SITE_NAME; ?>" width="40" height="40" class="d-inline-block align-text-middle me-2">
                <?php echo SITE_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($pageTitle == 'Welcome') ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>">Home</a>
                    </li>
                    
                    <?php 
                    if (!function_exists('isLoggedIn')) {
                        function isLoggedIn() {
                            return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
                        }
                    }
                    
                    if (!function_exists('hasCashierPrivileges')) {
                        function hasCashierPrivileges() {
                            return isset($_SESSION['role']) && $_SESSION['role'] === 'cashier';
                        }
                    }
                    if (isLoggedIn()): ?>
                        <?php if (hasAdminPrivileges()): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($pageTitle == 'Dashboard') ? 'active' : ''; ?>" href="<?php echo ADMIN_URL; ?>">Dashboard</a>
                            </li>
                        <?php elseif (hasCashierPrivileges()): ?>
                            <li class="nav-item">
                                <a class="nav-link <?php echo ($pageTitle == 'Cashier Dashboard') ? 'active' : ''; ?>" href="<?php echo CASHIER_URL; ?>">Dashboard</a>
                            </li>
                        <?php endif; ?>
                        
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['name']); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/profile.php"><i class="fas fa-user me-2"></i> Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($pageTitle == 'Login') ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/auth/login.php">Login</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>