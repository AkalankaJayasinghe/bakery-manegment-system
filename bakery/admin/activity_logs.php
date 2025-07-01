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

// Check if user has admin privileges
if (!hasAdminPrivileges()) {
    header("Location: " . SITE_URL . "/index.php?error=unauthorized");
    exit;
}

// Database connection
$conn = getDBConnection();

// Initialize variables
$searchTerm = '';
$userFilter = '';
$actionFilter = '';
$dateFilter = '';

// Handle search and filters
if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $searchTerm = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
    $userFilter = isset($_GET['user']) ? sanitizeInput($_GET['user']) : '';
    $actionFilter = isset($_GET['action']) ? sanitizeInput($_GET['action']) : '';
    $dateFilter = isset($_GET['date']) ? sanitizeInput($_GET['date']) : '';
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
    $whereConditions[] = "(al.action LIKE ? OR al.description LIKE ? OR u.username LIKE ?)";
    $searchParam = "%$searchTerm%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= 'sss';
}

if (!empty($userFilter)) {
    $whereConditions[] = "al.user_id = ?";
    $params[] = $userFilter;
    $types .= 'i';
}

if (!empty($actionFilter)) {
    $whereConditions[] = "al.action = ?";
    $params[] = $actionFilter;
    $types .= 's';
}

if (!empty($dateFilter)) {
    $whereConditions[] = "DATE(al.created_at) = ?";
    $params[] = $dateFilter;
    $types .= 's';
}

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

// Count total records
$countSql = "SELECT COUNT(*) as total FROM activity_logs al LEFT JOIN users u ON al.user_id = u.id $whereClause";
$countStmt = $conn->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $limit);

// Get activity logs
$sql = "SELECT al.*, 
        COALESCE(u.username, 'System') as username,
        COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'System User') as full_name
        FROM activity_logs al 
        LEFT JOIN users u ON al.user_id = u.id
        $whereClause
        ORDER BY al.created_at DESC 
        LIMIT $offset, $limit";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$logs = [];
while ($row = $result->fetch_assoc()) {
    $logs[] = $row;
}

// Get all users for filter dropdown
$users = [];
$userResult = $conn->query("SELECT id, username, first_name, last_name FROM users ORDER BY first_name, last_name");
while ($row = $userResult->fetch_assoc()) {
    $users[] = $row;
}

// Get distinct actions for filter dropdown
$actions = [];
$actionResult = $conn->query("SELECT DISTINCT action FROM activity_logs ORDER BY action");
while ($row = $actionResult->fetch_assoc()) {
    $actions[] = $row['action'];
}

// Set page title
$pageTitle = "Activity Logs";

// Include header
include_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Include sidebar -->
        <?php include_once '../includes/sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Activity Logs</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="refreshLogs()">
                            <i class="fa fa-refresh"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>
            
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
                                       placeholder="Action, Description, or Username">
                            </div>
                            <div class="col-md-2">
                                <label for="user" class="form-label">User</label>
                                <select class="form-select" id="user" name="user">
                                    <option value="">All Users</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>" <?php echo $userFilter == $user['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="action" class="form-label">Action</label>
                                <select class="form-select" id="action" name="action">
                                    <option value="">All Actions</option>
                                    <?php foreach ($actions as $action): ?>
                                        <option value="<?php echo htmlspecialchars($action); ?>" <?php echo $actionFilter == $action ? 'selected' : ''; ?>>
                                            <?php echo ucfirst(str_replace('_', ' ', $action)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
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
                        <?php if (!empty($searchTerm) || !empty($userFilter) || !empty($actionFilter) || !empty($dateFilter)): ?>
                        <div class="row mt-2">
                            <div class="col-md-12">
                                <a href="activity_logs.php" class="btn btn-secondary btn-sm">
                                    <i class="fa fa-times"></i> Clear Filters
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            
            <!-- Activity Logs List -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5>Activity Records</h5>
                    <span class="badge bg-primary"><?php echo $totalRecords; ?> Activities</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Description</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($logs) > 0): ?>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td>
                                                <span data-bs-toggle="tooltip" title="<?php echo date('l, F j, Y g:i:s A', strtotime($log['created_at'])); ?>">
                                                    <?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($log['full_name']); ?></strong>
                                                        <br><small class="text-muted">@<?php echo htmlspecialchars($log['username']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                $actionClass = '';
                                                $iconClass = '';
                                                
                                                switch ($log['action']) {
                                                    case 'login':
                                                        $actionClass = 'bg-success';
                                                        $iconClass = 'fa-sign-in';
                                                        break;
                                                    case 'logout':
                                                        $actionClass = 'bg-info';
                                                        $iconClass = 'fa-sign-out';
                                                        break;
                                                    case 'create_user':
                                                    case 'create_category':
                                                    case 'create_product':
                                                        $actionClass = 'bg-primary';
                                                        $iconClass = 'fa-plus';
                                                        break;
                                                    case 'update_user':
                                                    case 'update_category':
                                                    case 'update_product':
                                                        $actionClass = 'bg-warning';
                                                        $iconClass = 'fa-edit';
                                                        break;
                                                    case 'delete_user':
                                                    case 'delete_category':
                                                    case 'delete_product':
                                                        $actionClass = 'bg-danger';
                                                        $iconClass = 'fa-trash';
                                                        break;
                                                    case 'change_status':
                                                    case 'change_user_status':
                                                    case 'change_category_status':
                                                        $actionClass = 'bg-secondary';
                                                        $iconClass = 'fa-toggle-on';
                                                        break;
                                                    case 'reset_password':
                                                        $actionClass = 'bg-warning';
                                                        $iconClass = 'fa-key';
                                                        break;
                                                    case 'make_sale':
                                                        $actionClass = 'bg-success';
                                                        $iconClass = 'fa-shopping-cart';
                                                        break;
                                                    default:
                                                        $actionClass = 'bg-light text-dark';
                                                        $iconClass = 'fa-info';
                                                }
                                                ?>
                                                <span class="badge <?php echo $actionClass; ?>">
                                                    <i class="fa <?php echo $iconClass; ?>"></i>
                                                    <?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="text-truncate" style="max-width: 300px;" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($log['description']); ?>">
                                                    <?php echo htmlspecialchars($log['description']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($log['ip_address']); ?>
                                                </small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">
                                            <div class="py-4">
                                                <i class="fa fa-list-alt fa-3x text-muted mb-3"></i>
                                                <p class="text-muted">No activity logs found</p>
                                                <?php if (empty($searchTerm) && empty($userFilter) && empty($actionFilter) && empty($dateFilter)): ?>
                                                <p class="text-muted">Activities will appear here as users interact with the system</p>
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
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($searchTerm); ?>&user=<?php echo urlencode($userFilter); ?>&action=<?php echo urlencode($actionFilter); ?>&date=<?php echo urlencode($dateFilter); ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($searchTerm); ?>&user=<?php echo urlencode($userFilter); ?>&action=<?php echo urlencode($actionFilter); ?>&date=<?php echo urlencode($dateFilter); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($searchTerm); ?>&user=<?php echo urlencode($userFilter); ?>&action=<?php echo urlencode($actionFilter); ?>&date=<?php echo urlencode($dateFilter); ?>" aria-label="Next">
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

<script>
// Initialize tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});

// Refresh logs function
function refreshLogs() {
    window.location.reload();
}

// Auto-refresh every 30 seconds
setInterval(function() {
    // Only auto-refresh if no filters are applied to avoid disrupting user's work
    const urlParams = new URLSearchParams(window.location.search);
    if (!urlParams.has('search') && !urlParams.has('user') && !urlParams.has('action') && !urlParams.has('date')) {
        refreshLogs();
    }
}, 30000);
</script>

<?php include_once '../includes/footer.php'; ?>