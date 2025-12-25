<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Stoor errors in log, nie in output nie

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

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (!$input || !isset($input['verse_ref']) || !isset($input['color'])) {
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Missing required fields']);
  exit;
}

$verseRef = $input['verse_ref'];
$color = (int)$input['color'];

try {
  if ($color === 0) {
    // Remove highlight
    $stmt = $pdo->prepare('DELETE FROM bible_highlights WHERE user_id = ? AND verse_ref = ?');
    $stmt->execute([$userId, $verseRef]);
    
    echo json_encode(['success' => true, 'action' => 'removed']);
  } else {
    // Add/update highlight
    $stmt = $pdo->prepare('
      INSERT INTO bible_highlights (user_id, verse_ref, color)
      VALUES (?, ?, ?)
      ON DUPLICATE KEY UPDATE color = VALUES(color), updated_at = NOW()
    ');
    $stmt->execute([$userId, $verseRef, $color]);
    
    echo json_encode(['success' => true, 'action' => 'saved', 'color' => $color]);
  }
} catch (Exception $e) {
  error_log("Highlight save error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}