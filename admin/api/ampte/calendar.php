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

try {
    // Verify requester has permission to view this calendar
    // (Must be in same hierarchy scope)
    $requesterStmt = $pdo->prepare("SELECT amp_id, town_id, congregation_id FROM users WHERE id = ? LIMIT 1");
    $requesterStmt->execute([$userId]);
    $requester = $requesterStmt->fetch(PDO::FETCH_ASSOC);
    
    $targetStmt = $pdo->prepare("SELECT amp_id, town_id, congregation_id FROM users WHERE id = ? LIMIT 1");
    $targetStmt->execute([$targetUserId]);
    $target = $targetStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$requester || !$target) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    
    $requesterAmpId = (int)($requester['amp_id'] ?? 0);
    $targetAmpId = (int)($target['amp_id'] ?? 0);
    
    // Check permission
    $hasPermission = false;
    
    if ($userId === $targetUserId) {
        $hasPermission = true; // Can view own calendar
    } elseif ($requesterAmpId === 1) {
        $hasPermission = in_array($targetAmpId, [2,3,4]);
    } elseif (in_array($requesterAmpId, [2,3,4])) {
        $hasPermission = ($requester['town_id'] === $target['town_id']);
    } elseif ($requesterAmpId === 5) {
        $hasPermission = ($requester['town_id'] === $target['town_id'] && $targetAmpId >= 6);
    } elseif ($requesterAmpId === 6) {
        $hasPermission = ($requester['congregation_id'] === $target['congregation_id'] && $targetAmpId >= 7);
    } elseif ($requesterAmpId === 7) {
        $hasPermission = ($requester['congregation_id'] === $target['congregation_id'] && $targetAmpId >= 8);
    }
    
    if (!$hasPermission) {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied']);
        exit;
    }
    
    // Fetch calendar events (future events only)
    $stmt = $pdo->prepare("SELECT id, title, description, start_at, end_at, all_day 
        FROM calendar_events 
        WHERE user_id = ? 
        AND start_at >= CURDATE()
        ORDER BY start_at ASC 
        LIMIT 50");
    $stmt->execute([$targetUserId]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'events' => $events
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}