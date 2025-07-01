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
$cashier_id = $_SESSION['user_id'];
$cashier_name = getUserName($conn, $cashier_id);

// Get invoices/sales
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$salesQuery = "SELECT s.*, u.first_name, u.last_name, 
               COUNT(si.id) as item_count
               FROM sales s 
               LEFT JOIN users u ON s.cashier_id = u.id 
               LEFT JOIN sale_items si ON s.id = si.sale_id
               GROUP BY s.id
               ORDER BY s.created_at DESC 
               LIMIT ? OFFSET ?";
$salesStmt = $conn->prepare($salesQuery);
$salesStmt->bind_param("ii", $limit, $offset);
$salesStmt->execute();
$salesResult = $salesStmt->get_result();

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM sales";
$countResult = $conn->query($countQuery);
$totalSales = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalSales / $limit);

// Handle invoice actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Cancel invoice
    if (isset($_POST['cancel_invoice'])) {
        $invoiceId = $_POST['cancel_invoice'];
        
        // Check if invoice can be cancelled (only today's invoices)
        $stmt = $conn->prepare("SELECT DATE(created_at) as invoice_date, status FROM invoices WHERE id = ?");
        $stmt->bind_param("i", $invoiceId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $invoice = $result->fetch_assoc();
            
            if ($invoice['status'] == INVOICE_CANCELLED) {
                $error = "Invoice is already cancelled";
            } elseif ($invoice['invoice_date'] != date('Y-m-d') && !hasAdminPrivileges()) {
                $error = "Only today's invoices can be cancelled by cashiers";
            } else {
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Update invoice status
                    $stmt = $conn->prepare("UPDATE invoices SET status = ? WHERE id = ?");
                    $cancelledStatus = INVOICE_CANCELLED;
                    $stmt->bind_param("ii", $cancelledStatus, $invoiceId);
                    $stmt->execute();
                    
                    // Get invoice items to restore stock
                    $stmt = $conn->prepare("SELECT product_id, quantity FROM invoice_items WHERE invoice_id = ?");
                    $stmt->bind_param("i", $invoiceId);
                    $stmt->execute();
                    $items = $stmt->get_result();
                    
                    // Restore stock for each item
                    while ($item = $items->fetch_assoc()) {
                        // Update product stock
                        $stmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
                        $stmt->bind_param("ii", $item['quantity'], $item['product_id']);
                        $stmt->execute();
                        
                        // Record stock movement
                        $stmt = $conn->prepare("INSERT INTO stock_movements (product_id, quantity, movement_type, reference_id, notes, created_by) VALUES (?, ?, 'return', ?, 'Invoice cancelled', ?)");
                        $stmt->bind_param("iiis", $item['product_id'], $item['quantity'], $invoiceId, $_SESSION['user_id']);
                        $stmt->execute();
                    }
                    
                    // Log activity
                    logActivity('cancel_invoice', "Cancelled invoice ID: $invoiceId");
                    
                    // Commit transaction
                    $conn->commit();
                    $success = "Invoice cancelled successfully";
                } catch (Exception $e) {
                    // Rollback transaction on error
                    $conn->rollback();
                    $error = "Error cancelling invoice: " . $e->getMessage();
                }
            }
        } else {
            $error = "Invoice not found";
        }
    }
}

// Build query for invoices
$sql = "SELECT i.*, CONCAT(c.first_name, ' ', c.last_name) as customer_name, 
        CONCAT(u.first_name, ' ', u.last_name) as cashier_name
        FROM invoices i
        LEFT JOIN customers c ON i.customer_id = c.id
        LEFT JOIN users u ON i.user_id = u.id
        WHERE 1=1";

$params = [];
$types = "";

// Add date filters
$sql .= " AND DATE(i.created_at) BETWEEN ? AND ?";
$params[] = $startDate;
$params[] = $endDate;
$types .= "ss";

// Add status filter
if ($invoiceStatus !== '') {
    $sql .= " AND i.status = ?";
    $params[] = $invoiceStatus;
    $types .= "i";
}

// Add search filter
if (!empty($searchTerm)) {
    $sql .= " AND (i.invoice_number LIKE ? OR CONCAT(c.first_name, ' ', c.last_name) LIKE ?)";
    $searchParam = "%$searchTerm%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ss";
}

// Add sorting
$sql .= " ORDER BY i.created_at DESC";

// Prepare and execute the query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Fetch all invoices
while ($row = $result->fetch_assoc()) {
    $invoices[] = $row;
}

// Set page title
$pageTitle = "Manage Invoices";

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Include sidebar -->
        <?php include_once '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Manage Invoices</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="<?php echo CASHIER_URL; ?>" class="btn btn-sm btn-primary me-2">
                        <i class="fa fa-plus"></i> New Sale
                    </a>
                </div>
            </div>
            
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <!-- Filter Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Filter Invoices</h5>
                </div>
                <div class="card-body">
                    <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="row g-3">
                        <input type="hidden" name="filter" value="1">
                        
                        <div class="col-md-3">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All</option>
                                <option value="<?php echo INVOICE_PAID; ?>" <?php echo ($invoiceStatus == INVOICE_PAID) ? 'selected' : ''; ?>>Paid</option>
                                <option value="<?php echo INVOICE_UNPAID; ?>" <?php echo ($invoiceStatus == INVOICE_UNPAID) ? 'selected' : ''; ?>>Unpaid</option>
                                <option value="<?php echo INVOICE_CANCELLED; ?>" <?php echo ($invoiceStatus == INVOICE_CANCELLED) ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" placeholder="Invoice # or Customer" value="<?php echo $searchTerm; ?>">
                        </div>
                        
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary">Filter</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Invoices Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Invoices List</h5>
                    <span class="badge bg-primary"><?php echo count($invoices); ?> Invoices</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Cashier</th>
                                    <th>Items</th>
                                    <th>Subtotal</th>
                                    <th>Tax</th>
                                    <th>Discount</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($invoices) > 0): ?>
                                    <?php foreach ($invoices as $invoice): ?>
                                        <?php
                                        // Get number of items in this invoice
                                        $stmt = $conn->prepare("SELECT SUM(quantity) as items FROM invoice_items WHERE invoice_id = ?");
                                        $stmt->bind_param("i", $invoice['id']);
                                        $stmt->execute();
                                        $result = $stmt->get_result();
                                        $itemCount = $result->fetch_assoc()['items'];
                                        ?>
                                        <tr>
                                            <td><?php echo $invoice['invoice_number']; ?></td>
                                            <td><?php echo date(DATETIME_FORMAT, strtotime($invoice['created_at'])); ?></td>
                                            <td><?php echo $invoice['customer_name'] ?: 'Walk-in Customer'; ?></td>
                                            <td><?php echo $invoice['cashier_name']; ?></td>
                                            <td><?php echo $itemCount; ?></td>
                                            <td><?php echo formatCurrency($invoice['subtotal']); ?></td>
                                            <td><?php echo formatCurrency($invoice['tax_amount']); ?></td>
                                            <td>
                                                <?php if ($invoice['discount_type'] == 'percentage'): ?>
                                                    <?php echo $invoice['discount_value']; ?>%
                                                <?php else: ?>
                                                    <?php echo formatCurrency($invoice['discount_value']); ?>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo formatCurrency($invoice['total_amount']); ?></td>
                                            <td>
                                                <?php echo getStatusBadge($invoice['status']); ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-info view-invoice" data-id="<?php echo $invoice['id']; ?>" data-bs-toggle="modal" data-bs-target="#invoiceDetailsModal">
                                                        <i class="fa fa-eye"></i>
                                                    </button>
                                                    
                                                    <a href="#" class="btn btn-primary print-invoice" data-id="<?php echo $invoice['id']; ?>">
                                                        <i class="fa fa-print"></i>
                                                    </a>
                                                    
                                                    <?php if ($invoice['status'] != INVOICE_CANCELLED && (date('Y-m-d', strtotime($invoice['created_at'])) == date('Y-m-d') || hasAdminPrivileges())): ?>
                                                        <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this invoice? This will restore product stock levels.');">
                                                            <input type="hidden" name="cancel_invoice" value="<?php echo $invoice['id']; ?>">
                                                            <button type="submit" class="btn btn-danger">
                                                                <i class="fa fa-times"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="11" class="text-center">No invoices found matching your criteria.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Invoice Details Modal -->
<div class="modal fade" id="invoiceDetailsModal" tabindex="-1" aria-labelledby="invoiceDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="invoiceDetailsModalLabel">Invoice Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="invoiceDetails">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="printInvoiceBtn">Print Invoice</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // View invoice details
    document.querySelectorAll('.view-invoice').forEach(function(button) {
        button.addEventListener('click', function() {
            const invoiceId = this.getAttribute('data-id');
            const detailsContainer = document.getElementById('invoiceDetails');
            
            // Show loading spinner
            detailsContainer.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `;
            
            // Fetch invoice details using AJAX
            fetch('get_invoice_details.php?id=' + invoiceId)
                .then(response => response.text())
                .then(html => {
                    detailsContainer.innerHTML = html;
                })
                .catch(error => {
                    detailsContainer.innerHTML = `
                        <div class="alert alert-danger">
                            Error loading invoice details: ${error.message}
                        </div>
                    `;
                });
        });
    });
    
    // Print invoice
    document.querySelectorAll('.print-invoice').forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const invoiceId = this.getAttribute('data-id');
            window.open('print_invoice.php?id=' + invoiceId, '_blank');
        });
    });
    
    // Print invoice from modal
    document.getElementById('printInvoiceBtn').addEventListener('click', function() {
        const invoiceId = document.querySelector('.view-invoice[data-bs-toggle="modal"]').getAttribute('data-id');
        window.open('print_invoice.php?id=' + invoiceId, '_blank');
    });
    
    // Date range validation
    document.querySelector('form').addEventListener('submit', function(e) {
        const startDate = new Date(document.getElementById('start_date').value);
        const endDate = new Date(document.getElementById('end_date').value);
        
        if (startDate > endDate) {
            e.preventDefault();
            alert('Start date cannot be after end date');
        }
    });
});

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function(alert) {
        const alertInstance = new bootstrap.Alert(alert);
        alertInstance.close();
    });
}, 5000);
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>
