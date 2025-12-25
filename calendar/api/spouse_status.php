<?php
/**
 * Check if user has a linked spouse
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../security/auth_gate.php';

try {
  $stmt = $pdo->prepare('SELECT spouse_user_id FROM users WHERE id = :uid');
  $stmt->execute(['uid' => $_SESSION['user_id']]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  
  $hasSpouse = !empty($row['spouse_user_id']);
  $spouseId = $hasSpouse ? (int)$row['spouse_user_id'] : null;
  
  echo json_encode([
    'has_spouse' => $hasSpouse, 
    'spouse_id' => $spouseId
  ]);
  
} catch (Throwable $e) {
  error_log('Spouse status error: ' . $e->getMessage());
  echo json_encode(['has_spouse' => false, 'spouse_id' => null]);
}