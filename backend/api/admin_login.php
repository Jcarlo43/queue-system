<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config.php';

$data = json_decode(file_get_contents('php://input'), true);
$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

$db = get_db();

$stmt = $db->prepare("SELECT id, username, password_hash, display_name, counter_name FROM admins WHERE username = ? AND is_active = 1");
$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();

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