<?php
require_once __DIR__ . '/../../../security/auth_gate.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'error' => 'Method not allowed']);
  exit;
}

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
  http_response_code(401);
  echo json_encode(['success' => false, 'error' => 'Unauthorized']);
  exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['verse_ref'])) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Missing verse_ref']);
  exit;
}

$verseRef = $input['verse_ref'];
$noteText = trim($input['note_text'] ?? '');

if (strlen($verseRef) > 100) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Invalid verse_ref']);
  exit;
}
if (strlen($noteText) > 10000) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Note too long (max 10000 characters)']);
  exit;
}

try {
  if (empty($noteText)) {
    // Delete note
    $stmt = $pdo->prepare('DELETE FROM bible_notes WHERE user_id = ? AND verse_ref = ?');
    $stmt->execute([$userId, $verseRef]);
    
    echo json_encode(['success' => true, 'action' => 'deleted']);
  } else {
    // Save/update note
    $stmt = $pdo->prepare('
      INSERT INTO bible_notes (user_id, verse_ref, note_text)
      VALUES (?, ?, ?)
      ON DUPLICATE KEY UPDATE note_text = VALUES(note_text), updated_at = NOW()
    ');
    $stmt->execute([$userId, $verseRef, $noteText]);
    
    echo json_encode(['success' => true, 'action' => 'saved']);
  }
} catch (Exception $e) {
  error_log("Note save error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Database error']);
}