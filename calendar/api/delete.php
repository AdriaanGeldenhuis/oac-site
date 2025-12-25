<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../security/auth_gate.php';

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

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'invalid_id']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Get user info
$userStmt = $pdo->prepare("SELECT amp_id, town_id, congregation_id FROM users WHERE id = ?");
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

try {
    $pdo->beginTransaction();
    
    // Check permissions based on type
    if ($type === 'event') {
        // Get post with room info
        $stmt = $pdo->prepare("
            SELECT p.id, p.user_id, p.room_id, r.type AS room_type, r.town_id, r.gemeente_id
            FROM posts p
            LEFT JOIN rooms r ON r.id = p.room_id
            WHERE p.id = ? AND p.type = 'event'
        ");
        $stmt->execute([$id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$event) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'not_found']);
            exit;
        }
        
        $canDelete = false;
        
        // Amp 10: read-only
        if ($ampId === 10) {
            $canDelete = false;
        }
        // Amp 1-5: full opsienerskap rights
        elseif ($ampId >= 1 && $ampId <= 5 && $event['room_type'] === 'opsienerskap' && $event['town_id'] == $townId) {
            $canDelete = true;
        }
        // Amp 5-9: full gemeente rights
        elseif ($ampId >= 5 && $ampId <= 9 && $event['room_type'] === 'gemeente' && $event['gemeente_id'] == $congId) {
            $canDelete = true;
        }
        // Jeug & Sondagskool: all except amp 10
        elseif ($ampId < 10 && in_array($event['room_type'], ['jeug', 'sondagskool']) && $event['town_id'] == $townId) {
            $canDelete = true;
        }
        // Own events
        elseif ($event['user_id'] == $userId) {
            $canDelete = true;
        }
        
        if (!$canDelete) {
            $pdo->rollBack();
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'forbidden']);
            exit;
        }
        
        // Delete post
        $delStmt = $pdo->prepare("DELETE FROM posts WHERE id = ? AND type = 'event'");
        $delStmt->execute([$id]);
        
    } elseif ($type === 'diary') {
        // Diary: only owner
        $stmt = $pdo->prepare("SELECT id FROM diaries WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        if (!$stmt->fetch()) {
            $pdo->rollBack();
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'forbidden']);
            exit;
        }
        
        $delStmt = $pdo->prepare("DELETE FROM diaries WHERE id = ? AND user_id = ?");
        $delStmt->execute([$id, $userId]);
        
    } elseif ($type === 'visit') {
        // Visit: only owner
        $stmt = $pdo->prepare("SELECT id FROM calendar_visits WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        if (!$stmt->fetch()) {
            $pdo->rollBack();
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'forbidden']);
            exit;
        }
        
        $delStmt = $pdo->prepare("DELETE FROM calendar_visits WHERE id = ? AND user_id = ?");
        $delStmt->execute([$id, $userId]);
    }
    
    // Delete shares
    $delShare = $pdo->prepare("DELETE FROM calendar_shared_events WHERE event_id = ? AND event_type = ?");
    $delShare->execute([$id, $type]);
    
    $pdo->commit();
    echo json_encode(['success' => true]);
    
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Calendar delete error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'server_error']);
}