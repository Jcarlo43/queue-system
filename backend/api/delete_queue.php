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

$data     = json_decode(file_get_contents('php://input'), true);
$queue_id = (int)($data['queue_id'] ?? 0);
$admin_id = (int)($data['admin_id'] ?? 0);

if ($queue_id <= 0 || $admin_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

$db = get_db();

// Verify admin exists and is active
$stmt = $db->prepare("SELECT id FROM admins WHERE id = ? AND is_active = true");
$stmt->execute([$admin_id]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Fetch current state of the queue entry
$stmt = $db->prepare("SELECT id, status, called_by FROM queue WHERE id = ?");
$stmt->execute([$queue_id]);
$entry = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$entry) {
    echo json_encode(['success' => false, 'error' => 'Queue entry not found']);
    exit;
}

// If someone is actively serving this customer, clear the admin's slot first
if ($entry['called_by']) {
    $db->prepare("UPDATE admins SET current_serving = NULL WHERE id = ? AND current_serving = ?")
       ->execute([$entry['called_by'], $queue_id]);
}

// Hard-delete the row
$db->prepare("DELETE FROM queue WHERE id = ?")->execute([$queue_id]);

echo json_encode(['success' => true]);
