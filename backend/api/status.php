<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../config.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

$db = get_db();

$query = "
    SELECT 
        q.id,
        q.queue_number,
        q.name,
        q.purpose,
        q.status,
        a.counter_name as assigned_counter,
        a.display_name as assigned_staff,
        (SELECT COUNT(*) FROM queue WHERE status = 'waiting' AND queue_number < q.queue_number) as waiting_ahead,
        (SELECT COUNT(*) FROM queue WHERE status IN ('called', 'approached', 'processing')) as currently_serving
    FROM queue q
    LEFT JOIN admins a ON q.called_by = a.id
    WHERE q.id = ?
";

$stmt = $db->prepare($query);
$stmt->execute([$id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);  // ✅ Fixed

if ($data) {
    echo json_encode($data);
} else {
    echo json_encode(['error' => 'Queue not found']);
}
?>