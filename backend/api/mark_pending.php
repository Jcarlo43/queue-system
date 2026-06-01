<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config.php';

$data = json_decode(file_get_contents('php://input'), true);
$queue_id = (int)($data['queue_id'] ?? 0);
$admin_id = (int)($data['admin_id'] ?? 0);
$reason = $data['reason'] ?? 'no_show';

$db = get_db();

// Verify this customer is assigned to this admin
$check = $db->prepare("
    SELECT id FROM queue 
    WHERE id = ? AND called_by = ? AND status IN ('called', 'approached')
");
$check->bind_param('ii', $queue_id, $admin_id);
$check->execute();
$result = $check->get_result();
$customer = $result->fetch_assoc();
$check->close();

if (!$customer) {
    echo json_encode(['success' => false, 'error' => 'Customer not found']);
    exit;
}

// Move back to waiting (pending)
$update = $db->prepare("
    UPDATE queue 
    SET status = 'waiting', 
        called_by = NULL, 
        called_at = NULL,
        pending_reason = ?
    WHERE id = ?
");
$update->bind_param('si', $reason, $queue_id);
$update->execute();
$update->close();

// Clear admin's current serving
$db->query("UPDATE admins SET current_serving = NULL WHERE id = $admin_id");

echo json_encode(['success' => true]);
?>