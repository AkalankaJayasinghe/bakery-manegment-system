<?php
session_start();
require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: " . SITE_URL . "/auth/login.php");
    exit;
}

// Check if user has cashier or admin privileges
if (!hasCashierPrivileges() && !hasAdminPrivileges()) {
    header("Location: " . SITE_URL . "/index.php?error=unauthorized");
    exit;
}

// Database connection
$conn = getDBConnection();

// Initialize variables
$error = '';
$success = '';
$searchTerm = '';
$statusFilter = '';
$dateFilter = '';

// Handle search and filters
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $searchTerm = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
    $statusFilter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
    $dateFilter = isset($_GET['date']) ? sanitizeInput($_GET['date']) : '';
}

// Handle status change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_status'])) {
    $saleId = intval($_POST['sale_id']);
    $newStatus = sanitizeInput($_POST['status']);
    
    $validStatuses = ['paid', 'unpaid', 'cancelled'];
    if (in_array($newStatus, $validStatuses)) {
        $stmt = $conn->prepare("UPDATE sales SET payment_status = ? WHERE id = ?");
        $stmt->bind_param("si", $newStatus, $saleId);
        
        if ($stmt->execute()) {
            $success = "Sale status updated successfully";
            logActivity('change_sale_status', "Changed sale status: ID $saleId to $newStatus");
        } else {
            $error = "Error updating sale status: " . $conn->error;
        }
    } else {
        $error = "Invalid status value";
    }
}

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Build query with filters
$whereConditions = [];
$params = [];
$types = '';

if (!empty($searchTerm)) {
    $whereConditions[] = "(s.invoice_no LIKE ? OR s.customer_name LIKE ? OR s.customer_phone LIKE ?)";
    $searchParam = "%$searchTerm%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'sss';
}

if (!empty($statusFilter)) {
    $whereConditions[] = "s.payment_status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

if (!empty($dateFilter)) {
    $whereConditions[] = "DATE(s.created_at) = ?";
    $params[] = $dateFilter;
    $types .= 's';
}

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

// Count total records
$countSql = "SELECT COUNT(*) as total FROM sales s $whereClause";
$countStmt = $conn->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $limit);

// Get sales data
$sql = "SELECT s.*, u.username as cashier_name,
        (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) as item_count
        FROM sales s 
        LEFT JOIN users u ON s.user_id = u.id
        $whereClause
        ORDER BY s.created_at DESC 
        LIMIT $offset, $limit";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$sales = [];
while ($row = $result->fetch_assoc()) {
    $sales[] = $row;
}

// Set page title
$pageTitle = "Sales Management";

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Include sidebar -->
        <?php include_once '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Sales Management</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <?php if (hasCashierPrivileges()): ?>
                        <a href="<?php echo CASHIER_URL; ?>/billing.php" class="btn btn-sm btn-primary">
                            <i class="fa fa-plus"></i> New Sale
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Filters and Search -->
            <div class="card mb-3">
                <div class="card-header">
                    <h5><i class="fa fa-filter"></i> Search & Filter</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                        <div class="row">
                            <div class="col-md-3">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($searchTerm); ?>" 
                                       placeholder="Invoice No, Customer Name or Phone">
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Payment Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="paid" <?php echo $statusFilter == 'paid' ? 'selected' : ''; ?>>Paid</option>
                                    <option value="unpaid" <?php echo $statusFilter == 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                                    <option value="cancelled" <?php echo $statusFilter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="date" class="form-label">Date</label>
                                <input type="date" class="form-control" id="date" name="date" 
                                       value="<?php echo htmlspecialchars($dateFilter); ?>">
                            </div>
                            <div class="col-md-3">
                                <label>&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-search"></i> Search
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php if (!empty($searchTerm) || !empty($statusFilter) || !empty($dateFilter)): ?>
                        <div class="row mt-2">
                            <div class="col-md-12">
                                <a href="sales.php" class="btn btn-secondary btn-sm">
                                    <i class="fa fa-times"></i> Clear Filters
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            
            <!-- Sales List -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5>Sales Records</h5>
                    <span class="badge bg-primary"><?php echo $totalRecords; ?> Sales</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Invoice No</th>
                                    <th>Customer</th>
                                    <th>Items</th>
                                    <th>Total Amount</th>
                                    <th>Payment</th>
                                    <th>Status</th>
                                    <th>Cashier</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($sales) > 0): ?>
                                    <?php foreach ($sales as $sale): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($sale['invoice_no']); ?></strong></td>
                                            <td>
                                                <?php if (!empty($sale['customer_name'])): ?>
                                                    <?php echo htmlspecialchars($sale['customer_name']); ?>
                                                    <?php if (!empty($sale['customer_phone'])): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($sale['customer_phone']); ?></small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Walk-in Customer</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $sale['item_count']; ?> items</td>
                                            <td><?php echo formatCurrency($sale['total_amount']); ?></td>
                                            <td><?php echo ucfirst($sale['payment_method']); ?></td>
                                            <td>
                                                <?php
                                                $statusClass = '';
                                                switch ($sale['payment_status']) {
                                                    case 'paid':
                                                        $statusClass = 'bg-success';
                                                        break;
                                                    case 'unpaid':
                                                        $statusClass = 'bg-warning';
                                                        break;
                                                    case 'cancelled':
                                                        $statusClass = 'bg-danger';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge <?php echo $statusClass; ?>">
                                                    <?php echo ucfirst($sale['payment_status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($sale['cashier_name']); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($sale['created_at'])); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-info" 
                                                            onclick="viewSaleDetails(<?php echo $sale['id']; ?>)" 
                                                            title="View Details">
                                                        <i class="fa fa-eye"></i>
                                                    </button>
                                                    
                                                    <?php if (hasAdminPrivileges() && $sale['payment_status'] != 'cancelled'): ?>
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-warning dropdown-toggle" 
                                                                data-bs-toggle="dropdown" title="Change Status">
                                                            <i class="fa fa-edit"></i>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <?php if ($sale['payment_status'] != 'paid'): ?>
                                                            <li>
                                                                <form method="post" class="d-inline">
                                                                    <input type="hidden" name="change_status" value="1">
                                                                    <input type="hidden" name="sale_id" value="<?php echo $sale['id']; ?>">
                                                                    <input type="hidden" name="status" value="paid">
                                                                    <button type="submit" class="dropdown-item">
                                                                        <i class="fa fa-check text-success"></i> Mark as Paid
                                                                    </button>
                                                                </form>
                                                            </li>
                                                            <?php endif; ?>
                                                            <?php if ($sale['payment_status'] != 'unpaid'): ?>
                                                            <li>
                                                                <form method="post" class="d-inline">
                                                                    <input type="hidden" name="change_status" value="1">
                                                                    <input type="hidden" name="sale_id" value="<?php echo $sale['id']; ?>">
                                                                    <input type="hidden" name="status" value="unpaid">
                                                                    <button type="submit" class="dropdown-item">
                                                                        <i class="fa fa-clock text-warning"></i> Mark as Unpaid
                                                                    </button>
                                                                </form>
                                                            </li>
                                                            <?php endif; ?>
                                                            <li>
                                                                <form method="post" class="d-inline" 
                                                                      onsubmit="return confirm('Are you sure you want to cancel this sale?');">
                                                                    <input type="hidden" name="change_status" value="1">
                                                                    <input type="hidden" name="sale_id" value="<?php echo $sale['id']; ?>">
                                                                    <input type="hidden" name="status" value="cancelled">
                                                                    <button type="submit" class="dropdown-item">
                                                                        <i class="fa fa-times text-danger"></i> Cancel Sale
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center">
                                            <div class="py-4">
                                                <i class="fa fa-shopping-cart fa-3x text-muted mb-3"></i>
                                                <p class="text-muted">No sales records found</p>
                                                <?php if (hasCashierPrivileges()): ?>
                                                <a href="<?php echo CASHIER_URL; ?>/billing.php" class="btn btn-primary">
                                                    <i class="fa fa-plus"></i> Create First Sale
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($searchTerm); ?>&status=<?php echo urlencode($statusFilter); ?>&date=<?php echo urlencode($dateFilter); ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($searchTerm); ?>&status=<?php echo urlencode($statusFilter); ?>&date=<?php echo urlencode($dateFilter); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($searchTerm); ?>&status=<?php echo urlencode($statusFilter); ?>&date=<?php echo urlencode($dateFilter); ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Sale Details Modal -->
<div class="modal fade" id="saleDetailsModal" tabindex="-1" aria-labelledby="saleDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="saleDetailsModalLabel">Sale Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="saleDetailsContent">
                <!-- Sale details will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
function viewSaleDetails(saleId) {
    // Show loading state
    document.getElementById('saleDetailsContent').innerHTML = '<div class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading...</div>';
    
    // Show modal
    var modal = new bootstrap.Modal(document.getElementById('saleDetailsModal'));
    modal.show();
    
    // Load sale details via AJAX
    fetch('ajax/get_sale_details.php?id=' + saleId)
        .then(response => response.text())
        .then(data => {
            document.getElementById('saleDetailsContent').innerHTML = data;
        })
        .catch(error => {
            document.getElementById('saleDetailsContent').innerHTML = '<div class="alert alert-danger">Error loading sale details</div>';
        });
}
</script>

<?php include_once '../includes/footer.php'; ?>