<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config.php';

$data = json_decode(file_get_contents('php://input'), true);
$queue_id = (int)($data['queue_id'] ?? 0);

$db = get_db();

// Get the admin who called this customer
$stmt = $db->prepare("SELECT called_by FROM queue WHERE id = ? AND status = 'called'");
$stmt->bind_param('i', $queue_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if ($row && $row['called_by']) {
    $admin_id = $row['called_by'];
    
    // Move back to waiting (auto pending due to no response)
    $update = $db->prepare("
        UPDATE queue 
        SET status = 'waiting', 
            called_by = NULL, 
            called_at = NULL,
            pending_reason = 'auto_pending_no_response'
        WHERE id = ?
    ");
    $update->bind_param('i', $queue_id);
    $update->execute();
    $update->close();
    
    // Clear admin's current serving
    $db->query("UPDATE admins SET current_serving = NULL WHERE id = $admin_id");
}

echo json_encode(['success' => true]);
?>