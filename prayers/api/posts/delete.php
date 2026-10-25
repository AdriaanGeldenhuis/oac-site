<?php
declare(strict_types=1);
/**
 * POST /prayers/api/posts/delete.php  {"post_id": N}  (X-CSRF-Token)
 * The writer, or an officer (amp 1-6), deletes a prayer with its comments
 * and reactions, in one transaction; the photo file goes afterwards.
 */

require_once dirname(__DIR__, 3) . '/security/auth_gate.php';
require_once dirname(__DIR__, 2) . '/lib/prayers.php';

pr_require_post();
if (!isset($pdo) || !($pdo instanceof PDO)) {
    pr_fail('db_unavailable', 500);
}

$in = pr_input();
$postId = (int)($in['post_id'] ?? 0);
if ($postId <= 0) pr_fail('invalid_input');

try {
    $pdo->beginTransaction();
    $post = pr_post_row($pdo, $postId, true);
    if (!$post) {
        $pdo->rollBack();
        pr_fail('not_found', 404);
    }
    if ((int)$post['user_id'] !== pr_user_id() && !pr_is_moderator($pdo)) {
        $pdo->rollBack();
        pr_fail('forbidden', 403);
    }

    $pdo->prepare('DELETE FROM prayers_comments WHERE post_id = ?')->execute([$postId]);
    $pdo->prepare('DELETE FROM prayers_reactions WHERE post_id = ?')->execute([$postId]);
    $pdo->prepare('DELETE FROM prayers_posts WHERE id = ?')->execute([$postId]);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Prayers delete error: ' . $e->getMessage());
    pr_fail('server_error', 500);
}

pr_photo_delete($post['photo_url'] ?? null);
pr_json(['success' => true]);
