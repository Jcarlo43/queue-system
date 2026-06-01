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
$queue_id = (int)($data['queue_id'] ?? 0);

$db = get_db();

// Get the admin who called this customer
$stmt = $db->prepare("SELECT called_by FROM queue WHERE id = ? AND status = 'called'");
$stmt->execute([$queue_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row && $row['called_by']) {
    $admin_id = $row['called_by'];
    
    // Move back to waiting (auto pending due to no response)
    $stmt = $db->prepare("
        UPDATE queue 
        SET status = 'waiting', 
            called_by = NULL, 
            called_at = NULL,
            pending_reason = 'auto_pending_no_response'
        WHERE id = ?
    ");
    $stmt->execute([$queue_id]);
    
    // Clear admin's current serving
    $db->prepare("UPDATE admins SET current_serving = NULL WHERE id = ?")->execute([$admin_id]);
}

echo json_encode(['success' => true]);
?>