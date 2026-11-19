<?php
session_start();
require_once '../config/database.php';
require_once '../config/monitoring_functions.php';
require_once '../includes/header.php';

// Require admin login
requireAdmin();

$page_title = 'Advanced Asset Reports - Asset Management System';

$report_data = [];
$report_type = $_GET['report'] ?? '';
$report_title = '';

try {
    $pdo = getDB();
    
    switch ($report_type) {
        case 'monitoring_summary':
            $summary = getMonitoringSummary($pdo);
            $report_data = [
                'maintenance_due_30' => getAssetsNearingMaintenance($pdo, 30),
                'warranty_expiring_30' => getAssetsWithinWarranty($pdo, 30),
                'depreciated_assets' => getDepreciatedAssets($pdo, 0.8, 0.9),
                'replacement_candidates' => getReplacementCandidates($pdo, 0.9),
                'summary' => $summary
            ];
            $report_title = 'Asset Monitoring Summary Report';
            break;
            
        case 'maintenance_due':
            $report_data = getAssetsNearingMaintenance($pdo, 90); // 90 days
            $report_title = 'Assets Nearing Maintenance Report';
            break;
            
        case 'depreciated_assets':
            $report_data = getDepreciatedAssets($pdo, 0.8, 0.9);
            $report_title = 'Highly Depreciated Assets Report (80%-89%)';
            break;
            
        case 'replacement_candidates':
            $report_data = getReplacementCandidates($pdo, 0.9);
            $report_title = 'Replacement Candidates Report';
            break;
            
        case 'warranty_status':
            $stmt = $pdo->query("
                SELECT a.asset_id, a.asset_name, a.control_number, a.assigned_to_name,
                       d.department_name, a.insurance_end, a.insurance_provider,
                       CASE 
                           WHEN a.insurance_end IS NULL THEN 'No Warranty'
                           WHEN a.insurance_end >= CURDATE() THEN 'Under Warranty'
                           ELSE 'Warranty Expired'
                       END as warranty_status,
                       DATEDIFF(a.insurance_end, CURDATE()) as days_until_expires
                FROM assets a
                LEFT JOIN departments d ON a.department_id = d.department_id
                WHERE a.insurance_end IS NOT NULL
                ORDER BY a.insurance_end ASC
            ");
            $report_data = $stmt->fetchAll();
            $report_title = 'Warranty Status Report';
            break;
            
        case 'all_assets_monitoring':
            $report_data = getAllAssetsWithMonitoring($pdo);
            $report_title = 'All Assets Monitoring Report';
            break;
            
        default:
            $report_title = 'Select a Report';
            break;
    }
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="fas fa-file-alt me-2"></i>Advanced Asset Reports
                </h1>
                <div class="btn-group">
                    <a href="../monitoring/dashboard.php" class="btn btn-outline-primary">
                        <i class="fas fa-chart-line me-1"></i>Monitoring Dashboard
                    </a>
                    <?php if (!empty($report_data) && $report_type !== 'monitoring_summary'): ?>
                        <div class="dropdown">
                            <button class="btn btn-success dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-download me-1"></i>Export Data
                            </button>
                            <ul class="dropdown-menu">
                                <li><button class="dropdown-item" onclick="downloadCSV()">
                                    <i class="fas fa-file-csv me-2"></i>Download CSV
                                </button></li>
                                <li><button class="dropdown-item" onclick="downloadExcel()">
                                    <i class="fas fa-file-excel me-2"></i>Download Excel
                                </button></li>
                                <li><button class="dropdown-item" onclick="printTable()">
                                    <i class="fas fa-print me-2"></i>Print Report
                                </button></li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Report Selection -->
    <?php if (empty($report_type)): ?>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Select Report Type</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <a href="?report=monitoring_summary" class="btn btn-primary w-100 h-100 d-flex flex-column justify-content-center">
                                    <i class="fas fa-chart-line fa-2x mb-2"></i>
                                    <strong>Monitoring Summary</strong>
                                    <small>Complete monitoring overview</small>
                                </a>
                            </div>
                            <div class="col-md-4 mb-3">
                                <a href="?report=maintenance_due" class="btn btn-warning w-100 h-100 d-flex flex-column justify-content-center">
                                    <i class="fas fa-tools fa-2x mb-2"></i>
                                    <strong>Maintenance Due</strong>
                                    <small>Assets needing maintenance</small>
                                </a>
                            </div>
                            <div class="col-md-4 mb-3">
                                <a href="?report=depreciated_assets" class="btn btn-danger w-100 h-100 d-flex flex-column justify-content-center">
                                    <i class="fas fa-chart-line-down fa-2x mb-2"></i>
                                    <strong>Depreciated Assets</strong>
                                    <small>80%+ depreciated assets</small>
                                </a>
                            </div>
                            <div class="col-md-4 mb-3">
                                <a href="?report=replacement_candidates" class="btn btn-dark w-100 h-100 d-flex flex-column justify-content-center">
                                    <i class="fas fa-recycle fa-2x mb-2"></i>
                                    <strong>Replacement Candidates</strong>
                                    <small>90%+ & fully depreciated assets</small>
                                </a>
                            </div>
                            <div class="col-md-4 mb-3">
                                <a href="?report=warranty_status" class="btn btn-info w-100 h-100 d-flex flex-column justify-content-center">
                                    <i class="fas fa-shield-alt fa-2x mb-2"></i>
                                    <strong>Warranty Status</strong>
                                    <small>All warranty information</small>
                                </a>
                            </div>
                            <div class="col-md-4 mb-3">
                                <a href="?report=all_assets_monitoring" class="btn btn-secondary w-100 h-100 d-flex flex-column justify-content-center">
                                    <i class="fas fa-list fa-2x mb-2"></i>
                                    <strong>All Assets</strong>
                                    <small>Complete asset monitoring data</small>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Report Content -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-file-alt me-2"></i><?php echo $report_title; ?>
                    <small class="text-muted ms-2">Generated on <?php echo date('F d, Y \a\t g:i A'); ?></small>
                </h5>
            </div>
            <div class="card-body">
                <?php if ($report_type === 'monitoring_summary'): ?>
                    <!-- Monitoring Summary Report -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card border-warning">
                                <div class="card-body text-center">
                                    <h3 class="text-warning"><?php echo $report_data['summary']['maintenance_due_30']; ?></h3>
                                    <p class="mb-0">Maintenance Due (30 days)</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-info">
                                <div class="card-body text-center">
                                    <h3 class="text-info"><?php echo $report_data['summary']['warranty_expiring_30']; ?></h3>
                                    <p class="mb-0">Warranty Expiring (30 days)</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-danger">
                                <div class="card-body text-center">
                                    <h3 class="text-danger"><?php echo $report_data['summary']['highly_depreciated']; ?></h3>
                                    <p class="mb-0">Highly Depreciated (80%+)</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-dark">
                                <div class="card-body text-center">
                                    <h3 class="text-dark"><?php echo $report_data['summary']['replacement_candidates']; ?></h3>
                                    <p class="mb-0">Replacement Candidates (90%+)</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Detailed sections for monitoring summary -->
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Assets Nearing Maintenance (30 days)</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Asset</th>
                                            <th>Due Date</th>
                                            <th>Days</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($report_data['maintenance_due_30'] as $asset): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($asset['asset_name']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($asset['next_due_date'])); ?></td>
                                                <td><?php echo $asset['days_until_due']; ?> days</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6>Warranty Expiring Soon (30 days)</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Asset</th>
                                            <th>Expires</th>
                                            <th>Days</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($report_data['warranty_expiring_30'] as $asset): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($asset['asset_name']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($asset['insurance_end'])); ?></td>
                                                <td><?php echo $asset['days_until_expires']; ?> days</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <!-- Standard Report Tables -->
                    <?php if (!empty($report_data)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead class="table-dark">
                                    <?php if ($report_type === 'maintenance_due'): ?>
                                        <tr>
                                            <th>Asset Name</th>
                                            <th>Control Number</th>
                                            <th>Assigned To</th>
                                            <th>Department</th>
                                            <th>Next Due Date</th>
                                            <th>Days Until Due</th>
                                            <th>Details</th>
                                        </tr>
                                    <?php elseif ($report_type === 'depreciated_assets' || $report_type === 'replacement_candidates'): ?>
                                        <tr>
                                            <th>Asset Name</th>
                                            <th>Control Number</th>
                                            <th>Department</th>
                                            <th>Original Cost</th>
                                            <th>Current Value</th>
                                            <th>Depreciation %</th>
                                            <th>Days Since Purchase</th>
                                        </tr>
                                    <?php elseif ($report_type === 'warranty_status'): ?>
                                        <tr>
                                            <th>Asset Name</th>
                                            <th>Control Number</th>
                                            <th>Department</th>
                                            <th>Warranty Status</th>
                                            <th>Expires</th>
                                            <th>Provider</th>
                                        </tr>
                                    <?php elseif ($report_type === 'all_assets_monitoring'): ?>
                                        <tr>
                                            <th>Asset Name</th>
                                            <th>Control Number</th>
                                            <th>Department</th>
                                            <th>Status</th>
                                            <th>Depreciation %</th>
                                            <th>Warranty Status</th>
                                            <th>Maintenance Due</th>
                                        </tr>
                                    <?php endif; ?>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data as $row): ?>
                                        <tr>
                                            <?php if ($report_type === 'maintenance_due'): ?>
                                                <td><?php echo htmlspecialchars($row['asset_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['control_number']); ?></td>
                                                <td><?php echo htmlspecialchars($row['assigned_to_name'] ?: 'Unassigned'); ?></td>
                                                <td><?php echo htmlspecialchars($row['department_name'] ?: 'N/A'); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($row['next_due_date'])); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $row['days_until_due'] <= 7 ? 'danger' : ($row['days_until_due'] <= 14 ? 'warning' : 'info'); ?>">
                                                        <?php echo $row['days_until_due']; ?> days
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($row['details']); ?></td>
                                            <?php elseif ($report_type === 'depreciated_assets' || $report_type === 'replacement_candidates'): ?>
                                                <td><?php echo htmlspecialchars($row['asset_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['control_number']); ?></td>
                                                <td><?php echo htmlspecialchars($row['department_name'] ?: 'N/A'); ?></td>
                                                <td>₱<?php echo number_format($row['cost'], 2); ?></td>
                                                <td>₱<?php echo number_format($row['current_value'], 2); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $row['depreciation_percentage'] >= 90 ? 'dark' : 'danger'; ?>">
                                                        <?php echo $row['depreciation_percentage']; ?>%
                                                    </span>
                                                </td>
                                                <td><?php echo $row['days_since_purchase']; ?> days</td>
                                            <?php elseif ($report_type === 'warranty_status'): ?>
                                                <td><?php echo htmlspecialchars($row['asset_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['control_number']); ?></td>
                                                <td><?php echo htmlspecialchars($row['department_name'] ?: 'N/A'); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $row['warranty_status'] === 'Under Warranty' ? 'success' : ($row['warranty_status'] === 'Warranty Expired' ? 'danger' : 'secondary'); ?>">
                                                        <?php echo $row['warranty_status']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $row['insurance_end'] ? date('M d, Y', strtotime($row['insurance_end'])) : 'N/A'; ?></td>
                                                <td><?php echo htmlspecialchars($row['insurance_provider'] ?: 'N/A'); ?></td>
                                            <?php elseif ($report_type === 'all_assets_monitoring'): ?>
                                                <td><?php echo htmlspecialchars($row['asset_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['control_number']); ?></td>
                                                <td><?php echo htmlspecialchars($row['department_name'] ?: 'N/A'); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $row['status'] === 'Active' ? 'success' : ($row['status'] === 'Depreciated' ? 'danger' : 'secondary'); ?>">
                                                        <?php echo $row['status']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $row['depreciation_percentage'] >= 80 ? 'danger' : 'info'; ?>">
                                                        <?php echo $row['depreciation_percentage']; ?>%
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $row['warranty_status'] === 'Under Warranty' ? 'success' : ($row['warranty_status'] === 'Warranty Expired' ? 'danger' : 'secondary'); ?>">
                                                        <?php echo $row['warranty_status']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($row['maintenance_due_count'] > 0): ?>
                                                        <span class="badge bg-warning"><?php echo $row['maintenance_due_count']; ?> due</span>
                                                    <?php else: ?>
                                                        <span class="text-muted">None</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>No data found for this report.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
$(document).ready(function() {
    // Initialize simple DataTable for all report tables
    if ($('table').length) {
        $('table').each(function(index) {
            const tableId = $(this).attr('id') || 'reportTable_' + index;
            $(this).attr('id', tableId);
            
            $(this).DataTable({
                responsive: true,
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                order: [[0, 'asc']],
                language: {
                    search: "Search records:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries",
                    infoEmpty: "No records found",
                    infoFiltered: "(filtered from _MAX_ total entries)",
                    paginate: {
                        first: "First",
                        last: "Last",
                        next: "Next",
                        previous: "Previous"
                    },
                    emptyTable: "No data available in table"
                }
            });
        });
    }
});

// Working Export Functions that actually download files
function downloadCSV() {
    console.log('Starting CSV download...');
    
    const table = document.querySelector('table');
    if (!table) {
        alert('No table found to export');
        return;
    }
    
    let csvContent = '';
    const rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = [];
        const cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            let cellText = cols[j].innerText || cols[j].textContent || '';
            cellText = cellText.trim().replace(/\s+/g, ' ');
            cellText = cellText.replace(/"/g, '""');
            row.push('"' + cellText + '"');
        }
        
        if (row.length > 0) {
            csvContent += row.join(',') + '\n';
        }
    }
    
    // Create and download file
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    const filename = getFileName() + '.csv';
    link.setAttribute('href', url);
    link.setAttribute('download', filename);
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    URL.revokeObjectURL(url);
    showSuccess('CSV file downloaded: ' + filename);
}

function downloadExcel() {
    console.log('Starting Excel download...');
    
    const table = document.querySelector('table');
    if (!table) {
        alert('No table found to export');
        return;
    }
    
    let csvContent = '';
    const rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = [];
        const cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            let cellText = cols[j].innerText || cols[j].textContent || '';
            cellText = cellText.trim().replace(/\s+/g, ' ');
            cellText = cellText.replace(/"/g, '""');
            row.push('"' + cellText + '"');
        }
        
        if (row.length > 0) {
            csvContent += row.join(',') + '\n';
        }
    }
    
    // Create Excel file
    const blob = new Blob([csvContent], { type: 'application/vnd.ms-excel;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    const filename = getFileName() + '.xls';
    link.setAttribute('href', url);
    link.setAttribute('download', filename);
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    URL.revokeObjectURL(url);
    showSuccess('Excel file downloaded: ' + filename);
}

function printTable() {
    console.log('Starting print...');
    
    const table = document.querySelector('table');
    if (!table) {
        alert('No table found to print');
        return;
    }
    
    const printWindow = window.open('', '_blank');
    if (!printWindow) {
        alert('Please allow pop-ups to print');
        return;
    }
    
    const reportTitle = getReportTitle();
    const currentDate = new Date().toLocaleString();
    
    printWindow.document.write('<!DOCTYPE html>');
    printWindow.document.write('<html><head><title>' + reportTitle + '</title>');
    printWindow.document.write('<style>');
    printWindow.document.write('body { font-family: Arial, sans-serif; margin: 20px; }');
    printWindow.document.write('h1 { color: #667eea; text-align: center; }');
    printWindow.document.write('table { width: 100%; border-collapse: collapse; margin-top: 20px; }');
    printWindow.document.write('th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }');
    printWindow.document.write('th { background-color: #f2f2f2; font-weight: bold; }');
    printWindow.document.write('</style></head><body>');
    printWindow.document.write('<h1>' + reportTitle + '</h1>');
    printWindow.document.write('<p style="text-align: center;">Generated on: ' + currentDate + '</p>');
    printWindow.document.write(table.outerHTML);
    printWindow.document.write('</body></html>');
    
    printWindow.document.close();
    printWindow.print();
    showSuccess('Print dialog opened');
}

// Helper functions
function getReportTitle() {
    const titleElement = document.querySelector('.card-title');
    return titleElement ? titleElement.textContent.trim() : 'Advanced Asset Report';
}

function getFileName() {
    const title = getReportTitle();
    // Clean the title to make it filename-friendly
    const cleanTitle = title.toLowerCase()
        .replace(/[^a-z0-9\s]/g, '') // Remove special characters
        .replace(/\s+/g, '_')        // Replace spaces with underscores
        .replace(/^_+|_+$/g, '')     // Remove leading/trailing underscores
        .substring(0, 50);           // Limit length
    const timestamp = new Date().toISOString().slice(0, 10);
    return cleanTitle + '_' + timestamp;
}

function showSuccess(message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-success alert-dismissible fade show position-fixed';
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = '<i class="fas fa-check-circle me-2"></i>' + message + 
        '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    
    document.body.appendChild(alertDiv);
    
    setTimeout(function() {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

</script>


<?php require_once '../includes/footer.php'; ?>
