<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../config.php';

$data = json_decode(file_get_contents('php://input'), true);
$name = trim($data['name'] ?? '');
$purpose = trim($data['purpose'] ?? '');
$phone = trim($data['phone'] ?? '');

if (!$name || !$purpose) {
    echo json_encode(['success' => false, 'error' => 'Name and purpose required']);
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
$queue_id = $stmt->fetchColumn();  // ✅ PostgreSQL way to get last insert ID

echo json_encode([
    'success' => true,
    'queue_number' => $queue_number,
    'queue_id' => $queue_id
]);
?>