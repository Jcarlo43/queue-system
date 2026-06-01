<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'config.php';

$admin_id = (int)($_GET['admin_id'] ?? 0);

$db = get_db();

// Get admin's current customer
$current = $db->query("
    SELECT q.id, q.queue_number, q.name, q.status 
    FROM queue q 
    WHERE q.called_by = $admin_id AND q.status IN ('called', 'approached', 'processing')
    LIMIT 1
")->fetch_assoc();

$waiting = $db->query("SELECT COUNT(*) FROM queue WHERE status = 'waiting'")->fetch_row()[0];
$called = $db->query("SELECT COUNT(*) FROM queue WHERE status IN ('called', 'approached', 'processing')")->fetch_row()[0];
$completed = $db->query("SELECT COUNT(*) FROM queue WHERE status = 'done' AND DATE(served_at) = CURDATE()")->fetch_row()[0];
$my_completed = $db->query("SELECT COUNT(*) FROM queue WHERE served_by = $admin_id AND DATE(served_at) = CURDATE()")->fetch_row()[0];

$queue_result = $db->query("
    SELECT q.id, q.queue_number, q.name, q.phone, q.purpose, q.status,
           TIMESTAMPDIFF(MINUTE, q.created_at, NOW()) as wait_mins,
           a.counter_name
    FROM queue q
    LEFT JOIN admins a ON q.called_by = a.id
    WHERE q.status IN ('waiting', 'called', 'approached')
    ORDER BY FIELD(q.status, 'called', 'approached', 'waiting'), q.queue_number
");

$queue = [];
while ($row = $queue_result->fetch_assoc()) {
    $queue[] = $row;
}

echo json_encode([
    'current_customer' => $current,
    'waiting_count' => $waiting,
    'called_count' => $called,
    'completed_today' => $completed,
    'my_completed' => $my_completed,
    'queue' => $queue
]);
?>