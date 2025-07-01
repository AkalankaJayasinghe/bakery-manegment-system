/**
 * Enhanced JavaScript for Bakery Management System
 */
document.addEventListener('DOMContentLoaded', function() {
    // Add fade-in animation to main content
    document.querySelector('main').classList.add('fade-in');
    
    // Initialize Bootstrap components
    initBootstrapComponents();
    
    // Add counter animation to dashboard stats
    animateCounters();
    
    // Initialize charts if they exist
    initCharts();
    
    // Handle sidebar toggle on mobile
    setupMobileMenu();
    
    // Setup form validations
    setupFormValidations();
    
    // Setup interactive features
    setupInteractiveFeatures();
    
    // Handle special pages
    handleSpecialPages();
});

/**
 * Initialize Bootstrap components
 */
function initBootstrapComponents() {
    // Tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl, {
            animation: true,
            delay: { show: 100, hide: 100 }
        });
    });
    
    // Popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function(popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl, {
            trigger: 'focus'
        });
    });
    
    // Toasts
    var toastElList = [].slice.call(document.querySelectorAll('.toast'));
    toastElList.map(function(toastEl) {
        return new bootstrap.Toast(toastEl, {
            autohide: true,
            delay: 5000
        }).show();
    });
}

/**
 * Animate counter numbers on dashboard
 */
function animateCounters() {
    const counters = document.querySelectorAll('.counter-number');
    
    counters.forEach(counter => {
        const target = +counter.getAttribute('data-target');
        const duration = 1500; // ms
        const step = target / (duration / 16); // 60fps
        
        let current = 0;
        const updateCounter = () => {
            current += step;
            if (current < target) {
                counter.textContent = Math.round(current).toLocaleString();
                requestAnimationFrame(updateCounter);
            } else {
                counter.textContent = target.toLocaleString();
            }
        };
        
        updateCounter();
    });
}

/**
 * Initialize charts for reports
 */
function initCharts() {
    // Sales chart
    const salesChartEl = document.getElementById('salesChart');
    if (salesChartEl) {
        const ctx = salesChartEl.getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(255, 107, 107, 0.4)');
        gradient.addColorStop(1, 'rgba(255, 107, 107, 0)');
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: salesData.labels,
                datasets: [{
                    label: 'Daily Sales',
                    data: salesData.values,
                    backgroundColor: gradient,
                    borderColor: '#ff6b6b',
                    borderWidth: 2,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#ff6b6b',
                    pointRadius: 4,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: 'Daily Sales Trend',
                        font: {
                            size: 16,
                            weight: 'bold'
                        },
                        padding: {
                            top: 10,
                            bottom: 20
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(45, 64, 89, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        padding: 12,
                        titleFont: {
                            weight: 'bold'
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 10
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            borderDash: [2, 2],
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    }
                }
            }
        });
    }
    
    // Product chart
    const productChartEl = document.getElementById('productChart');
    if (productChartEl) {
        const ctx = productChartEl.getContext('2d');
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: productData.labels,
                datasets: [{
                    label: 'Units Sold',
                    data: productData.values,
                    backgroundColor: '#4ecdc4',
                    borderColor: '#4ecdc4',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: 'Top Products by Units Sold',
                        font: {
                            size: 16,
                            weight: 'bold'
                        },
                        padding: {
                            top: 10,
                            bottom: 20
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            borderDash: [2, 2],
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    }
                }
            }
        });
    }
    
    // Category chart
    const categoryChartEl = document.getElementById('categoryChart');
    if (categoryChartEl) {
        const ctx = categoryChartEl.getContext('2d');
        
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: categoryData.labels,
                datasets: [{
                    data: categoryData.values,
                    backgroundColor: [
                        '#ff6b6b', '#4ecdc4', '#ffbe0b', '#2d4059', '#66bb6a',
                        '#ffa726', '#ef5350', '#42a5f5', '#ab47bc', '#26a69a'
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 15,
                            padding: 15
                        }
                    },
                    title: {
                        display: true,
                        text: 'Sales by Category',
                        font: {
                            size: 16,
                            weight: 'bold'
                        },
                        padding: {
                            top: 10,
                            bottom: 20
                        }
                    }
                }
            }
        });
    }
}

/**
 * Setup mobile menu toggle
 */
function setupMobileMenu() {
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    }
}

/**
 * Setup form validations and enhancements
 */
function setupFormValidations() {
    // Bootstrap form validation
    const forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
    
    // Password toggle
    const togglePassword = document.querySelector('#togglePassword');
    if (togglePassword) {
        const password = document.querySelector('#password');
        
        togglePassword.addEventListener('click', function() {
            // Toggle the type attribute
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            
            // Toggle the icon
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    }
    
    // File input preview
    const fileInputs = document.querySelectorAll('.custom-file-input');
    fileInputs.forEach(input => {
        input.addEventListener('change', function() {
            const fileName = this.files[0]?.name || 'No file chosen';
            this.nextElementSibling.textContent = fileName;
            
            // Image preview if it's an image
            const previewEl = document.querySelector('.img-preview');
            if (previewEl && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewEl.src = e.target.result;
                    previewEl.style.display = 'block';
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    });
}

/**
 * Setup interactive features throughout the application
 */
function setupInteractiveFeatures() {
    // Confirm dialog for delete actions
    document.querySelectorAll('.confirm-action').forEach(function(element) {
        element.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to perform this action? This cannot be undone.')) {
                e.preventDefault();
            }
        });
    });
    
    // Enhanced select boxes
    const selects = document.querySelectorAll('.form-select-enhanced');
    selects.forEach(select => {
        if (typeof Choices !== 'undefined') {
            new Choices(select, {
                searchEnabled: true,
                itemSelectText: '',
                shouldSort: false
            });
        }
    });
    
    // Datepickers
    const datepickers = document.querySelectorAll('.datepicker');
    datepickers.forEach(input => {
        if (typeof flatpickr !== 'undefined') {
            flatpickr(input, {
                dateFormat: "Y-m-d",
                allowInput: true
            });
        }
    });
    
    // Lazy loading images
    const lazyImages = document.querySelectorAll('.lazy-img');
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy-img');
                    imageObserver.unobserve(img);
                }
            });
        });
        
        lazyImages.forEach(img => imageObserver.observe(img));
    } else {
        // Fallback for browsers without IntersectionObserver
        lazyImages.forEach(img => {
            img.src = img.dataset.src;
            img.classList.remove('lazy-img');
        });
    }
}

/**
 * Handle special pages
 */
function handleSpecialPages() {
    // Billing page cart management
    setupBillingPage();
    
    // Print invoice functionality
    setupInvoicePrinting();
    
    // Report filters
    setupReportFilters();
}

/**
 * Setup billing page functionality
 */
function setupBillingPage() {
    const addToCartButtons = document.querySelectorAll('.add-to-cart');
    
    if (addToCartButtons.length) {
        addToCartButtons.forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.dataset.id;
                const productName = this.dataset.name;
                const productPrice = parseFloat(this.dataset.price);
                
                addToCart(productId, productName, productPrice);
                
                // Show toast notification
                const toastEl = document.getElementById('cartToast');
                if (toastEl) {
                    const toastBody = toastEl.querySelector('.toast-body');
                    toastBody.textContent = `Added ${productName} to cart`;
                    const toast = new bootstrap.Toast(toastEl);
                    toast.show();
                }
            });
        });
    }
}

/**
 * Setup invoice printing
 */
function setupInvoicePrinting() {
    const printInvoiceBtn = document.getElementById('printInvoice');
    
    if (printInvoiceBtn) {
        printInvoiceBtn.addEventListener('click', function() {
            window.print();
        });
    }
}

/**
 * Setup report filters
 */
function setupReportFilters() {
    const reportTypeSelect = document.getElementById('reportType');
    
    if (reportTypeSelect) {
        reportTypeSelect.addEventListener('change', function() {
            const dateFields = document.getElementById('dateFields');
            
            // Hide date fields for inventory reports
            if (this.value === 'inventory' || this.value === 'low_stock') {
                dateFields.style.display = 'none';
            } else {
                dateFields.style.display = 'flex';
            }
        });
        
        // Trigger on load
        reportTypeSelect.dispatchEvent(new Event('change'));
    }
}

/**
 * Show notification
 * @param {string} message - The message to display
 * @param {string} type - The type of notification (success, error, warning, info)
 */
function showNotification(message, type = 'info') {
    const notificationContainer = document.getElementById('notificationContainer') || createNotificationContainer();
    
    const notification = document.createElement('div');
    notification.className = `notification notification-${type} fade-in`;
    notification.innerHTML = `
        <div class="notification-icon">
            <i class="fas ${getIconForType(type)}"></i>
        </div>
        <div class="notification-content">
            ${message}
        </div>
    `;
    
    notificationContainer.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        notification.classList.add('fade-out');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 5000);
}

/**
 * Create notification container if it doesn't exist
 */
function createNotificationContainer() {
    const container = document.createElement('div');
    container.id = 'notificationContainer';
    container.className = 'notification-container';
    document.body.appendChild(container);
    return container;
}

/**
 * Get icon class based on notification type
 */
function getIconForType(type) {
    switch (type) {
        case 'success': return 'fa-check-circle';
        case 'error': return 'fa-exclamation-circle';
        case 'warning': return 'fa-exclamation-triangle';
        default: return 'fa-info-circle';
    }
}

/**
 * Format currency with appropriate symbol
 * @param {number} amount - The amount to format
 * @param {string} currency - The currency code
 * @returns {string} Formatted currency string
 */
function formatCurrency(amount, currency = 'USD') {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency
    }).format(amount);
}

/**
 * Format date in user-friendly format
 * @param {string} dateString - The date string to format
 * @returns {string} Formatted date string
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}