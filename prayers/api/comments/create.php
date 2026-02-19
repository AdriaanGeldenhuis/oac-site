<?php
require_once __DIR__ . '/../../../security/auth_gate.php';

global $pdo;
header('Content-Type: application/json; charset=utf-8');

$userId = $_SESSION['user_id'] ?? 0;

if (!$userId) {
  http_response_code(401);
  echo json_encode(['success' => false, 'error' => 'Unauthorized']);
  exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$postId = (int)($input['post_id'] ?? 0);
$text = trim($input['text'] ?? '');

if (!$postId || empty($text)) {
  echo json_encode(['success' => false, 'error' => 'Invalid input']);
  exit;
}

try {
  $stmt = $pdo->prepare("INSERT INTO prayers_comments (post_id, user_id, text) VALUES (?, ?, ?)");
  $stmt->execute([$postId, $userId, $text]);
  
  echo json_encode(['success' => true, 'comment_id' => $pdo->lastInsertId()]);
} catch (Exception $e) {
  error_log('Prayers comment create error: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'server_error']);
}