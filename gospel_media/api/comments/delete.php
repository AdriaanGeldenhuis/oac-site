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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$commentId = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;

if ($commentId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_comment_id']);
    exit;
}

try {
    // Get comment info
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
    
    $pdo->beginTransaction();
    
    // Delete comment
    $delComment = $pdo->prepare("DELETE FROM comments WHERE id = ? LIMIT 1");
    $delComment->execute([$commentId]);
    
    // Update post comment_count
    $upd = $pdo->prepare('UPDATE posts SET comment_count = (SELECT COUNT(*) FROM comments WHERE post_id = ?) WHERE id = ?');
    $upd->execute([$postId, $postId]);
    
    $pdo->commit();
    echo json_encode(['ok' => 1, 'success' => true, 'deleted_comment' => $commentId]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Comment delete error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error']);
}