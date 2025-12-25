<?php
declare(strict_types=1);
header('Content-Type: application/json');
require_once __DIR__ . '/../../security/auth_gate.php';

$meId = (int)($_SESSION['user_id'] ?? 0);
$targetId = isset($_POST['user_id']) && is_numeric($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

if (!$targetId) {
    echo json_encode(['success' => false, 'error' => 'Invalid user_id']);
    exit;
}

if ($meId === $targetId) {
    echo json_encode(['success' => false, 'error' => 'Cannot delete yourself']);
    exit;
}

try {
    // Get permissions
    $stmt = $pdo->prepare("SELECT amp_id, town_id, congregation_id FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$meId]);
    $me = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("SELECT amp_id, town_id, congregation_id FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$targetId]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$me || !$target) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }
    
    $myAmpId = (int)($me['amp_id'] ?? 10);
    $targetAmpId = (int)($target['amp_id'] ?? 10);
    
    if ($myAmpId >= $targetAmpId) {
        echo json_encode(['success' => false, 'error' => 'Insufficient permissions']);
        exit;
    }
    
    // Check scope
    if ($myAmpId >= 6) {
        if ($me['congregation_id'] !== $target['congregation_id']) {
            echo json_encode(['success' => false, 'error' => 'Outside jurisdiction']);
            exit;
        }
    } elseif ($myAmpId >= 2) {
        if ($me['town_id'] !== $target['town_id']) {
            echo json_encode(['success' => false, 'error' => 'Outside jurisdiction']);
            exit;
        }
    }
    
    $pdo->beginTransaction();
    
    // Delete related records first to avoid foreign key constraints
    
    // 1. Delete from ampte_hierarchy_log (both as actor and target)
    $stmt = $pdo->prepare("DELETE FROM ampte_hierarchy_log WHERE actor_id = ? OR target_user_id = ?");
    $stmt->execute([$targetId, $targetId]);
    
    // 2. Delete spouse requests
    $stmt = $pdo->prepare("DELETE FROM spouse_requests WHERE requester_id = ? OR receiver_id = ?");
    $stmt->execute([$targetId, $targetId]);
    
    // 3. Unlink spouse relationships
    $stmt = $pdo->prepare("UPDATE users SET spouse_user_id = NULL WHERE spouse_user_id = ?");
    $stmt->execute([$targetId]);
    
    // 4. Delete calendar events
    $stmt = $pdo->prepare("DELETE FROM calendar_events WHERE user_id = ?");
    $stmt->execute([$targetId]);
    
    // 5. Delete diary entries
    $stmt = $pdo->prepare("DELETE FROM diaries WHERE user_id = ?");
    $stmt->execute([$targetId]);
    
    // 6. Delete calendar visits
    $stmt = $pdo->prepare("DELETE FROM calendar_visits WHERE user_id = ?");
    $stmt->execute([$targetId]);
    
    // 7. Delete shared calendar events
    $stmt = $pdo->prepare("DELETE FROM calendar_shared_events WHERE owner_user_id = ? OR shared_with_user_id = ?");
    $stmt->execute([$targetId, $targetId]);
    
    // 8. Delete appointments
    $stmt = $pdo->prepare("DELETE FROM ampte_appointments WHERE requester_id = ? OR target_user_id = ?");
    $stmt->execute([$targetId, $targetId]);
    
    // 9. Delete room memberships
    $stmt = $pdo->prepare("DELETE FROM room_memberships WHERE user_id = ?");
    $stmt->execute([$targetId]);
    
    // 10. Delete posts
    $stmt = $pdo->prepare("DELETE FROM posts WHERE user_id = ?");
    $stmt->execute([$targetId]);
    
    // 11. Delete notifications
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ?");
    $stmt->execute([$targetId]);
    
    // 12. Delete comments
    $stmt = $pdo->prepare("DELETE FROM comments WHERE user_id = ?");
    $stmt->execute([$targetId]);
    
    // 13. Delete likes
    $stmt = $pdo->prepare("DELETE FROM likes WHERE user_id = ?");
    $stmt->execute([$targetId]);
    
    // Finally, delete the user
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$targetId]);
    
    $pdo->commit();
    
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Delete user error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}