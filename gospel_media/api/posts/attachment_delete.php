<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
require_once dirname(__DIR__, 3) . '/security/auth_gate.php';
require_once dirname(__DIR__, 2) . '/lib/permissions.php';

if (!isset($pdo) || !($pdo instanceof PDO)) { 
    http_response_code(500); 
    echo json_encode(['error'=>'db_unavailable']); 
    exit; 
}

$input = $_POST;
if (empty($input)) {
    $raw = @file_get_contents('php://input');
    $json = @json_decode($raw, true);
    if (is_array($json)) $input = $json;
}

// Accept multiple parameter names for flexibility
$attId = 0;
$postId = isset($input['post_id']) ? (int)$input['post_id'] : 0;
$path = isset($input['path']) ? trim((string)$input['path']) : '';

// Try to get attachment ID from various sources
if (isset($input['attachment_id'])) {
    $attId = (int)$input['attachment_id'];
} elseif (isset($input['id'])) {
    $attId = (int)$input['id'];
}

$userId = (int)($_SESSION['user_id'] ?? 0);

// We need either attachment ID or (post_id + path)
if ($attId <= 0 && ($postId <= 0 || !$path)) { 
    http_response_code(400); 
    echo json_encode(['error'=>'missing_params']); 
    exit; 
}

function safe_unlink_attachment(string $path): bool {
    if (!$path) return false;
    
    $candidates = [
        $_SERVER['DOCUMENT_ROOT'] . $path,
        dirname(__DIR__, 3) . $path,
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
    
    // Get attachment info with room data for permission check
    if ($attId > 0) {
        $st = $pdo->prepare("
            SELECT a.id, a.path_original, a.path_thumb, p.user_id, p.room_id,
                   r.type AS room_type, r.town_id, r.gemeente_id
            FROM attachments a
            JOIN posts p ON p.id = a.post_id
            LEFT JOIN rooms r ON r.id = p.room_id
            WHERE a.id = ? LIMIT 1
        ");
        $st->execute([$attId]);
    } else {
        $st = $pdo->prepare("
            SELECT a.id, a.path_original, a.path_thumb, p.user_id, p.room_id,
                   r.type AS room_type, r.town_id, r.gemeente_id
            FROM attachments a
            JOIN posts p ON p.id = a.post_id
            LEFT JOIN rooms r ON r.id = p.room_id
            WHERE a.post_id = ? AND (a.path_original = ? OR a.path_thumb = ?)
            LIMIT 1
        ");
        $st->execute([$postId, $path, $path]);
    }

    $row = $st->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['error'=>'not_found']);
        exit;
    }

    // Check permissions using room-based logic (owner + moderators)
    $post = ['user_id' => $row['user_id'], 'room_id' => $row['room_id']];
    $room = ['id' => $row['room_id'], 'type' => $row['room_type'] ?? '', 'town_id' => $row['town_id'] ?? 0, 'gemeente_id' => $row['gemeente_id'] ?? 0];
    if (!user_can_edit_post($pdo, $userId, $post, $room)) {
        $pdo->rollBack();
        http_response_code(403);
        echo json_encode(['error'=>'forbidden']);
        exit;
    }

    $deletedId = (int)$row['id'];
    
    // Delete from database
    $pdo->prepare("DELETE FROM attachments WHERE id = ? LIMIT 1")->execute([$deletedId]);
    
    $pdo->commit();

    // Remove files (best-effort)
    safe_unlink_attachment($row['path_original'] ?? '');
    if (!empty($row['path_thumb']) && $row['path_thumb'] !== $row['path_original']) {
        safe_unlink_attachment($row['path_thumb']);
    }

    echo json_encode(['ok'=>1, 'success'=>true, 'deleted_attachment'=>$deletedId]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Gospel attachment_delete error: ".$e->getMessage());
    http_response_code(500);
    echo json_encode(['error'=>'server_error', 'message'=>$e->getMessage()]);
}