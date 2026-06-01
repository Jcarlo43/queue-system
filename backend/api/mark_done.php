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
    WHERE id = ? AND called_by = ? AND status IN ('approached', 'processing')
");
$stmt->execute([$queue_id, $admin_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);  // ✅ Fixed

if (!$customer) {
    echo json_encode(['success' => false, 'error' => 'Customer not found or not in appropriate state']);
    exit;
}

// Update status to done
$stmt = $db->prepare("
    UPDATE queue 
    SET status = 'done', served_by = ?, served_at = NOW() 
    WHERE id = ?
");
$stmt->execute([$admin_id, $queue_id]);

// Clear admin's current serving
$db->prepare("UPDATE admins SET current_serving = NULL WHERE id = ?")->execute([$admin_id]);

// Update total served today
$db->prepare("UPDATE settings SET setting_value = (setting_value::INTEGER + 1) WHERE setting_key = 'total_served_today'")->execute();

echo json_encode(['success' => true]);
?>