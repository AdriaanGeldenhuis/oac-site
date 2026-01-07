<?php
require_once __DIR__ . '/../../../security/auth_gate.php';
require_once __DIR__ . '/../../../includes/languages.php';

global $pdo;
header('Content-Type: application/json; charset=utf-8');

$userId = $_SESSION['user_id'] ?? 0;
$userApte = $_SESSION['apte'] ?? 8;
$pageLang = $_SESSION['language'] ?? 'af';

if (!$userId) {
  http_response_code(401);
  echo json_encode(['success' => false, 'error' => 'Unauthorized']);
  exit;
}

$postId = (int)($_GET['post_id'] ?? 0);

if (!$postId) {
  echo json_encode(['success' => false, 'error' => 'Invalid post ID']);
  exit;
}

try {
  $stmt = $pdo->prepare("
    SELECT
      c.*,
      u.name as user_name,
      u.surname as user_surname,
      u.amp_id,
      u.gender,
      u.photo as user_pic
    FROM prayers_comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.post_id = ?
    ORDER BY c.created_at ASC
  ");
  $stmt->execute([$postId]);
  $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $isAdmin = ($userApte >= 2 && $userApte <= 7);

  foreach ($comments as &$comment) {
    $comment['created_at'] = date('Y-m-d H:i', strtotime($comment['created_at']));
    $comment['user_pic'] = $comment['user_pic'] ?: '/assets/default-avatar.png';

    // Build username with office title
    $ampId = (int)($comment['amp_id'] ?? 10);
    $gender = $comment['gender'] ?? 'man';
    $officeTitle = get_translated_office($ampId, $gender, $pageLang);
    $fullName = trim($comment['user_name'] . ' ' . $comment['user_surname']);
    $comment['username'] = $officeTitle . ' ' . $fullName;

    // Clean up fields not needed in response
    unset($comment['user_name'], $comment['user_surname'], $comment['amp_id'], $comment['gender']);

    $comment['can_edit'] = ($comment['user_id'] == $userId || $isAdmin);
    $comment['can_delete'] = ($comment['user_id'] == $userId || $isAdmin);
  }
  
  echo json_encode(['success' => true, 'comments' => $comments]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}