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
  $stmt = $pdo->prepare('
    SELECT verse_ref, verse_text, UNIX_TIMESTAMP(created_at) as timestamp
    FROM bible_bookmarks 
    WHERE user_id = ?
    ORDER BY created_at DESC
  ');
  $stmt->execute([$userId]);
  
  $bookmarks = [];
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $bookmarks[$row['verse_ref']] = [
      'text' => $row['verse_text'],
      'timestamp' => (int)$row['timestamp'] * 1000 // Convert to milliseconds
    ];
  }
  
  echo json_encode(['success' => true, 'bookmarks' => $bookmarks]);
} catch (Exception $e) {
  error_log("Bookmark load error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'Database error']);
}