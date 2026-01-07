<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 3) . '/security/auth_gate.php';
require_once dirname(__DIR__, 3) . '/includes/languages.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['error' => 'db_unavailable']);
    exit;
}

$pageLang = $_SESSION['language'] ?? 'af';

// Helper: Fix photo path
function fix_photo_path(?string $photo): ?string {
    if (!$photo) return null;
    if (strpos($photo, 'http') === 0) return $photo;
    if (strpos($photo, '/') === 0) return $photo;
    return '/assets/uploads/' . $photo;
}

$userId = (int)$_SESSION['user_id'];
$roomId = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
$limit = max(1, min((int)($_GET['limit'] ?? 20), 50));
$afterId = isset($_GET['after_id']) ? (int)$_GET['after_id'] : null;

if ($roomId <= 0) {
    echo json_encode([]);
    exit;
}

// Check access
require_once dirname(__DIR__, 2) . '/lib/permissions.php';
$st = $pdo->prepare("SELECT * FROM rooms WHERE id=? LIMIT 1");
$st->execute([$roomId]);
$room = $st->fetch(PDO::FETCH_ASSOC);

if (!$room || !user_has_access_to_room($pdo, $userId, $room)) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

try {
    $sql = "SELECT p.*,
                   u.name AS user_name,
                   u.surname AS user_surname,
                   u.gender AS user_gender,
                   u.amp_id AS user_amp_id,
                   u.photo AS user_photo,
                   p.heart_count,
                   p.pray_count,
                   p.comment_count
            FROM posts p
            JOIN users u ON u.id = p.user_id
            WHERE p.room_id = :rid";
    
    if ($afterId !== null && $afterId > 0) {
        $sql .= " AND p.id < :after";
    }
    
    $sql .= " ORDER BY p.id DESC LIMIT :lim";
    
    $st = $pdo->prepare($sql);
    $st->bindValue(':rid', $roomId, PDO::PARAM_INT);
    if ($afterId !== null && $afterId > 0) {
        $st->bindValue(':after', $afterId, PDO::PARAM_INT);
    }
    $st->bindValue(':lim', $limit, PDO::PARAM_INT);
    $st->execute();
    
    $posts = $st->fetchAll(PDO::FETCH_ASSOC);
    
    if (!$posts) {
        echo json_encode([]);
        exit;
    }
    
    // Get attachments
    $ids = array_map(function($r) { return (int)$r['id']; }, $posts);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    $att = $pdo->prepare("SELECT post_id, path_thumb, path_original, mime, width, height 
                          FROM attachments 
                          WHERE post_id IN ($placeholders)
                          ORDER BY id ASC");
    $att->execute($ids);
    
    $attachmentsByPost = [];
    while ($a = $att->fetch(PDO::FETCH_ASSOC)) {
        $pid = (int)$a['post_id'];
        if (!isset($attachmentsByPost[$pid])) {
            $attachmentsByPost[$pid] = [];
        }
        $attachmentsByPost[$pid][] = [
            'path_thumb' => $a['path_thumb'] ?: $a['path_original'],
            'path_original' => $a['path_original'],
            'mime' => $a['mime'],
            'width' => (int)($a['width'] ?? 0),
            'height' => (int)($a['height'] ?? 0)
        ];
    }
    
    // Get user's reactions
    $reactStmt = $pdo->prepare("SELECT post_id, type 
                                FROM reactions 
                                WHERE post_id IN ($placeholders) AND user_id = ?");
    $reactStmt->execute(array_merge($ids, [$userId]));
    
    $myReactions = [];
    while ($r = $reactStmt->fetch(PDO::FETCH_ASSOC)) {
        $pid = (int)$r['post_id'];
        if (!isset($myReactions[$pid])) {
            $myReactions[$pid] = [];
        }
        $myReactions[$pid][] = $r['type'];
    }
    
    // Enhance posts
    foreach ($posts as &$p) {
        $pid = (int)$p['id'];
        $p['attachments'] = $attachmentsByPost[$pid] ?? [];
        $p['my_reactions'] = $myReactions[$pid] ?? [];

        // FIX PHOTO PATH
        $p['user_photo'] = fix_photo_path($p['user_photo']);

        // Translate office title
        $ampId = (int)($p['user_amp_id'] ?? 10);
        $gender = $p['user_gender'] ?? 'man';
        $p['amp_title'] = get_translated_office($ampId, $gender, $pageLang);

        // Check if current user owns this post
        $p['is_owner'] = ((int)$p['user_id'] === $userId);

        $p['heart_count'] = (int)($p['heart_count'] ?? 0);
        $p['pray_count'] = (int)($p['pray_count'] ?? 0);
        $p['comment_count'] = (int)($p['comment_count'] ?? 0);
    }
    unset($p);
    
    echo json_encode($posts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log("Gospel posts list error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error', 'message' => $e->getMessage()]);
}