<?php
require_once __DIR__ . '/../../security/boot.php';
require_once __DIR__ . '/../../security/ratelimit.php';
header('Content-Type: application/json');

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (!rate_limit_bump('login:'.$ip, 25, 300)) {
  http_response_code(429); echo json_encode(['ok'=>false,'error'=>'Te veel pogings. Wag ’n bietjie.']); exit;
}

$raw = file_get_contents('php://input'); $payload = [];
if (!empty($raw) && stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) { $payload = json_decode($raw,true) ?: []; } else { $payload = $_POST; }
csrf_validate($payload['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null));

$email = trim(strtolower($payload['email'] ?? ''));
$pass = (string)($payload['password'] ?? '');
$remember = !empty($payload['remember']);

if (!$email || !$pass) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Email en wagwoord is verpligtend.']); exit; }

[$ok,$msg] = auth_login($email,$pass,$remember);
if (!$ok) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }
echo json_encode(['ok'=>true,'redirect'=>'/WELCOME.php']);
