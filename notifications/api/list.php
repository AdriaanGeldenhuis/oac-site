<?php
declare(strict_types=1);
/**
 * GET /notifications/api/list.php?filter=all|unread|prayer|gospel|calendar|thought|other&before=ID
 * My notifications, newest first, NT_PAGE at a time, in my language.
 * ?before= continues the list below the last id shown. The first page
 * also brings the counts for the filter chips.
 */

require_once dirname(__DIR__, 2) . '/security/auth_gate.php';
require_once dirname(__DIR__) . '/lib/notifications.php';

$userId = nt_user_id();
$lang = nt_lang();
$filter = (string)($_GET['filter'] ?? 'all');
$before = max(0, (int)($_GET['before'] ?? 0));

try {
    $db = nt_db();
    [$cond, $params] = nt_filter_sql($filter);
    $sql = 'SELECT * FROM notifications WHERE user_id = ? AND ' . $cond . ($before > 0 ? ' AND id < ?' : '')
         . ' ORDER BY id DESC LIMIT ' . (NT_PAGE + 1);
    $args = array_merge([$userId], $params, $before > 0 ? [$before] : []);
    $st = $db->prepare($sql);
    $st->execute($args);
    $rows = $st->fetchAll();

    $hasMore = count($rows) > NT_PAGE;
    $rows = array_slice($rows, 0, NT_PAGE);
    $out = [
        'success' => true,
        'notifications' => array_map(fn($r) => nt_out($r, $lang), $rows),
        'has_more' => $hasMore,
        'now' => time(),
    ];
    if ($before === 0) {
        $out['counts'] = nt_counts($db, $userId);
    }
    nt_json($out);
} catch (Throwable $e) {
    error_log('Notifications list error: ' . $e->getMessage());
    nt_fail('server_error', 500);
}
