<?php
require_once __DIR__ . '/../../auth_gate.php';
header('Content-Type: application/json');
echo json_encode(['ok'=>true,'uid'=>$_SESSION['uid'] ?? null]);
