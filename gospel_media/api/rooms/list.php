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
$mine = isset($_GET['mine']) && in_array($_GET['mine'], ['1','true'], true);

try {
    // Get user's town and congregation
    $st = $pdo->prepare("SELECT town_id, congregation_id FROM users WHERE id=?");
    $st->execute([$userId]);
    $user = $st->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode([]);
        exit;
    }
    
    $townId = (int)($user['town_id'] ?? 0);
    $congId = (int)($user['congregation_id'] ?? 0);
    
    if ($mine) {
        $sql = "SELECT r.*, t.name AS town_name, c.name AS congregation_name
                FROM rooms r
                LEFT JOIN towns t ON t.id = r.town_id
                LEFT JOIN congregations c ON c.id = r.gemeente_id
                WHERE r.type != 'gemeenskap'
                AND (
                    (r.type = 'gemeente' AND r.gemeente_id = ?)
                    OR (r.type IN ('opsienerskap', 'jeug', 'sondagskool') AND r.town_id = ?)
                )
                ORDER BY FIELD(r.type, 'gemeente', 'opsienerskap', 'jeug', 'sondagskool'), r.name";
        $st = $pdo->prepare($sql);
        $st->execute([$congId, $townId]);
    } else {
        $sql = "SELECT r.*, t.name AS town_name, c.name AS congregation_name
                FROM rooms r
                LEFT JOIN towns t ON t.id = r.town_id
                LEFT JOIN congregations c ON c.id = r.gemeente_id
                WHERE r.type != 'gemeenskap' 
                ORDER BY FIELD(r.type, 'gemeente', 'opsienerskap', 'jeug', 'sondagskool'), r.name";
        $st = $pdo->query($sql);
    }
    
    $rooms = $st->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rooms, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log("Gospel rooms list error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error']);
}