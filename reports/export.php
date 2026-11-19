<?php
session_start();
require_once '../config/database.php';

// Require admin login
requireAdmin();

// Get parameters
$report_type = $_GET['report'] ?? '';
$format = $_GET['format'] ?? 'csv';

if (empty($report_type)) {
    die('Report type is required');
}

try {
    $pdo = getDB();
    $report_data = [];
    $report_title = '';
    $headers = [];
    
    // Generate report data based on type
    switch ($report_type) {
        case 'all_assets':
            $stmt = $pdo->query("
                SELECT a.asset_name, a.control_number, a.asset_location, a.purchase_date, 
                       a.cost, a.current_value, a.status, a.assigned_to_name, d.department_name,
                       CASE 
                           WHEN a.insurance_end >= CURDATE() THEN 'Under Warranty'
                           ELSE 'Warranty Expired'
                       END as warranty_status
                FROM assets a
                LEFT JOIN departments d ON a.department_id = d.department_id
                ORDER BY a.asset_name
            ");
            $report_data = $stmt->fetchAll();
            $report_title = 'All Assets Report';
            $headers = ['Asset Name', 'Control Number', 'Location', 'Purchase Date', 'Cost', 'Current Value', 'Status', 'Assigned To', 'Department', 'Warranty Status'];
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
            $report_title = 'Maintenance Due Report';
            $headers = ['Asset Name', 'Control Number', 'Assigned To', 'Department', 'Due Date', 'Details', 'Status', 'Days Until Due'];
            break;
            
        case 'depreciated_assets':
            $stmt = $pdo->query("
                SELECT a.asset_name, a.control_number, a.purchase_date, a.cost, a.current_value,
                       d.department_name, a.status,
                       ROUND(((a.cost - a.current_value) / a.cost) * 100, 2) as depreciation_percentage
                FROM assets a
                LEFT JOIN departments d ON a.department_id = d.department_id
                WHERE a.status = 'Depreciated'
                ORDER BY depreciation_percentage DESC
            ");
            $report_data = $stmt->fetchAll();
            $report_title = 'Depreciated Assets Report';
            $headers = ['Asset Name', 'Control Number', 'Purchase Date', 'Original Cost', 'Current Value', 'Department', 'Status', 'Depreciation %'];
            break;
            
        case 'replacement_candidates':
            // Use the exact same function as advanced_reports.php
            require_once '../config/monitoring_functions.php';
            $report_data = getReplacementCandidates($pdo, 0.9);
            $report_title = 'Replacement Candidates Report';
            $headers = ['Asset Name', 'Control Number', 'Department', 'Original Cost', 'Current Value', 'Depreciation %', 'Days Since Purchase'];
            break;
            
        default:
            die('Invalid report type');
    }
    
    if (empty($report_data)) {
        die('No data found for this report');
    }
    
    // Generate export based on format
    if ($format === 'csv') {
        // CSV Export
        $filename = strtolower(str_replace(' ', '_', $report_title)) . '_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');
        
        $output = fopen('php://output', 'w');
        
        // Add title and generation info
        fputcsv($output, [$report_title]);
        fputcsv($output, ['Generated on: ' . date('F d, Y H:i:s')]);
        fputcsv($output, []); // Empty row
        
        // Add headers
        fputcsv($output, $headers);
        
        // Add data
        foreach ($report_data as $row) {
            $csv_row = [];
            
            // Map the data based on report type to match headers exactly
            if ($report_type === 'replacement_candidates') {
                $csv_row = [
                    $row['asset_name'] ?? '',
                    $row['control_number'] ?? '',
                    $row['department_name'] ?? 'N/A',
                    '₱' . number_format($row['cost'] ?? 0, 2),
                    '₱' . number_format($row['current_value'] ?? 0, 2),
                    ($row['depreciation_percentage'] ?? 0) . '%',
                    ($row['days_since_purchase'] ?? 0) . ' days'
                ];
            } else {
                // Default behavior for other reports
                foreach ($row as $key => $value) {
                    if (!is_numeric($key)) { // Skip numeric keys (duplicates)
                        if ($key === 'cost' || $key === 'current_value') {
                            $csv_row[] = '₱' . number_format($value, 2);
                        } elseif ($key === 'purchase_date' || $key === 'next_due_date') {
                            $csv_row[] = date('M d, Y', strtotime($value));
                        } else {
                            $csv_row[] = $value;
                        }
                    }
                }
            }
            
            fputcsv($output, $csv_row);
        }
        
        fclose($output);
        
    } elseif ($format === 'pdf') {
        // Simple PDF export using HTML
        $filename = strtolower(str_replace(' ', '_', $report_title)) . '_' . date('Y-m-d_H-i-s') . '.pdf';
        
        // For now, we'll create an HTML version that can be printed to PDF
        header('Content-Type: text/html');
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title><?php echo htmlspecialchars($report_title); ?></title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .title { color: #667eea; font-size: 24px; font-weight: bold; }
                .date { color: #666; font-size: 14px; margin-top: 10px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #667eea; color: white; font-weight: bold; }
                tr:nth-child(even) { background-color: #f9f9f9; }
                .currency { text-align: right; }
                .center { text-align: center; }
                @media print {
                    body { margin: 0; }
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="title"><?php echo htmlspecialchars($report_title); ?></div>
                <div class="date">Generated on: <?php echo date('F d, Y H:i:s'); ?></div>
            </div>
            
            <div class="no-print" style="margin-bottom: 20px; text-align: center;">
                <button onclick="window.print()" style="padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer;">
                    Print to PDF
                </button>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <?php foreach ($headers as $header): ?>
                            <th><?php echo htmlspecialchars($header); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data as $row): ?>
                        <tr>
                            <?php if ($report_type === 'replacement_candidates'): ?>
                                <td><?php echo htmlspecialchars($row['asset_name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['control_number'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['department_name'] ?? 'N/A'); ?></td>
                                <td class="currency">₱<?php echo number_format($row['cost'] ?? 0, 2); ?></td>
                                <td class="currency">₱<?php echo number_format($row['current_value'] ?? 0, 2); ?></td>
                                <td class="center"><?php echo ($row['depreciation_percentage'] ?? 0); ?>%</td>
                                <td class="center"><?php echo ($row['days_since_purchase'] ?? 0); ?> days</td>
                            <?php else: ?>
                                <?php foreach ($row as $key => $value): ?>
                                    <?php if (!is_numeric($key)): ?>
                                        <td class="<?php echo ($key === 'cost' || $key === 'current_value') ? 'currency' : ''; ?>">
                                            <?php 
                                            if ($key === 'cost' || $key === 'current_value') {
                                                echo '₱' . number_format($value, 2);
                                            } elseif ($key === 'purchase_date' || $key === 'next_due_date') {
                                                echo date('M d, Y', strtotime($value));
                                            } else {
                                                echo htmlspecialchars($value);
                                            }
                                            ?>
                                        </td>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <script>
                // Auto-print for PDF generation
                if (window.location.search.includes('auto_print=1')) {
                    window.onload = function() {
                        setTimeout(function() {
                            window.print();
                        }, 1000);
                    };
                }
            </script>
        </body>
        </html>
        <?php
    }
    
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}
?>
