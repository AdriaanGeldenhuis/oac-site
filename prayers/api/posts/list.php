<?php
require_once __DIR__ . '/../../../security/auth_gate.php';
require_once __DIR__ . '/../../../includes/languages.php';

global $pdo;
header('Content-Type: application/json; charset=utf-8');

$userId = $_SESSION['user_id'] ?? 0;
$userTownId = $_SESSION['town_id'] ?? 0;
$userApte = $_SESSION['apte'] ?? 8;
$pageLang = $_SESSION['language'] ?? 'af';

if (!$userId) {
  http_response_code(401);
  echo json_encode(['success' => false, 'error' => 'Unauthorized']);
  exit;
}

$isAdmin = ($userApte >= 2 && $userApte <= 7);

try {
  $sql = "
    SELECT
      p.id,
      p.user_id,
      p.kind,
      p.text,
      p.photo_url,
      p.created_at,
      p.town_id,
      u.name as user_name,
      u.surname as user_surname,
      u.amp_id,
      u.gender,
      u.photo as user_pic,
      t.name as town_name
    FROM prayers_posts p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN towns t ON p.town_id = t.id
    WHERE 1=1
  ";

  $params = [];

  if (!$isAdmin && $userTownId > 0) {
    $sql .= " AND (p.town_id = ? OR p.town_id = 0)";
    $params[] = $userTownId;
  }

  $sql .= " ORDER BY p.created_at DESC LIMIT 100";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  foreach ($posts as &$post) {
    $post['created_at'] = date('Y-m-d H:i', strtotime($post['created_at']));
    $post['user_pic'] = $post['user_pic'] ?: '/assets/default-avatar.png';

    // Build username with office title
    $ampId = (int)($post['amp_id'] ?? 10);
    $gender = $post['gender'] ?? 'man';
    $officeTitle = get_translated_office($ampId, $gender, $pageLang);
    $fullName = trim($post['user_name'] . ' ' . $post['user_surname']);
    $post['username'] = $officeTitle . ' ' . $fullName;

    // Clean up fields not needed in response
    unset($post['user_name'], $post['user_surname'], $post['amp_id'], $post['gender']);

    $stmtHeart = $pdo->prepare("SELECT COUNT(*) FROM prayers_reactions WHERE post_id = ? AND type = 'heart'");
    $stmtHeart->execute([$post['id']]);
    $post['heart_count'] = (int)$stmtHeart->fetchColumn();
    
    $stmtPray = $pdo->prepare("SELECT COUNT(*) FROM prayers_reactions WHERE post_id = ? AND type = 'pray'");
    $stmtPray->execute([$post['id']]);
    $post['pray_count'] = (int)$stmtPray->fetchColumn();
    
    $stmtComment = $pdo->prepare("SELECT COUNT(*) FROM prayers_comments WHERE post_id = ?");
    $stmtComment->execute([$post['id']]);
    $post['comment_count'] = (int)$stmtComment->fetchColumn();
    
    $stmtUserHeart = $pdo->prepare("SELECT COUNT(*) FROM prayers_reactions WHERE post_id = ? AND user_id = ? AND type = 'heart'");
    $stmtUserHeart->execute([$post['id'], $userId]);
    $post['user_hearted'] = $stmtUserHeart->fetchColumn() > 0;
    
    $stmtUserPray = $pdo->prepare("SELECT COUNT(*) FROM prayers_reactions WHERE post_id = ? AND user_id = ? AND type = 'pray'");
    $stmtUserPray->execute([$post['id'], $userId]);
    $post['user_prayed'] = $stmtUserPray->fetchColumn() > 0;
    
    $post['can_edit'] = ($post['user_id'] == $userId || $isAdmin);
    $post['can_delete'] = ($post['user_id'] == $userId || $isAdmin);
  }
  
  echo json_encode(['success' => true, 'posts' => $posts]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}