<?php
declare(strict_types=1);
header('Content-Type: application/json');
require_once __DIR__ . '/../../../security/auth_gate.php';

$userId = $_SESSION['user_id'] ?? null;
$targetUserId = isset($_POST['user_id']) && is_numeric($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

if (!$userId || !$targetUserId) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

if ($userId === $targetUserId) {
    http_response_code(400);
    echo json_encode(['error' => 'Cannot delete yourself']);
    exit;
}

try {
    // Get requester's amp
    $requesterStmt = $pdo->prepare("SELECT amp_id, town_id, congregation_id FROM users WHERE id = ? LIMIT 1");
    $requesterStmt->execute([$userId]);
    $requester = $requesterStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get target's amp
    $targetStmt = $pdo->prepare("SELECT amp_id, town_id, congregation_id, name, surname FROM users WHERE id = ? LIMIT 1");
    $targetStmt->execute([$targetUserId]);
    $target = $targetStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$requester || !$target) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    
    $requesterAmpId = (int)($requester['amp_id'] ?? 0);
    $targetAmpId = (int)($target['amp_id'] ?? 0);
    
    // Verify permission (can only delete users below your level)
    if ($targetAmpId <= $requesterAmpId) {
        http_response_code(403);
        echo json_encode(['error' => 'Cannot delete user equal to or above your level']);
        exit;
    }
    
    // Verify same jurisdiction
    if ($requesterAmpId >= 6) {
        // Congregation scope
        if ($requester['congregation_id'] !== $target['congregation_id']) {
            http_response_code(403);
            echo json_encode(['error' => 'User not in your congregation']);
            exit;
        }
    } elseif ($requesterAmpId >= 2) {
        // Town scope
        if ($requester['town_id'] !== $target['town_id']) {
            http_response_code(403);
            echo json_encode(['error' => 'User not in your town']);
            exit;
        }
    }
    
    // Log the deletion before removing
    $logStmt = $pdo->prepare("INSERT INTO ampte_hierarchy_log 
        (actor_id, target_user_id, action, old_amp_id, reason)
        VALUES (?, ?, 'delete', ?, ?)");
    $logStmt->execute([
        $userId, 
        $targetUserId, 
        $targetAmpId,
        'Deleted by ' . trim(($requester['name'] ?? '') . ' ' . ($requester['surname'] ?? ''))
    ]);
    
    // Delete user (cascades will handle related records)
    $deleteStmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $deleteStmt->execute([$targetUserId]);
    
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}