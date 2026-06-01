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
$queue_id = (int)($data['queue_id'] ?? 0);
$admin_id = (int)($data['admin_id'] ?? 0);
$reason = $data['reason'] ?? 'cancelled';

$db = get_db();

// Verify this customer is assigned to this admin
$stmt = $db->prepare("
    SELECT id FROM queue 
    WHERE id = ? AND called_by = ? AND status IN ('called', 'approached')
");
$stmt->execute([$queue_id, $admin_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    echo json_encode(['success' => false, 'error' => 'Customer not found']);
    exit;
}

// Update status to cancelled
$stmt = $db->prepare("
    UPDATE queue 
    SET status = 'cancelled', 
        pending_reason = ?
    WHERE id = ?
");
$stmt->execute([$reason, $queue_id]);

// Clear admin's current serving
$db->prepare("UPDATE admins SET current_serving = NULL WHERE id = ?")->execute([$admin_id]);

// Update cancelled count
$db->prepare("UPDATE settings SET setting_value = (setting_value::INTEGER + 1) WHERE setting_key = 'total_cancelled_today'")->execute();

echo json_encode(['success' => true]);
?>