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

$data    = json_decode(file_get_contents('php://input'), true);
$name    = trim($data['name']          ?? '');
$purpose = trim($data['purpose']       ?? '');
$phone   = trim($data['phone']         ?? '');
$token   = trim($data['browser_token'] ?? '');

if (!$name || !$purpose) {
    echo json_encode(['success' => false, 'error' => 'Name and purpose required']);
    exit;
}

$db = get_db();

// ── Safe migration: add browser_token column if it doesn't exist yet ─────────
// Must run BEFORE any query that references the column.
$db->exec("
    ALTER TABLE queue
    ADD COLUMN IF NOT EXISTS browser_token VARCHAR(120) DEFAULT ''
");

// ── Duplicate check via browser token ────────────────────────────────────────
if ($token !== '') {
    $stmt = $db->prepare("
        SELECT id, queue_number
        FROM queue
        WHERE browser_token = ?
          AND status NOT IN ('done', 'cancelled')
          AND created_at::date = CURRENT_DATE
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        echo json_encode([
            'success'         => false,
            'duplicate'       => true,
            'existing_id'     => $existing['id'],
            'existing_number' => $existing['queue_number'],
        ]);
        exit;
    }
}

// ── Register new queue entry ──────────────────────────────────────────────────
$queue_number = get_next_queue_number();

$stmt = $db->prepare("
    INSERT INTO queue (queue_number, name, purpose, phone, browser_token, status, created_at)
    VALUES (?, ?, ?, ?, ?, 'waiting', NOW())
    RETURNING id
");
$stmt->execute([$queue_number, $name, $purpose, $phone, $token]);
$queue_id = $stmt->fetchColumn();

echo json_encode([
    'success'      => true,
    'queue_number' => $queue_number,
    'queue_id'     => $queue_id,
]);