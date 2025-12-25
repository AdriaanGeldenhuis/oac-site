<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../security/auth_gate.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'db_unavailable']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

try {
    // Get users in same town
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.surname
        FROM users u
        WHERE u.status = 'approved' 
          AND u.town_id = (SELECT town_id FROM users WHERE id = ?)
          AND u.id != ?
        ORDER BY u.name ASC, u.surname ASC
    ");
    $stmt->execute([$userId, $userId]);
    
    $users = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $users[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'surname' => $row['surname']
        ];
    }
    
    echo json_encode(['success' => true, 'users' => $users]);
    
} catch (Throwable $e) {
    error_log('Calendar users error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'server_error']);
}