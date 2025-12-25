<?php
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/../../security/auth_gate.php';

// Check permissions (same logic as approve_user.php)
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$stmt = $pdo->prepare("SELECT amp_id, town_id, congregation_id FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$approver = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$approver) {
    http_response_code(403);
    echo json_encode(['error' => 'Approver not found']);
    exit;
}

$ampId = (int)($approver['amp_id'] ?? 0);
$canApprove = ($ampId >= 1 && $ampId <= 6);

if (!$canApprove) {
    http_response_code(403);
    echo json_encode(['error' => 'Insufficient permissions']);
    exit;
}

$targetUserId = (int)($_POST['user_id'] ?? 0);
if (!$targetUserId) {
    http_response_code(400);
    echo json_encode(['error' => 'User ID required']);
    exit;
}

$stmt = $pdo->prepare("SELECT amp_id, town_id, congregation_id FROM users WHERE id = ? AND status = 'pending' LIMIT 1");
$stmt->execute([$targetUserId]);
$target = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$target) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found or already processed']);
    exit;
}

// Verify jurisdiction (same logic as approve)
$targetAmpId = (int)($target['amp_id'] ?? 0);
$hasJurisdiction = false;

if ($ampId === 6) {
    $hasJurisdiction = in_array($targetAmpId, [7,8,9,10], true) 
        && $target['congregation_id'] === $approver['congregation_id'];
} elseif ($ampId === 5) {
    $hasJurisdiction = in_array($targetAmpId, [5,6], true) 
        && $target['town_id'] === $approver['town_id'];
} elseif ($ampId >= 2 && $ampId <= 4) {
    $hasJurisdiction = in_array($targetAmpId, [2,3,4,5], true) 
        && $target['town_id'] === $approver['town_id'];
} elseif ($ampId === 1) {
    $hasJurisdiction = ($targetAmpId === 1);
}

if (!$hasJurisdiction) {
    http_response_code(403);
    echo json_encode(['error' => 'Outside jurisdiction']);
    exit;
}

// Reject user
try {
    $stmt = $pdo->prepare("UPDATE users SET status = 'rejected' WHERE id = ?");
    $stmt->execute([$targetUserId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'User rejected successfully'
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}