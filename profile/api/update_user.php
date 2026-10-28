<?php
declare(strict_types=1);
header('Content-Type: application/json');
require_once __DIR__ . '/../../security/auth_gate.php';
require_once __DIR__ . '/../../includes/admin_api.php';

// Changes someone's office: POST only, with the session's CSRF token (profile.js sends it)
admin_api_require_post();

$meId = (int)($_SESSION['user_id'] ?? 0);
$targetId = isset($_POST['user_id']) && is_numeric($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$newAmpId = isset($_POST['amp_id']) && is_numeric($_POST['amp_id']) ? (int)$_POST['amp_id'] : 0;

if (!$targetId || !$newAmpId) {
    admin_api_fail('adm_err_invalid');
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
        admin_api_fail('adm_err_not_found', 404);
    }
    
    $myAmpId = (int)($me['amp_id'] ?? 10);
    $targetAmpId = (int)($target['amp_id'] ?? 10);
    
    // Check permissions
    if ($myAmpId >= $targetAmpId) {
        admin_api_fail('adm_err_forbidden', 403);
    }
    
    if ($newAmpId <= $myAmpId) {
        admin_api_fail('adm_err_forbidden', 403);
    }

    // Only an office that exists (the list the edit form offers)
    $stmt = $pdo->prepare("SELECT 1 FROM amptes WHERE id = ? LIMIT 1");
    $stmt->execute([$newAmpId]);
    if (!$stmt->fetchColumn()) {
        admin_api_fail('adm_err_invalid');
    }

    // Check scope
    if ($myAmpId >= 6) {
        // Congregation scope
        if ($me['congregation_id'] !== $target['congregation_id']) {
            admin_api_fail('adm_err_forbidden', 403);
        }
    } elseif ($myAmpId >= 2) {
        // Town scope
        if ($me['town_id'] !== $target['town_id']) {
            admin_api_fail('adm_err_forbidden', 403);
        }
    }
    
    // Update
    $stmt = $pdo->prepare("UPDATE users SET amp_id = ? WHERE id = ?");
    $stmt->execute([$newAmpId, $targetId]);
    
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    admin_api_server_error($e, 'profile/update_user');
}