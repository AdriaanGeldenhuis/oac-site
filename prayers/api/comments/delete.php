<?php
declare(strict_types=1);
/**
 * POST /prayers/api/comments/delete.php  {"comment_id": N}  (X-CSRF-Token)
 * The writer, or an officer (amp 1-6), deletes a comment.
 */

require_once dirname(__DIR__, 3) . '/security/auth_gate.php';
require_once dirname(__DIR__, 2) . '/lib/prayers.php';

pr_require_post();
if (!isset($pdo) || !($pdo instanceof PDO)) {
    pr_fail('db_unavailable', 500);
}

$in = pr_input();
$commentId = (int)($in['comment_id'] ?? 0);
if ($commentId <= 0) pr_fail('invalid_input');

try {
    $st = $pdo->prepare('SELECT user_id FROM prayers_comments WHERE id = ? LIMIT 1');
    $st->execute([$commentId]);
    $owner = $st->fetchColumn();
    if ($owner === false) pr_fail('not_found', 404);
    if ((int)$owner !== pr_user_id() && !pr_is_moderator($pdo)) pr_fail('forbidden', 403);

    $pdo->prepare('DELETE FROM prayers_comments WHERE id = ?')->execute([$commentId]);
    pr_json(['success' => true]);
} catch (Throwable $e) {
    error_log('Prayers comment delete error: ' . $e->getMessage());
    pr_fail('server_error', 500);
}
