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
    $stmt = $pdo->prepare("SELECT * FROM ampte_appointments WHERE id = ? LIMIT 1");
    $stmt->execute([$appointmentId]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appointment) {
        http_response_code(404);
        echo json_encode(['error' => 'Appointment not found']);
        exit;
    }
    
    // Verify user is involved in appointment
    if ((int)$appointment['requester_id'] !== $userId && (int)$appointment['target_user_id'] !== $userId) {
        http_response_code(403);
        echo json_encode(['error' => 'You are not authorized to cancel this appointment']);
        exit;
    }
    
    // Update appointment status
    $updateStmt = $pdo->prepare("UPDATE ampte_appointments SET status = 'cancelled' WHERE id = ?");
    $updateStmt->execute([$appointmentId]);
    
    // Remove from calendars
    $datetime = $appointment['appointment_date'] . ' ' . $appointment['appointment_time'];
    $deleteCalStmt = $pdo->prepare("DELETE FROM calendar_events 
        WHERE user_id IN (?, ?) 
        AND start_at = ?
        AND title LIKE '%Afspraak%'");
    $deleteCalStmt->execute([
        $appointment['requester_id'],
        $appointment['target_user_id'],
        $datetime
    ]);
    
    // Send notification to other party
    $otherId = ($userId === (int)$appointment['requester_id']) 
        ? (int)$appointment['target_user_id'] 
        : (int)$appointment['requester_id'];
    
    $userStmt = $pdo->prepare("SELECT name, surname FROM users WHERE id = ? LIMIT 1");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    $notifStmt = $pdo->prepare("INSERT INTO notifications (user_id, type, payload) VALUES (?, 'appointment_cancelled', ?)");
    $notifStmt->execute([$otherId, json_encode([
        'from_id' => $userId,
        'from_name' => trim(($user['name'] ?? '') . ' ' . ($user['surname'] ?? '')),
        'appointment_id' => $appointmentId,
        'date' => $appointment['appointment_date'],
        'time' => $appointment['appointment_time']
    ])]);
    
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}