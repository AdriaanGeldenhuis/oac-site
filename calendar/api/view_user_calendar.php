<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../security/auth_gate.php';

$meId = (int)($_SESSION['user_id'] ?? 0);
$targetId = isset($_GET['user_id']) && is_numeric($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if (!$targetId) {
    echo json_encode([]);
    exit;
}

try {
    // Get my permissions
    $stmt = $pdo->prepare("SELECT amp_id, town_id, congregation_id FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$meId]);
    $me = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get target permissions
    $stmt = $pdo->prepare("SELECT amp_id, town_id, congregation_id FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$targetId]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$me || !$target) {
        echo json_encode([]);
        exit;
    }
    
    $myAmpId = (int)($me['amp_id'] ?? 10);
    $myTownId = (int)($me['town_id'] ?? 0);
    $myCongId = (int)($me['congregation_id'] ?? 0);
    
    $targetAmpId = (int)($target['amp_id'] ?? 10);
    $targetTownId = (int)($target['town_id'] ?? 0);
    $targetCongId = (int)($target['congregation_id'] ?? 0);
    
    // Check permission
    $canView = false;
    
    if ($meId === $targetId) {
        $canView = true;
    } elseif ($myAmpId < $targetAmpId) {
        if ($myAmpId >= 6) {
            $canView = ($myCongId === $targetCongId);
        } elseif ($myAmpId >= 2) {
            $canView = ($myTownId === $targetTownId);
        } else {
            $canView = true;
        }
    } elseif ($myAmpId <= 5 && $myTownId === $targetTownId) {
        $canView = true;
    }
    
    if (!$canView) {
        echo json_encode([]);
        exit;
    }
    
    $events = [];
    
    // Get calendar events
    $stmt = $pdo->prepare("
        SELECT 
            id,
            title,
            DATE(start_at) AS date,
            TIME_FORMAT(start_at, '%H:%i') AS time,
            description AS notes,
            'event' AS type
        FROM calendar_events 
        WHERE user_id = ?
        ORDER BY start_at ASC
    ");
    $stmt->execute([$targetId]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get diary entries
    $diaryStmt = $pdo->prepare("
        SELECT 
            id,
            title,
            date,
            time,
            body AS notes,
            'diary' AS type
        FROM diaries 
        WHERE user_id = ?
        ORDER BY date ASC, time ASC
    ");
    $diaryStmt->execute([$targetId]);
    $events = array_merge($events, $diaryStmt->fetchAll(PDO::FETCH_ASSOC));
    
    // Get visits
    $visitStmt = $pdo->prepare("
        SELECT 
            id,
            title,
            visit_date AS date,
            visit_time AS time,
            notes,
            'visit' AS type
        FROM calendar_visits 
        WHERE user_id = ?
        ORDER BY visit_date ASC, visit_time ASC
    ");
    $visitStmt->execute([$targetId]);
    $events = array_merge($events, $visitStmt->fetchAll(PDO::FETCH_ASSOC));
    
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
    
    echo json_encode($events, JSON_UNESCAPED_UNICODE);
    
} catch (Throwable $e) {
    error_log('View calendar error: ' . $e->getMessage());
    echo json_encode([]);
}