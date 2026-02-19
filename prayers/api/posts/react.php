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
$type = $input['type'] ?? '';

if (!in_array($type, ['heart', 'pray'])) {
  echo json_encode(['success' => false, 'error' => 'Invalid type']);
  exit;
}

try {
  $pdo->beginTransaction();

  $stmt = $pdo->prepare("SELECT id FROM prayers_reactions WHERE post_id = ? AND user_id = ? AND type = ?");
  $stmt->execute([$postId, $userId, $type]);
  $exists = $stmt->fetch();

  if ($exists) {
    $stmt = $pdo->prepare("DELETE FROM prayers_reactions WHERE post_id = ? AND user_id = ? AND type = ?");
    $stmt->execute([$postId, $userId, $type]);
  } else {
    $stmt = $pdo->prepare("INSERT INTO prayers_reactions (post_id, user_id, type) VALUES (?, ?, ?)");
    $stmt->execute([$postId, $userId, $type]);
  }

  $stmt = $pdo->prepare("SELECT COUNT(*) FROM prayers_reactions WHERE post_id = ? AND type = ?");
  $stmt->execute([$postId, $type]);
  $count = $stmt->fetchColumn();

  $pdo->commit();

  echo json_encode(['success' => true, 'count' => (int)$count, 'active' => !$exists]);
} catch (Exception $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  error_log('Prayers react error: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'server_error']);
}