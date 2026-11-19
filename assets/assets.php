<?php
session_start();
require_once '../config/database.php';
require_once '../includes/header.php';

// Require admin login
requireAdmin();

$page_title = 'Asset Management - Asset Management System';
$message = '';
$error = '';
$assets = [];
$departments = [];

try {
    $pdo = getDB();
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        // Process form submission
        
        if ($action === 'create') {
            $asset_name = trim($_POST['asset_name']);
            $description = trim($_POST['description']);
            $control_number = trim($_POST['control_number']);
            $purchase_date = $_POST['purchase_date'];
            $cost = floatval($_POST['cost']);
            $lifetime_years = intval($_POST['lifetime_years']);
            $depreciation_method = $_POST['depreciation_method'];
            $status = $_POST['status'];
            $assigned_to_name = trim($_POST['assigned_to_name']);
            $assigned_to_email = trim($_POST['assigned_to_email']);
            $assigned_to_contact = trim($_POST['assigned_to_contact']);
            $department_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
            $asset_location = trim($_POST['asset_location']);
            $insurance_type = trim($_POST['insurance_type']);
            $insurance_provider = trim($_POST['insurance_provider']);
            $insurance_start = !empty($_POST['insurance_start']) ? $_POST['insurance_start'] : null;
            $insurance_end = !empty($_POST['insurance_end']) ? $_POST['insurance_end'] : null;
            $insurance_details = trim($_POST['insurance_details']);
            
            // Enhanced validation
            $validation_errors = [];
            
            // Required field validation
            if (empty($asset_name)) $validation_errors[] = 'Asset name is required.';
            if (empty($control_number)) $validation_errors[] = 'Control number is required.';
            if (empty($asset_location)) $validation_errors[] = 'Asset location is required.';
            if (empty($purchase_date)) $validation_errors[] = 'Purchase date is required.';
            if (empty($cost)) $validation_errors[] = 'Cost is required.';
            if (empty($lifetime_years)) $validation_errors[] = 'Lifetime is required.';
            
            // Data type and range validation
            if ($cost <= 0) $validation_errors[] = 'Cost must be greater than 0.';
            if ($lifetime_years < 1 || $lifetime_years > 50) $validation_errors[] = 'Lifetime must be between 1 and 50 years.';
            
            // Date validation
            if ($purchase_date && strtotime($purchase_date) > time()) {
                $validation_errors[] = 'Purchase date cannot be in the future.';
            }
            
            // Email validation
            if (!empty($assigned_to_email) && !filter_var($assigned_to_email, FILTER_VALIDATE_EMAIL)) {
                $validation_errors[] = 'Please enter a valid email address.';
            }
            
            // Contact number validation
            if (!empty($assigned_to_contact) && !preg_match('/^[0-9+\-\s()]{7,15}$/', $assigned_to_contact)) {
                $validation_errors[] = 'Please enter a valid contact number.';
            }
            
            // Insurance date validation
            if (!empty($insurance_start) && !empty($insurance_end) && strtotime($insurance_end) <= strtotime($insurance_start)) {
                $validation_errors[] = 'Insurance end date must be after start date.';
            }
            
            // Assignment Information - CONDITIONAL REQUIRED
            $assignmentFields = [$assigned_to_name, $assigned_to_email, $assigned_to_contact, $department_id];
            $hasAssignmentData = !empty(array_filter($assignmentFields));
            
            if ($hasAssignmentData) {
                if (empty($assigned_to_name)) $validation_errors[] = 'Assigned person name is required when assignment information is provided.';
                if (empty($department_id)) $validation_errors[] = 'Department is required when assignment information is provided.';
                if (empty($assigned_to_email)) $validation_errors[] = 'Email is required when assignment information is provided.';
                if (empty($assigned_to_contact)) $validation_errors[] = 'Contact number is required when assignment information is provided.';
            }
            
            // Conditional validation for Insurance Information
            $insuranceFields = [$insurance_type, $insurance_provider, $insurance_start, $insurance_end, $insurance_details];
            $hasInsuranceData = !empty(array_filter($insuranceFields));
            
            if ($hasInsuranceData) {
                if (empty($insurance_type)) $validation_errors[] = 'Insurance type is required when insurance information is provided.';
                if (empty($insurance_provider)) $validation_errors[] = 'Insurance provider is required when insurance information is provided.';
                if (empty($insurance_start)) $validation_errors[] = 'Insurance start date is required when insurance information is provided.';
                if (empty($insurance_end)) $validation_errors[] = 'Insurance end date is required when insurance information is provided.';
                if (empty($insurance_details)) $validation_errors[] = 'Insurance details are required when insurance information is provided.';
            }
            
            if (empty($validation_errors)) {
                // Check if control number already exists
                $stmt = $pdo->prepare("SELECT asset_id FROM assets WHERE control_number = ?");
                $stmt->execute([$control_number]);
                if ($stmt->fetch()) {
                    $validation_errors[] = 'Control number already exists. Please use a different one.';
                }
                
                if (empty($validation_errors)) {
                    $stmt = $pdo->prepare("
                        INSERT INTO assets (asset_name, description, control_number, purchase_date, cost,
                                          lifetime_years, depreciation_method, status, assigned_to_name, 
                                          assigned_to_email, assigned_to_contact, department_id, asset_location,
                                          insurance_type, insurance_provider, insurance_start, 
                                          insurance_end, insurance_details) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $execute_result = $stmt->execute([
                        $asset_name, $description, $control_number, $purchase_date, $cost,
                        $lifetime_years, $depreciation_method, $status, $assigned_to_name,
                        $assigned_to_email, $assigned_to_contact, $department_id, $asset_location,
                        $insurance_type, $insurance_provider, $insurance_start,
                        $insurance_end, $insurance_details
                    ]);
                    
                    if ($execute_result) {
                        $message = 'Asset created successfully.';
                        logActivity($pdo, $_SESSION['user_id'], 'CREATE', 'assets', "Created asset: $asset_name");
                    } else {
                        $error = 'Failed to create asset.';
                    }
                } else {
                    $error = implode("\n• ", $validation_errors);
                }
            } else {
                $error = implode("\n• ", $validation_errors);
            }
        } elseif ($action === 'update') {
            $asset_id = intval($_POST['asset_id']);
            $asset_name = trim($_POST['asset_name']);
            $description = trim($_POST['description']);
            $control_number = trim($_POST['control_number']);
            $purchase_date = $_POST['purchase_date'];
            $cost = floatval($_POST['cost']);
            $lifetime_years = intval($_POST['lifetime_years']);
            $depreciation_method = $_POST['depreciation_method'];
            $status = $_POST['status'];
            $assigned_to_name = trim($_POST['assigned_to_name']);
            $assigned_to_email = trim($_POST['assigned_to_email']);
            $assigned_to_contact = trim($_POST['assigned_to_contact']);
            $department_id = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
            $asset_location = trim($_POST['asset_location']);
            $insurance_type = trim($_POST['insurance_type']);
            $insurance_provider = trim($_POST['insurance_provider']);
            $insurance_start = !empty($_POST['insurance_start']) ? $_POST['insurance_start'] : null;
            $insurance_end = !empty($_POST['insurance_end']) ? $_POST['insurance_end'] : null;
            $insurance_details = trim($_POST['insurance_details']);
            
            // Enhanced validation
            $validation_errors = [];
            
            // Required field validation
            if (empty($asset_name)) $validation_errors[] = 'Asset name is required.';
            if (empty($control_number)) $validation_errors[] = 'Control number is required.';
            if (empty($asset_location)) $validation_errors[] = 'Asset location is required.';
            if (empty($purchase_date)) $validation_errors[] = 'Purchase date is required.';
            if (empty($cost)) $validation_errors[] = 'Cost is required.';
            if (empty($lifetime_years)) $validation_errors[] = 'Lifetime is required.';
            
            // Data type and range validation
            if ($cost <= 0) $validation_errors[] = 'Cost must be greater than 0.';
            if ($lifetime_years < 1 || $lifetime_years > 50) $validation_errors[] = 'Lifetime must be between 1 and 50 years.';
            
            // Date validation
            if ($purchase_date && strtotime($purchase_date) > time()) {
                $validation_errors[] = 'Purchase date cannot be in the future.';
            }
            
            // Email validation
            if (!empty($assigned_to_email) && !filter_var($assigned_to_email, FILTER_VALIDATE_EMAIL)) {
                $validation_errors[] = 'Please enter a valid email address.';
            }
            
            // Contact number validation
            if (!empty($assigned_to_contact) && !preg_match('/^[0-9+\-\s()]{7,15}$/', $assigned_to_contact)) {
                $validation_errors[] = 'Please enter a valid contact number.';
            }
            
            // Insurance date validation
            if (!empty($insurance_start) && !empty($insurance_end) && strtotime($insurance_end) <= strtotime($insurance_start)) {
                $validation_errors[] = 'Insurance end date must be after start date.';
            }
            
            // Assignment Information - CONDITIONAL REQUIRED
            $assignmentFields = [$assigned_to_name, $assigned_to_email, $assigned_to_contact, $department_id];
            $hasAssignmentData = !empty(array_filter($assignmentFields));
            
            if ($hasAssignmentData) {
                if (empty($assigned_to_name)) $validation_errors[] = 'Assigned person name is required when assignment information is provided.';
                if (empty($department_id)) $validation_errors[] = 'Department is required when assignment information is provided.';
                if (empty($assigned_to_email)) $validation_errors[] = 'Email is required when assignment information is provided.';
                if (empty($assigned_to_contact)) $validation_errors[] = 'Contact number is required when assignment information is provided.';
            }
            
            // Conditional validation for Insurance Information
            $insuranceFields = [$insurance_type, $insurance_provider, $insurance_start, $insurance_end, $insurance_details];
            $hasInsuranceData = !empty(array_filter($insuranceFields));
            
            if ($hasInsuranceData) {
                if (empty($insurance_type)) $validation_errors[] = 'Insurance type is required when insurance information is provided.';
                if (empty($insurance_provider)) $validation_errors[] = 'Insurance provider is required when insurance information is provided.';
                if (empty($insurance_start)) $validation_errors[] = 'Insurance start date is required when insurance information is provided.';
                if (empty($insurance_end)) $validation_errors[] = 'Insurance end date is required when insurance information is provided.';
                if (empty($insurance_details)) $validation_errors[] = 'Insurance details are required when insurance information is provided.';
            }
            
            if (empty($validation_errors)) {
                // Check if control number already exists (excluding current asset)
                $stmt = $pdo->prepare("SELECT asset_id FROM assets WHERE control_number = ? AND asset_id != ?");
                $stmt->execute([$control_number, $asset_id]);
                if ($stmt->fetch()) {
                    $validation_errors[] = 'Control number already exists. Please use a different one.';
                }
                
                if (empty($validation_errors)) {
                    // Calculate current value
                    $current_value = calculateCurrentValue($cost, $lifetime_years, $purchase_date, $depreciation_method);
                    
                    $stmt = $pdo->prepare("
                        UPDATE assets SET 
                            asset_name = ?, description = ?, control_number = ?, purchase_date = ?, 
                            cost = ?, current_value = ?, lifetime_years = ?, depreciation_method = ?, status = ?, 
                            assigned_to_name = ?, assigned_to_email = ?, assigned_to_contact = ?, 
                            department_id = ?, asset_location = ?, insurance_type = ?, insurance_provider = ?, 
                            insurance_start = ?, insurance_end = ?, insurance_details = ?,
                            updated_at = CURRENT_TIMESTAMP
                        WHERE asset_id = ?
                    ");
                    
                    if ($stmt->execute([
                        $asset_name, $description, $control_number, $purchase_date, $cost, $current_value,
                        $lifetime_years, $depreciation_method, $status, $assigned_to_name,
                        $assigned_to_email, $assigned_to_contact, $department_id, $asset_location,
                        $insurance_type, $insurance_provider, $insurance_start,
                        $insurance_end, $insurance_details, $asset_id
                    ])) {
                        $message = 'Asset updated successfully.';
                        logActivity($pdo, $_SESSION['user_id'], 'UPDATE', 'assets', "Updated asset: $asset_name");
                    } else {
                        $error = 'Failed to update asset.';
                    }
                } else {
                    $error = implode("\n• ", $validation_errors);
                }
            } else {
                $error = implode("\n• ", $validation_errors);
            }
        } elseif ($action === 'delete') {
            $asset_id = intval($_POST['asset_id']);
            
            // Get asset name for logging
            $stmt = $pdo->prepare("SELECT asset_name FROM assets WHERE asset_id = ?");
            $stmt->execute([$asset_id]);
            $asset = $stmt->fetch();
            
            if ($asset) {
                $stmt = $pdo->prepare("DELETE FROM assets WHERE asset_id = ?");
                if ($stmt->execute([$asset_id])) {
                    $message = 'Asset deleted successfully.';
                    logActivity($pdo, $_SESSION['user_id'], 'DELETE', 'assets', "Deleted asset: " . $asset['asset_name']);
                } else {
                    $error = 'Failed to delete asset.';
                }
            } else {
                $error = 'Asset not found.';
            }
        }
    }
    
    // Get active departments for dropdown (alphabetically sorted)
    $stmt = $pdo->query("SELECT department_id, department_name FROM departments WHERE status = 'Active' ORDER BY department_name ASC");
    $departments = $stmt->fetchAll();
    
    // Get assets with department info
    $stmt = $pdo->query("
        SELECT a.*, d.department_name 
        FROM assets a 
        LEFT JOIN departments d ON a.department_id = d.department_id 
        ORDER BY a.created_at DESC
    ");
    $assets = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
    if (!isset($assets)) $assets = [];
    if (!isset($departments)) $departments = [];
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-boxes me-2"></i>Asset Management
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-primary" onclick="addAsset()">
                <i class="fas fa-plus me-1"></i>Add New Asset
            </button>
        </div>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i>
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <?php echo nl2br(htmlspecialchars($error)); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<style>
/* Group Required Styling */
.group-required-active {
    border-left: 4px solid #ff9800 !important;
    background: linear-gradient(135deg, #fff3e0 0%, #fce4ec 100%) !important;
    box-shadow: 0 2px 8px rgba(255, 152, 0, 0.2);
    animation: groupPulse 2s infinite;
}

@keyframes groupPulse {
    0% { box-shadow: 0 2px 8px rgba(255, 152, 0, 0.2); }
    50% { box-shadow: 0 4px 16px rgba(255, 152, 0, 0.4); }
    100% { box-shadow: 0 2px 8px rgba(255, 152, 0, 0.2); }
}

.group-required {
    border-color: #ff9800 !important;
    box-shadow: 0 0 0 0.2rem rgba(255, 152, 0, 0.25) !important;
}

.group-required:focus {
    border-color: #ff9800 !important;
    box-shadow: 0 0 0 0.2rem rgba(255, 152, 0, 0.25) !important;
}

.required-indicator {
    animation: pulse 2s infinite;
    background: rgba(255, 152, 0, 0.1);
    padding: 2px 8px;
    border-radius: 4px;
    border: 1px solid #ff9800;
}

@keyframes pulse {
    0% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.8; transform: scale(1.05); }
    100% { opacity: 1; transform: scale(1); }
}

.required-asterisk {
    animation: blink 1.5s infinite;
    font-size: 1.2em;
}

@keyframes blink {
    0%, 50% { opacity: 1; color: #dc3545; }
    51%, 100% { opacity: 0.4; color: #ff9800; }
}

/* Enhanced visual feedback for required groups */
.group-required-active .group-header {
    background: rgba(255, 152, 0, 0.1);
    border-radius: 4px;
    padding: 8px;
    margin: -8px -8px 8px -8px;
}

.group-required-active .form-label {
    font-weight: 600;
    color: #333;
}

.group-required-active .form-control,
.group-required-active .form-select {
    border-width: 2px;
}

.group-required-active .form-control:focus,
.group-required-active .form-select:focus {
    border-color: #ff9800;
    box-shadow: 0 0 0 0.2rem rgba(255, 152, 0, 0.25);
}

/* Group Error Styling */
.group-error-active {
    border-left: 4px solid #dc3545 !important;
    background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%) !important;
    box-shadow: 0 2px 8px rgba(220, 53, 69, 0.2);
    animation: groupErrorPulse 2s infinite;
}

@keyframes groupErrorPulse {
    0% { box-shadow: 0 2px 8px rgba(220, 53, 69, 0.2); }
    50% { box-shadow: 0 4px 16px rgba(220, 53, 69, 0.4); }
    100% { box-shadow: 0 2px 8px rgba(220, 53, 69, 0.2); }
}

.error-indicator {
    animation: errorPulse 1.5s infinite;
    background: rgba(220, 53, 69, 0.1);
    padding: 2px 8px;
    border-radius: 4px;
    border: 1px solid #dc3545;
}

@keyframes errorPulse {
    0% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.7; transform: scale(1.05); }
    100% { opacity: 1; transform: scale(1); }
}

.group-error-active .group-header {
    background: rgba(220, 53, 69, 0.1);
    border-radius: 4px;
    padding: 8px;
    margin: -8px -8px 8px -8px;
}
</style>

<!-- Assets Table -->
<div class="card">
    <div class="card-header">
        <div class="mb-3">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>Assets List
                <?php if (!empty($assets)): ?>
                    <small class="text-muted">(<?php echo count($assets); ?> assets found)</small>
                <?php else: ?>
                    <small class="text-muted">(No assets found)</small>
                <?php endif; ?>
            </h5>
        </div>
        
        <!-- Advanced Search Controls -->
        <div class="row g-3 align-items-end">
            <div class="col-md-12">
                <div class="d-flex justify-content-end">
                    <button class="btn btn-outline-primary" id="advancedSearchBtn" data-bs-toggle="modal" data-bs-target="#advancedSearchModal" title="Advanced Search">
                        <i class="fas fa-filter me-1"></i>Advanced Search
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="assetsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Asset Name</th>
                        <th>Control Number</th>
                        <th>Location</th>
                        <th>Purchase Date</th>
                        <th>Cost</th>
                        <th>Current Value</th>
                        <th>Status</th>
                        <th>Assigned To</th>
                        <th>Department</th>
                        <th>Warranty Status</th>
                        <th style="display: none;">Depreciation Method</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assets as $asset): ?>
                        <tr>
                            <td><?php echo $asset['asset_id']; ?></td>
                            <td><?php echo htmlspecialchars($asset['asset_name']); ?></td>
                            <td><?php echo htmlspecialchars($asset['control_number']); ?></td>
                            <td><?php echo htmlspecialchars($asset['asset_location'] ?: 'Not specified'); ?></td>
                            <td><?php echo formatDate($asset['purchase_date']); ?></td>
                            <td class="currency"><?php echo formatCurrency($asset['cost']); ?></td>
                            <td class="currency text-success fw-bold"><?php echo formatCurrency($asset['current_value'] ?: $asset['cost']); ?></td>
                            <td>
                                <span class="badge status-<?php echo strtolower(str_replace(' ', '-', $asset['status'])); ?>">
                                    <?php echo htmlspecialchars($asset['status']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($asset['assigned_to_name'] ?: 'Unassigned'); ?></td>
                            <td><?php echo htmlspecialchars($asset['department_name'] ?: 'N/A'); ?></td>
                            <td>
                                <?php if ($asset['insurance_end']): ?>
                                    <?php 
                                    $endDate = new DateTime($asset['insurance_end']);
                                    $today = new DateTime();
                                    $isExpired = $endDate < $today;
                                    ?>
                                    <span class="badge bg-<?php echo $isExpired ? 'danger' : 'success'; ?>">
                                        <?php echo $isExpired ? 'Expired' : 'Active'; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">No Warranty</span>
                                <?php endif; ?>
                            </td>
                            <td style="display: none;"><?php echo htmlspecialchars($asset['depreciation_method']); ?></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-info" 
                                            onclick="viewAssetDetails(<?php echo $asset['asset_id']; ?>)" 
                                            title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="editAsset(<?php echo htmlspecialchars(json_encode($asset)); ?>)" 
                                            title="Edit Asset">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            onclick="deleteAsset(<?php echo $asset['asset_id']; ?>, <?php echo htmlspecialchars(json_encode($asset['asset_name']), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($asset['status']), ENT_QUOTES); ?>)"
                                            title="Delete Asset">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Asset Modal -->
<div class="modal fade" id="assetModal" tabindex="-1" aria-labelledby="assetModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assetModalLabel">
                    <i class="fas fa-plus me-2"></i>Add New Asset
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" id="assetForm">
                <input type="hidden" name="action" id="assetAction" value="create">
                <input type="hidden" name="asset_id" id="assetId">
                <div class="modal-body">
                    <!-- Basic Information Section -->
                    <div class="border rounded p-3 mb-4" data-group="basic" style="background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%); border-left: 4px solid #2196f3 !important;">
                        <h6 class="mb-3 text-primary group-header">
                            <i class="fas fa-info-circle me-2"></i>Basic Information
                        </h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="asset_name" class="form-label">Asset Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="asset_name" name="asset_name" required 
                                       placeholder="Enter asset name (e.g., Dell Laptop, Office Chair)">
                                <div class="invalid-feedback">Asset name is required.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="control_number" class="form-label">Control Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="control_number" name="control_number" required 
                                       placeholder="Auto-generated or enter manually">
                                <div class="invalid-feedback">Control number is required and must be unique.</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3" 
                                      placeholder="Detailed description of the asset, including specifications, model, etc."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="asset_location" class="form-label">Asset Location <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="asset_location" name="asset_location" required
                                   placeholder="Physical location of the asset (e.g., Room 101, Building A, Floor 2)">
                            <div class="invalid-feedback">Asset location is required.</div>
                        </div>
                    </div>

                    <!-- Financial Information Section -->
                    <div class="border rounded p-3 mb-4" data-group="financial" style="background: linear-gradient(135deg, #e8f5e8 0%, #f0f8e8 100%); border-left: 4px solid #4caf50 !important;">
                        <h6 class="mb-3 text-success group-header">
                            <i class="fas fa-dollar-sign me-2"></i>Financial Information
                        </h6>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="purchase_date" class="form-label">Purchase Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="purchase_date" name="purchase_date" required>
                                <div class="invalid-feedback">Purchase date is required.</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="cost" class="form-label">Cost <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" class="form-control" id="cost" name="cost" step="0.01" min="0" required 
                                           placeholder="0.00">
                                </div>
                                <div class="invalid-feedback">Cost is required and must be greater than 0.</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="lifetime_years" class="form-label">Lifetime (Years) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="lifetime_years" name="lifetime_years" min="1" max="50" required 
                                       placeholder="5">
                                <div class="invalid-feedback">Lifetime must be between 1 and 50 years.</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="depreciation_method" class="form-label">Depreciation Method</label>
                                <select class="form-select" id="depreciation_method" name="depreciation_method">
                                    <option value="straight-line">Straight Line</option>
                                    <option value="declining-balance">Declining Balance</option>
                                </select>
                                <div class="form-text">Straight line: Equal depreciation each year. Declining balance: Higher depreciation in early years.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="Active">Active</option>
                                    <option value="Depreciated">Depreciated</option>
                                    <option value="Disposed">Disposed</option>
                                    <option value="Under Maintenance">Under Maintenance</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Real-time Depreciation Calculation -->
                        <div class="row mt-3" id="depreciationCalculation" style="display: none;">
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <h6 class="mb-2"><i class="fas fa-calculator me-2"></i>Depreciation Calculation</h6>
                                    <div class="row">
                                        <div class="col-md-3">
                                            <small class="text-muted">Current Value:</small><br>
                                            <strong id="currentValue">₱0.00</strong>
                                        </div>
                                        <div class="col-md-3">
                                            <small class="text-muted">Annual Depreciation:</small><br>
                                            <strong id="annualDepreciation">₱0.00</strong>
                                        </div>
                                        <div class="col-md-3">
                                            <small class="text-muted">Years Depreciated:</small><br>
                                            <strong id="yearsDepreciated">0</strong>
                                        </div>
                                        <div class="col-md-3">
                                            <small class="text-muted">Remaining Value:</small><br>
                                            <strong id="remainingValue" class="text-success">₱0.00</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Assignment Information Section -->
                    <div class="border rounded p-3 mb-4" data-group="assignment" style="background: linear-gradient(135deg, #fff3e0 0%, #fce4ec 100%); border-left: 4px solid #ff9800 !important;">
                        <h6 class="mb-3 text-warning group-header">
                            <i class="fas fa-user me-2"></i>Assignment Information
                            <small class="text-muted">(All fields required if any field has data)</small>
                        </h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="assigned_to_name" class="form-label">Assigned To</label>
                                <input type="text" class="form-control" id="assigned_to_name" name="assigned_to_name" 
                                       placeholder="Full name of the person assigned to this asset">
                                <div class="invalid-feedback">Assigned person name is required when assignment information is provided.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="department_id" class="form-label">Department</label>
                                <select class="form-select" id="department_id" name="department_id">
                                    <option value="">Select Department</option>
                                    <?php foreach ($departments as $dept): ?>
                                        <option value="<?php echo $dept['department_id']; ?>">
                                            <?php echo htmlspecialchars($dept['department_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Department is required when assignment information is provided.</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="assigned_to_email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="assigned_to_email" name="assigned_to_email" 
                                       placeholder="assigned.person@company.com">
                                <div class="invalid-feedback">Valid email is required when assignment information is provided.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="assigned_to_contact" class="form-label">Contact Number</label>
                                <input type="text" class="form-control" id="assigned_to_contact" name="assigned_to_contact" 
                                       placeholder="09XX XXX XXXX" pattern="[0-9+\s()-]+">
                                <div class="invalid-feedback">Contact number is required when assignment information is provided.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Insurance Information Section -->
                    <div class="border rounded p-3 mb-4" data-group="insurance" style="background: linear-gradient(135deg, #f3e5f5 0%, #e1f5fe 100%); border-left: 4px solid #9c27b0 !important;">
                        <h6 class="mb-3 text-info group-header">
                            <i class="fas fa-shield-alt me-2"></i>Insurance Information
                            <small class="text-muted">(All fields required if any field has data)</small>
                        </h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="insurance_type" class="form-label">Insurance Type</label>
                                <input type="text" class="form-control" id="insurance_type" name="insurance_type" 
                                       placeholder="e.g., General Liability, Property Insurance">
                                <div class="invalid-feedback">Insurance type is required when insurance information is provided.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="insurance_provider" class="form-label">Insurance Provider</label>
                                <input type="text" class="form-control" id="insurance_provider" name="insurance_provider" 
                                       placeholder="Name of insurance company">
                                <div class="invalid-feedback">Insurance provider is required when insurance information is provided.</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="insurance_start" class="form-label">Insurance Start Date</label>
                                <input type="date" class="form-control" id="insurance_start" name="insurance_start">
                                <div class="invalid-feedback">Insurance start date is required when insurance information is provided.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="insurance_end" class="form-label">Insurance End Date</label>
                                <input type="date" class="form-control" id="insurance_end" name="insurance_end">
                                <div class="invalid-feedback">Insurance end date is required when insurance information is provided.</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="insurance_details" class="form-label">Insurance Details</label>
                            <textarea class="form-control" id="insurance_details" name="insurance_details" rows="2" 
                                      placeholder="Additional insurance information, policy number, coverage details, etc."></textarea>
                            <div class="invalid-feedback">Insurance details are required when insurance information is provided.</div>
                        </div>
                    </div>

                    <!-- Form Validation Alert -->
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> Fields marked with <span class="text-danger">*</span> are required. 
                        Control numbers are auto-generated but can be customized. Assignment and Insurance sections require all fields if any field has data.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Save Asset
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmModalLabel">Confirm Action</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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

<!-- Hidden form for delete operations -->
<form method="POST" id="deleteForm" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="asset_id" id="deleteAssetId">
</form>

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
                        <!-- Basic Information Section -->
                        <div class="col-12">
                            <h6 class="text-primary mb-3"><i class="fas fa-info-circle me-2"></i>Basic Information</h6>
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
                            <label for="searchLocation" class="form-label">Location</label>
                            <input type="text" class="form-control" id="searchLocation" placeholder="Search by location">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="searchStatus" class="form-label">Status</label>
                            <select class="form-select" id="searchStatus">
                                <option value="">All Status</option>
                                <option value="Active">Active</option>
                                <option value="Depreciated">Depreciated</option>
                                <option value="Disposed">Disposed</option>
                                <option value="Under Maintenance">Under Maintenance</option>
                            </select>
                        </div>
                        
                        <!-- Assignment Information Section -->
                        <div class="col-12 mt-4">
                            <h6 class="text-success mb-3"><i class="fas fa-users me-2"></i>Assignment Information</h6>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="searchDepartment" class="form-label">Department</label>
                            <select class="form-select" id="searchDepartment">
                                <option value="">All Departments</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['department_id']; ?>">
                                        <?php echo htmlspecialchars($dept['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="searchAssignedTo" class="form-label">Assigned To</label>
                            <input type="text" class="form-control" id="searchAssignedTo" placeholder="Search by assigned person">
                        </div>
                        
                        <!-- Financial Information Section -->
                        <div class="col-12 mt-4">
                            <h6 class="text-warning mb-3"><i class="fas fa-dollar-sign me-2"></i>Financial Information</h6>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="searchCostMin" class="form-label">Cost Range (Min)</label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" class="form-control" id="searchCostMin" placeholder="0.00" step="0.01" min="0">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="searchCostMax" class="form-label">Cost Range (Max)</label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" class="form-control" id="searchCostMax" placeholder="999999.99" step="0.01" min="0">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="searchPurchaseDateFrom" class="form-label">Purchase Date (From)</label>
                            <input type="date" class="form-control" id="searchPurchaseDateFrom">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="searchPurchaseDateTo" class="form-label">Purchase Date (To)</label>
                            <input type="date" class="form-control" id="searchPurchaseDateTo">
                        </div>
                        
                        <!-- Insurance Information Section -->
                        <div class="col-12 mt-4">
                            <h6 class="text-info mb-3"><i class="fas fa-shield-alt me-2"></i>Insurance Information</h6>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="searchInsuranceProvider" class="form-label">Insurance Provider</label>
                            <input type="text" class="form-control" id="searchInsuranceProvider" placeholder="Search by insurance provider">
                        </div>
                        
                        <div class="col-md-6">
                            <label for="searchWarrantyStatus" class="form-label">Warranty Status</label>
                            <select class="form-select" id="searchWarrantyStatus">
                                <option value="">All</option>
                                <option value="under_warranty">Under Warranty</option>
                                <option value="expired">Warranty Expired</option>
                            </select>
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
                    <i class="fas fa-search me-1"></i>Search
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Asset Detail View Modal -->
<div class="modal fade" id="assetDetailModal" tabindex="-1" aria-labelledby="assetDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assetDetailModalLabel">
                    <i class="fas fa-info-circle me-2"></i>Asset Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="assetDetailContent">
                <!-- Asset details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="editFromDetailBtn" onclick="editFromDetail()">
                    <i class="fas fa-edit me-1"></i>Edit Asset
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Cache bust: <?php echo time(); ?>
// SIMPLE VALIDATION FUNCTIONS - NO COMPLEX CLASSES
function clearValidationErrors() {
    const invalidFields = document.querySelectorAll('.is-invalid');
    invalidFields.forEach(field => {
        field.classList.remove('is-invalid');
        const errorMsg = field.parentNode.querySelector('.invalid-feedback');
        if (errorMsg) {
            errorMsg.remove();
        }
    });
}

function showFieldError(fieldId, message) {
    const field = document.getElementById(fieldId);
    if (!field) {
        return;
    }
    
    field.classList.add('is-invalid');
    
    // Remove existing error message
    const existingError = field.parentNode.querySelector('.invalid-feedback');
    if (existingError) {
        existingError.remove();
    }
    
    // Add new error message
    const errorDiv = document.createElement('div');
    errorDiv.className = 'invalid-feedback';
    errorDiv.textContent = message;
    field.parentNode.appendChild(errorDiv);
}

// Simple confirmation modal function (if needed elsewhere)
function showConfirmModal(title, message, callback) {
    if (confirm(`${title}\n\n${message}`)) {
        if (callback) callback();
    }
}

// Group required indicator functions
function showGroupRequiredIndicator(groupType) {
    const groupElement = document.querySelector(`[data-group="${groupType}"]`);
    if (groupElement) {
        // Add required indicator to group header with orange warning style
        const header = groupElement.querySelector('.group-header');
        if (header && !header.querySelector('.required-indicator')) {
            const indicator = document.createElement('span');
            indicator.className = 'required-indicator text-warning ms-2';
            indicator.innerHTML = '<i class="fas fa-exclamation-triangle"></i> All fields required';
            indicator.style.fontSize = '0.9em';
            indicator.style.fontWeight = 'bold';
            header.appendChild(indicator);
        }
        
        // Add required asterisks to all fields in the group
        const fields = groupElement.querySelectorAll('input, select, textarea');
        fields.forEach(field => {
            const label = groupElement.querySelector(`label[for="${field.id}"]`);
            if (label && !label.querySelector('.required-asterisk')) {
                const asterisk = document.createElement('span');
                asterisk.className = 'required-asterisk text-danger ms-1';
                asterisk.textContent = '*';
                asterisk.style.fontWeight = 'bold';
                label.appendChild(asterisk);
            }
            
            // Add visual styling to indicate field is now required
            field.classList.add('group-required');
        });
        
        // Add visual styling to the group container
        groupElement.classList.add('group-required-active');
    }
}

function hideGroupRequiredIndicator(groupType) {
    const groupElement = document.querySelector(`[data-group="${groupType}"]`);
    if (groupElement) {
        // Remove required indicator from group header
        const indicator = groupElement.querySelector('.required-indicator');
        if (indicator) {
            indicator.remove();
        }
        
        // Remove required asterisks from fields in the group
        const asterisks = groupElement.querySelectorAll('.required-asterisk');
        asterisks.forEach(asterisk => {
            asterisk.remove();
        });
        
        // Remove visual styling from fields
        const fields = groupElement.querySelectorAll('input, select, textarea');
        fields.forEach(field => {
            field.classList.remove('group-required');
        });
        
        // Remove visual styling from group container
        groupElement.classList.remove('group-required-active');
    }
}

// Function to show error indicators for groups with validation errors
function showGroupErrorIndicators(fieldErrors) {
    // Define which fields belong to which groups
    const groupFields = {
        'basic': ['asset_name', 'control_number', 'asset_location'],
        'financial': ['purchase_date', 'cost', 'lifetime_years'],
        'assignment': ['assigned_to_name', 'assigned_to_email', 'assigned_to_contact', 'department_id'],
        'insurance': ['insurance_type', 'insurance_provider', 'insurance_start', 'insurance_end', 'insurance_details']
    };
    
    // Check each group for errors
    Object.keys(groupFields).forEach(groupType => {
        const groupHasErrors = groupFields[groupType].some(fieldId => fieldErrors[fieldId]);
        
        if (groupHasErrors) {
            const groupElement = document.querySelector(`[data-group="${groupType}"]`);
            if (groupElement) {
                // Add error styling to group
                groupElement.classList.add('group-error-active');
                
                // Add error indicator to group header
                const header = groupElement.querySelector('.group-header');
                if (header && !header.querySelector('.error-indicator')) {
                    const errorIndicator = document.createElement('span');
                    errorIndicator.className = 'error-indicator text-danger ms-2';
                    errorIndicator.innerHTML = '<i class="fas fa-exclamation-circle"></i> Validation errors';
                    errorIndicator.style.fontSize = '0.9em';
                    errorIndicator.style.fontWeight = 'bold';
                    header.appendChild(errorIndicator);
                }
            }
        } else {
            // Remove error styling if no errors
            const groupElement = document.querySelector(`[data-group="${groupType}"]`);
            if (groupElement) {
                groupElement.classList.remove('group-error-active');
                const errorIndicator = groupElement.querySelector('.error-indicator');
                if (errorIndicator) {
                    errorIndicator.remove();
                }
            }
        }
    });
}

// NO SETUP NEEDED - DIRECT VALIDATION ONLY

// COMPLETELY NEW - FRESH START
function addAsset() {
    console.log('addAsset function called');
    
    try {
        // Reset form
        document.getElementById('assetForm').reset();
        document.getElementById('assetModalLabel').innerHTML = '<i class="fas fa-plus me-2"></i>Add New Asset';
        document.getElementById('assetAction').value = 'create';
        document.getElementById('assetId').value = '';
        
        // Clear any existing errors
        clearValidationErrors();
        
        // Set today's date
        document.getElementById('purchase_date').value = new Date().toISOString().split('T')[0];
        
        // Ensure form submission handler is attached
        attachFormSubmissionHandler();
        
        // Show modal
        console.log('Attempting to show modal...');
        const modalElement = document.getElementById('assetModal');
        if (modalElement) {
            // Try Bootstrap 5 modal
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                const modal = new bootstrap.Modal(modalElement);
                modal.show();
                console.log('Bootstrap 5 modal should be showing now');
            } else {
                // Fallback to jQuery modal if Bootstrap 5 is not available
                console.log('Bootstrap 5 not available, trying jQuery modal...');
                $(modalElement).modal('show');
                console.log('jQuery modal should be showing now');
            }
        } else {
            console.error('Modal element not found!');
            alert('Error: Modal element not found. Please refresh the page and try again.');
        }
    } catch (error) {
        console.error('Error in addAsset function:', error);
        alert('Error opening add asset modal. Please check the console for details.');
    }
}

function editAsset(asset) {
    console.log('editAsset function called with asset:', asset);
    
    try {
        // Set modal title and action
        document.getElementById('assetModalLabel').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Asset';
        document.getElementById('assetAction').value = 'update';
        document.getElementById('assetId').value = asset.asset_id;
        
        // Populate form fields
        document.getElementById('asset_name').value = asset.asset_name || '';
        document.getElementById('description').value = asset.description || '';
        document.getElementById('control_number').value = asset.control_number || '';
        document.getElementById('asset_location').value = asset.asset_location || '';
        document.getElementById('purchase_date').value = asset.purchase_date || '';
        document.getElementById('cost').value = asset.cost || '';
        document.getElementById('lifetime_years').value = asset.lifetime_years || '';
        document.getElementById('depreciation_method').value = asset.depreciation_method || 'straight-line';
        document.getElementById('status').value = asset.status || 'Active';
        document.getElementById('assigned_to_name').value = asset.assigned_to_name || '';
        document.getElementById('assigned_to_email').value = asset.assigned_to_email || '';
        document.getElementById('assigned_to_contact').value = asset.assigned_to_contact || '';
        document.getElementById('department_id').value = asset.department_id || '';
        document.getElementById('insurance_type').value = asset.insurance_type || '';
        document.getElementById('insurance_provider').value = asset.insurance_provider || '';
        document.getElementById('insurance_start').value = asset.insurance_start || '';
        document.getElementById('insurance_end').value = asset.insurance_end || '';
        document.getElementById('insurance_details').value = asset.insurance_details || '';
        
        // Clear any existing errors
        clearValidationErrors();
        
        // Ensure form submission handler is attached
        attachFormSubmissionHandler();
        
        // Calculate depreciation
        setTimeout(() => {
            calculateDepreciation();
        }, 100);
        
        // Show modal
        console.log('Attempting to show edit modal...');
        const modalElement = document.getElementById('assetModal');
        if (modalElement) {
            // Try Bootstrap 5 modal
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                const modal = new bootstrap.Modal(modalElement);
                modal.show();
                console.log('Bootstrap 5 edit modal should be showing now');
            } else {
                // Fallback to jQuery modal if Bootstrap 5 is not available
                console.log('Bootstrap 5 not available, trying jQuery modal...');
                $(modalElement).modal('show');
                console.log('jQuery edit modal should be showing now');
            }
        } else {
            console.error('Modal element not found!');
            alert('Error: Modal element not found. Please refresh the page and try again.');
        }
    } catch (error) {
        console.error('Error in editAsset function:', error);
        alert('Error opening edit asset modal. Please check the console for details.');
    }
}

function deleteAsset(assetId, assetName, status) {
    // Clean the status string - remove HTML tags and normalize
    const cleanStatus = status.toString().replace(/<[^>]*>/g, '').trim().toLowerCase();
    
    // Check if asset can be deleted - only Disposed assets can be deleted
    if (cleanStatus === 'disposed') {
        // Show confirmation dialog
        if (confirm(`Are you sure you want to permanently delete the disposed asset "${assetName}"?\n\nThis action cannot be undone.`)) {
            // Set the asset ID and submit the form
            document.getElementById('deleteAssetId').value = assetId;
            document.getElementById('deleteForm').submit();
        }
    } else {
        // Show warning for non-disposed assets
        alert(`Cannot delete asset "${assetName}".\n\nOnly assets with "Disposed" status can be permanently deleted.\n\nCurrent status: ${cleanStatus}\n\nPlease change the status to "Disposed" first.`);
    }
}



// Utility functions for validation (using FormValidator class methods)

function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function isValidContact(contact) {
    const contactRegex = /^[0-9+\-\s()]{7,15}$/;
    return contactRegex.test(contact);
}

// Real-time depreciation calculation
function calculateDepreciation() {
    const cost = parseFloat(document.getElementById('cost').value) || 0;
    const lifetime = parseInt(document.getElementById('lifetime_years').value) || 0;
    const purchaseDate = document.getElementById('purchase_date').value;
    const method = document.getElementById('depreciation_method').value;
    
    if (cost > 0 && lifetime > 0 && purchaseDate) {
        const purchase = new Date(purchaseDate);
        const today = new Date();
        const yearsElapsed = (today - purchase) / (365.25 * 24 * 60 * 60 * 1000);
        
        let currentValue, annualDepreciation, remainingValue;
        
        if (method === 'straight-line') {
            annualDepreciation = cost / lifetime;
            currentValue = Math.max(0, cost - (annualDepreciation * yearsElapsed));
            remainingValue = Math.max(0, cost - (annualDepreciation * lifetime));
        } else { // declining-balance
            const rate = 2 / lifetime; // Double declining balance
            currentValue = cost * Math.pow(1 - rate, yearsElapsed);
            annualDepreciation = cost * rate * Math.pow(1 - rate, yearsElapsed - 1);
            remainingValue = cost * Math.pow(1 - rate, lifetime);
        }
        
        const depreciationHtml = `
            <div class="depreciation-info mt-3 p-3 bg-light rounded">
                <h6 class="text-primary mb-2"><i class="fas fa-calculator me-2"></i>Depreciation Calculation</h6>
                <div class="row">
                    <div class="col-md-6">
                        <small><strong>Current Value:</strong> $${currentValue.toFixed(2)}</small><br>
                        <small><strong>Annual Depreciation:</strong> $${annualDepreciation.toFixed(2)}</small>
                    </div>
                    <div class="col-md-6">
                        <small><strong>Years Depreciated:</strong> ${yearsElapsed.toFixed(1)}</small><br>
                        <small><strong>Remaining Value:</strong> $${remainingValue.toFixed(2)}</small>
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('depreciationCalculation').innerHTML = depreciationHtml;
    } else {
        document.getElementById('depreciationCalculation').innerHTML = '';
    }
}

// COMPLETELY NEW VALIDATION - FRESH START
function validateAssetForm() {
    // Clear previous errors
    clearValidationErrors();
    
    let hasErrors = false;
    
    // 1. BASIC FIELDS - ALWAYS REQUIRED
    const basicFields = ['asset_name', 'control_number', 'asset_location', 'purchase_date', 'cost', 'lifetime_years'];
    const basicNames = ['Asset Name', 'Control Number', 'Asset Location', 'Purchase Date', 'Cost', 'Lifetime Years'];
    
    basicFields.forEach((fieldId, index) => {
        const field = document.getElementById(fieldId);
        if (!field || !field.value.trim()) {
            showFieldError(fieldId, `${basicNames[index]} is required.`);
            hasErrors = true;
        }
    });
    
    // 2. ASSIGNMENT FIELDS - CONDITIONAL REQUIRED
    const assignmentFields = ['assigned_to_name', 'assigned_to_email', 'assigned_to_contact', 'department_id'];
    const assignmentNames = ['Assigned To Name', 'Email', 'Contact Number', 'Department'];
    
    // Check if any assignment field has data
    let hasAssignmentData = false;
    assignmentFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field && field.value.trim()) {
            hasAssignmentData = true;
        }
    });
    
    // If any assignment field has data, all are required
    if (hasAssignmentData) {
        assignmentFields.forEach((fieldId, index) => {
            const field = document.getElementById(fieldId);
            if (!field || !field.value.trim()) {
                showFieldError(fieldId, `${assignmentNames[index]} is required when assignment information is provided.`);
                hasErrors = true;
            }
        });
    }
    
    // 3. INSURANCE FIELDS - CONDITIONAL REQUIRED
    const insuranceFields = ['insurance_type', 'insurance_provider', 'insurance_start', 'insurance_end', 'insurance_details'];
    const insuranceNames = ['Insurance Type', 'Insurance Provider', 'Insurance Start Date', 'Insurance End Date', 'Insurance Details'];
    
    // Check if any insurance field has data
    let hasInsuranceData = false;
    insuranceFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field && field.value.trim()) {
            hasInsuranceData = true;
        }
    });
    
    // If any insurance field has data, all are required
    if (hasInsuranceData) {
        insuranceFields.forEach((fieldId, index) => {
            const field = document.getElementById(fieldId);
            if (!field || !field.value.trim()) {
                showFieldError(fieldId, `${insuranceNames[index]} is required when insurance information is provided.`);
                hasErrors = true;
            }
        });
    }
    
    // 4. DATA TYPE VALIDATIONS
    const cost = document.getElementById('cost');
    if (cost && cost.value && (isNaN(cost.value) || parseFloat(cost.value) <= 0)) {
        showFieldError('cost', 'Cost must be a valid number greater than 0.');
        hasErrors = true;
    }
    
    const lifetime = document.getElementById('lifetime_years');
    if (lifetime && lifetime.value && (isNaN(lifetime.value) || parseInt(lifetime.value) < 1 || parseInt(lifetime.value) > 50)) {
        showFieldError('lifetime_years', 'Lifetime must be between 1 and 50 years.');
        hasErrors = true;
    }
    
    // 5. EMAIL VALIDATION
    const email = document.getElementById('assigned_to_email');
    if (email && email.value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
        showFieldError('assigned_to_email', 'Please enter a valid email address.');
        hasErrors = true;
    }
    
    // 6. PHONE VALIDATION
    const phone = document.getElementById('assigned_to_contact');
    if (phone && phone.value && !/^[0-9+\-\s()]{7,15}$/.test(phone.value)) {
        showFieldError('assigned_to_contact', 'Please enter a valid contact number.');
        hasErrors = true;
    }
    
    if (hasErrors) {
        // Validation failed
    } else {
        // Validation passed
    }
    
    return !hasErrors;
}

// Date range validation
function validateDateRange() {
    const purchaseDate = document.getElementById('purchase_date').value;
    const insuranceStart = document.getElementById('insurance_start').value;
    const insuranceEnd = document.getElementById('insurance_end').value;
    
    if (purchaseDate && insuranceStart) {
        const purchase = new Date(purchaseDate);
        const insurance = new Date(insuranceStart);
        
        if (insurance < purchase) {
            alert('Insurance start date cannot be before purchase date.');
            document.getElementById('insurance_start').focus();
            return false;
        }
    }
    
    if (insuranceStart && insuranceEnd) {
        const start = new Date(insuranceStart);
        const end = new Date(insuranceEnd);
        
        if (end <= start) {
            alert('Insurance end date must be after start date.');
            document.getElementById('insurance_end').focus();
            return false;
        }
    }
    
    if (purchaseDate) {
        const purchase = new Date(purchaseDate);
        const today = new Date();
        
        if (purchase > today) {
            alert('Purchase date cannot be in the future.');
            document.getElementById('purchase_date').focus();
            return false;
        }
    }
    
    return true;
}

// Duplicate detection
function checkForDuplicates() {
    const assetName = document.getElementById('asset_name').value.trim();
    const controlNumber = document.getElementById('control_number').value.trim();
    
    if (assetName.length > 3 && typeof $ !== 'undefined') {
        try {
            // Check for similar asset names
            const table = $('#assetsTable').DataTable();
            let similarFound = false;
            
            table.rows().every(function() {
                const data = this.data();
                const existingName = data[1].toLowerCase();
                const newName = assetName.toLowerCase();
                
                // Check for similarity (simple check)
                if (existingName.includes(newName) || newName.includes(existingName)) {
                    if (data[2] !== controlNumber) { // Different control number
                        similarFound = true;
                        return false; // Break the loop
                    }
                }
            });
            
            if (similarFound) {
                alert('Warning: An asset with a similar name already exists. Please verify this is not a duplicate.');
            }
        } catch (error) {
            console.log('Duplicate check skipped - DataTable not available');
        }
    }
}

// Advanced Search Functions
function applyAdvancedSearch() {
    if (typeof $ === 'undefined') {
        alert('Advanced search requires jQuery. Please refresh the page.');
        return;
    }
    
    const table = $('#assetsTable').DataTable();
    
    // Clear all previous searches and show all rows
    table.search('').columns().search('').draw();
    table.rows().every(function() {
        $(this.node()).show();
    });
    
    // Get search values
    const assetName = $('#searchAssetName').val().trim();
    const controlNumber = $('#searchControlNumber').val().trim();
    const location = $('#searchLocation').val();
    const department = $('#searchDepartment').val();
    const status = $('#searchStatus').val();
    const assignedTo = $('#searchAssignedTo').val().trim();
    const costMin = parseFloat($('#searchCostMin').val()) || null;
    const costMax = parseFloat($('#searchCostMax').val()) || null;
    const purchaseDateFrom = $('#searchPurchaseDateFrom').val();
    const purchaseDateTo = $('#searchPurchaseDateTo').val();
    const insuranceProvider = $('#searchInsuranceProvider').val().trim();
    const warrantyStatus = $('#searchWarrantyStatus').val();
    
    let totalVisibleRows = 0;
    let filteredCount = 0;
    
    // Apply filters to each row
    table.rows().every(function() {
        const row = this.node();
        const data = this.data();
        let showRow = true;
        let filterReasons = [];
        
        // Basic Information filters
        if (assetName) {
            const assetNameMatch = data[1].toLowerCase().includes(assetName.toLowerCase());
            if (!assetNameMatch) {
                showRow = false;
                filterReasons.push('asset name');
            }
        }
        
        if (controlNumber) {
            const controlMatch = data[2].toLowerCase().includes(controlNumber.toLowerCase());
            if (!controlMatch) {
                showRow = false;
                filterReasons.push('control number');
            }
        }
        
        if (location) {
            const locationMatch = data[3].toLowerCase().includes(location.toLowerCase());
            if (!locationMatch) {
                showRow = false;
                filterReasons.push('location');
            }
        }
        
        if (status) {
            // Extract clean status text from HTML badge
            const statusText = data[7].replace(/<[^>]*>/g, '').trim();
            const statusMatch = statusText === status;
            if (!statusMatch) {
                showRow = false;
                filterReasons.push('status');
            }
        }
        
        // Assignment Information filters
        if (assignedTo) {
            const assignedMatch = data[8].toLowerCase().includes(assignedTo.toLowerCase());
            if (!assignedMatch) {
                showRow = false;
                filterReasons.push('assigned to');
            }
        }
        
        if (department) {
            const departmentMatch = data[9] === department;
            if (!departmentMatch) {
                showRow = false;
                filterReasons.push('department');
            }
        }
        
        // Financial Information filters
        if (costMin !== null || costMax !== null) {
            // Extract numeric value from cost string (remove $, ₱, and commas)
            const costStr = data[5].toString().replace(/[$,₱]/g, '');
            const cost = parseFloat(costStr);
            
            if (isNaN(cost)) {
                // Could not parse cost
                showRow = false;
                filterReasons.push('cost (invalid format)');
            } else {
                if (costMin !== null && cost < costMin) {
                    showRow = false;
                    filterReasons.push('cost (below minimum)');
                }
                if (costMax !== null && cost > costMax) {
                    showRow = false;
                    filterReasons.push('cost (above maximum)');
                }
            }
        }
        
        if (purchaseDateFrom || purchaseDateTo) {
            const purchaseDate = new Date(data[4]);
            if (isNaN(purchaseDate.getTime())) {
                // Could not parse purchase date
                showRow = false;
                filterReasons.push('purchase date (invalid format)');
            } else {
                if (purchaseDateFrom) {
                    const fromDate = new Date(purchaseDateFrom);
                    if (purchaseDate < fromDate) {
                        showRow = false;
                        filterReasons.push('purchase date (before from date)');
                    }
                }
                if (purchaseDateTo) {
                    const toDate = new Date(purchaseDateTo);
                    if (purchaseDate > toDate) {
                        showRow = false;
                        filterReasons.push('purchase date (after to date)');
                    }
                }
            }
        }
        
        // Insurance Information filters
        if (insuranceProvider) {
            // Search in all data for insurance provider
            const allData = data.join(' ').toLowerCase();
            const insuranceMatch = allData.includes(insuranceProvider.toLowerCase());
            if (!insuranceMatch) {
                showRow = false;
                filterReasons.push('insurance provider');
            }
        }
        
        if (warrantyStatus) {
            const warrantyText = data[10].toLowerCase();
            let warrantyMatch = false;
            
            if (warrantyStatus === 'under_warranty') {
                warrantyMatch = warrantyText.includes('under warranty');
            } else if (warrantyStatus === 'expired') {
                warrantyMatch = warrantyText.includes('expired');
            }
            
            if (!warrantyMatch) {
                showRow = false;
                filterReasons.push('warranty status');
            }
        }
        
        // Show or hide row based on filters
        if (showRow) {
            $(row).show();
            totalVisibleRows++;
        } else {
            $(row).hide();
            filteredCount++;
        }
    });
    
    // Update the table display
    table.draw(false); // false = don't reset pagination
    
    // Show results message
    if (totalVisibleRows === 0) {
        alert('No assets found matching the search criteria.');
    } else {
        alert(`Found ${totalVisibleRows} asset(s) matching the search criteria.`);
    }
    
    // Close modal
    $('#advancedSearchModal').modal('hide');
}

function clearAdvancedSearch() {
    if (typeof $ === 'undefined') {
        alert('Clear search requires jQuery. Please refresh the page.');
        return;
    }
    
    $('#advancedSearchForm')[0].reset();
    
    // Clear DataTable search and show all rows
    const table = $('#assetsTable').DataTable();
    table.search('').columns().search('').draw();
    
    // Show all rows that might be hidden by advanced search
    table.rows().every(function() {
        $(this.node()).show();
    });
    
    // Redraw the table to show all data
    table.draw();
    
    alert('All filters cleared. Showing all assets.');
}


// Asset Detail View Functions
function viewAssetDetails(assetId) {
    if (typeof $ === 'undefined') {
        alert('Asset details view requires jQuery. Please refresh the page.');
        return;
    }
    
    // Find the asset data from the table
    const table = $('#assetsTable').DataTable();
    const rowData = table.row(function(idx, data, node) {
        return data[0] == assetId; // ID is in first column
    }).data();
    
    if (rowData) {
        currentAssetId = assetId;
        currentAssetData = rowData;
        showAssetDetailModal(rowData);
    }
}

function showAssetDetailModal(assetData) {
    const [id, name, controlNumber, location, purchaseDate, cost, currentValue, statusHtml, assignedTo, department, warrantyStatus, depreciationMethod] = assetData;
    
    // Extract clean status text from HTML
    const statusText = statusHtml.replace(/<[^>]*>/g, '').trim();
    const statusClass = statusText.toLowerCase().replace(/\s+/g, '-');
    
    // Helper functions for financial information
    function getDepreciationMethodText() {
        // Get depreciation method from the asset data
        // The depreciation method is now directly available in the function scope
        let method = depreciationMethod || 'straight-line';
        
        // Convert to display format
        if (method === 'straight-line') {
            return 'Straight-line';
        } else if (method === 'declining-balance') {
            return 'Declining Balance';
        }
        
        return method;
    }
    
    function calculateTotalDepreciation() {
        const originalCost = parseFloat(cost.replace(/[₱$,]/g, ''));
        const currentVal = parseFloat(currentValue.replace(/[₱$,]/g, ''));
        const totalDepreciation = originalCost - currentVal;
        return '₱' + totalDepreciation.toFixed(2);
    }
    
    const detailContent = `
        <div class="row">
            <div class="col-md-4">
                <h6 class="text-primary mb-3"><i class="fas fa-info-circle me-2"></i>Basic Information</h6>
                <table class="table table-sm">
                    <tr><td><strong>Asset ID:</strong></td><td>${id}</td></tr>
                    <tr><td><strong>Asset Name:</strong></td><td>${name}</td></tr>
                    <tr><td><strong>Control Number:</strong></td><td>${controlNumber}</td></tr>
                    <tr><td><strong>Location:</strong></td><td>${location || 'Not specified'}</td></tr>
                    <tr><td><strong>Purchase Date:</strong></td><td>${purchaseDate}</td></tr>
                    <tr><td><strong>Status:</strong></td><td><span class="badge status-${statusClass}">${statusText}</span></td></tr>
                </table>
            </div>
            <div class="col-md-4">
                <h6 class="text-warning mb-3"><i class="fas fa-dollar-sign me-2"></i>Financial Information</h6>
                <table class="table table-sm">
                    <tr><td><strong>Original Cost:</strong></td><td>${cost}</td></tr>
                    <tr><td><strong>Current Value:</strong></td><td><span class="text-success fw-bold">${currentValue}</span></td></tr>
                    <tr><td><strong>Depreciation Method:</strong></td><td>${getDepreciationMethodText()}</td></tr>
                    <tr><td><strong>Total Depreciation:</strong></td><td><span class="text-warning">${calculateTotalDepreciation()}</span></td></tr>
                </table>
            </div>
            <div class="col-md-4">
                <h6 class="text-success mb-3"><i class="fas fa-users me-2"></i>Assignment Information</h6>
                <table class="table table-sm">
                    <tr><td><strong>Assigned To:</strong></td><td>${assignedTo || 'Not assigned'}</td></tr>
                    <tr><td><strong>Department:</strong></td><td>${department || 'Not assigned'}</td></tr>
                    <tr><td><strong>Warranty Status:</strong></td><td>${warrantyStatus}</td></tr>
                </table>
            </div>
        </div>
    `;
    
    $('#assetDetailContent').html(detailContent);
    
    // Show modal
    if (typeof $ !== 'undefined') {
        $('#assetDetailModal').modal('show');
    } else {
        // Fallback for when jQuery is not available
        const modalElement = document.getElementById('assetDetailModal');
        if (modalElement && typeof bootstrap !== 'undefined') {
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
        }
    }
}

function editFromDetail() {
    if (typeof $ !== 'undefined') {
        $('#assetDetailModal').modal('hide');
        // Find the asset in the table and trigger edit
        const table = $('#assetsTable').DataTable();
        table.rows().every(function() {
            if (this.data()[0] == currentAssetId) {
                const row = this.node();
                const editBtn = $(row).find('button[onclick*="editAsset"]')[0];
                if (editBtn) {
                    editBtn.click();
                }
            }
        });
    } else {
        // Fallback for when jQuery is not available
        const modalElement = document.getElementById('assetDetailModal');
        if (modalElement && typeof bootstrap !== 'undefined') {
            const modal = bootstrap.Modal.getInstance(modalElement);
            if (modal) {
                modal.hide();
            }
        }
        
        // Find asset data and edit
        if (currentAssetData) {
            editAsset(currentAssetData);
        }
    }
}

// Global variables for current asset data
let currentAssetData = null;
let currentAssetId = null;

// Test function to verify modal functionality
function testModal() {
    console.log('Testing modal functionality...');
    console.log('jQuery available:', typeof $ !== 'undefined');
    console.log('Bootstrap available:', typeof bootstrap !== 'undefined');
    console.log('Modal element exists:', document.getElementById('assetModal') !== null);
    
    const modalElement = document.getElementById('assetModal');
    if (modalElement) {
        console.log('Modal element found, attempting to show...');
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const modal = new bootstrap.Modal(modalElement);
            modal.show();
            console.log('Bootstrap modal test successful');
        } else {
            console.log('Bootstrap not available, trying jQuery...');
            $(modalElement).modal('show');
            console.log('jQuery modal test successful');
        }
    } else {
        console.error('Modal element not found!');
    }
}

// Function to attach form submission handler (GLOBAL SCOPE)
function attachFormSubmissionHandler() {
    console.log('Attaching form submission handler');
    const formElement = document.getElementById('assetForm');
    
    if (formElement) {
        // Remove any existing handlers first
        if (typeof $ !== 'undefined') {
            $('#assetForm').off('submit');
            $('#assetForm').on('submit', function(e) {
                console.log('Form submission triggered');
                e.preventDefault(); // ALWAYS prevent default submission
                e.stopPropagation(); // Stop event bubbling
                
                // Run validation
                const isValid = validateAssetForm();
                console.log('Form validation result:', isValid);
                
                if (!isValid) {
                    // Validation failed - keep modal open, do NOT submit
                    console.log('Validation failed - keeping modal open');
                    return false;
                }
                
                // Validation passed - submit form
                console.log('Validation passed - submitting form');
                // Remove the event listener to allow normal form submission
                $(this).off('submit');
                // Add a small delay to ensure the modal closes before submission
                setTimeout(() => {
                    this.submit();
                }, 100);
            });
        } else {
            // Fallback for when jQuery is not available
            formElement.addEventListener('submit', function(e) {
                console.log('Form submission triggered (no jQuery)');
                e.preventDefault(); // ALWAYS prevent default submission
                e.stopPropagation(); // Stop event bubbling
                
                // Run validation
                const isValid = validateAssetForm();
                console.log('Form validation result:', isValid);
                
                if (!isValid) {
                    // Validation failed - keep modal open, do NOT submit
                    console.log('Validation failed - keeping modal open');
                    return false;
                }
                
                // Validation passed - submit form
                console.log('Validation passed - submitting form');
                // Remove the event listener to allow normal form submission
                formElement.removeEventListener('submit', arguments.callee);
                // Add a small delay to ensure the modal closes before submission
                setTimeout(() => {
                    this.submit();
                }, 100);
            });
        }
        console.log('Form submission handler attached successfully');
    } else {
        console.error('Form element not found!');
    }
}

// Initialize DataTable and event handlers
function initializeAssetsPage() {
    console.log('Initializing assets page...');
    
    // Check if jQuery is loaded
    if (typeof $ === 'undefined') {
        console.error('jQuery is not loaded');
        return;
    }
    
    // Check if Bootstrap is loaded
    if (typeof bootstrap === 'undefined') {
        console.error('Bootstrap is not loaded');
        return;
    }
    
    console.log('jQuery and Bootstrap are loaded successfully');
    
    // Initialize DataTable with sorting and filtering
    console.log('Initializing DataTable...');
    
    // Add custom currency sorting for DataTables
    $.fn.dataTable.ext.type.detect.unshift(function (data) {
        if (typeof data !== 'string') return null;
        
        // Check if it's a currency format (₱X,XXX.XX)
        if (data.match(/^₱[\d,]+\.?\d*$/)) {
            return 'currency';
        }
        return null;
    });

    $.fn.dataTable.ext.type.order['currency-pre'] = function (data) {
        // Remove currency symbol and commas, convert to number
        return parseFloat(data.replace(/[₱,]/g, '')) || 0;
    };

    // Check if table exists before initializing
    if ($('#assetsTable').length > 0) {
        $('#assetsTable').DataTable({
            "pageLength": 25,
            "order": [[0, "desc"]], // Sort by ID descending by default
            "columnDefs": [
                { "orderable": true, "targets": [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11] }, // All columns sortable except Actions
                { "orderable": false, "targets": [12] }, // Actions column not sortable
                { "visible": false, "targets": [11] }, // Hide depreciation method column
                { 
                    "type": "currency", 
                    "targets": [5, 6] // Cost and Current Value columns
                }
            ],
            "language": {
                "search": "Search:",
                "lengthMenu": "Show _MENU_ entries",
                "info": "Showing _START_ to _END_ of _TOTAL_ entries",
                "infoEmpty": "Showing 0 to 0 of 0 entries",
                "infoFiltered": "(filtered from _MAX_ total entries)",
                "paginate": {
                    "first": "First",
                    "last": "Last",
                    "next": "Next",
                    "previous": "Previous"
                }
            }
        });
        console.log('DataTable initialized successfully');
    } else {
        console.log('Assets table not found, skipping DataTable initialization');
    }
    
    // Real-time depreciation calculation on input changes
    if (typeof $ !== 'undefined') {
        $('#cost, #lifetime_years, #purchase_date, #depreciation_method').on('input change', function() {
            calculateDepreciation();
        });
    } else {
        // Fallback for when jQuery is not available
        document.addEventListener('DOMContentLoaded', function() {
            const fields = ['cost', 'lifetime_years', 'purchase_date', 'depreciation_method'];
            fields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.addEventListener('input', calculateDepreciation);
                    field.addEventListener('change', calculateDepreciation);
                }
            });
        });
    }
    
    // Real-time field validation and error clearing
    const allFormFields = [
        'asset_name', 'control_number', 'asset_location', 'purchase_date', 'cost', 'lifetime_years',
        'assigned_to_name', 'assigned_to_email', 'assigned_to_contact', 'department_id',
        'insurance_type', 'insurance_provider', 'insurance_start', 'insurance_end', 'insurance_details'
    ];
    
    allFormFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.addEventListener('input', function() {
                // Clear validation errors when user starts typing
                if (this.classList.contains('is-invalid')) {
                    this.classList.remove('is-invalid');
                    const errorMsg = this.parentNode.querySelector('.invalid-feedback');
                    if (errorMsg) {
                        errorMsg.remove();
                    }
                }
                
                // Real-time validation for individual fields
                // Simple validation - clear errors when user types
                if (this.classList.contains('is-invalid')) {
                    this.classList.remove('is-invalid');
                    const errorMsg = this.parentNode.querySelector('.invalid-feedback');
                    if (errorMsg) {
                        errorMsg.remove();
                    }
                }
            });
            
            field.addEventListener('blur', function() {
                // Simple validation on blur - just clear any existing errors
                if (this.classList.contains('is-invalid')) {
                    this.classList.remove('is-invalid');
                    const errorMsg = this.parentNode.querySelector('.invalid-feedback');
                    if (errorMsg) {
                        errorMsg.remove();
                    }
                }
            });
        }
    });
    
    // Function removed - using direct validation only
    
    // Duplicate detection on asset name change
    if (typeof $ !== 'undefined') {
        $('#asset_name').on('blur', checkForDuplicates);
        $('#purchase_date, #insurance_start, #insurance_end').on('change', validateDateRange);
    } else {
        // Fallback for when jQuery is not available
        const assetNameField = document.getElementById('asset_name');
        if (assetNameField) {
            assetNameField.addEventListener('blur', checkForDuplicates);
        }
        
        const dateFields = ['purchase_date', 'insurance_start', 'insurance_end'];
        dateFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.addEventListener('change', validateDateRange);
            }
        });
    }
    
    // Real-time validation on input (FROM WORKING TEST PAGE)
    document.querySelectorAll('input, select, textarea').forEach(field => {
        field.addEventListener('input', function() {
            // Clear errors when user starts typing
            if (this.classList.contains('is-invalid')) {
                this.classList.remove('is-invalid');
                const errorMsg = this.parentNode.querySelector('.invalid-feedback');
                if (errorMsg) {
                    errorMsg.remove();
                }
            }
        });
    });
    
    // Attach handler on page load
    attachFormSubmissionHandler();
    
    // Function removed - using direct form submission
    
    // COMPLETELY NEW MODAL RESET - FRESH START
    document.getElementById('assetModal').addEventListener('hidden.bs.modal', function () {
        // Reset form
        document.getElementById('assetForm').reset();
        document.getElementById('assetModalLabel').innerHTML = '<i class="fas fa-plus me-2"></i>Add New Asset';
        document.getElementById('assetAction').value = 'create';
        document.getElementById('assetId').value = '';
        
        // Clear validation errors
        clearValidationErrors();
        
        // Reset today's date
        document.getElementById('purchase_date').value = new Date().toISOString().split('T')[0];
    });
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeAssetsPage);
} else {
    initializeAssetsPage();
}

// Individual field validation
function validateField(field) {
    const value = field.value.trim();
    
    switch (field.id) {
        case 'asset_name':
            if (!value) {
                showFieldError(field, 'Asset name is required.');
                return false;
            }
            break;
            
        case 'control_number':
            if (!value) {
                showFieldError(field, 'Control number is required.');
                return false;
            }
            break;
            
        case 'asset_location':
            if (!value) {
                showFieldError(field, 'Asset location is required.');
                return false;
            }
            break;
            
        case 'cost':
            const cost = parseFloat(value);
            if (!value) {
                showFieldError(field, 'Cost is required.');
                return false;
            } else if (cost <= 0) {
                showFieldError(field, 'Cost must be greater than 0.');
                return false;
            }
            break;
            
        case 'lifetime_years':
            const lifetime = parseInt(value);
            if (!value) {
                showFieldError(field, 'Lifetime is required.');
                return false;
            } else if (lifetime < 1 || lifetime > 50) {
                showFieldError(field, 'Lifetime must be between 1 and 50 years.');
                return false;
            }
            break;
            
        case 'assigned_to_email':
            if (value && !isValidEmail(value)) {
                showFieldError(field, 'Please enter a valid email address.');
                return false;
            }
            break;
            
        case 'assigned_to_contact':
            if (value && !isValidContact(value)) {
                showFieldError(field, 'Please enter a valid contact number.');
                return false;
            }
            break;
    }
    
    return true;
}
</script>

<?php require_once '../includes/footer.php'; ?>
