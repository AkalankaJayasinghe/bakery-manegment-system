-- Simple sales table for the admin dashboard to work with your current sales.php
-- This complements the existing schema

-- Create sales table if it doesn't exist (for compatibility with sales.php)
CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    sale_date DATE NOT NULL,
    customer_name VARCHAR(255),
    total_amount DECIMAL(10, 2) NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Ensure products table has quantity field
ALTER TABLE products ADD COLUMN IF NOT EXISTS quantity INT DEFAULT 0;

-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS idx_sales_date ON sales(sale_date);
CREATE INDEX IF NOT EXISTS idx_sales_product ON sales(product_id);
CREATE INDEX IF NOT EXISTS idx_sales_user ON sales(user_id);
CREATE INDEX IF NOT EXISTS idx_products_quantity ON products(quantity);

-- Insert some sample data if tables are empty
INSERT IGNORE INTO users (id, username, password, role, email, created_at) VALUES 
(1, 'admin', '$2y$10$qJSLXmIyY7I7zcG7FbQcvOK8MkuHPhJkXd.we6Mj/nSdvfQS8Xd/O', 'admin', 'admin@bakery.com', NOW());

INSERT IGNORE INTO categories (id, name, description) VALUES 
(1, 'Bread', 'Various types of bread and loaves'),
(2, 'Pastries', 'Croissants, danishes, and other pastries'),
(3, 'Cakes', 'Birthday cakes and celebration cakes'),
(4, 'Cookies', 'Fresh baked cookies and biscuits'),
(5, 'Beverages', 'Coffee, tea, and cold drinks');

INSERT IGNORE INTO products (id, name, description, category_id, price, quantity) VALUES 
(1, 'White Bread', 'Fresh white bread loaf', 1, 2.50, 25),
(2, 'Chocolate Croissant', 'Buttery croissant with chocolate', 2, 3.00, 15),
(3, 'Birthday Cake (Small)', 'Small celebration cake', 3, 15.00, 5),
(4, 'Chocolate Chip Cookies', 'Classic chocolate chip cookies', 4, 1.50, 30),
(5, 'Coffee', 'Fresh brewed coffee', 5, 2.00, 50);

-- Sample sales data
INSERT IGNORE INTO sales (id, product_id, quantity, sale_date, customer_name, total_amount, user_id) VALUES 
(1, 1, 2, CURDATE(), 'John Doe', 5.00, 1),
(2, 2, 1, CURDATE(), 'Jane Smith', 3.00, 1),
(3, 4, 6, CURDATE(), '', 9.00, 1),
(4, 5, 3, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Bob Johnson', 6.00, 1),
(5, 3, 1, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'Alice Brown', 15.00, 1);
