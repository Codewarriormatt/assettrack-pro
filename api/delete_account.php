<?php
/**
 * API Endpoint: Delete User Account
 * Handles secure self-deletion with all required protections
 */

session_start();
require_once '../config/database.php';

// Set JSON header
header('Content-Type: application/json');

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Require admin login
requireAdmin();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $pdo = getDB();
    $pdo->beginTransaction();
    
    // Get current user info
    $userId = $_SESSION['user_id'];
    $username = $_SESSION['username'];
    $fullName = $_SESSION['full_name'];
    
    // Get request data
    $input = json_decode(file_get_contents('php://input'), true);
    $password = $input['password'] ?? '';
    
    if (empty($password)) {
        throw new Exception('Password is required for account deletion');
    }
    
    // 1. PASSWORD VERIFICATION
    $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($password, $user['password'])) {
        // Log failed attempt
        logActivity($pdo, $userId, 'DELETE', 'users', "Failed account deletion attempt - incorrect password");
        throw new Exception('Invalid password');
    }
    
    // 2. MINIMUM USER PROTECTION - Ensure at least one admin remains
    $stmt = $pdo->query("SELECT COUNT(*) as admin_count FROM users WHERE role = 'Admin'");
    $result = $stmt->fetch();
    $adminCount = $result['admin_count'];
    
    if ($adminCount <= 1) {
        throw new Exception('Cannot delete account. At least one admin account must remain in the system.');
    }
    
    // 3. DATA INTEGRITY CHECKS - Check for critical dependencies
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as asset_count 
        FROM assets 
        WHERE assigned_to_name = ? AND status IN ('Active', 'Under Maintenance')
    ");
    $stmt->execute([$fullName]);
    $result = $stmt->fetch();
    $assetCount = $result['asset_count'];
    
    // 4. LOG DELETION ATTEMPT
    logActivity($pdo, $userId, 'DELETE', 'users', "Account deletion initiated - User: $username ($fullName)");
    
    // 5. HANDLE ASSET REASSIGNMENT
    if ($assetCount > 0) {
        // Update assets to be unassigned
        $stmt = $pdo->prepare("
            UPDATE assets 
            SET assigned_to_name = 'Unassigned', 
                assigned_to_email = NULL, 
                assigned_to_contact = NULL,
                updated_at = NOW()
            WHERE assigned_to_name = ? AND status IN ('Active', 'Under Maintenance')
        ");
        $stmt->execute([$fullName]);
        
        // Log asset reassignment
        logActivity($pdo, $userId, 'UPDATE', 'assets', "Reassigned $assetCount asset(s) to 'Unassigned' due to account deletion");
    }
    
    // 6. HANDLE MAINTENANCE RECORDS
    // Note: Maintenance table doesn't have assigned_to column, it's linked via asset_id
    // We need to find maintenance records for assets assigned to this user
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as maintenance_count 
        FROM maintenance m
        INNER JOIN assets a ON m.asset_id = a.asset_id
        WHERE a.assigned_to_name = ?
    ");
    $stmt->execute([$fullName]);
    $result = $stmt->fetch();
    $maintenanceCount = $result['maintenance_count'];
    
    if ($maintenanceCount > 0) {
        // Update maintenance records details to note the user deletion
        $stmt = $pdo->prepare("
            UPDATE maintenance m
            INNER JOIN assets a ON m.asset_id = a.asset_id
            SET m.details = CONCAT(m.details, ' [Note: Asset previously assigned to deleted user: ', ?, ']'),
                m.updated_at = NOW()
            WHERE a.assigned_to_name = ?
        ");
        $stmt->execute([$fullName, $fullName]);
        
        // Log maintenance record updates
        logActivity($pdo, $userId, 'UPDATE', 'maintenance', "Updated $maintenanceCount maintenance record(s) due to account deletion");
    }
    
    // 7. PRESERVE ALL AUDIT TRAIL - Convert all user's activity logs to system entries
    // This prevents the CASCADE deletion from destroying the audit trail
    
    // First, get all activity log entries for this user
    $stmt = $pdo->prepare("SELECT * FROM activity_log WHERE user_id = ?");
    $stmt->execute([$userId]);
    $userActivities = $stmt->fetchAll();
    
    // Temporarily disable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Convert each user activity to a system entry with preserved user info
    foreach ($userActivities as $activity) {
        $stmt = $pdo->prepare("
            INSERT INTO activity_log (user_id, action_type, table_name, description, timestamp) 
            VALUES (0, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $activity['action_type'],
            $activity['table_name'],
            "SYSTEM: [DELETED USER: $username] " . $activity['description'],
            $activity['timestamp']
        ]);
    }
    
    // Create the main deletion log entry
    $stmt = $pdo->prepare("
        INSERT INTO activity_log (user_id, action_type, table_name, description, timestamp) 
        VALUES (0, 'DELETE', 'users', ?, NOW())
    ");
    $stmt->execute([
        "SYSTEM: Account deleted - User: $username ($fullName). Assets reassigned: $assetCount, Maintenance records updated: $maintenanceCount"
    ]);
    
    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // 9. LOGOUT USER FIRST - Clear session before deletion
    session_destroy();
    
    // 10. DELETE USER ACCOUNT (after logout)
    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Account deleted successfully',
        'assets_reassigned' => $assetCount,
        'maintenance_updated' => $maintenanceCount
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    // Log error
    if (isset($userId)) {
        logActivity($pdo, $userId, 'DELETE', 'users', "Account deletion failed: " . $e->getMessage());
    }
    
    error_log("Account deletion error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (PDOException $e) {
    // Rollback transaction on database error
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    
    error_log("Database error in delete_account.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
?>
