<?php
declare(strict_types=1);
/**
 * POST /prayers/api/posts/create.php  (multipart: text, photo?; X-CSRF-Token)
 * Shares a prayer. The writer's town is stored with it. Answers with the
 * new card. At most 10 prayers an hour per member.
 */

require_once dirname(__DIR__, 3) . '/security/auth_gate.php';
require_once dirname(__DIR__, 2) . '/lib/prayers.php';

pr_require_post();
pr_require_body();
if (!isset($pdo) || !($pdo instanceof PDO)) {
    pr_fail('db_unavailable', 500);
}

[$text, $error] = pr_text_from($_POST['text'] ?? '', PR_TEXT_MAX);
if ($error !== null) pr_fail($error);

if (!pr_rate_ok('prayer_post', 10)) pr_fail('rate_limited', 429);

[$photoUrl, $photoError] = pr_photo_from_upload();
if ($photoError !== null) pr_fail($photoError);

try {
    $me = pr_me($pdo);
    $stmt = $pdo->prepare("INSERT INTO prayers_posts (user_id, town_id, kind, text, photo_url, visibility) VALUES (?, ?, 'prayer', ?, ?, 'opsienerskap')");
    $stmt->execute([pr_user_id(), (int)($me['town_id'] ?? 0), $text, $photoUrl]);
    $id = (int)$pdo->lastInsertId();

    pr_json(['success' => true, 'post' => pr_post_by_id($pdo, $id, pr_lang())]);
} catch (Throwable $e) {
    pr_photo_delete($photoUrl);
    error_log('Prayers create error: ' . $e->getMessage());
    pr_fail('server_error', 500);
}
