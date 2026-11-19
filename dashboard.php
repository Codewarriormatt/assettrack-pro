<?php
session_start();
require_once 'config/database.php';
require_once 'includes/header.php';

// Require admin login
requireAdmin();

$page_title = 'Dashboard - Asset Management System';

try {
    $pdo = getDB();
    
    // Get summary statistics
    $stats = [];
    
    // Total Assets
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM assets");
    $stats['total_assets'] = $stmt->fetch()['count'];
    
    // Active Assets
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM assets WHERE status = 'Active'");
    $stats['active_assets'] = $stmt->fetch()['count'];
    
    // Depreciated Assets
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM assets WHERE status = 'Depreciated'");
    $stats['depreciated_assets'] = $stmt->fetch()['count'];
    
    // Assets Under Warranty
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM assets WHERE insurance_end >= CURDATE()");
    $stats['under_warranty'] = $stmt->fetch()['count'];
    
    // Assets Due for Maintenance (within 30 days)
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM maintenance WHERE status = 'Pending' AND next_due_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
    $stats['maintenance_due'] = $stmt->fetch()['count'];
    
    // Total Asset Value
    $stmt = $pdo->query("SELECT SUM(cost) as total FROM assets WHERE status = 'Active'");
    $stats['total_value'] = $stmt->fetch()['total'] ?? 0;
    
    // Recent Activity (last 10 activities)
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
        LIMIT 10
    ");
    $recent_activities = $stmt->fetchAll();
    
    // Assets nearing maintenance
    $stmt = $pdo->query("
        SELECT a.asset_name, a.control_number, m.next_due_date, 
               DATEDIFF(m.next_due_date, CURDATE()) as days_until_due
        FROM maintenance m
        JOIN assets a ON m.asset_id = a.asset_id
        WHERE m.status = 'Pending' AND m.next_due_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        ORDER BY m.next_due_date ASC
        LIMIT 5
    ");
    $maintenance_due = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
                <i class="fas fa-sync-alt me-1"></i>Refresh
            </button>
        </div>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
        <div class="card summary-card h-100">
            <div class="card-body text-center">
                <div class="d-flex align-items-center justify-content-center mb-2">
                    <i class="fas fa-boxes fa-2x text-primary me-2"></i>
                </div>
                <h3 class="mb-1"><?php echo number_format($stats['total_assets']); ?></h3>
                <p class="text-muted mb-0">Total Assets</p>
            </div>
        </div>
    </div>
    
    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
        <div class="card summary-card h-100">
            <div class="card-body text-center">
                <div class="d-flex align-items-center justify-content-center mb-2">
                    <i class="fas fa-check-circle fa-2x text-success me-2"></i>
                </div>
                <h3 class="mb-1"><?php echo number_format($stats['active_assets']); ?></h3>
                <p class="text-muted mb-0">Active Assets</p>
            </div>
        </div>
    </div>
    
    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
        <div class="card summary-card h-100">
            <div class="card-body text-center">
                <div class="d-flex align-items-center justify-content-center mb-2">
                    <i class="fas fa-exclamation-triangle fa-2x text-warning me-2"></i>
                </div>
                <h3 class="mb-1"><?php echo number_format($stats['depreciated_assets']); ?></h3>
                <p class="text-muted mb-0">Depreciated</p>
            </div>
        </div>
    </div>
    
    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
        <div class="card summary-card h-100">
            <div class="card-body text-center">
                <div class="d-flex align-items-center justify-content-center mb-2">
                    <i class="fas fa-shield-alt fa-2x text-info me-2"></i>
                </div>
                <h3 class="mb-1"><?php echo number_format($stats['under_warranty']); ?></h3>
                <p class="text-muted mb-0">Under Warranty</p>
            </div>
        </div>
    </div>
    
    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
        <div class="card summary-card h-100">
            <div class="card-body text-center">
                <div class="d-flex align-items-center justify-content-center mb-2">
                    <i class="fas fa-tools fa-2x text-danger me-2"></i>
                </div>
                <h3 class="mb-1"><?php echo number_format($stats['maintenance_due']); ?></h3>
                <p class="text-muted mb-0">Maintenance Due</p>
            </div>
        </div>
    </div>
    
    <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
        <div class="card summary-card h-100">
            <div class="card-body text-center">
                <div class="d-flex align-items-center justify-content-center mb-2">
                    <i class="fas fa-peso-sign fa-2x text-success me-2"></i>
                </div>
                <h3 class="mb-1 currency"><?php echo formatCurrency($stats['total_value']); ?></h3>
                <p class="text-muted mb-0">Total Value</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Activity -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Recent Activity</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_activities)): ?>
                    <p class="text-muted text-center py-3">No recent activity found.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover" id="activityTable">
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
                                <?php foreach ($recent_activities as $activity): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y H:i', strtotime($activity['timestamp'])); ?></td>
                                        <td>
                                            <?php if ($activity['user_id'] == 0): ?>
                                                <span class="badge bg-warning text-dark">
                                                    <i class="fas fa-cog me-1"></i><?php echo htmlspecialchars($activity['full_name']); ?>
                                                </span>
                                            <?php elseif ($activity['full_name'] == 'Deleted User'): ?>
                                                <span class="badge bg-danger text-white">
                                                    <i class="fas fa-user-times me-1"></i><?php echo htmlspecialchars($activity['full_name']); ?>
                                                </span>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($activity['full_name']); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $activity['action_type'] === 'CREATE' ? 'success' : 
                                                    ($activity['action_type'] === 'UPDATE' ? 'warning' : 
                                                    ($activity['action_type'] === 'DELETE' ? 'danger' : 'info')); 
                                            ?>">
                                                <?php echo htmlspecialchars($activity['action_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($activity['table_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($activity['description']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Maintenance Due -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-tools me-2"></i>Maintenance Due</h5>
            </div>
            <div class="card-body">
                <?php if (empty($maintenance_due)): ?>
                    <p class="text-muted text-center py-3">No maintenance due in the next 30 days.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($maintenance_due as $maintenance): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($maintenance['asset_name']); ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($maintenance['control_number']); ?></small>
                                    <br>
                                    <small class="text-muted">Due: <?php echo formatDate($maintenance['next_due_date']); ?></small>
                                </div>
                                <span class="badge bg-<?php echo $maintenance['days_until_due'] <= 7 ? 'danger' : 'warning'; ?>">
                                    <?php echo $maintenance['days_until_due']; ?> days
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-center mt-3">
                        <a href="<?php echo getBaseUrl(); ?>/maintenance/maintenance.php" class="btn btn-sm btn-outline-primary">
                            View All Maintenance
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 col-sm-6 mb-3">
                        <a href="<?php echo getBaseUrl(); ?>/assets/assets.php" class="btn btn-outline-primary w-100">
                            <i class="fas fa-plus me-2"></i>Add New Asset
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <a href="<?php echo getBaseUrl(); ?>/users/users.php" class="btn btn-outline-success w-100">
                            <i class="fas fa-user-plus me-2"></i>Create User Account
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <a href="<?php echo getBaseUrl(); ?>/maintenance/maintenance.php" class="btn btn-outline-warning w-100">
                            <i class="fas fa-tools me-2"></i>Schedule Maintenance
                        </a>
                    </div>
                    <div class="col-md-3 col-sm-6 mb-3">
                        <a href="<?php echo getBaseUrl(); ?>/reports/reports.php" class="btn btn-outline-info w-100">
                            <i class="fas fa-chart-bar me-2"></i>Generate Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize activity table
    $('#activityTable').DataTable({
        responsive: true,
        pageLength: 10,
        lengthMenu: [[10], [10]],
        dom: 'rt',
        order: [[0, 'desc']],
        paging: false,
        language: {
            search: "Search activities:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "No activities found",
            infoFiltered: "(filtered from _MAX_ total entries)"
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
