<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 3) . '/security/auth_gate.php';
require_once __DIR__ . '/../notifications/helper.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['error'=>'db_unavailable']);
    exit;
}

ini_set('display_errors', '0');
ini_set('log_errors', '1');

$userId = (int)($_SESSION['user_id'] ?? 0);
$roomId = (int)($_POST['room_id'] ?? 0);
$type   = ($_POST['type'] ?? 'post') === 'event' ? 'event' : 'post';
$text   = trim((string)($_POST['text'] ?? ''));

if ($userId <= 0 || $roomId <= 0) { 
    http_response_code(400); 
    echo json_encode(['error'=>'bad_request']); 
    exit; 
}

function parse_event_at(?string $raw): ?string {
    if ($raw === null || $raw === '') return null;
    $ts = strtotime($raw);
    return $ts === false ? null : date('Y-m-d H:i:s', $ts);
}

$eventAt    = $type === 'event' ? parse_event_at(trim((string)($_POST['event_at'] ?? ''))) : null;
$eventPlace = $type === 'event' ? trim((string)($_POST['event_place'] ?? '')) : null;

// Image processing function
function process_uploaded_image(string $tmpPath, int $userId, int $postId): ?array {
    // Create user-specific upload directory
    $uploadBase = dirname(__DIR__, 3) . '/uploads/posts/' . $userId;
    if (!is_dir($uploadBase)) {
        @mkdir($uploadBase, 0755, true);
    }
    
    // Generate unique filename
    $timestamp = date('YmdHis');
    $random = substr(md5(uniqid((string)mt_rand(), true)), 0, 6);
    $filename = $timestamp . '_' . $random . '.webp';
    $destPath = $uploadBase . '/' . $filename;
    
    // Load image
    $info = @getimagesize($tmpPath);
    if (!$info) return null;
    
    $mime = $info['mime'];
    $width = $info[0];
    $height = $info[1];
    
    // Create image resource based on mime type
    $img = null;
    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            $img = @imagecreatefromjpeg($tmpPath);
            break;
        case 'image/png':
            $img = @imagecreatefrompng($tmpPath);
            break;
        case 'image/gif':
            $img = @imagecreatefromgif($tmpPath);
            break;
        case 'image/webp':
            $img = @imagecreatefromwebp($tmpPath);
            break;
        default:
            return null;
    }
    
    if (!$img) return null;
    
    // Convert to WebP
    $success = @imagewebp($img, $destPath, 85);
    @imagedestroy($img);
    
    if (!$success) return null;
    
    // Return URL path (relative to document root)
    $urlPath = '/uploads/posts/' . $userId . '/' . $filename;
    
    return [
        'path_original' => $urlPath,
        'path_thumb' => $urlPath,
        'mime' => 'image/webp',
        'width' => $width,
        'height' => $height
    ];
}

try {
    $pdo->beginTransaction();

    $ins = $pdo->prepare("
        INSERT INTO posts (room_id, user_id, type, text, event_at, event_place, created_at)
        VALUES (:room_id, :user_id, :type, :text, :event_at, :event_place, NOW())
    ");
    $ins->execute([
        ':room_id'     => $roomId,
        ':user_id'     => $userId,
        ':type'        => $type,
        ':text'        => $text,
        ':event_at'    => $eventAt,
        ':event_place' => $eventPlace ?: null,
    ]);
    $postId = (int)$pdo->lastInsertId();

    // Handle file uploads
    if (!empty($_FILES['images']) && is_array($_FILES['images']['tmp_name'])) {
        $files = $_FILES['images'];
        $insAtt = $pdo->prepare("
            INSERT INTO attachments (post_id, path_original, path_thumb, mime, width, height)
            VALUES (:post_id, :path_original, :path_thumb, :mime, :width, :height)
        ");

        $fileCount = count($files['tmp_name']);
        for ($i = 0; $i < $fileCount; $i++) {
            $tmp = $files['tmp_name'][$i];
            if (!$tmp || !is_uploaded_file($tmp)) continue;
            
            $result = process_uploaded_image($tmp, $userId, $postId);
            if (!$result) continue;
            
            $insAtt->execute([
                ':post_id'      => $postId,
                ':path_original'=> $result['path_original'],
                ':path_thumb'   => $result['path_thumb'],
                ':mime'         => $result['mime'],
                ':width'        => $result['width'],
                ':height'       => $result['height']
            ]);
        }
    }

    $pdo->commit();

    // Notify all room members about the new post
    try {
        // Get room name and author name
        $roomStmt = $pdo->prepare("SELECT name FROM rooms WHERE id = ?");
        $roomStmt->execute([$roomId]);
        $roomName = $roomStmt->fetchColumn() ?: 'Room';

        $authorStmt = $pdo->prepare("SELECT CONCAT(name, ' ', surname) AS full_name FROM users WHERE id = ?");
        $authorStmt->execute([$userId]);
        $authorName = $authorStmt->fetchColumn() ?: 'Someone';

        // Get all room members except the post author
        $membersStmt = $pdo->prepare("
            SELECT user_id FROM room_members
            WHERE room_id = ? AND user_id != ?
        ");
        $membersStmt->execute([$roomId, $userId]);
        $members = $membersStmt->fetchAll(PDO::FETCH_COLUMN);

        // Send notification to each member
        foreach ($members as $memberId) {
            createGospelNotification($pdo, (int)$memberId, 'new_post', [
                'room_id' => $roomId,
                'room_name' => $roomName,
                'author_name' => $authorName,
                'post_id' => $postId
            ]);
        }
    } catch (Throwable $notifError) {
        error_log('Gospel post notification error: ' . $notifError->getMessage());
    }

    echo json_encode(['ok'=>1, 'success'=>true, 'post_id'=>$postId]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Gospel create post error: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['error'=>'server_error', 'message'=>$e->getMessage()]);
}