<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/security/config.php';
require_once dirname(__DIR__, 3) . '/security/session.php';
require_once dirname(__DIR__, 3) . '/security/auth.php';

ob_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (!auth_logged_in()) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = auth_user_id();

// Check Elder permissions
try {
    $stmt = $pdo->prepare('SELECT amp_id FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $ampId = (int)($user['amp_id'] ?? 0);
    if ($ampId < 1 || $ampId > 5) {
        ob_end_clean();
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Insufficient permissions']);
        exit;
    }
} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id < 1) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid ID']);
    exit;
}

try {
    $stmt = $pdo->prepare('DELETE FROM daily_thoughts WHERE id = ?');
    $stmt->execute([$id]);

    ob_end_clean();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log('Daily thought delete error: ' . $e->getMessage());
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to delete']);
}
exit;
