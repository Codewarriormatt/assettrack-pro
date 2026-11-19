<?php
session_start();
require_once '../config/database.php';
require_once '../includes/header.php';

// Require admin login
requireAdmin();

$page_title = 'Maintenance Management - Asset Management System';

$message = '';
$error = '';

try {
    $pdo = getDB();
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create') {
            $asset_id = intval($_POST['asset_id']);
            $maintenance_date = $_POST['maintenance_date'];
            $next_due_date = $_POST['next_due_date'];
            $details = trim($_POST['details']);
            $status = $_POST['status'];
            $cost = floatval($_POST['cost']);
            
            // Validate required fields
            if (empty($asset_id) || empty($maintenance_date) || empty($next_due_date) || empty($details)) {
                $error = 'Please fill in all required fields.';
            } elseif (strtotime($next_due_date) <= strtotime($maintenance_date)) {
                $error = 'Next due date must be after maintenance date.';
            } else {
                // Check if asset already has pending maintenance
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM maintenance WHERE asset_id = ? AND status = 'Pending'");
                $stmt->execute([$asset_id]);
                $existing_pending = $stmt->fetch()['count'];
                
                if ($existing_pending > 0) {
                    $error = 'This asset already has pending maintenance scheduled. Please complete or cancel the existing maintenance before scheduling new one.';
                } else {
                $stmt = $pdo->prepare("
                    INSERT INTO maintenance (asset_id, maintenance_date, next_due_date, details, status, cost) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                if ($stmt->execute([$asset_id, $maintenance_date, $next_due_date, $details, $status, $cost])) {
                    // Get asset info for logging
                    $stmt = $pdo->prepare("SELECT asset_name, control_number FROM assets WHERE asset_id = ?");
                    $stmt->execute([$asset_id]);
                    $asset = $stmt->fetch();
                    
                    // Automatically update asset status based on maintenance status
                    if ($status === 'Pending') {
                        // Set asset to "Under Maintenance" when scheduling maintenance
                        $stmt = $pdo->prepare("UPDATE assets SET status = 'Under Maintenance' WHERE asset_id = ?");
                        $stmt->execute([$asset_id]);
                        logActivity($pdo, $_SESSION['user_id'], 'UPDATE', 'assets', "Asset status changed to 'Under Maintenance' due to scheduled maintenance: {$asset['asset_name']} ({$asset['control_number']})");
                    } elseif ($status === 'Completed') {
                        // Check if asset is fully depreciated before setting back to Active
                        $stmt = $pdo->prepare("SELECT current_value FROM assets WHERE asset_id = ?");
                        $stmt->execute([$asset_id]);
                        $asset_data = $stmt->fetch();
                        
                        if ($asset_data && $asset_data['current_value'] <= 0) {
                            // Asset is fully depreciated, set to Depreciated status
                            $stmt = $pdo->prepare("UPDATE assets SET status = 'Depreciated' WHERE asset_id = ?");
                            $stmt->execute([$asset_id]);
                            logActivity($pdo, $_SESSION['user_id'], 'UPDATE', 'assets', "Asset status changed to 'Depreciated' after completed maintenance (fully depreciated): {$asset['asset_name']} ({$asset['control_number']})");
                        } else {
                            // Asset still has value, set back to Active
                            $stmt = $pdo->prepare("UPDATE assets SET status = 'Active' WHERE asset_id = ?");
                            $stmt->execute([$asset_id]);
                            logActivity($pdo, $_SESSION['user_id'], 'UPDATE', 'assets', "Asset status changed to 'Active' after completed maintenance: {$asset['asset_name']} ({$asset['control_number']})");
                        }
                    }
                    
                    logActivity($pdo, $_SESSION['user_id'], 'CREATE', 'maintenance', "Created maintenance record for asset: {$asset['asset_name']} ({$asset['control_number']})");
                    $message = 'Maintenance record created successfully! Asset status updated automatically.';
                } else {
                    $error = 'Failed to create maintenance record. Please try again.';
                }
                }
            }
        }
        
        elseif ($action === 'update') {
            $maintenance_id = intval($_POST['maintenance_id']);
            $asset_id = intval($_POST['asset_id']);
            $maintenance_date = $_POST['maintenance_date'];
            $next_due_date = $_POST['next_due_date'];
            $details = trim($_POST['details']);
            $status = $_POST['status'];
            $cost = floatval($_POST['cost']);
            
            if (strtotime($next_due_date) <= strtotime($maintenance_date)) {
                $error = 'Next due date must be after maintenance date.';
            } else {
                // Check if we're changing to a different asset that already has pending maintenance
                $stmt = $pdo->prepare("SELECT asset_id FROM maintenance WHERE maintenance_id = ?");
                $stmt->execute([$maintenance_id]);
                $current_asset = $stmt->fetch();
                
                if ($current_asset && $current_asset['asset_id'] != $asset_id) {
                    // Asset is being changed, check if new asset has pending maintenance
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM maintenance WHERE asset_id = ? AND status = 'Pending'");
                    $stmt->execute([$asset_id]);
                    $existing_pending = $stmt->fetch()['count'];
                    
                    if ($existing_pending > 0) {
                        $error = 'The selected asset already has pending maintenance scheduled. Please complete or cancel the existing maintenance first.';
                    }
                }
                
                if (empty($error)) {
                // Get the previous maintenance status BEFORE updating
                $stmt = $pdo->prepare("SELECT status FROM maintenance WHERE maintenance_id = ?");
                $stmt->execute([$maintenance_id]);
                $old_maintenance = $stmt->fetch();
                $old_status = $old_maintenance['status'];
                
                $stmt = $pdo->prepare("
                    UPDATE maintenance SET 
                        asset_id = ?, maintenance_date = ?, next_due_date = ?, 
                        details = ?, status = ?, cost = ? 
                    WHERE maintenance_id = ?
                ");
                
                if ($stmt->execute([$asset_id, $maintenance_date, $next_due_date, $details, $status, $cost, $maintenance_id])) {
                    // Get asset info for logging
                    $stmt = $pdo->prepare("SELECT asset_name, control_number FROM assets WHERE asset_id = ?");
                    $stmt->execute([$asset_id]);
                    $asset = $stmt->fetch();
                    
                    // Automatically update asset status based on maintenance status change
                    if ($status === 'Pending' && $old_status !== 'Pending') {
                        // Set asset to "Under Maintenance" when changing to pending
                        $stmt = $pdo->prepare("UPDATE assets SET status = 'Under Maintenance' WHERE asset_id = ?");
                        $stmt->execute([$asset_id]);
                        logActivity($pdo, $_SESSION['user_id'], 'UPDATE', 'assets', "Asset status changed to 'Under Maintenance' due to pending maintenance: {$asset['asset_name']} ({$asset['control_number']})");
                    } elseif ($status === 'Completed' && $old_status !== 'Completed') {
                        // Check if asset is fully depreciated before setting back to Active
                        $stmt = $pdo->prepare("SELECT current_value FROM assets WHERE asset_id = ?");
                        $stmt->execute([$asset_id]);
                        $asset_data = $stmt->fetch();
                        
                        if ($asset_data && $asset_data['current_value'] <= 0) {
                            // Asset is fully depreciated, set to Depreciated status
                            $stmt = $pdo->prepare("UPDATE assets SET status = 'Depreciated' WHERE asset_id = ?");
                            $stmt->execute([$asset_id]);
                            logActivity($pdo, $_SESSION['user_id'], 'UPDATE', 'assets', "Asset status changed to 'Depreciated' after completed maintenance (fully depreciated): {$asset['asset_name']} ({$asset['control_number']})");
                        } else {
                            // Asset still has value, set back to Active
                            $stmt = $pdo->prepare("UPDATE assets SET status = 'Active' WHERE asset_id = ?");
                            $stmt->execute([$asset_id]);
                            logActivity($pdo, $_SESSION['user_id'], 'UPDATE', 'assets', "Asset status changed to 'Active' after completed maintenance: {$asset['asset_name']} ({$asset['control_number']})");
                        }
                    }
                    
                    logActivity($pdo, $_SESSION['user_id'], 'UPDATE', 'maintenance', "Updated maintenance record for asset: {$asset['asset_name']} ({$asset['control_number']})");
                    $message = 'Maintenance record updated successfully! Asset status updated automatically.';
                } else {
                    $error = 'Failed to update maintenance record. Please try again.';
                }
                }
            }
        }
        
        elseif ($action === 'delete') {
            $maintenance_id = intval($_POST['maintenance_id']);
            
            // Get maintenance info for logging
            $stmt = $pdo->prepare("
                SELECT m.*, a.asset_name, a.control_number 
                FROM maintenance m 
                JOIN assets a ON m.asset_id = a.asset_id 
                WHERE m.maintenance_id = ?
            ");
            $stmt->execute([$maintenance_id]);
            $maintenance = $stmt->fetch();
            
            if ($maintenance) {
                $stmt = $pdo->prepare("DELETE FROM maintenance WHERE maintenance_id = ?");
                if ($stmt->execute([$maintenance_id])) {
                    // If the deleted maintenance was pending, set asset back to Active
                    if ($maintenance['status'] === 'Pending') {
                        $stmt = $pdo->prepare("UPDATE assets SET status = 'Active' WHERE asset_id = ?");
                        $stmt->execute([$maintenance['asset_id']]);
                        logActivity($pdo, $_SESSION['user_id'], 'UPDATE', 'assets', "Asset status changed to 'Active' after deleting pending maintenance: {$maintenance['asset_name']} ({$maintenance['control_number']})");
                    }
                    
                    logActivity($pdo, $_SESSION['user_id'], 'DELETE', 'maintenance', "Deleted maintenance record for asset: {$maintenance['asset_name']} ({$maintenance['control_number']})");
                    $message = 'Maintenance record deleted successfully! Asset status updated automatically.';
                } else {
                    $error = 'Failed to delete maintenance record. Please try again.';
                }
            } else {
                $error = 'Maintenance record not found.';
            }
        }
    }
    
    // Get assets for dropdown (Active and Under Maintenance assets)
    $stmt = $pdo->query("SELECT asset_id, asset_name, control_number, status FROM assets WHERE status IN ('Active', 'Under Maintenance') ORDER BY asset_name");
    $assets = $stmt->fetchAll();
    
    // Get maintenance records with asset info
    $stmt = $pdo->query("
        SELECT m.*, a.asset_name, a.control_number, a.assigned_to_name,
               DATEDIFF(m.next_due_date, CURDATE()) as days_until_due
        FROM maintenance m 
        JOIN assets a ON m.asset_id = a.asset_id 
        ORDER BY m.next_due_date ASC
    ");
    $maintenance_records = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="fas fa-tools me-2"></i>Maintenance Management
                </h1>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-primary" id="advancedSearchBtn" data-bs-toggle="modal" data-bs-target="#advancedSearchModal" title="Advanced Search">
                        <i class="fas fa-filter me-1"></i>Advanced Search
                    </button>
                    <a href="../monitoring/dashboard.php" class="btn btn-outline-info">
                        <i class="fas fa-chart-line me-1"></i>Asset Monitoring
                    </a>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#maintenanceModal" onclick="openMaintenanceModal()">
                        <i class="fas fa-plus me-1"></i>Schedule Maintenance
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="location.reload()">
                        <i class="fas fa-sync-alt me-1"></i>Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Maintenance Table -->
<div class="card shadow mb-4">
    <div class="card-header py-3 bg-primary text-white">
        <h6 class="m-0 font-weight-bold"><i class="fas fa-list me-2"></i>Maintenance Records</h6>
    </div>
    <div class="card-body">
        <!-- Fallback controls (will be replaced by DataTables) -->
        <div class="row mb-3" id="fallbackControls" style="display: none;">
            <div class="col-sm-6">
                <label>Show 
                    <select class="form-select form-select-sm d-inline-block w-auto">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    entries
                </label>
            </div>
            <div class="col-sm-6 text-end">
                <label>Search: 
                    <input type="search" class="form-control form-control-sm d-inline-block w-auto" placeholder="Search maintenance records...">
                </label>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-bordered table-hover" id="maintenanceTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Asset</th>
                        <th>Control Number</th>
                        <th>Assigned To</th>
                        <th>Maintenance Date</th>
                        <th>Next Due Date</th>
                        <th>Days Until Due</th>
                        <th>Status</th>
                        <th>Cost</th>
                        <th>Details</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($maintenance_records as $record): ?>
                        <tr class="<?php echo $record['days_until_due'] <= 7 && $record['status'] === 'Pending' ? 'table-warning' : ''; ?>">
                            <td><?php echo $record['maintenance_id']; ?></td>
                            <td><?php echo htmlspecialchars($record['asset_name']); ?></td>
                            <td><?php echo htmlspecialchars($record['control_number']); ?></td>
                            <td><?php echo htmlspecialchars($record['assigned_to_name'] ?: 'Unassigned'); ?></td>
                            <td><?php echo formatDate($record['maintenance_date']); ?></td>
                            <td><?php echo formatDate($record['next_due_date']); ?></td>
                            <td>
                                <?php if ($record['status'] === 'Pending'): ?>
                                    <span class="badge bg-<?php 
                                        echo $record['days_until_due'] <= 0 ? 'danger' : 
                                            ($record['days_until_due'] <= 7 ? 'warning' : 'info'); 
                                    ?>">
                                        <?php echo $record['days_until_due'] > 0 ? $record['days_until_due'] . ' days' : 'Overdue'; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-success">Completed</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge status-<?php echo strtolower(str_replace(' ', '-', $record['status'])); ?>">
                                    <?php echo htmlspecialchars($record['status']); ?>
                                </span>
                            </td>
                            <td class="currency"><?php echo formatCurrency($record['cost']); ?></td>
                            <td>
                                <span class="text-truncate d-inline-block" style="max-width: 200px;" 
                                      data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($record['details']); ?>">
                                    <?php echo htmlspecialchars($record['details']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="editMaintenance(<?php echo htmlspecialchars(json_encode($record)); ?>)"
                                            data-bs-toggle="tooltip" title="Edit Maintenance">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            onclick="deleteMaintenance(<?php echo $record['maintenance_id']; ?>, <?php echo htmlspecialchars(json_encode($record['asset_name']), ENT_QUOTES); ?>)"
                                            data-bs-toggle="tooltip" title="Delete Maintenance">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Fallback pagination info (will be replaced by DataTables) -->
        <div class="row mt-3" id="fallbackPagination" style="display: none;">
            <div class="col-sm-6">
                <div class="text-muted">
                    Showing <?php echo min(10, count($maintenance_records)); ?> of <?php echo count($maintenance_records); ?> entries
                </div>
            </div>
            <div class="col-sm-6 text-end">
                <nav>
                    <ul class="pagination pagination-sm justify-content-end">
                        <li class="page-item disabled"><span class="page-link">Previous</span></li>
                        <li class="page-item active"><span class="page-link">1</span></li>
                        <li class="page-item disabled"><span class="page-link">Next</span></li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Maintenance Modal -->
<div class="modal fade" id="maintenanceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="maintenanceModalTitle">Schedule Maintenance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="maintenanceForm">
                <div class="modal-body">
                    <input type="hidden" name="action" id="maintenanceAction" value="create">
                    <input type="hidden" name="maintenance_id" id="maintenanceId">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="asset_id" class="form-label">Asset <span class="text-danger">*</span></label>
                            <select class="form-select" id="asset_id" name="asset_id" required>
                                <option value="">Select Asset</option>
                                <?php foreach ($assets as $asset): ?>
                                    <option value="<?php echo $asset['asset_id']; ?>">
                                        <?php echo htmlspecialchars($asset['asset_name'] . ' (' . $asset['control_number'] . ')'); ?>
                                        <?php if ($asset['status'] === 'Under Maintenance'): ?>
                                            - [Under Maintenance]
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="Pending">Pending</option>
                                <option value="Completed">Completed</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="maintenance_date" class="form-label">Maintenance Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="maintenance_date" name="maintenance_date" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="next_due_date" class="form-label">Next Due Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="next_due_date" name="next_due_date" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="cost" class="form-label">Cost (₱)</label>
                            <input type="number" class="form-control" id="cost" name="cost" step="0.01" min="0" value="0">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="details" class="form-label">Details <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="details" name="details" rows="4" required placeholder="Describe the maintenance work performed or scheduled..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Maintenance</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmModalTitle">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="confirmModalBody">
                Are you sure you want to perform this action?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmModalBtn">Confirm</button>
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
                    <i class="fas fa-search me-2"></i>Advanced Search - Maintenance Records
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="advancedSearchForm">
                    <div class="row g-3">
                        <!-- Asset Information Section -->
                        <div class="col-12">
                            <h6 class="text-primary mb-3"><i class="fas fa-boxes me-2"></i>Asset Information</h6>
                        </div>
                        <div class="col-md-6">
                            <label for="searchAssetName" class="form-label">Asset Name</label>
                            <input type="text" class="form-control" id="searchAssetName" placeholder="Search by asset name">
                        </div>
                        <div class="col-md-6">
                            <label for="searchControlNumber" class="form-label">Control Number</label>
                            <input type="text" class="form-control" id="searchControlNumber" placeholder="Search by control number">
                        </div>
                        <div class="col-md-6">
                            <label for="searchAssignedTo" class="form-label">Assigned To</label>
                            <input type="text" class="form-control" id="searchAssignedTo" placeholder="Search by assigned person">
                        </div>
                        
                        <!-- Maintenance Information Section -->
                        <div class="col-12">
                            <h6 class="text-warning mb-3"><i class="fas fa-tools me-2"></i>Maintenance Information</h6>
                        </div>
                        <div class="col-md-6">
                            <label for="searchStatus" class="form-label">Status</label>
                            <select class="form-select" id="searchStatus">
                                <option value="">All Statuses</option>
                                <option value="Pending">Pending</option>
                                <option value="Completed">Completed</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="searchDaysUntilDue" class="form-label">Days Until Due</label>
                            <select class="form-select" id="searchDaysUntilDue">
                                <option value="">All</option>
                                <option value="overdue">Overdue</option>
                                <option value="due_soon">Due Soon (≤7 days)</option>
                                <option value="due_later">Due Later (>7 days)</option>
                            </select>
                        </div>
                        
                        <!-- Date Range Section -->
                        <div class="col-12">
                            <h6 class="text-info mb-3"><i class="fas fa-calendar me-2"></i>Date Range</h6>
                        </div>
                        <div class="col-md-6">
                            <label for="searchMaintenanceDateFrom" class="form-label">Maintenance Date From</label>
                            <input type="date" class="form-control" id="searchMaintenanceDateFrom">
                        </div>
                        <div class="col-md-6">
                            <label for="searchMaintenanceDateTo" class="form-label">Maintenance Date To</label>
                            <input type="date" class="form-control" id="searchMaintenanceDateTo">
                        </div>
                        <div class="col-md-6">
                            <label for="searchDueDateFrom" class="form-label">Due Date From</label>
                            <input type="date" class="form-control" id="searchDueDateFrom">
                        </div>
                        <div class="col-md-6">
                            <label for="searchDueDateTo" class="form-label">Due Date To</label>
                            <input type="date" class="form-control" id="searchDueDateTo">
                        </div>
                        
                        <!-- Cost Range Section -->
                        <div class="col-12">
                            <h6 class="text-success mb-3"><i class="fas fa-money-bill me-2"></i>Cost Range</h6>
                        </div>
                        <div class="col-md-6">
                            <label for="searchCostMin" class="form-label">Minimum Cost (₱)</label>
                            <input type="number" class="form-control" id="searchCostMin" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="col-md-6">
                            <label for="searchCostMax" class="form-label">Maximum Cost (₱)</label>
                            <input type="number" class="form-control" id="searchCostMax" step="0.01" min="0" placeholder="999999.99">
                        </div>
                        
                        <!-- Details Search -->
                        <div class="col-12">
                            <h6 class="text-secondary mb-3"><i class="fas fa-comment me-2"></i>Details</h6>
                        </div>
                        <div class="col-12">
                            <label for="searchDetails" class="form-label">Details Contains</label>
                            <input type="text" class="form-control" id="searchDetails" placeholder="Search in maintenance details">
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

<style>
/* Ensure DataTable controls are always visible */
.dataTables_length,
.dataTables_filter,
.dataTables_info,
.dataTables_paginate {
    display: block !important;
    visibility: visible !important;
}

.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter {
    margin: 10px 0 !important;
}
</style>

<script>
$(document).ready(function() {
    // Hide fallback controls immediately
    $('#fallbackControls').hide();
    $('#fallbackPagination').hide();
    
    // Initialize DataTable with simple layout (exactly like activity_log.php)
    $('#maintenanceTable').DataTable({
        responsive: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        dom: 'lfrtip', // Length menu, filtering, responsive, table, info, pagination
        order: [[5, 'asc']], // Sort by next due date ascending by default
        language: {
            search: "Search maintenance records:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "No maintenance records found",
            infoFiltered: "(filtered from _MAX_ total entries)",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            },
            emptyTable: "No maintenance data available"
        }
    });
    
    // Set today's date as default for maintenance date
    document.getElementById('maintenance_date').valueAsDate = new Date();
    
    // Set next due date to 6 months from now
    const nextDueDate = new Date();
    nextDueDate.setMonth(nextDueDate.getMonth() + 6);
    document.getElementById('next_due_date').valueAsDate = nextDueDate;
});

function openMaintenanceModal() {
    document.getElementById('maintenanceModalTitle').textContent = 'Schedule Maintenance';
    document.getElementById('maintenanceAction').value = 'create';
    document.getElementById('maintenanceForm').reset();
    document.getElementById('maintenanceId').value = '';
    document.getElementById('maintenance_date').valueAsDate = new Date();
    
    const nextDueDate = new Date();
    nextDueDate.setMonth(nextDueDate.getMonth() + 6);
    document.getElementById('next_due_date').valueAsDate = nextDueDate;
}

function editMaintenance(maintenance) {
    document.getElementById('maintenanceModalTitle').textContent = 'Edit Maintenance';
    document.getElementById('maintenanceAction').value = 'update';
    document.getElementById('maintenanceId').value = maintenance.maintenance_id;
    document.getElementById('asset_id').value = maintenance.asset_id;
    document.getElementById('maintenance_date').value = maintenance.maintenance_date;
    document.getElementById('next_due_date').value = maintenance.next_due_date;
    document.getElementById('details').value = maintenance.details;
    document.getElementById('status').value = maintenance.status;
    document.getElementById('cost').value = maintenance.cost;
    
    new bootstrap.Modal(document.getElementById('maintenanceModal')).show();
}

function deleteMaintenance(maintenanceId, assetName) {
    showConfirmModal(
        'Delete Maintenance Record',
        `Are you sure you want to delete the maintenance record for "${assetName}"? This action cannot be undone.`,
        function() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="maintenance_id" value="${maintenanceId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    );
}

// Form validation
document.getElementById('maintenanceForm').addEventListener('submit', function(e) {
    const maintenanceDate = new Date(document.getElementById('maintenance_date').value);
    const nextDueDate = new Date(document.getElementById('next_due_date').value);
    
    if (nextDueDate <= maintenanceDate) {
        e.preventDefault();
        alert('Next due date must be after maintenance date.');
        return;
    }
});

// Advanced Search Functions
function applyAdvancedSearch() {
    if (typeof $ === 'undefined') {
        alert('Advanced search requires jQuery. Please refresh the page.');
        return;
    }
    
    const table = $('#maintenanceTable').DataTable();
    
    // Clear all previous searches
    table.search('').columns().search('').draw();
    
    // Get search values
    const assetName = $('#searchAssetName').val().trim();
    const controlNumber = $('#searchControlNumber').val().trim();
    const assignedTo = $('#searchAssignedTo').val().trim();
    const status = $('#searchStatus').val();
    const daysUntilDue = $('#searchDaysUntilDue').val();
    const maintenanceDateFrom = $('#searchMaintenanceDateFrom').val();
    const maintenanceDateTo = $('#searchMaintenanceDateTo').val();
    const dueDateFrom = $('#searchDueDateFrom').val();
    const dueDateTo = $('#searchDueDateTo').val();
    const costMin = $('#searchCostMin').val();
    const costMax = $('#searchCostMax').val();
    const details = $('#searchDetails').val().trim();
    
    // Apply column-specific searches
    if (assetName) {
        table.column(1).search(assetName, false, false);
    }
    
    if (controlNumber) {
        table.column(2).search(controlNumber, false, false);
    }
    
    if (assignedTo) {
        table.column(3).search(assignedTo, false, false);
    }
    
    if (status) {
        table.column(7).search(status, false, false);
    }
    
    if (details) {
        table.column(9).search(details, false, false);
    }
    
    // Clear any existing custom search functions
    $.fn.dataTable.ext.search = [];
    
    // Apply custom search functions for complex filters
    if (daysUntilDue || maintenanceDateFrom || maintenanceDateTo || dueDateFrom || dueDateTo || costMin || costMax) {
        $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
            if (settings.nTable.id !== 'maintenanceTable') {
                return true;
            }
            
            // Days until due filter
            if (daysUntilDue) {
                const daysText = data[6]; // Days Until Due column
                const isOverdue = daysText.includes('Overdue');
                const daysMatch = daysText.match(/(\d+)\s+days/);
                const days = daysMatch ? parseInt(daysMatch[1]) : (isOverdue ? -1 : 999);
                
                switch (daysUntilDue) {
                    case 'overdue':
                        if (!isOverdue) return false;
                        break;
                    case 'due_soon':
                        if (isOverdue || days > 7) return false;
                        break;
                    case 'due_later':
                        if (isOverdue || days <= 7) return false;
                        break;
                }
            }
            
            // Maintenance date range filter
            if (maintenanceDateFrom || maintenanceDateTo) {
                const maintenanceDate = new Date(data[4]);
                if (maintenanceDateFrom && maintenanceDate < new Date(maintenanceDateFrom)) {
                    return false;
                }
                if (maintenanceDateTo && maintenanceDate > new Date(maintenanceDateTo + 'T23:59:59')) {
                    return false;
                }
            }
            
            // Due date range filter
            if (dueDateFrom || dueDateTo) {
                const dueDate = new Date(data[5]);
                if (dueDateFrom && dueDate < new Date(dueDateFrom)) {
                    return false;
                }
                if (dueDateTo && dueDate > new Date(dueDateTo + 'T23:59:59')) {
                    return false;
                }
            }
            
            // Cost range filter
            if (costMin || costMax) {
                const costText = data[8]; // Cost column
                const costMatch = costText.match(/₱([\d,]+\.?\d*)/);
                const cost = costMatch ? parseFloat(costMatch[1].replace(/,/g, '')) : 0;
                
                if (costMin && cost < parseFloat(costMin)) {
                    return false;
                }
                if (costMax && cost > parseFloat(costMax)) {
                    return false;
                }
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
        alert('No maintenance records found matching the search criteria.');
    } else {
        alert(`Found ${visibleRows} maintenance record${visibleRows === 1 ? '' : 's'} matching the search criteria.`);
    }
    
    // Close modal
    $('#advancedSearchModal').modal('hide');
}

function clearAdvancedSearch(silent = false) {
    if (typeof $ === 'undefined') {
        if (!silent) alert('Clear search requires jQuery. Please refresh the page.');
        return;
    }
    
    // Clear all form fields
    $('#advancedSearchForm')[0].reset();
    
    // Clear custom search functions
    $.fn.dataTable.ext.search = [];
    
    // Clear DataTable search and show all rows
    const table = $('#maintenanceTable').DataTable();
    table.search('').columns().search('').draw();
    
    if (!silent) {
        alert('All filters cleared. Showing all maintenance records.');
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
