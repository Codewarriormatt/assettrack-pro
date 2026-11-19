<?php
session_start();
require_once 'config/database.php';
require_once 'includes/header.php';

// Require admin login
requireAdmin();

$page_title = 'Profile - Asset Management System';

$message = '';
$error = '';

try {
    $pdo = getDB();
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_profile') {
            $full_name = trim($_POST['full_name']);
            $username = trim($_POST['username']);
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            $department_id = $_POST['department_id'] ?: null;
            
            // Validate required fields
            if (empty($full_name) || empty($username)) {
                $error = 'Full name and username are required.';
            } else {
                // Check if username already exists (excluding current user)
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
                $stmt->execute([$username, $_SESSION['user_id']]);
                if ($stmt->fetch()) {
                    $error = 'Username already exists. Please choose a different one.';
                } else {
                    // If password change is requested
                    if (!empty($new_password)) {
                        if (empty($current_password)) {
                            $error = 'Current password is required to change password.';
                        } elseif ($new_password !== $confirm_password) {
                            $error = 'New passwords do not match.';
                        } elseif (strlen($new_password) < 6) {
                            $error = 'New password must be at least 6 characters long.';
                        } else {
                            // Verify current password
                            $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
                            $stmt->execute([$_SESSION['user_id']]);
                            $user = $stmt->fetch();
                            
                            if (!password_verify($current_password, $user['password'])) {
                                $error = 'Current password is incorrect.';
                            } else {
                                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                                $stmt = $pdo->prepare("
                                    UPDATE users SET full_name = ?, username = ?, password = ?, department_id = ? 
                                    WHERE user_id = ?
                                ");
                                $success = $stmt->execute([$full_name, $username, $hashed_password, $department_id, $_SESSION['user_id']]);
                            }
                        }
                    } else {
                        // Update without password change
                        $stmt = $pdo->prepare("
                            UPDATE users SET full_name = ?, username = ?, department_id = ? 
                            WHERE user_id = ?
                        ");
                        $success = $stmt->execute([$full_name, $username, $department_id, $_SESSION['user_id']]);
                    }
                    
                    if (isset($success) && $success) {
                        // Update session variables
                        $_SESSION['full_name'] = $full_name;
                        $_SESSION['username'] = $username;
                        
                        logActivity($pdo, $_SESSION['user_id'], 'UPDATE', 'users', "Updated own profile");
                        $message = 'Profile updated successfully!';
                    } elseif (!isset($error)) {
                        $error = 'Failed to update profile. Please try again.';
                    }
                }
            }
        }
    }
    
    // Get current user info
    $stmt = $pdo->prepare("
        SELECT u.*, d.department_name 
        FROM users u 
        LEFT JOIN departments d ON u.department_id = d.department_id 
        WHERE u.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    // Get departments for dropdown
    $stmt = $pdo->query("SELECT department_id, department_name FROM departments ORDER BY department_name");
    $departments = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-user-cog me-2"></i>My Profile</h1>
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

<div class="row">
    <!-- Profile Information -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-user me-2"></i>Profile Information</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="full_name" name="full_name" 
                                   value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo htmlspecialchars($user['username']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="department_id" class="form-label">Department</label>
                            <select class="form-select" id="department_id" name="department_id">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['department_id']; ?>" 
                                            <?php echo $user['department_id'] == $dept['department_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['department_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['role']); ?>" readonly>
                        </div>
                    </div>
                    
                    <hr>
                    <h6 class="mb-3">Change Password (Optional)</h6>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Note:</strong> Leave password fields blank if you don't want to change your password.
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update Profile
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Account Information -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Account Information</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">User ID</label>
                    <p class="form-control-plaintext"><?php echo $user['user_id']; ?></p>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Department</label>
                    <p class="form-control-plaintext"><?php echo htmlspecialchars($user['department_name'] ?: 'Not assigned'); ?></p>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Account Created</label>
                    <p class="form-control-plaintext"><?php echo formatDate($user['created_at']); ?></p>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Last Updated</label>
                    <p class="form-control-plaintext"><?php echo formatDate($user['updated_at']); ?></p>
                </div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Current Session</label>
                    <p class="form-control-plaintext"><?php echo date('M d, Y H:i:s'); ?></p>
                </div>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Security</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Security Notice:</strong> Keep your password secure and change it regularly.
                </div>
                
                <div class="d-grid gap-2">
                    <a href="<?php echo getBaseUrl(); ?>/logout.php" class="btn btn-outline-danger">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Account Deletion Section -->
        <div class="card mt-3 border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="fas fa-user-times me-2"></i>Danger Zone</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Warning:</strong> Deleting your account is permanent and cannot be undone. 
                    All your data will be removed from the system.
                </div>
                
                <p class="text-muted mb-3">
                    <strong>What happens when you delete your account:</strong>
                </p>
                <ul class="text-muted small mb-3">
                    <li>Your user account will be permanently deleted</li>
                    <li>Assets assigned to you will be marked as "Unassigned"</li>
                    <li>Your activity logs will be preserved for audit purposes</li>
                    <li>You will be logged out immediately</li>
                </ul>
                
                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                    <i class="fas fa-user-times me-2"></i>Delete My Account
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Account Modal -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-labelledby="deleteAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteAccountModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>Delete Account Confirmation
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Step 1: Warning and Asset Check -->
                <div id="deleteStep1">
                    <div class="alert alert-danger">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>Final Warning</h6>
                        <p class="mb-0">You are about to permanently delete your account. This action cannot be undone!</p>
                    </div>
                    
                    <div id="assetWarning" class="alert alert-warning" style="display: none;">
                        <h6><i class="fas fa-boxes me-2"></i>Assets Assigned to You</h6>
                        <p class="mb-2">You currently have <strong id="assetCount">0</strong> asset(s) assigned to you.</p>
                        <p class="mb-0">These assets will be marked as "Unassigned" after your account is deleted.</p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">To confirm you understand the consequences, please type:</label>
                        <div class="input-group">
                            <span class="input-group-text">I understand the consequences</span>
                            <input type="text" class="form-control" id="confirmationText" placeholder="Type the text above exactly">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="understandCheckbox">
                            <label class="form-check-label" for="understandCheckbox">
                                I understand that this action is permanent and cannot be undone
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Step 2: Password Verification -->
                <div id="deleteStep2" style="display: none;">
                    <div class="alert alert-info">
                        <h6><i class="fas fa-lock me-2"></i>Password Verification Required</h6>
                        <p class="mb-0">Please enter your current password to confirm your identity.</p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="deletePassword" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="deletePassword" placeholder="Enter your current password">
                    </div>
                </div>
                
                <!-- Step 3: Final Confirmation -->
                <div id="deleteStep3" style="display: none;">
                    <div class="alert alert-danger">
                        <h6><i class="fas fa-skull-crossbones me-2"></i>Last Chance</h6>
                        <p class="mb-0">This is your final opportunity to cancel. Clicking "DELETE MY ACCOUNT" will permanently remove your account.</p>
                    </div>
                    
                    <div class="text-center">
                        <h5 class="text-danger">Are you absolutely sure?</h5>
                        <p class="text-muted">There is no way to recover your account once deleted.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-outline-danger" id="prevStepBtn" style="display: none;">Previous</button>
                <button type="button" class="btn btn-danger" id="nextStepBtn">Next</button>
                <button type="button" class="btn btn-danger" id="deleteAccountBtn" style="display: none;">
                    <i class="fas fa-user-times me-2"></i>DELETE MY ACCOUNT
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
document.querySelector('form').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const currentPassword = document.getElementById('current_password').value;
    
    if (newPassword && !currentPassword) {
        e.preventDefault();
        alert('Current password is required to change password.');
        return;
    }
    
    if (newPassword && newPassword !== confirmPassword) {
        e.preventDefault();
        alert('New passwords do not match.');
        return;
    }
    
    if (newPassword && newPassword.length < 6) {
        e.preventDefault();
        alert('New password must be at least 6 characters long.');
        return;
    }
});

// Account deletion modal functionality
let currentStep = 1;
const totalSteps = 3;

// Load asset count when modal opens
document.getElementById('deleteAccountModal').addEventListener('show.bs.modal', function() {
    // Reset modal state
    currentStep = 1;
    showStep(1);
    
    // Load asset count
    fetch('<?php echo getBaseUrl(); ?>/api/check_user_assets.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const assetCount = data.asset_count;
                const assetWarning = document.getElementById('assetWarning');
                const assetCountSpan = document.getElementById('assetCount');
                
                if (assetCount > 0) {
                    assetCountSpan.textContent = assetCount;
                    assetWarning.style.display = 'block';
                } else {
                    assetWarning.style.display = 'none';
                }
            }
        })
        .catch(error => {
            console.error('Error loading asset count:', error);
        });
});

// Step navigation
function showStep(step) {
    // Hide all steps
    for (let i = 1; i <= totalSteps; i++) {
        document.getElementById(`deleteStep${i}`).style.display = 'none';
    }
    
    // Show current step
    document.getElementById(`deleteStep${step}`).style.display = 'block';
    
    // Update buttons
    const prevBtn = document.getElementById('prevStepBtn');
    const nextBtn = document.getElementById('nextStepBtn');
    const deleteBtn = document.getElementById('deleteAccountBtn');
    
    prevBtn.style.display = step > 1 ? 'inline-block' : 'none';
    nextBtn.style.display = step < totalSteps ? 'inline-block' : 'none';
    deleteBtn.style.display = step === totalSteps ? 'inline-block' : 'none';
    
    // Update next button text
    if (step === totalSteps - 1) {
        nextBtn.textContent = 'Final Step';
    } else {
        nextBtn.textContent = 'Next';
    }
}

// Next step button
document.getElementById('nextStepBtn').addEventListener('click', function() {
    if (validateCurrentStep()) {
        currentStep++;
        showStep(currentStep);
    }
});

// Previous step button
document.getElementById('prevStepBtn').addEventListener('click', function() {
    currentStep--;
    showStep(currentStep);
});

// Validate current step
function validateCurrentStep() {
    switch (currentStep) {
        case 1:
            const confirmationText = document.getElementById('confirmationText').value;
            const understandCheckbox = document.getElementById('understandCheckbox');
            
            if (confirmationText !== 'I understand the consequences') {
                alert('Please type the confirmation text exactly as shown.');
                return false;
            }
            
            if (!understandCheckbox.checked) {
                alert('Please check the confirmation checkbox.');
                return false;
            }
            
            return true;
            
        case 2:
            const password = document.getElementById('deletePassword').value;
            if (!password) {
                alert('Please enter your current password.');
                return false;
            }
            return true;
            
        default:
            return true;
    }
}

// Delete account button
document.getElementById('deleteAccountBtn').addEventListener('click', function() {
    const password = document.getElementById('deletePassword').value;
    
    if (!password) {
        alert('Password verification is required.');
        return;
    }
    
    // Show loading state
    const deleteBtn = document.getElementById('deleteAccountBtn');
    const originalText = deleteBtn.innerHTML;
    deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Deleting Account...';
    deleteBtn.disabled = true;
    
    // Send deletion request
    fetch('<?php echo getBaseUrl(); ?>/api/delete_account.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            password: password
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message and redirect
            alert('Your account has been successfully deleted. You have been logged out and will be redirected to the login page.');
            // Force redirect to login page (session is already destroyed)
            window.location.href = '<?php echo getBaseUrl(); ?>/login.php';
        } else {
            // Show error message
            alert('Error: ' + data.message);
            
            // Reset button
            deleteBtn.innerHTML = originalText;
            deleteBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting your account. Please try again.');
        
        // Reset button
        deleteBtn.innerHTML = originalText;
        deleteBtn.disabled = false;
    });
});

// Reset modal when closed
document.getElementById('deleteAccountModal').addEventListener('hidden.bs.modal', function() {
    // Reset form fields
    document.getElementById('confirmationText').value = '';
    document.getElementById('understandCheckbox').checked = false;
    document.getElementById('deletePassword').value = '';
    
    // Reset to step 1
    currentStep = 1;
    showStep(1);
});
</script>

<?php require_once 'includes/footer.php'; ?>
