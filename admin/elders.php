<?php
declare(strict_types=1);

require_once __DIR__ . '/../security/auth_gate.php';
require_once __DIR__ . '/../includes/languages.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$lang = $_SESSION['language'] ?? 'af';

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

function slug($s) {
    return preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($s)));
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

// File paths for all 5 languages
$contentFiles = [
    'af' => $baseDir . '/lering_content.html',
    'en' => $baseDir . '/teaching_content.en.html',
    'zu' => $baseDir . '/teaching_content.zu.html',
    'xh' => $baseDir . '/teaching_content.xh.html',
    'pt' => $baseDir . '/teaching_content.pt.html'
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
    'pt' => '<p>Comece a digitar aqui...</p>'
];

foreach (SUPPORTED_LANGS as $code) {
    $file = $contentFiles[$code];
    $contents[$code] = file_exists($file) ? file_get_contents($file) : $defaultContent[$code];
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
            padding: 6px 12px;
            background: var(--surface-light);
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--text);
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
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

        /* Editor heading styles - use site fonts */
        .editor h1 {
            font-family: 'Parisienne', cursive;
            font-size: 2.5rem;
            color: var(--primary);
            margin: 1em 0 0.5em;
            font-weight: normal;
        }
        .editor h2 {
            font-family: 'Parisienne', cursive;
            font-size: 2rem;
            color: var(--primary);
            margin: 1em 0 0.5em;
            font-weight: normal;
        }
        .editor h3 {
            font-family: 'Parisienne', cursive;
            font-size: 1.5rem;
            color: var(--primary-dark);
            margin: 0.8em 0 0.4em;
            font-weight: normal;
        }
        .editor p {
            margin: 1em 0;
        }

        /* Bible verse classes - matching welcome.css */
        .editor .vref {
            font-family: 'Parisienne', cursive;
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--primary);
            margin: 1.5em 0 0.5em;
            display: block;
        }
        .editor .vtxt {
            font-style: italic;
            color: var(--text-dim);
            line-height: 1.9;
            padding-left: 16px;
            border-left: 2px solid var(--primary-dark);
            margin: 0 0 1em;
        }
        .editor .vtxt sup {
            font-size: 0.7em;
            font-weight: 600;
            color: var(--primary);
            margin-right: 2px;
            font-style: normal;
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

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .header { flex-direction: column; gap: 20px; }
            .toolbar { padding: 10px; }
            .sep { display: none; }
            .form-grid { grid-template-columns: 1fr; }
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

        <!-- TOOLBAR ROW 1: Headings & Basic Formatting -->
        <div class="toolbar">
            <div class="tool-group">
                <button class="tool-btn btn-h1" id="btn-h1" title="Heading 1">H1</button>
                <button class="tool-btn btn-h2" id="btn-h2" title="Heading 2">H2</button>
                <button class="tool-btn btn-h3" id="btn-h3" title="Heading 3">H3</button>
                <button class="tool-btn" id="btn-p" title="Paragraph">P</button>
            </div>

            <div class="sep"></div>

            <div class="tool-group">
                <button class="tool-btn" id="btn-bold" title="Bold"><b>B</b></button>
                <button class="tool-btn" id="btn-italic" title="Italic"><i>I</i></button>
                <button class="tool-btn" id="btn-underline" title="Underline"><u>U</u></button>
            </div>

            <div class="sep"></div>

            <div class="tool-group">
                <label>Size</label>
                <select id="font-size">
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
                <label>Line</label>
                <select id="line-spacing">
                    <option value="1">1.0</option>
                    <option value="1.5" selected>1.5</option>
                    <option value="1.8">1.8</option>
                    <option value="2">2.0</option>
                    <option value="2.5">2.5</option>
                </select>
            </div>

            <div class="sep"></div>

            <div class="tool-group">
                <button class="tool-btn" id="btn-left" title="Align Left">&#8676;</button>
                <button class="tool-btn" id="btn-center" title="Center">&#8801;</button>
                <button class="tool-btn" id="btn-right" title="Align Right">&#8677;</button>
            </div>

            <div class="sep"></div>

            <div class="tool-group">
                <button class="tool-btn" id="btn-ul" title="Bullet List">&#8226; List</button>
                <button class="tool-btn" id="btn-ol" title="Numbered List">1. List</button>
            </div>
        </div>

        <!-- TOOLBAR ROW 2: Colors & Actions -->
        <div class="toolbar">
            <div class="tool-group">
                <label>Text Color</label>
                <?php foreach ($siteColors as $color): ?>
                <button class="color-swatch"
                        style="background: <?= $color['value'] ?>"
                        data-color="<?= $color['value'] ?>"
                        data-type="text"
                        title="<?= $color['name'] ?>"></button>
                <?php endforeach; ?>
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
            townId: <?= $townId ?? 'null' ?>,
            supportedLangs: <?= json_encode(SUPPORTED_LANGS) ?>,
            bibleFiles: <?= json_encode(BIBLE_FILES) ?>,
            langNames: <?= json_encode(LANG_NAMES) ?>
        };

        // State
        let currentLang = 'af';
        let editors = {};
        let bibles = {};
        let hasChanges = false;

        // Get all editors
        CONFIG.supportedLangs.forEach(code => {
            editors[code] = document.getElementById('editor-' + code);
        });

        // Current editor getter
        const getCurrentEditor = () => editors[currentLang];

        // ===== LANGUAGE DETECTION =====
        function detectLanguage(text) {
            // Common words in each language
            const markers = {
                af: ['die', 'en', 'van', 'het', 'ons', 'met', 'is', 'dat', 'nie', 'vir', 'wat', 'sal', 'kan', 'hul', 'ook'],
                en: ['the', 'and', 'of', 'to', 'is', 'in', 'that', 'for', 'with', 'was', 'are', 'have', 'this', 'will', 'but'],
                zu: ['ukuthi', 'futhi', 'yena', 'kwa', 'ngi', 'uma', 'yabo', 'bonke', 'wena', 'ukuba'],
                xh: ['ukuba', 'futhi', 'yena', 'kwa', 'ndi', 'ukuthi', 'wabo', 'bonke', 'wena', 'ukuba'],
                pt: ['que', 'de', 'para', 'com', 'uma', 'seu', 'sua', 'como', 'mais', 'quando', 'esse', 'esta']
            };

            const words = text.toLowerCase().split(/\s+/);
            const scores = {};

            CONFIG.supportedLangs.forEach(lang => {
                scores[lang] = 0;
                markers[lang].forEach(marker => {
                    words.forEach(word => {
                        if (word === marker || word.startsWith(marker)) {
                            scores[lang]++;
                        }
                    });
                });
            });

            // Find highest score
            let detected = 'af';
            let maxScore = 0;
            Object.keys(scores).forEach(lang => {
                if (scores[lang] > maxScore) {
                    maxScore = scores[lang];
                    detected = lang;
                }
            });

            return detected;
        }

        // ===== PASTE HANDLER - Preserve Word formatting =====
        Object.values(editors).forEach(ed => {
            if (!ed) return;
            ed.addEventListener('paste', function(e) {
                e.preventDefault();
                hasChanges = true;

                const html = e.clipboardData.getData('text/html');
                const text = e.clipboardData.getData('text/plain');

                if (html) {
                    const temp = document.createElement('div');
                    temp.innerHTML = html;

                    // Remove Word-specific junk
                    temp.querySelectorAll('meta, link, style, script, xml, o\\:p, w\\:sdt').forEach(el => el.remove());

                    // Process paragraphs - detect headings by style
                    temp.querySelectorAll('p, h1, h2, h3, h4').forEach(el => {
                        const style = el.getAttribute('style') || '';
                        const text = el.textContent.trim();

                        // Get font size
                        let fontSize = 11;
                        const sizeMatch = style.match(/font-size:\s*([\d.]+)\s*pt/i);
                        if (sizeMatch) fontSize = parseFloat(sizeMatch[1]);

                        // Detect if centered
                        const isCentered = style.includes('center') || el.style.textAlign === 'center';

                        // Check if short (likely heading)
                        const isShort = text.length < 120;

                        // Determine heading level
                        if ((fontSize >= 16 && isShort) || el.tagName.match(/^H[123]$/)) {
                            const level = fontSize >= 18 || el.tagName === 'H1' ? 'h1' :
                                         fontSize >= 14 || el.tagName === 'H2' ? 'h2' : 'h3';
                            const heading = document.createElement(level);
                            heading.textContent = text;
                            if (el.parentNode) el.parentNode.replaceChild(heading, el);
                        } else {
                            // Body paragraph - preserve line spacing
                            const lineHeight = style.match(/line-height:\s*([\d.]+)/i);
                            if (lineHeight) {
                                el.style.lineHeight = lineHeight[1];
                            }
                        }
                    });

                    document.execCommand('insertHTML', false, temp.innerHTML);
                } else if (text) {
                    document.execCommand('insertText', false, text);
                }
            });

            // Track changes
            ed.addEventListener('input', () => hasChanges = true);
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
            };
        });

        // ===== FORMATTING COMMANDS =====
        function execCmd(cmd, val = null) {
            document.execCommand(cmd, false, val);
            getCurrentEditor().focus();
        }

        // Headings - apply site fonts automatically
        document.getElementById('btn-h1').onclick = () => {
            execCmd('formatBlock', '<h1>');
            hasChanges = true;
        };
        document.getElementById('btn-h2').onclick = () => {
            execCmd('formatBlock', '<h2>');
            hasChanges = true;
        };
        document.getElementById('btn-h3').onclick = () => {
            execCmd('formatBlock', '<h3>');
            hasChanges = true;
        };
        document.getElementById('btn-p').onclick = () => {
            execCmd('formatBlock', '<p>');
            hasChanges = true;
        };

        // Basic formatting
        document.getElementById('btn-bold').onclick = () => execCmd('bold');
        document.getElementById('btn-italic').onclick = () => execCmd('italic');
        document.getElementById('btn-underline').onclick = () => execCmd('underline');

        // Alignment
        document.getElementById('btn-left').onclick = () => execCmd('justifyLeft');
        document.getElementById('btn-center').onclick = () => execCmd('justifyCenter');
        document.getElementById('btn-right').onclick = () => execCmd('justifyRight');

        // Lists
        document.getElementById('btn-ul').onclick = () => execCmd('insertUnorderedList');
        document.getElementById('btn-ol').onclick = () => execCmd('insertOrderedList');

        // Font size
        document.getElementById('font-size').onchange = function() {
            const size = this.value;
            const sel = window.getSelection();
            if (!sel.rangeCount || sel.getRangeAt(0).collapsed) return;

            const range = sel.getRangeAt(0);
            const contents = range.extractContents();
            const span = document.createElement('span');
            span.style.fontSize = size;
            span.appendChild(contents);
            range.insertNode(span);
            getCurrentEditor().focus();
            hasChanges = true;
        };

        // Line spacing
        document.getElementById('line-spacing').onchange = function() {
            const lineHeight = this.value;
            const sel = window.getSelection();
            if (!sel.rangeCount) return;

            let node = sel.anchorNode;
            while (node && node !== getCurrentEditor()) {
                if (node.nodeType === 1 && ['P', 'DIV', 'H1', 'H2', 'H3', 'LI'].includes(node.tagName)) {
                    node.style.lineHeight = lineHeight;
                    break;
                }
                node = node.parentNode;
            }
            getCurrentEditor().focus();
            hasChanges = true;
        };

        // Color swatches
        document.querySelectorAll('.color-swatch').forEach(swatch => {
            swatch.onclick = function() {
                const color = this.dataset.color;
                execCmd('foreColor', color);
            };
        });

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
                alert('Bible not available for this language');
                return;
            }

            const modal = document.getElementById('verse-modal');
            const bookSel = document.getElementById('v-book');

            bookSel.innerHTML = '<option value="">Select...</option>';
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

            chapterSel.innerHTML = '<option value="">Select...</option>';
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

            fromSel.innerHTML = '<option value="">Select...</option>';
            toSel.innerHTML = '<option value="">Select...</option>';
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
                alert('Please select all fields');
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
            showStatus('saved', 'Verse inserted!');
            hasChanges = true;
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
            if (!content.trim()) return;

            const btn = document.getElementById('btn-improve');
            btn.disabled = true;
            btn.textContent = 'Improving...';
            showStatus('saving', 'Improving...');

            try {
                const fd = new URLSearchParams();
                fd.append('content', content);
                fd.append('lang', currentLang);

                const res = await fetch('/admin/api/ai/improve.php', { method: 'POST', body: fd });
                const data = await res.json();

                if (data.success) {
                    editor.innerHTML = data.improved;
                    showStatus('saved', 'Improved!');
                    hasChanges = true;
                } else {
                    throw new Error(data.error || 'Improvement failed');
                }
            } catch(e) {
                showStatus('error', e.message);
            } finally {
                btn.disabled = false;
                btn.textContent = 'Improve';
            }
        };

        // ===== TRANSLATE ALL - with full-screen overlay =====
        document.getElementById('btn-translate-all').onclick = async () => {
            // Get source content and detect language
            const sourceEditor = getCurrentEditor();
            const content = sourceEditor.innerHTML;
            if (!content.trim()) return;

            // Detect source language from content
            const textContent = sourceEditor.textContent;
            const detectedLang = detectLanguage(textContent);

            // Show overlay
            const overlay = document.getElementById('translate-overlay');
            overlay.classList.add('show');

            // Reset badges
            document.querySelectorAll('.translate-progress .lang-badge').forEach(b => {
                b.classList.remove('active', 'done');
            });

            // Mark source as done (no translation needed)
            const sourceBadge = document.querySelector(`.lang-badge[data-lang="${detectedLang}"]`);
            if (sourceBadge) sourceBadge.classList.add('done');

            try {
                const fd = new URLSearchParams();
                fd.append('content', content);
                fd.append('source_lang', detectedLang);

                // Mark all others as active (being translated)
                CONFIG.supportedLangs.forEach(lang => {
                    if (lang !== detectedLang) {
                        const badge = document.querySelector(`.lang-badge[data-lang="${lang}"]`);
                        if (badge) badge.classList.add('active');
                    }
                });

                const res = await fetch('/admin/api/ai/translate_all.php', { method: 'POST', body: fd });
                const data = await res.json();

                if (data.success) {
                    // Update all editors
                    Object.keys(data.translations).forEach(lang => {
                        if (editors[lang]) {
                            editors[lang].innerHTML = data.translations[lang];
                            const badge = document.querySelector(`.lang-badge[data-lang="${lang}"]`);
                            if (badge) {
                                badge.classList.remove('active');
                                badge.classList.add('done');
                            }
                        }
                    });
                    hasChanges = true;

                    // Short delay to show success
                    await new Promise(r => setTimeout(r, 1000));
                    showStatus('saved', 'All translations complete!');
                } else {
                    throw new Error(data.error || 'Translation failed');
                }
            } catch(e) {
                showStatus('error', e.message);
            } finally {
                overlay.classList.remove('show');
            }
        };

        // ===== SAVE ALL =====
        document.getElementById('btn-save').onclick = async () => {
            showStatus('saving', 'Saving...');

            try {
                const fd = new URLSearchParams();
                CONFIG.supportedLangs.forEach(code => {
                    if (editors[code]) {
                        fd.append('content_' + code, editors[code].innerHTML);
                    }
                });
                fd.append('town_id', CONFIG.townId);

                const res = await fetch('/admin/api/elders/save_teaching.php', { method: 'POST', body: fd });
                const data = await res.json();

                if (data.success) {
                    showStatus('saved', 'Saved!');
                    hasChanges = false;
                } else {
                    throw new Error(data.error || 'Save failed');
                }
            } catch(e) {
                showStatus('error', e.message);
            }
        };

        // ===== STATUS =====
        function showStatus(type, msg) {
            const badge = document.getElementById('status');
            const icon = document.getElementById('status-icon');
            const text = document.getElementById('status-text');

            badge.className = 'status ' + type;
            icon.innerHTML = type === 'saving' ? '&#9203;' : type === 'saved' ? '&#9989;' : '&#10060;';
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
        loadBible('af');
        console.log('Elder teaching editor initialized');

    })();
    </script>

    <?php require_once __DIR__ . '/../header_footer/footer.php'; ?>
</body>
</html>
