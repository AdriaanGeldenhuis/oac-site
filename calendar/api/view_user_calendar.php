<?php
declare(strict_types=1);
/**
 * GET /calendar/api/view_user_calendar.php?user_id=N[&from=&to=]
 * Someone else's calendar, for the people cal_view_rights() allows (the
 * rule view.php always used). Visits and appointments with title, time
 * and place only: never diary entries, never notes.
 * Answer: a JSON array (empty, with 403, when not allowed)
 */

require_once __DIR__ . '/../../security/auth_gate.php';
require_once __DIR__ . '/../lib/calendar.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    cal_json([], 500);
}

$me = cal_user($pdo, (int)$_SESSION['user_id']);
$target = cal_user($pdo, (int)($_GET['user_id'] ?? 0));
if (!$me || !$target || !cal_view_rights($me, $target)['view']) {
    cal_json([], 403);
}

[$from, $to] = cal_range($_GET);

try {
    cal_json(cal_items_for_viewer($pdo, $target, (int)$me['id'], $from, $to, cal_lang()));
} catch (Throwable $e) {
    error_log('View calendar error: ' . $e->getMessage());
    cal_json([], 500);
}
