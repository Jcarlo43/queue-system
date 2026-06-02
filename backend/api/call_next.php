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
$admin_id = (int)($data['admin_id'] ?? 0);

$db = get_db();

// Check if admin already has a customer
$stmt = $db->prepare("SELECT current_serving FROM admins WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if ($admin && $admin['current_serving']) {
    echo json_encode(['success' => false, 'error' => 'You already have a customer']);
    exit;
}

// Get next waiting customer
$db->beginTransaction();

$stmt = $db->prepare("
    SELECT id, queue_number, name, phone, purpose 
    FROM queue 
    WHERE status = 'waiting' 
    ORDER BY queue_number ASC 
    LIMIT 1 
    FOR UPDATE
");
$stmt->execute();
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    $db->commit();
    echo json_encode(['success' => false, 'error' => 'No customers waiting']);
    exit;
}

// Update customer status
$stmt = $db->prepare("
    UPDATE queue 
    SET status = 'called', 
        called_by = ?, 
        called_at = NOW()
    WHERE id = ?
");
$stmt->execute([$admin_id, $customer['id']]);

// Update admin's current serving
$stmt = $db->prepare("
    UPDATE admins 
    SET current_serving = ?, last_activity = NOW() 
    WHERE id = ?
");
$stmt->execute([$customer['id'], $admin_id]);

$db->commit();

echo json_encode([
    'success' => true,
    'queue_number' => $customer['queue_number'],
    'customer_name' => $customer['name']
]);
?>  