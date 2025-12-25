<?php
require_once __DIR__ . '/../../security/auth_gate.php';
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'Method not allowed']);
  exit;
}

$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) {
  http_response_code(401);
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$verseRef = $input['verse_ref'] ?? '';
$verseText = $input['verse_text'] ?? '';
$question = $input['question'] ?? '';
$lang = $input['lang'] ?? 'af';

if (!$verseRef || !$verseText) {
  http_response_code(400);
  echo json_encode(['error' => 'Missing required fields']);
  exit;
}

try {
  // Build prompt
  $systemPrompt = $lang === 'af' 
    ? "Jy is 'n Bybelse geleerde wat diepgaande insigte gee oor Bybeltekste. Antwoord in Afrikaans."
    : "You are a Biblical scholar providing deep insights on Bible verses. Answer in English.";
  
  $userPrompt = $lang === 'af'
    ? "Vers: {$verseRef}\nTeks: \"{$verseText}\"\n\n" . ($question ? "Vraag: {$question}" : "Gee 'n kort verduideliking van hierdie vers.")
    : "Verse: {$verseRef}\nText: \"{$verseText}\"\n\n" . ($question ? "Question: {$question}" : "Provide a brief explanation of this verse.");

  // Call OpenAI API
  $ch = curl_init(OPENAI_API_URL);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . OPENAI_API_KEY
  ]);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'model' => OPENAI_MODEL,
    'messages' => [
      ['role' => 'system', 'content' => $systemPrompt],
      ['role' => 'user', 'content' => $userPrompt]
    ],
    'max_tokens' => 500,
    'temperature' => 0.7
  ]));

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($httpCode !== 200) {
    throw new Exception('OpenAI API error: ' . $httpCode);
  }

  $result = json_decode($response, true);
  $answer = $result['choices'][0]['message']['content'] ?? '';

  if (!$answer) {
    throw new Exception('Empty response from OpenAI');
  }

  // Save to database
  $stmt = $pdo->prepare('
    INSERT INTO bible_ai_commentary (user_id, verse_ref, question, answer)
    VALUES (?, ?, ?, ?)
  ');
  $stmt->execute([$userId, $verseRef, $question, $answer]);

  echo json_encode([
    'success' => true,
    'answer' => $answer
  ]);

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()]);
}