<?php
require_once __DIR__ . '/../../../security/auth_gate.php';
require_once __DIR__ . '/../notifications/helper.php';

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

  // Get post owner so we can notify them of new reactions
  $postStmt = $pdo->prepare("SELECT user_id FROM prayers_posts WHERE id = ?");
  $postStmt->execute([$postId]);
  $post = $postStmt->fetch();

  if (!$post) {
    $pdo->rollBack();
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'post_not_found']);
    exit;
  }

  $postOwnerId = (int)$post['user_id'];

  $stmt = $pdo->prepare("SELECT id FROM prayers_reactions WHERE post_id = ? AND user_id = ? AND type = ?");
  $stmt->execute([$postId, $userId, $type]);
  $exists = $stmt->fetch();

  $notifyOwner = false;

  if ($exists) {
    $stmt = $pdo->prepare("DELETE FROM prayers_reactions WHERE post_id = ? AND user_id = ? AND type = ?");
    $stmt->execute([$postId, $userId, $type]);
  } else {
    $stmt = $pdo->prepare("INSERT INTO prayers_reactions (post_id, user_id, type) VALUES (?, ?, ?)");
    $stmt->execute([$postId, $userId, $type]);
    $notifyOwner = true;
  }

  $stmt = $pdo->prepare("SELECT COUNT(*) FROM prayers_reactions WHERE post_id = ? AND type = ?");
  $stmt->execute([$postId, $type]);
  $count = $stmt->fetchColumn();

  $pdo->commit();

  echo json_encode(['success' => true, 'count' => (int)$count, 'active' => !$exists]);

  // Flush response, then notify in background so the tap feels instant
  if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
  } else {
    if (ob_get_level() > 0) ob_end_flush();
    flush();
  }

  if ($notifyOwner && $postOwnerId !== (int)$userId) {
    $userStmt = $pdo->prepare("SELECT name, surname FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();
    $userName = trim(($user['name'] ?? '') . ' ' . ($user['surname'] ?? ''));

    createPrayerNotification($pdo, $postOwnerId, 'reaction_on_post', [
      'reactor_name' => $userName,
      'reaction_type' => $type
    ]);
  }
} catch (Exception $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  error_log('Prayers react error: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'server_error']);
}