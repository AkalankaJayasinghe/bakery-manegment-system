mysql -u root -pmysql -u root -p-- Create database
CREATE DATABASE IF NOT EXISTS bakery_system;
USE bakery_system;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    role INT NOT NULL, -- 1: Admin, 2: Manager, 3: Cashier
    status TINYINT NOT NULL DEFAULT 1, -- 0: Inactive, 1: Active
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME
);

-- Categories table
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    status TINYINT NOT NULL DEFAULT 1, -- 0: Inactive, 1: Active
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Products table
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    category_id INT,
    price DECIMAL(10, 2) NOT NULL,
    cost_price DECIMAL(10, 2) NOT NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    reorder_level INT DEFAULT 10,
    image VARCHAR(255),
    status TINYINT NOT NULL DEFAULT 1, -- 0: Inactive, 1: Active
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- Customers table
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100) UNIQUE,
    address TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Invoices table
CREATE TABLE invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(20) NOT NULL UNIQUE,
    customer_id INT,
    user_id INT NOT NULL, -- Cashier who created the invoice
    subtotal DECIMAL(10, 2) NOT NULL,
    tax_amount DECIMAL(10, 2) NOT NULL,
    discount_type ENUM('percentage', 'fixed') DEFAULT 'fixed',
    discount_value DECIMAL(10, 2) DEFAULT 0,
    total_amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('cash', 'card') NOT NULL,
    amount_tendered DECIMAL(10, 2) NOT NULL,
    change_amount DECIMAL(10, 2) NOT NULL,
    status TINYINT NOT NULL DEFAULT 1, -- 0: Unpaid, 1: Paid, 2: Cancelled
    notes TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Invoice Items table
CREATE TABLE invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- Stock Movement table
CREATE TABLE stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    quantity INT NOT NULL, -- Positive for additions, negative for deductions
    movement_type ENUM('purchase', 'sales', 'adjustment', 'return') NOT NULL,
    reference_id INT, -- Invoice ID or purchase order ID
    notes TEXT,
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Activity Logs table
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(50),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Permissions table
CREATE TABLE permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    permission_name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255)
);

-- Role Permissions table
CREATE TABLE role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

-- Insert default admin user (password: admin123)
INSERT INTO users (username, password, first_name, last_name, email, role, status)
VALUES ('admin', '$2y$10$qJSLXmIyY7I7zcG7FbQcvOK8MkuHPhJkXd.we6Mj/nSdvfQS8Xd/O', 'Admin', 'User', 'admin@bakery.com', 1, 1);

-- Insert some default categories
INSERT INTO categories (name, description, status) VALUES
('Bread', 'Various types of bread', 1),
('Pastries', 'Delicious pastries and croissants', 1),
('Cakes', 'Birthday and special occasion cakes', 1),
('Cookies', 'Fresh baked cookies', 1),
('Beverages', 'Coffee, tea, and other drinks', 1);

-- Insert some default products
INSERT INTO products (name, description, category_id, price, cost_price, stock_quantity, reorder_level, status) VALUES
('White Bread', 'Standard white bread loaf', 1, 2.50, 1.00, 20, 5, 1),
('Brown Bread', 'Healthy brown bread loaf', 1, 3.00, 1.20, 15, 5, 1),
('Croissant', 'Butter croissant', 2, 1.50, 0.60, 30, 10, 1),
('Chocolate Croissant', 'Croissant with chocolate filling', 2, 1.80, 0.70, 25, 10, 1),
('Birthday Cake (Small)', 'Small birthday cake (6 servings)', 3, 15.00, 7.50, 5, 2, 1),
('Birthday Cake (Medium)', 'Medium birthday cake (12 servings)', 3, 25.00, 12.50, 3, 2, 1),
('Birthday Cake (Large)', 'Large birthday cake (20 servings)', 3, 35.00, 17.50, 2, 1, 1),
('Chocolate Chip Cookie', 'Classic chocolate chip cookie', 4, 1.00, 0.40, 50, 20, 1),
('Oatmeal Cookie', 'Healthy oatmeal cookie', 4, 1.20, 0.50, 40, 15, 1),
('Coffee (Small)', 'Small coffee', 5, 2.00, 0.50, 100, 0, 1),
('Coffee (Medium)', 'Medium coffee', 5, 2.50, 0.70, 100, 0, 1),
('Coffee (Large)', 'Large coffee', 5, 3.00, 0.90, 100, 0, 1);

-- Insert default permissions
INSERT INTO permissions (permission_name, description) VALUES
('manage_products', 'Create, update, and delete products'),
('manage_categories', 'Create, update, and delete categories'),
('manage_users', 'Create, update, and delete users'),
('manage_customers', 'Create, update, and delete customers'),
('view_reports', 'View sales and inventory reports'),
('make_sales', 'Process sales transactions'),
('manage_invoices', 'View and manage invoices'),
('manage_inventory', 'Update inventory levels');

-- Insert default role permissions
-- Admin role (role_id = 1) has all permissions
INSERT INTO role_permissions (role_id, permission_id) VALUES
(1, 1), (1, 2), (1, 3), (1, 4), (1, 5), (1, 6), (1, 7), (1, 8);

-- Cashier role (role_id = 3) has limited permissions
INSERT INTO role_permissions (role_id, permission_id) VALUES
(3, 4), (3, 6), (3, 7);

-- Sample customer
INSERT INTO customers (first_name, last_name, phone, email, address) VALUES
('John', 'Doe', '123-456-7890', 'john@example.com', '123 Main St');

ALTER TABLE users 
ADD COLUMN first_name VARCHAR(50) AFTER username,
ADD COLUMN last_name VARCHAR(50) AFTER first_name;
