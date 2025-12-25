<?php
declare(strict_types=1);
header('Content-Type: application/json');
require_once __DIR__ . '/../../../security/auth_gate.php';
require_once __DIR__ . '/../notifications/helper.php';

$userId = $_SESSION['user_id'] ?? null;
$receiverId = isset($_POST['receiver_id']) && is_numeric($_POST['receiver_id']) ? (int)$_POST['receiver_id'] : 0;

if (!$userId || !$receiverId) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

if ($userId === $receiverId) {
    http_response_code(400);
    echo json_encode(['error' => 'Cannot request yourself']);
    exit;
}

try {
    // Check if requester is already married
    $stmt = $pdo->prepare("SELECT spouse_user_id, marital_status FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $requester = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!empty($requester['spouse_user_id'])) {
        echo json_encode(['error' => 'You are already married / Jy is reeds getroud']);
        exit;
    }
    
    // Check if receiver is already married
    $stmt = $pdo->prepare("SELECT spouse_user_id, marital_status FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$receiverId]);
    $receiver = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$receiver) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    
    if (!empty($receiver['spouse_user_id'])) {
        echo json_encode(['error' => 'This person is already married / Hierdie persoon is reeds getroud']);
        exit;
    }
    
    // Check if request already exists (either direction)
    $checkStmt = $pdo->prepare("SELECT id FROM spouse_requests WHERE 
        ((requester_id = ? AND receiver_id = ?) OR 
         (requester_id = ? AND receiver_id = ?))
        AND status = 'pending' LIMIT 1");
    $checkStmt->execute([$userId, $receiverId, $receiverId, $userId]);
    
    if ($checkStmt->fetch()) {
        echo json_encode(['error' => 'Request already exists / Versoek bestaan reeds']);
        exit;
    }
    
    // Create request
    $stmt = $pdo->prepare("INSERT INTO spouse_requests (requester_id, receiver_id, status) VALUES (?, ?, 'pending')");
    $stmt->execute([$userId, $receiverId]);
    
    // Get requester name
    $userStmt = $pdo->prepare("SELECT name, surname FROM users WHERE id = ? LIMIT 1");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    $requesterName = trim(($user['name'] ?? '') . ' ' . ($user['surname'] ?? ''));
    
    // Create notification
    createAdminNotification($receiverId, 'spouse_request', [
        'from_id' => $userId,
        'from_name' => $requesterName
    ]);
    
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}