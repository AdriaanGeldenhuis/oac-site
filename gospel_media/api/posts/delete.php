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

// Accept form-data or JSON
$input = $_POST;
if (empty($input)) {
    $raw = @file_get_contents('php://input');
    $json = @json_decode($raw, true);
    if (is_array($json)) $input = $json;
}

// accept post_id or id
$postId = isset($input['post_id']) ? (int)$input['post_id'] : (isset($input['id']) ? (int)$input['id'] : 0);

if ($postId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_post_id', 'message' => 'post_id or id required']);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['error' => 'not_authenticated']);
    exit;
}

function safe_unlink_gospel(string $path): bool {
    if (!$path) return false;
    
    $candidates = [
        $_SERVER['DOCUMENT_ROOT'] . $path,
        dirname(__DIR__, 3) . $path,
        dirname(__DIR__, 2) . '/uploads/' . basename($path),
    ];
    
    foreach ($candidates as $c) {
        $real = @realpath($c);
        if ($real && is_file($real)) {
            @unlink($real);
            return true;
        }
    }
    return false;
}

try {
    $pdo->beginTransaction();
    
    // Get post with room info
    $st = $pdo->prepare("SELECT p.id, p.user_id, p.room_id, r.type, r.town_id, r.gemeente_id 
                         FROM posts p 
                         LEFT JOIN rooms r ON r.id = p.room_id 
                         WHERE p.id = ? LIMIT 1");
    $st->execute([$postId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['error' => 'not_found']);
        exit;
    }
    
    // Split post and room data
    $post = [
        'id' => $row['id'],
        'user_id' => $row['user_id'],
        'room_id' => $row['room_id']
    ];
    
    $room = [
        'id' => $row['room_id'],
        'type' => $row['type'] ?? '',
        'town_id' => $row['town_id'] ?? 0,
        'gemeente_id' => $row['gemeente_id'] ?? 0
    ];
    
    // Check permissions using room-based logic
    if (!user_can_edit_post($pdo, $userId, $post, $room)) {
        $pdo->rollBack();
        http_response_code(403);
        echo json_encode(['error' => 'forbidden']);
        exit;
    }

    // Collect attachments paths to unlink
    $sel = $pdo->prepare("SELECT path_original, path_thumb FROM attachments WHERE post_id = ?");
    $sel->execute([$postId]);
    $attachments = $sel->fetchAll(PDO::FETCH_ASSOC);

    // Delete attachment rows
    $pdo->prepare("DELETE FROM attachments WHERE post_id = ?")->execute([$postId]);

    // Delete related records
    $pdo->prepare("DELETE FROM reactions WHERE post_id = ?")->execute([$postId]);
    $pdo->prepare("DELETE FROM comments WHERE post_id = ?")->execute([$postId]);

    // Delete post row
    $pdo->prepare("DELETE FROM posts WHERE id = ? LIMIT 1")->execute([$postId]);

    $pdo->commit();

    // Remove files after commit (best-effort)
    foreach ($attachments as $a) {
        safe_unlink_gospel($a['path_original'] ?? '');
        if (!empty($a['path_thumb']) && $a['path_thumb'] !== $a['path_original']) {
            safe_unlink_gospel($a['path_thumb']);
        }
    }

    echo json_encode(['ok' => 1, 'deleted_post' => $postId]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Gospel delete error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error', 'message' => $e->getMessage()]);
}