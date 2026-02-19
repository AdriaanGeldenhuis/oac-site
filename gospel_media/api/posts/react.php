<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../security/auth_gate.php';
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

$postId = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
$type = trim($_POST['type'] ?? '');

if ($postId <= 0 || !in_array($type, ['heart', 'pray'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_input']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

try {
    $pdo->beginTransaction();

    // Get post info
    $postStmt = $pdo->prepare("SELECT user_id, room_id FROM posts WHERE id = ?");
    $postStmt->execute([$postId]);
    $post = $postStmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'post_not_found']);
        exit;
    }

    $postOwnerId = (int)$post['user_id'];
    $roomId = (int)$post['room_id'];

    // Check if already reacted
    $stmt = $pdo->prepare("SELECT id FROM reactions WHERE post_id = ? AND user_id = ? AND type = ?");
    $stmt->execute([$postId, $userId, $type]);
    $existing = $stmt->fetch();

    $notifyOwner = false;

    if ($existing) {
        $stmt = $pdo->prepare("DELETE FROM reactions WHERE post_id = ? AND user_id = ? AND type = ?");
        $stmt->execute([$postId, $userId, $type]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO reactions (post_id, user_id, type, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$postId, $userId, $type]);
        $notifyOwner = true;
    }

    // Get updated counts and sync to post
    $stmt = $pdo->prepare("SELECT
        (SELECT COUNT(*) FROM reactions WHERE post_id = ? AND type = 'heart') as heart_count,
        (SELECT COUNT(*) FROM reactions WHERE post_id = ? AND type = 'pray') as pray_count
    ");
    $stmt->execute([$postId, $postId]);
    $counts = $stmt->fetch(PDO::FETCH_ASSOC);

    $pdo->prepare("UPDATE posts SET heart_count = ?, pray_count = ? WHERE id = ?")
        ->execute([$counts['heart_count'], $counts['pray_count'], $postId]);

    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'heart_count' => (int)$counts['heart_count'],
        'pray_count' => (int)$counts['pray_count']
    ]);

    // Flush response, then notify in background
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        if (ob_get_level() > 0) ob_end_flush();
        flush();
    }

    if ($notifyOwner && $postOwnerId !== $userId) {
        $userStmt = $pdo->prepare("SELECT name, surname FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        $userName = trim(($user['name'] ?? '') . ' ' . ($user['surname'] ?? ''));

        createGospelNotification($pdo, $postOwnerId, 'reaction_on_post', [
            'reactor_name' => $userName,
            'reaction_type' => $type,
            'room_id' => $roomId
        ]);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('React error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_error']);
}