<?php
declare(strict_types=1);
/**
 * Retired: no page and no app version calls this any more.
 * It showed someone's calendar_events under its own view rule.
 * Live path: /calendar/view.php and /calendar/api/view_user_calendar.php (cal_view_rights() in calendar/lib/calendar.php)
 *
 * The file stays as this stub on purpose. Deploys copy files but never
 * delete them on the server, so the old version would otherwise stay
 * reachable there, with an older permission rule than the live path.
 */

http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(['success' => false, 'error' => 'gone']);
