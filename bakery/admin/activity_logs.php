<?php
// Include database connection
include 'includes/db_connect.php';
include 'includes/functions.php';
include 'includes/session.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// Pagination settings
$records_per_page = 20;
$page = isset($_GET['page']) ? $_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Build query with filters
$query = "SELECT al.*, u.username 
          FROM activity_logs al
          LEFT JOIN users u ON al.user_id = u.id";

// Apply filters if set
$where_conditions = [];

if (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
    $user_id = $_GET['user_id'];
    $where_conditions[] = "al.user_id = '$user_id'";
}

if (isset($_GET['action']) && !empty($_GET['action'])) {
    $action = $_GET['action'];
    $where_conditions[] = "al.action LIKE '%$action%'";
}

if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
    $date_from = $_GET['date_from'];
    $where_conditions[] = "DATE(al.created_at) >= '$date_from'";
}

if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
    $date_to = $_GET['date_to'];
    $where_conditions[] = "DATE(al.created_at) <= '$date_to'";
}

// Add WHERE clause if filters are applied
if (!empty($where_conditions)) {
    $query .= " WHERE " . implode(" AND ", $where_conditions);
}

// Add order by
$query .= " ORDER BY al.created_at DESC";

// Get total records for pagination
$total_records_query = "SELECT COUNT(*) as total FROM (" . $query . ") as total_query";
$total_records_result = mysqli_query($conn, $total_records_query);
$total_records = mysqli_fetch_assoc($total_records_result)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Add pagination limit
$query .= " LIMIT $offset, $records_per_page";

// Execute query
$result = mysqli_query($conn, $query);

// Get all users for filter dropdown
$users_query = "SELECT id, username FROM users ORDER BY username ASC";
$users_result = mysqli_query($conn, $users_query);

// Include header
include 'includes/header.php';
?>

<div class="main-content">
    <h1>Activity Logs</h1>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h3>Filter Logs</h3>
        </div>
        <div class="card-body">
            <form method="GET" class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>User</label>
                        <select name="user_id" class="form-control">
                            <option value="">All Users</option>
                            <?php mysqli_data_seek($users_result, 0); ?>
                            <?php while ($user = mysqli_fetch_assoc($users_result)): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo (isset($_GET['user_id']) && $_GET['user_id'] == $user['id']) ? 'selected' : ''; ?>>
                                    <?php echo $user['username']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Action</label>
                        <input type="text" name="action" class="form-control" value="<?php echo isset($_GET['action']) ? $_GET['action'] : ''; ?>" placeholder="Filter by action...">
                    </div>
                </div>
                
                <div class="col-md-2">
                    <div class="form-group">
                        <label>From Date</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo isset($_GET['date_from']) ? $_GET['date_from'] : ''; ?>">
                    </div>
                </div>
                
                <div class="col-md-2">
                    <div class="form-group">
                        <label>To Date</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo isset($_GET['date_to']) ? $_GET['date_to'] : ''; ?>">
                    </div>
                </div>
                
                <div class="col-md-2">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                            <a href="activity_logs.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Activity Logs Table -->
    <div class="card">
        <div class="card-header">
            <h3>System Activity Logs</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>IP Address</th>
                            <th>Date & Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result) > 0): ?>
                            <?php $counter = $offset + 1; while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo $row['username'] ? $row['username'] : 'System'; ?></td>
                                    <td><?php echo $row['action']; ?></td>
                                    <td><?php echo $row['ip_address']; ?></td>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($row['created_at'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">No activity logs found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo isset($_GET['user_id']) ? '&user_id=' . $_GET['user_id'] : ''; ?><?php echo isset($_GET['action']) ? '&action=' . $_GET['action'] : ''; ?><?php echo isset($_GET['date_from']) ? '&date_from=' . $_GET['date_from'] : ''; ?><?php echo isset($_GET['date_to']) ? '&date_to=' . $_GET['date_to'] : ''; ?>">
                                Previous
                            </a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo isset($_GET['user_id']) ? '&user_id=' . $_GET['user_id'] : ''; ?><?php echo isset($_GET['action']) ? '&action=' . $_GET['action'] : ''; ?><?php echo isset($_GET['date_from']) ? '&date_from=' . $_GET['date_from'] : ''; ?><?php echo isset($_GET['date_to']) ? '&date_to=' . $_GET['date_to'] : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo isset($_GET['user_id']) ? '&user_id=' . $_GET['user_id'] : ''; ?><?php echo isset($_GET['action']) ? '&action=' . $_GET['action'] : ''; ?><?php echo isset($_GET['date_from']) ? '&date_from=' . $_GET['date_from'] : ''; ?><?php echo isset($_GET['date_to']) ? '&date_to=' . $_GET['date_to'] : ''; ?>">
                                Next
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>