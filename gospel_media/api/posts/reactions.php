<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 3) . '/security/auth_gate.php';
require_once dirname(__DIR__, 3) . '/includes/languages.php';
require_once dirname(__DIR__, 2) . '/lib/permissions.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_unavailable']);
    exit;
}

$pageLang = $_SESSION['language'] ?? 'af';
$userId = (int)($_SESSION['user_id'] ?? 0);
$postId = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;

if ($postId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing_post_id']);
    exit;
}

// Helper: Fix photo path
function fix_photo_path(?string $photo): ?string {
    if (!$photo) return null;
    if (strpos($photo, 'http') === 0) return $photo;
    if (strpos($photo, '/') === 0) return $photo;
    return '/assets/uploads/' . $photo;
}

try {
    // Only users with access to the post's room may see who reacted
    $roomStmt = $pdo->prepare("SELECT r.* FROM rooms r JOIN posts p ON p.room_id = r.id WHERE p.id = ? LIMIT 1");
    $roomStmt->execute([$postId]);
    $room = $roomStmt->fetch(PDO::FETCH_ASSOC);

    if (!$room || !user_has_access_to_room($pdo, $userId, $room)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'forbidden']);
        exit;
    }

    $st = $pdo->prepare("
        SELECT r.type, r.created_at,
               u.id AS user_id, u.name, u.surname, u.photo, u.gender, u.amp_id
        FROM reactions r
        JOIN users u ON u.id = r.user_id
        WHERE r.post_id = ?
        ORDER BY r.created_at DESC, r.id DESC
    ");
    $st->execute([$postId]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $reactions = [];
    foreach ($rows as $r) {
        $ampId = (int)($r['amp_id'] ?? 10);
        $gender = $r['gender'] ?? 'man';

        $fullName = trim(($r['name'] ?? '') . ' ' . ($r['surname'] ?? ''));
        $nameParts = preg_split('/\s+/', $fullName);
        if (count($nameParts) >= 2) {
            $initials = strtoupper(mb_substr($nameParts[0], 0, 1) . mb_substr($nameParts[count($nameParts) - 1], 0, 1));
        } elseif (count($nameParts) === 1 && !empty($nameParts[0])) {
            $initials = strtoupper(mb_substr($nameParts[0], 0, 2));
        } else {
            $initials = '??';
        }

        $reactions[] = [
            'type' => $r['type'],
            'created_at' => $r['created_at'],
            'user_id' => (int)$r['user_id'],
            'name' => $r['name'],
            'surname' => $r['surname'],
            'gender' => $gender,
            'photo' => fix_photo_path($r['photo'] ?? null),
            'amp_title' => get_translated_office($ampId, $gender, $pageLang),
            'initials' => $initials,
            'is_me' => ((int)$r['user_id'] === $userId)
        ];
    }

    echo json_encode(['ok' => true, 'reactions' => $reactions], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('Gospel reactions list error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error']);
}
