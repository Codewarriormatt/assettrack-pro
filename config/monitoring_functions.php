<?php
/**
 * Asset Monitoring Functions
 * Core functions for monitoring asset status, depreciation, maintenance, and warranty
 */

// Function to get assets nearing maintenance
function getAssetsNearingMaintenance($pdo, $days = 30) {
    $stmt = $pdo->prepare("
        SELECT a.asset_id, a.asset_name, a.control_number, a.assigned_to_name, 
               d.department_name, m.next_due_date, m.details, m.status,
               DATEDIFF(m.next_due_date, CURDATE()) as days_until_due
        FROM maintenance m
        JOIN assets a ON m.asset_id = a.asset_id
        LEFT JOIN departments d ON a.department_id = d.department_id
        WHERE m.status = 'Pending' 
        AND m.next_due_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
        ORDER BY m.next_due_date ASC
    ");
    $stmt->execute([$days]);
    return $stmt->fetchAll();
}

// Function to get assets within warranty (expiring soon)
function getAssetsWithinWarranty($pdo, $days = 30) {
    $stmt = $pdo->prepare("
        SELECT a.asset_id, a.asset_name, a.control_number, a.assigned_to_name,
               d.department_name, a.insurance_end, a.insurance_provider,
               DATEDIFF(a.insurance_end, CURDATE()) as days_until_expires
        FROM assets a
        LEFT JOIN departments d ON a.department_id = d.department_id
        WHERE a.insurance_end IS NOT NULL 
        AND a.insurance_end >= CURDATE()
        AND a.insurance_end <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
        ORDER BY a.insurance_end ASC
    ");
    $stmt->execute([$days]);
    return $stmt->fetchAll();
}

// Function to get depreciated assets (excludes replacement candidates to avoid duplication)
function getDepreciatedAssets($pdo, $threshold = 0.8, $max_threshold = 0.9) {
    $stmt = $pdo->prepare("
        SELECT a.asset_id, a.asset_name, a.control_number, a.cost, a.current_value,
               a.lifetime_years, a.depreciation_method, a.assigned_to_name,
               d.department_name, a.purchase_date,
               ROUND(((a.cost - a.current_value) / a.cost) * 100, 2) as depreciation_percentage,
               DATEDIFF(CURDATE(), a.purchase_date) as days_since_purchase
        FROM assets a
        LEFT JOIN departments d ON a.department_id = d.department_id
        WHERE a.status = 'Active'
        AND ((a.cost - a.current_value) / a.cost) >= ?
        AND ((a.cost - a.current_value) / a.cost) < ?
        ORDER BY depreciation_percentage DESC
    ");
    $stmt->execute([$threshold, $max_threshold]);
    return $stmt->fetchAll();
}

// Function to get replacement candidates (highly depreciated assets + fully depreciated assets)
function getReplacementCandidates($pdo, $threshold = 0.9) {
    $stmt = $pdo->prepare("
        SELECT a.asset_id, a.asset_name, a.control_number, a.cost, a.current_value,
               a.lifetime_years, a.assigned_to_name, d.department_name, a.status,
               ROUND(((a.cost - a.current_value) / a.cost) * 100, 2) as depreciation_percentage,
               DATEDIFF(CURDATE(), a.purchase_date) as days_since_purchase,
               (a.cost - a.current_value) as total_depreciation
        FROM assets a
        LEFT JOIN departments d ON a.department_id = d.department_id
        WHERE (
            (a.status = 'Active' AND ((a.cost - a.current_value) / a.cost) >= ?)
            OR a.status = 'Depreciated'
        )
        ORDER BY depreciation_percentage DESC, a.status DESC
    ");
    $stmt->execute([$threshold]);
    return $stmt->fetchAll();
}

// Function to get fully depreciated assets (status = 'Depreciated')
function getFullyDepreciatedAssets($pdo) {
    $stmt = $pdo->prepare("
        SELECT a.asset_id, a.asset_name, a.control_number, a.cost, a.current_value,
               a.lifetime_years, a.assigned_to_name, d.department_name, a.purchase_date,
               ROUND(((a.cost - a.current_value) / a.cost) * 100, 2) as depreciation_percentage,
               DATEDIFF(CURDATE(), a.purchase_date) as days_since_purchase,
               (a.cost - a.current_value) as total_depreciation
        FROM assets a
        LEFT JOIN departments d ON a.department_id = d.department_id
        WHERE a.status = 'Depreciated'
        ORDER BY a.purchase_date ASC
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

// Function to calculate depreciation percentage for a specific asset
function calculateDepreciationPercentage($pdo, $asset_id) {
    $stmt = $pdo->prepare("
        SELECT cost, current_value 
        FROM assets 
        WHERE asset_id = ?
    ");
    $stmt->execute([$asset_id]);
    $asset = $stmt->fetch();
    
    if ($asset && $asset['cost'] > 0) {
        return round((($asset['cost'] - $asset['current_value']) / $asset['cost']) * 100, 2);
    }
    return 0;
}

// Function to update asset statuses based on depreciation
function updateAssetStatuses($pdo) {
    $updated_count = 0;
    
    // Get all active assets
    $stmt = $pdo->query("
        SELECT asset_id, cost, current_value, status 
        FROM assets 
        WHERE status = 'Active'
    ");
    $assets = $stmt->fetchAll();
    
    foreach ($assets as $asset) {
        $depreciation_percentage = 0;
        if ($asset['cost'] > 0) {
            $depreciation_percentage = (($asset['cost'] - $asset['current_value']) / $asset['cost']) * 100;
        }
        
        // Update status to Depreciated if 100% depreciated
        if ($depreciation_percentage >= 100 && $asset['status'] === 'Active') {
            $updateStmt = $pdo->prepare("UPDATE assets SET status = 'Depreciated' WHERE asset_id = ?");
            $updateStmt->execute([$asset['asset_id']]);
            $updated_count++;
        }
    }
    
    return $updated_count;
}

// Function to get monitoring summary statistics
function getMonitoringSummary($pdo) {
    $summary = [];
    
    // Assets nearing maintenance (30 days)
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM maintenance 
        WHERE status = 'Pending' 
        AND next_due_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ");
    $summary['maintenance_due_30'] = $stmt->fetch()['count'];
    
    // Assets with warranty expiring (30 days)
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM assets 
        WHERE insurance_end IS NOT NULL 
        AND insurance_end >= CURDATE()
        AND insurance_end <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ");
    $summary['warranty_expiring_30'] = $stmt->fetch()['count'];
    
    // Highly depreciated assets (80%+)
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM assets 
        WHERE status = 'Active'
        AND ((cost - current_value) / cost) >= 0.8
    ");
    $summary['highly_depreciated'] = $stmt->fetch()['count'];
    
    // Replacement candidates (90%+ Active assets + Depreciated assets)
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM assets 
        WHERE (
            (status = 'Active' AND ((cost - current_value) / cost) >= 0.9)
            OR status = 'Depreciated'
        )
    ");
    $summary['replacement_candidates'] = $stmt->fetch()['count'];
    
    return $summary;
}

// Function to get all assets with monitoring data
function getAllAssetsWithMonitoring($pdo) {
    $stmt = $pdo->query("
        SELECT a.asset_id, a.asset_name, a.control_number, a.cost, a.current_value,
               a.lifetime_years, a.depreciation_method, a.status, a.purchase_date,
               a.assigned_to_name, d.department_name, a.insurance_end,
               ROUND(((a.cost - a.current_value) / a.cost) * 100, 2) as depreciation_percentage,
               DATEDIFF(CURDATE(), a.purchase_date) as days_since_purchase,
               CASE 
                   WHEN a.insurance_end IS NULL THEN 'No Warranty'
                   WHEN a.insurance_end >= CURDATE() THEN 'Under Warranty'
                   ELSE 'Warranty Expired'
               END as warranty_status,
               (SELECT COUNT(*) FROM maintenance m WHERE m.asset_id = a.asset_id AND m.status = 'Pending' AND m.next_due_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)) as maintenance_due_count
        FROM assets a
        LEFT JOIN departments d ON a.department_id = d.department_id
        ORDER BY a.asset_name
    ");
    return $stmt->fetchAll();
}
?>
