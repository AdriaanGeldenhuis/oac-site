<?php
declare(strict_types=1);
/**
 * POST /prayers/api/posts/react.php  {"post_id": N, "type": "pray"|"heart"}  (X-CSRF-Token)
 * Switches my "I pray along" / heart on or off and answers with both counts.
 * The prayer row is locked while toggling, so a double tap never counts twice.
 * The writer is told once (not on every off/on), after the answer has gone.
 */

require_once dirname(__DIR__, 3) . '/security/auth_gate.php';
require_once dirname(__DIR__, 2) . '/lib/prayers.php';
require_once dirname(__DIR__) . '/notifications/helper.php';

pr_require_post();
if (!isset($pdo) || !($pdo instanceof PDO)) {
    pr_fail('db_unavailable', 500);
}

$in = pr_input();
$postId = (int)($in['post_id'] ?? 0);
$type = (string)($in['type'] ?? '');
if ($postId <= 0 || !in_array($type, ['heart', 'pray'], true)) pr_fail('invalid_input');

$me = pr_user_id();
try {
    $pdo->beginTransaction();
    $post = pr_post_row($pdo, $postId, true);
    if (!$post) {
        $pdo->rollBack();
        pr_fail('not_found', 404);
    }

    $st = $pdo->prepare('SELECT id FROM prayers_reactions WHERE post_id = ? AND user_id = ? AND type = ?');
    $st->execute([$postId, $me, $type]);
    $had = (bool)$st->fetchColumn();

    if ($had) {
        $pdo->prepare('DELETE FROM prayers_reactions WHERE post_id = ? AND user_id = ? AND type = ?')->execute([$postId, $me, $type]);
    } else {
        $pdo->prepare('INSERT INTO prayers_reactions (post_id, user_id, type) VALUES (?, ?, ?)')->execute([$postId, $me, $type]);
    }

    $counts = $pdo->prepare("SELECT
        SUM(type = 'pray') AS pray_count, SUM(type = 'heart') AS heart_count,
        SUM(type = 'pray' AND user_id = ?) AS prayed, SUM(type = 'heart' AND user_id = ?) AS hearted
        FROM prayers_reactions WHERE post_id = ?");
    $counts->execute([$me, $me, $postId]);
    $c = $counts->fetch(PDO::FETCH_ASSOC) ?: [];
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Prayers react error: ' . $e->getMessage());
    pr_fail('server_error', 500);
}

$response = [
    'success' => true,
    'active' => !$had,
    'pray_count' => (int)($c['pray_count'] ?? 0),
    'heart_count' => (int)($c['heart_count'] ?? 0),
    'prayed' => (int)($c['prayed'] ?? 0) > 0,
    'hearted' => (int)($c['hearted'] ?? 0) > 0,
];

// Answer first, so the tap feels instant; then tell the writer
$ownerId = (int)$post['user_id'];
$notify = !$had && $ownerId !== $me;
if (!$notify) pr_json($response);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
echo json_encode($response, JSON_UNESCAPED_UNICODE);
ignore_user_abort(true);
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    if (ob_get_level() > 0) ob_end_flush();
    flush();
}

// Once an hour per person, prayer and kind: switching off and on again is no news
if (pr_rate_ok('prayer_notify_' . $type . '_' . $postId, 1)) {
    createPrayerNotification($pdo, $ownerId, 'reaction_on_post', [
        'reactor_name' => pr_full_name(pr_me($pdo)),
        'reaction_type' => $type,
        'post_id' => $postId,
    ]);
}
exit;
