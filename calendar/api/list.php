<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../security/auth_gate.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode([]);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Get user info
$userStmt = $pdo->prepare("SELECT amp_id, town_id, congregation_id FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode([]);
    exit;
}

$ampId = (int)($user['amp_id'] ?? 10);
$townId = (int)($user['town_id'] ?? 0);
$congId = (int)($user['congregation_id'] ?? 0);

try {
    $events = [];
    
    // Get accessible room IDs based on permissions
    $accessibleRooms = [];
    
    // Amp 1-5: Opsienerskap rooms
    if ($ampId >= 1 && $ampId <= 5) {
        $stmt = $pdo->prepare("SELECT id FROM rooms WHERE type = 'opsienerskap' AND town_id = ?");
        $stmt->execute([$townId]);
        while ($row = $stmt->fetch()) {
            $accessibleRooms[] = (int)$row['id'];
        }
    }
    
    // Amp 5-9: Own gemeente
    if ($ampId >= 5 && $ampId <= 9) {
        $stmt = $pdo->prepare("SELECT id FROM rooms WHERE type = 'gemeente' AND gemeente_id = ?");
        $stmt->execute([$congId]);
        while ($row = $stmt->fetch()) {
            $accessibleRooms[] = (int)$row['id'];
        }
    }
    
    // Amp <10: Jeug & Sondagskool
    if ($ampId < 10) {
        $stmt = $pdo->prepare("SELECT id FROM rooms WHERE type IN ('jeug', 'sondagskool') AND town_id = ?");
        $stmt->execute([$townId]);
        while ($row = $stmt->fetch()) {
            $accessibleRooms[] = (int)$row['id'];
        }
    }
    
    // Get events from accessible rooms
    if ($accessibleRooms) {
        $placeholders = implode(',', array_fill(0, count($accessibleRooms), '?'));
        $sql = "
            SELECT 
                p.id,
                p.text AS title,
                DATE(p.event_at) AS date,
                TIME_FORMAT(p.event_at, '%H:%i') AS time,
                p.event_place AS location,
                p.room_id,
                r.type AS room_type,
                r.town_id AS room_town_id,
                r.gemeente_id AS room_gemeente_id,
                p.user_id,
                'event' AS type,
                0 AS is_shared
            FROM posts p
            JOIN rooms r ON r.id = p.room_id
            WHERE p.type = 'event' 
              AND p.event_at IS NOT NULL
              AND p.room_id IN ($placeholders)
            ORDER BY p.event_at ASC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($accessibleRooms);
        $events = array_merge($events, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    // Own diary entries
    $diaryStmt = $pdo->prepare("
        SELECT 
            id,
            title,
            date,
            time,
            body AS notes,
            'diary' AS type,
            0 AS is_shared,
            NULL AS room_id,
            NULL AS room_type,
            NULL AS room_town_id,
            NULL AS room_gemeente_id,
            user_id
        FROM diaries 
        WHERE user_id = ?
        ORDER BY date ASC, time ASC
    ");
    $diaryStmt->execute([$userId]);
    $events = array_merge($events, $diaryStmt->fetchAll(PDO::FETCH_ASSOC));
    
    // Own visits
    $visitStmt = $pdo->prepare("
        SELECT 
            id,
            title,
            visit_date AS date,
            visit_time AS time,
            location,
            notes,
            linked_user_id,
            person_name,
            'visit' AS type,
            0 AS is_shared,
            NULL AS room_id,
            NULL AS room_type,
            NULL AS room_town_id,
            NULL AS room_gemeente_id,
            user_id
        FROM calendar_visits 
        WHERE user_id = ?
        ORDER BY visit_date ASC, visit_time ASC
    ");
    $visitStmt->execute([$userId]);
    $events = array_merge($events, $visitStmt->fetchAll(PDO::FETCH_ASSOC));
    
    // Shared events from spouse
    $spouseStmt = $pdo->prepare("SELECT spouse_user_id FROM users WHERE id = ?");
    $spouseStmt->execute([$userId]);
    $spouseRow = $spouseStmt->fetch();
    
    if ($spouseRow && $spouseRow['spouse_user_id']) {
        $spouseId = (int)$spouseRow['spouse_user_id'];
        
        // Shared events
        $sharedEventStmt = $pdo->prepare("
            SELECT 
                p.id,
                p.text AS title,
                DATE(p.event_at) AS date,
                TIME_FORMAT(p.event_at, '%H:%i') AS time,
                p.event_place AS location,
                p.room_id,
                r.type AS room_type,
                r.town_id AS room_town_id,
                r.gemeente_id AS room_gemeente_id,
                p.user_id,
                'event' AS type,
                1 AS is_shared
            FROM calendar_shared_events cse
            JOIN posts p ON p.id = cse.event_id
            JOIN rooms r ON r.id = p.room_id
            WHERE cse.shared_with_user_id = ? 
              AND cse.event_type = 'event'
              AND p.event_at IS NOT NULL
        ");
        $sharedEventStmt->execute([$userId]);
        $events = array_merge($events, $sharedEventStmt->fetchAll(PDO::FETCH_ASSOC));
        
        // Shared diaries
        $sharedDiaryStmt = $pdo->prepare("
            SELECT 
                d.id,
                d.title,
                d.date,
                d.time,
                d.body AS notes,
                'diary' AS type,
                1 AS is_shared,
                NULL AS room_id,
                NULL AS room_type,
                NULL AS room_town_id,
                NULL AS room_gemeente_id,
                d.user_id
            FROM calendar_shared_events cse
            JOIN diaries d ON d.id = cse.event_id
            WHERE cse.shared_with_user_id = ? 
              AND cse.event_type = 'diary'
        ");
        $sharedDiaryStmt->execute([$userId]);
        $events = array_merge($events, $sharedDiaryStmt->fetchAll(PDO::FETCH_ASSOC));
        
        // Shared visits
        $sharedVisitStmt = $pdo->prepare("
            SELECT 
                v.id,
                v.title,
                v.visit_date AS date,
                v.visit_time AS time,
                v.location,
                v.notes,
                v.linked_user_id,
                v.person_name,
                'visit' AS type,
                1 AS is_shared,
                NULL AS room_id,
                NULL AS room_type,
                NULL AS room_town_id,
                NULL AS room_gemeente_id,
                v.user_id
            FROM calendar_shared_events cse
            JOIN calendar_visits v ON v.id = cse.event_id
            WHERE cse.shared_with_user_id = ? 
              AND cse.event_type = 'visit'
        ");
        $sharedVisitStmt->execute([$userId]);
        $events = array_merge($events, $sharedVisitStmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    // Sort by date and time
    usort($events, function($a, $b) {
        $cmp = strcmp($a['date'], $b['date']);
        if ($cmp === 0) {
            $timeA = $a['time'] ?? '00:00';
            $timeB = $b['time'] ?? '00:00';
            return strcmp($timeA, $timeB);
        }
        return $cmp;
    });
    
    // Format data
    foreach ($events as &$event) {
        $event['id'] = (int)$event['id'];
        $event['user_id'] = (int)($event['user_id'] ?? 0);
        $event['is_shared'] = (int)$event['is_shared'];
        $event['room_id'] = isset($event['room_id']) ? (int)$event['room_id'] : null;
        $event['room_town_id'] = isset($event['room_town_id']) ? (int)$event['room_town_id'] : null;
        $event['room_gemeente_id'] = isset($event['room_gemeente_id']) ? (int)$event['room_gemeente_id'] : null;
        $event['linked_user_id'] = isset($event['linked_user_id']) ? (int)$event['linked_user_id'] : null;
    }
    unset($event);
    
    echo json_encode($events, JSON_UNESCAPED_UNICODE);
    
} catch (Throwable $e) {
    error_log('Calendar list error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([]);
}