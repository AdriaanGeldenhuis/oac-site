<?php
declare(strict_types=1);
header('Content-Type: application/json');
require_once __DIR__ . '/../../../security/auth_gate.php';
require_once __DIR__ . '/../notifications/helper.php';

$userId = $_SESSION['user_id'] ?? null;
$requestId = isset($_POST['request_id']) && is_numeric($_POST['request_id']) ? (int)$_POST['request_id'] : 0;

if (!$userId || !$requestId) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

try {
    // Get request - ONLY receiver can reject
    $stmt = $pdo->prepare("SELECT * FROM spouse_requests WHERE id = ? AND receiver_id = ? AND status = 'pending' LIMIT 1");
    $stmt->execute([$requestId, $userId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        http_response_code(404);
        echo json_encode(['error' => 'Request not found or already processed']);
        exit;
    }
    
    $requesterId = (int)$request['requester_id'];
    
    // Update request status
    $stmt = $pdo->prepare("UPDATE spouse_requests SET status = 'rejected', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$requestId]);
    
    // Get user name for notification
    $userStmt = $pdo->prepare("SELECT name, surname FROM users WHERE id = ? LIMIT 1");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    $userName = trim(($user['name'] ?? '') . ' ' . ($user['surname'] ?? ''));
    
    // Notify requester (optional)
    createAdminNotification($requesterId, 'spouse_rejected', [
        'from_id' => $userId,
        'from_name' => $userName
    ]);
    
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}