<?php
require_once __DIR__ . '/../../../security/auth_gate.php';

global $pdo;
header('Content-Type: application/json; charset=utf-8');

$userId = $_SESSION['user_id'] ?? 0;
$townId = $_SESSION['town_id'] ?? 0;

if (!$userId) {
  http_response_code(401);
  echo json_encode(['success' => false, 'error' => 'Unauthorized']);
  exit;
}

$kind = $_POST['kind'] ?? 'prayer';
$text = trim($_POST['text'] ?? '');
$visibility = $_POST['visibility'] ?? 'opsienerskap';

if (!in_array($kind, ['prayer', 'testimony'])) {
  echo json_encode(['success' => false, 'error' => 'Invalid kind']);
  exit;
}

if (empty($text)) {
  echo json_encode(['success' => false, 'error' => 'Text required']);
  exit;
}

// Helper: resize image to fit within max dimensions
function resize_prayer_image($img, int $origW, int $origH, int $maxW, int $maxH) {
    if ($origW <= $maxW && $origH <= $maxH) return $img;
    $ratio = min($maxW / $origW, $maxH / $origH);
    $newW = (int)round($origW * $ratio);
    $newH = (int)round($origH * $ratio);
    $resized = @imagecreatetruecolor($newW, $newH);
    if (!$resized) return $img;
    @imagealphablending($resized, false);
    @imagesavealpha($resized, true);
    @imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
    return $resized;
}

// Process uploaded image: resize to max 1920px and save as WebP
function process_prayer_image(string $tmpPath): ?string {
    $info = @getimagesize($tmpPath);
    if (!$info) return null;

    $mime = $info['mime'];
    $width = $info[0];
    $height = $info[1];

    $img = null;
    switch ($mime) {
        case 'image/jpeg': case 'image/jpg': $img = @imagecreatefromjpeg($tmpPath); break;
        case 'image/png': $img = @imagecreatefrompng($tmpPath); break;
        case 'image/gif': $img = @imagecreatefromgif($tmpPath); break;
        case 'image/webp': $img = @imagecreatefromwebp($tmpPath); break;
        default: return null;
    }
    if (!$img) return null;

    $uploadDir = __DIR__ . '/../../../uploads/prayers/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $filename = 'prayer_' . uniqid() . '.webp';

    $resized = resize_prayer_image($img, $width, $height, 1920, 1920);
    $success = @imagewebp($resized, $uploadDir . $filename, 82);
    if ($resized !== $img) @imagedestroy($resized);
    @imagedestroy($img);

    if (!$success) return null;

    return '/uploads/prayers/' . $filename;
}

$photoUrl = null;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
  $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
  $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

  if (in_array($ext, $allowed)) {
    $photoUrl = process_prayer_image($_FILES['photo']['tmp_name']);
  }
}

try {
  $stmt = $pdo->prepare("
    INSERT INTO prayers_posts (user_id, town_id, kind, text, photo_url, visibility)
    VALUES (?, ?, ?, ?, ?, ?)
  ");
  $stmt->execute([$userId, $townId, $kind, $text, $photoUrl, $visibility]);

  echo json_encode(['success' => true, 'post_id' => $pdo->lastInsertId()]);
} catch (Exception $e) {
  error_log('Prayers create error: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'server_error']);
}
