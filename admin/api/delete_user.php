<?php
/**
 * Delete user API
 *
 * Allows an authorised user to permanently remove another user account.  This
 * endpoint expects a POST request with `user_id`.  The requester must hold
 * a higher or equivalent office (according to the defined hierarchy) than
 * the target.  Priests may only delete members of their own congregation.
 * Elders, evangelists, prophets, overseers and apostles (and their female
 * equivalents) may delete any user with a rank strictly below their own.
 * The authenticated user may not delete their own account via this endpoint.
 * On success returns {"success": true}.
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../session.php';
require_once __DIR__ . '/../../app/db_connect.php';

// Ensure user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$currentUserId = (int)$_SESSION['user_id'];
$targetUserId  = isset($_POST['user_id']) && is_numeric($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
if ($targetUserId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid user_id']);
    exit;
}

// Cannot delete yourself
if ($currentUserId === $targetUserId) {
    http_response_code(400);
    echo json_encode(['error' => 'Cannot delete your own account']);
    exit;
}

try {
    // Fetch current user role and congregation
    $meStmt = $pdo->prepare('SELECT LOWER(TRIM(amp)) AS amp, congregation_id FROM users WHERE id = ? LIMIT 1');
    $meStmt->execute([$currentUserId]);
    $me = $meStmt->fetch(PDO::FETCH_ASSOC);
    if (!$me) {
        http_response_code(403);
        echo json_encode(['error' => 'Not authorised']);
        exit;
    }
    $myRole   = strtolower(trim((string)($me['amp'] ?? '')));
    $myCongId = $me['congregation_id'] ?? null;
    // Fetch target user role and congregation
    $tarStmt = $pdo->prepare('SELECT LOWER(TRIM(amp)) AS amp, congregation_id FROM users WHERE id = ? LIMIT 1');
    $tarStmt->execute([$targetUserId]);
    $target = $tarStmt->fetch(PDO::FETCH_ASSOC);
    if (!$target) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    $targetRole   = strtolower(trim((string)($target['amp'] ?? '')));
    $targetCongId = $target['congregation_id'] ?? null;
    // Define role hierarchy (same as update_role.php)
    $roleRanks = [
        'apostel' => 0,
        'apostel suster' => 0,
        'opsiener' => 1,
        'opsiener suster' => 1,
        'profeet' => 2,
        'profeet suster' => 2,
        'evangelis' => 2,
        'evangelis suster' => 2,
        'oudste' => 3,
        'oudste suster' => 3,
        'priester' => 4,
        'priester suster' => 4,
        'onderdiaken' => 5,
        'onderdiaken suster' => 5,
        'diaken' => 6,
        'diaken suster' => 6,
        'hulpkrag' => 7,
        'hulpkrag suster' => 7,
        'broer' => 8,
        'suster' => 8,
        'lid' => 9,
        'lid suster' => 9
    ];
    $myRank     = $roleRanks[$myRole]     ?? 99;
    $targetRank = $roleRanks[$targetRole] ?? 99;
    // Determine if user has deletion rights
    $canDelete = false;
    // Priests (rank 4) have congregation-scoped permissions
    $isPriest = in_array($myRole, ['priester','priester suster'], true);
    if ($myRank <= 3) {
        // Apostles, overseers, prophets/evangelists and elders may delete users strictly below their rank
        if ($myRank < $targetRank) {
            $canDelete = true;
        }
    } elseif ($isPriest) {
        // Priests may only delete users within their own congregation and strictly below their rank
        if ($myRank < $targetRank && $myCongId && $targetCongId && (int)$myCongId === (int)$targetCongId) {
            $canDelete = true;
        }
    }
    if (!$canDelete) {
        http_response_code(403);
        echo json_encode(['error' => 'Not authorised to delete this user']);
        exit;
    }
    // Perform deletion.  Foreign keys with ON DELETE CASCADE will remove dependent rows.
    $del = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $del->execute([$targetUserId]);
    echo json_encode(['success' => true]);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
    exit;
}