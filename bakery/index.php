<?php
session_start();
require_once 'config/database.php';
require_once 'config/constants.php';
require_once 'includes/functions.php';

// Page title
$pageTitle = "Welcome";

// Include header
include_once 'includes/header.php';
?>

<div class="landing-page">
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="hero-content">
                        <h1 class="hero-title">Bakery Management System</h1>
                        <p class="hero-subtitle">Efficiently manage your bakery business with our comprehensive system</p>
                        <div class="hero-buttons">
                            <?php if (isLoggedIn()): ?>
                                <?php if (hasAdminPrivileges()): ?>
                                    <a href="<?php echo ADMIN_URL; ?>" class="btn btn-primary btn-lg">Go to Dashboard</a>
                                <?php elseif (hasCashierPrivileges()): ?>
                                    <a href="<?php echo CASHIER_URL; ?>" class="btn btn-primary btn-lg">Go to Cashier</a>
                                <?php endif; ?>
                            <?php else: ?>
                                <a href="<?php echo SITE_URL; ?>/auth/login.php" class="btn btn-primary btn-lg">Login to System</a>
                            <?php endif; ?>
                            <a href="#features" class="btn btn-outline-primary btn-lg">Learn More</a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="hero-image">
                        <img src="<?php echo ASSETS_URL; ?>/images/bakery-hero.png" alt="Bakery Management" class="img-fluid">
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Features Section -->
    <section class="features-section" id="features">
        <div class="container">
            <div class="section-header text-center">
                <h2 class="section-title">Key Features</h2>
                <p class="section-subtitle">Everything you need to manage your bakery business efficiently</p>
            </div>
            
            <div class="row feature-cards">
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-cash-register"></i>
                        </div>
                        <h3 class="feature-title">Point of Sale</h3>
                        <p class="feature-description">Quick and easy sales processing with a user-friendly interface. Manage customer orders, apply discounts, and generate receipts.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <h3 class="feature-title">Inventory Management</h3>
                        <p class="feature-description">Keep track of your bakery items and get low stock alerts. Manage product categories, prices, and stock levels.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3 class="feature-title">Sales Reports</h3>
                        <p class="feature-description">Generate comprehensive sales reports to analyze your business. Track daily, monthly, and product-wise sales performance.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3 class="feature-title">User Management</h3>
                        <p class="feature-description">Control access with different user roles. Assign admin and cashier privileges with appropriate permissions.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                        <h3 class="feature-title">Invoice Generation</h3>
                        <p class="feature-description">Create and print professional invoices for customers. Keep track of all transactions with detailed records.</p>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-cogs"></i>
                        </div>
                        <h3 class="feature-title">Easy Configuration</h3>
                        <p class="feature-description">Customize the system according to your bakery's needs. Set tax rates, discounts, and other business parameters.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Call to Action Section -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-content text-center">
                <h2 class="cta-title">Ready to streamline your bakery operations?</h2>
                <p class="cta-text">Join hundreds of bakery owners who are already using our system</p>
                <?php if (!isLoggedIn()): ?>
                    <a href="<?php echo SITE_URL; ?>/auth/login.php" class="btn btn-light btn-lg">Get Started Now</a>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>

<style>
    /* Landing Page Specific Styles */
    :root {
        --primary-color: #ff6b6b;
        --secondary-color: #4ecdc4;
        --dark-color: #2d4059;
        --light-color: #f7f9fb;
        --accent-color: #ffbe0b;
    }
    
    body {
        font-family: 'Nunito', sans-serif;
        color: #333;
        background-color: var(--light-color);
    }
    
    /* Hero Section */
    .hero-section {
        padding: 80px 0;
        background-color: white;
        position: relative;
        overflow: hidden;
    }
    
    .hero-section::before {
        content: '';
        position: absolute;
        top: -100px;
        right: -100px;
        width: 300px;
        height: 300px;
        border-radius: 50%;
        background-color: rgba(255, 107, 107, 0.1);
        z-index: 0;
    }
    
    .hero-section::after {
        content: '';
        position: absolute;
        bottom: -100px;
        left: -100px;
        width: 300px;
        height: 300px;
        border-radius: 50%;
        background-color: rgba(78, 205, 196, 0.1);
        z-index: 0;
    }
    
    .hero-content {
        position: relative;
        z-index: 1;
        animation: fadeInUp 0.8s ease;
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .hero-title {
        font-size: 3rem;
        font-weight: 800;
        color: var(--dark-color);
        margin-bottom: 20px;
        line-height: 1.2;
    }
    
    .hero-subtitle {
        font-size: 1.2rem;
        color: #666;
        margin-bottom: 30px;
        line-height: 1.5;
    }
    
    .hero-buttons {
        display: flex;
        gap: 15px;
        margin-bottom: 30px;
    }
    
    .btn-primary {
        background: linear-gradient(45deg, var(--primary-color), #ff8a8a);
        border: none;
        border-radius: 30px;
        padding: 12px 30px;
        font-weight: 700;
        box-shadow: 0 4px 10px rgba(255, 107, 107, 0.3);
        transition: all 0.3s ease;
    }
    
    .btn-primary:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 15px rgba(255, 107, 107, 0.4);
        background: linear-gradient(45deg, #ff5252, #ff7676);
    }
    
    .btn-outline-primary {
        background: transparent;
        border: 2px solid var(--primary-color);
        color: var(--primary-color);
        border-radius: 30px;
        padding: 12px 30px;
        font-weight: 700;
        transition: all 0.3s ease;
    }
    
    .btn-outline-primary:hover {
        background-color: rgba(255, 107, 107, 0.1);
        border-color: var(--primary-color);
        color: var(--primary-color);
        transform: translateY(-3px);
    }
    
    .hero-image {
        position: relative;
        z-index: 1;
        animation: float 3s ease-in-out infinite alternate;
    }
    
    @keyframes float {
        from { transform: translateY(0); }
        to { transform: translateY(-15px); }
    }
    
    /* Features Section */
    .features-section {
        padding: 80px 0;
        background-color: #f8f9fa;
    }
    
    .section-header {
        margin-bottom: 50px;
    }
    
    .section-title {
        font-size: 2.5rem;
        font-weight: 700;
        color: var(--dark-color);
        margin-bottom: 15px;
    }
    
    .section-subtitle {
        font-size: 1.1rem;
        color: #666;
        max-width: 700px;
        margin: 0 auto;
    }
    
    .feature-card {
        background-color: white;
        border-radius: 15px;
        padding: 30px;
        height: 100%;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        transition: all 0.3s ease;
    }
    
    .feature-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }
    
    .feature-icon {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        background: linear-gradient(45deg, var(--primary-color), #ff8a8a);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 20px;
        font-size: 1.5rem;
        color: white;
    }
    
    .feature-title {
        font-size: 1.3rem;
        font-weight: 700;
        color: var(--dark-color);
        margin-bottom: 15px;
    }
    
    .feature-description {
        color: #666;
        line-height: 1.6;
    }
    
    /* CTA Section */
    .cta-section {
        padding: 80px 0;
        background: linear-gradient(45deg, var(--primary-color), #ff8a8a);
        color: white;
        text-align: center;
    }
    
    .cta-title {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 15px;
    }
    
    .cta-text {
        font-size: 1.2rem;
        margin-bottom: 30px;
        opacity: 0.9;
    }
    
    .btn-light {
        background-color: white;
        color: var(--primary-color);
        font-weight: 700;
        border-radius: 30px;
        padding: 12px 30px;
        border: none;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }
    
    .btn-light:hover {
        background-color: white;
        color: var(--primary-color);
        transform: translateY(-3px);
        box-shadow: 0 6px 15px rgba(0, 0, 0, 0.2);
    }
    
    /* Responsive adjustments */
    @media (max-width: 991px) {
        .hero-section {
            text-align: center;
            padding: 60px 0;
        }
        
        .hero-buttons {
            justify-content: center;
        }
        
        .hero-image {
            margin-top: 40px;
            max-width: 80%;
            margin-left: auto;
            margin-right: auto;
        }
        
        .hero-title {
            font-size: 2.5rem;
        }
    }
    
    @media (max-width: 767px) {
        .section-title {
            font-size: 2rem;
        }
        
        .cta-title {
            font-size: 1.8rem;
        }
    }
</style>

<?php
// Include footer
include_once 'includes/footer.php';
?>