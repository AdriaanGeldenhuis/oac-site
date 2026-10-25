<?php
declare(strict_types=1);
/**
 * POST /prayers/api/comments/create.php  {"post_id": N, "text": "..."}  (X-CSRF-Token)
 * Adds an encouraging word to a prayer and answers with the new comment.
 * The writer of the prayer is told after the answer has gone.
 * At most 40 comments an hour per member.
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
[$text, $error] = pr_text_from($in['text'] ?? '', PR_COMMENT_MAX);
if ($postId <= 0) pr_fail('invalid_input');
if ($error !== null) pr_fail($error);

$post = pr_post_row($pdo, $postId);
if (!$post) pr_fail('not_found', 404);
if (!pr_rate_ok('prayer_comment', 40)) pr_fail('rate_limited', 429);

try {
    $pdo->prepare('INSERT INTO prayers_comments (post_id, user_id, text) VALUES (?, ?, ?)')->execute([$postId, pr_user_id(), $text]);
    $id = (int)$pdo->lastInsertId();
    $st = $pdo->prepare(pr_comment_select() . ' WHERE c.id = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('Prayers comment create error: ' . $e->getMessage());
    pr_fail('server_error', 500);
}

$response = ['success' => true, 'comment' => $row ? pr_comment_out($row, $pdo, pr_lang()) : null];
$ownerId = (int)$post['user_id'];
if ($ownerId === pr_user_id()) pr_json($response);

// Answer first, then tell the writer of the prayer
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

createPrayerNotification($pdo, $ownerId, 'comment_on_post', [
    'commenter_name' => pr_full_name(pr_me($pdo)),
    'post_id' => $postId,
]);
exit;
