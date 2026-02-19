<?php
declare(strict_types=1);
header('Content-Type: application/json');
require_once __DIR__ . '/../../../security/auth_gate.php';
require_once __DIR__ . '/../notifications/helper.php';

$userId = $_SESSION['user_id'] ?? null;
$targetUserId = isset($_POST['target_user_id']) && is_numeric($_POST['target_user_id']) ? (int)$_POST['target_user_id'] : 0;
$newAmpId = isset($_POST['new_amp_id']) && is_numeric($_POST['new_amp_id']) ? (int)$_POST['new_amp_id'] : 0;

if (!$userId || !$targetUserId || !$newAmpId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

if ($userId === $targetUserId) {
    http_response_code(400);
    echo json_encode(['error' => 'Cannot change your own role']);
    exit;
}

try {
    // Get requester's amp
    $requesterStmt = $pdo->prepare("SELECT amp_id, town_id, congregation_id FROM users WHERE id = ? LIMIT 1");
    $requesterStmt->execute([$userId]);
    $requester = $requesterStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get target's current amp
    $targetStmt = $pdo->prepare("SELECT amp_id, town_id, congregation_id FROM users WHERE id = ? LIMIT 1");
    $targetStmt->execute([$targetUserId]);
    $target = $targetStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$requester || !$target) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    
    $requesterAmpId = (int)($requester['amp_id'] ?? 0);
    $currentAmpId = (int)($target['amp_id'] ?? 0);
    
    // Verify permission (can only change roles below your level)
    if ($newAmpId <= $requesterAmpId) {
        http_response_code(403);
        echo json_encode(['error' => 'Cannot assign role equal to or above your level']);
        exit;
    }
    
    if ($currentAmpId <= $requesterAmpId) {
        http_response_code(403);
        echo json_encode(['error' => 'Cannot modify user equal to or above your level']);
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
    
    // Update role
    $updateStmt = $pdo->prepare("UPDATE users SET amp_id = ? WHERE id = ?");
    $updateStmt->execute([$newAmpId, $targetUserId]);
    
    // Log the change
    $logStmt = $pdo->prepare("INSERT INTO ampte_hierarchy_log 
        (actor_id, target_user_id, action, old_amp_id, new_amp_id)
        VALUES (?, ?, 'role_change', ?, ?)");
    $logStmt->execute([$userId, $targetUserId, $currentAmpId, $newAmpId]);
    
    // Send notification
    $userStmt = $pdo->prepare("SELECT name, surname FROM users WHERE id = ? LIMIT 1");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    createAdminNotification($targetUserId, 'ampte_change', [
        'new_amp' => 'Amp ' . $newAmpId
    ]);
    
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}