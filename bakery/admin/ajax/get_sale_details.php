<?php
session_start();
require_once '../../config/database.php';
require_once '../../config/constants.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Unauthorized access</div>';
    exit;
}

// Check if user has proper privileges
if (!hasCashierPrivileges() && !hasAdminPrivileges()) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Insufficient privileges</div>';
    exit;
}

// Get sale ID
$saleId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($saleId <= 0) {
    echo '<div class="alert alert-danger">Invalid sale ID</div>';
    exit;
}

$conn = getDBConnection();

// Get sale details
$sql = "SELECT s.*, u.username as cashier_name, u.first_name, u.last_name
        FROM sales s 
        LEFT JOIN users u ON s.user_id = u.id
        WHERE s.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $saleId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo '<div class="alert alert-danger">Sale not found</div>';
    exit;
}

$sale = $result->fetch_assoc();

// Get sale items
$sql = "SELECT si.*, p.name as product_name
        FROM sale_items si
        JOIN products p ON si.product_id = p.id
        WHERE si.sale_id = ?
        ORDER BY si.id";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $saleId);
$stmt->execute();
$itemsResult = $stmt->get_result();

$items = [];
while ($row = $itemsResult->fetch_assoc()) {
    $items[] = $row;
}
?>

<div class="row">
    <div class="col-md-6">
        <h6><i class="fa fa-file-text"></i> Sale Information</h6>
        <table class="table table-sm">
            <tr>
                <td><strong>Invoice No:</strong></td>
                <td><?php echo htmlspecialchars($sale['invoice_no']); ?></td>
            </tr>
            <tr>
                <td><strong>Date:</strong></td>
                <td><?php echo date('d/m/Y H:i:s', strtotime($sale['created_at'])); ?></td>
            </tr>
            <tr>
                <td><strong>Status:</strong></td>
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
            </tr>
            <tr>
                <td><strong>Cashier:</strong></td>
                <td><?php echo htmlspecialchars($sale['first_name'] . ' ' . $sale['last_name']); ?> (<?php echo htmlspecialchars($sale['cashier_name']); ?>)</td>
            </tr>
        </table>
    </div>
    
    <div class="col-md-6">
        <h6><i class="fa fa-user"></i> Customer Information</h6>
        <table class="table table-sm">
            <tr>
                <td><strong>Name:</strong></td>
                <td><?php echo !empty($sale['customer_name']) ? htmlspecialchars($sale['customer_name']) : '<span class="text-muted">Walk-in Customer</span>'; ?></td>
            </tr>
            <tr>
                <td><strong>Phone:</strong></td>
                <td><?php echo !empty($sale['customer_phone']) ? htmlspecialchars($sale['customer_phone']) : '<span class="text-muted">Not provided</span>'; ?></td>
            </tr>
            <tr>
                <td><strong>Email:</strong></td>
                <td><?php echo !empty($sale['customer_email']) ? htmlspecialchars($sale['customer_email']) : '<span class="text-muted">Not provided</span>'; ?></td>
            </tr>
        </table>
    </div>
</div>

<hr>

<h6><i class="fa fa-shopping-cart"></i> Items Purchased</h6>
<div class="table-responsive">
    <table class="table table-sm table-striped">
        <thead>
            <tr>
                <th>#</th>
                <th>Product</th>
                <th>Quantity</th>
                <th>Unit Price</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($items) > 0): ?>
                <?php foreach ($items as $index => $item): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                        <td><?php echo $item['quantity']; ?></td>
                        <td><?php echo formatCurrency($item['unit_price']); ?></td>
                        <td><?php echo formatCurrency($item['subtotal']); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="text-center text-muted">No items found</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<hr>

<div class="row">
    <div class="col-md-6">
        <h6><i class="fa fa-credit-card"></i> Payment Information</h6>
        <table class="table table-sm">
            <tr>
                <td><strong>Payment Method:</strong></td>
                <td><?php echo ucfirst($sale['payment_method']); ?></td>
            </tr>
            <tr>
                <td><strong>Amount Paid:</strong></td>
                <td><?php echo formatCurrency($sale['amount_paid']); ?></td>
            </tr>
            <tr>
                <td><strong>Change:</strong></td>
                <td><?php echo formatCurrency($sale['change_amount']); ?></td>
            </tr>
        </table>
    </div>
    
    <div class="col-md-6">
        <h6><i class="fa fa-calculator"></i> Amount Breakdown</h6>
        <table class="table table-sm">
            <tr>
                <td><strong>Subtotal:</strong></td>
                <td><?php echo formatCurrency($sale['subtotal']); ?></td>
            </tr>
            <?php if ($sale['discount_amount'] > 0): ?>
            <tr>
                <td><strong>Discount:</strong></td>
                <td>-<?php echo formatCurrency($sale['discount_amount']); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($sale['tax_amount'] > 0): ?>
            <tr>
                <td><strong>Tax (<?php echo $sale['tax_rate']; ?>%):</strong></td>
                <td><?php echo formatCurrency($sale['tax_amount']); ?></td>
            </tr>
            <?php endif; ?>
            <tr class="table-success">
                <td><strong>Total:</strong></td>
                <td><strong><?php echo formatCurrency($sale['total_amount']); ?></strong></td>
            </tr>
        </table>
    </div>
</div>

<?php if (!empty($sale['notes'])): ?>
<hr>
<h6><i class="fa fa-comment"></i> Notes</h6>
<div class="alert alert-info">
    <?php echo nl2br(htmlspecialchars($sale['notes'])); ?>
</div>
<?php endif; ?>