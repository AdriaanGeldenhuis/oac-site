<?php
declare(strict_types=1);

require_once __DIR__ . '/../security/auth_gate.php';
require_once __DIR__ . '/../includes/languages.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$lang = validate_language((string)($_SESSION['language'] ?? 'af'));

// Check Elder permissions
$stmt = $pdo->prepare('SELECT amp_id, town_id FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || (int)($user['amp_id'] ?? 0) < 1 || (int)($user['amp_id'] ?? 0) > 5) {
    header('Location: /admin/index.php');
    exit;
}

// Get town/province
$townId = $user['town_id'] ?? null;
$cityName = 'Unknown';
$provinceName = 'Unknown';

if ($townId) {
    $stmt = $pdo->prepare('
        SELECT t.name AS town, p.name AS province
        FROM towns t
        LEFT JOIN provinces p ON p.id = t.province_id
        WHERE t.id = ? LIMIT 1
    ');
    $stmt->execute([$townId]);
    $loc = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($loc) {
        $cityName = $loc['town'] ?? 'Unknown';
        $provinceName = $loc['province'] ?? 'Unknown';
    }
}

// MUST match the slug logic in welcome.php and save_teaching.php
function slug(string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/', '_', $s);
    return trim($s, '_') ?: 'unknown';
}

$provinceSlug = slug($provinceName);
$citySlug = slug($cityName);

$baseDir = __DIR__ . '/../welcome';
if ($provinceSlug && $citySlug && $provinceSlug !== 'unknown' && $citySlug !== 'unknown') {
    $baseDir .= '/south_africa/' . $provinceSlug . '/' . $citySlug;
}

if (!is_dir($baseDir)) {
    @mkdir($baseDir, 0755, true);
}

// File paths for all 6 languages
$contentFiles = [
    'af' => $baseDir . '/lering_content.html',
    'en' => $baseDir . '/teaching_content.en.html',
    'zu' => $baseDir . '/teaching_content.zu.html',
    'xh' => $baseDir . '/teaching_content.xh.html',
    'pt' => $baseDir . '/teaching_content.pt.html',
    'st' => $baseDir . '/teaching_content.st.html'
];

// Backwards compatibility
if (!file_exists($contentFiles['en']) && file_exists($baseDir . '/teaching_content.html')) {
    $contentFiles['en'] = $baseDir . '/teaching_content.html';
}

// Load existing content for all languages
$contents = [];
$defaultContent = [
    'af' => '<p>Begin tik hier...</p>',
    'en' => '<p>Start typing here...</p>',
    'zu' => '<p>Qala ukuthayipha lapha...</p>',
    'xh' => '<p>Qala ukuchwetheza apha...</p>',
    'pt' => '<p>Comece a digitar aqui...</p>',
    'st' => '<p>Qala ho thaepa mona...</p>'
];

foreach (SUPPORTED_LANGS as $code) {
    $file = $contentFiles[$code];
    $loaded = file_exists($file) ? file_get_contents($file) : false;
    $contents[$code] = ($loaded !== false && trim($loaded) !== '') ? $loaded : $defaultContent[$code];
}

function t(string $key): string {
    global $lang;
    return __t($key, $lang);
}

// Site colors for toolbar
$siteColors = [
    ['name' => 'Rose Gold', 'value' => '#e8b4a8'],
    ['name' => 'Rose Gold Dark', 'value' => '#c99485'],
    ['name' => 'Peach', 'value' => '#f5d5c8'],
    ['name' => 'Peach Light', 'value' => '#fae8e0'],
    ['name' => 'Silver', 'value' => '#b8b8b8'],
    ['name' => 'White', 'value' => '#ffffff'],
    ['name' => 'Black', 'value' => '#1a1816'],
    ['name' => 'Dark Gray', 'value' => '#3d3835'],
];

// Soft highlight colors that read well on both dark and light themes
$highlightColors = [
    ['name' => 'Rose', 'value' => 'rgba(232, 180, 168, 0.45)'],
    ['name' => 'Amber', 'value' => 'rgba(255, 213, 128, 0.45)'],
    ['name' => 'Green', 'value' => 'rgba(165, 214, 167, 0.45)'],
    ['name' => 'Blue', 'value' => 'rgba(144, 202, 249, 0.45)'],
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('edit_teaching') ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Parisienne&family=Dancing+Script:wght@400;700&family=Great+Vibes&display=swap" rel="stylesheet">

    <style>
        /* ===== THEME VARIABLES ===== */
        :root {
            --bg: #0a0a0a;
            --surface: #1a1a1a;
            --surface-light: #2a2a2a;
            --primary: #e8b4a8;
            --primary-dark: #c99485;
            --peach: #f5d5c8;
            --text: #f5ebe6;
            --text-dim: #c8c0ba;
            --border: rgba(232, 180, 168, 0.2);
            --success: #4caf50;
            --error: #f44336;
            --warning: #ff9800;
            --editor-bg: #0a0a0a;
            --editor-text: #f5ebe6;
        }

        [data-theme="light"] {
            --bg: #faf8f6;
            --surface: #ffffff;
            --surface-light: #f0eeec;
            --primary: #c9847a;
            --primary-dark: #a86860;
            --peach: #1a1816;
            --text: #1a1816;
            --text-dim: #3d3835;
            --border: rgba(169, 104, 96, 0.25);
            --editor-bg: #ffffff;
            --editor-text: #1a1816;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding-top: 80px;
            transition: background 0.4s, color 0.4s;
        }

        .container {
            max-width: 1400px;
            margin: 20px auto 40px;
            padding: 0 20px 100px;
        }

        /* ===== HEADER ===== */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 25px 30px;
            background: var(--surface);
            border-radius: 16px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
            border: 1px solid var(--border);
        }

        .header h1 {
            font-family: 'Parisienne', cursive;
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 8px;
        }

        .location {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            background: var(--surface-light);
            border: 1px solid var(--primary);
            border-radius: 20px;
            font-size: 0.85rem;
        }

        /* ===== TOOLBAR ===== */
        .toolbar {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            background: var(--surface);
            border-radius: 12px;
            margin-bottom: 16px;
            border: 1px solid var(--border);
            flex-wrap: wrap;
        }

        .tool-group {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .tool-group label {
            font-size: 0.8rem;
            color: var(--text-dim);
            font-weight: 500;
        }

        .tool-group select {
            padding: 6px 10px;
            background: var(--surface-light);
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--text);
            font-size: 0.85rem;
            cursor: pointer;
        }

        .tool-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            min-width: 34px;
            min-height: 32px;
            padding: 5px 10px;
            background: var(--surface-light);
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--text);
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .tool-btn svg {
            width: 16px;
            height: 16px;
            pointer-events: none;
        }

        .tool-btn:hover {
            border-color: var(--primary);
            background: var(--surface);
        }

        .tool-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .sep {
            width: 1px;
            height: 28px;
            background: var(--border);
            margin: 0 4px;
        }

        /* Heading buttons with site fonts */
        .btn-h1, .btn-h2, .btn-h3 {
            font-family: 'Parisienne', cursive;
        }
        .btn-h1 { font-size: 1.1rem; }
        .btn-h2 { font-size: 1rem; }
        .btn-h3 { font-size: 0.9rem; }

        /* Color swatches */
        .color-swatch {
            width: 24px;
            height: 24px;
            border-radius: 4px;
            border: 2px solid var(--border);
            cursor: pointer;
            transition: transform 0.2s;
        }
        .color-swatch:hover {
            transform: scale(1.15);
            border-color: var(--primary);
        }

        /* "Remove highlight" swatch - diagonal red line */
        .color-swatch.swatch-none {
            position: relative;
            background: var(--surface-light);
        }
        .color-swatch.swatch-none::after {
            content: '';
            position: absolute;
            inset: 2px;
            background: linear-gradient(135deg, transparent 45%, var(--error) 45%, var(--error) 55%, transparent 55%);
        }

        /* Special buttons */
        .btn-bible {
            background: linear-gradient(135deg, #2196f3, #1976d2);
            color: white;
            border: none;
        }

        .btn-translate {
            background: linear-gradient(135deg, #4caf50, #388e3c);
            color: white;
            border: none;
            min-width: 160px;
            position: relative;
        }

        .btn-improve {
            background: linear-gradient(135deg, #9c27b0, #7b1fa2);
            color: white;
            border: none;
        }

        /* ===== EDITOR ===== */
        .editor-wrap {
            background: var(--editor-bg);
            border: 2px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }

        .editor {
            min-height: 600px;
            padding: 40px;
            outline: none;
            font-family: Georgia, serif;
            font-size: 16px;
            line-height: 1.8;
            color: var(--editor-text);
            background: var(--editor-bg);
        }

        /* Editor heading styles - MATCH WELCOME.CSS exactly */
        .editor h1,
        .editor h2,
        .editor h3 {
            font-weight: 400;
            color: var(--primary);
            margin: 28px 0 14px;
            line-height: 1.3;
        }
        /* Only H1 gets Parisienne cursive */
        .editor h1 {
            font-family: 'Parisienne', cursive;
            font-size: 2rem;
        }
        /* H2 and H3 use system font */
        .editor h2 {
            font-family: system-ui, -apple-system, sans-serif;
            font-size: 1.4rem;
        }
        .editor h3 {
            font-family: system-ui, -apple-system, sans-serif;
            font-size: 1.2rem;
            color: var(--primary-dark);
        }
        .editor h1:first-child,
        .editor h2:first-child,
        .editor h3:first-child {
            margin-top: 0;
        }
        .editor p {
            font-family: system-ui, -apple-system, sans-serif;
            font-size: 16px;
            font-weight: 300;
            margin: 0 0 16px;
            color: var(--editor-text);
            line-height: 1.9;
        }
        .editor p:last-child {
            margin-bottom: 0;
        }
        .editor ul, .editor ol {
            margin: 0 0 16px;
            padding-left: 24px;
        }
        .editor li {
            font-family: system-ui, -apple-system, sans-serif;
            font-size: 16px;
            font-weight: 300;
            margin-bottom: 8px;
            line-height: 1.7;
            color: var(--editor-text);
        }
        .editor strong, .editor b {
            font-weight: 500;
        }
        .editor blockquote {
            margin: 0 0 16px;
            padding: 14px 20px;
            border-left: 3px solid var(--primary);
            background: rgba(232, 180, 168, 0.06);
            border-radius: 0 8px 8px 0;
            font-style: italic;
            color: var(--text-dim);
        }
        .editor blockquote p:last-child {
            margin-bottom: 0;
        }
        .editor hr {
            border: none;
            height: 1px;
            margin: 28px auto;
            background: linear-gradient(90deg, transparent, var(--primary-dark), transparent);
        }
        .editor a {
            color: var(--primary);
            text-decoration: underline;
            text-underline-offset: 2px;
        }

        /* ===== INLINE COLOR SAFETY =====
           Ink colors picked on the opposite theme stay readable here */
        .editor [style*="#3d3835" i],
        .editor [style*="rgb(61, 56, 53)"],
        .editor [style*="rgb(61,56,53)"],
        .editor font[color="#3d3835" i] {
            color: var(--editor-text) !important;
        }

        [data-theme="light"] .editor [style*="#fff" i],
        [data-theme="light"] .editor [style*="rgb(255, 255, 255)"],
        [data-theme="light"] .editor [style*="rgb(255,255,255)"],
        [data-theme="light"] .editor [style*="#fae8e0" i],
        [data-theme="light"] .editor [style*="rgb(250, 232, 224)"],
        [data-theme="light"] .editor [style*="rgb(250,232,224)"],
        [data-theme="light"] .editor [style*="#f5d5c8" i],
        [data-theme="light"] .editor [style*="rgb(245, 213, 200)"],
        [data-theme="light"] .editor [style*="rgb(245,213,200)"],
        [data-theme="light"] .editor [style*="#b8b8b8" i],
        [data-theme="light"] .editor [style*="rgb(184, 184, 184)"],
        [data-theme="light"] .editor [style*="rgb(184,184,184)"],
        [data-theme="light"] .editor font[color="#ffffff" i],
        [data-theme="light"] .editor font[color="white" i],
        [data-theme="light"] .editor font[color="#fae8e0" i],
        [data-theme="light"] .editor font[color="#f5d5c8" i],
        [data-theme="light"] .editor font[color="#b8b8b8" i] {
            color: var(--editor-text) !important;
        }

        [data-theme="light"] .editor [style*="#e8b4a8" i],
        [data-theme="light"] .editor [style*="rgb(232, 180, 168)"],
        [data-theme="light"] .editor [style*="rgb(232,180,168)"],
        [data-theme="light"] .editor [style*="#c99485" i],
        [data-theme="light"] .editor [style*="rgb(201, 148, 133)"],
        [data-theme="light"] .editor [style*="rgb(201,148,133)"],
        [data-theme="light"] .editor font[color="#e8b4a8" i],
        [data-theme="light"] .editor font[color="#c99485" i] {
            color: var(--primary-dark) !important;
        }

        /* Bible verse classes - MATCH WELCOME.CSS exactly */
        .editor .vref {
            font-family: 'Parisienne', cursive;
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--primary);
            margin: 24px 0 8px;
            display: block;
        }
        .editor .vtxt {
            font-style: italic;
            color: var(--text-dim);
            line-height: 1.9;
            padding-left: 16px;
            border-left: 2px solid var(--primary-dark);
            margin: 0 0 20px;
        }
        .editor .vtxt sup {
            font-size: 0.7em;
            font-weight: 600;
            color: var(--primary);
            margin-right: 2px;
            font-style: normal;
            vertical-align: super;
        }

        /* Numbered paragraphs styling */
        .editor p[style*="text-align: center"] {
            text-align: center;
        }

        /* ===== ACTIONS ===== */
        .actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 30px;
            background: var(--surface);
            border-radius: 12px;
            margin-top: 20px;
            border: 1px solid var(--border);
        }

        .status {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: var(--surface-light);
            border: 1px solid var(--border);
            border-radius: 20px;
            font-size: 0.9rem;
        }
        .status.saving { border-color: var(--warning); color: var(--warning); }
        .status.saved { border-color: var(--success); color: var(--success); }
        .status.error { border-color: var(--error); color: var(--error); }
        .status.unsaved { border-color: var(--warning); color: var(--warning); }

        .word-count {
            font-size: 0.85rem;
            color: var(--text-dim);
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.3);
        }

        .btn:disabled, .tool-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }

        /* ===== TRANSLATION OVERLAY ===== */
        .translate-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.95);
            backdrop-filter: blur(10px);
            z-index: 20000;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 30px;
        }
        .translate-overlay.show { display: flex; }

        .translate-spinner {
            width: 80px;
            height: 80px;
            border: 4px solid var(--surface-light);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .translate-text {
            font-family: 'Parisienne', cursive;
            font-size: 2rem;
            color: var(--primary);
            text-align: center;
        }

        .translate-time {
            font-size: 1.2rem;
            color: var(--peach);
            text-align: center;
        }

        .translate-warning {
            font-size: 0.95rem;
            color: var(--text-dim);
            text-align: center;
            max-width: 400px;
        }

        .translate-progress {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .lang-badge {
            padding: 6px 14px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            font-size: 0.85rem;
            color: var(--text-dim);
            transition: all 0.3s;
        }
        .lang-badge.active {
            border-color: var(--warning);
            color: var(--warning);
        }
        .lang-badge.done {
            border-color: var(--success);
            color: var(--success);
        }

        /* ===== MODAL ===== */
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.9);
            backdrop-filter: blur(8px);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal.show { display: flex; }

        .modal-content {
            background: var(--surface);
            border: 2px solid var(--primary);
            border-radius: 16px;
            max-width: 700px;
            width: 100%;
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .modal-header {
            padding: 25px 30px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            font-family: 'Parisienne', cursive;
            font-size: 1.8rem;
            color: var(--primary);
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--text);
            font-size: 2rem;
            cursor: pointer;
        }

        .modal-body {
            padding: 30px;
            overflow-y: auto;
            flex: 1;
        }

        .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--primary);
        }

        .form-control {
            padding: 10px 14px;
            background: var(--surface-light);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            font-size: 0.95rem;
        }

        .verse-preview {
            padding: 20px;
            background: var(--surface-light);
            border-left: 4px solid var(--primary);
            border-radius: 8px;
            max-height: 300px;
            overflow-y: auto;
            line-height: 1.8;
            color: var(--text);
            font-family: Georgia, serif;
        }
        .verse-preview:empty { display: none; }
        .verse-preview strong {
            color: var(--primary);
            font-size: 1.1rem;
            display: block;
            margin-bottom: 12px;
        }
        .verse-preview sup {
            color: var(--primary);
            font-weight: bold;
        }

        .btn-secondary {
            background: var(--surface-light);
            color: var(--text);
            border: 1px solid var(--border);
        }

        /* ===== TABS for language preview ===== */
        .lang-tabs {
            display: flex;
            gap: 0;
            background: var(--surface-light);
            border-radius: 8px;
            padding: 4px;
            margin-bottom: 16px;
        }

        .lang-tab {
            padding: 8px 16px;
            border: none;
            background: transparent;
            color: var(--text-dim);
            font-weight: 500;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.2s;
            font-size: 0.85rem;
        }

        .lang-tab.active {
            background: var(--primary);
            color: white;
        }

        /* Green dot on tabs that already contain real content */
        .lang-tab.filled::after {
            content: '';
            display: inline-block;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--success);
            margin-left: 7px;
            vertical-align: middle;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .header { flex-direction: column; gap: 20px; }
            .toolbar { padding: 10px; }
            .sep { display: none; }
            .form-grid { grid-template-columns: 1fr; }
            .lang-tabs { flex-wrap: wrap; }
            .editor { padding: 20px; min-height: 400px; }
            .actions { flex-direction: column; gap: 14px; }
            .actions .btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../header_footer/header.php'; ?>

    <div class="container">
        <div class="header">
            <div>
                <h1><?= t('monthly_teaching') ?></h1>
                <span class="location">
                    <?= htmlspecialchars($cityName) ?>, <?= htmlspecialchars($provinceName) ?>
                </span>
            </div>
            <!-- Language tabs for viewing different translations -->
            <div class="lang-tabs">
                <?php foreach (SUPPORTED_LANGS as $code): ?>
                <button class="lang-tab <?= $code === 'af' ? 'active' : '' ?>" data-lang="<?= $code ?>"><?= htmlspecialchars(LANG_NAMES[$code]) ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- TOOLBAR ROW 1: History, Blocks & Text Formatting -->
        <div class="toolbar">
            <div class="tool-group">
                <button class="tool-btn" id="btn-undo" title="<?= htmlspecialchars(t('undo')) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/></svg>
                </button>
                <button class="tool-btn" id="btn-redo" title="<?= htmlspecialchars(t('redo')) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 14 5-5-5-5"/><path d="M20 9H9.5a5.5 5.5 0 0 0 0 11H13"/></svg>
                </button>
            </div>

            <div class="sep"></div>

            <div class="tool-group">
                <button class="tool-btn btn-h1" id="btn-h1" title="<?= htmlspecialchars(t('heading')) ?> 1">H1</button>
                <button class="tool-btn btn-h2" id="btn-h2" title="<?= htmlspecialchars(t('heading')) ?> 2">H2</button>
                <button class="tool-btn btn-h3" id="btn-h3" title="<?= htmlspecialchars(t('heading')) ?> 3">H3</button>
                <button class="tool-btn" id="btn-p" title="<?= htmlspecialchars(t('paragraph')) ?>">P</button>
            </div>

            <div class="sep"></div>

            <div class="tool-group">
                <button class="tool-btn" id="btn-bold" title="<?= htmlspecialchars(t('bold')) ?>"><b>B</b></button>
                <button class="tool-btn" id="btn-italic" title="<?= htmlspecialchars(t('italic')) ?>"><i>I</i></button>
                <button class="tool-btn" id="btn-underline" title="<?= htmlspecialchars(t('underline')) ?>"><u>U</u></button>
                <button class="tool-btn" id="btn-strike" title="<?= htmlspecialchars(t('strikethrough')) ?>"><s>S</s></button>
                <button class="tool-btn" id="btn-clear" title="<?= htmlspecialchars(t('clear_formatting')) ?>"><span><s>T</s>x</span></button>
            </div>

            <div class="sep"></div>

            <div class="tool-group">
                <label><?= t('size') ?></label>
                <select id="font-size">
                    <option value="" id="fs-custom" hidden></option>
                    <option value="12px">12</option>
                    <option value="14px">14</option>
                    <option value="16px" selected>16</option>
                    <option value="18px">18</option>
                    <option value="20px">20</option>
                    <option value="24px">24</option>
                    <option value="28px">28</option>
                    <option value="32px">32</option>
                    <option value="36px">36</option>
                    <option value="48px">48</option>
                </select>
            </div>

            <div class="tool-group">
                <label><?= t('line_spacing') ?></label>
                <select id="line-spacing">
                    <option value="" id="ls-custom" hidden></option>
                    <option value="1">1.0</option>
                    <option value="1.5" selected>1.5</option>
                    <option value="1.8">1.8</option>
                    <option value="2">2.0</option>
                    <option value="2.5">2.5</option>
                </select>
            </div>

            <div class="sep"></div>

            <div class="tool-group">
                <button class="tool-btn" id="btn-left" title="<?= htmlspecialchars(t('align_left')) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="14" y2="12"/><line x1="4" y1="18" x2="18" y2="18"/></svg>
                </button>
                <button class="tool-btn" id="btn-center" title="<?= htmlspecialchars(t('align_center')) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="4" y1="6" x2="20" y2="6"/><line x1="7" y1="12" x2="17" y2="12"/><line x1="5" y1="18" x2="19" y2="18"/></svg>
                </button>
                <button class="tool-btn" id="btn-right" title="<?= htmlspecialchars(t('align_right')) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="4" y1="6" x2="20" y2="6"/><line x1="10" y1="12" x2="20" y2="12"/><line x1="6" y1="18" x2="20" y2="18"/></svg>
                </button>
                <button class="tool-btn" id="btn-justify" title="<?= htmlspecialchars(t('align_justify')) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>
                </button>
            </div>

            <div class="sep"></div>

            <div class="tool-group">
                <button class="tool-btn" id="btn-ul" title="<?= htmlspecialchars(t('bullet_list')) ?>">&#8226; <?= t('bullet_list') ?></button>
                <button class="tool-btn" id="btn-ol" title="<?= htmlspecialchars(t('numbered_list')) ?>">1. <?= t('numbered_list') ?></button>
                <button class="tool-btn" id="btn-outdent" title="<?= htmlspecialchars(t('outdent')) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="7 8 3 12 7 16"/><line x1="21" y1="6" x2="11" y2="6"/><line x1="21" y1="12" x2="11" y2="12"/><line x1="21" y1="18" x2="11" y2="18"/></svg>
                </button>
                <button class="tool-btn" id="btn-indent" title="<?= htmlspecialchars(t('indent')) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 8 7 12 3 16"/><line x1="21" y1="6" x2="11" y2="6"/><line x1="21" y1="12" x2="11" y2="12"/><line x1="21" y1="18" x2="11" y2="18"/></svg>
                </button>
            </div>
        </div>

        <!-- TOOLBAR ROW 2: Colors, Inserts & Actions -->
        <div class="toolbar">
            <div class="tool-group">
                <label><?= t('text_color') ?></label>
                <?php foreach ($siteColors as $color): ?>
                <button class="color-swatch"
                        style="background: <?= $color['value'] ?>"
                        data-color="<?= $color['value'] ?>"
                        data-type="text"
                        title="<?= $color['name'] ?>"></button>
                <?php endforeach; ?>
            </div>

            <div class="sep"></div>

            <div class="tool-group">
                <label><?= t('highlight_color') ?></label>
                <?php foreach ($highlightColors as $color): ?>
                <button class="color-swatch"
                        style="background: <?= $color['value'] ?>"
                        data-color="<?= $color['value'] ?>"
                        data-type="highlight"
                        title="<?= $color['name'] ?>"></button>
                <?php endforeach; ?>
                <button class="color-swatch swatch-none"
                        data-color="transparent"
                        data-type="highlight"
                        title="<?= htmlspecialchars(t('remove_highlight')) ?>"></button>
            </div>

            <div class="sep"></div>

            <div class="tool-group">
                <button class="tool-btn" id="btn-quote" title="<?= htmlspecialchars(t('quote_block')) ?>">&#10077;</button>
                <button class="tool-btn" id="btn-hr" title="<?= htmlspecialchars(t('divider')) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="4" y1="12" x2="20" y2="12"/><line x1="7" y1="6" x2="17" y2="6" opacity=".35"/><line x1="7" y1="18" x2="17" y2="18" opacity=".35"/></svg>
                </button>
                <button class="tool-btn" id="btn-link" title="<?= htmlspecialchars(t('insert_link')) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                </button>
                <button class="tool-btn" id="btn-unlink" title="<?= htmlspecialchars(t('remove_link')) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18.84 12.25l1.72-1.71a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M5.17 11.75l-1.72 1.71a5 5 0 0 0 7.07 7.07l1.72-1.71"/><line x1="8" y1="2" x2="8" y2="5"/><line x1="2" y1="8" x2="5" y2="8"/><line x1="16" y1="19" x2="16" y2="22"/><line x1="19" y1="16" x2="22" y2="16"/></svg>
                </button>
            </div>

            <div class="sep"></div>

            <button class="tool-btn btn-bible" id="btn-verse">
                <?= t('add_verse') ?>
            </button>

            <button class="tool-btn btn-improve" id="btn-improve">
                <?= t('improve') ?>
            </button>

            <button class="tool-btn btn-translate" id="btn-translate-all">
                <?= t('translate_all') ?>
            </button>
        </div>

        <!-- Editors for each language -->
        <div class="editor-wrap">
            <?php foreach (SUPPORTED_LANGS as $code): ?>
            <div class="editor"
                 id="editor-<?= $code ?>"
                 contenteditable="true"
                 data-lang="<?= $code ?>"
                 <?= $code !== 'af' ? 'style="display:none"' : '' ?>><?= $contents[$code] ?></div>
            <?php endforeach; ?>
        </div>

        <div class="actions">
            <span class="status" id="status">
                <span id="status-icon">&#128190;</span>
                <span id="status-text"><?= t('ready_to_save') ?></span>
            </span>
            <span class="word-count" id="word-count"></span>
            <button class="btn btn-primary" id="btn-save">
                <?= t('save_all') ?>
            </button>
        </div>
    </div>

    <!-- Translation Overlay -->
    <div id="translate-overlay" class="translate-overlay">
        <div class="translate-spinner"></div>
        <div class="translate-text"><?= t('translating') ?>...</div>
        <div class="translate-time">
            <?= t('translation_time') ?>
        </div>
        <div class="translate-warning">
            <?= t('do_not_close') ?>
        </div>
        <div class="translate-progress">
            <?php foreach (SUPPORTED_LANGS as $code): ?>
            <span class="lang-badge" data-lang="<?= $code ?>"><?= LANG_NAMES[$code] ?></span>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Verse Modal -->
    <div id="verse-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><?= t('add_bible_verse') ?></h2>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label><?= t('book') ?></label>
                        <select id="v-book" class="form-control">
                            <option value=""><?= t('select') ?>...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><?= t('chapter') ?></label>
                        <select id="v-chapter" class="form-control"></select>
                    </div>
                    <div class="form-group">
                        <label><?= t('from') ?></label>
                        <select id="v-from" class="form-control"></select>
                    </div>
                    <div class="form-group">
                        <label><?= t('to') ?></label>
                        <select id="v-to" class="form-control"></select>
                    </div>
                </div>
                <div id="v-preview" class="verse-preview"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary modal-close"><?= t('cancel') ?></button>
                <button class="btn btn-primary" id="btn-insert"><?= t('insert') ?></button>
            </div>
        </div>
    </div>

    <script>
    (function() {
        'use strict';

        // Configuration
        const CONFIG = {
            supportedLangs: <?= json_encode(SUPPORTED_LANGS) ?>,
            bibleFiles: <?= json_encode(BIBLE_FILES) ?>,
            langNames: <?= json_encode(LANG_NAMES) ?>,
            placeholders: <?= json_encode(array_map('strip_tags', $defaultContent), JSON_UNESCAPED_UNICODE) ?>
        };

        // Translated UI strings
        const STR = <?= json_encode([
            'saving' => t('saving'),
            'saved' => t('saved'),
            'save_failed' => t('save_failed'),
            'unsaved_changes' => t('unsaved_changes'),
            'translate_confirm' => t('translate_confirm'),
            'translations_done_saved' => t('translations_done_saved'),
            'improving' => t('improving'),
            'improved' => t('improved'),
            'verse_inserted' => t('verse_inserted'),
            'bible_not_available' => t('bible_not_available'),
            'select_all_fields' => t('select_all_fields'),
            'editor_empty' => t('editor_empty'),
            'translation_stopped' => t('translation_stopped'),
            'languages_completed' => t('languages_completed'),
            'ready_to_save' => t('ready_to_save'),
            'select' => t('select'),
            'improve' => t('improve'),
            'words' => t('words'),
            'characters' => t('characters'),
            'link_prompt' => t('link_prompt')
        ], JSON_UNESCAPED_UNICODE) ?>;

        // State
        let currentLang = 'af';
        let editors = {};
        let bibles = {};
        let hasChanges = false;
        let isSaving = false;

        // Get all editors
        CONFIG.supportedLangs.forEach(code => {
            editors[code] = document.getElementById('editor-' + code);
        });

        // Current editor getter
        const getCurrentEditor = () => editors[currentLang];

        // Mark content as changed and reflect it in the status badge
        function markUnsaved() {
            hasChanges = true;
            showStatus('unsaved', STR.unsaved_changes);
        }

        // Does this editor contain real content (not empty, not the placeholder)?
        function editorHasContent(code) {
            const ed = editors[code];
            if (!ed) return false;
            const text = ed.textContent.trim();
            return text !== '' && text !== (CONFIG.placeholders[code] || '').trim();
        }

        // Green dot on tabs that already have real content
        function updateTabIndicators() {
            document.querySelectorAll('.lang-tab').forEach(tab => {
                tab.classList.toggle('filled', editorHasContent(tab.dataset.lang));
            });
        }

        // ===== PASTE HANDLER - Plain paste, user chooses formatting =====
        Object.values(editors).forEach(ed => {
            if (!ed) return;
            ed.addEventListener('paste', function(e) {
                e.preventDefault();
                markUnsaved();

                const html = e.clipboardData.getData('text/html');
                const text = e.clipboardData.getData('text/plain');

                if (html) {
                    const temp = document.createElement('div');
                    temp.innerHTML = html;

                    // Remove Word-specific junk
                    temp.querySelectorAll('meta, link, style, script, xml').forEach(el => el.remove());

                    // Unwrap o:p tags (Word paragraph markers)
                    temp.querySelectorAll('o\\:p').forEach(el => {
                        el.replaceWith(...el.childNodes);
                    });

                    // Convert ALL block elements to plain paragraphs
                    temp.querySelectorAll('p, h1, h2, h3, h4, h5, h6, div').forEach(el => {
                        const textContent = el.textContent.trim();

                        // Skip empty elements
                        if (!textContent) {
                            el.remove();
                            return;
                        }

                        // Convert everything to plain paragraph
                        const p = document.createElement('p');

                        // Keep only basic inline formatting: b, i, u, strong, em, sup, sub
                        let cleanedInner = el.innerHTML
                            .replace(/<span[^>]*>([\s\S]*?)<\/span>/gi, '$1')
                            .replace(/<font[^>]*>([\s\S]*?)<\/font>/gi, '$1')
                            .replace(/\sclass="[^"]*"/gi, '')
                            .replace(/\sstyle="[^"]*"/gi, '');

                        p.innerHTML = cleanedInner;
                        el.replaceWith(p);
                    });

                    // Process lists - keep structure but clean
                    temp.querySelectorAll('ul, ol').forEach(list => {
                        list.removeAttribute('style');
                        list.removeAttribute('class');
                        list.querySelectorAll('li').forEach(li => {
                            li.removeAttribute('style');
                            li.removeAttribute('class');
                        });
                    });

                    // Clean up the final HTML
                    let cleanHtml = temp.innerHTML
                        .replace(/<!--[\s\S]*?-->/g, '')
                        .replace(/<\/?o:[^>]*>/gi, '')
                        .replace(/<\/?w:[^>]*>/gi, '')
                        .replace(/<\/?m:[^>]*>/gi, '')
                        .replace(/\s+style=""/gi, '')
                        .replace(/\s+class=""/gi, '')
                        .replace(/<p>\s*<\/p>/gi, '')
                        .replace(/<p>\s*&nbsp;\s*<\/p>/gi, '');

                    document.execCommand('insertHTML', false, cleanHtml);
                } else if (text) {
                    // Plain text - just wrap in paragraphs
                    const paragraphs = text.split(/\n\n+/);
                    const htmlParts = paragraphs.map(para => {
                        const trimmed = para.trim();
                        if (!trimmed) return '';
                        return `<p>${trimmed}</p>`;
                    }).filter(p => p);
                    document.execCommand('insertHTML', false, htmlParts.join(''));
                }
            });

            // Track changes
            ed.addEventListener('input', () => {
                markUnsaved();
                updateTabIndicators();
            });
        });

        // ===== LANGUAGE TABS =====
        document.querySelectorAll('.lang-tab').forEach(tab => {
            tab.onclick = () => {
                currentLang = tab.dataset.lang;
                document.querySelectorAll('.lang-tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');

                // Show/hide editors
                CONFIG.supportedLangs.forEach(code => {
                    if (editors[code]) {
                        editors[code].style.display = (code === currentLang) ? 'block' : 'none';
                    }
                });

                loadBible(currentLang);
                updateToolbarState();
            };
        });

        // ===================================================================
        // FORMATTING ENGINE
        // ===================================================================
        const BLOCK_TAGS = ['P', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'LI', 'BLOCKQUOTE', 'PRE', 'DIV'];

        // New lines create <p> instead of <div>
        try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch(e) {}

        // ----- Selection helpers -----

        // Returns {sel, range, editor} when the selection lives inside the
        // currently visible editor, otherwise null
        function selectionInEditor() {
            const sel = window.getSelection();
            if (!sel.rangeCount) return null;
            const range = sel.getRangeAt(0);
            const editor = getCurrentEditor();
            if (!editor || !editor.contains(range.commonAncestorContainer)) return null;
            return { sel, range, editor };
        }

        function closestBlock(node) {
            const editor = getCurrentEditor();
            while (node && node !== editor) {
                if (node.nodeType === 1 && BLOCK_TAGS.includes(node.tagName)) return node;
                node = node.parentNode;
            }
            return null;
        }

        // True only when the range actually overlaps the block's content,
        // not when it merely touches a boundary (e.g. triple-click selections
        // that end at the start of the next paragraph)
        function rangeOverlapsBlock(range, blk) {
            const r = document.createRange();
            r.selectNodeContents(blk);
            return range.compareBoundaryPoints(Range.START_TO_END, r) > 0 &&
                   range.compareBoundaryPoints(Range.END_TO_START, r) < 0;
        }

        // All innermost block elements covered by the current selection
        function getSelectedBlocks() {
            const ctx = selectionInEditor();
            if (!ctx) return [];
            const candidates = [];
            ctx.editor.querySelectorAll(BLOCK_TAGS.join(',')).forEach(blk => {
                if (rangeOverlapsBlock(ctx.range, blk)) candidates.push(blk);
            });
            const blocks = candidates.filter(b => !candidates.some(o => o !== b && b.contains(o)));
            if (!blocks.length) {
                const blk = closestBlock(ctx.sel.anchorNode);
                if (blk) blocks.push(blk);
            }
            return blocks;
        }

        // Commands that need a selection get the whole current block when
        // the cursor is just sitting somewhere - so clicking a color or size
        // always visibly does something
        function expandToBlockIfCollapsed(sel) {
            if (!sel.isCollapsed) return;
            const blk = closestBlock(sel.anchorNode);
            if (!blk) return;
            const r = document.createRange();
            r.selectNodeContents(blk);
            sel.removeAllRanges();
            sel.addRange(r);
        }

        function unwrap(el) {
            const parent = el.parentNode;
            while (el.firstChild) parent.insertBefore(el.firstChild, el);
            parent.removeChild(el);
        }

        function clearFontSizeStyle(el) {
            el.style.fontSize = '';
            if (!el.getAttribute('style')) el.removeAttribute('style');
            if (el.tagName === 'SPAN' && !el.attributes.length) unwrap(el);
        }

        function execCmd(cmd, val = null) {
            getCurrentEditor().focus();
            document.execCommand(cmd, false, val);
            markUnsaved();
            updateToolbarState();
        }

        // ----- Block type (H1 / H2 / H3 / P) -----
        // Uses the browser's formatBlock (handles multi-block selections and
        // lists correctly), then strips inline font sizes inside headings so
        // the site's heading styles actually show
        function setBlockType(tag) {
            const editor = getCurrentEditor();
            editor.focus();
            const ctx = selectionInEditor();
            if (!ctx) return;

            let target = tag.toUpperCase();
            const cur = closestBlock(ctx.sel.anchorNode);
            if (target !== 'P' && cur && cur.tagName === target) {
                target = 'P'; // clicking the active heading toggles back to paragraph
            }

            document.execCommand('formatBlock', false, '<' + target + '>');

            if (/^H[1-6]$/.test(target)) {
                getSelectedBlocks().forEach(blk => {
                    if (!/^H[1-6]$/.test(blk.tagName)) return;
                    blk.style.fontSize = '';
                    if (!blk.getAttribute('style')) blk.removeAttribute('style');
                    blk.querySelectorAll('span, font').forEach(el => {
                        if (el.style && el.style.fontSize) clearFontSizeStyle(el);
                        if (el.tagName === 'FONT') el.removeAttribute('size');
                    });
                });
            }
            markUnsaved();
            updateToolbarState();
        }

        // ----- Font size -----
        // Collapsed cursor: the whole current block gets the size.
        // Real selection: the browser splits the text nodes cleanly via the
        // legacy fontSize command, then the <font> tags are swapped for
        // proper px spans and conflicting nested sizes are removed.
        function applyFontSize(px) {
            const editor = getCurrentEditor();
            editor.focus();
            const ctx = selectionInEditor();
            if (!ctx) return;

            if (ctx.sel.isCollapsed) {
                const blk = closestBlock(ctx.sel.anchorNode);
                if (!blk) return;
                blk.querySelectorAll('span, font').forEach(el => {
                    if (el.style && el.style.fontSize) clearFontSizeStyle(el);
                    if (el.tagName === 'FONT') el.removeAttribute('size');
                });
                blk.style.fontSize = px;
            } else {
                document.execCommand('styleWithCSS', false, false);
                document.execCommand('fontSize', false, '7');
                const spans = [];
                editor.querySelectorAll('font[size="7"]').forEach(f => {
                    const span = document.createElement('span');
                    span.style.fontSize = px;
                    while (f.firstChild) span.appendChild(f.firstChild);
                    f.replaceWith(span);
                    span.querySelectorAll('span, font').forEach(el => {
                        if (el.style && el.style.fontSize) clearFontSizeStyle(el);
                        if (el.tagName === 'FONT') el.removeAttribute('size');
                    });
                    spans.push(span);
                });
                if (spans.length) {
                    const r = document.createRange();
                    r.setStartBefore(spans[0]);
                    r.setEndAfter(spans[spans.length - 1]);
                    ctx.sel.removeAllRanges();
                    ctx.sel.addRange(r);
                }
            }
            markUnsaved();
            updateToolbarState();
        }

        // ----- Line spacing: every block in the selection -----
        function applyLineSpacing(value) {
            getCurrentEditor().focus();
            const blocks = getSelectedBlocks();
            if (!blocks.length) return;
            blocks.forEach(b => { b.style.lineHeight = value; });
            markUnsaved();
            updateToolbarState();
        }

        // ----- Colors -----
        function applyColor(color) {
            const editor = getCurrentEditor();
            editor.focus();
            const ctx = selectionInEditor();
            if (!ctx) return;
            expandToBlockIfCollapsed(ctx.sel);
            document.execCommand('styleWithCSS', false, true);
            document.execCommand('foreColor', false, color);
            document.execCommand('styleWithCSS', false, false);
            markUnsaved();
            updateToolbarState();
        }

        function applyHighlight(color) {
            const editor = getCurrentEditor();
            editor.focus();
            const ctx = selectionInEditor();
            if (!ctx) return;
            expandToBlockIfCollapsed(ctx.sel);
            document.execCommand('styleWithCSS', false, true);
            let ok = false;
            try { ok = document.execCommand('hiliteColor', false, color); } catch(e) {}
            if (!ok) {
                try { document.execCommand('backColor', false, color); } catch(e) {}
            }
            document.execCommand('styleWithCSS', false, false);
            markUnsaved();
            updateToolbarState();
        }

        // ----- Quote block toggle -----
        function toggleQuote() {
            const editor = getCurrentEditor();
            editor.focus();
            const ctx = selectionInEditor();
            if (!ctx) return;
            let el = ctx.sel.anchorNode;
            if (el && el.nodeType === 3) el = el.parentElement;
            const bq = el && el.closest ? el.closest('blockquote') : null;

            if (bq && editor.contains(bq)) {
                document.execCommand('formatBlock', false, '<P>');
                // Some browsers leave the old blockquote wrapper behind
                let el2 = window.getSelection().anchorNode;
                if (el2 && el2.nodeType === 3) el2 = el2.parentElement;
                const left = el2 && el2.closest ? el2.closest('blockquote') : null;
                if (left && editor.contains(left)) unwrap(left);
            } else {
                document.execCommand('formatBlock', false, '<BLOCKQUOTE>');
            }
            markUnsaved();
            updateToolbarState();
        }

        // ----- Clear formatting -----
        function clearFormatting() {
            const editor = getCurrentEditor();
            editor.focus();
            const ctx = selectionInEditor();
            if (!ctx) return;
            expandToBlockIfCollapsed(ctx.sel);
            document.execCommand('removeFormat');
            document.execCommand('unlink');
            getSelectedBlocks().forEach(blk => {
                ['fontSize', 'color', 'backgroundColor', 'lineHeight', 'textAlign'].forEach(prop => {
                    blk.style[prop] = '';
                });
                if (!blk.getAttribute('style')) blk.removeAttribute('style');
            });
            markUnsaved();
            updateToolbarState();
        }

        // ----- Links -----
        function insertLink() {
            const editor = getCurrentEditor();
            editor.focus();
            const ctx = selectionInEditor();
            if (!ctx) return;
            const savedRange = ctx.range.cloneRange();

            let url = prompt(STR.link_prompt, 'https://');
            if (!url) return;
            url = url.trim();
            if (!/^https?:\/\//i.test(url)) url = 'https://' + url;

            editor.focus();
            ctx.sel.removeAllRanges();
            ctx.sel.addRange(savedRange);

            if (savedRange.collapsed) {
                const a = document.createElement('a');
                a.href = url;
                a.textContent = url.replace(/^https?:\/\//i, '');
                savedRange.insertNode(a);
                const r = document.createRange();
                r.setStartAfter(a);
                r.collapse(true);
                ctx.sel.removeAllRanges();
                ctx.sel.addRange(r);
            } else {
                document.execCommand('createLink', false, url);
            }
            editor.querySelectorAll('a').forEach(a => {
                a.target = '_blank';
                a.rel = 'noopener';
            });
            markUnsaved();
            updateToolbarState();
        }

        function removeLink() {
            const editor = getCurrentEditor();
            editor.focus();
            const ctx = selectionInEditor();
            if (!ctx) return;
            if (ctx.sel.isCollapsed) {
                let el = ctx.sel.anchorNode;
                if (el && el.nodeType === 3) el = el.parentElement;
                const a = el && el.closest ? el.closest('a') : null;
                if (a && editor.contains(a)) {
                    const r = document.createRange();
                    r.selectNodeContents(a);
                    ctx.sel.removeAllRanges();
                    ctx.sel.addRange(r);
                }
            }
            document.execCommand('unlink');
            markUnsaved();
            updateToolbarState();
        }

        // ----- Toolbar wiring -----

        document.getElementById('btn-undo').onclick = () => execCmd('undo');
        document.getElementById('btn-redo').onclick = () => execCmd('redo');

        document.getElementById('btn-h1').onclick = () => setBlockType('h1');
        document.getElementById('btn-h2').onclick = () => setBlockType('h2');
        document.getElementById('btn-h3').onclick = () => setBlockType('h3');
        document.getElementById('btn-p').onclick = () => setBlockType('p');

        document.getElementById('btn-bold').onclick = () => execCmd('bold');
        document.getElementById('btn-italic').onclick = () => execCmd('italic');
        document.getElementById('btn-underline').onclick = () => execCmd('underline');
        document.getElementById('btn-strike').onclick = () => execCmd('strikeThrough');
        document.getElementById('btn-clear').onclick = clearFormatting;

        document.getElementById('btn-left').onclick = () => execCmd('justifyLeft');
        document.getElementById('btn-center').onclick = () => execCmd('justifyCenter');
        document.getElementById('btn-right').onclick = () => execCmd('justifyRight');
        document.getElementById('btn-justify').onclick = () => execCmd('justifyFull');

        document.getElementById('btn-ul').onclick = () => execCmd('insertUnorderedList');
        document.getElementById('btn-ol').onclick = () => execCmd('insertOrderedList');
        document.getElementById('btn-indent').onclick = () => execCmd('indent');
        document.getElementById('btn-outdent').onclick = () => execCmd('outdent');

        document.getElementById('btn-quote').onclick = toggleQuote;
        document.getElementById('btn-hr').onclick = () => execCmd('insertHorizontalRule');
        document.getElementById('btn-link').onclick = insertLink;
        document.getElementById('btn-unlink').onclick = removeLink;

        document.getElementById('font-size').onchange = function() {
            if (this.value) applyFontSize(this.value);
        };

        document.getElementById('line-spacing').onchange = function() {
            if (this.value) applyLineSpacing(this.value);
        };

        document.querySelectorAll('.color-swatch').forEach(swatch => {
            swatch.onclick = function() {
                if (this.dataset.type === 'highlight') {
                    applyHighlight(this.dataset.color);
                } else {
                    applyColor(this.dataset.color);
                }
            };
        });

        // Tab / Shift+Tab indents and outdents (handy inside lists)
        Object.values(editors).forEach(ed => {
            if (!ed) return;
            ed.addEventListener('keydown', e => {
                if (e.key === 'Tab') {
                    e.preventDefault();
                    execCmd(e.shiftKey ? 'outdent' : 'indent');
                }
            });
        });

        // ===================================================================
        // TOOLBAR STATE SYNC - buttons and dropdowns follow the cursor
        // ===================================================================

        function setActive(id, on) {
            const el = document.getElementById(id);
            if (el) el.classList.toggle('active', !!on);
        }

        function reflectFontSize(px) {
            const sel = document.getElementById('font-size');
            const custom = document.getElementById('fs-custom');
            const val = px + 'px';
            if (Array.from(sel.options).some(o => o !== custom && o.value === val)) {
                custom.textContent = '';
                sel.value = val;
            } else {
                custom.value = val;
                custom.textContent = px;
                sel.value = val;
            }
        }

        function reflectLineSpacing(ratio) {
            const sel = document.getElementById('line-spacing');
            const custom = document.getElementById('ls-custom');
            const match = Array.from(sel.options).find(o =>
                o !== custom && Math.abs(parseFloat(o.value) - ratio) < 0.05);
            if (match) {
                custom.textContent = '';
                sel.value = match.value;
            } else {
                const v = (Math.round(ratio * 10) / 10).toString();
                custom.value = v;
                custom.textContent = v;
                sel.value = v;
            }
        }

        function updateWordCount() {
            const out = document.getElementById('word-count');
            if (!out) return;
            const ed = getCurrentEditor();
            const text = ed ? ed.innerText.trim() : '';
            const words = text ? (text.match(/\S+/g) || []).length : 0;
            const chars = text.replace(/\s+/g, ' ').length;
            out.textContent = words + ' ' + STR.words + ' · ' + chars + ' ' + STR.characters;
        }

        function updateToolbarState() {
            const ctx = selectionInEditor();
            const q = cmd => {
                try { return document.queryCommandState(cmd); } catch(e) { return false; }
            };
            const on = !!ctx;

            setActive('btn-bold', on && q('bold'));
            setActive('btn-italic', on && q('italic'));
            setActive('btn-underline', on && q('underline'));
            setActive('btn-strike', on && q('strikeThrough'));
            setActive('btn-left', on && q('justifyLeft'));
            setActive('btn-center', on && q('justifyCenter'));
            setActive('btn-right', on && q('justifyRight'));
            setActive('btn-justify', on && q('justifyFull'));
            setActive('btn-ul', on && q('insertUnorderedList'));
            setActive('btn-ol', on && q('insertOrderedList'));

            let tag = '', inQuote = false, el = null;
            if (ctx) {
                el = ctx.sel.focusNode;
                if (el && el.nodeType === 3) el = el.parentElement;
                const blk = closestBlock(ctx.sel.focusNode);
                tag = blk ? blk.tagName : '';
                if (el && el.closest) {
                    const bq = el.closest('blockquote');
                    inQuote = !!(bq && ctx.editor.contains(bq));
                }
            }
            setActive('btn-h1', tag === 'H1');
            setActive('btn-h2', tag === 'H2');
            setActive('btn-h3', tag === 'H3');
            setActive('btn-p', tag === 'P');
            setActive('btn-quote', inQuote);

            if (ctx && el && ctx.editor.contains(el)) {
                const cs = getComputedStyle(el);
                reflectFontSize(Math.round(parseFloat(cs.fontSize)));

                const blk = closestBlock(ctx.sel.focusNode) || el;
                const bcs = getComputedStyle(blk);
                const ratio = bcs.lineHeight === 'normal'
                    ? 1.2
                    : parseFloat(bcs.lineHeight) / parseFloat(bcs.fontSize);
                reflectLineSpacing(ratio);
            }
            updateWordCount();
        }

        let stateRaf = null;
        document.addEventListener('selectionchange', () => {
            if (stateRaf) return;
            stateRaf = requestAnimationFrame(() => {
                stateRaf = null;
                updateToolbarState();
            });
        });

        // Escape closes any open modal
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.show').forEach(m => m.classList.remove('show'));
            }
        });

        // White ink (dark theme) and near-black ink (light theme) ARE the
        // theme defaults - storing them literally makes the text invisible
        // on the opposite theme. Strip them so the text adapts to any theme.
        const THEME_INK = ['rgb(255,255,255)', '#ffffff', '#fff', 'white', 'rgb(26,24,22)', '#1a1816'];

        function stripThemeInkColors(root) {
            root.querySelectorAll('[style*="color" i]').forEach(el => {
                const c = (el.style.color || '').toLowerCase().replace(/\s+/g, '');
                if (c && THEME_INK.includes(c)) {
                    el.style.color = '';
                    if (!el.getAttribute('style')) el.removeAttribute('style');
                    if (el.tagName === 'SPAN' && !el.attributes.length) unwrap(el);
                }
            });
            root.querySelectorAll('font[color]').forEach(f => {
                const c = (f.getAttribute('color') || '').toLowerCase().replace(/\s+/g, '');
                if (THEME_INK.includes(c)) f.removeAttribute('color');
            });
        }

        // Make legacy content predictable: top-level <div> becomes <p>,
        // stray inline nodes get wrapped in a paragraph
        function normalizeEditor(ed) {
            stripThemeInkColors(ed);
            Array.from(ed.children).forEach(child => {
                if (child.tagName === 'DIV') {
                    const p = document.createElement('p');
                    p.innerHTML = child.innerHTML;
                    const style = child.getAttribute('style');
                    if (style) p.setAttribute('style', style);
                    child.replaceWith(p);
                }
            });
            const blockLevel = BLOCK_TAGS.concat(['UL', 'OL', 'HR', 'TABLE']);
            let wrap = null;
            Array.from(ed.childNodes).forEach(node => {
                if (node.nodeType === 1 && blockLevel.includes(node.tagName)) {
                    wrap = null;
                    return;
                }
                if (node.nodeType === 3 && !node.textContent.trim()) return;
                if (!wrap) {
                    wrap = document.createElement('p');
                    ed.insertBefore(wrap, node);
                }
                wrap.appendChild(node);
            });
        }

        // ===== BIBLE LOADING =====
        async function loadBible(lang) {
            if (bibles[lang]) return bibles[lang];

            const file = CONFIG.bibleFiles[lang] || CONFIG.bibleFiles['en'];
            try {
                const res = await fetch(`/bible/bibles/${file}`);
                const data = await res.json();
                if (data && Object.keys(data).length > 0) {
                    bibles[lang] = data;
                    console.log('Bible loaded for', lang);
                    return data;
                }
            } catch(e) {
                console.error('Bible load failed for', lang, e);
            }
            return null;
        }

        // ===== VERSE MODAL =====
        document.getElementById('btn-verse').onclick = async () => {
            const bible = await loadBible(currentLang);
            if (!bible) {
                alert(STR.bible_not_available);
                return;
            }

            const modal = document.getElementById('verse-modal');
            const bookSel = document.getElementById('v-book');

            bookSel.innerHTML = '<option value="">' + STR.select + '...</option>';
            Object.keys(bible).forEach(book => {
                const opt = document.createElement('option');
                opt.value = book;
                opt.textContent = book;
                bookSel.appendChild(opt);
            });

            document.getElementById('v-chapter').innerHTML = '';
            document.getElementById('v-from').innerHTML = '';
            document.getElementById('v-to').innerHTML = '';
            document.getElementById('v-preview').innerHTML = '';

            modal.classList.add('show');
        };

        // Book change
        document.getElementById('v-book').onchange = async function() {
            const bible = bibles[currentLang];
            const book = this.value;
            const chapterSel = document.getElementById('v-chapter');

            chapterSel.innerHTML = '<option value="">' + STR.select + '...</option>';
            document.getElementById('v-from').innerHTML = '';
            document.getElementById('v-to').innerHTML = '';
            document.getElementById('v-preview').innerHTML = '';

            if (book && bible[book]) {
                Object.keys(bible[book]).forEach(ch => {
                    const opt = document.createElement('option');
                    opt.value = ch;
                    opt.textContent = ch;
                    chapterSel.appendChild(opt);
                });
            }
        };

        // Get verses with proper numbering
        function getVersesWithNumbers(bible, book, chapter) {
            if (!bible || !bible[book] || !bible[book][chapter]) return [];
            const raw = bible[book][chapter];
            const verses = [];
            let num = 1;
            for (const item of raw) {
                if (item.v) {
                    verses.push({ n: num, v: item.v });
                    num++;
                }
            }
            return verses;
        }

        // Chapter change
        document.getElementById('v-chapter').onchange = function() {
            const bible = bibles[currentLang];
            const book = document.getElementById('v-book').value;
            const chapter = this.value;
            const fromSel = document.getElementById('v-from');
            const toSel = document.getElementById('v-to');

            fromSel.innerHTML = '<option value="">' + STR.select + '...</option>';
            toSel.innerHTML = '<option value="">' + STR.select + '...</option>';
            document.getElementById('v-preview').innerHTML = '';

            if (book && chapter) {
                const verses = getVersesWithNumbers(bible, book, chapter);
                verses.forEach(v => {
                    const opt1 = document.createElement('option');
                    opt1.value = v.n;
                    opt1.textContent = v.n;
                    fromSel.appendChild(opt1);

                    const opt2 = document.createElement('option');
                    opt2.value = v.n;
                    opt2.textContent = v.n;
                    toSel.appendChild(opt2);
                });
            }
        };

        // Preview
        function updateVersePreview() {
            const bible = bibles[currentLang];
            const book = document.getElementById('v-book').value;
            const chapter = document.getElementById('v-chapter').value;
            const from = parseInt(document.getElementById('v-from').value);
            const to = parseInt(document.getElementById('v-to').value) || from;
            const preview = document.getElementById('v-preview');

            if (!book || !chapter || !from) {
                preview.innerHTML = '';
                return;
            }

            const verses = getVersesWithNumbers(bible, book, chapter);
            let html = `<strong>${book} ${chapter}:${from}${to > from ? '-' + to : ''}</strong><br><br>`;
            verses.forEach(v => {
                if (v.n >= from && v.n <= to) {
                    html += `<sup>${v.n}</sup> ${v.v} `;
                }
            });
            preview.innerHTML = html;
        }

        document.getElementById('v-from').onchange = updateVersePreview;
        document.getElementById('v-to').onchange = updateVersePreview;

        // Insert verse - using vref/vtxt classes to match welcome.css
        document.getElementById('btn-insert').onclick = () => {
            const bible = bibles[currentLang];
            const book = document.getElementById('v-book').value;
            const chapter = document.getElementById('v-chapter').value;
            const from = parseInt(document.getElementById('v-from').value);
            const to = parseInt(document.getElementById('v-to').value) || from;

            if (!book || !chapter || !from) {
                alert(STR.select_all_fields);
                return;
            }

            const verses = getVersesWithNumbers(bible, book, chapter);
            let html = `<p class="vref">${book} ${chapter}:${from}${to > from ? '-' + to : ''}</p><p class="vtxt">`;
            verses.forEach(v => {
                if (v.n >= from && v.n <= to) {
                    html += `<sup>${v.n}</sup> ${v.v} `;
                }
            });
            html += '</p>';

            execCmd('insertHTML', html);
            document.getElementById('verse-modal').classList.remove('show');
            showStatus('saved', STR.verse_inserted);
            hasChanges = true;
            updateTabIndicators();
        };

        // Modal close
        document.querySelectorAll('.modal-close').forEach(btn => {
            btn.onclick = () => btn.closest('.modal').classList.remove('show');
        });

        document.querySelectorAll('.modal').forEach(modal => {
            modal.onclick = e => {
                if (e.target === modal) modal.classList.remove('show');
            };
        });

        // ===== IMPROVE WITH AI =====
        document.getElementById('btn-improve').onclick = async () => {
            const editor = getCurrentEditor();
            const content = editor.innerHTML;
            if (!editorHasContent(currentLang)) {
                showStatus('error', STR.editor_empty);
                return;
            }

            const btn = document.getElementById('btn-improve');
            btn.disabled = true;
            btn.textContent = STR.improving;
            showStatus('saving', STR.improving);

            try {
                const fd = new URLSearchParams();
                fd.append('content', content);
                fd.append('lang', currentLang);

                const res = await fetch('/admin/api/ai/improve.php', { method: 'POST', body: fd });
                const data = await res.json();

                if (data.success) {
                    editor.innerHTML = data.improved;
                    showStatus('saved', STR.improved);
                    hasChanges = true;
                    updateTabIndicators();
                    updateToolbarState();
                } else {
                    throw new Error(data.error || STR.save_failed);
                }
            } catch(e) {
                showStatus('error', e.message);
            } finally {
                btn.disabled = false;
                btn.textContent = STR.improve;
            }
        };

        // ===== TRANSLATE ALL - with full-screen overlay =====
        // Translates ONE language at a time to avoid API timeout,
        // then saves everything automatically.
        document.getElementById('btn-translate-all').onclick = async () => {
            // Get source content from CURRENT TAB (not detection - more reliable)
            const sourceEditor = getCurrentEditor();
            const content = sourceEditor.innerHTML;
            if (!editorHasContent(currentLang)) {
                showStatus('error', STR.editor_empty);
                return;
            }

            // The other tabs get overwritten - make sure the elder means it
            if (!confirm(STR.translate_confirm)) return;

            // Use current tab as source language (user is on this tab, so content is in this language)
            const sourceLang = currentLang;
            console.log('Source language (current tab):', sourceLang);

            // Show overlay
            const overlay = document.getElementById('translate-overlay');
            const overlayText = overlay.querySelector('.translate-text');
            const overlayTextDefault = overlayText.textContent;
            overlay.classList.add('show');

            // Reset badges
            document.querySelectorAll('.translate-progress .lang-badge').forEach(b => {
                b.classList.remove('active', 'done');
            });

            // Mark source as done (no translation needed)
            const sourceBadge = document.querySelector(`.lang-badge[data-lang="${sourceLang}"]`);
            if (sourceBadge) sourceBadge.classList.add('done');

            // Get target languages (exclude source)
            const targetLangs = CONFIG.supportedLangs.filter(l => l !== sourceLang);
            console.log('Target languages:', targetLangs);
            let successCount = 0;
            let errorLang = null;

            try {
                // Translate ONE language at a time (sequential)
                for (const targetLang of targetLangs) {
                    // Mark this language as active
                    const badge = document.querySelector(`.lang-badge[data-lang="${targetLang}"]`);
                    if (badge) {
                        badge.classList.remove('done');
                        badge.classList.add('active');
                    }

                    console.log('Translating', sourceLang, '->', targetLang);

                    const fd = new URLSearchParams();
                    fd.append('content', content);
                    fd.append('source_lang', sourceLang);
                    fd.append('target_lang', targetLang);

                    const res = await fetch('/admin/api/ai/translate_all.php', { method: 'POST', body: fd });

                    // Check if response is OK
                    if (!res.ok) {
                        const text = await res.text();
                        console.error('Translation API Error for ' + targetLang + ':', res.status, text);
                        errorLang = targetLang;
                        try {
                            const errData = JSON.parse(text);
                            throw new Error(errData.error + (errData.detail ? ': ' + errData.detail : ''));
                        } catch(parseErr) {
                            if (parseErr.message.includes('error')) throw parseErr;
                            throw new Error('Server error ' + res.status + ': ' + text.substring(0, 200));
                        }
                    }

                    const data = await res.json();

                    if (data.success) {
                        // Update this language's editor
                        if (editors[targetLang]) {
                            editors[targetLang].innerHTML = data.translation;
                        }
                        // Mark as done
                        if (badge) {
                            badge.classList.remove('active');
                            badge.classList.add('done');
                        }
                        successCount++;
                        console.log('Translation complete for:', targetLang);
                    } else {
                        console.error('Translation failed for ' + targetLang + ':', data);
                        errorLang = targetLang;
                        throw new Error(data.error + (data.detail ? ': ' + data.detail : ''));
                    }
                }

                hasChanges = true;
                updateTabIndicators();

                // Auto-save everything while the overlay is still up
                overlayText.textContent = STR.saving;
                const saved = await saveAll();
                if (saved) {
                    showStatus('saved', STR.translations_done_saved);
                }

            } catch(e) {
                console.error('Translation error:', e);
                showStatus('error', (errorLang ? errorLang.toUpperCase() + ': ' : '') + e.message);
                if (successCount > 0) {
                    alert(STR.translation_stopped + ' ' + (errorLang || '?').toUpperCase() + ': ' + e.message + '\n\n' + successCount + ' ' + STR.languages_completed);
                } else {
                    alert(e.message);
                }
            } finally {
                overlayText.textContent = overlayTextDefault;
                overlay.classList.remove('show');
            }
        };

        // ===== SAVE ALL =====
        // Shared by the save button, Ctrl+S and the translate flow.
        // Returns true on success.
        async function saveAll() {
            if (isSaving) return false;
            isSaving = true;

            const saveBtn = document.getElementById('btn-save');
            saveBtn.disabled = true;
            showStatus('saving', STR.saving);

            try {
                const fd = new URLSearchParams();
                CONFIG.supportedLangs.forEach(code => {
                    if (editors[code]) {
                        stripThemeInkColors(editors[code]);
                        fd.append('content_' + code, editors[code].innerHTML);
                    }
                });

                const res = await fetch('/admin/api/elders/save_teaching.php', { method: 'POST', body: fd });
                const data = await res.json();

                if (data.success) {
                    showStatus('saved', STR.saved);
                    hasChanges = false;
                    return true;
                }
                throw new Error(data.error || STR.save_failed);
            } catch(e) {
                showStatus('error', e.message);
                return false;
            } finally {
                isSaving = false;
                saveBtn.disabled = false;
            }
        }

        document.getElementById('btn-save').onclick = () => saveAll();

        // Ctrl+S / Cmd+S saves
        document.addEventListener('keydown', e => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
                e.preventDefault();
                saveAll();
            }
        });

        // ===== STATUS =====
        function showStatus(type, msg) {
            const badge = document.getElementById('status');
            const icon = document.getElementById('status-icon');
            const text = document.getElementById('status-text');

            badge.className = 'status ' + type;
            icon.innerHTML = type === 'saving' ? '&#9203;'
                : type === 'saved' ? '&#9989;'
                : type === 'unsaved' ? '&#9998;'
                : '&#10060;';
            text.textContent = msg;
        }

        // ===== PREVENT LOSS =====
        window.onbeforeunload = e => {
            if (hasChanges) {
                e.preventDefault();
                e.returnValue = '';
            }
        };

        // ===== INIT =====
        Object.values(editors).forEach(ed => { if (ed) normalizeEditor(ed); });
        loadBible('af');
        updateTabIndicators();
        updateToolbarState();

    })();
    </script>

    <?php require_once __DIR__ . '/../header_footer/footer.php'; ?>
</body>
</html>
