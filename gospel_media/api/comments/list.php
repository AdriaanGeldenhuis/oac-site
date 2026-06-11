<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 3) . '/security/auth_gate.php';
require_once dirname(__DIR__, 3) . '/includes/languages.php';
require_once dirname(__DIR__, 2) . '/lib/permissions.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['error' => 'db_unavailable']);
    exit;
}

$pageLang = $_SESSION['language'] ?? 'af';
$userId = (int)($_SESSION['user_id'] ?? 0);
$postId = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;

if ($postId <= 0) {
    echo json_encode([]);
    exit;
}

try {
    // Only users with access to the post's room may read its comments
    $roomStmt = $pdo->prepare("SELECT r.* FROM rooms r JOIN posts p ON p.room_id = r.id WHERE p.id = ? LIMIT 1");
    $roomStmt->execute([$postId]);
    $room = $roomStmt->fetch(PDO::FETCH_ASSOC);

    if (!$room || !user_has_access_to_room($pdo, $userId, $room)) {
        http_response_code(403);
        echo json_encode(['error' => 'forbidden']);
        exit;
    }

    // Include heart counts and whether the current user hearted each comment.
    // Falls back to the plain query if the comment_reactions table is missing.
    try {
        $sql = "SELECT c.*, u.name, u.surname, u.photo, u.gender, u.amp_id,
                       (SELECT COUNT(*) FROM comment_reactions cr
                         WHERE cr.comment_id = c.id AND cr.type = 'heart') AS heart_count,
                       EXISTS(SELECT 1 FROM comment_reactions cr2
                         WHERE cr2.comment_id = c.id AND cr2.user_id = ? AND cr2.type = 'heart') AS my_heart
                FROM comments c
                JOIN users u ON u.id = c.user_id
                WHERE c.post_id = ?
                ORDER BY c.created_at ASC";

        $st = $pdo->prepare($sql);
        $st->execute([$userId, $postId]);
        $comments = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $sql = "SELECT c.*, u.name, u.surname, u.photo, u.gender, u.amp_id,
                       0 AS heart_count, 0 AS my_heart
                FROM comments c
                JOIN users u ON u.id = c.user_id
                WHERE c.post_id = ?
                ORDER BY c.created_at ASC";

        $st = $pdo->prepare($sql);
        $st->execute([$postId]);
        $comments = $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // Fix photo paths and translate office titles
    foreach ($comments as &$c) {
        if (isset($c['photo']) && $c['photo']) {
            $photo = $c['photo'];
            if (strpos($photo, '/') !== 0 && strpos($photo, 'http') !== 0) {
                $c['photo'] = '/assets/uploads/' . $photo;
            }
        }

        // Translate office title
        $ampId = (int)($c['amp_id'] ?? 10);
        $gender = $c['gender'] ?? 'man';
        $c['amp_title'] = get_translated_office($ampId, $gender, $pageLang);

        $c['heart_count'] = (int)($c['heart_count'] ?? 0);
        $c['my_heart'] = (bool)($c['my_heart'] ?? false);

        // Generate initials
        $fullName = trim(($c['name'] ?? '') . ' ' . ($c['surname'] ?? ''));
        $nameParts = preg_split('/\s+/', $fullName);
        if (count($nameParts) >= 2) {
            $c['initials'] = strtoupper(mb_substr($nameParts[0], 0, 1) . mb_substr($nameParts[count($nameParts) - 1], 0, 1));
        } elseif (count($nameParts) === 1 && !empty($nameParts[0])) {
            $c['initials'] = strtoupper(mb_substr($nameParts[0], 0, 2));
        } else {
            $c['initials'] = '??';
        }
    }
    unset($c);
    
    echo json_encode($comments, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("Comment list error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error']);
}