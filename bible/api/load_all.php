<?php
require_once __DIR__ . '/../../security/auth_gate.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
  http_response_code(401);
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}

try {
  // Load highlights
  $highlightsStmt = $pdo->prepare('SELECT verse_ref, color FROM bible_highlights WHERE user_id = ?');
  $highlightsStmt->execute([$userId]);
  $highlights = [];
  while ($row = $highlightsStmt->fetch(PDO::FETCH_ASSOC)) {
    $highlights[$row['verse_ref']] = (int)$row['color'];
  }
  
  // Load notes
  $notesStmt = $pdo->prepare('SELECT verse_ref, note_text FROM bible_notes WHERE user_id = ?');
  $notesStmt->execute([$userId]);
  $notes = [];
  while ($row = $notesStmt->fetch(PDO::FETCH_ASSOC)) {
    $notes[$row['verse_ref']] = $row['note_text'];
  }
  
  // Load bookmarks
  $bookmarksStmt = $pdo->prepare('
    SELECT verse_ref, verse_text, UNIX_TIMESTAMP(created_at) as timestamp
    FROM bible_bookmarks 
    WHERE user_id = ?
  ');
  $bookmarksStmt->execute([$userId]);
  $bookmarks = [];
  while ($row = $bookmarksStmt->fetch(PDO::FETCH_ASSOC)) {
    $bookmarks[$row['verse_ref']] = [
      'text' => $row['verse_text'],
      'timestamp' => (int)$row['timestamp'] * 1000
    ];
  }
  
  echo json_encode([
    'success' => true,
    'highlights' => $highlights,
    'notes' => $notes,
    'bookmarks' => $bookmarks
  ]);
  
} catch (Exception $e) {
  error_log("Load all error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'Database error']);
}