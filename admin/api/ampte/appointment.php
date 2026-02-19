<?php
declare(strict_types=1);
header('Content-Type: application/json');
require_once __DIR__ . '/../../../security/auth_gate.php';
require_once __DIR__ . '/../notifications/helper.php';

$userId = $_SESSION['user_id'] ?? null;
$targetUserId = isset($_POST['target_user_id']) && is_numeric($_POST['target_user_id']) ? (int)$_POST['target_user_id'] : 0;
$appointmentDate = trim($_POST['appointment_date'] ?? '');
$appointmentTime = trim($_POST['appointment_time'] ?? '');
$location = trim($_POST['location'] ?? '');
$notes = trim($_POST['notes'] ?? '');

if (!$userId || !$targetUserId || !$appointmentDate || !$appointmentTime) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

if ($userId === $targetUserId) {
    http_response_code(400);
    echo json_encode(['error' => 'Cannot create appointment with yourself']);
    exit;
}

try {
    // Validate date/time
    $datetime = $appointmentDate . ' ' . $appointmentTime;
    $timestamp = strtotime($datetime);
    
    if ($timestamp === false || $timestamp < time()) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid date/time']);
        exit;
    }
    
    // Create appointment
    $stmt = $pdo->prepare("INSERT INTO ampte_appointments 
        (requester_id, target_user_id, appointment_date, appointment_time, location, notes, status)
        VALUES (?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->execute([$userId, $targetUserId, $appointmentDate, $appointmentTime, $location, $notes]);
    
    $appointmentId = (int)$pdo->lastInsertId();
    
    // Send notification to target user
    $userStmt = $pdo->prepare("SELECT name, surname FROM users WHERE id = ? LIMIT 1");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    createAdminNotification($targetUserId, 'appointment_request', [
        'from_name' => trim(($user['name'] ?? '') . ' ' . ($user['surname'] ?? '')),
        'date' => $appointmentDate,
        'time' => $appointmentTime
    ]);
    
    // Add to calendar for both users
    $calendarStmt = $pdo->prepare("INSERT INTO calendar_events 
        (user_id, title, description, start_at, visibility)
        VALUES (?, ?, ?, ?, 'private')");
    
    $title = 'Afspraak met ' . trim(($user['name'] ?? '') . ' ' . ($user['surname'] ?? ''));
    $description = $notes ?: 'Afspraak';
    $startAt = $appointmentDate . ' ' . $appointmentTime;
    
    // Requester's calendar
    $calendarStmt->execute([$userId, $title, $description, $startAt]);
    
    // Target's calendar (pending)
    $targetUserStmt = $pdo->prepare("SELECT name, surname FROM users WHERE id = ? LIMIT 1");
    $targetUserStmt->execute([$targetUserId]);
    $targetUser = $targetUserStmt->fetch(PDO::FETCH_ASSOC);
    
    $targetTitle = 'Afspraak versoek van ' . trim(($user['name'] ?? '') . ' ' . ($user['surname'] ?? ''));
    $calendarStmt->execute([$targetUserId, $targetTitle, $description, $startAt]);
    
    echo json_encode(['success' => true, 'appointment_id' => $appointmentId]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}