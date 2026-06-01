<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config.php';

$data = json_decode(file_get_contents('php://input'), true);

$name = trim($data['name'] ?? '');
$purpose = trim($data['purpose'] ?? '');
$phone = trim($data['phone'] ?? '');

if (!$name || !$purpose) {
    echo json_encode(['success' => false, 'error' => 'Name and purpose are required']);
    exit;
}

$db = get_db();
$queue_number = get_next_queue_number();

$stmt = $db->prepare("
    INSERT INTO queue (queue_number, name, purpose, phone, status, created_at) 
    VALUES (?, ?, ?, ?, 'waiting', NOW())
    RETURNING id
");
$stmt->execute([$queue_number, $name, $purpose, $phone]);
$queue_id = $stmt->fetchColumn();

echo json_encode([
    'success' => true, 
    'queue_number' => $queue_number, 
    'queue_id' => $queue_id
]);
?>