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
    // Get request details
    $stmt = $pdo->prepare("SELECT * FROM spouse_requests WHERE id = ? AND status = 'pending' LIMIT 1");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        echo json_encode(['error' => 'Request not found']);
        exit;
    }
    
    $requesterId = (int)$request['requester_id'];
    $receiverId = (int)$request['receiver_id'];
    
    // Verify user is involved
    if ($userId !== $requesterId && $userId !== $receiverId) {
        http_response_code(403);
        echo json_encode(['error' => 'Not authorized']);
        exit;
    }
    
    // Update request status
    $updateStmt = $pdo->prepare("UPDATE spouse_requests SET status = 'accepted' WHERE id = ?");
    $updateStmt->execute([$requestId]);
    
    // Link spouses in users table
    $linkStmt = $pdo->prepare("UPDATE users SET spouse_user_id = CASE 
        WHEN id = ? THEN ? 
        WHEN id = ? THEN ? 
        END WHERE id IN (?, ?)");
    $linkStmt->execute([$requesterId, $receiverId, $receiverId, $requesterId, $requesterId, $receiverId]);
    
    // Send notification to other party
    $otherId = ($userId === $requesterId) ? $receiverId : $requesterId;
    $userStmt = $pdo->prepare("SELECT name, surname FROM users WHERE id = ? LIMIT 1");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    createAdminNotification($otherId, 'spouse_accepted', [
        'from_name' => trim(($user['name'] ?? '') . ' ' . ($user['surname'] ?? ''))
    ]);
    
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}