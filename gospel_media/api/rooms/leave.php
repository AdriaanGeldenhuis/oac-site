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
$roomId = isset($_POST['room_id']) ? (int)$_POST['room_id'] : 0;

if ($roomId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_room']);
    exit;
}

try {
    // Get user info
    $us = $pdo->prepare("SELECT amp_id, town_id, congregation_id FROM users WHERE id=?");
    $us->execute([$userId]);
    $user = $us->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(403);
        echo json_encode(['error' => 'user_not_found']);
        exit;
    }
    
    $ampId = (int)($user['amp_id'] ?? 999);
    $congId = (int)($user['congregation_id'] ?? 0);
    
    // Get room info
    $rs = $pdo->prepare("SELECT type, gemeente_id FROM rooms WHERE id=?");
    $rs->execute([$roomId]);
    $room = $rs->fetch(PDO::FETCH_ASSOC);
    
    if (!$room) {
        http_response_code(404);
        echo json_encode(['error' => 'room_not_found']);
        exit;
    }
    
    $roomType = strtolower($room['type'] ?? '');
    $roomGemeenteId = (int)($room['gemeente_id'] ?? 0);
    
    // RULE 1: Cannot leave opsienerskap rooms (auto-membership) — except Apostle (amp 1) who manually joins
    if ($roomType === 'opsienerskap' && $ampId !== 1) {
        http_response_code(403);
        echo json_encode(['error' => 'cannot_leave_opsienerskap']);
        exit;
    }
    
    // RULE 2: Gemeente leave logic
    if ($roomType === 'gemeente') {
        // Check if this is user's OWN gemeente (from their profile)
        if ($roomGemeenteId === $congId) {
            // Officers (Amp 2-6) CAN leave their own gemeente to join another
            // Members (Amp 7+) CANNOT leave their own gemeente
            if ($ampId >= 7) {
                http_response_code(403);
                echo json_encode(['error' => 'cannot_leave_own_gemeente']);
                exit;
            }
            // Amp 2-6 (Officers) can proceed to leave
        }
        // If it's NOT their own gemeente, anyone can leave (they joined it manually)
    }
    
    // Delete membership
    $del = $pdo->prepare("DELETE FROM room_memberships WHERE room_id=? AND user_id=?");
    $del->execute([$roomId, $userId]);
    
    echo json_encode(['ok' => 1, 'success' => true]);
} catch (Throwable $e) {
    error_log("Room leave error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error', 'message' => $e->getMessage()]);
}