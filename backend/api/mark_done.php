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
    WHERE id = ? AND called_by = ? AND status IN ('approached', 'processing')
");
$check->bind_param('ii', $queue_id, $admin_id);
$check->execute();
$result = $check->get_result();
$customer = $result->fetch_assoc();
$check->close();

if (!$customer) {
    echo json_encode(['success' => false, 'error' => 'Customer not found or not in approprite state']);
    exit;
}

// Update status to done
$update = $db->prepare("
    UPDATE queue 
    SET status = 'done', served_by = ?, served_at = NOW() 
    WHERE id = ?
");
$update->bind_param('ii', $admin_id, $queue_id);
$update->execute();
$update->close();

// Clear admin's current serving
$db->query("UPDATE admins SET current_serving = NULL WHERE id = $admin_id");

// Update total served today
$db->query("UPDATE settings SET setting_value = setting_value + 1 WHERE setting_key = 'total_served_today'");

echo json_encode(['success' => true]);
?>