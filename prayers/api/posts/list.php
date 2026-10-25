<?php
declare(strict_types=1);
/**
 * GET /prayers/api/posts/list.php?filter=all|town|mine&before=ID
 * GET /prayers/api/posts/list.php?id=ID   (one prayer, for a notification link)
 * Prayers, newest first, PR_PAGE at a time, with counts and what I may do.
 * "town" means prayers written by members of my own town.
 */

require_once dirname(__DIR__, 3) . '/security/auth_gate.php';
require_once dirname(__DIR__, 2) . '/lib/prayers.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    pr_fail('db_unavailable', 500);
}

$lang = pr_lang();
$me = pr_user_id();

try {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $post = pr_post_by_id($pdo, $id, $lang);
        if (!$post) pr_fail('not_found', 404);
        pr_json(['success' => true, 'posts' => [$post], 'has_more' => false]);
    }

    $sql = pr_post_select();
    $params = [$me, $me];

    $filter = (string)($_GET['filter'] ?? 'all');
    if ($filter === 'mine') {
        $sql .= ' AND p.user_id = ?';
        $params[] = $me;
    } elseif ($filter === 'town') {
        $town = (int)(pr_me($pdo)['town_id'] ?? 0);
        if ($town > 0) {
            $sql .= ' AND u.town_id = ?';
            $params[] = $town;
        }
    }

    $before = (int)($_GET['before'] ?? 0);
    if ($before > 0) {
        $sql .= ' AND p.id < ?';
        $params[] = $before;
    }

    // LIMIT is an integer built here, never user text
    $sql .= ' ORDER BY p.id DESC LIMIT ' . (PR_PAGE + 1);
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $hasMore = count($rows) > PR_PAGE;
    $rows = array_slice($rows, 0, PR_PAGE);

    pr_json([
        'success' => true,
        'posts' => array_map(fn($r) => pr_post_out($r, $pdo, $lang), $rows),
        'has_more' => $hasMore,
    ]);
} catch (Throwable $e) {
    error_log('Prayers list error: ' . $e->getMessage());
    pr_fail('server_error', 500);
}
