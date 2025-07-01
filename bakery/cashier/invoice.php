<?php
session_start();
require_once 'includes/compatibility.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Get database connection
$conn = connectDB();

// Get invoice ID from URL
$sale_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($sale_id <= 0) {
    header("Location: billing.php");
    exit;
}

// Get sale details
$saleQuery = "SELECT s.*, u.first_name, u.last_name 
              FROM sales s 
              LEFT JOIN users u ON s.cashier_id = u.id 
              WHERE s.id = ?";
$saleStmt = $conn->prepare($saleQuery);
$saleStmt->bind_param("i", $sale_id);
$saleStmt->execute();
$saleResult = $saleStmt->get_result();

if ($saleResult->num_rows === 0) {
    header("Location: billing.php");
    exit;
}

$sale = $saleResult->fetch_assoc();

// Get sale items
$itemsQuery = "SELECT si.*, p.name as product_name 
               FROM sale_items si 
               LEFT JOIN products p ON si.product_id = p.id 
               WHERE si.sale_id = ?";
$itemsStmt = $conn->prepare($itemsQuery);
$itemsStmt->bind_param("i", $sale_id);
$itemsStmt->execute();
$itemsResult = $itemsStmt->get_result();

$sale_items = [];
while ($row = $itemsResult->fetch_assoc()) {
    $sale_items[] = $row;
}

// Clear cart from session since order is complete
unset($_SESSION['cart']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo htmlspecialchars($sale['invoice_no']); ?> - Bakery Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: white !important;
                font-size: 12px;
            }
            .container {
                max-width: none !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .invoice-header {
                background: #f8f9fa !important;
                color: black !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .invoice-body {
                border: none !important;
            }
            .company-logo {
                color: black !important;
            }
            .table {
                font-size: 11px;
            }
            .invoice-total {
                background: #f8f9fa !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
        
        .invoice-header {
            background: linear-gradient(135deg, #ff6b6b, #ff5252);
            color: white;
            padding: 2rem;
            border-radius: 10px 10px 0 0;
        }
        
        .invoice-body {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 0 0 10px 10px;
            padding: 2rem;
        }
        
        .invoice-table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }
        
        .invoice-total {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
            margin-top: 1rem;
        }
        
        .company-info {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .company-logo {
            font-size: 3rem;
            color: #ff6b6b;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <!-- Print and Actions -->
                <div class="no-print mb-3">
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="fas fa-print"></i> Print Invoice
                    </button>
                    <a href="download_pdf.php?id=<?php echo $sale_id; ?>" class="btn btn-success" target="_blank">
                        <i class="fas fa-file-pdf"></i> Download PDF
                    </a>
                    <a href="billing.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Billing
                    </a>
                    <a href="index.php" class="btn btn-success">
                        <i class="fas fa-plus"></i> New Order
                    </a>
                </div>
                
                <!-- Invoice Container -->
                <div class="invoice-container">
                    <!-- Invoice Header -->
                    <div class="invoice-header">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="company-info">
                                    <div class="company-logo">
                                        <i class="fas fa-birthday-cake"></i>
                                    </div>
                                    <h2>Bakery Management System</h2>
                                    <p class="mb-0">Fresh Baked Goods Daily</p>
                                    <p class="mb-0">📧 info@bakery.com | 📞 (123) 456-7890</p>
                                </div>
                            </div>
                            <div class="col-md-6 text-right">
                                <h1>INVOICE</h1>
                                <h3>#<?php echo htmlspecialchars($sale['invoice_no']); ?></h3>
                                <p class="mb-1"><strong>Date:</strong> <?php echo date('M j, Y g:i A', strtotime($sale['created_at'])); ?></p>
                                <p class="mb-0"><strong>Cashier:</strong> <?php echo htmlspecialchars($sale['first_name'] . ' ' . $sale['last_name']); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Invoice Body -->
                    <div class="invoice-body">
                        <!-- Customer Info -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5>Bill To:</h5>
                                <p class="mb-0">
                                    <strong><?php echo !empty($sale['customer_name']) ? htmlspecialchars($sale['customer_name']) : 'Walk-in Customer'; ?></strong>
                                </p>
                            </div>
                            <div class="col-md-6 text-right">
                                <h5>Payment Details:</h5>
                                <p class="mb-0"><strong>Method:</strong> <?php echo ucfirst(htmlspecialchars($sale['payment_method'])); ?></p>
                                <p class="mb-0"><strong>Status:</strong> <span class="badge badge-success">Paid</span></p>
                            </div>
                        </div>
                        
                        <!-- Items Table -->
                        <table class="table table-bordered invoice-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Product</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-right">Unit Price</th>
                                    <th class="text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $item_no = 1; ?>
                                <?php foreach ($sale_items as $item): ?>
                                    <tr>
                                        <td><?php echo $item_no++; ?></td>
                                        <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                        <td class="text-center"><?php echo $item['quantity']; ?></td>
                                        <td class="text-right">$<?php echo number_format($item['unit_price'], 2); ?></td>
                                        <td class="text-right">$<?php echo number_format($item['subtotal'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <!-- Totals -->
                        <div class="row">
                            <div class="col-md-6">
                                <?php if (!empty($sale['notes'])): ?>
                                    <h5>Notes:</h5>
                                    <p><?php echo nl2br(htmlspecialchars($sale['notes'])); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <div class="invoice-total">
                                    <div class="row mb-2">
                                        <div class="col-6"><strong>Subtotal:</strong></div>
                                        <div class="col-6 text-right">$<?php echo number_format($sale['subtotal'], 2); ?></div>
                                    </div>
                                    <div class="row mb-2">
                                        <div class="col-6"><strong>Tax:</strong></div>
                                        <div class="col-6 text-right">$<?php echo number_format($sale['tax_amount'], 2); ?></div>
                                    </div>
                                    <?php if ($sale['discount_amount'] > 0): ?>
                                        <div class="row mb-2">
                                            <div class="col-6"><strong>Discount:</strong></div>
                                            <div class="col-6 text-right">-$<?php echo number_format($sale['discount_amount'], 2); ?></div>
                                        </div>
                                    <?php endif; ?>
                                    <hr>
                                    <div class="row">
                                        <div class="col-6"><h4><strong>Total:</strong></h4></div>
                                        <div class="col-6 text-right"><h4><strong>$<?php echo number_format($sale['total_amount'], 2); ?></strong></h4></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Footer -->
                        <div class="text-center mt-4 pt-4" style="border-top: 1px solid #e0e0e0;">
                            <p class="mb-0"><strong>Thank you for your business!</strong></p>
                            <p class="text-muted">Visit us again for fresh baked goods daily</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Auto print on load (optional) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show success notification on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Add success toast notification
            const toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
            toastContainer.style.zIndex = '1060';
            document.body.appendChild(toastContainer);
            
            const toastHTML = `
                <div class="toast" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="toast-header bg-success text-white">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong class="me-auto">Order Complete</strong>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body">
                        Invoice #<?php echo htmlspecialchars($sale['invoice_no']); ?> has been generated successfully!
                    </div>
                </div>
            `;
            
            toastContainer.innerHTML = toastHTML;
            const toast = new bootstrap.Toast(toastContainer.querySelector('.toast'));
            toast.show();
        });
        
        // Uncomment the line below to auto-print when page loads
        // window.onload = function() { window.print(); }
        
        // Print button functionality
        function printInvoice() {
            window.print();
        }
        
        // Show success message
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('printed')) {
            alert('Invoice has been generated successfully!');
        }
    </script>
</body>
</html>
