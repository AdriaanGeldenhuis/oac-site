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

$photoUrl = null;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
  $uploadDir = __DIR__ . '/../../../uploads/prayers/';
  if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
  
  $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
  $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
  
  if (in_array($ext, $allowed)) {
    $filename = 'prayer_' . uniqid() . '.' . $ext;
    if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $filename)) {
      $photoUrl = '/uploads/prayers/' . $filename;
    }
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
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}