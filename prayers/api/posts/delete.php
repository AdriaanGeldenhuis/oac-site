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

$input = json_decode(file_get_contents('php://input'), true);
$postId = (int)($input['post_id'] ?? 0);

if (!$postId) {
  echo json_encode(['success' => false, 'error' => 'Invalid post ID']);
  exit;
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
  
  $pdo->beginTransaction();

  $stmt = $pdo->prepare("DELETE FROM prayers_comments WHERE post_id = ?");
  $stmt->execute([$postId]);

  $stmt = $pdo->prepare("DELETE FROM prayers_reactions WHERE post_id = ?");
  $stmt->execute([$postId]);

  $stmt = $pdo->prepare("DELETE FROM prayers_posts WHERE id = ?");
  $stmt->execute([$postId]);

  $pdo->commit();

  // Delete photo file after successful DB transaction
  if ($post['photo_url']) {
    $photoPath = __DIR__ . '/../../../' . ltrim($post['photo_url'], '/');
    if (file_exists($photoPath)) @unlink($photoPath);
  }

  echo json_encode(['success' => true]);
} catch (Exception $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  error_log('Prayers delete error: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'server_error']);
}