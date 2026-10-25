<?php
declare(strict_types=1);
/**
 * GET /prayers/api/posts/reactions.php?post_id=N
 * Who prays along with a prayer and who sent a heart, newest first.
 * Names only (with office), as the counts on the card are open to all.
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
    $st = $pdo->prepare('SELECT r.type, r.user_id, u.name, u.surname, u.amp_id, u.gender, u.photo AS user_photo
                         FROM prayers_reactions r JOIN users u ON u.id = r.user_id
                         WHERE r.post_id = ? ORDER BY r.id DESC LIMIT 500');
    $st->execute([$postId]);
    $out = ['pray' => [], 'heart' => []];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $type = $r['type'] === 'heart' ? 'heart' : 'pray';
        $out[$type][] = [
            'name' => pr_display_name($r, $lang),
            'initials' => pr_initials(pr_full_name($r)),
            'photo' => pr_photo_url($r['user_photo'] ?? null),
            'me' => (int)$r['user_id'] === pr_user_id(),
        ];
    }
    pr_json(['success' => true] + $out);
} catch (Throwable $e) {
    error_log('Prayers reactions error: ' . $e->getMessage());
    pr_fail('server_error', 500);
}
