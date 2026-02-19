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
$commentId = (int)($input['comment_id'] ?? 0);

if (!$commentId) {
  echo json_encode(['success' => false, 'error' => 'Invalid comment ID']);
  exit;
}

try {
  $stmt = $pdo->prepare("SELECT user_id FROM prayers_comments WHERE id = ?");
  $stmt->execute([$commentId]);
  $comment = $stmt->fetch();
  
  if (!$comment) {
    echo json_encode(['success' => false, 'error' => 'Comment not found']);
    exit;
  }
  
  $isAdmin = ($userApte >= 2 && $userApte <= 7);
  $isOwner = ($comment['user_id'] == $userId);
  
  if (!$isOwner && !$isAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
  }
  
  $stmt = $pdo->prepare("DELETE FROM prayers_comments WHERE id = ?");
  $stmt->execute([$commentId]);
  
  echo json_encode(['success' => true]);
} catch (Exception $e) {
  error_log('Prayers comment delete error: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'server_error']);
}