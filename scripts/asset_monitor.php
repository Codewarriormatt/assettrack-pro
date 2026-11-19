<?php
/**
 * Automated Asset Monitoring Script
 * Updates asset current values and statuses based on depreciation
 * Run this script daily via cron job or scheduled task
 */

// Set time limit for long-running script
set_time_limit(300); // 5 minutes

// Include required files (paths based on this file, so it also runs from any folder in CLI)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/monitoring_functions.php';

// From a web browser, only a logged-in admin may run this script (CLI runs need no login)
if (php_sapi_name() !== 'cli') {
    session_start();
    requireAdmin();
}

// Log function for monitoring script
function logMonitorActivity($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message" . PHP_EOL;
    file_put_contents(__DIR__ . '/../logs/asset_monitor.log', $logMessage, FILE_APPEND | LOCK_EX);
    echo $logMessage;
}

// Create logs directory if it doesn't exist
if (!is_dir(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0755, true);
}

logMonitorActivity("=== Asset Monitoring Script Started ===");

try {
    $pdo = getDB();
    
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }
    
    logMonitorActivity("Database connection established");
    
    // Step 1: Update all asset current values
    logMonitorActivity("Step 1: Updating asset current values...");
    
    $stmt = $pdo->query("SELECT asset_id, cost, lifetime_years, purchase_date, depreciation_method FROM assets WHERE status = 'Active'");
    $assets = $stmt->fetchAll();
    
    $updated_values = 0;
    foreach ($assets as $asset) {
        $current_value = calculateCurrentValue(
            $asset['cost'],
            $asset['lifetime_years'],
            $asset['purchase_date'],
            $asset['depreciation_method']
        );
        
        $updateStmt = $pdo->prepare("UPDATE assets SET current_value = ? WHERE asset_id = ?");
        if ($updateStmt->execute([$current_value, $asset['asset_id']])) {
            $updated_values++;
        }
    }
    
    logMonitorActivity("Updated current values for $updated_values assets");
    
    // Step 2: Update asset statuses based on depreciation
    logMonitorActivity("Step 2: Updating asset statuses...");
    
    $status_updates = updateAssetStatuses($pdo);
    logMonitorActivity("Updated status for $status_updates assets");
    
    // Step 3: Check for maintenance due alerts
    logMonitorActivity("Step 3: Checking maintenance due alerts...");
    
    $maintenance_due_7 = getAssetsNearingMaintenance($pdo, 7);
    $maintenance_due_30 = getAssetsNearingMaintenance($pdo, 30);
    
    logMonitorActivity("Found " . count($maintenance_due_7) . " assets due for maintenance in 7 days");
    logMonitorActivity("Found " . count($maintenance_due_30) . " assets due for maintenance in 30 days");
    
    // Step 4: Check for warranty expiration alerts
    logMonitorActivity("Step 4: Checking warranty expiration alerts...");
    
    $warranty_expiring_7 = getAssetsWithinWarranty($pdo, 7);
    $warranty_expiring_30 = getAssetsWithinWarranty($pdo, 30);
    
    logMonitorActivity("Found " . count($warranty_expiring_7) . " warranties expiring in 7 days");
    logMonitorActivity("Found " . count($warranty_expiring_30) . " warranties expiring in 30 days");
    
    // Step 5: Check for highly depreciated assets
    logMonitorActivity("Step 5: Checking highly depreciated assets...");
    
    $depreciated_assets = getDepreciatedAssets($pdo, 0.8, 0.9);
    $replacement_candidates = getReplacementCandidates($pdo, 0.9);
    
    logMonitorActivity("Found " . count($depreciated_assets) . " highly depreciated assets (80%-89%)");
    logMonitorActivity("Found " . count($replacement_candidates) . " replacement candidates (90%+)");
    
    // Step 6: Generate monitoring summary
    logMonitorActivity("Step 6: Generating monitoring summary...");
    
    $summary = getMonitoringSummary($pdo);
    
    logMonitorActivity("Monitoring Summary:");
    logMonitorActivity("- Maintenance due (30 days): " . $summary['maintenance_due_30']);
    logMonitorActivity("- Warranty expiring (30 days): " . $summary['warranty_expiring_30']);
    logMonitorActivity("- Highly depreciated (80%+): " . $summary['highly_depreciated']);
    logMonitorActivity("- Replacement candidates (90%+): " . $summary['replacement_candidates']);
    
    // Step 7: Log critical alerts
    $critical_alerts = [];
    
    if (count($maintenance_due_7) > 0) {
        $critical_alerts[] = "URGENT: " . count($maintenance_due_7) . " assets due for maintenance within 7 days";
    }
    
    if (count($warranty_expiring_7) > 0) {
        $critical_alerts[] = "URGENT: " . count($warranty_expiring_7) . " warranties expiring within 7 days";
    }
    
    if (count($replacement_candidates) > 0) {
        $critical_alerts[] = "NOTICE: " . count($replacement_candidates) . " assets are replacement candidates (90%+ depreciated)";
    }
    
    if (!empty($critical_alerts)) {
        logMonitorActivity("=== CRITICAL ALERTS ===");
        foreach ($critical_alerts as $alert) {
            logMonitorActivity($alert);
        }
        logMonitorActivity("=== END CRITICAL ALERTS ===");
    }
    
    // Step 8: Update monitoring statistics table (if exists)
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS monitoring_stats (
                id INT AUTO_INCREMENT PRIMARY KEY,
                date DATE NOT NULL,
                maintenance_due_30 INT DEFAULT 0,
                warranty_expiring_30 INT DEFAULT 0,
                highly_depreciated INT DEFAULT 0,
                replacement_candidates INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_date (date)
            )
        ");
        
        $stmt = $pdo->prepare("
            INSERT INTO monitoring_stats (date, maintenance_due_30, warranty_expiring_30, highly_depreciated, replacement_candidates)
            VALUES (CURDATE(), ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            maintenance_due_30 = VALUES(maintenance_due_30),
            warranty_expiring_30 = VALUES(warranty_expiring_30),
            highly_depreciated = VALUES(highly_depreciated),
            replacement_candidates = VALUES(replacement_candidates)
        ");
        
        $stmt->execute([
            $summary['maintenance_due_30'],
            $summary['warranty_expiring_30'],
            $summary['highly_depreciated'],
            $summary['replacement_candidates']
        ]);
        
        logMonitorActivity("Updated monitoring statistics table");
        
    } catch (Exception $e) {
        logMonitorActivity("Warning: Could not update monitoring statistics: " . $e->getMessage());
    }
    
    logMonitorActivity("=== Asset Monitoring Script Completed Successfully ===");
    
} catch (Exception $e) {
    logMonitorActivity("ERROR: " . $e->getMessage());
    logMonitorActivity("=== Asset Monitoring Script Failed ===");
    exit(1);
}

// If running from web browser, show results
if (isset($_SERVER['HTTP_HOST'])) {
    echo "<h1>Asset Monitoring Script Results</h1>";
    echo "<p>Script completed successfully. Check the log file for detailed results.</p>";
    echo "<p><a href='../monitoring/dashboard.php'>View Monitoring Dashboard</a></p>";
    echo "<p><a href='../reports/advanced_reports.php?report=monitoring_summary'>View Monitoring Report</a></p>";
}
?>
