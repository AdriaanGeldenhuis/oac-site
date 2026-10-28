<?php
declare(strict_types=1);
/**
 * GET /admin/api/gedagte/list.php — the thoughts of the last 7 days and every
 * coming one, newest first, with their translations (elders, amp 1-5: a
 * coming thought is not for members' eyes before its time).
 */

require_once dirname(__DIR__, 3) . '/security/config.php';
require_once dirname(__DIR__, 3) . '/security/session.php';
require_once dirname(__DIR__, 3) . '/security/auth.php';
require_once dirname(__DIR__, 3) . '/includes/admin_api.php';
require_once dirname(__DIR__, 3) . '/includes/daily_thoughts.php';

ob_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

dt_admin_guard($pdo);

try {
    $since = dt_now()->modify('-7 days')->format('Y-m-d');
    $ids = dt_run($pdo, 'SELECT id FROM daily_thoughts WHERE display_date >= ? ORDER BY display_date DESC LIMIT 30', [$since])
        ->fetchAll(PDO::FETCH_COLUMN, 0);

    $thoughts = [];
    foreach ($ids as $id) {
        $t = dt_load($pdo, (int)$id);
        if (!$t) continue;
        $thoughts[] = [
            'id' => $t['id'],
            'content' => $t['content'],
            'author' => $t['author'],
            'lang' => $t['lang'],
            'display_date' => $t['display_date'],
            'display_time' => $t['display_time'],
            'notification_sent' => $t['notification_sent'],
            'translations' => (object)$t['translations'],
            'missing' => dt_missing_langs($t),
        ];
    }

    admin_api_json(['success' => true, 'ai' => dt_ai_ready(), 'thoughts' => $thoughts]);
} catch (Throwable $e) {
    admin_api_server_error($e, 'gedagte/list');
}
