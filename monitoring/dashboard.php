<?php
session_start();
require_once '../config/database.php';
require_once '../config/monitoring_functions.php';
require_once '../includes/header.php';

// Require admin login
requireAdmin();

$page_title = 'Asset Monitoring Dashboard - Asset Management System';

try {
    $pdo = getDB();
    
    // Get monitoring summary
    $summary = getMonitoringSummary($pdo);
    
    // Get detailed monitoring data
    $maintenance_due_30 = getAssetsNearingMaintenance($pdo, 30);
    $maintenance_due_60 = getAssetsNearingMaintenance($pdo, 60);
    $warranty_expiring_30 = getAssetsWithinWarranty($pdo, 30);
    $warranty_expiring_60 = getAssetsWithinWarranty($pdo, 60);
    $depreciated_assets = getDepreciatedAssets($pdo, 0.8, 0.9);
    $replacement_candidates = getReplacementCandidates($pdo, 0.9);
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="fas fa-chart-line me-2"></i>Asset Monitoring Dashboard
                </h1>
                <div class="btn-group">
                    <a href="../maintenance/maintenance.php" class="btn btn-outline-warning">
                        <i class="fas fa-tools me-1"></i>Manage Maintenance
                    </a>
                    <a href="../reports/advanced_reports.php?report=monitoring_summary" class="btn btn-outline-primary">
                        <i class="fas fa-file-alt me-1"></i>Generate Report
                    </a>
                    <a href="../scripts/asset_monitor.php" class="btn btn-outline-success" target="_blank">
                        <i class="fas fa-sync me-1"></i>Update Monitoring
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-tools fa-2x text-warning"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="card-title mb-0">Maintenance Due</h5>
                            <h3 class="mb-0"><?php echo $summary['maintenance_due_30']; ?></h3>
                            <small class="text-muted">Next 30 days</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-shield-alt fa-2x text-info"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="card-title mb-0">Warranty Expiring</h5>
                            <h3 class="mb-0"><?php echo $summary['warranty_expiring_30']; ?></h3>
                            <small class="text-muted">Next 30 days</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-danger">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-chart-line-down fa-2x text-danger"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="card-title mb-0">Highly Depreciated</h5>
                            <h3 class="mb-0"><?php echo $summary['highly_depreciated']; ?></h3>
                            <small class="text-muted">80%+ depreciated</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-dark">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fas fa-recycle fa-2x text-dark"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h5 class="card-title mb-0">Replacement Ready</h5>
                            <h3 class="mb-0"><?php echo $summary['replacement_candidates']; ?></h3>
                            <small class="text-muted">90%+ depreciated</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Monitoring Sections -->
    <div class="row">
        <!-- Assets Nearing Maintenance -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-tools me-2"></i>Assets Nearing Maintenance
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($maintenance_due_30)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Asset</th>
                                        <th>Due Date</th>
                                        <th>Days</th>
                                        <th>Department</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($maintenance_due_30 as $asset): ?>
                                        <tr class="<?php echo $asset['days_until_due'] <= 7 ? 'table-danger' : ($asset['days_until_due'] <= 14 ? 'table-warning' : ''); ?>">
                                            <td>
                                                <strong><?php echo htmlspecialchars($asset['asset_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($asset['control_number']); ?></small>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($asset['next_due_date'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $asset['days_until_due'] <= 7 ? 'danger' : ($asset['days_until_due'] <= 14 ? 'warning' : 'info'); ?>">
                                                    <?php echo $asset['days_until_due']; ?> days
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($asset['department_name'] ?: 'N/A'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">No assets due for maintenance in the next 30 days.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Assets Within Warranty -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-shield-alt me-2"></i>Warranty Expiring Soon
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($warranty_expiring_30)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Asset</th>
                                        <th>Expires</th>
                                        <th>Days</th>
                                        <th>Provider</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($warranty_expiring_30 as $asset): ?>
                                        <tr class="<?php echo $asset['days_until_expires'] <= 7 ? 'table-danger' : ($asset['days_until_expires'] <= 14 ? 'table-warning' : ''); ?>">
                                            <td>
                                                <strong><?php echo htmlspecialchars($asset['asset_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($asset['control_number']); ?></small>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($asset['insurance_end'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $asset['days_until_expires'] <= 7 ? 'danger' : ($asset['days_until_expires'] <= 14 ? 'warning' : 'info'); ?>">
                                                    <?php echo $asset['days_until_expires']; ?> days
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($asset['insurance_provider'] ?: 'N/A'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">No warranties expiring in the next 30 days.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Depreciated Assets -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-line-down me-2"></i>Highly Depreciated Assets (80%-89%)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($depreciated_assets)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Asset</th>
                                        <th>Original Cost</th>
                                        <th>Current Value</th>
                                        <th>Depreciation</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($depreciated_assets as $asset): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($asset['asset_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($asset['control_number']); ?></small>
                                            </td>
                                            <td>₱<?php echo number_format($asset['cost'], 2); ?></td>
                                            <td>₱<?php echo number_format($asset['current_value'], 2); ?></td>
                                            <td>
                                                <span class="badge bg-danger">
                                                    <?php echo $asset['depreciation_percentage']; ?>%
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">No highly depreciated assets found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Replacement Candidates -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-recycle me-2"></i>Replacement Candidates (90%+)
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($replacement_candidates)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Asset</th>
                                        <th>Original Cost</th>
                                        <th>Current Value</th>
                                        <th>Depreciation</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($replacement_candidates as $asset): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($asset['asset_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($asset['control_number']); ?></small>
                                            </td>
                                            <td>₱<?php echo number_format($asset['cost'], 2); ?></td>
                                            <td>₱<?php echo number_format($asset['current_value'], 2); ?></td>
                                            <td>
                                                <span class="badge bg-dark">
                                                    <?php echo $asset['depreciation_percentage']; ?>%
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">No replacement candidates found.</p>
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
                    <h5 class="card-title mb-0">
                        <i class="fas fa-bolt me-2"></i>Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <a href="../reports/advanced_reports.php?report=maintenance_due" class="btn btn-warning w-100">
                                <i class="fas fa-tools me-1"></i>Maintenance Report
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="../reports/advanced_reports.php?report=depreciated_assets" class="btn btn-danger w-100">
                                <i class="fas fa-chart-line-down me-1"></i>Depreciation Report
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="../reports/advanced_reports.php?report=replacement_candidates" class="btn btn-dark w-100">
                                <i class="fas fa-recycle me-1"></i>Replacement Report
                            </a>
                        </div>
                        <div class="col-md-3 mb-2">
                            <a href="../reports/advanced_reports.php?report=all_assets_monitoring" class="btn btn-primary w-100">
                                <i class="fas fa-list me-1"></i>All Assets Report
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
