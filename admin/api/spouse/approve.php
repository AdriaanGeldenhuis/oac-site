<?php
declare(strict_types=1);
header('Content-Type: application/json');
require_once __DIR__ . '/../../../security/auth_gate.php';
require_once __DIR__ . '/../notifications/helper.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
$requestId = isset($_POST['request_id']) && is_numeric($_POST['request_id']) ? (int)$_POST['request_id'] : 0;

if (!$userId || !$requestId) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM spouse_requests WHERE id = ? AND status = 'pending' LIMIT 1");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        http_response_code(404);
        echo json_encode(['error' => 'Request not found or already processed']);
        exit;
    }

    $requesterId = (int)$request['requester_id'];
    $receiverId = (int)$request['receiver_id'];

    // Only the receiver may accept their own request; office bearers
    // (amp 1-6, same group that sees the approvals tab) may approve too.
    // The requester can never approve their own request.
    $meStmt = $pdo->prepare('SELECT amp_id FROM users WHERE id = ? LIMIT 1');
    $meStmt->execute([$userId]);
    $myAmpId = (int)($meStmt->fetchColumn() ?: 0);
    $isOfficeBearer = ($myAmpId >= 1 && $myAmpId <= 6);

    if ($userId !== $receiverId && !$isOfficeBearer) {
        http_response_code(403);
        echo json_encode(['error' => 'Not authorized']);
        exit;
    }

    // Both parties must still be unmarried
    $stmt = $pdo->prepare('SELECT id FROM users WHERE id IN (?, ?) AND spouse_user_id IS NOT NULL');
    $stmt->execute([$requesterId, $receiverId]);
    if ($stmt->fetch()) {
        echo json_encode(['error' => 'One of them is already married / Een van hulle is reeds getroud']);
        exit;
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("UPDATE spouse_requests SET status = 'accepted', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$requestId]);

    $stmt = $pdo->prepare("UPDATE users SET spouse_user_id = ?, marital_status = 'getroud' WHERE id = ?");
    $stmt->execute([$requesterId, $receiverId]);

    $stmt = $pdo->prepare("UPDATE users SET spouse_user_id = ?, marital_status = 'getroud' WHERE id = ?");
    $stmt->execute([$receiverId, $requesterId]);

    $pdo->commit();

    // Notify both parties (skip the acting user)
    $nameStmt = $pdo->prepare('SELECT id, name, surname FROM users WHERE id IN (?, ?)');
    $nameStmt->execute([$requesterId, $receiverId]);
    $names = [];
    foreach ($nameStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $names[(int)$row['id']] = trim(($row['name'] ?? '') . ' ' . ($row['surname'] ?? ''));
    }

    if ($requesterId !== $userId) {
        createAdminNotification($requesterId, 'spouse_accepted', [
            'from_id' => $receiverId,
            'from_name' => $names[$receiverId] ?? ''
        ]);
    }
    if ($receiverId !== $userId) {
        createAdminNotification($receiverId, 'spouse_accepted', [
            'from_id' => $requesterId,
            'from_name' => $names[$requesterId] ?? ''
        ]);
    }

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
