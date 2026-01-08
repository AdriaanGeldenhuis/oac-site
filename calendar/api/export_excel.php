<?php
declare(strict_types=1);
require_once __DIR__ . '/../../security/auth_gate.php';
require_once __DIR__ . '/../../includes/languages.php';

$userLang = $_SESSION['language'] ?? 'af';
$meId = (int)($_SESSION['user_id'] ?? 0);
$targetId = isset($_POST['user_id']) && is_numeric($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$view = trim($_POST['view'] ?? 'month');
$date = trim($_POST['date'] ?? date('Y-m-d'));

if (!$targetId) {
    http_response_code(400);
    die('Invalid user_id');
}

// Check permissions
$stmt = $pdo->prepare("SELECT amp_id FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$meId]);
$me = $stmt->fetch(PDO::FETCH_ASSOC);

$myAmpId = (int)($me['amp_id'] ?? 10);

if ($myAmpId >= 10) {
    http_response_code(403);
    die('Insufficient permissions');
}

// Get user info
$stmt = $pdo->prepare("SELECT name, surname FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$targetId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(404);
    die('User not found');
}

$fullName = trim($user['name'] . ' ' . $user['surname']);

// Fetch events
$stmt = $pdo->prepare("
    SELECT 
        ce.title,
        ce.start_at AS datetime,
        ce.description AS notes,
        'event' AS type
    FROM calendar_events ce
    WHERE ce.user_id = ?
    UNION ALL
    SELECT 
        d.title,
        CONCAT(d.date, ' ', COALESCE(d.time, '00:00:00')) AS datetime,
        d.body AS notes,
        'diary' AS type
    FROM diaries d
    WHERE d.user_id = ?
    UNION ALL
    SELECT 
        cv.title,
        CONCAT(cv.visit_date, ' ', COALESCE(cv.visit_time, '00:00:00')) AS datetime,
        cv.notes,
        'visit' AS type
    FROM calendar_visits cv
    WHERE cv.user_id = ?
    ORDER BY datetime ASC
");
$stmt->execute([$targetId, $targetId, $targetId]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Generate CSV (Excel can open this)
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="calendar_' . $targetId . '_' . time() . '.csv"');

// Translation labels
$dateLabel = __t('date', $userLang);
$timeLabel = __t('time', $userLang);
$titleLabel = __t('title', $userLang);
$typeLabel = __t('type', $userLang);
$notesLabel = __t('notes', $userLang);

// Type translations
$typeMap = [
    'event' => __t('event', $userLang),
    'diary' => __t('diary', $userLang),
    'visit' => __t('appointment', $userLang)
];

// UTF-8 BOM for Excel
echo "\xEF\xBB\xBF";

// Headers
echo "$dateLabel/$timeLabel,$titleLabel,$typeLabel,$notesLabel\n";

// Data
foreach ($events as $event) {
    $datetime = date('Y-m-d H:i', strtotime($event['datetime']));
    $title = str_replace('"', '""', $event['title']);
    $type = $typeMap[$event['type']] ?? ucfirst($event['type']);
    $notes = str_replace('"', '""', $event['notes'] ?? '');

    echo "\"$datetime\",\"$title\",\"$type\",\"$notes\"\n";
}