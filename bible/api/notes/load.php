<?php
require_once __DIR__ . '/../../../security/auth_gate.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
  http_response_code(401);
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}

try {
  $stmt = $pdo->prepare('SELECT verse_ref, note_text FROM bible_notes WHERE user_id = ?');
  $stmt->execute([$userId]);
  
  $notes = [];
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $notes[$row['verse_ref']] = $row['note_text'];
  }
  
  echo json_encode(['success' => true, 'notes' => $notes]);
} catch (Exception $e) {
  error_log("Note load error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'Database error']);
}