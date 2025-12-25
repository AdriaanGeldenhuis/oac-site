<?php
/**
 * Get pending spouse requests for current user
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../security/auth_gate.php';

try {
  $stmt = $pdo->prepare('
    SELECT 
      sr.id, 
      sr.requester_id, 
      sr.receiver_id,
      sr.status,
      sr.created_at,
      u.name, 
      u.surname,
      u.photo
    FROM spouse_requests sr
    JOIN users u ON u.id = sr.requester_id
    WHERE sr.receiver_id = :uid AND sr.status = "pending"
    ORDER BY sr.created_at DESC
  ');
  $stmt->execute(['uid' => $_SESSION['user_id']]);
  
  $requests = [];
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $requests[] = [
      'id' => (int)$row['id'],
      'requester_id' => (int)$row['requester_id'],
      'name' => $row['name'],
      'surname' => $row['surname'],
      'photo' => $row['photo'],
      'created_at' => $row['created_at']
    ];
  }
  
  echo json_encode(['success' => true, 'requests' => $requests]);
  
} catch (Throwable $e) {
  error_log('Spouse requests error: ' . $e->getMessage());
  echo json_encode(['success' => false, 'requests' => []]);
}