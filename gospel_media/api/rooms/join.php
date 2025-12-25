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
    $townId = (int)($user['town_id'] ?? 0);
    $congId = (int)($user['congregation_id'] ?? 0);
    
    // Get room info
    $rs = $pdo->prepare("SELECT r.*, 
                                CASE 
                                    WHEN r.type = 'gemeente' THEN (SELECT town_id FROM congregations WHERE id = r.gemeente_id)
                                    ELSE r.town_id
                                END AS room_town_id
                         FROM rooms r 
                         WHERE r.id=?");
    $rs->execute([$roomId]);
    $room = $rs->fetch(PDO::FETCH_ASSOC);
    
    if (!$room) {
        http_response_code(404);
        echo json_encode(['error' => 'room_not_found']);
        exit;
    }
    
    $roomType = strtolower($room['type'] ?? '');
    $roomTownId = (int)($room['room_town_id'] ?? 0);
    
    // RULE 1: Apostel can ONLY join opsienerskappe (nothing else)
    if ($ampId === 1) {
        if ($roomType !== 'opsienerskap') {
            http_response_code(403);
            echo json_encode(['error' => 'apostel_only_opsienerskap']);
            exit;
        }
        // Apostel can join any opsienerskap (any town)
    }
    
    // RULE 2: Gemeentes are auto-member only (cannot join manually)
    if ($roomType === 'gemeente') {
        // Check if this is user's own gemeente
        if ((int)($room['gemeente_id'] ?? 0) === $congId) {
            // Already auto-member
            echo json_encode(['ok' => 1, 'success' => true, 'message' => 'already_member']);
            exit;
        }
        
        // For amp 2-6: can join OTHER gemeentes in SAME town
        if ($ampId >= 2 && $ampId <= 6) {
            // Check town match
            if ($roomTownId !== $townId) {
                http_response_code(403);
                echo json_encode(['error' => 'wrong_town']);
                exit;
            }
            // Allow join
        } else {
            // Amp 7+ cannot join other gemeentes
            http_response_code(403);
            echo json_encode(['error' => 'gemeente_join_not_allowed']);
            exit;
        }
    }
    
    // RULE 3: Opsienerskap - only apostel (amp 1) can join, already handled above
    if ($roomType === 'opsienerskap') {
        if ($ampId !== 1) {
            // Non-apostels get auto-membership to their own town's opsienerskap
            // They should never be able to manually join
            http_response_code(403);
            echo json_encode(['error' => 'opsienerskap_auto_only']);
            exit;
        }
    }
    
    // RULE 4: Jeug/Sondagskool - must be in same town
    if (in_array($roomType, ['jeug', 'sondagskool'], true)) {
        if ($roomTownId !== $townId) {
            http_response_code(403);
            echo json_encode(['error' => 'wrong_town']);
            exit;
        }
    }
    
    // RULE 5: All rooms must be in user's town (except apostel with opsienerskappe)
    if ($ampId !== 1) { // Non-apostels
        if ($roomTownId > 0 && $roomTownId !== $townId) {
            http_response_code(403);
            echo json_encode(['error' => 'wrong_town']);
            exit;
        }
    }
    
    // Insert membership (ignore if already exists)
    $ins = $pdo->prepare("INSERT IGNORE INTO room_memberships (room_id, user_id, joined_at) VALUES (?, ?, NOW())");
    $ins->execute([$roomId, $userId]);
    
    echo json_encode(['ok' => 1, 'success' => true]);
} catch (Throwable $e) {
    error_log("Room join error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error', 'message' => $e->getMessage()]);
}