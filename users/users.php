<?php
/**
 * User Management System - Read Only View
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
require_once '../config/database.php';
require_once '../includes/header.php';

// Require admin login
requireAdmin();

// Additional security check - prevent direct file access
if (!defined('ASSET_MANAGEMENT_SYSTEM')) {
    define('ASSET_MANAGEMENT_SYSTEM', true);
}

// File protection - prevent unauthorized access
if (basename($_SERVER['PHP_SELF']) !== 'users.php' || !isset($_SESSION['user_id'])) {
    http_response_code(403);
    die('Access Denied: Unauthorized access to protected file.');
}

// Enable user management functionality
define('USER_MANAGEMENT_MODE', true);

$page_title = 'User Management - Asset Management System';

$message = '';
$error = '';

try {
    $pdo = getDB();
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create_user') {
            $full_name = trim($_POST['full_name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            $department_id = $_POST['department_id'] ?: null;
            
            // Validation
            if (empty($full_name) || empty($username) || empty($password)) {
                $error = 'Full name, username, and password are required.';
            } elseif ($password !== $confirm_password) {
                $error = 'Passwords do not match.';
            } elseif (strlen($password) < 6) {
                $error = 'Password must be at least 6 characters long.';
            } elseif (strlen($username) < 3) {
                $error = 'Username must be at least 3 characters long.';
            } else {
                // Check if username already exists
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $error = 'Username already exists. Please choose a different one.';
                } else {
                    // Create new user
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        INSERT INTO users (full_name, username, password, role, department_id) 
                        VALUES (?, ?, ?, 'Admin', ?)
                    ");
                    
                    if ($stmt->execute([$full_name, $username, $hashed_password, $department_id])) {
                        $new_user_id = $pdo->lastInsertId();
                        logActivity($pdo, $_SESSION['user_id'], 'CREATE', 'users', "Created user account: $full_name ($username)");
                        $message = "User account created successfully!";
                    } else {
                        $error = 'Failed to create user account. Please try again.';
                    }
                }
            }
        }
    }
    
    // Get users with department info
    $stmt = $pdo->query("
        SELECT u.*, d.department_name 
        FROM users u 
        LEFT JOIN departments d ON u.department_id = d.department_id 
        ORDER BY u.created_at DESC
    ");
    $users = $stmt->fetchAll();
    
    // Get departments for dropdown
    $stmt = $pdo->query("SELECT department_id, department_name FROM departments ORDER BY department_name");
    $departments = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $users = [];
    $departments = [];
    $error = "Database error occurred.";
}

?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="fas fa-users me-2"></i>User Management
                </h1>
                <div class="btn-group">
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createUserModal">
                        <i class="fas fa-user-plus me-1"></i>Create New User
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="location.reload()">
                        <i class="fas fa-sync-alt me-1"></i>Refresh
                    </button>
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

    <div class="card shadow mb-4">
        <div class="card-header py-3 bg-primary text-white">
            <h6 class="m-0 font-weight-bold"><i class="fas fa-list me-2"></i>System Users</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="usersTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Department</th>
                            <th>Created Date</th>
                            <th>Last Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['user_id']); ?></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td>
                                    @<?php echo htmlspecialchars($user['username']); ?>
                                </td>
                                <td>
                                    <span class="badge bg-info text-white">
                                        <i class="fas fa-user-shield me-1"></i><?php echo htmlspecialchars($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['department_name']): ?>
                                        <i class="fas fa-building me-1"></i><?php echo htmlspecialchars($user['department_name']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">No Department</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y H:i', strtotime($user['created_at'])); ?></td>
                                <td><?php echo date('M d, Y H:i', strtotime($user['updated_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Create User Modal -->
<div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="createUserModalLabel">
                    <i class="fas fa-user-plus me-2"></i>Create New User Account
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_user">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> All new users will be created with Admin privileges. 
                        Passwords must be at least 6 characters long.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="full_name" name="full_name" 
                                   placeholder="Enter full name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   placeholder="Enter username (min 3 characters)" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Enter password (min 6 characters)" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                   placeholder="Confirm password" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="department_id" class="form-label">Department</label>
                        <select class="form-select" id="department_id" name="department_id">
                            <option value="">Select Department (Optional)</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept['department_id']; ?>">
                                    <?php echo htmlspecialchars($dept['department_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-shield-alt me-2"></i>
                        <strong>Security Notice:</strong> The new user will have full admin access to the system. 
                        Make sure to provide them with their login credentials securely.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-user-plus me-2"></i>Create User Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<script>
$(document).ready(function() {
    // Initialize DataTable with simple layout (same as activity_log.php)
    $('#usersTable').DataTable({
        responsive: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        dom: 'lfrtip', // Length menu, filtering, responsive, table, info, pagination
        order: [[0, 'desc']], // Sort by User ID descending by default
        language: {
            search: "Search users:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            infoEmpty: "No users found",
            infoFiltered: "(filtered from _MAX_ total entries)",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            },
            emptyTable: "No user data available"
        }
    });
    
    // Form validation for create user modal
    $('#createUserModal form').on('submit', function(e) {
        const fullName = $('#full_name').val().trim();
        const username = $('#username').val().trim();
        const password = $('#password').val();
        const confirmPassword = $('#confirm_password').val();
        
        // Clear previous error states
        $('.form-control').removeClass('is-invalid');
        $('.invalid-feedback').remove();
        
        let hasErrors = false;
        
        // Validate full name
        if (fullName.length < 2) {
            $('#full_name').addClass('is-invalid');
            $('#full_name').after('<div class="invalid-feedback">Full name must be at least 2 characters long.</div>');
            hasErrors = true;
        }
        
        // Validate username
        if (username.length < 3) {
            $('#username').addClass('is-invalid');
            $('#username').after('<div class="invalid-feedback">Username must be at least 3 characters long.</div>');
            hasErrors = true;
        } else if (!/^[a-zA-Z0-9._-]+$/.test(username)) {
            $('#username').addClass('is-invalid');
            $('#username').after('<div class="invalid-feedback">Username can only contain letters, numbers, dots, underscores, and hyphens.</div>');
            hasErrors = true;
        }
        
        // Validate password
        if (password.length < 6) {
            $('#password').addClass('is-invalid');
            $('#password').after('<div class="invalid-feedback">Password must be at least 6 characters long.</div>');
            hasErrors = true;
        }
        
        // Validate password confirmation
        if (password !== confirmPassword) {
            $('#confirm_password').addClass('is-invalid');
            $('#confirm_password').after('<div class="invalid-feedback">Passwords do not match.</div>');
            hasErrors = true;
        }
        
        if (hasErrors) {
            e.preventDefault();
            return false;
        }
    });
    
    // Clear validation errors when modal is closed
    $('#createUserModal').on('hidden.bs.modal', function() {
        $('.form-control').removeClass('is-invalid');
        $('.invalid-feedback').remove();
        this.querySelector('form').reset();
    });
    
    // Real-time username validation
    $('#username').on('blur', function() {
        const username = $(this).val().trim();
        if (username.length >= 3) {
            // Check if username is available (this would require an AJAX call in a real implementation)
            // For now, we'll just validate format
            if (!/^[a-zA-Z0-9._-]+$/.test(username)) {
                $(this).addClass('is-invalid');
                if (!$(this).next('.invalid-feedback').length) {
                    $(this).after('<div class="invalid-feedback">Username can only contain letters, numbers, dots, underscores, and hyphens.</div>');
                }
            } else {
                $(this).removeClass('is-invalid');
                $(this).next('.invalid-feedback').remove();
            }
        }
    });
});
</script>