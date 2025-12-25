<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../../../security/auth_gate.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
  http_response_code(401);
  echo json_encode(['success' => false, 'error' => 'Unauthorized']);
  exit;
}

try {
  $stmt = $pdo->prepare('SELECT verse_ref, color FROM bible_highlights WHERE user_id = ?');
  $stmt->execute([$userId]);
  
  $highlights = [];
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $highlights[$row['verse_ref']] = (int)$row['color'];
  }
  
  echo json_encode([
    'success' => true, 
    'highlights' => $highlights,
    'count' => count($highlights)
  ]);
  
} catch (Exception $e) {
  error_log("Highlight load error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Database error']);
}