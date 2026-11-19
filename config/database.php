<?php
/**
 * Database Configuration
 * Asset Management System
 */

class Database {
    private $host = 'localhost';
    private $db_name = 'asset_management';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        
        return $this->conn;
    }
}

// Global function to get database connection
function getDB() {
    $database = new Database();
    return $database->getConnection();
}

// Base URL function for proper path handling
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    
    // Get the project folder on disk and the current script path
    $appRoot = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    $scriptFile = str_replace('\\', '/', realpath($_SERVER['SCRIPT_FILENAME']));
    $scriptPath = $_SERVER['SCRIPT_NAME'];

    // Find the project folder from the request path, so any folder name works
    // Example: /assettrack-pro-main/assets/assets.php -> /assettrack-pro-main
    $projectPath = '';
    if ($appRoot !== '' && strncasecmp($scriptFile, $appRoot . '/', strlen($appRoot) + 1) === 0) {
        $relativePath = substr($scriptFile, strlen($appRoot)); // e.g. /assets/assets.php
        $projectPath = substr($scriptPath, 0, strlen($scriptPath) - strlen($relativePath));
    }

    return $protocol . '://' . $host . rtrim($projectPath, '/');
}

// Function to log user activities
function logActivity($pdo, $userId, $actionType, $tableName, $description) {
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_log (user_id, action_type, table_name, description, timestamp) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $actionType, $tableName, $description]);
    } catch(PDOException $e) {
        error_log("Activity logging failed: " . $e->getMessage());
    }
}

// Function to format currency (Philippine Peso)
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

// Function to format date for display
function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Function to check if user is admin
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'Admin';
}

// Function to redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . getBaseUrl() . '/login.php');
        exit();
    }
}

// Function to redirect if not admin
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . getBaseUrl() . '/dashboard.php');
        exit();
    }
}

// Function to calculate current depreciated value
function calculateCurrentValue($cost, $lifetime_years, $purchase_date, $depreciation_method = 'straight-line') {
    if (empty($cost) || empty($lifetime_years) || empty($purchase_date)) {
        return $cost; // Return original cost if missing data
    }
    
    $cost = floatval($cost);
    $lifetime_years = intval($lifetime_years);
    
    // Calculate years since purchase
    $purchase_timestamp = strtotime($purchase_date);
    $current_timestamp = time();
    $years_elapsed = ($current_timestamp - $purchase_timestamp) / (365.25 * 24 * 60 * 60); // Account for leap years
    
    if ($years_elapsed <= 0) {
        return $cost; // Asset is new
    }
    
    if ($years_elapsed >= $lifetime_years) {
        return 0; // Asset is fully depreciated
    }
    
    switch ($depreciation_method) {
        case 'straight-line':
            $annual_depreciation = $cost / $lifetime_years;
            $total_depreciation = $annual_depreciation * $years_elapsed;
            return max(0, $cost - $total_depreciation);
            
        case 'declining-balance':
            $rate = 2 / $lifetime_years; // Double declining balance
            $current_value = $cost;
            for ($i = 0; $i < $years_elapsed; $i++) {
                $current_value = $current_value * (1 - $rate);
            }
            return max(0, $current_value);
            
        default:
            // Default to straight-line
            $annual_depreciation = $cost / $lifetime_years;
            $total_depreciation = $annual_depreciation * $years_elapsed;
            return max(0, $cost - $total_depreciation);
    }
}

// Function to update asset current value
function updateAssetCurrentValue($pdo, $asset_id) {
    try {
        // Get asset data
        $stmt = $pdo->prepare("SELECT cost, lifetime_years, purchase_date, depreciation_method, status FROM assets WHERE asset_id = ?");
        $stmt->execute([$asset_id]);
        $asset = $stmt->fetch();
        
        if ($asset) {
            $current_value = calculateCurrentValue(
                $asset['cost'],
                $asset['lifetime_years'],
                $asset['purchase_date'],
                $asset['depreciation_method']
            );
            
            // Check if asset should be marked as depreciated
            $new_status = $asset['status'];
            if ($current_value <= 0 && $asset['status'] === 'Active') {
                $new_status = 'Depreciated';
            }
            
            // Update current value and status if needed
            if ($new_status !== $asset['status']) {
                $updateStmt = $pdo->prepare("UPDATE assets SET current_value = ?, status = ? WHERE asset_id = ?");
                $updateStmt->execute([$current_value, $new_status, $asset_id]);
                
                // Log the status change
                if (function_exists('logActivity')) {
                    logActivity($pdo, $_SESSION['user_id'] ?? 1, 'UPDATE', 'assets', "Asset automatically marked as Depreciated due to 100% depreciation (Asset ID: $asset_id)");
                }
            } else {
                $updateStmt = $pdo->prepare("UPDATE assets SET current_value = ? WHERE asset_id = ?");
                $updateStmt->execute([$current_value, $asset_id]);
            }
            
            return $current_value;
        }
    } catch (PDOException $e) {
        error_log("Error updating asset current value: " . $e->getMessage());
    }
    
    return null;
}
?>
