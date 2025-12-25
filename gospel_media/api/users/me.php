<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 3) . '/security/auth_gate.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['error' => 'db_unavailable']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

try {
    $st = $pdo->prepare("SELECT id, name, surname, amp_id, photo, gender FROM users WHERE id = ? LIMIT 1");
    $st->execute([$userId]);
    $user = $st->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'not_found']);
        exit;
    }
    
    echo json_encode($user, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("Gospel me error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error']);
}