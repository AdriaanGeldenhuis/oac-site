<?php
declare(strict_types=1);
/**
 * GET /prayers/api/comments/list.php?post_id=N
 * The comments on a prayer, oldest first (a conversation), with what I may do.
 */

require_once dirname(__DIR__, 3) . '/security/auth_gate.php';
require_once dirname(__DIR__, 2) . '/lib/prayers.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    pr_fail('db_unavailable', 500);
}

$postId = (int)($_GET['post_id'] ?? 0);
if (!pr_post_row($pdo, $postId)) pr_fail('not_found', 404);

try {
    $lang = pr_lang();
    $st = $pdo->prepare(pr_comment_select() . ' WHERE c.post_id = ? ORDER BY c.id ASC LIMIT 500');
    $st->execute([$postId]);
    pr_json([
        'success' => true,
        'comments' => array_map(fn($r) => pr_comment_out($r, $pdo, $lang), $st->fetchAll(PDO::FETCH_ASSOC)),
    ]);
} catch (Throwable $e) {
    error_log('Prayers comment list error: ' . $e->getMessage());
    pr_fail('server_error', 500);
}
