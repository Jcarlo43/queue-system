<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'config.php';

$data = json_decode(file_get_contents('php://input'), true);
$queue_id = (int)($data['queue_id'] ?? 0);
$admin_id = (int)($data['admin_id'] ?? 0);

$db = get_db();

$stmt = $db->prepare("UPDATE queue SET status = 'processing', processing_at = NOW() WHERE id = ? AND called_by = ? AND status = 'approached'");
$stmt->bind_param('ii', $queue_id, $admin_id);
$success = $stmt->execute();
$stmt->close();

if ($success) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Could not update status']);
}
?>