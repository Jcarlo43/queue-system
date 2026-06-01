<?php
header('Access-Control-Allow-Origin: https://jcarlo43.github.io');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Database configuration from environment variables
define('DB_HOST', getenv('DB_HOST') ?: 'aws-1-ap-southeast-1.pooler.supabase.com');
define('DB_USER', getenv('DB_USER') ?: 'postgres.rwofmwiasawnihpbsijg');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'postgres');
define('DB_PORT', getenv('DB_PORT') ?: '5432');

// PDO connection for PostgreSQL
function get_db() {
    static $connection = null;
    
    if ($connection === null) {
        try {
            $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";sslmode=require";
            $connection = new PDO($dsn, DB_USER, DB_PASS);
            $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    
    return $connection;
}

function get_next_queue_number() {
    $db = get_db();
    $stmt = $db->prepare("UPDATE settings SET setting_value = (setting_value::INTEGER + 1) WHERE setting_key = 'queue_counter' RETURNING setting_value");
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function create_tables_if_not_exist() {
    $db = get_db();
    
    // Queue table
    $db->exec("
        CREATE TABLE IF NOT EXISTS queue (
            id SERIAL PRIMARY KEY,
            queue_number INTEGER NOT NULL UNIQUE,
            name VARCHAR(120) NOT NULL,
            purpose VARCHAR(200) NOT NULL,
            phone VARCHAR(30) DEFAULT '',
            status VARCHAR(20) DEFAULT 'waiting',
            called_by INTEGER DEFAULT NULL,
            called_at TIMESTAMP DEFAULT NULL,
            approached_at TIMESTAMP DEFAULT NULL,
            processing_at TIMESTAMP DEFAULT NULL,
            served_by INTEGER DEFAULT NULL,
            served_at TIMESTAMP DEFAULT NULL,
            pending_reason VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Admins table
    $db->exec("
        CREATE TABLE IF NOT EXISTS admins (
            id SERIAL PRIMARY KEY,
            username VARCHAR(60) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            display_name VARCHAR(100) DEFAULT '',
            counter_name VARCHAR(100) DEFAULT '',
            role VARCHAR(20) DEFAULT 'staff',
            is_active BOOLEAN DEFAULT TRUE,
            current_serving INTEGER DEFAULT NULL,
            last_activity TIMESTAMP DEFAULT NULL,
            last_login TIMESTAMP DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Settings table
    $db->exec("
        CREATE TABLE IF NOT EXISTS settings (
            setting_key VARCHAR(60) PRIMARY KEY,
            setting_value VARCHAR(200) NOT NULL
        )
    ");
    
    // Check if settings exist
    $stmt = $db->query("SELECT COUNT(*) FROM settings");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        $db->exec("
            INSERT INTO settings (setting_key, setting_value) VALUES 
                ('queue_counter', '0'),
                ('total_served_today', '0'),
                ('total_cancelled_today', '0'),
                ('last_reset_date', CURRENT_DATE)
        ");
    }
    
    // Check if admin exists
    $stmt = $db->query("SELECT COUNT(*) FROM admins WHERE username = 'admin'");
    $adminCount = $stmt->fetchColumn();
    
    if ($adminCount == 0) {
        $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("
            INSERT INTO admins (username, password_hash, display_name, counter_name, role) 
            VALUES ('admin', ?, 'Administrator', 'Main Counter', 'admin')
        ");
        $stmt->execute([$password_hash]);
    }
}

// Initialize tables
create_tables_if_not_exist();
?>