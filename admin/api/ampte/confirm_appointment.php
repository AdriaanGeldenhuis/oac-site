<?php
declare(strict_types=1);
header('Content-Type: application/json');
require_once __DIR__ . '/../../../security/auth_gate.php';

$userId = $_SESSION['user_id'] ?? null;
$appointmentId = isset($_POST['appointment_id']) && is_numeric($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : 0;

if (!$userId || !$appointmentId) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

try {
    // Get appointment details
    $stmt = $pdo->prepare("SELECT * FROM ampte_appointments WHERE id = ? AND status = 'pending' LIMIT 1");
    $stmt->execute([$appointmentId]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appointment) {
        http_response_code(404);
        echo json_encode(['error' => 'Appointment not found or already processed']);
        exit;
    }
    
    // Verify user is the target
    if ((int)$appointment['target_user_id'] !== $userId) {
        http_response_code(403);
        echo json_encode(['error' => 'You are not authorized to confirm this appointment']);
        exit;
    }
    
    // Update appointment status
    $updateStmt = $pdo->prepare("UPDATE ampte_appointments SET status = 'confirmed' WHERE id = ?");
    $updateStmt->execute([$appointmentId]);
    
    // Update calendar events for both users
    $datetime = $appointment['appointment_date'] . ' ' . $appointment['appointment_time'];
    
    // Update requester's calendar event
    $requesterStmt = $pdo->prepare("SELECT name, surname FROM users WHERE id = ? LIMIT 1");
    $requesterStmt->execute([$appointment['requester_id']]);
    $requester = $requesterStmt->fetch(PDO::FETCH_ASSOC);
    
    $targetStmt = $pdo->prepare("SELECT name, surname FROM users WHERE id = ? LIMIT 1");
    $targetStmt->execute([$appointment['target_user_id']]);
    $target = $targetStmt->fetch(PDO::FETCH_ASSOC);
    
    // Add confirmed event to both calendars
    $calendarStmt = $pdo->prepare("INSERT INTO calendar_events 
        (user_id, title, description, start_at, visibility)
        VALUES (?, ?, ?, ?, 'private')
        ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description)");
    
    $requesterTitle = 'Afspraak met ' . trim(($target['name'] ?? '') . ' ' . ($target['surname'] ?? '')) . ' (Bevestig)';
    $targetTitle = 'Afspraak met ' . trim(($requester['name'] ?? '') . ' ' . ($requester['surname'] ?? '')) . ' (Bevestig)';
    $description = $appointment['notes'] ?: 'Afspraak';
    
    $calendarStmt->execute([$appointment['requester_id'], $requesterTitle, $description, $datetime]);
    $calendarStmt->execute([$appointment['target_user_id'], $targetTitle, $description, $datetime]);
    
    // Send notification to requester
    $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, type, payload) VALUES (?, 'appointment_confirmed', ?)");
    $notifStmt->execute([$appointment['requester_id'], json_encode([
        'from_id' => $userId,
        'from_name' => trim(($target['name'] ?? '') . ' ' . ($target['surname'] ?? '')),
        'appointment_id' => $appointmentId,
        'date' => $appointment['appointment_date'],
        'time' => $appointment['appointment_time']
    ])]);
    
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}