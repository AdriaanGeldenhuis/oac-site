<?php
declare(strict_types=1);
/**
 * GET /profile/api/export_calendar.php?user_id=N
 * A person's calendar as an .ics file, for those allowed to export it
 * (the calendar's own rule: cal_view_rights()). The same content as the
 * calendar shows them: visits and appointments, never diary entries.
 */
require_once __DIR__ . '/../../security/auth_gate.php';
require_once __DIR__ . '/../../calendar/lib/ics.php';

$me = cal_user($pdo, (int)($_SESSION['user_id'] ?? 0));
$target = cal_user($pdo, isset($_GET['user_id']) && is_numeric($_GET['user_id']) ? (int)$_GET['user_id'] : 0);

if (!$target) {
    http_response_code(400);
    die('Invalid user_id');
}
if (!$me || !cal_view_rights($me, $target)['export']) {
    http_response_code(403);
    die('Insufficient permissions');
}

try {
    $items = cal_items_for_viewer($pdo, $target, (int)$me['id'], null, null, cal_lang());
} catch (Throwable $e) {
    error_log('Profile calendar export error: ' . $e->getMessage());
    http_response_code(500);
    die('Export failed');
}

$name = cal_name($target['name'] ?? '', $target['surname'] ?? '');
cal_send_ics($items, 'kalender-' . $name, __t('calendar', cal_lang()) . ' - ' . $name);
