<?php
require_once __DIR__ . '/../../security/auth_gate.php';
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
  http_response_code(401);
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Start or update reading plan
  $input = json_decode(file_get_contents('php://input'), true);
  $planType = $input['plan_type'] ?? '';
  
  if (!isset(READING_PLANS[$planType])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid plan type']);
    exit;
  }
  
  try {
    $progress = json_encode(['day' => 1, 'started_at' => date('Y-m-d')]);
    
    $stmt = $pdo->prepare('
      INSERT INTO bible_user_data (user_id, reading_plan, reading_progress, updated_at)
      VALUES (?, ?, ?, NOW())
      ON DUPLICATE KEY UPDATE
        reading_plan = VALUES(reading_plan),
        reading_progress = VALUES(reading_progress),
        updated_at = NOW()
    ');
    $stmt->execute([$userId, $planType, $progress]);
    
    echo json_encode(['success' => true]);
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save plan']);
  }
  
} else {
  // Get current reading plan
  try {
    $stmt = $pdo->prepare('
      SELECT reading_plan, reading_progress FROM bible_user_data WHERE user_id = ?
    ');
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row && $row['reading_plan']) {
      $plan = READING_PLANS[$row['reading_plan']] ?? null;
      $progress = json_decode($row['reading_progress'] ?? '{}', true);
      
      echo json_encode([
        'success' => true,
        'plan' => $plan,
        'progress' => $progress
      ]);
    } else {
      echo json_encode([
        'success' => true,
        'plan' => null,
        'progress' => null
      ]);
    }
  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
  }
}