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

try {
    // Create table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS daily_thoughts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            content TEXT NOT NULL,
            author VARCHAR(255) DEFAULT NULL,
            display_date DATE NOT NULL,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_date (display_date),
            INDEX idx_display_date (display_date),
            INDEX idx_created_by (created_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Get today and upcoming thoughts (today + future, last 7 past)
    $stmt = $pdo->prepare('
        SELECT dt.id, dt.content, dt.author, dt.display_date, u.name AS created_by_name
        FROM daily_thoughts dt
        LEFT JOIN users u ON u.id = dt.created_by
        WHERE dt.display_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ORDER BY dt.display_date ASC
        LIMIT 30
    ');
    $stmt->execute();
    $thoughts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ob_end_clean();
    echo json_encode(['success' => true, 'thoughts' => $thoughts]);
} catch (Throwable $e) {
    error_log('Daily thought list error: ' . $e->getMessage());
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to load']);
}
exit;
