<?php
declare(strict_types=1);

error_log('=====================================');
error_log('🔍 SAVE_TEACHING.PHP START');
error_log('=====================================');

// Load dependencies
require_once dirname(__DIR__, 3) . '/security/config.php';
require_once dirname(__DIR__, 3) . '/security/session.php';
require_once dirname(__DIR__, 3) . '/security/auth.php';
require_once dirname(__DIR__, 3) . '/includes/languages.php';

// Headers after session
ob_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Auth check
if (!auth_logged_in()) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = auth_user_id();

// Check Elder permissions
try {
    $stmt = $pdo->prepare('SELECT amp_id, town_id FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        ob_end_clean();
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }

    $ampId = (int)($user['amp_id'] ?? 0);
    if ($ampId < 1 || $ampId > 5) {
        ob_end_clean();
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Insufficient permissions']);
        exit;
    }
} catch (Throwable $e) {
    error_log('❌ DB error: ' . $e->getMessage());
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}

// Method guard
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

/**
 * Sanitize HTML content - remove dangerous tags and attributes
 * Keeps safe HTML like <p>, <h1-h6>, <strong>, <em>, <span>, <sup>, <br>
 */
function sanitize_html(string $html): string {
    if (trim($html) === '') {
        return '';
    }

    // Dangerous tags to remove completely (with content)
    $dangerousTags = ['script', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select'];
    foreach ($dangerousTags as $tag) {
        $html = preg_replace('/<' . $tag . '\b[^>]*>.*?<\/' . $tag . '>/is', '', $html);
        $html = preg_replace('/<' . $tag . '\b[^>]*\/?>/i', '', $html);
    }

    // Remove all on* event handlers (onclick, onload, onerror, etc.)
    $html = preg_replace('/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
    $html = preg_replace('/\s+on\w+\s*=\s*[^\s>]+/i', '', $html);

    // Remove javascript: and data: URLs from href/src attributes
    $html = preg_replace('/\s+(href|src)\s*=\s*["\']?\s*javascript:[^"\'>\s]*/i', '', $html);
    $html = preg_replace('/\s+(href|src)\s*=\s*["\']?\s*data:[^"\'>\s]*/i', '', $html);

    // Remove style expressions (IE-specific XSS vector)
    $html = preg_replace('/expression\s*\([^)]*\)/i', '', $html);

    return $html;
}

// Get inputs for all 5 languages
$contents = [];
foreach (SUPPORTED_LANGS as $code) {
    $raw = trim((string)($_POST['content_' . $code] ?? ''));
    $contents[$code] = sanitize_html($raw);
}

$townId = (int)($_POST['town_id'] ?? $user['town_id'] ?? 0);

// Check if at least one language has content
$hasAnyContent = false;
foreach ($contents as $content) {
    if ($content !== '') {
        $hasAnyContent = true;
        break;
    }
}

if (!$hasAnyContent) {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No content provided']);
    exit;
}

// Get location
$province = '';
$town = '';

if ($townId > 0) {
    try {
        $stmt = $pdo->prepare('
            SELECT t.name AS town_name, p.name AS province_name
            FROM towns t
            LEFT JOIN provinces p ON p.id = t.province_id
            WHERE t.id = ?
            LIMIT 1
        ');
        $stmt->execute([$townId]);
        $location = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($location) {
            $province = $location['province_name'] ?? '';
            $town = $location['town_name'] ?? '';
        }
    } catch (Throwable $e) {
        error_log('⚠️ Location fetch failed: ' . $e->getMessage());
    }
}

// Build path
function slugify(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '_', $text);
    return trim($text, '_') ?: 'unknown';
}

$baseDir = dirname(__DIR__, 3) . '/welcome';
$targetDir = $baseDir;

if ($province !== '' && $town !== '') {
    $targetDir .= '/south_africa/' . slugify($province) . '/' . slugify($town);
}

// Create directory
if (!is_dir($targetDir)) {
    if (!@mkdir($targetDir, 0755, true)) {
        error_log('❌ Failed to create: ' . $targetDir);
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to create directory']);
        exit;
    }
}

// File naming convention per language
// AF: lering_content.html
// Others: teaching_content.<lang>.html
$fileNames = [
    'af' => 'lering_content.html',
    'en' => 'teaching_content.en.html',
    'zu' => 'teaching_content.zu.html',
    'xh' => 'teaching_content.xh.html',
    'pt' => 'teaching_content.pt.html'
];

// Write files
$written = [];
$hasContent = [];

try {
    foreach (SUPPORTED_LANGS as $code) {
        if ($contents[$code] !== '') {
            $filePath = $targetDir . '/' . $fileNames[$code];
            if (file_put_contents($filePath, $contents[$code]) === false) {
                throw new Exception("Failed to write {$code} file");
            }
            $written[] = $fileNames[$code];
            $hasContent[$code] = 1;
        } else {
            $hasContent[$code] = 0;
        }
    }
} catch (Throwable $e) {
    error_log('❌ Write error: ' . $e->getMessage());
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to write files']);
    exit;
}

// Log to DB
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS teachings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            town_id INT DEFAULT NULL,
            province VARCHAR(100) DEFAULT NULL,
            town VARCHAR(100) DEFAULT NULL,
            has_af TINYINT(1) DEFAULT 0,
            has_en TINYINT(1) DEFAULT 0,
            has_zu TINYINT(1) DEFAULT 0,
            has_xh TINYINT(1) DEFAULT 0,
            has_pt TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_town (town_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $stmt = $pdo->prepare('
        INSERT INTO teachings (user_id, town_id, province, town, has_af, has_en, has_zu, has_xh, has_pt, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ');
    $stmt->execute([
        $userId,
        $townId > 0 ? $townId : null,
        $province !== '' ? $province : null,
        $town !== '' ? $town : null,
        $hasContent['af'] ?? 0,
        $hasContent['en'] ?? 0,
        $hasContent['zu'] ?? 0,
        $hasContent['xh'] ?? 0,
        $hasContent['pt'] ?? 0
    ]);
} catch (Throwable $e) {
    error_log('⚠️ DB log failed: ' . $e->getMessage());
}

// SUCCESS
error_log('✅ SAVE SUCCESS: ' . implode(', ', $written));

ob_end_clean();
http_response_code(200);
echo json_encode([
    'success' => true,
    'dir' => $targetDir,
    'files' => $written,
    'province' => $province,
    'town' => $town
], JSON_UNESCAPED_UNICODE);

exit;
