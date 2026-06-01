<?php
header('Access-Control-Allow-Origin: https://jcarlo43.github.io');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../config.php';

$data = json_decode(file_get_contents('php://input'), true);
$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

$db = get_db();

$stmt = $db->prepare("SELECT id, username, password_hash, display_name, counter_name FROM admins WHERE username = ? AND is_active = true");
$stmt->execute([$username]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if ($admin && password_verify($password, $admin['password_hash'])) {
    $token = bin2hex(random_bytes(32));
    echo json_encode([
        'success' => true,
        'token' => $token,
        'admin_id' => $admin['id'],
        'display_name' => $admin['display_name'] ?: $admin['username'],
        'counter_name' => $admin['counter_name']
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
}
?>