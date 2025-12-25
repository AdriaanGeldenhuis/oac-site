<?php
require_once __DIR__ . '/../security/auth_gate.php';

header('Content-Type: text/plain');

echo "=== BIBLE SAVE TEST ===\n\n";

$userId = $_SESSION['user_id'] ?? 0;
echo "User ID: $userId\n\n";

if (!$userId) {
  die("ERROR: Not logged in\n");
}

// Test data
$testData = [
  'Genesis-1-1' => 3,
  'Genesis-1-2' => 5
];

$json = json_encode($testData);
echo "Test JSON: $json\n\n";

try {
  // Create table
  $createSql = "CREATE TABLE IF NOT EXISTS bible_user_data (
    user_id INT PRIMARY KEY,
    highlights JSON,
    notes JSON,
    bookmarks JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
  
  $pdo->exec($createSql);
  echo "✅ Table created/verified\n\n";

  // Insert test data
  $stmt = $pdo->prepare('
    INSERT INTO bible_user_data (user_id, highlights, notes, bookmarks)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE highlights = VALUES(highlights)
  ');
  
  $result = $stmt->execute([$userId, $json, '{}', '{}']);
  echo "✅ Insert/Update executed\n";
  echo "Affected rows: " . $stmt->rowCount() . "\n\n";

  // Verify
  $verifyStmt = $pdo->prepare('SELECT highlights FROM bible_user_data WHERE user_id = ?');
  $verifyStmt->execute([$userId]);
  $row = $verifyStmt->fetch(PDO::FETCH_ASSOC);
  
  if ($row) {
    echo "✅ Data found in DB:\n";
    echo $row['highlights'] . "\n\n";
    
    $decoded = json_decode($row['highlights'], true);
    echo "Decoded:\n";
    print_r($decoded);
  } else {
    echo "❌ No data found!\n";
  }

} catch (Exception $e) {
  echo "❌ ERROR: " . $e->getMessage() . "\n";
  echo $e->getTraceAsString();
}