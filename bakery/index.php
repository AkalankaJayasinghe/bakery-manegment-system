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
                        <h1 class="hero-title" data-aos="fade-up" data-aos-delay="100">Bakery Management System</h1>
                        <p class="hero-subtitle" data-aos="fade-up" data-aos-delay="200">Efficiently manage your bakery business with our comprehensive system</p>
                        <div class="hero-buttons" data-aos="fade-up" data-aos-delay="300">
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
                        <!-- Bakery-themed image -->
                        <img src="https://images.unsplash.com/photo-1568254183919-78a4f43a2877?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80" alt="Bakery Management" class="img-fluid hero-main-img">
                        <!-- Additional decorative images for attractiveness -->
                        <div class="hero-decor">
                            <img src="https://images.unsplash.com/photo-1587241321921-91a834d6d191?ixlib=rb-4.0.3&auto=format&fit=crop&w=200&q=80" alt="Fresh Bread" class="decor-img decor-1">
                            <img src="https://images.unsplash.com/photo-1599785209707-a456fc1337bb?ixlib=rb-4.0.3&auto=format&fit=crop&w=200&q=80" alt="Pastries" class="decor-img decor-2">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Particle background for advanced JS effect -->
        <div id="particles-js" class="particles-container"></div>
    </section>
    
    <!-- Features Section -->
    <section class="features-section" id="features">
        <div class="container">
            <div class="section-header text-center">
                <h2 class="section-title" data-aos="fade-up">Key Features</h2>
                <p class="section-subtitle" data-aos="fade-up" data-aos-delay="100">Everything you need to manage your bakery business efficiently</p>
            </div>
            
            <div class="row feature-cards">
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card" data-aos="zoom-in" data-aos-delay="100">
                        <div class="feature-icon">
                            <i class="fas fa-cash-register"></i>
                        </div>
                        <h3 class="feature-title">Point of Sale</h3>
                        <p class="feature-description">Quick and easy sales processing with a user-friendly interface. Manage customer orders, apply discounts, and generate receipts.</p>
                        <!-- Feature image -->
                        <img src="https://images.unsplash.com/photo-1588625424399-53213a85362a?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80" alt="POS System" class="feature-img">
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card" data-aos="zoom-in" data-aos-delay="200">
                        <div class="feature-icon">
                            <i class="fas fa-boxes"></i>
                        </div>
                        <h3 class="feature-title">Inventory Management</h3>
                        <p class="feature-description">Keep track of your bakery items and get low stock alerts. Manage product categories, prices, and stock levels.</p>
                        <!-- Feature image -->
                        <img src="https://images.unsplash.com/photo-1578985545062-69928b1d9587?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80" alt="Inventory" class="feature-img">
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card" data-aos="zoom-in" data-aos-delay="300">
                        <div class="feature-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3 class="feature-title">Sales Reports</h3>
                        <p class="feature-description">Generate comprehensive sales reports to analyze your business. Track daily, monthly, and product-wise sales performance.</p>
                        <!-- Feature image -->
                        <img src="https://images.unsplash.com/photo-1504868584819-f8e8b4b6d7e3?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80" alt="Reports" class="feature-img">
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card" data-aos="zoom-in" data-aos-delay="400">
                        <div class="feature-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3 class="feature-title">User Management</h3>
                        <p class="feature-description">Control access with different user roles. Assign admin and cashier privileges with appropriate permissions.</p>
                        <!-- Feature image -->
                        <img src="https://images.unsplash.com/photo-1556157382-97eda2d62296?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80" alt="Users" class="feature-img">
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card" data-aos="zoom-in" data-aos-delay="500">
                        <div class="feature-icon">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                        <h3 class="feature-title">Invoice Generation</h3>
                        <p class="feature-description">Create and print professional invoices for customers. Keep track of all transactions with detailed records.</p>
                        <!-- Feature image -->
                        <img src="https://images.unsplash.com/photo-1586348943529-beaae6c28db9?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80" alt="Invoice" class="feature-img">
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-card" data-aos="zoom-in" data-aos-delay="600">
                        <div class="feature-icon">
                            <i class="fas fa-cogs"></i>
                        </div>
                        <h3 class="feature-title">Easy Configuration</h3>
                        <p class="feature-description">Customize the system according to your bakery's needs. Set tax rates, discounts, and other business parameters.</p>
                        <!-- Feature image -->
                        <img src="https://images.unsplash.com/photo-1506784983877-45594efa4cbe?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80" alt="Settings" class="feature-img">
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Call to Action Section -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-content text-center" data-aos="fade-up">
                <h2 class="cta-title">Ready to streamline your bakery operations?</h2>
                <p class="cta-text">Join hundreds of bakery owners who are already using our system</p>
                <?php if (!isLoggedIn()): ?>
                    <a href="<?php echo SITE_URL; ?>/auth/login.php" class="btn btn-light btn-lg">Get Started Now</a>
                <?php endif; ?>
                <!-- CTA decorative image -->
                <img src="https://images.unsplash.com/photo-1509440159596-0249088772ff?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="Bakery Team" class="cta-img">
            </div>
        </div>
    </section>
</div>

<!-- AOS Library for Scroll Animations (Advanced JS) -->
<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

<!-- Particles.js for Hero Background (Advanced JS Effect) -->
<script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>

<style>
    /* Enhanced Landing Page Styles - More Attractive & Clear */
    @import url('https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&family=Poppins:wght@400;600;700&display=swap'); /* Google Fonts for clarity */

    :root {
        --primary-color: #ff6b6b;
        --secondary-color: #4ecdc4;
        --dark-color: #2d4059;
        --light-color: #f7f9fb;
        --accent-color: #ffbe0b;
        --text-primary: #1a1a1a;
        --text-secondary: #6c757d;
    }
    
    body {
        font-family: 'Nunito', sans-serif;
        color: var(--text-primary);
        background-color: var(--light-color);
        line-height: 1.6;
        overflow-x: hidden; /* Clear page flow */
    }
    
    /* Hero Section - Enhanced with Particles */
    .hero-section {
        padding: 100px 0;
        background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
        position: relative;
        overflow: hidden;
    }
    
    .particles-container {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 0;
        opacity: 0.6;
    }
    
    .hero-content {
        position: relative;
        z-index: 2;
    }
    
    .hero-title {
        font-size: 3.5rem;
        font-weight: 800;
        background: linear-gradient(45deg, var(--primary-color), var(--accent-color));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 20px;
        line-height: 1.1;
        text-shadow: 0 2px 4px rgba(0,0,0,0.1); /* Clarity enhancement */
    }
    
    .hero-subtitle {
        font-size: 1.3rem;
        color: var(--text-secondary);
        margin-bottom: 30px;
        font-weight: 400;
    }
    
    .hero-buttons {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
        justify-content: flex-start;
    }
    
    .btn {
        border-radius: 50px;
        padding: 15px 35px;
        font-weight: 600;
        text-decoration: none;
        display: inline-block;
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); /* Smooth bounce */
        font-size: 1rem;
        border: none;
        cursor: pointer;
    }
    
    .btn-primary {
        background: linear-gradient(45deg, var(--primary-color), var(--accent-color));
        color: white;
        box-shadow: 0 8px 25px rgba(255, 107, 107, 0.3);
    }
    
    .btn-primary:hover {
        transform: translateY(-5px) scale(1.05);
        box-shadow: 0 12px 35px rgba(255, 107, 107, 0.4);
    }
    
    .btn-outline-primary {
        background: transparent;
        border: 2px solid var(--primary-color);
        color: var(--primary-color);
    }
    
    .btn-outline-primary:hover {
        background: var(--primary-color);
        color: white;
        transform: translateY(-5px) scale(1.05);
    }
    
    .hero-image {
        position: relative;
        z-index: 2;
    }
    
    .hero-main-img {
        border-radius: 20px;
        box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        transition: transform 0.3s ease;
    }
    
    .hero-main-img:hover {
        transform: scale(1.02);
    }
    
    .hero-decor {
        position: absolute;
        top: -20px;
        right: -20px;
    }
    
    .decor-img {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        position: absolute;
        animation: floatDecor 4s ease-in-out infinite;
    }
    
    .decor-1 { top: 0; left: 0; animation-delay: 0s; }
    .decor-2 { top: 60px; right: 0; animation-delay: 2s; }
    
    @keyframes floatDecor {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        50% { transform: translateY(-10px) rotate(5deg); }
    }
    
    /* Features Section - Enhanced Clarity */
    .features-section {
        padding: 100px 0;
        background: linear-gradient(to bottom, #f8f9fa 0%, white 100%);
    }
    
    .section-title {
        font-size: 2.8rem;
        font-weight: 700;
        color: var(--dark-color);
        margin-bottom: 15px;
        position: relative;
    }
    
    .section-title::after {
        content: '';
        position: absolute;
        bottom: -10px;
        left: 50%;
        transform: translateX(-50%);
        width: 60px;
        height: 4px;
        background: linear-gradient(45deg, var(--primary-color), var(--accent-color));
        border-radius: 2px;
    }
    
    .section-subtitle {
        font-size: 1.2rem;
        color: var(--text-secondary);
        max-width: 600px;
        margin: 0 auto;
        font-weight: 300;
    }
    
    .feature-card {
        background: white;
        border-radius: 20px;
        padding: 40px 30px;
        height: 100%;
        box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        transition: all 0.4s ease;
        overflow: hidden;
        position: relative;
    }
    
    .feature-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(45deg, var(--primary-color), var(--accent-color));
        transform: scaleX(0);
        transition: transform 0.4s ease;
    }
    
    .feature-card:hover::before {
        transform: scaleX(1);
    }
    
    .feature-card:hover {
        transform: translateY(-15px);
        box-shadow: 0 20px 50px rgba(0,0,0,0.15);
    }
    
    .feature-icon {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: linear-gradient(45deg, var(--primary-color), var(--accent-color));
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 25px;
        font-size: 1.8rem;
        color: white;
        box-shadow: 0 8px 20px rgba(255, 107, 107, 0.3);
    }
    
    .feature-title {
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--dark-color);
        margin-bottom: 15px;
        text-align: center;
    }
    
    .feature-description {
        color: var(--text-secondary);
        line-height: 1.7;
        text-align: center;
        margin-bottom: 20px;
    }
    
    .feature-img {
        width: 100%;
        height: 150px;
        object-fit: cover;
        border-radius: 10px;
        margin-top: 20px;
        transition: transform 0.3s ease;
    }
    
    .feature-img:hover {
        transform: scale(1.05);
    }
    
    /* CTA Section - Enhanced */
    .cta-section {
        padding: 100px 0;
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        color: white;
        text-align: center;
        position: relative;
    }
    
    .cta-title {
        font-size: 2.8rem;
        font-weight: 700;
        margin-bottom: 15px;
        text-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .cta-text {
        font-size: 1.3rem;
        margin-bottom: 30px;
        opacity: 0.95;
        font-weight: 300;
    }
    
    .btn-light {
        background: rgba(255,255,255,0.95);
        color: var(--primary-color);
        font-weight: 700;
        box-shadow: 0 8px 25px rgba(0,0,0,0.2);
    }
    
    .btn-light:hover {
        background: white;
        transform: translateY(-5px) scale(1.05);
        box-shadow: 0 12px 35px rgba(0,0,0,0.3);
    }
    
    .cta-img {
        margin-top: 40px;
        border-radius: 20px;
        max-width: 600px;
        width: 100%;
        box-shadow: 0 15px 40px rgba(0,0,0,0.2);
        opacity: 0.8;
    }
    
    /* Responsive - Ensure Clarity on Mobile */
    @media (max-width: 991px) {
        .hero-section { padding: 80px 0; text-align: center; }
        .hero-buttons { justify-content: center; }
        .hero-title { font-size: 2.8rem; }
        .hero-subtitle { font-size: 1.1rem; }
        .hero-decor { display: none; } /* Hide decor on small screens for clarity */
    }
    
    @media (max-width: 767px) {
        .section-title, .cta-title { font-size: 2.2rem; }
        .hero-buttons { gap: 10px; }
        .btn { padding: 12px 25px; font-size: 0.9rem; }
        .feature-card { padding: 30px 20px; }
    }
</style>

<script>
// Initialize AOS for advanced scroll animations
AOS.init({
    duration: 1000,
    once: true,
    offset: 100
});

// Particles.js for attractive hero background effect
particlesJS('particles-js', {
    particles: {
        number: { value: 50, density: { enable: true, value_area: 800 } },
        color: { value: ['#ff6b6b', '#4ecdc4', '#ffbe0b'] },
        shape: { type: 'circle' },
        opacity: { value: 0.5, random: true },
        size: { value: 3, random: true },
        line_linked: { enable: true, distance: 150, color: '#ff6b6b', opacity: 0.4, width: 1 },
        move: { enable: true, speed: 2, direction: 'none', random: false, straight: false, out_mode: 'out', bounce: false }
    },
    interactivity: {
        detect_on: 'canvas',
        events: { onhover: { enable: true, mode: 'repulse' }, onclick: { enable: true, mode: 'push' }, resize: true },
        modes: { repulse: { distance: 100, duration: 0.4 }, push: { particles_nb: 4 } }
    },
    retina_detect: true
});

// Smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        document.querySelector(this.getAttribute('href')).scrollIntoView({
            behavior: 'smooth'
        });
    });
});

// Typing effect for hero title (advanced JS)
function typeWriter(element, text, speed = 100) {
    let i = 0;
    element.innerHTML = '';
    function type() {
        if (i < text.length) {
            element.innerHTML += text.charAt(i);
            i++;
            setTimeout(type, speed);
        }
    }
    type();
}

// Initialize typing effect after page load
window.addEventListener('load', () => {
    const title = document.querySelector('.hero-title');
    const originalText = title.textContent;
    typeWriter(title, originalText, 80);
});
</script>

<?php
// Include footer
include_once 'includes/footer.php';
?>