<?php
declare(strict_types=1);
header('Content-Type: application/json');
require_once __DIR__ . '/../../../security/auth_gate.php';

$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    // Get current user's details
    $userStmt = $pdo->prepare("SELECT amp_id, town_id, congregation_id FROM users WHERE id = ? LIMIT 1");
    $userStmt->execute([$userId]);
    $currentUser = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$currentUser) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    
    $myAmpId = (int)($currentUser['amp_id'] ?? 0);
    $myTownId = $currentUser['town_id'] ?? null;
    $myCongId = $currentUser['congregation_id'] ?? null;
    
    // Determine visibility based on role
    $canSeeAmps = [];
    $scopeFilter = '';
    $scopeParams = [];
    
    if ($myAmpId === 1) {
        $canSeeAmps = [2,3,4];
    } elseif (in_array($myAmpId, [2,3,4])) {
        $canSeeAmps = [1,5,6,7,8,9,10];
        if ($myTownId) {
            $scopeFilter = 'AND u.town_id = :town_id';
            $scopeParams[':town_id'] = $myTownId;
        }
    } elseif ($myAmpId === 5) {
        $canSeeAmps = [2,3,4,6,7,8,9,10];
        if ($myTownId) {
            $scopeFilter = 'AND u.town_id = :town_id';
            $scopeParams[':town_id'] = $myTownId;
        }
    } elseif ($myAmpId === 6) {
        $canSeeAmps = [5,7,8,9,10];
        if ($myCongId) {
            $scopeFilter = 'AND u.congregation_id = :cong_id';
            $scopeParams[':cong_id'] = $myCongId;
        }
    } elseif ($myAmpId === 7) {
        $canSeeAmps = [6,8,9,10];
        if ($myCongId) {
            $scopeFilter = 'AND u.congregation_id = :cong_id';
            $scopeParams[':cong_id'] = $myCongId;
        }
    } elseif (in_array($myAmpId, [8,9])) {
        $canSeeAmps = [6,7,10];
        if ($myCongId) {
            $scopeFilter = 'AND u.congregation_id = :cong_id';
            $scopeParams[':cong_id'] = $myCongId;
        }
    }
    
    if (empty($canSeeAmps)) {
        echo json_encode(['success' => true, 'amptes' => []]);
        exit;
    }
    
    $ampList = implode(',', $canSeeAmps);
    $sql = "SELECT u.id, u.name, u.surname, u.amp_id, u.email, u.phone, u.photo,
            CASE WHEN u.gender='vrou' THEN a.female_name ELSE a.male_name END AS amp_name,
            c.name AS congregation
            FROM users u
            LEFT JOIN amptes a ON a.id = u.amp_id
            LEFT JOIN congregations c ON c.id = u.congregation_id
            WHERE u.amp_id IN ($ampList) 
            AND u.status = 'approved'
            $scopeFilter
            ORDER BY u.amp_id, u.surname, u.name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($scopeParams);
    $amptes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'amptes' => $amptes
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}