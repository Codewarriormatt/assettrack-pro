<?php
session_start();
require_once '../config/database.php';
require_once '../includes/header.php';

// Require admin login
requireAdmin();

$page_title = 'Reports - Asset Management System';

$report_data = [];
$report_type = $_GET['report'] ?? '';

try {
    $pdo = getDB();
    
    switch ($report_type) {
        case 'all_assets':
            $stmt = $pdo->query("
                SELECT a.*, d.department_name,
                       CASE 
                           WHEN a.insurance_end >= CURDATE() THEN 'Under Warranty'
                           ELSE 'Warranty Expired'
                       END as warranty_status,
                       CASE 
                           WHEN DATEDIFF(CURDATE(), a.purchase_date) > (a.lifetime_years * 365) THEN 'Depreciated'
                           ELSE 'Active'
                       END as depreciation_status,
                       a.current_value,
                       (a.cost - a.current_value) as total_depreciation
                FROM assets a
                LEFT JOIN departments d ON a.department_id = d.department_id
                ORDER BY a.asset_name
            ");
            $report_data = $stmt->fetchAll();
            $report_title = 'All Assets Report';
            break;
            
        case 'maintenance_due':
            $stmt = $pdo->query("
                SELECT a.asset_name, a.control_number, a.assigned_to_name, d.department_name,
                       m.next_due_date, m.details, m.status,
                       DATEDIFF(m.next_due_date, CURDATE()) as days_until_due
                FROM maintenance m
                JOIN assets a ON m.asset_id = a.asset_id
                LEFT JOIN departments d ON a.department_id = d.department_id
                WHERE m.status = 'Pending' AND m.next_due_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                ORDER BY m.next_due_date ASC
            ");
            $report_data = $stmt->fetchAll();
            $report_title = 'Assets Nearing Maintenance (Next 30 Days)';
            break;
            
        case 'depreciated_assets':
            $stmt = $pdo->query("
                SELECT a.*, d.department_name,
                       DATEDIFF(CURDATE(), a.purchase_date) as days_owned,
                       (a.lifetime_years * 365) as lifetime_days,
                       a.current_value,
                       (a.cost - a.current_value) as total_depreciation,
                       ROUND(((a.cost - a.current_value) / a.cost) * 100, 2) as depreciation_percentage
                FROM assets a
                LEFT JOIN departments d ON a.department_id = d.department_id
                WHERE a.status = 'Depreciated'
                ORDER BY a.purchase_date ASC
            ");
            $report_data = $stmt->fetchAll();
            $report_title = 'Depreciated Assets Report';
            break;
            
        case 'warranty_assets':
            $stmt = $pdo->query("
                SELECT a.*, d.department_name,
                       DATEDIFF(a.insurance_end, CURDATE()) as days_until_expiry
                FROM assets a
                LEFT JOIN departments d ON a.department_id = d.department_id
                WHERE a.insurance_end >= CURDATE()
                ORDER BY a.insurance_end ASC
            ");
            $report_data = $stmt->fetchAll();
            $report_title = 'Assets Under Warranty Report';
            break;
            
        case 'department_summary':
            $stmt = $pdo->query("
                SELECT d.department_name, d.manager,
                       COUNT(a.asset_id) as total_assets,
                       SUM(CASE WHEN a.status = 'Active' THEN 1 ELSE 0 END) as active_assets,
                       SUM(CASE WHEN a.status = 'Depreciated' THEN 1 ELSE 0 END) as depreciated_assets,
                       SUM(CASE WHEN a.status = 'Active' THEN a.cost ELSE 0 END) as total_original_value,
                       SUM(CASE WHEN a.status = 'Active' THEN a.current_value ELSE 0 END) as total_current_value,
                       SUM(CASE WHEN a.status = 'Active' THEN (a.cost - a.current_value) ELSE 0 END) as total_depreciation,
                       AVG(CASE WHEN a.status = 'Active' THEN a.cost ELSE NULL END) as avg_original_value,
                       AVG(CASE WHEN a.status = 'Active' THEN a.current_value ELSE NULL END) as avg_current_value
                FROM departments d
                LEFT JOIN assets a ON d.department_id = a.department_id
                GROUP BY d.department_id, d.department_name, d.manager
                ORDER BY total_current_value DESC
            ");
            $report_data = $stmt->fetchAll();
            $report_title = 'Asset Summary by Department';
            break;
            
        case 'depreciation_analysis':
            $stmt = $pdo->query("
                SELECT a.*, d.department_name,
                       a.current_value,
                       (a.cost - a.current_value) as total_depreciation,
                       ROUND(((a.cost - a.current_value) / a.cost) * 100, 2) as depreciation_percentage,
                       DATEDIFF(CURDATE(), a.purchase_date) as days_owned,
                       (a.lifetime_years * 365) as lifetime_days,
                       ROUND((DATEDIFF(CURDATE(), a.purchase_date) / (a.lifetime_years * 365)) * 100, 2) as lifetime_percentage_used
                FROM assets a
                LEFT JOIN departments d ON a.department_id = d.department_id
                WHERE a.current_value > 0
                ORDER BY depreciation_percentage DESC
            ");
            $report_data = $stmt->fetchAll();
            $report_title = 'Depreciation Analysis Report';
            break;
            
        case 'maintenance_costs':
            $stmt = $pdo->query("
                SELECT a.asset_name, a.control_number, d.department_name,
                       COUNT(m.maintenance_id) as maintenance_count,
                       SUM(m.cost) as total_maintenance_cost,
                       AVG(m.cost) as avg_maintenance_cost,
                       MAX(m.maintenance_date) as last_maintenance_date
                FROM assets a
                LEFT JOIN departments d ON a.department_id = d.department_id
                LEFT JOIN maintenance m ON a.asset_id = m.asset_id
                GROUP BY a.asset_id, a.asset_name, a.control_number, d.department_name
                HAVING maintenance_count > 0
                ORDER BY total_maintenance_cost DESC
            ");
            $report_data = $stmt->fetchAll();
            $report_title = 'Maintenance Cost Analysis';
            break;
            
        default:
            $report_title = 'Select a Report';
    }
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-chart-bar me-2"></i>Reports</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-download me-1"></i>Export Options
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#" onclick="exportReport('csv'); return false;">
                        <i class="fas fa-file-csv me-2"></i>Export as CSV
                    </a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportReport('excel'); return false;">
                        <i class="fas fa-file-excel me-2"></i>Export as Excel
                    </a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportReport('pdf'); return false;">
                        <i class="fas fa-file-pdf me-2"></i>Export as PDF
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="#" onclick="exportReport('print'); return false;">
                        <i class="fas fa-print me-2"></i>Print Report
                    </a></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger" role="alert">
        <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<!-- Report Selection -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Select Report</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <a href="?report=all_assets" class="btn btn-outline-primary w-100 <?php echo $report_type === 'all_assets' ? 'active' : ''; ?>">
                            <i class="fas fa-boxes me-2"></i>All Assets
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="?report=maintenance_due" class="btn btn-outline-warning w-100 <?php echo $report_type === 'maintenance_due' ? 'active' : ''; ?>">
                            <i class="fas fa-tools me-2"></i>Maintenance Due
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="?report=depreciated_assets" class="btn btn-outline-danger w-100 <?php echo $report_type === 'depreciated_assets' ? 'active' : ''; ?>">
                            <i class="fas fa-exclamation-triangle me-2"></i>Depreciated Assets
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="?report=depreciation_analysis" class="btn btn-outline-secondary w-100 <?php echo $report_type === 'depreciation_analysis' ? 'active' : ''; ?>">
                            <i class="fas fa-chart-line me-2"></i>Depreciation Analysis
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="?report=warranty_assets" class="btn btn-outline-success w-100 <?php echo $report_type === 'warranty_assets' ? 'active' : ''; ?>">
                            <i class="fas fa-shield-alt me-2"></i>Under Warranty
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="?report=department_summary" class="btn btn-outline-info w-100 <?php echo $report_type === 'department_summary' ? 'active' : ''; ?>">
                            <i class="fas fa-building me-2"></i>Department Summary
                        </a>
                    </div>
                    <div class="col-md-4 mb-3">
                        <a href="?report=maintenance_costs" class="btn btn-outline-secondary w-100 <?php echo $report_type === 'maintenance_costs' ? 'active' : ''; ?>">
                            <i class="fas fa-chart-line me-2"></i>Maintenance Costs
                        </a>
                    </div>
                </div>
                
                <?php if (!empty($report_data)): ?>
                <hr>
                <div class="row">
                    <div class="col-12">
                        <h6 class="mb-3"><i class="fas fa-download me-2"></i>Quick Export Options</h6>
                        <div class="btn-group" role="group">
                            <a href="export.php?report=<?php echo urlencode($report_type); ?>&format=csv" 
                               class="btn btn-outline-success btn-sm">
                                <i class="fas fa-file-csv me-1"></i>Download CSV
                            </a>
                            <a href="export.php?report=<?php echo urlencode($report_type); ?>&format=pdf" 
                               class="btn btn-outline-danger btn-sm" target="_blank">
                                <i class="fas fa-file-pdf me-1"></i>View PDF
                            </a>
                            <a href="export.php?report=<?php echo urlencode($report_type); ?>&format=pdf&auto_print=1" 
                               class="btn btn-outline-info btn-sm" target="_blank">
                                <i class="fas fa-print me-1"></i>Print PDF
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($report_type && !empty($report_data)): ?>
    <!-- Report Results -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-chart-bar me-2"></i><?php echo htmlspecialchars($report_title); ?></h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="reportTable">
                    <thead>
                        <tr>
                            <?php if ($report_type === 'all_assets'): ?>
                                <th>Asset Name</th>
                                <th>Control Number</th>
                                <th>Purchase Date</th>
                                <th>Original Cost</th>
                                <th>Current Value</th>
                                <th>Total Depreciation</th>
                                <th>Status</th>
                                <th>Assigned To</th>
                                <th>Department</th>
                                <th>Warranty Status</th>
                                <th>Depreciation Status</th>
                            <?php elseif ($report_type === 'maintenance_due'): ?>
                                <th>Asset Name</th>
                                <th>Control Number</th>
                                <th>Assigned To</th>
                                <th>Department</th>
                                <th>Next Due Date</th>
                                <th>Days Until Due</th>
                                <th>Status</th>
                                <th>Details</th>
                            <?php elseif ($report_type === 'depreciated_assets'): ?>
                                <th>Asset Name</th>
                                <th>Control Number</th>
                                <th>Purchase Date</th>
                                <th>Original Cost</th>
                                <th>Current Value</th>
                                <th>Total Depreciation</th>
                                <th>Depreciation %</th>
                                <th>Lifetime (Years)</th>
                                <th>Days Owned</th>
                                <th>Department</th>
                                <th>Assigned To</th>
                            <?php elseif ($report_type === 'depreciation_analysis'): ?>
                                <th>Asset Name</th>
                                <th>Control Number</th>
                                <th>Purchase Date</th>
                                <th>Original Cost</th>
                                <th>Current Value</th>
                                <th>Total Depreciation</th>
                                <th>Depreciation %</th>
                                <th>Lifetime Used %</th>
                                <th>Department</th>
                                <th>Assigned To</th>
                            <?php elseif ($report_type === 'warranty_assets'): ?>
                                <th>Asset Name</th>
                                <th>Control Number</th>
                                <th>Insurance Type</th>
                                <th>Insurance Provider</th>
                                <th>Insurance End Date</th>
                                <th>Days Until Expiry</th>
                                <th>Department</th>
                                <th>Assigned To</th>
                            <?php elseif ($report_type === 'department_summary'): ?>
                                <th>Department</th>
                                <th>Manager</th>
                                <th>Total Assets</th>
                                <th>Active Assets</th>
                                <th>Depreciated Assets</th>
                                <th>Total Value</th>
                                <th>Average Asset Value</th>
                            <?php elseif ($report_type === 'maintenance_costs'): ?>
                                <th>Asset Name</th>
                                <th>Control Number</th>
                                <th>Department</th>
                                <th>Maintenance Count</th>
                                <th>Total Cost</th>
                                <th>Average Cost</th>
                                <th>Last Maintenance</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $row): ?>
                            <tr>
                                <?php if ($report_type === 'all_assets'): ?>
                                    <td><?php echo htmlspecialchars($row['asset_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['control_number']); ?></td>
                                    <td><?php echo formatDate($row['purchase_date']); ?></td>
                                    <td class="currency"><?php echo formatCurrency($row['cost']); ?></td>
                                    <td class="currency text-success fw-bold"><?php echo formatCurrency($row['current_value'] ?: $row['cost']); ?></td>
                                    <td class="currency text-warning"><?php echo formatCurrency($row['total_depreciation'] ?: 0); ?></td>
                                    <td>
                                        <span class="badge status-<?php echo strtolower(str_replace(' ', '-', $row['status'])); ?>">
                                            <?php echo htmlspecialchars($row['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['assigned_to_name'] ?: 'Unassigned'); ?></td>
                                    <td><?php echo htmlspecialchars($row['department_name'] ?: 'N/A'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $row['warranty_status'] === 'Under Warranty' ? 'success' : 'danger'; ?>">
                                            <?php echo htmlspecialchars($row['warranty_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $row['depreciation_status'] === 'Active' ? 'success' : 'warning'; ?>">
                                            <?php echo htmlspecialchars($row['depreciation_status']); ?>
                                        </span>
                                    </td>
                                <?php elseif ($report_type === 'maintenance_due'): ?>
                                    <td><?php echo htmlspecialchars($row['asset_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['control_number']); ?></td>
                                    <td><?php echo htmlspecialchars($row['assigned_to_name'] ?: 'Unassigned'); ?></td>
                                    <td><?php echo htmlspecialchars($row['department_name'] ?: 'N/A'); ?></td>
                                    <td><?php echo formatDate($row['next_due_date']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $row['days_until_due'] <= 0 ? 'danger' : 
                                                ($row['days_until_due'] <= 7 ? 'warning' : 'info'); 
                                        ?>">
                                            <?php echo $row['days_until_due'] > 0 ? $row['days_until_due'] . ' days' : 'Overdue'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge status-<?php echo strtolower(str_replace(' ', '-', $row['status'])); ?>">
                                            <?php echo htmlspecialchars($row['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['details']); ?></td>
                                <?php elseif ($report_type === 'depreciated_assets'): ?>
                                    <td><?php echo htmlspecialchars($row['asset_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['control_number']); ?></td>
                                    <td><?php echo formatDate($row['purchase_date']); ?></td>
                                    <td class="currency"><?php echo formatCurrency($row['cost']); ?></td>
                                    <td><?php echo $row['lifetime_years']; ?></td>
                                    <td><?php echo number_format($row['days_owned']); ?></td>
                                    <td><?php echo htmlspecialchars($row['department_name'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($row['assigned_to_name'] ?: 'Unassigned'); ?></td>
                                <?php elseif ($report_type === 'warranty_assets'): ?>
                                    <td><?php echo htmlspecialchars($row['asset_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['control_number']); ?></td>
                                    <td><?php echo htmlspecialchars($row['insurance_type'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($row['insurance_provider'] ?: 'N/A'); ?></td>
                                    <td><?php echo formatDate($row['insurance_end']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $row['days_until_expiry'] <= 30 ? 'warning' : 'success'; 
                                        ?>">
                                            <?php echo $row['days_until_expiry']; ?> days
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['department_name'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($row['assigned_to_name'] ?: 'Unassigned'); ?></td>
                                <?php elseif ($report_type === 'department_summary'): ?>
                                    <td><?php echo htmlspecialchars($row['department_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['manager'] ?: 'N/A'); ?></td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $row['total_assets']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success"><?php echo $row['active_assets']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-warning"><?php echo $row['depreciated_assets']; ?></span>
                                    </td>
                                    <td class="currency"><?php echo formatCurrency($row['total_value'] ?: 0); ?></td>
                                    <td class="currency"><?php echo formatCurrency($row['avg_asset_value'] ?: 0); ?></td>
                                <?php elseif ($report_type === 'maintenance_costs'): ?>
                                    <td><?php echo htmlspecialchars($row['asset_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['control_number']); ?></td>
                                    <td><?php echo htmlspecialchars($row['department_name'] ?: 'N/A'); ?></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $row['maintenance_count']; ?></span>
                                    </td>
                                    <td class="currency"><?php echo formatCurrency($row['total_maintenance_cost'] ?: 0); ?></td>
                                    <td class="currency"><?php echo formatCurrency($row['avg_maintenance_cost'] ?: 0); ?></td>
                                    <td><?php echo $row['last_maintenance_date'] ? formatDate($row['last_maintenance_date']) : 'N/A'; ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php elseif ($report_type): ?>
    <div class="alert alert-info" role="alert">
        <i class="fas fa-info-circle me-2"></i>No data found for the selected report.
    </div>
<?php else: ?>
    <div class="alert alert-info" role="alert">
        <i class="fas fa-info-circle me-2"></i>Please select a report from the options above.
    </div>
<?php endif; ?>

<script>
$(document).ready(function() {
    if ($('#reportTable').length) {
        // Initialize DataTable with enhanced export buttons
        const table = $('#reportTable').DataTable({
            responsive: true,
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                 '<"row"<"col-sm-12"B>>' +
                 '<"row"<"col-sm-12"tr>>' +
                 '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            buttons: [
                {
                    extend: 'colvis',
                    text: '<i class="fas fa-columns"></i> Columns',
                    className: 'btn btn-outline-primary btn-sm me-1'
                },
                {
                    extend: 'copy',
                    text: '<i class="fas fa-copy"></i> Copy',
                    className: 'btn btn-outline-secondary btn-sm me-1',
                    title: '<?php echo $report_title ?? 'Report'; ?>'
                },
                {
                    extend: 'csv',
                    text: '<i class="fas fa-file-csv"></i> Export CSV',
                    className: 'btn btn-outline-success btn-sm me-1',
                    filename: '<?php echo isset($report_title) ? strtolower(str_replace(' ', '_', $report_title)) : 'report'; ?>_<?php echo date('Y-m-d_H-i-s'); ?>',
                    title: '<?php echo $report_title ?? 'Report'; ?>',
                    exportOptions: {
                        columns: ':visible'
                    }
                },
                {
                    extend: 'excel',
                    text: '<i class="fas fa-file-excel"></i> Export Excel',
                    className: 'btn btn-outline-success btn-sm me-1',
                    filename: '<?php echo isset($report_title) ? strtolower(str_replace(' ', '_', $report_title)) : 'report'; ?>_<?php echo date('Y-m-d_H-i-s'); ?>',
                    title: '<?php echo $report_title ?? 'Report'; ?>',
                    messageTop: 'Generated on: <?php echo date('F d, Y H:i:s'); ?>',
                    exportOptions: {
                        columns: ':visible'
                    }
                },
                {
                    extend: 'pdf',
                    text: '<i class="fas fa-file-pdf"></i> Export PDF',
                    className: 'btn btn-outline-danger btn-sm me-1',
                    filename: '<?php echo isset($report_title) ? strtolower(str_replace(' ', '_', $report_title)) : 'report'; ?>_<?php echo date('Y-m-d_H-i-s'); ?>',
                    title: '<?php echo $report_title ?? 'Report'; ?>',
                    messageTop: 'Generated on: <?php echo date('F d, Y H:i:s'); ?>',
                    orientation: 'landscape',
                    pageSize: 'A4',
                    exportOptions: {
                        columns: ':visible'
                    },
                    customize: function(doc) {
                        doc.styles.title = {
                            color: '#667eea',
                            fontSize: '16',
                            alignment: 'center',
                            margin: [0, 0, 0, 10]
                        };
                        doc.styles.tableHeader = {
                            bold: true,
                            fontSize: 10,
                            color: 'white',
                            fillColor: '#667eea',
                            alignment: 'center'
                        };
                    }
                },
                {
                    extend: 'print',
                    text: '<i class="fas fa-print"></i> Print',
                    className: 'btn btn-outline-info btn-sm',
                    title: '<?php echo $report_title ?? 'Report'; ?>',
                    messageTop: '<h3 style="text-align:center; color:#667eea;"><?php echo $report_title ?? 'Report'; ?></h3><p style="text-align:center;">Generated on: <?php echo date('F d, Y H:i:s'); ?></p>',
                    exportOptions: {
                        columns: ':visible'
                    }
                }
            ],
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
        
        // Add custom styling to buttons
        $('.dt-buttons').addClass('mb-3');
        
        // Make sure buttons are visible
        setTimeout(function() {
            if ($('.dt-buttons').length && $('.dt-buttons').is(':empty')) {
                console.warn('DataTable buttons not rendered, adding fallback buttons');
                addFallbackExportButtons();
            }
        }, 1000);
    }
});

// Fallback export buttons if DataTable buttons fail
function addFallbackExportButtons() {
    const buttonsHtml = `
        <div class="mb-3">
            <div class="btn-group" role="group" aria-label="Export options">
                <button type="button" class="btn btn-outline-success btn-sm" onclick="exportTableAsCSV()">
                    <i class="fas fa-file-csv"></i> Export CSV
                </button>
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="exportTableAsPDF()">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
                <button type="button" class="btn btn-outline-info btn-sm" onclick="printTable()">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>
    `;
    $('#reportTable').before(buttonsHtml);
}

// Manual CSV export function
function exportTableAsCSV() {
    const table = document.getElementById('reportTable');
    if (!table) return;
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = [];
        const cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length - 1; j++) { // Skip last column (actions)
            let cellText = cols[j].innerText.replace(/"/g, '""');
            row.push('"' + cellText + '"');
        }
        csv.push(row.join(','));
    }
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'report_' + new Date().toISOString().slice(0, 10) + '.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}

// Manual print function
function printTable() {
    const table = document.getElementById('reportTable');
    if (!table) return;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Report</title>
            <style>
                body { font-family: Arial, sans-serif; }
                table { border-collapse: collapse; width: 100%; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .actions { display: none; }
            </style>
        </head>
        <body>
            <h2>Asset Management Report</h2>
            <p>Generated on: ${new Date().toLocaleString()}</p>
            ${table.outerHTML}
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

// Manual PDF export function (using print to PDF)
function exportTableAsPDF() {
    const table = document.getElementById('reportTable');
    if (!table) return;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>Report PDF</title>
            <style>
                @media print {
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    table { border-collapse: collapse; width: 100%; font-size: 10px; }
                    th, td { border: 1px solid #ddd; padding: 4px; text-align: left; }
                    th { background-color: #f2f2f2; font-weight: bold; }
                    .actions { display: none; }
                    @page { margin: 1cm; }
                }
                body { font-family: Arial, sans-serif; margin: 20px; }
                table { border-collapse: collapse; width: 100%; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .actions { display: none; }
            </style>
        </head>
        <body>
            <h2>Asset Management Report</h2>
            <p>Generated on: ${new Date().toLocaleString()}</p>
            ${table.outerHTML}
            <script>
                window.onload = function() {
                    // Hide action columns
                    const actionCells = document.querySelectorAll('.actions, th:last-child, td:last-child');
                    actionCells.forEach(cell => cell.style.display = 'none');
                    
                    // Auto-trigger print dialog for PDF
                    setTimeout(() => {
                        window.print();
                    }, 500);
                };
            </script>
        </body>
        </html>
    `);
    printWindow.document.close();
}

function exportReport(format) {
    console.log('Export function called with format:', format);
    
    if (!$('#reportTable').length) {
        alert('No report table found. Please generate a report first.');
        return;
    }
    
    try {
        const table = $('#reportTable').DataTable();
        console.log('DataTable instance found:', table);
        
        switch(format) {
            case 'csv':
                console.log('Triggering CSV export...');
                if (table.button('.buttons-csv').length) {
                    table.button('.buttons-csv').trigger();
                } else {
                    console.log('DataTable CSV button not found, using fallback');
                    exportTableAsCSV();
                }
                break;
            case 'excel':
                console.log('Triggering Excel export...');
                if (table.button('.buttons-excel').length) {
                    table.button('.buttons-excel').trigger();
                } else {
                    console.log('DataTable Excel button not found, using CSV fallback');
                    exportTableAsCSV();
                }
                break;
            case 'pdf':
                console.log('Triggering PDF export...');
                if (table.button('.buttons-pdf').length) {
                    table.button('.buttons-pdf').trigger();
                } else {
                    console.log('DataTable PDF button not found, using fallback');
                    exportTableAsPDF();
                }
                break;
            case 'print':
                console.log('Triggering Print...');
                if (table.button('.buttons-print').length) {
                    table.button('.buttons-print').trigger();
                } else {
                    console.log('DataTable Print button not found, using fallback');
                    printTable();
                }
                break;
            default:
                alert('Unknown export format: ' + format);
        }
    } catch (error) {
        console.error('Export error:', error);
        alert('Export failed. Please try again or use the export buttons below the table.');
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
