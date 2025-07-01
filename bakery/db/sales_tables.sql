-- Additional tables for sales functionality
-- These complement the existing invoices table structure

-- Sales table (for direct sales transactions)
CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(20) NOT NULL UNIQUE,
    customer_name VARCHAR(100),
    customer_phone VARCHAR(20),
    customer_email VARCHAR(100),
    user_id INT NOT NULL, -- Cashier who made the sale
    subtotal DECIMAL(10, 2) NOT NULL,
    tax_rate DECIMAL(5, 2) DEFAULT 0,
    tax_amount DECIMAL(10, 2) DEFAULT 0,
    discount_type ENUM('percentage', 'fixed') DEFAULT 'fixed',
    discount_amount DECIMAL(10, 2) DEFAULT 0,
    total_amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('cash', 'card', 'credit') NOT NULL DEFAULT 'cash',
    payment_status ENUM('paid', 'unpaid', 'cancelled') DEFAULT 'paid',
    amount_paid DECIMAL(10, 2) NOT NULL,
    change_amount DECIMAL(10, 2) DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Sale Items table (for individual products in each sale)
CREATE TABLE IF NOT EXISTS sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sale_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Update users table to use string roles if not already done
-- (The functions.php expects string roles like 'admin', 'cashier')
-- ALTER TABLE users MODIFY COLUMN role VARCHAR(20) NOT NULL DEFAULT 'cashier';

-- Sample sales data for testing
INSERT IGNORE INTO sales (invoice_no, customer_name, user_id, subtotal, tax_amount, total_amount, payment_method, payment_status, amount_paid) VALUES
('INV-2024-001', 'John Doe', 1, 25.00, 2.50, 27.50, 'cash', 'paid', 30.00),
('INV-2024-002', 'Jane Smith', 1, 15.50, 1.55, 17.05, 'card', 'paid', 17.05),
('INV-2024-003', 'Bob Wilson', 1, 8.00, 0.80, 8.80, 'cash', 'paid', 10.00);

-- Sample sale items
INSERT IGNORE INTO sale_items (sale_id, product_id, quantity, unit_price, subtotal) VALUES
(1, 1, 2, 2.50, 5.00),
(1, 3, 4, 1.50, 6.00),
(1, 8, 14, 1.00, 14.00),
(2, 2, 1, 3.00, 3.00),
(2, 4, 3, 1.80, 5.40),
(2, 9, 6, 1.20, 7.20),
(3, 10, 4, 2.00, 8.00);