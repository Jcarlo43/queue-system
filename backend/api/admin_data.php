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

$admin_id = (int)($_GET['admin_id'] ?? 0);

$db = get_db();

// Get admin's current customer
$stmt = $db->prepare("
    SELECT q.id, q.queue_number, q.name, q.status 
    FROM queue q 
    WHERE q.called_by = ? AND q.status IN ('called', 'approached', 'processing')
    LIMIT 1
");
$stmt->execute([$admin_id]);
$current = $stmt->fetch(PDO::FETCH_ASSOC);

// Get counts
$waiting = $db->query("SELECT COUNT(*) FROM queue WHERE status = 'waiting'")->fetchColumn();
$called = $db->query("SELECT COUNT(*) FROM queue WHERE status IN ('called', 'approached', 'processing')")->fetchColumn();
$completed = $db->query("SELECT COUNT(*) FROM queue WHERE status = 'done' AND DATE(served_at) = CURRENT_DATE")->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM queue WHERE served_by = ? AND DATE(served_at) = CURRENT_DATE");
$stmt->execute([$admin_id]);
$my_completed = $stmt->fetchColumn();

// Get active queue
$stmt = $db->prepare("
    SELECT q.id, q.queue_number, q.name, q.phone, q.purpose, q.status,
           EXTRACT(MINUTE FROM (NOW() - q.created_at)) as wait_mins,
           a.counter_name
    FROM queue q
    LEFT JOIN admins a ON q.called_by = a.id
    WHERE q.status IN ('waiting', 'called', 'approached')
    ORDER BY CASE q.status 
        WHEN 'called' THEN 1 
        WHEN 'approached' THEN 2 
        ELSE 3 END, q.queue_number
");
$stmt->execute();
$queue = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get active counters
$stmt = $db->query("
    SELECT a.id, a.display_name, a.counter_name, a.current_serving,
           q.queue_number
    FROM admins a
    LEFT JOIN queue q ON a.current_serving = q.id
    WHERE a.is_active = true AND a.role != 'admin'
    ORDER BY a.counter_name
");
$counters = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'current_customer' => $current,
    'waiting_count' => (int)$waiting,
    'called_count' => (int)$called,
    'completed_today' => (int)$completed,
    'my_completed' => (int)$my_completed,
    'queue' => $queue,
    'counters' => $counters
]);
?>