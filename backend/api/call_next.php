<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config.php';

$data = json_decode(file_get_contents('php://input'), true);
$admin_id = (int)($data['admin_id'] ?? 0);

$db = get_db();

// Check if admin already has a customer
$check = $db->prepare("SELECT current_serving FROM admins WHERE id = ?");
$check->bind_param('i', $admin_id);
$check->execute();
$admin = $check->get_result()->fetch_assoc();
$check->close();

if ($admin && $admin['current_serving']) {
    echo json_encode(['success' => false, 'error' => 'You already have a customer']);
    exit;
}

// Get next waiting customer
$db->begin_transaction();

$stmt = $db->prepare("
    SELECT id, queue_number, name, phone, purpose 
    FROM queue 
    WHERE status = 'waiting' 
    ORDER BY queue_number ASC 
    LIMIT 1 
    FOR UPDATE
");
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$customer) {
    $db->commit();
    echo json_encode(['success' => false, 'error' => 'No customers waiting']);
    exit;
}

// Update customer status
$update = $db->prepare("
    UPDATE queue 
    SET status = 'called', 
        called_by = ?, 
        called_at = NOW()
    WHERE id = ?
");
$update->bind_param('ii', $admin_id, $customer['id']);
$update->execute();
$update->close();

// Update admin's current serving
$update_admin = $db->prepare("
    UPDATE admins 
    SET current_serving = ?, last_activity = NOW() 
    WHERE id = ?
");
$update_admin->bind_param('ii', $customer['id'], $admin_id);
$update_admin->execute();
$update_admin->close();

$db->commit();

echo json_encode([
    'success' => true,
    'queue_number' => $customer['queue_number'],
    'customer_name' => $customer['name']
]);
?>