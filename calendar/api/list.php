<?php
declare(strict_types=1);
/**
 * GET /calendar/api/list.php[?from=YYYY-MM-DD&to=YYYY-MM-DD]
 *
 * The signed-in user's own calendar as a JSON array (the shape welcome.js
 * reads: type, date "YYYY-MM-DD", time "HH:MM" or null, title, location,
 * person_name). Types: event (room events the user can see in Gospel
 * Media), visit, appointment (Ampte), diary (own only), birthday (only when
 * a window is asked for). Without from/to the whole history is returned.
 * Optional: types=event,visit (only these) and limit=N (the first N).
 */

require_once __DIR__ . '/../../security/auth_gate.php';
require_once __DIR__ . '/../lib/calendar.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    cal_json([], 500);
}

$me = cal_user($pdo, (int)$_SESSION['user_id']);
if (!$me) {
    cal_json([]);
}

[$from, $to] = cal_range($_GET);

$types = array_values(array_intersect(
    explode(',', (string)($_GET['types'] ?? '')),
    ['event', 'visit', 'appointment', 'diary', 'birthday']
));
$limit = max(0, min(500, (int)($_GET['limit'] ?? 0)));

try {
    $items = cal_items_for_owner($pdo, $me, $from, $to, cal_lang());
    if ($types) {
        $items = array_values(array_filter($items, fn($it) => in_array($it['type'], $types, true)));
    }
    if ($limit > 0) {
        $items = array_slice($items, 0, $limit);
    }
    cal_json($items);
} catch (Throwable $e) {
    error_log('Calendar list error: ' . $e->getMessage());
    cal_json([], 500);
}
