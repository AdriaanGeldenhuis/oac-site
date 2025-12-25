<?php
declare(strict_types=1);

error_log('=====================================');
error_log('🔍 SAVE_TEACHING.PHP START');
error_log('=====================================');

// ✅ Load dependencies first
require_once dirname(__DIR__, 3) . '/security/config.php';
require_once dirname(__DIR__, 3) . '/security/session.php';
require_once dirname(__DIR__, 3) . '/security/auth.php';
require_once dirname(__DIR__, 2) . '/config/ai_config.php';

// ✅ Headers after session
ob_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ✅ Auth check
if (!auth_logged_in()) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = auth_user_id();

// ✅ Check Elder permissions
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

// ✅ Method guard
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// ✅ Get inputs
$contentAf = trim((string)($_POST['content_af'] ?? ''));
$contentEn = trim((string)($_POST['content_en'] ?? ''));
$townId = (int)($_POST['town_id'] ?? $user['town_id'] ?? 0);

if ($contentAf === '' && $contentEn === '') {
    ob_end_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No content provided']);
    exit;
}

// ✅ Get location
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

// ✅ Build path
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

// ✅ Create directory
if (!is_dir($targetDir)) {
    if (!@mkdir($targetDir, 0755, true)) {
        error_log('❌ Failed to create: ' . $targetDir);
        ob_end_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to create directory']);
        exit;
    }
}

// ✅ Write files
$written = [];

try {
    if ($contentAf !== '') {
        $fileAf = $targetDir . '/lering_content.html';
        if (file_put_contents($fileAf, $contentAf) === false) {
            throw new Exception('Failed to write Afrikaans file');
        }
        $written[] = basename($fileAf);
    }
    
    if ($contentEn !== '') {
        $fileEn = $targetDir . '/teaching_content.html';
        if (file_put_contents($fileEn, $contentEn) === false) {
            throw new Exception('Failed to write English file');
        }
        $written[] = basename($fileEn);
    }
} catch (Throwable $e) {
    error_log('❌ Write error: ' . $e->getMessage());
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to write files']);
    exit;
}

// ✅ Log to DB
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
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_town (town_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    $stmt = $pdo->prepare('
        INSERT INTO teachings (user_id, town_id, province, town, has_af, has_en, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ');
    $stmt->execute([
        $userId,
        $townId > 0 ? $townId : null,
        $province !== '' ? $province : null,
        $town !== '' ? $town : null,
        $contentAf !== '' ? 1 : 0,
        $contentEn !== '' ? 1 : 0
    ]);
} catch (Throwable $e) {
    error_log('⚠️ DB log failed: ' . $e->getMessage());
}

// ✅ SUCCESS
error_log('✅ SAVE SUCCESS');

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