<?php
declare(strict_types=1);

/**
 * Save the Teaching of the Month for the elder's own town - all languages
 * in one request.
 *
 * POST (application/x-www-form-urlencoded)
 *   content_af, content_en, content_zu, content_xh, content_pt, content_st
 *   Header: X-CSRF-Token
 *
 * A language sent empty REMOVES that language's file, so members fall back
 * to another language instead of seeing last month's teaching.
 */

require_once dirname(__DIR__, 3) . '/security/config.php';
require_once dirname(__DIR__, 3) . '/security/session.php';
require_once dirname(__DIR__, 3) . '/security/csrf.php';
require_once dirname(__DIR__, 3) . '/security/auth.php';
require_once dirname(__DIR__, 3) . '/includes/languages.php';
require_once dirname(__DIR__, 3) . '/includes/teaching.php';

ob_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function save_teaching_fail(int $code, string $error): void {
    ob_end_clean();
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $error], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!auth_logged_in()) {
    save_teaching_fail(401, 'Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    save_teaching_fail(405, 'Method not allowed');
}

if (!csrf_verify((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? ''))) {
    save_teaching_fail(403, 'Invalid CSRF token - reload the page');
}

$userId = (int)auth_user_id();

// Elder permissions + the elder's own town. The town is NEVER taken from the
// client, otherwise an elder could overwrite another town's teaching.
try {
    $stmt = $pdo->prepare('
        SELECT u.amp_id, u.town_id, t.name AS town_name, p.name AS province_name
        FROM users u
        LEFT JOIN towns t ON t.id = u.town_id
        LEFT JOIN provinces p ON p.id = t.province_id
        WHERE u.id = ?
        LIMIT 1
    ');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('save_teaching: DB error: ' . $e->getMessage());
    save_teaching_fail(500, 'Database error');
}

if (!$user) {
    save_teaching_fail(403, 'User not found');
}

$ampId = (int)($user['amp_id'] ?? 0);
if ($ampId < 1 || $ampId > 5) {
    save_teaching_fail(403, 'Insufficient permissions');
}

$townId = (int)($user['town_id'] ?? 0);
$province = (string)($user['province_name'] ?? '');
$town = (string)($user['town_name'] ?? '');

// Without ext-dom formatting can't be kept safely - refuse rather than
// silently storing plain text
if (!class_exists('DOMDocument')) {
    error_log('save_teaching: PHP ext-dom is missing');
    save_teaching_fail(500, 'Server is missing the PHP DOM extension');
}

// ---------------------------------------------------------------------------
// Validate + sanitize every language
// ---------------------------------------------------------------------------
$contents = [];
foreach (SUPPORTED_LANGS as $code) {
    $raw = (string)($_POST['content_' . $code] ?? '');
    if (strlen($raw) > TEACHING_MAX_BYTES) {
        save_teaching_fail(413, 'Content too large (' . strtoupper($code) . ')');
    }
    $clean = teaching_sanitize_html($raw);
    $contents[$code] = teaching_has_content($clean) ? $clean : '';
}

// Refuse to wipe every language at once - almost always a mistake
if (implode('', $contents) === '') {
    save_teaching_fail(400, 'No content provided');
}

// ---------------------------------------------------------------------------
// Write files (atomic: temp file + rename, readers never see half a file)
// ---------------------------------------------------------------------------
$targetDir = teaching_dir($province, $town);

if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
    error_log('save_teaching: Failed to create: ' . $targetDir);
    save_teaching_fail(500, 'Failed to create directory');
}

$written = [];
$removed = [];
$hasContent = [];
$temps = [];   // final path => temp path

// 1. Every new file is written to a temp file first. If any of them fails,
//    nothing on disk has changed yet.
try {
    foreach (SUPPORTED_LANGS as $code) {
        $hasContent[$code] = $contents[$code] === '' ? 0 : 1;
        if ($contents[$code] === '') {
            continue;
        }
        $file = teaching_file($targetDir, $code);
        $tmp = $file . '.tmp-' . bin2hex(random_bytes(4));
        if (file_put_contents($tmp, $contents[$code], LOCK_EX) !== strlen($contents[$code])) {
            throw new RuntimeException('Failed to write ' . $code);
        }
        @chmod($tmp, 0644);
        $temps[$file] = $tmp;
    }
} catch (Throwable $e) {
    foreach ($temps as $tmp) {
        @unlink($tmp);
    }
    error_log('save_teaching: Write error: ' . $e->getMessage());
    save_teaching_fail(500, 'Failed to write files');
}

// 2. Swap them in (rename in the same folder is atomic), then remove the
//    languages that were cleared. A file that is already gone is fine
//    (two saves at once).
$failed = false;
foreach ($temps as $file => $tmp) {
    if (@rename($tmp, $file)) {
        $written[] = basename($file);
    } else {
        @unlink($tmp);
        $failed = true;
        error_log('save_teaching: Failed to replace ' . $file);
    }
}
$toRemove = [];
foreach (SUPPORTED_LANGS as $code) {
    if ($contents[$code] === '') {
        $toRemove[] = teaching_file($targetDir, $code);
    }
}
// The legacy English file was shown in the English tab; it is now either
// saved as teaching_content.en.html or was deliberately cleared
$toRemove[] = $targetDir . '/' . TEACHING_LEGACY_EN_FILE;
foreach ($toRemove as $file) {
    if (!is_file($file)) {
        continue;
    }
    if (@unlink($file) || !is_file($file)) {
        $removed[] = basename($file);
    } else {
        $failed = true;
        error_log('save_teaching: Failed to remove ' . $file);
    }
}
if ($failed) {
    save_teaching_fail(500, 'Failed to write files');
}

// ---------------------------------------------------------------------------
// Log to DB (best effort - the files are the source of truth)
// ---------------------------------------------------------------------------
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
            has_st TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_town (town_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Older installs may be missing some per-language columns entirely
    // (e.g. a table created before zu/xh/pt/st were added) - add whatever
    // is missing so the insert below always works
    $existing = [];
    foreach ($pdo->query('SHOW COLUMNS FROM teachings') as $col) {
        $existing[] = $col['Field'];
    }
    foreach (SUPPORTED_LANGS as $code) {
        $colName = 'has_' . $code;
        if (!in_array($colName, $existing, true)) {
            $pdo->exec("ALTER TABLE teachings ADD COLUMN {$colName} TINYINT(1) DEFAULT 0");
        }
    }

    $stmt = $pdo->prepare('
        INSERT INTO teachings (user_id, town_id, province, town, has_af, has_en, has_zu, has_xh, has_pt, has_st, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
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
        $hasContent['pt'] ?? 0,
        $hasContent['st'] ?? 0
    ]);
} catch (Throwable $e) {
    error_log('save_teaching: DB log failed: ' . $e->getMessage());
}

ob_end_clean();
http_response_code(200);
echo json_encode([
    'success'  => true,
    'files'    => $written,
    'removed'  => $removed,
    'has'      => $hasContent,
    'province' => $province,
    'town'     => $town,
    'saved_at' => date('c')
], JSON_UNESCAPED_UNICODE);
exit;
