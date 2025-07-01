<?php
session_start();
require_once 'includes/compatibility.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Access denied.</div>';
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
        SELECT ii.*, p.name as product_name, p.description as product_description,
               c.name as category_name
        FROM invoice_items ii
        JOIN products p ON ii.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE ii.invoice_id = ?
        ORDER BY p.name
    ");
    
    $stmt->bind_param("i", $invoiceId);
    $stmt->execute();
    $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Format status
    function getStatusText($status) {
        switch ($status) {
            case 0: return 'Unpaid';
            case 1: return 'Paid';
            case 2: return 'Cancelled';
            default: return 'Unknown';
        }
    }
    
    function getStatusClass($status) {
        switch ($status) {
            case 0: return 'warning';
            case 1: return 'success';
            case 2: return 'danger';
            default: return 'secondary';
        }
    }
    
    ?>
    
    <div class="invoice-details">
        <!-- Invoice Header -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h4>Invoice #<?php echo htmlspecialchars($invoice['invoice_number']); ?></h4>
                <p class="text-muted mb-1">
                    <strong>Date:</strong> <?php echo date('F j, Y g:i A', strtotime($invoice['created_at'])); ?>
                </p>
                <p class="text-muted mb-1">
                    <strong>Cashier:</strong> <?php echo htmlspecialchars($invoice['cashier_name']); ?>
                </p>
                <p class="text-muted">
                    <strong>Status:</strong> 
                    <span class="badge bg-<?php echo getStatusClass($invoice['status']); ?>">
                        <?php echo getStatusText($invoice['status']); ?>
                    </span>
                </p>
            </div>
            <div class="col-md-6 text-end">
                <h5>Customer Information</h5>
                <?php if ($invoice['customer_name'] && trim($invoice['customer_name'])): ?>
                    <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($invoice['customer_name']); ?></p>
                    <?php if ($invoice['customer_phone']): ?>
                        <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($invoice['customer_phone']); ?></p>
                    <?php endif; ?>
                    <?php if ($invoice['customer_email']): ?>
                        <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($invoice['customer_email']); ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-muted">Walk-in Customer</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Invoice Items -->
        <div class="table-responsive mb-4">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-center">Quantity</th>
                        <th class="text-end">Subtotal</th>
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
                            <td><?php echo htmlspecialchars($item['category_name'] ?: 'Uncategorized'); ?></td>
                            <td class="text-end">$<?php echo number_format($item['unit_price'], 2); ?></td>
                            <td class="text-center"><?php echo $item['quantity']; ?></td>
                            <td class="text-end">$<?php echo number_format($item['subtotal'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Invoice Summary -->
        <div class="row">
            <div class="col-md-6">
                <?php if ($invoice['notes']): ?>
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Notes</h6>
                        </div>
                        <div class="card-body">
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Payment Summary</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <span>Subtotal:</span>
                            <span>$<?php echo number_format($invoice['subtotal'], 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Tax:</span>
                            <span>$<?php echo number_format($invoice['tax_amount'], 2); ?></span>
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
                        <hr>
                        <div class="d-flex justify-content-between">
                            <strong>Total:</strong>
                            <strong>$<?php echo number_format($invoice['total_amount'], 2); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Payment Method:</span>
                            <span class="text-capitalize"><?php echo htmlspecialchars($invoice['payment_method']); ?></span>
                        </div>
                        <?php if ($invoice['amount_tendered'] > 0): ?>
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
        </div>
    </div>
    
    <?php
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error loading invoice details: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>
