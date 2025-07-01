<?php
session_start();
require_once 'includes/compatibility.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Get invoice ID from URL
$sale_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($sale_id <= 0) {
    header("Location: billing.php");
    exit;
}

// Get database connection
$conn = connectDB();

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo htmlspecialchars($sale['invoice_no']); ?> - Print & PDF</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; }
            @page { margin: 0.5in; }
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .invoice-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            margin: 2rem auto;
            max-width: 800px;
            overflow: hidden;
        }
        
        .invoice-header {
            background: linear-gradient(135deg, #ff6b6b, #ff5252);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        
        .invoice-body {
            padding: 2rem;
        }
        
        .company-logo {
            font-size: 3rem;
            margin-bottom: 1rem;
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
        
        .status-message {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1060;
            min-width: 300px;
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 2000;
        }
        
        .loading-spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #ff6b6b;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Loading overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="text-center">
            <div class="loading-spinner"></div>
            <p class="mt-3">Generating PDF and preparing to print...</p>
        </div>
    </div>

    <!-- Status messages -->
    <div class="status-message" id="statusContainer"></div>

    <!-- Navigation (no-print) -->
    <div class="container-fluid no-print py-3">
        <div class="row">
            <div class="col-12 text-center">
                <h4 class="text-white mb-3">
                    <i class="fas fa-check-circle text-success"></i> 
                    Order Completed Successfully!
                </h4>
                <div class="btn-group">
                    <button onclick="downloadPDF()" class="btn btn-success">
                        <i class="fas fa-file-pdf"></i> Download PDF
                    </button>
                    <button onclick="printInvoice()" class="btn btn-primary">
                        <i class="fas fa-print"></i> Print Again
                    </button>
                    <a href="billing.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Billing
                    </a>
                    <a href="index.php" class="btn btn-info">
                        <i class="fas fa-plus"></i> New Order
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Invoice Content -->
    <div class="invoice-container">
        <!-- Invoice Header -->
        <div class="invoice-header">
            <div class="company-logo">
                <i class="fas fa-birthday-cake"></i>
            </div>
            <h2>Bakery Management System</h2>
            <p class="mb-1">Fresh Baked Goods Daily</p>
            <p class="mb-0">📧 info@bakery.com | 📞 (123) 456-7890</p>
            <hr class="my-3" style="border-color: rgba(255,255,255,0.3);">
            <h1 class="mb-2">INVOICE</h1>
            <h3>#<?php echo htmlspecialchars($sale['invoice_no']); ?></h3>
        </div>

        <!-- Invoice Body -->
        <div class="invoice-body">
            <!-- Invoice Info -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <h5>Bill To:</h5>
                    <p class="mb-1">
                        <strong><?php echo !empty($sale['customer_name']) ? htmlspecialchars($sale['customer_name']) : 'Walk-in Customer'; ?></strong>
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <h5>Invoice Details:</h5>
                    <p class="mb-1"><strong>Date:</strong> <?php echo date('M j, Y g:i A', strtotime($sale['created_at'])); ?></p>
                    <p class="mb-1"><strong>Cashier:</strong> <?php echo htmlspecialchars($sale['first_name'] . ' ' . $sale['last_name']); ?></p>
                    <p class="mb-1"><strong>Payment:</strong> <?php echo ucfirst(htmlspecialchars($sale['payment_method'])); ?></p>
                </div>
            </div>

            <!-- Items Table -->
            <div class="table-responsive">
                <table class="table table-bordered invoice-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th class="text-center">Quantity</th>
                            <th class="text-end">Unit Price</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sale_items as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td class="text-center"><?php echo $item['quantity']; ?></td>
                            <td class="text-end">$<?php echo number_format($item['unit_price'], 2); ?></td>
                            <td class="text-end">$<?php echo number_format($item['subtotal'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Totals -->
            <div class="row">
                <div class="col-md-6"></div>
                <div class="col-md-6">
                    <div class="invoice-total">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <span>$<?php echo number_format($sale['subtotal'], 2); ?></span>
                        </div>
                        <?php if ($sale['discount_amount'] > 0): ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Discount:</span>
                            <span>-$<?php echo number_format($sale['discount_amount'], 2); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tax:</span>
                            <span>$<?php echo number_format($sale['tax_amount'], 2); ?></span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <strong>Total:</strong>
                            <strong>$<?php echo number_format($sale['total_amount'], 2); ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="text-center mt-4 pt-4" style="border-top: 1px solid #ddd;">
                <h5 class="text-primary">Thank you for your business!</h5>
                <p class="text-muted">Visit us: 123 Bakery Street, Sweet City | Call: (123) 456-7890</p>
                <p class="text-muted small">Invoice generated on <?php echo date('M j, Y \a\t g:i A'); ?></p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let pdfDownloaded = false;
        let printCompleted = false;

        // Show status message
        function showStatus(message, type = 'success') {
            const container = document.getElementById('statusContainer');
            const alertHTML = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            container.innerHTML = alertHTML;
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                const alert = container.querySelector('.alert');
                if (alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            }, 5000);
        }

        // Download PDF
        function downloadPDF() {
            showStatus('Generating PDF...', 'info');
            
            // Open PDF in new window
            const pdfWindow = window.open('download_pdf.php?id=<?php echo $sale_id; ?>', '_blank');
            
            setTimeout(() => {
                pdfDownloaded = true;
                showStatus('PDF downloaded successfully!', 'success');
                checkCompletion();
            }, 2000);
        }

        // Print invoice
        function printInvoice() {
            showStatus('Preparing to print...', 'info');
            
            setTimeout(() => {
                window.print();
                printCompleted = true;
                showStatus('Print dialog opened!', 'success');
                checkCompletion();
            }, 1000);
        }

        // Check if both actions are completed
        function checkCompletion() {
            if (pdfDownloaded && printCompleted) {
                document.getElementById('loadingOverlay').style.display = 'none';
                showStatus('Order completed! PDF downloaded and print ready.', 'success');
            }
        }

        // Auto-execute on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Show initial status
            showStatus('Order #<?php echo htmlspecialchars($sale['invoice_no']); ?> completed successfully!', 'success');
            
            // Auto-download PDF after 1 second
            setTimeout(() => {
                downloadPDF();
            }, 1000);
            
            // Auto-print after 3 seconds
            setTimeout(() => {
                printInvoice();
            }, 3000);
            
            // Hide loading overlay after 5 seconds if user doesn't interact
            setTimeout(() => {
                if (!pdfDownloaded || !printCompleted) {
                    document.getElementById('loadingOverlay').style.display = 'none';
                    showStatus('Actions completed. You can manually download PDF or print if needed.', 'info');
                }
            }, 8000);
        });

        // Handle print event
        window.addEventListener('beforeprint', function() {
            showStatus('Printing invoice...', 'info');
        });

        window.addEventListener('afterprint', function() {
            printCompleted = true;
            showStatus('Print completed!', 'success');
            checkCompletion();
        });
    </script>
</body>
</html>
