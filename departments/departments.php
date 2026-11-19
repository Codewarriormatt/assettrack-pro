<?php
session_start();
require_once '../config/database.php';
require_once '../includes/header.php';

// Require admin login
requireAdmin();

$page_title = 'Department Management - Asset Management System';

$message = '';
$error = '';

try {
    $pdo = getDB();
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create') {
            $department_name = trim($_POST['department_name']);
            $description = trim($_POST['description']);
            $manager = trim($_POST['manager']);
            $manager_email = trim($_POST['manager_email']);
            $manager_contact = trim($_POST['manager_contact']);
            $status = $_POST['status'];
            
            // Validate required fields
            if (empty($department_name)) {
                $error = 'Department name is required.';
            } else {
                // Check if department name already exists
                $stmt = $pdo->prepare("SELECT department_id FROM departments WHERE department_name = ?");
                $stmt->execute([$department_name]);
                if ($stmt->fetch()) {
                    $error = 'Department name already exists. Please use a different name.';
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO departments (department_name, description, manager, manager_email, manager_contact, status) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    
                    if ($stmt->execute([$department_name, $description, $manager, $manager_email, $manager_contact, $status])) {
                        logActivity($pdo, $_SESSION['user_id'], 'CREATE', 'departments', "Created department: $department_name");
                        $message = 'Department created successfully!';
                    } else {
                        $error = 'Failed to create department. Please try again.';
                    }
                }
            }
        }
        
        elseif ($action === 'update') {
            $department_id = intval($_POST['department_id']);
            $department_name = trim($_POST['department_name']);
            $description = trim($_POST['description']);
            $manager = trim($_POST['manager']);
            $manager_email = trim($_POST['manager_email']);
            $manager_contact = trim($_POST['manager_contact']);
            $status = $_POST['status'];
            
            // Check if department name already exists (excluding current department)
            $stmt = $pdo->prepare("SELECT department_id FROM departments WHERE department_name = ? AND department_id != ?");
            $stmt->execute([$department_name, $department_id]);
            if ($stmt->fetch()) {
                $error = 'Department name already exists. Please use a different name.';
            } else {
                $stmt = $pdo->prepare("
                    UPDATE departments SET department_name = ?, description = ?, manager = ?, manager_email = ?, manager_contact = ?, status = ? 
                    WHERE department_id = ?
                ");
                
                if ($stmt->execute([$department_name, $description, $manager, $manager_email, $manager_contact, $status, $department_id])) {
                    logActivity($pdo, $_SESSION['user_id'], 'UPDATE', 'departments', "Updated department: $department_name");
                    $message = 'Department updated successfully!';
                } else {
                    $error = 'Failed to update department. Please try again.';
                }
            }
        }
        
        elseif ($action === 'delete') {
            $department_id = intval($_POST['department_id']);
            
            // Check if department is being used by assets or users
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM assets WHERE department_id = ?");
            $stmt->execute([$department_id]);
            $assetCount = $stmt->fetch()['count'];
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE department_id = ?");
            $stmt->execute([$department_id]);
            $userCount = $stmt->fetch()['count'];
            
            if ($assetCount > 0 || $userCount > 0) {
                $error = "Cannot delete department. It is currently assigned to $assetCount asset(s) and $userCount user(s).";
            } else {
                // Get department info for logging
                $stmt = $pdo->prepare("SELECT department_name FROM departments WHERE department_id = ?");
                $stmt->execute([$department_id]);
                $department = $stmt->fetch();
                
                if ($department) {
                    $stmt = $pdo->prepare("DELETE FROM departments WHERE department_id = ?");
                    if ($stmt->execute([$department_id])) {
                        logActivity($pdo, $_SESSION['user_id'], 'DELETE', 'departments', "Deleted department: {$department['department_name']}");
                        $message = 'Department deleted successfully!';
                    } else {
                        $error = 'Failed to delete department. Please try again.';
                    }
                } else {
                    $error = 'Department not found.';
                }
            }
        }
    }
    
    // Get departments
    $stmt = $pdo->query("
        SELECT d.*, 
               COUNT(DISTINCT a.asset_id) as asset_count,
               COUNT(DISTINCT u.user_id) as user_count
        FROM departments d 
        LEFT JOIN assets a ON d.department_id = a.department_id 
        LEFT JOIN users u ON d.department_id = u.department_id 
        GROUP BY d.department_id 
        ORDER BY d.department_name
    ");
    $departments = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-building me-2"></i>Department Management</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#departmentModal" onclick="openDepartmentModal()">
                <i class="fas fa-plus me-1"></i>Add New Department
            </button>
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

<!-- Departments Table -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Departments List</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover" id="departmentsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Department Name</th>
                        <th>Description</th>
                        <th>Manager</th>
                        <th>Assets Count</th>
                        <th>Users Count</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($departments as $department): ?>
                        <tr>
                            <td><?php echo $department['department_id']; ?></td>
                            <td><?php echo htmlspecialchars($department['department_name']); ?></td>
                            <td><?php echo htmlspecialchars($department['description'] ?: 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($department['manager'] ?: 'N/A'); ?></td>
                            <td>
                                <span class="badge bg-info"><?php echo $department['asset_count']; ?></span>
                            </td>
                            <td>
                                <span class="badge bg-primary"><?php echo $department['user_count']; ?></span>
                            </td>
                            <td><?php echo formatDate($department['created_at']); ?></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="editDepartment(<?php echo htmlspecialchars(json_encode($department)); ?>)"
                                            data-bs-toggle="tooltip" title="Edit Department">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            onclick="deleteDepartment(<?php echo $department['department_id']; ?>, <?php echo htmlspecialchars(json_encode($department['department_name']), ENT_QUOTES); ?>,<?php echo $department['asset_count']; ?>, <?php echo $department['user_count']; ?>)"
                                            data-bs-toggle="tooltip" title="Delete Department">
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

<!-- Department Modal -->
<div class="modal fade" id="departmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="departmentModalTitle">Add New Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="departmentForm">
                <div class="modal-body">
                    <input type="hidden" name="action" id="departmentAction" value="create">
                    <input type="hidden" name="department_id" id="departmentId">
                    
                    <div class="mb-3">
                        <label for="department_name" class="form-label">Department Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="department_name" name="department_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Brief description of the department's role and responsibilities..."></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="manager" class="form-label">Manager Name</label>
                            <input type="text" class="form-control" id="manager" name="manager" placeholder="Full name of the department manager">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="manager_email" class="form-label">Manager Email</label>
                            <input type="email" class="form-control" id="manager_email" name="manager_email" placeholder="manager@company.com">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="manager_contact" class="form-label">Manager Contact</label>
                            <input type="text" class="form-control" id="manager_contact" name="manager_contact" placeholder="09XX XXX XXXX">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Department</button>
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

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#departmentsTable').DataTable(dataTableConfig);
});

function openDepartmentModal() {
    document.getElementById('departmentModalTitle').textContent = 'Add New Department';
    document.getElementById('departmentAction').value = 'create';
    document.getElementById('departmentForm').reset();
    document.getElementById('departmentId').value = '';
}

function editDepartment(department) {
    document.getElementById('departmentModalTitle').textContent = 'Edit Department';
    document.getElementById('departmentAction').value = 'update';
    document.getElementById('departmentId').value = department.department_id;
    document.getElementById('department_name').value = department.department_name;
    document.getElementById('description').value = department.description || '';
    document.getElementById('manager').value = department.manager || '';
    document.getElementById('manager_email').value = department.manager_email || '';
    document.getElementById('manager_contact').value = department.manager_contact || '';
    document.getElementById('status').value = department.status || 'Active';
    
    new bootstrap.Modal(document.getElementById('departmentModal')).show();
}

function deleteDepartment(departmentId, departmentName, assetCount, userCount) {
    let message = `Are you sure you want to delete the department "${departmentName}"?`;
    
    if (assetCount > 0 || userCount > 0) {
        message += `\n\nThis department is currently assigned to ${assetCount} asset(s) and ${userCount} user(s). Deleting it will remove these assignments.`;
    }
    
    message += '\n\nThis action cannot be undone.';
    
    showConfirmModal(
        'Delete Department',
        message,
        function() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="department_id" value="${departmentId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    );
}
</script>

<?php require_once '../includes/footer.php'; ?>
