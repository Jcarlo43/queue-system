<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../config.php';

$data = json_decode(file_get_contents('php://input'), true);
$queue_id = (int)($data['queue_id'] ?? 0);
$admin_id = (int)($data['admin_id'] ?? 0);

$db = get_db();

$stmt = $db->prepare("
    UPDATE queue 
    SET status = 'processing', processing_at = NOW() 
    WHERE id = ? AND called_by = ? AND status = 'approached'
");
$stmt->execute([$queue_id, $admin_id]);
$success = $stmt->rowCount() > 0;

echo json_encode(['success' => $success]);
?>