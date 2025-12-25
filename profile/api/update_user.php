<?php
declare(strict_types=1);
header('Content-Type: application/json');
require_once __DIR__ . '/../../security/auth_gate.php';

$meId = (int)($_SESSION['user_id'] ?? 0);
$targetId = isset($_POST['user_id']) && is_numeric($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$newAmpId = isset($_POST['amp_id']) && is_numeric($_POST['amp_id']) ? (int)$_POST['amp_id'] : 0;

if (!$targetId || !$newAmpId) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

try {
    // Get my amp
    $stmt = $pdo->prepare("SELECT amp_id, town_id, congregation_id FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$meId]);
    $me = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get target amp
    $stmt = $pdo->prepare("SELECT amp_id, town_id, congregation_id FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$targetId]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$me || !$target) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }
    
    $myAmpId = (int)($me['amp_id'] ?? 10);
    $targetAmpId = (int)($target['amp_id'] ?? 10);
    
    // Check permissions
    if ($myAmpId >= $targetAmpId) {
        echo json_encode(['success' => false, 'error' => 'Insufficient permissions']);
        exit;
    }
    
    if ($newAmpId <= $myAmpId) {
        echo json_encode(['success' => false, 'error' => 'Cannot assign role equal to or above your level']);
        exit;
    }
    
    // Check scope
    if ($myAmpId >= 6) {
        // Congregation scope
        if ($me['congregation_id'] !== $target['congregation_id']) {
            echo json_encode(['success' => false, 'error' => 'Outside jurisdiction']);
            exit;
        }
    } elseif ($myAmpId >= 2) {
        // Town scope
        if ($me['town_id'] !== $target['town_id']) {
            echo json_encode(['success' => false, 'error' => 'Outside jurisdiction']);
            exit;
        }
    }
    
    // Update
    $stmt = $pdo->prepare("UPDATE users SET amp_id = ? WHERE id = ?");
    $stmt->execute([$newAmpId, $targetId]);
    
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}