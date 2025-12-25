<?php
require_once __DIR__ . '/../../security/boot.php';
header('Content-Type: application/json');
$u = auth_current_user();
if (!$u) { http_response_code(401); echo json_encode(['ok'=>false]); exit; }
echo json_encode(['ok'=>true,'user'=>$u]);
