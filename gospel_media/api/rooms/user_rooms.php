<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__, 3) . '/security/auth_gate.php';
require_once dirname(__DIR__, 3) . '/includes/languages.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['error' => 'db_unavailable']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$pageLang = $_SESSION['language'] ?? 'af';

// Helper to format room display name
function formatRoomName(string $type, string $name, string $lang): string {
    $t = strtolower($type);
    // Strip existing Afrikaans type prefix from room name to avoid duplication
    $afPrefixes = ['gemeente' => 'Gemeente ', 'opsienerskap' => 'Opsienerskap ', 'jeug' => 'Jeug ', 'sondagskool' => 'Sondagskool '];
    if (isset($afPrefixes[$t]) && stripos($name, $afPrefixes[$t]) === 0) {
        $name = substr($name, strlen($afPrefixes[$t]));
    }
    if ($t === 'gemeente') return __t('congregation', $lang) . ' ' . $name;
    if ($t === 'opsienerskap') return __t('oversight', $lang) . ' ' . $name;
    if ($t === 'jeug') return __t('youth', $lang) . ' ' . $name;
    if ($t === 'sondagskool') return __t('sunday_school', $lang) . ' ' . $name;
    return $name ?: __t('room', $lang);
}

try {
    // Get user info with birthdate for age calculation
    $st = $pdo->prepare("SELECT amp_id, town_id, congregation_id, birthdate, marital_status FROM users WHERE id = ?");
    $st->execute([$userId]);
    $user = $st->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['auto' => [], 'joined' => []]);
        exit;
    }
    
    $ampId = (int)($user['amp_id'] ?? 999);
    $townId = (int)($user['town_id'] ?? 0);
    $congId = (int)($user['congregation_id'] ?? 0);
    $birthdate = $user['birthdate'] ?? null;
    $maritalStatus = $user['marital_status'] ?? '';
    
    // Calculate age
    $age = 0;
    if ($birthdate) {
        $dob = new DateTime($birthdate);
        $now = new DateTime();
        $age = $now->diff($dob)->y;
    }
    
    $autoRooms = [];
    $joinedRooms = [];
    
    // Apostel: Only opsienerskappe (all joined ones)
    if ($ampId === 1) {
        $sql = "SELECT r.id, r.type, r.name, t.name AS town_name
                FROM room_memberships rm
                JOIN rooms r ON r.id = rm.room_id
                LEFT JOIN towns t ON t.id = r.town_id
                WHERE rm.user_id = ? AND r.type = 'opsienerskap'
                ORDER BY t.name";
        $st = $pdo->prepare($sql);
        $st->execute([$userId]);
        $joinedRooms = $st->fetchAll(PDO::FETCH_ASSOC);
        
    } else {
        // Own gemeente (auto for everyone)
        if ($congId > 0) {
            $sql = "SELECT r.id, r.type, r.name, c.name AS congregation_name
                    FROM rooms r
                    LEFT JOIN congregations c ON c.id = r.gemeente_id
                    WHERE r.type = 'gemeente' AND r.gemeente_id = ?";
            $st = $pdo->prepare($sql);
            $st->execute([$congId]);
            $gemeenteRooms = $st->fetchAll(PDO::FETCH_ASSOC);
            $autoRooms = array_merge($autoRooms, $gemeenteRooms);
        }
        
        // Own opsienerskap (auto for everyone)
        if ($townId > 0) {
            $sql = "SELECT r.id, r.type, r.name, t.name AS town_name
                    FROM rooms r
                    LEFT JOIN towns t ON t.id = r.town_id
                    WHERE r.type = 'opsienerskap' AND r.town_id = ?";
            $st = $pdo->prepare($sql);
            $st->execute([$townId]);
            $opsRooms = $st->fetchAll(PDO::FETCH_ASSOC);
            $autoRooms = array_merge($autoRooms, $opsRooms);
        }
        
        // Sondagskool (auto if under 16)
        if ($age > 0 && $age < 16 && $townId > 0) {
            $sql = "SELECT r.id, r.type, r.name, t.name AS town_name
                    FROM rooms r
                    LEFT JOIN towns t ON t.id = r.town_id
                    WHERE r.type = 'sondagskool' AND r.town_id = ?";
            $st = $pdo->prepare($sql);
            $st->execute([$townId]);
            $ssRooms = $st->fetchAll(PDO::FETCH_ASSOC);
            $autoRooms = array_merge($autoRooms, $ssRooms);
        }
        
        // Jeug (auto if 16-25 and unmarried)
        if ($age >= 16 && $age <= 25 && $maritalStatus === 'ongetroud' && $townId > 0) {
            $sql = "SELECT r.id, r.type, r.name, t.name AS town_name
                    FROM rooms r
                    LEFT JOIN towns t ON t.id = r.town_id
                    WHERE r.type = 'jeug' AND r.town_id = ?";
            $st = $pdo->prepare($sql);
            $st->execute([$townId]);
            $jeugRooms = $st->fetchAll(PDO::FETCH_ASSOC);
            $autoRooms = array_merge($autoRooms, $jeugRooms);
        }
        
        // Joined rooms (explicitly joined via memberships)
        // Exclude auto rooms we already have
        $autoIds = array_map(function($r) { return (int)$r['id']; }, $autoRooms);
        
        if ($autoIds) {
            $placeholders = implode(',', array_fill(0, count($autoIds), '?'));
            $sql = "SELECT r.id, r.type, r.name, 
                           t.name AS town_name,
                           c.name AS congregation_name
                    FROM room_memberships rm
                    JOIN rooms r ON r.id = rm.room_id
                    LEFT JOIN towns t ON t.id = r.town_id
                    LEFT JOIN congregations c ON c.id = r.gemeente_id
                    WHERE rm.user_id = ? AND r.id NOT IN ($placeholders)
                    ORDER BY r.type, r.name";
            $st = $pdo->prepare($sql);
            $st->execute(array_merge([$userId], $autoIds));
        } else {
            $sql = "SELECT r.id, r.type, r.name, 
                           t.name AS town_name,
                           c.name AS congregation_name
                    FROM room_memberships rm
                    JOIN rooms r ON r.id = rm.room_id
                    LEFT JOIN towns t ON t.id = r.town_id
                    LEFT JOIN congregations c ON c.id = r.gemeente_id
                    WHERE rm.user_id = ?
                    ORDER BY r.type, r.name";
            $st = $pdo->prepare($sql);
            $st->execute([$userId]);
        }
        
        $joinedRooms = $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // Apply translations to room display names
    foreach ($autoRooms as &$room) {
        $type = strtolower($room['type'] ?? '');
        // Use congregation_name for gemeente, town_name for others
        if ($type === 'gemeente') {
            $name = $room['congregation_name'] ?? $room['name'] ?? '';
        } else {
            $name = $room['town_name'] ?? $room['name'] ?? '';
        }
        $room['display_name'] = formatRoomName($room['type'] ?? '', $name, $pageLang);
    }
    unset($room);

    foreach ($joinedRooms as &$room) {
        $type = strtolower($room['type'] ?? '');
        // Use congregation_name for gemeente, town_name for others
        if ($type === 'gemeente') {
            $name = $room['congregation_name'] ?? $room['name'] ?? '';
        } else {
            $name = $room['town_name'] ?? $room['name'] ?? '';
        }
        $room['display_name'] = formatRoomName($room['type'] ?? '', $name, $pageLang);
    }
    unset($room);

    echo json_encode([
        'auto' => $autoRooms,
        'joined' => $joinedRooms
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Throwable $e) {
    error_log("User rooms error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error', 'auto' => [], 'joined' => []]);
}