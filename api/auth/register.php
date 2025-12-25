<?php
require_once __DIR__ . '/../../security/boot.php';
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

$raw = file_get_contents('php://input'); $payload = [];
if (!empty($raw) && stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) { $payload = json_decode($raw,true) ?: []; } else { $payload = $_POST; }
csrf_validate($payload['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null));

$name = trim($payload['name'] ?? '');
$surname = trim($payload['surname'] ?? '');
$email = trim(strtolower($payload['email'] ?? ''));
$pw = (string)($payload['password'] ?? '');
$pw2 = (string)($payload['password2'] ?? '');

if (!$name || !$surname || !$email || !$pw || !$pw2) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Vul asseblief alle velde in.']); exit; }
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Ongeldige e-pos.']); exit; }
if (strlen($pw) < 6) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Wagwoord moet minstens 6 karakters wees.']); exit; }
if (!hash_equals($pw, $pw2)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Wagwoorde stem nie ooreen nie.']); exit; }

$pdo = db();
try {
  $pdo->beginTransaction();
  $q = $pdo->prepare('SELECT id FROM users WHERE email = :e LIMIT 1'); $q->execute([':e'=>$email]);
  if ($q->fetch()) { throw new RuntimeException('Daardie e-pos bestaan reeds.'); }

  $hash = password_hash($pw, PASSWORD_BCRYPT, ['cost'=>12]);
  $ins = $pdo->prepare('INSERT INTO users (email,password_hash,name,surname,language,status,created_at) VALUES (:e,:h,:n,:s,:lang,:st,NOW())');
  $ins->execute([':e'=>$email, ':h'=>$hash, ':n'=>$name, ':s'=>$surname, ':lang'=>'af', ':st'=>'pending']);
  $pdo->commit();
  echo json_encode(['ok'=>true,'message'=>'Geregistreer. Wag vir goedkeuring.']);
} catch (Throwable $ex) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(400); echo json_encode(['ok'=>false,'error'=>$ex->getMessage()]);
}
