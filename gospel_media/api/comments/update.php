<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 3) . '/security/auth_gate.php';
require_once dirname(__DIR__, 2) . '/lib/permissions.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['error' => 'db_unavailable']);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$commentId = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;
$text = isset($_POST['text']) ? trim((string)$_POST['text']) : '';

if ($commentId <= 0 || $text === '') {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_parameters']);
    exit;
}

try {
    // Get comment info with post_id
    $st = $pdo->prepare("SELECT id, user_id, post_id FROM comments WHERE id = ? LIMIT 1");
    $st->execute([$commentId]);
    $comment = $st->fetch(PDO::FETCH_ASSOC);
    
    if (!$comment) {
        http_response_code(404);
        echo json_encode(['error' => 'not_found']);
        exit;
    }
    
    $postId = (int)$comment['post_id'];
    
    // Check permissions using room-based logic
    if (!user_can_edit_comment($pdo, $userId, $comment, $postId)) {
        http_response_code(403);
        echo json_encode(['error' => 'forbidden']);
        exit;
    }
    
    // Update comment
    $upd = $pdo->prepare("UPDATE comments SET text = ? WHERE id = ?");
    $upd->execute([$text, $commentId]);
    
    echo json_encode(['ok' => 1, 'success' => true]);
} catch (Throwable $e) {
    error_log("Comment update error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error']);
}