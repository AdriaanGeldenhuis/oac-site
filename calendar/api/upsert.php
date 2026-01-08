<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../security/auth_gate.php';
require_once __DIR__ . '/notifications/helper.php';
require_once __DIR__ . '/../../gospel_media/api/notifications/helper.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'db_unavailable']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'method_not_allowed']);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$type = trim($_POST['type'] ?? 'event');
$title = trim($_POST['title'] ?? '');
$date = trim($_POST['date'] ?? '');
$time = trim($_POST['time'] ?? '');
$notes = trim($_POST['notes'] ?? '');
$shareWithSpouse = isset($_POST['share_with_spouse']) && $_POST['share_with_spouse'] === '1';

if ($title === '' || $date === '') {
    echo json_encode(['success' => false, 'error' => 'title_and_date_required']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Get user info
$userStmt = $pdo->prepare("SELECT amp_id, town_id, congregation_id, name, surname FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'user_not_found']);
    exit;
}

$ampId = (int)($user['amp_id'] ?? 10);
$townId = (int)($user['town_id'] ?? 0);
$congId = (int)($user['congregation_id'] ?? 0);
$userName = trim(($user['name'] ?? '') . ' ' . ($user['surname'] ?? ''));

try {
    $pdo->beginTransaction();
    
    $insertedId = $id;
    
    if ($type === 'event') {
        $roomId = isset($_POST['room_id']) ? (int)$_POST['room_id'] : null;
        
        if (!$roomId && $id === 0) {
            throw new Exception('room_required');
        }
        
        // Check room permissions
        if ($roomId) {
            $roomStmt = $pdo->prepare("SELECT type, town_id, gemeente_id FROM rooms WHERE id = ?");
            $roomStmt->execute([$roomId]);
            $room = $roomStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$room) {
                throw new Exception('invalid_room');
            }
            
            $canPost = false;
            
            // Amp 10: read-only
            if ($ampId === 10) {
                $canPost = false;
            }
            // Amp 1-5: opsienerskap
            elseif ($ampId >= 1 && $ampId <= 5 && $room['type'] === 'opsienerskap' && $room['town_id'] == $townId) {
                $canPost = true;
            }
            // Amp 5-9: gemeente
            elseif ($ampId >= 5 && $ampId <= 9 && $room['type'] === 'gemeente' && $room['gemeente_id'] == $congId) {
                $canPost = true;
            }
            // Amp <10: jeug/sondagskool
            elseif ($ampId < 10 && in_array($room['type'], ['jeug', 'sondagskool']) && $room['town_id'] == $townId) {
                $canPost = true;
            }
            
            if (!$canPost) {
                throw new Exception('no_permission_for_room');
            }
        }
        
        $eventAt = $date . ($time ? ' ' . $time : ' 00:00:00');
        
        if ($id > 0) {
            // Update
            $stmt = $pdo->prepare("
                UPDATE posts 
                SET text = :title, event_at = :event_at 
                WHERE id = :id AND user_id = :uid AND type = 'event'
            ");
            $stmt->execute([
                'title' => $title,
                'event_at' => $eventAt,
                'id' => $id,
                'uid' => $userId
            ]);
        } else {
            // Insert
            $stmt = $pdo->prepare("
                INSERT INTO posts (room_id, user_id, type, text, event_at, created_at) 
                VALUES (:room_id, :uid, 'event', :title, :event_at, NOW())
            ");
            $stmt->execute([
                'room_id' => $roomId,
                'uid' => $userId,
                'title' => $title,
                'event_at' => $eventAt
            ]);
            $insertedId = (int)$pdo->lastInsertId();
        }

    } elseif ($type === 'diary') {
        if ($id > 0) {
            $stmt = $pdo->prepare("
                UPDATE diaries 
                SET title = :title, date = :date, time = :time, body = :notes 
                WHERE id = :id AND user_id = :uid
            ");
            $stmt->execute([
                'title' => $title,
                'date' => $date,
                'time' => $time,
                'notes' => $notes,
                'id' => $id,
                'uid' => $userId
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO diaries (user_id, title, date, time, body, created_at) 
                VALUES (:uid, :title, :date, :time, :notes, NOW())
            ");
            $stmt->execute([
                'uid' => $userId,
                'title' => $title,
                'date' => $date,
                'time' => $time,
                'notes' => $notes
            ]);
            $insertedId = (int)$pdo->lastInsertId();
        }
        
    } elseif ($type === 'visit') {
        $linkedUserId = !empty($_POST['linked_user_id']) ? (int)$_POST['linked_user_id'] : null;
        $personName = trim($_POST['person_name'] ?? '');
        $location = trim($_POST['location'] ?? '');
        
        if ($id > 0) {
            $stmt = $pdo->prepare("
                UPDATE calendar_visits 
                SET title = :title, 
                    visit_date = :date, 
                    visit_time = :time, 
                    linked_user_id = :linked, 
                    person_name = :person, 
                    location = :loc, 
                    notes = :notes 
                WHERE id = :id AND user_id = :uid
            ");
            $stmt->execute([
                'title' => $title,
                'date' => $date,
                'time' => $time,
                'linked' => $linkedUserId,
                'person' => $personName,
                'loc' => $location,
                'notes' => $notes,
                'id' => $id,
                'uid' => $userId
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO calendar_visits 
                (user_id, title, visit_date, visit_time, linked_user_id, person_name, location, notes, created_at) 
                VALUES (:uid, :title, :date, :time, :linked, :person, :loc, :notes, NOW())
            ");
            $stmt->execute([
                'uid' => $userId,
                'title' => $title,
                'date' => $date,
                'time' => $time,
                'linked' => $linkedUserId,
                'person' => $personName,
                'loc' => $location,
                'notes' => $notes
            ]);
            $insertedId = (int)$pdo->lastInsertId();
            
            // 🔔 NOTIFY LINKED USER IF VISIT
            if ($linkedUserId) {
                createCalendarNotification($linkedUserId, 'visit_scheduled', [
                    'person_name' => $userName,
                    'date' => $date
                ]);
            }
        }
    }
    
    // Handle spouse sharing
    if ($shareWithSpouse && $insertedId) {
        $spouseStmt = $pdo->prepare("SELECT spouse_user_id FROM users WHERE id = ?");
        $spouseStmt->execute([$userId]);
        $spouseRow = $spouseStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!empty($spouseRow['spouse_user_id'])) {
            $spouseId = (int)$spouseRow['spouse_user_id'];
            
            // Delete existing share
            $delStmt = $pdo->prepare("DELETE FROM calendar_shared_events WHERE event_id = ? AND event_type = ?");
            $delStmt->execute([$insertedId, $type]);
            
            // Insert new share
            $shareStmt = $pdo->prepare("
                INSERT INTO calendar_shared_events 
                (event_id, event_type, owner_user_id, shared_with_user_id, created_at) 
                VALUES (:eid, :type, :owner, :shared, NOW())
            ");
            $shareStmt->execute([
                'eid' => $insertedId,
                'type' => $type,
                'owner' => $userId,
                'shared' => $spouseId
            ]);
            
            // 🔔 NOTIFY SPOUSE
            if ($id === 0) { // Only notify on new event
                createCalendarNotification($spouseId, 'event_shared', [
                    'from_name' => $userName,
                    'event_title' => $title
                ]);
            }
        }
    } else if (!$shareWithSpouse && $insertedId) {
        // Remove sharing
        $delStmt = $pdo->prepare("DELETE FROM calendar_shared_events WHERE event_id = ? AND event_type = ? AND owner_user_id = ?");
        $delStmt->execute([$insertedId, $type, $userId]);
    }
    
    $pdo->commit();

    // Send response IMMEDIATELY so UI updates fast
    echo json_encode(['success' => true, 'id' => $insertedId]);

    // Flush output to browser immediately, then continue with notifications
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        if (ob_get_level() > 0) ob_end_flush();
        flush();
    }

    // 🔔 NOTIFY ROOM MEMBERS about new event (runs in background after response sent)
    if ($type === 'event' && $id === 0 && isset($roomId)) {
        try {
            // Get room info
            $roomInfoStmt = $pdo->prepare("SELECT name, type, town_id, gemeente_id FROM rooms WHERE id = ?");
            $roomInfoStmt->execute([$roomId]);
            $roomInfo = $roomInfoStmt->fetch(PDO::FETCH_ASSOC);
            $roomName = $roomInfo['name'] ?? 'Room';
            $roomType = strtolower($roomInfo['type'] ?? '');
            $roomTownId = (int)($roomInfo['town_id'] ?? 0);
            $roomGemeenteId = (int)($roomInfo['gemeente_id'] ?? 0);

            // Get room members based on room type (implicit membership)
            $members = [];

            if ($roomType === 'gemeente' && $roomGemeenteId > 0) {
                $membersStmt = $pdo->prepare("
                    SELECT id FROM users
                    WHERE congregation_id = ? AND id != ? AND status = 'approved'
                ");
                $membersStmt->execute([$roomGemeenteId, $userId]);
                $members = $membersStmt->fetchAll(PDO::FETCH_COLUMN);

            } elseif ($roomType === 'opsienerskap' && $roomTownId > 0) {
                $membersStmt = $pdo->prepare("
                    SELECT id FROM users
                    WHERE town_id = ? AND amp_id <= 5 AND id != ? AND status = 'approved'
                ");
                $membersStmt->execute([$roomTownId, $userId]);
                $members = $membersStmt->fetchAll(PDO::FETCH_COLUMN);

            } elseif (in_array($roomType, ['jeug', 'sondagskool']) && $roomTownId > 0) {
                $membersStmt = $pdo->prepare("
                    SELECT id FROM users
                    WHERE town_id = ? AND id != ? AND status = 'approved'
                ");
                $membersStmt->execute([$roomTownId, $userId]);
                $members = $membersStmt->fetchAll(PDO::FETCH_COLUMN);
            }

            foreach ($members as $memberId) {
                createGospelNotification($pdo, (int)$memberId, 'new_post', [
                    'room_id' => $roomId,
                    'room_name' => $roomName,
                    'author_name' => $userName,
                    'post_id' => $insertedId
                ]);
            }

            error_log("Calendar event notification: Sent to " . count($members) . " members for room $roomId ($roomType)");
        } catch (Throwable $notifErr) {
            error_log('Calendar event notification error: ' . $notifErr->getMessage());
        }
    }

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Calendar upsert error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}