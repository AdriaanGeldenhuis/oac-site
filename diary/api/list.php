<?php
declare(strict_types=1);
/**
 * GET /diary/api/list.php?q=&period=week|month|year&tag=&sort=newest|oldest&offset=N
 * My entries, newest first, DY_PAGE at a time, with an excerpt of the text
 * (the whole entry comes from get.php). The first page also brings the
 * entries written on this day in earlier years and my tags.
 */

require_once dirname(__DIR__, 2) . '/security/auth_gate.php';
require_once dirname(__DIR__) . '/lib/diary.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    dy_fail('db_unavailable', 500);
}

$userId = dy_user_id();
$offset = max(0, min(100000, (int)($_GET['offset'] ?? 0)));
[$where, $params] = dy_where($userId, $_GET);
$filtered = count($params) > 1;

try {
    $count = $pdo->prepare('SELECT COUNT(*) FROM diaries WHERE ' . $where);
    $count->execute($params);
    $total = (int)$count->fetchColumn();

    // LIMIT/OFFSET are integers built here, never user text
    $st = $pdo->prepare('SELECT ' . DY_COLUMNS . ' FROM diaries WHERE ' . $where
        . ' ORDER BY ' . dy_order($_GET) . ' LIMIT ' . (DY_PAGE + 1) . ' OFFSET ' . $offset);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $hasMore = count($rows) > DY_PAGE;
    $entries = array_map(fn($r) => dy_entry_out($r, false), array_slice($rows, 0, DY_PAGE));

    $out = [
        'success' => true,
        'entries' => $entries,
        'total' => $total,
        'offset' => $offset,
        'has_more' => $hasMore,
    ];

    if ($offset === 0) {
        // On this day, in earlier years (only on the plain first page)
        $out['on_this_day'] = [];
        if (!$filtered) {
            $now = dy_now();
            $otd = $pdo->prepare('SELECT ' . DY_COLUMNS . ' FROM diaries
                                  WHERE user_id = ? AND MONTH(date) = ? AND DAYOFMONTH(date) = ? AND date < ?
                                  ORDER BY date DESC, time DESC LIMIT 5');
            $otd->execute([$userId, (int)$now->format('n'), (int)$now->format('j'), $now->format('Y-01-01')]);
            $out['on_this_day'] = array_map(fn($r) => dy_entry_out($r, false), $otd->fetchAll(PDO::FETCH_ASSOC));
        }

        // My tags, most used first (suggestions while writing, filter chips)
        $tagSt = $pdo->prepare("SELECT tags FROM diaries WHERE user_id = ? AND tags IS NOT NULL AND tags <> ''");
        $tagSt->execute([$userId]);
        $counts = [];
        $labels = [];
        foreach ($tagSt->fetchAll(PDO::FETCH_COLUMN) as $json) {
            foreach (dy_tags_decode($json) as $tag) {
                $key = mb_strtolower($tag, 'UTF-8');
                $counts[$key] = ($counts[$key] ?? 0) + 1;
                $labels[$key] = $labels[$key] ?? $tag;
            }
        }
        arsort($counts);
        $out['tags'] = [];
        foreach (array_slice($counts, 0, 40, true) as $key => $n) {
            $out['tags'][] = ['tag' => $labels[$key], 'count' => $n];
        }
    }

    dy_json($out);
} catch (Throwable $e) {
    error_log('Diary list error: ' . $e->getMessage());
    dy_fail('server_error', 500);
}
