<?php
declare(strict_types=1);
/**
 * POST /prayers/api/comments/update.php  {"comment_id": N, "text": "..."}  (X-CSRF-Token)
 * The writer edits their own comment; answers with the comment as saved.
 */

require_once dirname(__DIR__, 3) . '/security/auth_gate.php';
require_once dirname(__DIR__, 2) . '/lib/prayers.php';

pr_require_post();
if (!isset($pdo) || !($pdo instanceof PDO)) {
    pr_fail('db_unavailable', 500);
}

$in = pr_input();
$commentId = (int)($in['comment_id'] ?? 0);
[$text, $error] = pr_text_from($in['text'] ?? '', PR_COMMENT_MAX);
if ($commentId <= 0) pr_fail('invalid_input');
if ($error !== null) pr_fail($error);

try {
    $st = $pdo->prepare('SELECT user_id FROM prayers_comments WHERE id = ? LIMIT 1');
    $st->execute([$commentId]);
    $owner = $st->fetchColumn();
    if ($owner === false) pr_fail('not_found', 404);
    if ((int)$owner !== pr_user_id()) pr_fail('forbidden', 403);

    $pdo->prepare('UPDATE prayers_comments SET text = ? WHERE id = ? AND user_id = ?')->execute([$text, $commentId, pr_user_id()]);

    $st = $pdo->prepare(pr_comment_select() . ' WHERE c.id = ? LIMIT 1');
    $st->execute([$commentId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    pr_json(['success' => true, 'comment' => $row ? pr_comment_out($row, $pdo, pr_lang()) : null]);
} catch (Throwable $e) {
    error_log('Prayers comment update error: ' . $e->getMessage());
    pr_fail('server_error', 500);
}
