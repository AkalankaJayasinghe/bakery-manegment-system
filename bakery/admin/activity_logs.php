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
$error = '';
$success = '';

// Pagination settings
$itemsPerPage = ITEMS_PER_PAGE;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max($page, 1);
$offset = ($page - 1) * $itemsPerPage;

// Filter variables
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$userFilter = isset($_GET['user_filter']) ? $_GET['user_filter'] : '';
$actionFilter = isset($_GET['action_filter']) ? $_GET['action_filter'] : '';

// Build WHERE clause
$whereConditions = [];
$whereConditions[] = "DATE(al.created_at) BETWEEN '$startDate' AND '$endDate'";

if (!empty($userFilter)) {
    $whereConditions[] = "al.user_id = '" . sanitizeInput($userFilter) . "'";
}

if (!empty($actionFilter)) {
    $whereConditions[] = "al.action LIKE '%" . sanitizeInput($actionFilter) . "%'";
}

$whereClause = "WHERE " . implode(" AND ", $whereConditions);

// Get total count for pagination
$countSql = "SELECT COUNT(*) as total 
             FROM activity_logs al 
             LEFT JOIN users u ON al.user_id = u.id 
             $whereClause";
$countResult = $conn->query($countSql);
$totalLogs = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalLogs / $itemsPerPage);

// Get activity logs
$logs = [];
$sql = "SELECT al.*, 
               CONCAT(u.first_name, ' ', u.last_name) as user_name,
               u.username
        FROM activity_logs al 
        LEFT JOIN users u ON al.user_id = u.id 
        $whereClause
        ORDER BY al.created_at DESC 
        LIMIT $itemsPerPage OFFSET $offset";

$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $logs[] = $row;
}

// Get all users for filter dropdown
$users = [];
$userResult = $conn->query("SELECT id, username, first_name, last_name FROM users ORDER BY first_name, last_name");
while ($row = $userResult->fetch_assoc()) {
    $users[] = $row;
}

// Get unique actions for filter
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
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearFilters()">
                            <i class="fa fa-refresh"></i> Clear Filters
                        </button>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fa fa-exclamation-triangle"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fa fa-check-circle"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fa fa-filter"></i> Filters</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="user_filter" class="form-label">User</label>
                            <select class="form-select" id="user_filter" name="user_filter">
                                <option value="">All Users</option>
                                <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo ($userFilter == $user['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['username'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="action_filter" class="form-label">Action</label>
                            <select class="form-select" id="action_filter" name="action_filter">
                                <option value="">All Actions</option>
                                <?php foreach ($actions as $action): ?>
                                <option value="<?php echo $action; ?>" <?php echo ($actionFilter == $action) ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(str_replace('_', ' ', $action)); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-search"></i> Apply Filters
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="clearFilters()">
                                <i class="fa fa-refresh"></i> Clear
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Activity Logs Table -->
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5>Activity Logs</h5>
                        <span class="badge bg-secondary"><?php echo $totalLogs; ?> total logs</span>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (count($logs) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Description</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td>
                                        <span class="fw-bold"><?php echo date('M d, Y', strtotime($log['created_at'])); ?></span><br>
                                        <small class="text-muted"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($log['user_name']): ?>
                                        <span class="fw-bold"><?php echo htmlspecialchars($log['user_name']); ?></span><br>
                                        <small class="text-muted">@<?php echo htmlspecialchars($log['username']); ?></small>
                                        <?php else: ?>
                                        <span class="text-muted">Unknown User</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $actionClass = 'bg-secondary';
                                        if (strpos($log['action'], 'add') !== false || strpos($log['action'], 'create') !== false) {
                                            $actionClass = 'bg-success';
                                        } elseif (strpos($log['action'], 'edit') !== false || strpos($log['action'], 'update') !== false) {
                                            $actionClass = 'bg-warning';
                                        } elseif (strpos($log['action'], 'delete') !== false) {
                                            $actionClass = 'bg-danger';
                                        } elseif (strpos($log['action'], 'login') !== false) {
                                            $actionClass = 'bg-info';
                                        }
                                        ?>
                                        <span class="badge <?php echo $actionClass; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $log['action'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($log['description'])): ?>
                                        <span><?php echo htmlspecialchars($log['description']); ?></span>
                                        <?php else: ?>
                                        <span class="text-muted">No description</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?php echo htmlspecialchars($log['ip_address']); ?></small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <nav aria-label="Activity logs pagination">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo ($page - 1); ?>&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>&user_filter=<?php echo $userFilter; ?>&action_filter=<?php echo $actionFilter; ?>">Previous</a>
                            </li>
                            <?php endif; ?>
                            
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>&user_filter=<?php echo $userFilter; ?>&action_filter=<?php echo $actionFilter; ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo ($page + 1); ?>&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>&user_filter=<?php echo $userFilter; ?>&action_filter=<?php echo $actionFilter; ?>">Next</a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    
                    <div class="text-center">
                        <small class="text-muted">
                            Showing <?php echo (($page - 1) * $itemsPerPage + 1); ?> to <?php echo min($page * $itemsPerPage, $totalLogs); ?> of <?php echo $totalLogs; ?> logs
                        </small>
                    </div>
                    <?php endif; ?>
                    
                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fa fa-info-circle fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No activity logs found for the selected criteria.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
function clearFilters() {
    window.location.href = 'activity_logs.php';
}
</script>

<?php include_once '../includes/footer.php'; ?>