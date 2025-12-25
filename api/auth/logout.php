<?php
require_once __DIR__ . '/../../security/boot.php';
auth_logout();
header('Content-Type: application/json');
echo json_encode(['ok'=>true,'redirect'=>'/login.php']);
