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

// Check Elder permissions (amp_id 1-5)
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

$content = trim((string)($_POST['content'] ?? ''));
$author = trim((string)($_POST['author'] ?? ''));
$displayDate = trim((string)($_POST['display_date'] ?? ''));

if ($content === '') {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Content is required']);
    exit;
}

if ($displayDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $displayDate)) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Valid date is required']);
    exit;
}

// Sanitize content (strip dangerous HTML)
$content = strip_tags($content, '<p><br><strong><em><b><i>');

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

    // Insert or update (replace if same date exists)
    $stmt = $pdo->prepare('
        INSERT INTO daily_thoughts (content, author, display_date, created_by)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE content = VALUES(content), author = VALUES(author), created_by = VALUES(created_by)
    ');
    $stmt->execute([
        $content,
        $author !== '' ? $author : null,
        $displayDate,
        $userId
    ]);

    ob_end_clean();
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    error_log('Daily thought save error: ' . $e->getMessage());
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save']);
}
exit;
