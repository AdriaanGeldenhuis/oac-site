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
$verseText = $input['verse_text'] ?? '';
$action = $input['action'] ?? 'toggle'; // 'add' or 'remove' or 'toggle'

try {
  if ($action === 'remove') {
    // Remove bookmark
    $stmt = $pdo->prepare('DELETE FROM bible_bookmarks WHERE user_id = ? AND verse_ref = ?');
    $stmt->execute([$userId, $verseRef]);
    
    echo json_encode(['success' => true, 'action' => 'removed', 'bookmarked' => false]);
  } elseif ($action === 'add') {
    // Add bookmark
    $stmt = $pdo->prepare('
      INSERT INTO bible_bookmarks (user_id, verse_ref, verse_text)
      VALUES (?, ?, ?)
      ON DUPLICATE KEY UPDATE verse_text = VALUES(verse_text)
    ');
    $stmt->execute([$userId, $verseRef, $verseText]);
    
    echo json_encode(['success' => true, 'action' => 'added', 'bookmarked' => true]);
  } else {
    // Toggle
    $checkStmt = $pdo->prepare('SELECT id FROM bible_bookmarks WHERE user_id = ? AND verse_ref = ?');
    $checkStmt->execute([$userId, $verseRef]);
    
    if ($checkStmt->fetch()) {
      // Exists, remove it
      $stmt = $pdo->prepare('DELETE FROM bible_bookmarks WHERE user_id = ? AND verse_ref = ?');
      $stmt->execute([$userId, $verseRef]);
      
      echo json_encode(['success' => true, 'action' => 'removed', 'bookmarked' => false]);
    } else {
      // Doesn't exist, add it
      $stmt = $pdo->prepare('
        INSERT INTO bible_bookmarks (user_id, verse_ref, verse_text)
        VALUES (?, ?, ?)
      ');
      $stmt->execute([$userId, $verseRef, $verseText]);
      
      echo json_encode(['success' => true, 'action' => 'added', 'bookmarked' => true]);
    }
  }
} catch (Exception $e) {
  error_log("Bookmark save error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Database error']);
}