<?php
session_start();
require_once 'includes/compatibility.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Get invoice ID from request
$invoiceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($invoiceId <= 0) {
    echo '<div class="alert alert-danger">Invalid invoice ID.</div>';
    exit;
}

try {
    // Get database connection
    $conn = connectDB();
    
    // Get invoice details
    $stmt = $conn->prepare("
        SELECT i.*, 
               CONCAT(COALESCE(c.first_name, ''), ' ', COALESCE(c.last_name, '')) as customer_name,
               c.phone as customer_phone,
               c.email as customer_email,
               c.address as customer_address,
               CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as cashier_name
        FROM invoices i
        LEFT JOIN customers c ON i.customer_id = c.id
        LEFT JOIN users u ON i.user_id = u.id
        WHERE i.id = ?
    ");
    
    $stmt->bind_param("i", $invoiceId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        echo '<div class="alert alert-danger">Invoice not found.</div>';
        exit;
    }
    
    $invoice = $result->fetch_assoc();
    
    // Get invoice items
    $stmt = $conn->prepare("
        SELECT ii.*, p.name as product_name, p.description as product_description
        FROM invoice_items ii
        JOIN products p ON ii.product_id = p.id
        WHERE ii.invoice_id = ?
        ORDER BY p.name
    ");
    
    $stmt->bind_param("i", $invoiceId);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error loading invoice: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo htmlspecialchars($invoice['invoice_number']); ?> - Print</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none; }
            body { font-size: 12px; }
            .container { max-width: none; width: 100%; }
        }
        
        .invoice-header {
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .company-info {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .invoice-details {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .items-table {
            margin-bottom: 30px;
        }
        
        .total-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }
        
        .footer-note {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            font-size: 0.9em;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <!-- Print Button -->
        <div class="no-print mb-3">
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i> Print Invoice
            </button>
            <button onclick="window.close()" class="btn btn-secondary">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
        
        <!-- Invoice Content -->
        <div class="invoice-content">
            <!-- Company Header -->
            <div class="company-info">
                <h2 class="mb-1">Sweet Delights Bakery</h2>
                <p class="mb-1">123 Baker Street, Sweet City, SC 12345</p>
                <p class="mb-1">Phone: (555) 123-4567 | Email: info@sweetdelights.com</p>
                <p class="mb-0">Tax ID: 123-456-789</p>
            </div>
            
            <!-- Invoice Header -->
            <div class="invoice-header">
                <div class="row">
                    <div class="col-md-6">
                        <h3>INVOICE</h3>
                        <p class="mb-1"><strong>Invoice #:</strong> <?php echo htmlspecialchars($invoice['invoice_number']); ?></p>
                        <p class="mb-1"><strong>Date:</strong> <?php echo date('F j, Y', strtotime($invoice['created_at'])); ?></p>
                        <p class="mb-0"><strong>Time:</strong> <?php echo date('g:i A', strtotime($invoice['created_at'])); ?></p>
                    </div>
                    <div class="col-md-6 text-end">
                        <h5>Bill To:</h5>
                        <?php if ($invoice['customer_name'] && trim($invoice['customer_name'])): ?>
                            <p class="mb-1"><?php echo htmlspecialchars($invoice['customer_name']); ?></p>
                            <?php if ($invoice['customer_phone']): ?>
                                <p class="mb-1"><?php echo htmlspecialchars($invoice['customer_phone']); ?></p>
                            <?php endif; ?>
                            <?php if ($invoice['customer_email']): ?>
                                <p class="mb-1"><?php echo htmlspecialchars($invoice['customer_email']); ?></p>
                            <?php endif; ?>
                            <?php if ($invoice['customer_address']): ?>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($invoice['customer_address'])); ?></p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="mb-0">Walk-in Customer</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Invoice Details -->
            <div class="invoice-details">
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Cashier:</strong> <?php echo htmlspecialchars($invoice['cashier_name']); ?></p>
                        <p class="mb-0"><strong>Payment Method:</strong> <?php echo ucfirst(htmlspecialchars($invoice['payment_method'])); ?></p>
                    </div>
                    <div class="col-md-6 text-end">
                        <p class="mb-0">
                            <strong>Status:</strong> 
                            <?php
                            switch ($invoice['status']) {
                                case 0: echo 'Unpaid'; break;
                                case 1: echo 'Paid'; break;
                                case 2: echo 'Cancelled'; break;
                                default: echo 'Unknown';
                            }
                            ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Items Table -->
            <div class="items-table">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Item</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                    <?php if ($item['product_description']): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($item['product_description']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><?php echo $item['quantity']; ?></td>
                                <td class="text-end">$<?php echo number_format($item['unit_price'], 2); ?></td>
                                <td class="text-end">$<?php echo number_format($item['subtotal'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Totals Section -->
            <div class="row">
                <div class="col-md-6">
                    <?php if ($invoice['notes']): ?>
                        <div class="mb-3">
                            <strong>Notes:</strong><br>
                            <?php echo nl2br(htmlspecialchars($invoice['notes'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <div class="total-section">
                        <div class="d-flex justify-content-between">
                            <span>Subtotal:</span>
                            <span>$<?php echo number_format($invoice['subtotal'], 2); ?></span>
                        </div>
                        
                        <?php if ($invoice['discount_value'] > 0): ?>
                            <div class="d-flex justify-content-between">
                                <span>Discount:</span>
                                <span>
                                    <?php if ($invoice['discount_type'] == 'percentage'): ?>
                                        -$<?php echo number_format(($invoice['subtotal'] * $invoice['discount_value']) / 100, 2); ?>
                                        (<?php echo $invoice['discount_value']; ?>%)
                                    <?php else: ?>
                                        -$<?php echo number_format($invoice['discount_value'], 2); ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-between">
                            <span>Tax:</span>
                            <span>$<?php echo number_format($invoice['tax_amount'], 2); ?></span>
                        </div>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-between">
                            <strong>Total:</strong>
                            <strong>$<?php echo number_format($invoice['total_amount'], 2); ?></strong>
                        </div>
                        
                        <?php if ($invoice['amount_tendered'] > 0): ?>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between">
                                <span>Amount Tendered:</span>
                                <span>$<?php echo number_format($invoice['amount_tendered'], 2); ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Change:</span>
                                <span>$<?php echo number_format($invoice['change_amount'], 2); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="footer-note">
                <p class="mb-2">Thank you for your business!</p>
                <p class="mb-2">Please keep this receipt for your records.</p>
                <p class="mb-0">Visit us again soon at Sweet Delights Bakery!</p>
            </div>
        </div>
    </div>

    <script>
        // Auto-print when page loads (optional)
        // window.onload = function() { window.print(); }
        
        // Close window after printing
        window.onafterprint = function() {
            // Uncomment the line below if you want to auto-close after printing
            // window.close();
        }
    </script>
</body>
</html>
