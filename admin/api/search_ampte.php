<?php
declare(strict_types=1);
header('Content-Type: application/json');
require_once __DIR__ . '/../../security/auth_gate.php';

$meId = (int)($_SESSION['user_id'] ?? 0);
$query = trim($_GET['q'] ?? '');

if (strlen($query) < 2) {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

try {
    // Get my permissions
    $stmt = $pdo->prepare("SELECT amp_id, town_id, congregation_id FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$meId]);
    $me = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$me) {
        echo json_encode(['success' => false, 'results' => []]);
        exit;
    }
    
    $myAmpId = (int)($me['amp_id'] ?? 10);
    $myTownId = (int)($me['town_id'] ?? 0);
    $myCongId = (int)($me['congregation_id'] ?? 0);
    
    // Determine search scope
    $scopeFilter = '';
    $scopeParams = [];
    
    if ($myAmpId === 1) {
        $scopeFilter = 'AND u.amp_id IN (2,3,4)';
    } elseif (in_array($myAmpId, [2,3,4])) {
        $scopeFilter = 'AND u.town_id = :town_id AND u.amp_id IN (1,5,6,7,8,9,10)';
        $scopeParams[':town_id'] = $myTownId;
    } elseif ($myAmpId === 5) {
        $scopeFilter = 'AND u.town_id = :town_id AND u.amp_id IN (2,3,4,6,7,8,9,10)';
        $scopeParams[':town_id'] = $myTownId;
    } elseif ($myAmpId === 6) {
        $scopeFilter = 'AND u.congregation_id = :cong_id AND u.amp_id IN (5,7,8,9,10)';
        $scopeParams[':cong_id'] = $myCongId;
    } elseif ($myAmpId === 7) {
        $scopeFilter = 'AND u.congregation_id = :cong_id AND u.amp_id IN (6,8,9,10)';
        $scopeParams[':cong_id'] = $myCongId;
    } elseif (in_array($myAmpId, [8,9])) {
        $scopeFilter = 'AND u.congregation_id = :cong_id AND u.amp_id IN (6,7,10)';
        $scopeParams[':cong_id'] = $myCongId;
    } else {
        echo json_encode(['success' => true, 'results' => []]);
        exit;
    }
    
    $sql = "SELECT u.id, u.name, u.surname, u.amp_id, u.photo,
        CASE WHEN u.gender='vrou' THEN a.female_name ELSE a.male_name END AS amp_name,
        c.name AS congregation
        FROM users u
        LEFT JOIN amptes a ON a.id = u.amp_id
        LEFT JOIN congregations c ON c.id = u.congregation_id
        WHERE u.status = 'approved'
        AND (u.name LIKE :q1 OR u.surname LIKE :q2 OR CONCAT(u.name, ' ', u.surname) LIKE :q3)
        $scopeFilter
        ORDER BY u.amp_id, u.surname, u.name
        LIMIT 50";
    
    $stmt = $pdo->prepare($sql);
    $searchParam = '%' . $query . '%';
    $params = array_merge([
        ':q1' => $searchParam,
        ':q2' => $searchParam,
        ':q3' => $searchParam
    ], $scopeParams);
    
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'results' => $results]);
    
} catch (Throwable $e) {
    error_log('Search error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error']);
}