<?php
// =====================================================================
// /welcome/api/verse.php — Verse of the Day (GET, read-only)
// ?offset=0   today
// ?offset=-1  yesterday ... down to -30
// =====================================================================
declare(strict_types=1);

require_once __DIR__ . '/../../security/auth_gate.php';
require_once __DIR__ . '/../../includes/verse_of_day.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$offset = filter_input(INPUT_GET, 'offset', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => -VOTD_MAX_BACK, 'max_range' => 0, 'default' => 0],
]);
if ($offset === false || $offset === null) {
    $offset = 0;
}

$lang = validate_language((string)($_SESSION['language'] ?? 'af'));
$verse = votd_for_offset((int)$offset, $lang);

if ($verse === null) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Verse data unavailable']);
    exit;
}

echo json_encode(['success' => true] + $verse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
