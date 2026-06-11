<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 3) . '/security/auth_gate.php';
require_once dirname(__DIR__, 2) . '/lib/permissions.php';
require_once __DIR__ . '/../notifications/helper.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_unavailable']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$commentId = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;
$type = trim($_POST['type'] ?? 'heart');

if ($commentId <= 0 || !in_array($type, ['heart'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_input']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

try {
    // Get comment with its post and room
    $st = $pdo->prepare("
        SELECT c.id, c.user_id AS comment_owner, c.post_id, p.room_id
        FROM comments c
        JOIN posts p ON p.id = c.post_id
        WHERE c.id = ? LIMIT 1
    ");
    $st->execute([$commentId]);
    $comment = $st->fetch(PDO::FETCH_ASSOC);

    if (!$comment) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'comment_not_found']);
        exit;
    }

    $commentOwnerId = (int)$comment['comment_owner'];
    $postId = (int)$comment['post_id'];
    $roomId = (int)$comment['room_id'];

    // Only users with access to the room may react
    $roomStmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ? LIMIT 1");
    $roomStmt->execute([$roomId]);
    $room = $roomStmt->fetch(PDO::FETCH_ASSOC);

    if (!$room || !user_has_access_to_room($pdo, $userId, $room)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'forbidden']);
        exit;
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id FROM comment_reactions WHERE comment_id = ? AND user_id = ? AND type = ?");
    $stmt->execute([$commentId, $userId, $type]);
    $existing = $stmt->fetch();

    $notifyOwner = false;

    if ($existing) {
        $pdo->prepare("DELETE FROM comment_reactions WHERE comment_id = ? AND user_id = ? AND type = ?")
            ->execute([$commentId, $userId, $type]);
    } else {
        $pdo->prepare("INSERT INTO comment_reactions (comment_id, user_id, type, created_at) VALUES (?, ?, ?, NOW())")
            ->execute([$commentId, $userId, $type]);
        $notifyOwner = true;
    }

    $cnt = $pdo->prepare("SELECT COUNT(*) FROM comment_reactions WHERE comment_id = ? AND type = ?");
    $cnt->execute([$commentId, $type]);
    $heartCount = (int)$cnt->fetchColumn();

    $pdo->commit();

    echo json_encode(['ok' => true, 'heart_count' => $heartCount]);

    // Flush response, then notify in background
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        if (ob_get_level() > 0) ob_end_flush();
        flush();
    }

    if ($notifyOwner && $commentOwnerId !== $userId) {
        $userStmt = $pdo->prepare("SELECT name, surname FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        $userName = trim(($user['name'] ?? '') . ' ' . ($user['surname'] ?? ''));

        createGospelNotification($pdo, $commentOwnerId, 'reaction_on_comment', [
            'reactor_name' => $userName,
            'reaction_type' => $type,
            'room_id' => $roomId,
            'post_id' => $postId
        ]);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Comment react error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_error']);
}
