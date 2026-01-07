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
$activeLang = in_array($lang, SUPPORTED_LANGS, true) ? $lang : 'af';

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

// Backwards compatibility: check for legacy teaching_content.html for English
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

// Translation helper using central 5-language system
function t(string $key): string {
    global $lang;
    return __t($key, $lang);
}
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
        :root {
            --bg: #0a0a0a;
            --surface: #1a1a1a;
            --surface-light: #2a2a2a;
            --primary: #f3c3b1;
            --primary-dark: #d4939e;
            --text: #f5d5c8;
            --text-dim: #c0c0c0;
            --border: rgba(192, 192, 192, 0.2);
            --success: #4caf50;
            --error: #f44336;
            --warning: #ff9800;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, var(--bg) 0%, #0f0f0f 100%);
            color: var(--text);
            min-height: 100vh;
            padding-top: 80px;
        }

        .container {
            max-width: 1400px;
            margin: 20px auto 40px;
            padding: 0 20px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 25px 30px;
            background: linear-gradient(135deg, var(--surface), var(--surface-light));
            border-radius: 16px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.8);
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
            background: var(--surface);
            border: 1px solid var(--primary);
            border-radius: 20px;
            font-size: 0.85rem;
        }

        .lang-switch {
            display: flex;
            gap: 0;
            background: var(--surface);
            border-radius: 8px;
            padding: 4px;
            border: 1px solid var(--border);
        }

        .lang-btn {
            padding: 10px 20px;
            border: none;
            background: transparent;
            color: var(--text-dim);
            font-weight: 500;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.3s;
        }

        .lang-btn.active {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary));
            color: white;
        }

        .toolbar {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 20px;
            background: var(--surface);
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
            border: 1px solid var(--border);
            flex-wrap: wrap;
        }

        .tool-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tool-group label {
            font-size: 0.85rem;
            color: var(--text-dim);
            font-weight: 500;
        }

        .tool-group select {
            padding: 6px 12px;
            background: #0a0a0a;
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--text);
            font-size: 0.9rem;
            cursor: pointer;
            min-width: 120px;
        }

        .tool-group input[type="color"] {
            width: 50px;
            height: 36px;
            padding: 4px;
            background: #0a0a0a;
            border: 1px solid var(--border);
            border-radius: 6px;
            cursor: pointer;
        }

        .tool-btn {
            padding: 8px 14px;
            background: var(--surface-light);
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--text);
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .tool-btn:hover {
            border-color: var(--primary);
            background: var(--surface);
        }

        .tool-btn.active {
            border-color: var(--primary);
            background: var(--primary);
            color: white;
        }

        .sep {
            width: 1px;
            height: 30px;
            background: var(--border);
        }

        .btn-bible {
            background: linear-gradient(135deg, #2196f3, #1976d2);
            color: white;
            border: none;
        }

        .btn-ai {
            background: linear-gradient(135deg, #9c27b0, #7b1fa2);
            color: white;
            border: none;
            position: relative;
            min-width: 100px;
        }

        .btn-ai.loading {
            color: transparent;
            pointer-events: none;
        }

        .btn-ai.loading::after {
            content: '⏳';
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            color: white;
            animation: pulse 1s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .editor-wrap {
            background: black;
            border: 2px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.8);
            overflow: hidden;
        }

        .editor {
            min-height: 600px;
            padding: 40px;
            font-family: Georgia, serif;
            font-size: 16px;
            line-height: 1.8;
            color: #333;
            background: black;
            outline: none;
        }

        .editor:focus {
            outline: none;
        }

        .editor h1 { 
            font-size: 2.5em; 
            color: #f3c3b1; 
            margin: 1em 0 0.5em; 
            font-family: 'Parisienne', cursive;
        }
        
        .editor h2 { 
            font-size: 2em; 
            color: #d4939e; 
            margin: 1em 0 0.5em; 
        }
        
        .editor h3 { 
            font-size: 1.5em; 
            color: #b76e79; 
            margin: 0.8em 0 0.4em; 
        }
        
        .editor p { 
            margin: 1em 0; 
        }
        
        .editor .vref { 
            color: #f3c3b1; 
            font-weight: bold; 
            font-size: 1.1em; 
            margin: 1.5em 0 0.5em; 
            display: block; 
        }
        
        .editor .vtxt { 
            font-style: italic; 
            color: #555; 
            line-height: 1.9; 
        }
        
        .editor .vtxt sup { 
            color: #f3c3b1; 
            font-weight: bold; 
        }

        .actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 30px;
            background: var(--surface);
            border-radius: 12px;
            margin-top: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
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

        .status.saving { border-color: var(--warning); }
        .status.saved { border-color: var(--success); }
        .status.error { border-color: var(--error); }

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
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.8);
        }

        .btn-secondary {
            background: var(--surface-light);
            color: var(--text);
            border: 1px solid var(--border);
        }

        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.95);
            backdrop-filter: blur(8px);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal.show { display: flex; }

        .modal-content {
            background: linear-gradient(135deg, var(--surface), var(--surface-light));
            border: 2px solid var(--primary);
            border-radius: 16px;
            max-width: 700px;
            width: 100%;
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.8);
            animation: slide 0.3s ease-out;
        }

        @keyframes slide {
            from { opacity: 0; transform: translateY(-40px); }
            to { opacity: 1; transform: translateY(0); }
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
            line-height: 1;
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
            color: var(--text);
        }

        .form-control {
            padding: 10px 14px;
            background: #0a0a0a;
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            font-size: 0.95rem;
        }

        .verse-preview {
            padding: 20px;
            background: #0a0a0a;
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

        @media (max-width: 768px) {
            .header { flex-direction: column; gap: 20px; }
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
                    📍 <?= htmlspecialchars($cityName) ?>, <?= htmlspecialchars($provinceName) ?>
                </span>
            </div>
            <div class="lang-switch">
                <?php foreach (SUPPORTED_LANGS as $code): ?>
                <button class="lang-btn <?= $activeLang === $code ? 'active' : '' ?>" data-lang="<?= $code ?>"><?= htmlspecialchars(LANG_NAMES[$code]) ?></button>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="toolbar">
            <div class="tool-group">
                <label><?= t('font') ?></label>
                <select id="font-family">
                    <option value="Georgia, serif">Georgia</option>
                    <option value="'Parisienne', cursive">Parisienne</option>
                    <option value="'Dancing Script', cursive">Dancing Script</option>
                    <option value="'Great Vibes', cursive">Great Vibes</option>
                    <option value="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif">System UI</option>
                </select>
            </div>

            <div class="tool-group">
                <label><?= t('size') ?></label>
                <select id="font-size">
                    <option value="12px">12</option>
                    <option value="14px">14</option>
                    <option value="16px" selected>16</option>
                    <option value="18px">18</option>
                    <option value="20px">20</option>
                    <option value="24px">24</option>
                    <option value="32px">32</option>
                </select>
            </div>
            
            <div class="sep"></div>
            
            <div class="tool-group">
                <button class="tool-btn" id="btn-bold"><b>B</b></button>
                <button class="tool-btn" id="btn-italic"><i>I</i></button>
                <button class="tool-btn" id="btn-underline"><u>U</u></button>
            </div>
            
            <div class="sep"></div>
            
            <div class="tool-group">
                <button class="tool-btn" id="btn-h1">H1</button>
                <button class="tool-btn" id="btn-h2">H2</button>
                <button class="tool-btn" id="btn-h3">H3</button>
                <button class="tool-btn" id="btn-p">P</button>
            </div>
            
            <div class="sep"></div>
            
            <div class="tool-group">
                <label><?= t('color') ?></label>
                <input type="color" id="text-color" value="#333333">
            </div>

            <div class="sep"></div>

            <button class="tool-btn btn-bible" id="btn-verse">
                📖 <?= t('add_verse') ?>
            </button>
            <button class="tool-btn btn-ai" id="btn-translate">
                🌐 <?= t('translate') ?>
            </button>
            <button class="tool-btn btn-ai" id="btn-improve">
                ✨ <?= t('improve') ?>
            </button>
        </div>
        
        <div class="editor-wrap">
            <?php foreach (SUPPORTED_LANGS as $code): ?>
            <div class="editor" id="editor-<?= $code ?>" contenteditable="true" <?= $activeLang !== $code ? 'style="display:none"' : '' ?>><?= $contents[$code] ?></div>
            <?php endforeach; ?>
        </div>
        
        <div class="actions">
            <span class="status" id="status">
                <span id="status-icon">💾</span>
                <span id="status-text"><?= t('ready_to_save') ?></span>
            </span>
            <button class="btn btn-primary" id="btn-save">
                💾 <?= t('save_all') ?>
            </button>
        </div>
    </div>
    
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
        const CONFIG = {
            townId: <?= $townId ?? 'null' ?>,
            lang: '<?= $activeLang ?>',
            supportedLangs: <?= json_encode(SUPPORTED_LANGS) ?>,
            bibleFiles: <?= json_encode(BIBLE_FILES) ?>,
            t: {
                af: {
                    saving: 'Besig om te stoor...',
                    saved: 'Gestoor!',
                    error: 'Fout',
                    improving: 'Besig om te verbeter...',
                    improved: 'Verbeter!',
                    translating: 'Besig om te vertaal...',
                    translated: 'Vertaal!',
                    selectVerse: 'Kies alle velde',
                    verseInserted: 'Vers ingevoeg!',
                    noBible: 'Bybel nie beskikbaar vir hierdie taal nie',
                    selectTargetLang: 'Kies eers \'n teikentaal'
                },
                en: {
                    saving: 'Saving...',
                    saved: 'Saved!',
                    error: 'Error',
                    improving: 'Improving...',
                    improved: 'Improved!',
                    translating: 'Translating...',
                    translated: 'Translated!',
                    selectVerse: 'Select all fields',
                    verseInserted: 'Verse inserted!',
                    noBible: 'Bible not available for this language',
                    selectTargetLang: 'Select a target language first'
                },
                zu: {
                    saving: 'Ukulondoloza...',
                    saved: 'Kulondoloziwe!',
                    error: 'Iphutha',
                    improving: 'Ukuthuthukisa...',
                    improved: 'Kuthuthukisiwe!',
                    translating: 'Ukuhumusha...',
                    translated: 'Kuhunyushiwe!',
                    selectVerse: 'Khetha zonke izindawo',
                    verseInserted: 'Ivesi lifakiwe!',
                    noBible: 'IBhayibheli alitholakali ngalolu limi',
                    selectTargetLang: 'Khetha ulimi oluqondwe kuqala'
                },
                xh: {
                    saving: 'Ukugcina...',
                    saved: 'Kugciniwe!',
                    error: 'Impazamo',
                    improving: 'Ukuphucula...',
                    improved: 'Kuphuculiwe!',
                    translating: 'Ukuguqulela...',
                    translated: 'Kuguqulelwe!',
                    selectVerse: 'Khetha zonke iindawo',
                    verseInserted: 'Ivesi lifakiwe!',
                    noBible: 'IBhayibhile ayifumaneki ngolu lwimi',
                    selectTargetLang: 'Khetha ulwimi ekujoliswe kulo kuqala'
                },
                pt: {
                    saving: 'Salvando...',
                    saved: 'Salvo!',
                    error: 'Erro',
                    improving: 'Melhorando...',
                    improved: 'Melhorado!',
                    translating: 'Traduzindo...',
                    translated: 'Traduzido!',
                    selectVerse: 'Selecione todos os campos',
                    verseInserted: 'Versículo inserido!',
                    noBible: 'Bíblia não disponível para este idioma',
                    selectTargetLang: 'Selecione um idioma de destino primeiro'
                }
            }
        };
        
        (function() {
            const T = k => CONFIG.t[CONFIG.lang]?.[k] || CONFIG.t['en']?.[k] || k;

            // Get all editors
            const editors = {};
            CONFIG.supportedLangs.forEach(code => {
                editors[code] = document.getElementById('editor-' + code);
            });

            let currentEditor = editors[CONFIG.lang];
            let bible = null;
            let hasChanges = false;

            console.log('✅ Custom editor loaded for 5 languages');

            // Track changes on all editors
            Object.values(editors).forEach(ed => {
                if (ed) ed.addEventListener('input', () => hasChanges = true);
            });

            // Lang switch
            document.querySelectorAll('.lang-btn').forEach(btn => {
                btn.onclick = () => {
                    CONFIG.lang = btn.dataset.lang;
                    document.querySelectorAll('.lang-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');

                    // Hide all editors, show selected one
                    CONFIG.supportedLangs.forEach(code => {
                        if (editors[code]) {
                            editors[code].style.display = (code === CONFIG.lang) ? 'block' : 'none';
                        }
                    });

                    currentEditor = editors[CONFIG.lang];
                    loadBible();
                };
            });
            
            // Format commands
            function execCmd(cmd, val = null) {
                document.execCommand(cmd, false, val);
                currentEditor.focus();
            }
            
            document.getElementById('btn-bold').onclick = () => execCmd('bold');
            document.getElementById('btn-italic').onclick = () => execCmd('italic');
            document.getElementById('btn-underline').onclick = () => execCmd('underline');
            document.getElementById('btn-h1').onclick = () => execCmd('formatBlock', 'h1');
            document.getElementById('btn-h2').onclick = () => execCmd('formatBlock', 'h2');
            document.getElementById('btn-h3').onclick = () => execCmd('formatBlock', 'h3');
            document.getElementById('btn-p').onclick = () => execCmd('formatBlock', 'p');
            
            document.getElementById('font-family').onchange = function() {
                execCmd('fontName', this.value);
            };
            
            document.getElementById('font-size').onchange = function() {
                execCmd('fontSize', '7');
                const sel = window.getSelection();
                if (sel.rangeCount) {
                    const range = sel.getRangeAt(0);
                    const span = document.createElement('span');
                    span.style.fontSize = this.value;
                    range.surroundContents(span);
                }
            };
            
            document.getElementById('text-color').onchange = function() {
                execCmd('foreColor', this.value);
            };
            
            // Load Bible for current language
            async function loadBible() {
                const file = CONFIG.bibleFiles[CONFIG.lang] || CONFIG.bibleFiles['en'];
                try {
                    const res = await fetch(`/bible/bibles/${file}`);
                    bible = await res.json();
                    if (Object.keys(bible).length === 0) {
                        bible = null;
                        console.log('⚠️ Bible is empty for', CONFIG.lang);
                    } else {
                        console.log('✅ Bible loaded for', CONFIG.lang);
                    }
                } catch(e) {
                    bible = null;
                    console.error('❌ Bible load failed:', e);
                }
            }
            
            // Verse modal
            document.getElementById('btn-verse').onclick = () => {
                if (!bible) { alert(T('noBible')); return; }
                const modal = document.getElementById('verse-modal');
                const bookSel = document.getElementById('v-book');
                bookSel.innerHTML = '<option value="">Kies...</option>';
                Object.keys(bible).forEach(book => {
                    const opt = document.createElement('option');
                    opt.value = book;
                    opt.textContent = book;
                    bookSel.appendChild(opt);
                });
                modal.classList.add('show');
            };
            
            document.getElementById('v-book').onchange = function() {
                const book = this.value;
                const chapterSel = document.getElementById('v-chapter');
                chapterSel.innerHTML = '<option value="">Kies...</option>';
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
            
            // Helper to get verses with numbers (filter out headers with 'h')
            function getVersesWithNumbers(book, chapter) {
                if (!bible || !bible[book] || !bible[book][chapter]) return [];
                const raw = bible[book][chapter];
                const verses = [];
                let verseNum = 1;
                for (const item of raw) {
                    if (item.v) {
                        verses.push({ n: verseNum, v: item.v });
                        verseNum++;
                    }
                }
                return verses;
            }

            document.getElementById('v-chapter').onchange = function() {
                const book = document.getElementById('v-book').value;
                const chapter = this.value;
                const fromSel = document.getElementById('v-from');
                const toSel = document.getElementById('v-to');
                fromSel.innerHTML = '<option value="">Kies...</option>';
                toSel.innerHTML = '<option value="">Kies...</option>';

                if (book && chapter) {
                    const verses = getVersesWithNumbers(book, chapter);
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
            
            function updatePreview() {
                const book = document.getElementById('v-book').value;
                const chapter = document.getElementById('v-chapter').value;
                const from = parseInt(document.getElementById('v-from').value);
                const to = parseInt(document.getElementById('v-to').value) || from;
                const preview = document.getElementById('v-preview');

                if (!book || !chapter || !from) {
                    preview.innerHTML = '';
                    return;
                }

                const verses = getVersesWithNumbers(book, chapter);
                let html = `<strong>${book} ${chapter}:${from}${to > from ? '-' + to : ''}</strong><br><br>`;
                verses.forEach(v => {
                    if (v.n >= from && v.n <= to) {
                        html += `<sup>${v.n}</sup> ${v.v} `;
                    }
                });
                preview.innerHTML = html;
            }
            
            document.getElementById('v-from').onchange = updatePreview;
            document.getElementById('v-to').onchange = updatePreview;
            
            document.getElementById('btn-insert').onclick = () => {
                const book = document.getElementById('v-book').value;
                const chapter = document.getElementById('v-chapter').value;
                const from = parseInt(document.getElementById('v-from').value);
                const to = parseInt(document.getElementById('v-to').value) || from;

                if (!book || !chapter || !from) {
                    alert(T('selectVerse'));
                    return;
                }

                const verses = getVersesWithNumbers(book, chapter);
                let html = `<p class="vref">${book} ${chapter}:${from}${to > from ? '-' + to : ''}</p><p class="vtxt">`;
                verses.forEach(v => {
                    if (v.n >= from && v.n <= to) {
                        html += `<sup>${v.n}</sup> ${v.v} `;
                    }
                });
                html += '</p>';

                execCmd('insertHTML', html);
                document.getElementById('verse-modal').classList.remove('show');
                showStatus('saved', T('verseInserted'));
            };
            
            // Translate to another language
            document.getElementById('btn-translate').onclick = async () => {
                const content = currentEditor.innerHTML;
                if (!content.trim()) return;

                // Only translate between AF and EN for now
                const fromLang = CONFIG.lang;
                const toLang = (fromLang === 'af') ? 'en' : 'af';
                const targetEditor = editors[toLang];

                if (!targetEditor) {
                    alert(T('selectTargetLang'));
                    return;
                }

                const btn = document.getElementById('btn-translate');
                btn.classList.add('loading');

                try {
                    const fd = new URLSearchParams();
                    fd.append('content', content);
                    fd.append('from_lang', fromLang);

                    const res = await fetch('/admin/api/ai/translate.php', { method: 'POST', body: fd });
                    const data = await res.json();

                    if (data.success) {
                        targetEditor.innerHTML = data.translated;
                        showStatus('saved', T('translated'));
                        hasChanges = true;
                    } else {
                        throw new Error(data.error);
                    }
                } catch(e) {
                    showStatus('error', e.message);
                } finally {
                    btn.classList.remove('loading');
                }
            };

            // Improve with AI
            document.getElementById('btn-improve').onclick = async () => {
                const content = currentEditor.innerHTML;
                if (!content.trim()) return;

                const btn = document.getElementById('btn-improve');
                btn.classList.add('loading');

                try {
                    const fd = new URLSearchParams();
                    fd.append('content', content);
                    fd.append('lang', CONFIG.lang);

                    const res = await fetch('/admin/api/ai/improve.php', { method: 'POST', body: fd });
                    const data = await res.json();

                    if (data.success) {
                        currentEditor.innerHTML = data.improved;
                        showStatus('saved', T('improved'));
                        hasChanges = true;
                    } else {
                        throw new Error(data.error);
                    }
                } catch(e) {
                    showStatus('error', e.message);
                } finally {
                    btn.classList.remove('loading');
                }
            };
            
            // Save all languages
            document.getElementById('btn-save').onclick = async () => {
                const sourceContent = currentEditor.innerHTML;
                if (!sourceContent.trim()) return;

                showStatus('saving', T('saving'));

                try {
                    // Save all language contents
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
                        showStatus('saved', T('saved'));
                        hasChanges = false;
                    } else {
                        throw new Error(data.error);
                    }
                } catch(e) {
                    showStatus('error', e.message);
                }
            };
            
            function showStatus(type, msg) {
                const badge = document.getElementById('status');
                const icon = document.getElementById('status-icon');
                const text = document.getElementById('status-text');
                
                badge.className = 'status ' + type;
                icon.textContent = type === 'saving' ? '⏳' : type === 'saved' ? '✅' : '❌';
                text.textContent = msg;
            }
            
            // Modal close
            document.querySelectorAll('.modal-close').forEach(btn => {
                btn.onclick = () => btn.closest('.modal').classList.remove('show');
            });
            
            document.querySelectorAll('.modal').forEach(modal => {
                modal.onclick = e => {
                    if (e.target === modal) modal.classList.remove('show');
                };
            });
            
            // Prevent loss
            window.onbeforeunload = e => {
                if (hasChanges) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            };
            
            // Initialize
            loadBible();
            console.log('✅ All initialized');
        })();
    </script>
    
    <?php require_once __DIR__ . '/../header_footer/footer.php'; ?>
</body>
</html>