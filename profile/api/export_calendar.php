<?php
declare(strict_types=1);
require_once __DIR__ . '/../../security/auth_gate.php';

$meId = (int)($_SESSION['user_id'] ?? 0);
$targetId = isset($_GET['user_id']) && is_numeric($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if (!$targetId) {
    http_response_code(400);
    die('Invalid user_id');
}

// Check permissions (amp 1-9 can export)
$stmt = $pdo->prepare("SELECT amp_id FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$meId]);
$me = $stmt->fetch(PDO::FETCH_ASSOC);

$myAmpId = (int)($me['amp_id'] ?? 10);

if ($myAmpId >= 10) {
    http_response_code(403);
    die('Insufficient permissions');
}

// Fetch calendar events
$stmt = $pdo->prepare("
    SELECT 
        e.id,
        e.title,
        e.start_at,
        e.end_at,
        e.description,
        e.all_day
    FROM calendar_events e
    WHERE e.user_id = ?
    ORDER BY e.start_at ASC
");
$stmt->execute([$targetId]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generate iCal format
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="calendar_' . $targetId . '.ics"');

echo "BEGIN:VCALENDAR\r\n";
echo "VERSION:2.0\r\n";
echo "PRODID:-//AI Slimbybel//Calendar Export//EN\r\n";
echo "CALSCALE:GREGORIAN\r\n";

foreach ($events as $event) {
    $uid = 'event-' . $event['id'] . '@aislimbybel.com';
    $dtstart = date('Ymd\THis\Z', strtotime($event['start_at']));
    $dtend = $event['end_at'] ? date('Ymd\THis\Z', strtotime($event['end_at'])) : $dtstart;
    $summary = str_replace(["\r", "\n"], ' ', $event['title'] ?? '');
    $description = str_replace(["\r", "\n"], ' ', $event['description'] ?? '');
    
    echo "BEGIN:VEVENT\r\n";
    echo "UID:$uid\r\n";
    echo "DTSTAMP:" . date('Ymd\THis\Z') . "\r\n";
    echo "DTSTART:$dtstart\r\n";
    echo "DTEND:$dtend\r\n";
    echo "SUMMARY:$summary\r\n";
    if ($description) {
        echo "DESCRIPTION:$description\r\n";
    }
    echo "END:VEVENT\r\n";
}

echo "END:VCALENDAR\r\n";