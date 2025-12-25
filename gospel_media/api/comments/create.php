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
$text = trim($_POST['text'] ?? '');

if ($postId <= 0 || $text === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing_fields']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

try {
    // Get post info
    $postStmt = $pdo->prepare("SELECT user_id, room_id FROM posts WHERE id = ?");
    $postStmt->execute([$postId]);
    $post = $postStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$post) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'post_not_found']);
        exit;
    }
    
    $postOwnerId = (int)$post['user_id'];
    $roomId = (int)$post['room_id'];
    
    // Insert comment
    $stmt = $pdo->prepare("INSERT INTO comments (post_id, user_id, text, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$postId, $userId, $text]);
    $commentId = (int)$pdo->lastInsertId();
    
    // Update comment count
    $pdo->prepare("UPDATE posts SET comment_count = comment_count + 1 WHERE id = ?")->execute([$postId]);
    
    // Get user name
    $userStmt = $pdo->prepare("SELECT name, surname FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    $userName = trim(($user['name'] ?? '') . ' ' . ($user['surname'] ?? ''));
    
    // 🔔 NOTIFY POST OWNER (if not commenting on own post)
    if ($postOwnerId !== $userId) {
        createGospelNotification($pdo, $postOwnerId, 'comment_on_post', [
            'commenter_name' => $userName,
            'post_id' => $postId,
            'room_id' => $roomId
        ]);
    }
    
    echo json_encode([
        'ok' => true,
        'success' => true,
        'comment' => [
            'id' => $commentId,
            'text' => $text,
            'user_id' => $userId
        ]
    ]);
} catch (Throwable $e) {
    error_log('Comment create error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'db_error']);
}