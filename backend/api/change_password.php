<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../config.php';

$data = json_decode(file_get_contents('php://input'), true);
$admin_id = (int)($data['admin_id'] ?? 0);
$current_password = $data['current_password'] ?? '';
$new_password = $data['new_password'] ?? '';

$db = get_db();

// Get current password hash
$stmt = $db->prepare("SELECT password_hash FROM admins WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);  // ✅ Fixed

if (!$admin) {
    echo json_encode(['success' => false, 'error' => 'Admin not found']);
    exit;
}

if (!password_verify($current_password, $admin['password_hash'])) {
    echo json_encode(['success' => false, 'error' => 'Current password is incorrect']);
    exit;
}

if (strlen($new_password) < 6) {
    echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters']);
    exit;
}

// Update password
$new_hash = password_hash($new_password, PASSWORD_DEFAULT);
$stmt = $db->prepare("UPDATE admins SET password_hash = ? WHERE id = ?");
$stmt->execute([$new_hash, $admin_id]);

echo json_encode(['success' => true]);
?>