# Bakery Management System

A modern, responsive bakery management system built with PHP, MySQL, and Bootstrap. This system provides a complete point-of-sale (POS) solution with admin dashboard capabilities.

## Features

### 🏪 Point of Sale (POS)
- **Modern Dashboard**: Interactive product grid with categories
- **Smart Cart System**: Real-time cart management with session persistence
- **Product Search**: Quick search by name or barcode
- **Category Filtering**: Easy product filtering by category
- **Billing & Checkout**: Complete order processing with invoice generation
- **Payment Methods**: Support for cash, card, and other payment types
- **Tax & Discount**: Configurable tax rates and quick discount buttons
- **Keyboard Shortcuts**: Speed up operations with keyboard shortcuts

### 📊 Admin Dashboard
- **Product Management**: Add, edit, and manage bakery products
- **Category Management**: Organize products into categories
- **Sales Reports**: Track sales and revenue
- **User Management**: Manage cashier accounts
- **Inventory Tracking**: Monitor stock levels

### 🧾 Invoice System
- **Professional Invoices**: Beautifully designed, printable invoices
- **Auto-generation**: Automatic invoice numbers and timestamps
- **Customer Information**: Optional customer details
- **Order History**: Complete sales transaction records

## Technology Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Framework**: Bootstrap 5.3
- **Icons**: Font Awesome 6.0
- **Server**: Apache (XAMPP recommended for development)

## Installation

1. **Prerequisites**
   - XAMPP or similar LAMP/WAMP stack
   - PHP 7.4 or higher
   - MySQL 5.7 or higher

2. **Setup**
   ```bash
   # Clone or extract files to your web directory
   # e.g., c:\xampp\htdocs\bakery
   
   # Start Apache and MySQL in XAMPP
   
   # Access the application
   http://localhost/bakery
   ```

3. **Database Setup**
   - Import the schema from `db/schema.sql`
   - Configure database connection in `config/database.php`
   - Run the admin setup if needed

## Usage

### For Cashiers
1. **Login**: Use your cashier credentials to access the POS system
2. **Select Products**: Click on products to add them to the cart
3. **Manage Cart**: Adjust quantities, remove items as needed
4. **Process Order**: Fill in customer details and complete the order
5. **Print Invoice**: Generate and print receipts for customers

### For Administrators
1. **Access Admin Panel**: Login with admin credentials
2. **Manage Products**: Add new bakery items, update prices and stock
3. **View Reports**: Monitor sales performance and trends
4. **Manage Users**: Add or modify cashier accounts

## Keyboard Shortcuts

- **F1**: Focus on product search
- **Ctrl + Enter**: Submit current order
- **Ctrl + D**: Clear shopping cart
- **Escape**: Close search results

## Security Features

- Session-based authentication
- SQL injection protection with prepared statements
- XSS protection with input sanitization
- CSRF protection on forms
- Role-based access control

## File Structure

```
bakery/
├── admin/              # Admin dashboard
├── auth/               # Authentication system
├── cashier/            # POS system
├── assets/             # CSS, JS, images
├── config/             # Configuration files
├── db/                 # Database schema
└── includes/           # Shared components
```

## Development Notes

- The system uses a compatibility layer for database operations
- Cart data is stored in PHP sessions
- Products support stock tracking with automatic inventory updates
- The system is responsive and works on tablets and mobile devices

## Production Deployment

1. Remove or secure the `test_cart.php` file
2. Update database credentials in `config/database.php`
3. Enable HTTPS in production
4. Configure proper error logging
5. Set up regular database backups

## Support

For questions or issues, please check the code comments or review the database schema in `db/schema.sql`.

---

**Built with ❤️ for modern bakery operations**
