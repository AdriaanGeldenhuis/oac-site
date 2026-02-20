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
      t.name as town_name,
      (SELECT COUNT(*) FROM prayers_reactions r WHERE r.post_id = p.id AND r.type = 'heart') AS heart_count,
      (SELECT COUNT(*) FROM prayers_reactions r WHERE r.post_id = p.id AND r.type = 'pray') AS pray_count,
      (SELECT COUNT(*) FROM prayers_comments c WHERE c.post_id = p.id) AS comment_count,
      (SELECT COUNT(*) FROM prayers_reactions r WHERE r.post_id = p.id AND r.user_id = ? AND r.type = 'heart') AS user_hearted,
      (SELECT COUNT(*) FROM prayers_reactions r WHERE r.post_id = p.id AND r.user_id = ? AND r.type = 'pray') AS user_prayed
    FROM prayers_posts p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN towns t ON p.town_id = t.id
    WHERE p.kind = 'prayer'
  ";

  $params = [$userId, $userId];

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
    $post['user_pic'] = $post['user_pic'] ?: null;

    // Build username with office title
    $ampId = (int)($post['amp_id'] ?? 10);
    $gender = $post['gender'] ?? 'man';
    $officeTitle = get_translated_office($ampId, $gender, $pageLang);
    $fullName = trim($post['user_name'] . ' ' . $post['user_surname']);
    $post['username'] = $officeTitle . ' ' . $fullName;

    // Generate initials
    $nameParts = preg_split('/\s+/', $fullName);
    if (count($nameParts) >= 2) {
        $post['initials'] = strtoupper(mb_substr($nameParts[0], 0, 1) . mb_substr($nameParts[count($nameParts) - 1], 0, 1));
    } elseif (count($nameParts) === 1 && !empty($nameParts[0])) {
        $post['initials'] = strtoupper(mb_substr($nameParts[0], 0, 2));
    } else {
        $post['initials'] = '??';
    }

    // Clean up fields not needed in response
    unset($post['user_name'], $post['user_surname'], $post['amp_id'], $post['gender']);

    $post['heart_count'] = (int)$post['heart_count'];
    $post['pray_count'] = (int)$post['pray_count'];
    $post['comment_count'] = (int)$post['comment_count'];
    $post['user_hearted'] = (int)$post['user_hearted'] > 0;
    $post['user_prayed'] = (int)$post['user_prayed'] > 0;
    $post['can_edit'] = ($post['user_id'] == $userId || $isAdmin);
    $post['can_delete'] = ($post['user_id'] == $userId || $isAdmin);
  }

  echo json_encode(['success' => true, 'posts' => $posts]);
} catch (Exception $e) {
  error_log('Prayers list error: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'server_error']);
}