<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 3) . '/security/auth_gate.php';
require_once dirname(__DIR__, 2) . '/lib/permissions.php';
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

// Enforce the existing room posting rules server-side (the UI only hides the button)
$roomStmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ? LIMIT 1");
$roomStmt->execute([$roomId]);
$roomRow = $roomStmt->fetch(PDO::FETCH_ASSOC);

if (!$roomRow) {
    http_response_code(404);
    echo json_encode(['error'=>'room_not_found']);
    exit;
}

if (!user_can_post_in_room($pdo, $userId, $roomRow)) {
    http_response_code(403);
    echo json_encode(['error'=>'forbidden']);
    exit;
}

// A post must contain text or at least one image
$hasImages = !empty($_FILES['images'])
    && is_array($_FILES['images']['tmp_name'] ?? null)
    && count(array_filter($_FILES['images']['tmp_name'])) > 0;

if ($text === '' && !$hasImages) {
    http_response_code(400);
    echo json_encode(['error'=>'empty_post']);
    exit;
}

function parse_event_at(?string $raw): ?string {
    if ($raw === null || $raw === '') return null;
    $ts = strtotime($raw);
    return $ts === false ? null : date('Y-m-d H:i:s', $ts);
}

$eventAt    = $type === 'event' ? parse_event_at(trim((string)($_POST['event_at'] ?? ''))) : null;
$eventPlace = $type === 'event' ? trim((string)($_POST['event_place'] ?? '')) : null;

// Helper: resize an image resource to fit within max dimensions
function resize_image($img, int $origW, int $origH, int $maxW, int $maxH) {
    if ($origW <= $maxW && $origH <= $maxH) return $img;

    $ratio = min($maxW / $origW, $maxH / $origH);
    $newW = (int)round($origW * $ratio);
    $newH = (int)round($origH * $ratio);

    $resized = @imagecreatetruecolor($newW, $newH);
    if (!$resized) return $img;

    // Preserve transparency
    @imagealphablending($resized, false);
    @imagesavealpha($resized, true);

    @imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
    return $resized;
}

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
    $filenameBase = $timestamp . '_' . $random;
    $destPath = $uploadBase . '/' . $filenameBase . '.webp';
    $thumbPath = $uploadBase . '/' . $filenameBase . '_thumb.webp';

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

    // Resize original to max 1920px wide for bandwidth savings
    $imgResized = resize_image($img, $width, $height, 1920, 1920);
    $finalW = imagesx($imgResized);
    $finalH = imagesy($imgResized);

    // Save resized original as WebP
    $success = @imagewebp($imgResized, $destPath, 82);
    if ($imgResized !== $img) @imagedestroy($imgResized);

    if (!$success) {
        @imagedestroy($img);
        return null;
    }

    // Generate thumbnail (max 600px) for feed display
    $imgThumb = resize_image($img, $width, $height, 600, 600);
    @imagewebp($imgThumb, $thumbPath, 75);
    if ($imgThumb !== $img) @imagedestroy($imgThumb);

    @imagedestroy($img);

    // Return URL paths (relative to document root)
    $urlPath = '/uploads/posts/' . $userId . '/' . $filenameBase . '.webp';
    $thumbUrl = '/uploads/posts/' . $userId . '/' . $filenameBase . '_thumb.webp';

    return [
        'path_original' => $urlPath,
        'path_thumb' => $thumbUrl,
        'mime' => 'image/webp',
        'width' => $finalW,
        'height' => $finalH
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

    // Send response IMMEDIATELY so UI updates fast
    echo json_encode(['ok'=>1, 'success'=>true, 'post_id'=>$postId]);

    // Flush output to browser immediately, then continue with notifications
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        if (ob_get_level() > 0) ob_end_flush();
        flush();
    }

    // Notify all room members about the new post (runs in background after response sent)
    try {
        // Room info already loaded for the permission check above
        $roomName = $roomRow['name'] ?? 'Room';
        $roomType = strtolower($roomRow['type'] ?? '');
        $roomTownId = (int)($roomRow['town_id'] ?? 0);
        $roomGemeenteId = (int)($roomRow['gemeente_id'] ?? 0);

        // Get author name
        $authorStmt = $pdo->prepare("SELECT CONCAT(name, ' ', surname) AS full_name FROM users WHERE id = ?");
        $authorStmt->execute([$userId]);
        $authorName = $authorStmt->fetchColumn() ?: 'Someone';

        // Get room members based on room type
        // Use room_memberships + implicit membership to find all recipients
        $members = [];

        if ($roomType === 'gemeente' && $roomGemeenteId > 0) {
            // Gemeente: all approved users in that congregation
            $membersStmt = $pdo->prepare("
                SELECT id FROM users
                WHERE congregation_id = ? AND id != ? AND status = 'approved'
            ");
            $membersStmt->execute([$roomGemeenteId, $userId]);
            $members = $membersStmt->fetchAll(PDO::FETCH_COLUMN);

        } elseif ($roomType === 'opsienerskap' && $roomTownId > 0) {
            // Opsienerskap: all approved users in town + any Apostel who joined via room_memberships
            $membersStmt = $pdo->prepare("
                SELECT DISTINCT u.id FROM users u
                WHERE u.id != ? AND u.status = 'approved'
                  AND (
                    u.town_id = ?
                    OR u.id IN (SELECT rm.user_id FROM room_memberships rm WHERE rm.room_id = ?)
                  )
            ");
            $membersStmt->execute([$userId, $roomTownId, $roomId]);
            $members = $membersStmt->fetchAll(PDO::FETCH_COLUMN);

        } elseif (in_array($roomType, ['jeug', 'sondagskool']) && $roomTownId > 0) {
            // Jeug/Sondagskool: auto-enrolled members + manually joined members
            $membersStmt = $pdo->prepare("
                SELECT DISTINCT u.id FROM users u
                WHERE u.id != ? AND u.status = 'approved'
                  AND (
                    u.town_id = ?
                    OR u.id IN (SELECT rm.user_id FROM room_memberships rm WHERE rm.room_id = ?)
                  )
            ");
            $membersStmt->execute([$userId, $roomTownId, $roomId]);
            $members = $membersStmt->fetchAll(PDO::FETCH_COLUMN);
        }

        // Send notification to each member
        foreach ($members as $memberId) {
            createGospelNotification($pdo, (int)$memberId, 'new_post', [
                'room_id' => $roomId,
                'room_name' => $roomName,
                'author_name' => $authorName,
                'post_id' => $postId
            ]);
        }

        error_log("Gospel notification: Sent to " . count($members) . " members for room $roomId ($roomType)");
    } catch (Throwable $notifError) {
        error_log('Gospel post notification error: ' . $notifError->getMessage());
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Gospel create post error: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['error'=>'server_error', 'message'=>$e->getMessage()]);
}