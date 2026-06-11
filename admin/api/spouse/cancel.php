<?php
declare(strict_types=1);
header('Content-Type: application/json');
require_once __DIR__ . '/../../../security/auth_gate.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
$requestId = isset($_POST['request_id']) && is_numeric($_POST['request_id']) ? (int)$_POST['request_id'] : 0;

if (!$userId || !$requestId) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

try {
    // Only the requester can withdraw their own pending request
    $stmt = $pdo->prepare("SELECT id FROM spouse_requests WHERE id = ? AND requester_id = ? AND status = 'pending' LIMIT 1");
    $stmt->execute([$requestId, $userId]);

    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Request not found or already processed']);
        exit;
    }

    $del = $pdo->prepare('DELETE FROM spouse_requests WHERE id = ?');
    $del->execute([$requestId]);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
