/**
 * Cashier/POS JavaScript file for Bakery Management System
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize cart
    let cart = [];
    let taxRate = parseFloat(document.getElementById('tax_rate')?.value || 10);
    let discountPercent = 0;
    let discountAmount = 0;
    let discountType = 'percentage'; // percentage or fixed
    
    // DOM elements
    const cartItemsContainer = document.getElementById('cart-items');
    const subtotalElement = document.getElementById('subtotal');
    const subtotalInput = document.getElementById('subtotal_input');
    const taxAmountElement = document.getElementById('tax_amount');
    const taxAmountInput = document.getElementById('tax_amount_input');
    const discountAmountElement = document.getElementById('discount_amount');
    const discountAmountInput = document.getElementById('discount_amount_input');
    const totalAmountElement = document.getElementById('total_amount');
    const totalAmountInput = document.getElementById('total_amount_input');
    const saveOrderBtn = document.getElementById('save_order_btn');
    const cartItemsInput = document.getElementById('cart_items_input');
    
    // Add product to cart
    window.addToCart = function(product) {
        // Check if product is already in cart
        const existingItemIndex = cart.findIndex(item => item.id === product.id);
        
        if (existingItemIndex !== -1) {
            // Product exists in cart, increment quantity
            if (cart[existingItemIndex].quantity < cart[existingItemIndex].max_quantity) {
                cart[existingItemIndex].quantity += 1;
                updateCartDisplay();
            } else {
                alert('Cannot add more of this product. Maximum stock reached.');
            }
        } else {
            // Add new product to cart
            cart.push({
                id: product.id,
                name: product.name,
                price: parseFloat(product.price),
                quantity: 1,
                max_quantity: parseInt(product.quantity)
            });
            updateCartDisplay();
        }
    };
    
    // Remove item from cart
    window.removeFromCart = function(index) {
        cart.splice(index, 1);
        updateCartDisplay();
    };
    
    // Update quantity of an item in cart
    window.updateQuantity = function(index, newQuantity) {
        newQuantity = parseInt(newQuantity);
        if (isNaN(newQuantity) || newQuantity < 1) {
            newQuantity = 1;
        } else if (newQuantity > cart[index].max_quantity) {
            newQuantity = cart[index].max_quantity;
            alert('Maximum available quantity for this product is ' + cart[index].max_quantity);
        }
        
        cart[index].quantity = newQuantity;
        updateCartDisplay();
    };
    
    // Update the cart display
    function updateCartDisplay() {
        if (!cartItemsContainer) return;
        
        if (cart.length === 0) {
            cartItemsContainer.innerHTML = `
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-shopping-cart fa-2x mb-2"></i>
                    <p>Cart is empty. Add products to continue.</p>
                </div>
            `;
            if (saveOrderBtn) {
                saveOrderBtn.disabled = true;
            }
        } else {
            let cartHTML = '';
            
            cart.forEach((item, index) => {
                const itemTotal = item.price * item.quantity;
                
                cartHTML += `
                    <div class="cart-item">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong>${item.name}</strong>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFromCart(${index})">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small>${currencySymbol}${item.price.toFixed(2)} x</small>
                                <input type="number" min="1" max="${item.max_quantity}" value="${item.quantity}" 
                                       class="item-quantity ms-1" onchange="updateQuantity(${index}, parseInt(this.value))">
                            </div>
                            <strong>${currencySymbol}${itemTotal.toFixed(2)}</strong>
                        </div>
                    </div>
                `;
            });
            
            cartItemsContainer.innerHTML = cartHTML;
            if (saveOrderBtn) {
                saveOrderBtn.disabled = false;
            }
        }
        
        // Update the cart summary
        updateCartSummary();
        
        // Update hidden input for form submission
        if (cartItemsInput) {
            cartItemsInput.value = JSON.stringify(cart);
        }
    }
    
    // Update cart summary (subtotal, tax, discount, total)
    function updateCartSummary() {
        // Calculate subtotal
        const subtotal = cart.reduce((total, item) => total + (item.price * item.quantity), 0);
        
        // Get tax rate from input if available
        const taxRateInput = document.getElementById('tax_rate');
        if (taxRateInput) {
            taxRate = parseFloat(taxRateInput.value) || 0;
        }
        
        // Calculate tax amount
        const taxAmount = subtotal * (taxRate / 100);
        
        // Calculate discount
        if (discountType === 'percentage') {
            discountAmount = subtotal * (discountPercent / 100);
        } // else discountAmount is already set for fixed discount
        
        // Calculate total
        const totalAmount = subtotal + taxAmount - discountAmount;
        
        // Update display elements
        if (subtotalElement) {
            subtotalElement.textContent = currencySymbol + subtotal.toFixed(2);
        }
        if (taxAmountElement) {
            taxAmountElement.textContent = currencySymbol + taxAmount.toFixed(2);
        }
        if (discountAmountElement) {
            discountAmountElement.textContent = currencySymbol + discountAmount.toFixed(2);
        }
        if (totalAmountElement) {
            totalAmountElement.textContent = currencySymbol + totalAmount.toFixed(2);
        }
        
        // Update hidden inputs for form submission
        if (subtotalInput) {
            subtotalInput.value = subtotal.toFixed(2);
        }
        if (taxAmountInput) {
            taxAmountInput.value = taxAmount.toFixed(2);
        }
        if (discountAmountInput) {
            discountAmountInput.value = discountAmount.toFixed(2);
        }
        if (totalAmountInput) {
            totalAmountInput.value = totalAmount.toFixed(2);
        }
    }
    
    // Clear the cart
    window.clearCart = function() {
        if (confirm('Are you sure you want to clear the cart?')) {
            cart = [];
            updateCartDisplay();
        }
    };
    
    // Add event listeners
    
    // Category filter
    const categoryFilter = document.getElementById('categoryFilter');
    if (categoryFilter) {
        categoryFilter.addEventListener('change', function() {
            const selectedCategory = this.value;
            const categorySections = document.querySelectorAll('.category-section');
            
            if (selectedCategory === '') {
                // Show all categories
                categorySections.forEach(section => {
                    section.style.display = 'block';
                });
            } else {
                // Show only the selected category
                categorySections.forEach(section => {
                    if (section.dataset.category === selectedCategory) {
                        section.style.display = 'block';
                    } else {
                        section.style.display = 'none';
                    }
                });
            }
        });
    }
    
    // Tax rate change
    const taxRateInput = document.getElementById('tax_rate');
    if (taxRateInput) {
        taxRateInput.addEventListener('change', function() {
            updateCartSummary();
        });
    }
    
    // Discount buttons
    document.querySelectorAll('.discount-btn').forEach(button => {
        button.addEventListener('click', function() {
            // Update active button state
            document.querySelectorAll('.discount-btn').forEach(btn => {
                btn.classList.remove('active', 'btn-secondary');
                btn.classList.add('btn-outline-secondary');
            });
            this.classList.remove('btn-outline-secondary');
            this.classList.add('active', 'btn-secondary');
            
            // Update discount percentage
            discountPercent = parseFloat(this.dataset.value);
            discountType = 'percentage';
            const discountPercentInput = document.getElementById('discount_percent');
            if (discountPercentInput) {
                discountPercentInput.value = discountPercent;
            }
            
            // Update cart summary
            updateCartSummary();
        });
    });
    
    // Custom discount input
    const discountValueInput = document.getElementById('discount_value');
    const discountTypeSelect = document.getElementById('discount_type');
    
    if (discountValueInput && discountTypeSelect) {
        discountValueInput.addEventListener('input', function() {
            const value = parseFloat(this.value) || 0;
            discountType = discountTypeSelect.value;
            
            if (discountType === 'percentage') {
                discountPercent = Math.min(value, 100); // Cap at 100%
                this.value = discountPercent;
            } else {
                discountAmount = Math.max(value, 0); // Ensure positive value
                this.value = discountAmount;
            }
            
            updateCartSummary();
        });
        
        discountTypeSelect.addEventListener('change', function() {
            discountType = this.value;
            updateCartSummary();
        });
    }
    
    // Product search
    const productSearch = document.getElementById('productSearch');
    const searchResults = document.getElementById('searchResults');
    
    if (productSearch && searchResults) {
        productSearch.addEventListener('input', function() {
            const searchTerm = this.value.trim().toLowerCase();
            
            if (searchTerm.length < 2) {
                searchResults.style.display = 'none';
                return;
            }
            
            // Filter products based on search term
            // This assumes productsData is defined globally (from PHP)
            const matchingProducts = (typeof productsData !== 'undefined' ? productsData : []).filter(product => {
                return product.name.toLowerCase().includes(searchTerm) || 
                       (product.barcode && product.barcode.includes(searchTerm));
            });
            
            // Display search results
            if (matchingProducts.length > 0) {
                let resultsHTML = '';
                
                matchingProducts.forEach(product => {
                    resultsHTML += `
                        <div class="search-result-item" onclick="addToCart(${JSON.stringify(product).replace(/"/g, '&quot;')})">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>${product.name}</span>
                                <span class="badge bg-primary">${currencySymbol}${parseFloat(product.price).toFixed(2)}</span>
                            </div>
                            <small class="text-muted">${product.category_name || 'Uncategorized'} - Stock: ${product.quantity}</small>
                        </div>
                    `;
                });
                
                searchResults.innerHTML = resultsHTML;
                searchResults.style.display = 'block';
            } else {
                searchResults.innerHTML = '<div class="p-3 text-center">No products found</div>';
                searchResults.style.display = 'block';
            }
        });
        
        // Hide search results when clicking outside
        document.addEventListener('click', function(event) {
            if (!productSearch.contains(event.target) && !searchResults.contains(event.target)) {
                searchResults.style.display = 'none';
            }
        });
    }
    
    // Billing form validation
    const billingForm = document.getElementById('billingForm');
    if (billingForm) {
        billingForm.addEventListener('submit', function(e) {
            if (cart.length === 0) {
                e.preventDefault();
                alert('Cart is empty. Please add products to the cart.');
                return false;
            }
            
            return true;
        });
    }
    
    // Handle cash payment change calculation
    const amountTenderedInput = document.getElementById('amountTendered');
    const changeElement = document.getElementById('change');
    
    if (amountTenderedInput && changeElement && totalAmountElement) {
        amountTenderedInput.addEventListener('input', function() {
            const amountTendered = parseFloat(this.value) || 0;
            const totalAmount = parseFloat(totalAmountInput.value) || 0;
            const change = amountTendered - totalAmount;
            
            if (change >= 0) {
                changeElement.textContent = currencySymbol + change.toFixed(2);
                changeElement.style.color = 'green';
            } else {
                changeElement.textContent = currencySymbol + Math.abs(change).toFixed(2) + ' (insufficient)';
                changeElement.style.color = 'red';
            }
        });
    }
    
    // Handle payment method change
    const paymentMethodSelect = document.getElementById('paymentMethod');
    const cashPaymentFields = document.getElementById('cashPaymentFields');
    
    if (paymentMethodSelect && cashPaymentFields) {
        paymentMethodSelect.addEventListener('change', function() {
            if (this.value === 'cash') {
                cashPaymentFields.style.display = 'flex';
            } else {
                cashPaymentFields.style.display = 'none';
            }
        });
    }
});