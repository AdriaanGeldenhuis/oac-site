<?php
declare(strict_types=1);
/**
 * POST /prayers/api/posts/update.php  (multipart: post_id, text, photo?, remove_photo?; X-CSRF-Token)
 * The writer edits their own prayer: the text, a new photo, or no photo.
 * The old photo file is only removed once the change is saved.
 */

require_once dirname(__DIR__, 3) . '/security/auth_gate.php';
require_once dirname(__DIR__, 2) . '/lib/prayers.php';

pr_require_post();
if (!isset($pdo) || !($pdo instanceof PDO)) {
    pr_fail('db_unavailable', 500);
}

$postId = (int)($_POST['post_id'] ?? 0);
[$text, $error] = pr_text_from($_POST['text'] ?? '', PR_TEXT_MAX);
if ($postId <= 0) pr_fail('invalid_input');
if ($error !== null) pr_fail($error);
$removePhoto = in_array((string)($_POST['remove_photo'] ?? ''), ['1', 'true'], true);

$post = pr_post_row($pdo, $postId);
if (!$post) pr_fail('not_found', 404);
if ((int)$post['user_id'] !== pr_user_id()) pr_fail('forbidden', 403);

[$newPhoto, $photoError] = pr_photo_from_upload();
if ($photoError !== null) pr_fail($photoError);

$oldPhoto = $post['photo_url'] ?? null;
$photo = $newPhoto ?? ($removePhoto ? null : $oldPhoto);

try {
    $stmt = $pdo->prepare('UPDATE prayers_posts SET text = ?, photo_url = ?, updated_at = NOW() WHERE id = ? AND user_id = ?');
    $stmt->execute([$text, $photo, $postId, pr_user_id()]);
} catch (Throwable $e) {
    pr_photo_delete($newPhoto);
    error_log('Prayers update error: ' . $e->getMessage());
    pr_fail('server_error', 500);
}

// Saved: the replaced / removed photo can go
if ($oldPhoto && $oldPhoto !== $photo) {
    pr_photo_delete($oldPhoto);
}

pr_json(['success' => true, 'post' => pr_post_by_id($pdo, $postId, pr_lang())]);
