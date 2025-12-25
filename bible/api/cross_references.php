<?php
require_once __DIR__ . '/../../security/auth_gate.php';

header('Content-Type: application/json');

$book = $_GET['book'] ?? '';
$chapter = (int)($_GET['chapter'] ?? 0);
$verse = (int)($_GET['verse'] ?? 0);

if (!$book || !$chapter || !$verse) {
  http_response_code(400);
  echo json_encode(['error' => 'Missing parameters']);
  exit;
}

try {
  $stmt = $pdo->prepare('
    SELECT to_book, to_chapter, to_verse, relationship_type
    FROM bible_cross_references
    WHERE from_book = ? AND from_chapter = ? AND from_verse = ?
  ');
  $stmt->execute([$book, $chapter, $verse]);
  
  $references = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  echo json_encode([
    'success' => true,
    'count' => count($references),
    'references' => $references
  ]);

} catch (Exception $e) {
  error_log("Cross-ref error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'Database error', 'details' => $e->getMessage()]);
}