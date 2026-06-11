<?php
declare(strict_types=1);
header('Content-Type: application/json');
require_once __DIR__ . '/../../../security/auth_gate.php';
require_once __DIR__ . '/../notifications/helper.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
$targetId = isset($_POST['user_id']) && is_numeric($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

if (!$userId || !$targetId) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

try {
    // Only office bearers (amp 1-6) may unlink spouses
    $meStmt = $pdo->prepare('SELECT amp_id FROM users WHERE id = ? LIMIT 1');
    $meStmt->execute([$userId]);
    $myAmpId = (int)($meStmt->fetchColumn() ?: 0);

    if ($myAmpId < 1 || $myAmpId > 6) {
        http_response_code(403);
        echo json_encode(['error' => 'Not authorized']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT id, name, surname, spouse_user_id FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$targetId]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target || empty($target['spouse_user_id'])) {
        http_response_code(404);
        echo json_encode(['error' => 'No spouse link found / Geen eggenoot-koppeling gevind nie']);
        exit;
    }

    $spouseId = (int)$target['spouse_user_id'];

    $spStmt = $pdo->prepare('SELECT id, name, surname FROM users WHERE id = ? LIMIT 1');
    $spStmt->execute([$spouseId]);
    $spouse = $spStmt->fetch(PDO::FETCH_ASSOC) ?: ['name' => '', 'surname' => ''];

    $pdo->beginTransaction();

    // Unlink both sides; marital_status stays as-is so the members can
    // set it themselves (e.g. weduwee/wewenaar after a passing)
    $unlink = $pdo->prepare('UPDATE users SET spouse_user_id = NULL WHERE id IN (?, ?)');
    $unlink->execute([$targetId, $spouseId]);

    $pdo->commit();

    $targetName = trim(($target['name'] ?? '') . ' ' . ($target['surname'] ?? ''));
    $spouseName = trim(($spouse['name'] ?? '') . ' ' . ($spouse['surname'] ?? ''));

    createAdminNotification($targetId, 'spouse_unlinked', ['from_name' => $spouseName]);
    createAdminNotification($spouseId, 'spouse_unlinked', ['from_name' => $targetName]);

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
