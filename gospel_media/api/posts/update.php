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

$userId = (int)($_SESSION['user_id'] ?? 0);
$postId = (int)($_POST['post_id'] ?? 0);
$text   = isset($_POST['text']) ? trim((string)$_POST['text']) : null;

function parse_event_at(?string $raw): ?string {
    if ($raw === null || $raw === '') return null;
    $ts = strtotime($raw);
    return $ts === false ? null : date('Y-m-d H:i:s', $ts);
}

$eventAtRaw    = isset($_POST['event_at']) ? trim((string)$_POST['event_at']) : null;
$eventPlaceRaw = isset($_POST['event_place']) ? trim((string)$_POST['event_place']) : null;

if ($userId <= 0 || $postId <= 0) { 
    http_response_code(400); 
    echo json_encode(['error'=>'bad_request']); 
    exit; 
}

// Image processing function
function process_uploaded_image_update(string $tmpPath, int $userId, int $postId): ?array {
    $uploadBase = dirname(__DIR__, 3) . '/uploads/posts/' . $userId;
    if (!is_dir($uploadBase)) {
        @mkdir($uploadBase, 0755, true);
    }
    
    $timestamp = date('YmdHis');
    $random = substr(md5(uniqid((string)mt_rand(), true)), 0, 6);
    $filename = $timestamp . '_' . $random . '.webp';
    $destPath = $uploadBase . '/' . $filename;
    
    $info = @getimagesize($tmpPath);
    if (!$info) return null;
    
    $mime = $info['mime'];
    $width = $info[0];
    $height = $info[1];
    
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
    
    $success = @imagewebp($img, $destPath, 85);
    @imagedestroy($img);
    
    if (!$success) return null;
    
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
    // Get post and room info
    $st = $pdo->prepare("SELECT p.id, p.user_id, p.room_id, p.type, r.type AS room_type, r.town_id, r.gemeente_id
                         FROM posts p 
                         LEFT JOIN rooms r ON r.id = p.room_id 
                         WHERE p.id = ?");
    $st->execute([$postId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) { 
        http_response_code(404); 
        echo json_encode(['error'=>'not_found']); 
        exit; 
    }
    
    $post = [
        'id' => $row['id'],
        'user_id' => $row['user_id'],
        'room_id' => $row['room_id'],
        'type' => $row['type']
    ];
    
    $room = [
        'id' => $row['room_id'],
        'type' => $row['room_type'] ?? '',
        'town_id' => $row['town_id'] ?? 0,
        'gemeente_id' => $row['gemeente_id'] ?? 0
    ];
    
    // Check permissions using room-based logic
    if (!user_can_edit_post($pdo, $userId, $post, $room)) {
        http_response_code(403);
        echo json_encode(['error'=>'forbidden']);
        exit;
    }

    $pdo->beginTransaction();

    $fields = [];
    $params = [':id' => $postId];

    if ($text !== null) { 
        $fields[] = 'text = :text'; 
        $params[':text'] = $text; 
    }

    if (($post['type'] ?? '') === 'event') {
        if ($eventAtRaw !== null) {
            $fields[] = 'event_at = :event_at';
            $params[':event_at'] = parse_event_at($eventAtRaw);
        }
        if ($eventPlaceRaw !== null) {
            $fields[] = 'event_place = :event_place';
            $params[':event_place'] = ($eventPlaceRaw === '') ? null : $eventPlaceRaw;
        }
    }

    if ($fields) {
        $sql = "UPDATE posts SET ".implode(', ', $fields)." WHERE id = :id";
        $up = $pdo->prepare($sql);
        $up->execute($params);
    }

    // Handle new image uploads
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
            
            $result = process_uploaded_image_update($tmp, $userId, $postId);
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

    echo json_encode(['ok'=>1, 'success'=>true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Gospel update post error: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['error'=>'server_error', 'message'=>$e->getMessage()]);
}