<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../security/auth_gate.php';
require_once __DIR__ . '/../../gospel_media/lib/permissions.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'db_unavailable']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

try {
    // Get user info
    $userStmt = $pdo->prepare("SELECT amp_id, town_id, congregation_id FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'rooms' => []]);
        exit;
    }
    
    $ampId = (int)($user['amp_id'] ?? 10);
    $townId = (int)($user['town_id'] ?? 0);
    $congId = (int)($user['congregation_id'] ?? 0);
    
    $rooms = [];
    
    // Get rooms based on permissions
    if ($ampId >= 1 && $ampId <= 5) {
        // Amp 1-5: Opsienerskap rooms
        $stmt = $pdo->prepare("
            SELECT r.id, r.name, r.type, r.town_id
            FROM rooms r
            WHERE r.type = 'opsienerskap' AND r.town_id = ?
            ORDER BY r.name
        ");
        $stmt->execute([$townId]);
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    if ($ampId >= 5 && $ampId <= 9) {
        // Amp 5-9: Own gemeente
        $stmt = $pdo->prepare("
            SELECT r.id, r.name, r.type, r.gemeente_id
            FROM rooms r
            WHERE r.type = 'gemeente' AND r.gemeente_id = ?
            ORDER BY r.name
        ");
        $stmt->execute([$congId]);
        $rooms = array_merge($rooms, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    if ($ampId < 10) {
        // All except Amp 10: Jeug & Sondagskool
        $stmt = $pdo->prepare("
            SELECT r.id, r.name, r.type, r.town_id
            FROM rooms r
            WHERE r.type IN ('jeug', 'sondagskool') AND r.town_id = ?
            ORDER BY r.type, r.name
        ");
        $stmt->execute([$townId]);
        $rooms = array_merge($rooms, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    // Format names
    foreach ($rooms as &$room) {
        $room['id'] = (int)$room['id'];
        if ($room['type'] === 'jeug') {
            $room['name'] = 'Jeug ' . $room['name'];
        } elseif ($room['type'] === 'sondagskool') {
            $room['name'] = 'Sondagskool ' . $room['name'];
        }
    }
    unset($room);
    
    echo json_encode(['success' => true, 'rooms' => $rooms]);
    
} catch (Throwable $e) {
    error_log('Calendar rooms error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'server_error']);
}