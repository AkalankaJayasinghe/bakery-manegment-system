<?php
/**
 * System Constants
 * 
 * This file contains all constant definitions used throughout the system
 */

// Base URLs
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$script_name = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$project_path = rtrim(preg_replace('/\/config$/', '', $script_name), '/');
define('SITE_URL', $protocol . '://' . $host . $project_path);
define('ADMIN_URL', SITE_URL . '/admin');
define('CASHIER_URL', SITE_URL . '/cashier');
define('ASSETS_URL', SITE_URL . '/assets');

// Site info
define('SITE_NAME', 'Bakery Management System');
define('SITE_VERSION', '1.0');
define('SITE_EMAIL', 'admin@bakery.com');

// User Roles (as strings in your database)
define('ROLE_ADMIN', 'admin');
define('ROLE_CASHIER', 'cashier');

// User Roles (as integers if you decide to change your database schema)
define('ROLE_ADMIN_ID', 1);
define('ROLE_CASHIER_ID', 2);

// User Status
define('USER_ACTIVE', 1);
define('USER_INACTIVE', 0);

// Product Status
define('PRODUCT_AVAILABLE', 1);
define('PRODUCT_OUT_OF_STOCK', 0);

// Invoice/Sale Status
define('INVOICE_PAID', 'paid');
define('INVOICE_UNPAID', 'unpaid');
define('INVOICE_CANCELLED', 'cancelled');

// System Date Format
define('DATE_FORMAT', 'd-m-Y');
define('DATETIME_FORMAT', 'd-m-Y H:i:s');
define('MYSQL_DATE_FORMAT', 'Y-m-d');
define('MYSQL_DATETIME_FORMAT', 'Y-m-d H:i:s');

// Currency Settings
define('CURRENCY_SYMBOL', '$');
define('DECIMAL_POINTS', 2);
define('TAX_RATE', 10); // 10% tax rate

// Pagination
define('ITEMS_PER_PAGE', 10);

// File Upload Settings
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('UPLOAD_DIR', '../uploads/');
define('PRODUCT_IMAGE_DIR', UPLOAD_DIR . 'products/');

// Session Timeout (in seconds)
define('SESSION_TIMEOUT', 1800); // 30 minutes

// Security
define('PASSWORD_MIN_LENGTH', 6);

// API Settings
define('API_KEY', 'your_api_key_here');
define('API_TIMEOUT', 30); // seconds

// Backup Settings
define('BACKUP_DIR', '../backups/');
define('MAX_BACKUPS', 5);

// Debug Mode (set to false in production)
define('DEBUG_MODE', true);
?>