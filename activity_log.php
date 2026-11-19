<?php
/**
 * Activity Log System - Read Only View
 * 
 * SECURITY NOTICE: This file is protected and read-only.
 * Unauthorized modifications are prohibited.
 * 
 * @version 1.0
 * @author Asset Management System
 * @copyright 2025
 */

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

session_start();
require_once 'config/database.php';
require_once 'includes/header.php';

// Require admin login
requireAdmin();

// Additional security check - prevent direct file access
if (!defined('ASSET_MANAGEMENT_SYSTEM')) {
    define('ASSET_MANAGEMENT_SYSTEM', true);
}

// File protection - prevent unauthorized access
if (basename($_SERVER['PHP_SELF']) !== 'activity_log.php' || !isset($_SESSION['user_id'])) {
    http_response_code(403);
    die('Access Denied: Unauthorized access to protected file.');
}

// Read-only mode enforcement
define('READ_ONLY_MODE', true);

$page_title = 'Activity Log - Asset Management System';

try {
    $pdo = getDB();
    $stmt = $pdo->query("
        SELECT al.*, 
               CASE 
                   WHEN al.user_id = 0 THEN 'System'
                   WHEN u.full_name IS NULL THEN 'Deleted User'
                   ELSE u.full_name 
               END as full_name,
               CASE 
                   WHEN al.user_id = 0 THEN 'system'
                   WHEN u.username IS NULL THEN 'deleted_user'
                   ELSE u.username 
               END as username
        FROM activity_log al 
        LEFT JOIN users u ON al.user_id = u.user_id 
        ORDER BY al.timestamp DESC
    ");
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $activities = [];
}

?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="fas fa-history me-2"></i>Activity Log
                </h1>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-primary" id="advancedSearchBtn" data-bs-toggle="modal" data-bs-target="#advancedSearchModal" title="Advanced Search">
                        <i class="fas fa-filter me-1"></i>Advanced Search
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="location.reload()">
                        <i class="fas fa-sync-alt me-1"></i>Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-primary text-white">
            <h6 class="m-0 font-weight-bold"><i class="fas fa-list me-2"></i>System Activity Log</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="activityTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Table</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activities as $activity): ?>
                            <tr>
                                <td><?php echo date('M d, Y H:i', strtotime($activity['timestamp'])); ?></td>
                                <td>
                                    <?php if ($activity['user_id'] == 0): ?>
                                        <span class="badge bg-warning text-dark">
                                            <i class="fas fa-cog me-1"></i><?php echo htmlspecialchars($activity['full_name']); ?>
                                        </span><br>
                                        <small class="text-muted">@<?php echo htmlspecialchars($activity['username']); ?></small>
                                    <?php elseif ($activity['full_name'] == 'Deleted User'): ?>
                                        <span class="badge bg-danger text-white">
                                            <i class="fas fa-user-times me-1"></i><?php echo htmlspecialchars($activity['full_name']); ?>
                                        </span><br>
                                        <small class="text-muted">@<?php echo htmlspecialchars($activity['username']); ?></small>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($activity['full_name'] ?? 'N/A'); ?><br>
                                        <small class="text-muted">@<?php echo htmlspecialchars($activity['username'] ?? 'N/A'); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $action_class = '';
                                    switch ($activity['action_type']) {
                                        case 'LOGIN':
                                        case 'LOGOUT':
                                            $action_class = 'btn-primary';
                                            break;
                                        case 'CREATE':
                                            $action_class = 'btn-success';
                                            break;
                                        case 'UPDATE':
                                            $action_class = 'btn-warning';
                                            break;
                                        case 'DELETE':
                                            $action_class = 'btn-danger';
                                            break;
                                        case 'MONITOR':
                                            $action_class = 'btn-info';
                                            break;
                                        default:
                                            $action_class = 'btn-secondary';
                                            break;
                                    }
                                    ?>
                                    <span class="btn btn-sm <?php echo $action_class; ?> text-white">
                                        <?php
                                        switch ($activity['action_type']) {
                                            case 'LOGIN': echo '<i class="fas fa-sign-in-alt me-1"></i>LOGIN'; break;
                                            case 'LOGOUT': echo '<i class="fas fa-sign-out-alt me-1"></i>LOGOUT'; break;
                                            case 'CREATE': echo '<i class="fas fa-plus me-1"></i>CREATE'; break;
                                            case 'UPDATE': echo '<i class="fas fa-edit me-1"></i>UPDATE'; break;
                                            case 'DELETE': echo '<i class="fas fa-trash me-1"></i>DELETE'; break;
                                            case 'MONITOR': echo '<i class="fas fa-chart-line me-1"></i>MONITOR'; break;
                                            default: echo htmlspecialchars($activity['action_type']); break;
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $table_icon = '';
                                    switch ($activity['table_name']) {
                                        case 'assets': $table_icon = 'fas fa-boxes'; break;
                                        case 'users': $table_icon = 'fas fa-users'; break;
                                        case 'departments': $table_icon = 'fas fa-building'; break;
                                        case 'maintenance': $table_icon = 'fas fa-tools'; break;
                                        case 'system': $table_icon = 'fas fa-cogs'; break;
                                        default: $table_icon = 'fas fa-table'; break;
                                    }
                                    ?>
                                    <i class="<?php echo $table_icon; ?> me-1"></i><?php echo htmlspecialchars($activity['table_name']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($activity['description']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Advanced Search Modal -->
<div class="modal fade" id="advancedSearchModal" tabindex="-1" aria-labelledby="advancedSearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="advancedSearchModalLabel">
                    <i class="fas fa-search me-2"></i>Advanced Search
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="advancedSearchForm">
                    <div class="row g-3">
                        <!-- User Information Section -->
                        <div class="col-12">
                            <h6 class="text-primary mb-3"><i class="fas fa-user me-2"></i>User Information</h6>
                        </div>
                        <div class="col-md-6">
                            <label for="searchFullName" class="form-label">Fullname</label>
                            <input type="text" class="form-control" id="searchFullName" placeholder="Search by fullname">
                        </div>
                        <div class="col-md-6">
                            <label for="searchUsername" class="form-label">Username</label>
                            <input type="text" class="form-control" id="searchUsername" placeholder="Search by username">
                        </div>
                        <!-- Action Information Section -->
                        <div class="col-12">
                            <h6 class="text-warning mb-3"><i class="fas fa-cogs me-2"></i>Action Information</h6>
                        </div>
                        <div class="col-md-6">
                            <label for="searchActionType" class="form-label">Action Type</label>
                            <select class="form-select" id="searchActionType">
                                <option value="">All Actions</option>
                                <option value="CREATE">CREATE</option>
                                <option value="UPDATE">UPDATE</option>
                                <option value="DELETE">DELETE</option>
                                <option value="LOGIN">LOGIN</option>
                                <option value="LOGOUT">LOGOUT</option>
                                <option value="MONITOR">MONITOR</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="searchTableName" class="form-label">Table Name</label>
                            <select class="form-select" id="searchTableName">
                                <option value="">All Tables</option>
                                <option value="assets">Assets</option>
                                <option value="users">Users</option>
                                <option value="departments">Departments</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="system">System</option>
                            </select>
                        </div>
                        <!-- Date Range Section -->
                        <div class="col-12">
                            <h6 class="text-info mb-3"><i class="fas fa-calendar me-2"></i>Date Range</h6>
                        </div>
                        <div class="col-md-6">
                            <label for="searchDateFrom" class="form-label">From Date</label>
                            <input type="date" class="form-control" id="searchDateFrom">
                        </div>
                        <div class="col-md-6">
                            <label for="searchDateTo" class="form-label">To Date</label>
                            <input type="date" class="form-control" id="searchDateTo">
                        </div>
                        <!-- Description Search -->
                        <div class="col-12">
                            <h6 class="text-success mb-3"><i class="fas fa-comment me-2"></i>Description</h6>
                        </div>
                        <div class="col-12">
                            <label for="searchDescription" class="form-label">Description Contains</label>
                            <input type="text" class="form-control" id="searchDescription" placeholder="Search in description">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-outline-secondary" onclick="clearAdvancedSearch()">
                    <i class="fas fa-times me-1"></i>Clear All
                </button>
                <button type="button" class="btn btn-primary" onclick="applyAdvancedSearch()">
                    <i class="fas fa-search me-1"></i>Apply Search
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
$(document).ready(function() {
    // Initialize DataTable with simple layout
    $('#activityTable').DataTable({
        responsive: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        dom: 'lfrtip', // Length menu, filtering, responsive, table, info, pagination
        order: [[0, 'desc']], // Sort by timestamp descending by default
        language: {
            search: "Search activities:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "No activities found",
            infoFiltered: "(filtered from _MAX_ total entries)",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            },
            emptyTable: "No activity data available"
        }
    });
});

// Advanced Search Functions
function applyAdvancedSearch() {
    if (typeof $ === 'undefined') {
        alert('Advanced search requires jQuery. Please refresh the page.');
        return;
    }
    
    const table = $('#activityTable').DataTable();
    
    // Clear all previous searches
    table.search('').columns().search('').draw();
    
    // Get search values
    const fullName = $('#searchFullName').val().trim();
    const username = $('#searchUsername').val().trim();
    const actionType = $('#searchActionType').val();
    const tableName = $('#searchTableName').val();
    const dateFrom = $('#searchDateFrom').val();
    const dateTo = $('#searchDateTo').val();
    const description = $('#searchDescription').val().trim();
    
    // Apply column-specific searches
    if (fullName) {
        table.column(1).search(fullName, false, false);
    }
    
    if (username) {
        table.column(1).search(username, false, false);
    }
    
    if (actionType) {
        table.column(2).search(actionType, false, false);
    }
    
    if (tableName) {
        table.column(3).search(tableName, false, false);
    }
    
    if (description) {
        table.column(4).search(description, false, false);
    }
    
    // Apply date range filter using custom search function
    if (dateFrom || dateTo) {
        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            if (settings.nTable.id !== 'activityTable') {
                return true;
            }
            
            const rowDate = new Date(data[0]);
            
            if (dateFrom && rowDate < new Date(dateFrom)) {
                return false;
            }
            if (dateTo && rowDate > new Date(dateTo + 'T23:59:59')) {
                return false;
            }
            
            return true;
        });
    }
    
    // Redraw the table with all filters applied
    table.draw();
    
    // Get the number of visible rows after filtering
    const visibleRows = table.rows({search: 'applied'}).count();
    
    // Show results message
    if (visibleRows === 0) {
        alert('No activities found matching the search criteria.');
    } else {
        alert(`Found ${visibleRows} activit${visibleRows === 1 ? 'y' : 'ies'} matching the search criteria.`);
    }
    
    // Close modal
    $('#advancedSearchModal').modal('hide');
}

function clearAdvancedSearch() {
    if (typeof $ === 'undefined') {
        alert('Clear search requires jQuery. Please refresh the page.');
        return;
    }
    
    // Clear all form fields
    $('#advancedSearchForm')[0].reset();
    
    // Clear custom search functions
    $.fn.dataTable.ext.search = [];
    
    // Clear DataTable search and show all rows
    const table = $('#activityTable').DataTable();
    table.search('').columns().search('').draw();
    
    alert('All filters cleared. Showing all activities.');
}
</script>