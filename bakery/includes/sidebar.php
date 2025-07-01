<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <?php if (isLoggedIn()): ?>
            <?php if (hasAdminPrivileges()): ?>
                <!-- Admin Navigation -->
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>" href="<?php echo ADMIN_URL; ?>">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'products.php' || $current_page == 'add_product.php' || $current_page == 'edit_product.php') ? 'active' : ''; ?>" href="<?php echo ADMIN_URL; ?>/products.php">
                            <i class="fas fa-box me-2"></i> Products
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'categories.php') ? 'active' : ''; ?>" href="<?php echo ADMIN_URL; ?>/categories.php">
                            <i class="fas fa-tags me-2"></i> Categories
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'sales.php' || $current_page == 'view_sale.php') ? 'active' : ''; ?>" href="<?php echo ADMIN_URL; ?>/sales.php">
                            <i class="fas fa-shopping-cart me-2"></i> Sales
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>" href="<?php echo ADMIN_URL; ?>/reports.php">
                            <i class="fas fa-chart-bar me-2"></i> Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'users.php' || $current_page == 'add_user.php' || $current_page == 'edit_user.php') ? 'active' : ''; ?>" href="<?php echo ADMIN_URL; ?>/users.php">
                            <i class="fas fa-users me-2"></i> Users
                        </a>
                    </li>
                </ul>

                <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                    <span>System</span>
                </h6>
                <ul class="nav flex-column mb-2">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>" href="<?php echo ADMIN_URL; ?>/settings.php">
                            <i class="fas fa-cog me-2"></i> Settings
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'activity_logs.php') ? 'active' : ''; ?>" href="<?php echo ADMIN_URL; ?>/activity_logs.php">
                            <i class="fas fa-history me-2"></i> Activity Logs
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo CASHIER_URL; ?>">
                            <i class="fas fa-cash-register me-2"></i> POS System
                        </a>
                    </li>
                </ul>
            <?php elseif (hasCashierPrivileges()): ?>
                <!-- Cashier Navigation -->
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>" href="<?php echo CASHIER_URL; ?>">
                            <i class="fas fa-cash-register me-2"></i> POS
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'sales.php' || $current_page == 'view_sale.php') ? 'active' : ''; ?>" href="<?php echo CASHIER_URL; ?>/sales.php">
                            <i class="fas fa-list-alt me-2"></i> Sales History
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'products.php') ? 'active' : ''; ?>" href="<?php echo CASHIER_URL; ?>/products.php">
                            <i class="fas fa-box me-2"></i> Products
                        </a>
                    </li>
                </ul>
            <?php endif; ?>
            
            <!-- Common Navigation Footer -->
            <div class="mt-auto p-3">
                <div class="d-grid gap-2">
                    <a href="<?php echo SITE_URL; ?>/auth/logout.php" class="btn btn-danger">
                        <i class="fas fa-sign-out-alt me-2"></i> Logout
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Public Navigation -->
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>">
                        <i class="fas fa-home me-2"></i> Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'login.php') ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/auth/login.php">
                        <i class="fas fa-sign-in-alt me-2"></i> Login
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($current_page == 'signup.php') ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/auth/signup.php">
                        <i class="fas fa-user-plus me-2"></i> Sign Up
                    </a>
                </li>
            </ul>
        <?php endif; ?>
    </div>
</nav>