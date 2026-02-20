<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/security/config.php';
require_once dirname(__DIR__, 2) . '/security/session.php';
require_once dirname(__DIR__, 2) . '/security/auth.php';

ob_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (!auth_logged_in()) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    // Get past 30 days of thoughts (only ones that have reached their display time)
    $stmt = $pdo->prepare('
        SELECT dt.content, dt.author, dt.display_date, dt.display_time
        FROM daily_thoughts dt
        WHERE dt.display_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
          AND dt.display_date <= CURDATE()
          AND (
            dt.display_date < CURDATE()
            OR (dt.display_date = CURDATE() AND (dt.display_time IS NULL OR dt.display_time <= CURTIME()))
          )
        ORDER BY dt.display_date DESC
        LIMIT 30
    ');
    $stmt->execute();
    $thoughts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ob_end_clean();
    echo json_encode(['success' => true, 'thoughts' => $thoughts]);
} catch (Throwable $e) {
    error_log('Thought history list error: ' . $e->getMessage());
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to load']);
}
exit;
