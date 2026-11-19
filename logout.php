<?php
session_start();
require_once 'config/database.php';

if (isLoggedIn()) {
    try {
        $pdo = getDB();
        // Log logout activity
        logActivity($pdo, $_SESSION['user_id'], 'LOGOUT', 'users', 'User logged out');
        
        // Create a system backup of the logout entry to preserve it even if user deletes account
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $stmt = $pdo->prepare("
            INSERT INTO activity_log (user_id, action_type, table_name, description, timestamp) 
            VALUES (0, 'LOGOUT', 'users', ?, NOW())
        ");
        $stmt->execute([
            "SYSTEM: [USER: {$_SESSION['username']} ({$_SESSION['full_name']})] User logged out"
        ]);
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    } catch (PDOException $e) {
        error_log("Logout logging failed: " . $e->getMessage());
    }
}

// Destroy session
session_destroy();

// Redirect to login page
header('Location: ' . getBaseUrl() . '/login.php');
exit();
?>
