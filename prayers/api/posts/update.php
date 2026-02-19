<?php
require_once __DIR__ . '/../../../security/auth_gate.php';

global $pdo;
header('Content-Type: application/json; charset=utf-8');

$userId = $_SESSION['user_id'] ?? 0;
$userApte = $_SESSION['apte'] ?? 8;

if (!$userId) {
  http_response_code(401);
  echo json_encode(['success' => false, 'error' => 'Unauthorized']);
  exit;
}

$isJson = isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false;

if ($isJson) {
  $input = json_decode(file_get_contents('php://input'), true);
  $postId = (int)($input['post_id'] ?? 0);
  $text = trim($input['text'] ?? '');
  $removePhoto = $input['remove_photo'] ?? false;
} else {
  $postId = (int)($_POST['post_id'] ?? 0);
  $text = trim($_POST['text'] ?? '');
  $removePhoto = isset($_POST['remove_photo']);
}

if (!$postId || empty($text)) {
  echo json_encode(['success' => false, 'error' => 'Invalid input']);
  exit;
}

// Helper: resize image to fit within max dimensions
function resize_prayer_image_update($img, int $origW, int $origH, int $maxW, int $maxH) {
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

// Process uploaded image: resize and save as WebP
function process_prayer_image_update(string $tmpPath): ?string {
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

    $resized = resize_prayer_image_update($img, $width, $height, 1920, 1920);
    $success = @imagewebp($resized, $uploadDir . $filename, 82);
    if ($resized !== $img) @imagedestroy($resized);
    @imagedestroy($img);

    if (!$success) return null;

    return '/uploads/prayers/' . $filename;
}

try {
  $stmt = $pdo->prepare("SELECT user_id, photo_url FROM prayers_posts WHERE id = ?");
  $stmt->execute([$postId]);
  $post = $stmt->fetch();

  if (!$post) {
    echo json_encode(['success' => false, 'error' => 'Post not found']);
    exit;
  }

  $isAdmin = ($userApte >= 2 && $userApte <= 7);
  $isOwner = ($post['user_id'] == $userId);

  if (!$isOwner && !$isAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
  }

  $newPhotoUrl = $post['photo_url'];

  if ($removePhoto && $post['photo_url']) {
    $photoPath = __DIR__ . '/../../../' . ltrim($post['photo_url'], '/');
    if (file_exists($photoPath)) @unlink($photoPath);
    $newPhotoUrl = null;
  }

  if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    // Delete old photo first
    if ($post['photo_url']) {
      $photoPath = __DIR__ . '/../../../' . ltrim($post['photo_url'], '/');
      if (file_exists($photoPath)) @unlink($photoPath);
    }

    $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (in_array($ext, $allowed)) {
      $result = process_prayer_image_update($_FILES['photo']['tmp_name']);
      if ($result) $newPhotoUrl = $result;
    }
  }

  $stmt = $pdo->prepare("UPDATE prayers_posts SET text = ?, photo_url = ?, updated_at = NOW() WHERE id = ?");
  $stmt->execute([$text, $newPhotoUrl, $postId]);

  echo json_encode(['success' => true, 'photo_url' => $newPhotoUrl]);
} catch (Exception $e) {
  error_log('Prayers update error: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'server_error']);
}
