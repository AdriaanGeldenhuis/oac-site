<?php
declare(strict_types=1);
require_once __DIR__ . '/../../security/auth_gate.php';

// Slegs ampte 0-8 (Admin t/m Hulpkrag) mag liedjies speel.
$stmt = $pdo->prepare("SELECT amp_id FROM users WHERE id = ? LIMIT 1");
$stmt->execute([(int)$_SESSION['user_id']]);
$ampId = $stmt->fetchColumn();
session_write_close();

if ($ampId === false || $ampId === null || (int)$ampId < 0 || (int)$ampId > 8) {
    http_response_code(403);
    exit;
}

$song = $_GET['song'] ?? '';
if (!is_string($song) || !preg_match('/^[0-9]{1,4}$/', $song) || (int)$song < 1) {
    http_response_code(400);
    exit;
}

$path = __DIR__ . '/../media/' . sprintf('%03d', (int)$song) . '.mid';
if (!is_file($path)) {
    http_response_code(404);
    exit;
}

header('Content-Type: audio/midi');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=86400');
readfile($path);
