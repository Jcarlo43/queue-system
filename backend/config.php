<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database configuration - UPDATE THESE WITH YOUR RENDER POSTGRESQL CREDENTIALS
define('DB_HOST', 'your-render-postgres-host');
define('DB_USER', 'your-username');
define('DB_PASS', 'your-password');
define('DB_NAME', 'your-database-name');

// Or use MySQL on Render (if using MySQL)
// define('DB_HOST', 'your-mysql-host');
// define('DB_USER', 'your-username');
// define('DB_PASS', 'your-password');
// define('DB_NAME', 'your-database-name');

function get_db() {
    static $connection = null;
    
    if ($connection === null) {
        // For PostgreSQL on Render
        $connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($connection->connect_error) {
            error_log("Database connection failed: " . $connection->connect_error);
            die(json_encode(['error' => 'Database connection failed']));
        }
        
        $connection->set_charset('utf8mb4');
        
        // Create tables if they don't exist
        create_tables_if_not_exist($connection);
    }
    
    return $connection;
}

function create_tables_if_not_exist($conn) {
    // Queue table
    $conn->query("
        CREATE TABLE IF NOT EXISTS queue (
            id INT AUTO_INCREMENT PRIMARY KEY,
            queue_number INT NOT NULL UNIQUE,
            name VARCHAR(120) NOT NULL,
            purpose VARCHAR(200) NOT NULL,
            phone VARCHAR(30) DEFAULT '',
            status ENUM('waiting','called','approached','processing','done','cancelled') DEFAULT 'waiting',
            called_by INT DEFAULT NULL,
            called_at DATETIME DEFAULT NULL,
            approached_at DATETIME DEFAULT NULL,
            processing_at DATETIME DEFAULT NULL,
            served_by INT DEFAULT NULL,
            served_at DATETIME DEFAULT NULL,
            pending_reason VARCHAR(255) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_queue_number (queue_number),
            INDEX idx_called_by (called_by)
        )
    ");
    
    // Admins table
    $conn->query("
        CREATE TABLE IF NOT EXISTS admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(60) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            display_name VARCHAR(100) DEFAULT '',
            counter_name VARCHAR(100) DEFAULT '',
            role VARCHAR(20) DEFAULT 'staff',
            is_active BOOLEAN DEFAULT TRUE,
            current_serving INT DEFAULT NULL,
            last_activity DATETIME DEFAULT NULL,
            last_login DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Settings table
    $conn->query("
        CREATE TABLE IF NOT EXISTS settings (
            setting_key VARCHAR(60) PRIMARY KEY,
            setting_value VARCHAR(200) NOT NULL
        )
    ");
    
    // Insert default settings if not exists
    $result = $conn->query("SELECT COUNT(*) as cnt FROM settings");
    $row = $result->fetch_assoc();
    if ($row['cnt'] == 0) {
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES 
            ('queue_counter', '0'),
            ('total_served_today', '0'),
            ('total_cancelled_today', '0'),
            ('last_reset_date', CURDATE())
        ");
    }
    
    // Insert default admin if not exists
    $result = $conn->query("SELECT COUNT(*) as cnt FROM admins WHERE username = 'admin'");
    $row = $result->fetch_assoc();
    if ($row['cnt'] == 0) {
        $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO admins (username, password_hash, display_name, counter_name, role) VALUES (?, ?, ?, ?, 'admin')");
        $display_name = 'Administrator';
        $counter_name = 'Main Counter';
        $stmt->bind_param('ssss', 'admin', $password_hash, $display_name, $counter_name);
        $stmt->execute();
        $stmt->close();
    }
}

function get_next_queue_number() {
    $db = get_db();
    $db->query("UPDATE settings SET setting_value = LAST_INSERT_ID(setting_value + 1) WHERE setting_key = 'queue_counter'");
    $result = $db->query("SELECT LAST_INSERT_ID() as next_num");
    $row = $result->fetch_assoc();
    return (int)$row['next_num'];
}

function log_activity($admin_id, $queue_id, $action, $details = null) {
    // Optional: Implement logging if needed
}
?>