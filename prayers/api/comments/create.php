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
$text = trim($input['text'] ?? '');

if (!$postId || empty($text)) {
  echo json_encode(['success' => false, 'error' => 'Invalid input']);
  exit;
}

try {
  // Get post owner so we can notify them of the new comment
  $postStmt = $pdo->prepare("SELECT user_id FROM prayers_posts WHERE id = ?");
  $postStmt->execute([$postId]);
  $post = $postStmt->fetch();

  if (!$post) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'post_not_found']);
    exit;
  }

  $postOwnerId = (int)$post['user_id'];

  $stmt = $pdo->prepare("INSERT INTO prayers_comments (post_id, user_id, text) VALUES (?, ?, ?)");
  $stmt->execute([$postId, $userId, $text]);

  echo json_encode(['success' => true, 'comment_id' => $pdo->lastInsertId()]);

  // Flush response, then notify in background
  if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
  } else {
    if (ob_get_level() > 0) ob_end_flush();
    flush();
  }

  if ($postOwnerId !== (int)$userId) {
    $userStmt = $pdo->prepare("SELECT name, surname FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();
    $userName = trim(($user['name'] ?? '') . ' ' . ($user['surname'] ?? ''));

    createPrayerNotification($pdo, $postOwnerId, 'comment_on_post', [
      'commenter_name' => $userName
    ]);
  }
} catch (Exception $e) {
  error_log('Prayers comment create error: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'server_error']);
}