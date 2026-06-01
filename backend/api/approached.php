<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../config.php';

$data = json_decode(file_get_contents('php://input'), true);
$queue_id = (int)($data['queue_id'] ?? 0);
$admin_id = (int)($data['admin_id'] ?? 0);

$db = get_db();

// Verify this customer is assigned to this admin
$stmt = $db->prepare("
    SELECT id, status FROM queue 
    WHERE id = ? AND called_by = ? AND status = 'called'
");
$stmt->execute([$queue_id, $admin_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);  // ✅ Fixed

if (!$customer) {
    echo json_encode(['success' => false, 'error' => 'Customer not found or not in called state']);
    exit;
}

// Update status to approached
$stmt = $db->prepare("
    UPDATE queue 
    SET status = 'approached', approached_at = NOW() 
    WHERE id = ?
");
$stmt->execute([$queue_id]);

echo json_encode(['success' => true]);
?>