<?php
/**
 * API Endpoint: Check User Assets
 * Returns the number of assets assigned to the current user
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

try {
    $pdo = getDB();
    
    // Get current user ID
    $userId = $_SESSION['user_id'];
    
    // Count assets assigned to this user
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as asset_count 
        FROM assets 
        WHERE assigned_to_name = (
            SELECT full_name FROM users WHERE user_id = ?
        ) AND status IN ('Active', 'Under Maintenance')
    ");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    
    $assetCount = $result['asset_count'] ?? 0;
    
    // Log the check
    logActivity($pdo, $userId, 'READ', 'assets', "Checked assigned assets count for account deletion");
    
    echo json_encode([
        'success' => true,
        'asset_count' => (int)$assetCount
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in check_user_assets.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
} catch (Exception $e) {
    error_log("Error in check_user_assets.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred'
    ]);
}
?>
