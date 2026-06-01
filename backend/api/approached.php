<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config.php';

$data = json_decode(file_get_contents('php://input'), true);
$queue_id = (int)($data['queue_id'] ?? 0);
$admin_id = (int)($data['admin_id'] ?? 0);

$db = get_db();

// Verify this customer is assigned to this admin
$check = $db->prepare("
    SELECT id, status FROM queue 
    WHERE id = ? AND called_by = ? AND status = 'called'
");
$check->bind_param('ii', $queue_id, $admin_id);
$check->execute();
$result = $check->get_result();
$customer = $result->fetch_assoc();
$check->close();

if (!$customer) {
    echo json_encode(['success' => false, 'error' => 'Customer not found or not in called state']);
    exit;
}

// Update status to approached
$update = $db->prepare("
    UPDATE queue 
    SET status = 'approached', approached_at = NOW() 
    WHERE id = ?
");
$update->bind_param('i', $queue_id);
$update->execute();
$update->close();

echo json_encode(['success' => true]);
?>