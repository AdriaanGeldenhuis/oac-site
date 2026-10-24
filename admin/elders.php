<?php
declare(strict_types=1);

require_once __DIR__ . '/../security/auth_gate.php';
require_once __DIR__ . '/../includes/languages.php';
require_once __DIR__ . '/../includes/teaching.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$lang = validate_language((string)($_SESSION['language'] ?? 'af'));

// Elder permissions + the elder's own town (same lookup as save_teaching.php)
$stmt = $pdo->prepare('
    SELECT u.amp_id, u.town_id, t.name AS town, p.name AS province
    FROM users u
    LEFT JOIN towns t ON t.id = u.town_id
    LEFT JOIN provinces p ON p.id = t.province_id
    WHERE u.id = ?
    LIMIT 1
');
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || (int)($user['amp_id'] ?? 0) < 1 || (int)($user['amp_id'] ?? 0) > 5) {
    header('Location: /admin/index.php');
    exit;
}

$cityName = (string)($user['town'] ?? '') ?: 'Unknown';
$provinceName = (string)($user['province'] ?? '') ?: 'Unknown';

// Exactly what is stored per language ('' = nothing yet)
$teachDir = teaching_dir((string)($user['province'] ?? ''), (string)($user['town'] ?? ''));
$contents = teaching_load_all($teachDir);

// Shown as a placeholder only (CSS), never stored as content
$placeholders = [
    'af' => 'Begin tik of plak hier...',
    'en' => 'Start typing or paste here...',
    'zu' => 'Qala ukuthayipha lapha...',
    'xh' => 'Qala ukuchwetheza apha...',
    'pt' => 'Comece a digitar aqui...',
    'st' => 'Qala ho thaepa mona...'
];

function t(string $key): string {
    global $lang;
    return __t($key, $lang);
}

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// Site colors for toolbar. Every one of them is remapped per theme on the
// welcome page, so the text stays readable in dark and light mode. Plain
// white / black are not here: that is the theme's own text colour, which
// the "default colour" swatch gives back.
$siteColors = [
    ['name' => 'Rose Gold', 'value' => '#e8b4a8'],
    ['name' => 'Rose Gold Dark', 'value' => '#c99485'],
    ['name' => 'Peach', 'value' => '#f5d5c8'],
    ['name' => 'Silver', 'value' => '#b8b8b8'],
    ['name' => 'Dark Gray', 'value' => '#3d3835'],
];

// Soft highlight colors that read well on both dark and light themes
$highlightColors = [
    ['name' => 'Rose', 'value' => 'rgba(232, 180, 168, 0.45)'],
    ['name' => 'Amber', 'value' => 'rgba(255, 213, 128, 0.45)'],
    ['name' => 'Green', 'value' => 'rgba(165, 214, 167, 0.45)'],
    ['name' => 'Blue', 'value' => 'rgba(144, 202, 249, 0.45)'],
];

// Everything the editor script needs
$editorData = [
    'supportedLangs' => SUPPORTED_LANGS,
    'bibleFiles'     => BIBLE_FILES,
    'langNames'      => LANG_NAMES,
    'csrf'           => csrf_token(),
    // One draft slot per user + town, so a shared device never mixes them up
    'draftKey'       => 'oac_teach_draft_' . $userId . '_' . (teaching_slug($provinceName . '_' . $cityName) ?: 'shared'),
    'str' => [
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
        'link_prompt' => t('link_prompt'),
        'paste_no_images' => t('paste_no_images'),
        'paste_options' => t('paste_options'),
        'paste_rich' => t('paste_rich'),
        'paste_plain' => t('paste_plain'),
    ],
];

$VER = (string)@filemtime(__DIR__ . '/js/elders.js') . '.' . (string)@filemtime(__DIR__ . '/css/elders.css');
?>
<!DOCTYPE html>
<html lang="<?= e($lang) ?>">
<head>
    <meta charset="UTF-8">
    <!-- resizes-content: the on-screen keyboard shrinks the page, so the
         toolbar and the paste bar stay visible above it -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, interactive-widget=resizes-content">
    <title><?= e(t('edit_teaching')) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Parisienne&family=Dancing+Script:wght@400;700&family=Great+Vibes&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="/admin/css/elders.css?v=<?= e($VER) ?>">
</head>
<body>
    <?php require_once __DIR__ . '/../header_footer/header.php'; ?>

    <div class="container">
        <div class="header">
            <div>
                <h1><?= t('monthly_teaching') ?></h1>
                <span class="location">
                    <?= e($cityName) ?>, <?= e($provinceName) ?>
                </span>
            </div>
            <!-- Language tabs for viewing different translations -->
            <div class="lang-tabs" role="tablist">
                <?php foreach (SUPPORTED_LANGS as $code): ?>
                <button type="button" class="lang-tab <?= $code === 'af' ? 'active' : '' ?>" role="tab" aria-selected="<?= $code === 'af' ? 'true' : 'false' ?>" aria-controls="editor-<?= $code ?>" data-lang="<?= $code ?>"><?= e(LANG_NAMES[$code]) ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Unsaved draft from an earlier visit (filled by JS) -->
        <div class="draft-bar" id="draft-bar" role="alert" hidden>
            <span><strong><?= e(t('draft_found')) ?></strong> <span id="draft-time"></span></span>
            <span class="draft-bar-actions">
                <button type="button" class="btn btn-primary" id="btn-draft-restore"><?= e(t('restore_draft')) ?></button>
                <button type="button" class="btn btn-secondary" id="btn-draft-discard"><?= e(t('discard_draft')) ?></button>
            </span>
        </div>

        <!-- Both toolbars stay in view while scrolling a long teaching -->
        <div class="toolbar-dock">
        <!-- TOOLBAR ROW 1: History, Paste mode, Blocks & Text Formatting -->
        <div class="toolbar" role="toolbar">
            <div class="tool-group">
                <button type="button" class="tool-btn" id="btn-undo" title="<?= e(t('undo')) ?> (Ctrl+Z)" aria-label="<?= e(t('undo')) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/></svg>
                </button>
                <button type="button" class="tool-btn" id="btn-redo" title="<?= e(t('redo')) ?> (Ctrl+Y)" aria-label="<?= e(t('redo')) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 14 5-5-5-5"/><path d="M20 9H9.5a5.5 5.5 0 0 0 0 11H13"/></svg>
                </button>
            </div>

            <div class="sep"></div>

            <!-- How a paste comes in: keep the source's look, or text only -->
            <div class="tool-group">
                <label id="paste-mode-label"><?= t('paste_label') ?></label>
                <div class="seg" role="radiogroup" aria-labelledby="paste-mode-label">
                    <button type="button" class="seg-btn" role="radio" aria-checked="true" data-paste-mode="rich" title="<?= e(t('paste_rich_hint')) ?>"><span class="seg-ico" aria-hidden="true">Aa</span><?= e(t('paste_rich')) ?></button>
                    <button type="button" class="seg-btn" role="radio" aria-checked="false" data-paste-mode="plain" title="<?= e(t('paste_plain_hint')) ?>"><span class="seg-ico" aria-hidden="true">T</span><?= e(t('paste_plain')) ?></button>
                </div>
            </div>

            <div class="sep"></div>

            <div class="tool-group">
                <button type="button" class="tool-btn btn-h1" id="btn-h1" title="<?= e(t('heading')) ?> 1">H1</button>
                <button type="button" class="tool-btn btn-h2" id="btn-h2" title="<?= e(t('heading')) ?> 2">H2</button>
                <button type="button" class="tool-btn btn-h3" id="btn-h3" title="<?= e(t('heading')) ?> 3">H3</button>
                <button type="button" class="tool-btn" id="btn-p" title="<?= e(t('paragraph')) ?>">P</button>
            </div>

            <div class="sep"></div>

            <div class="tool-group">
                <button type="button" class="tool-btn" id="btn-bold" title="<?= e(t('bold')) ?> (Ctrl+B)"><b>B</b></button>
                <button type="button" class="tool-btn" id="btn-italic" title="<?= e(t('italic')) ?> (Ctrl+I)"><i>I</i></button>
                <button type="button" class="tool-btn" id="btn-underline" title="<?= e(t('underline')) ?> (Ctrl+U)"><u>U</u></button>
                <button type="button" class="tool-btn" id="btn-strike" title="<?= e(t('strikethrough')) ?>"><s>S</s></button>
                <button type="button" class="tool-btn" id="btn-clear" title="<?= e(t('clear_formatting')) ?>"><span><s>T</s>x</span></button>
            </div>

            <div class="sep"></div>

            <div class="tool-group">
                <label for="font-size"><?= t('size') ?></label>
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
                <label for="line-spacing"><?= t('line_spacing') ?></label>
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
                <button type="button" class="tool-btn" id="btn-left" title="<?= e(t('align_left')) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="14" y2="12"/><line x1="4" y1="18" x2="18" y2="18"/></svg>
                </button>
                <button type="button" class="tool-btn" id="btn-center" title="<?= e(t('align_center')) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="4" y1="6" x2="20" y2="6"/><line x1="7" y1="12" x2="17" y2="12"/><line x1="5" y1="18" x2="19" y2="18"/></svg>
                </button>
                <button type="button" class="tool-btn" id="btn-right" title="<?= e(t('align_right')) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="4" y1="6" x2="20" y2="6"/><line x1="10" y1="12" x2="20" y2="12"/><line x1="6" y1="18" x2="20" y2="18"/></svg>
                </button>
                <button type="button" class="tool-btn" id="btn-justify" title="<?= e(t('align_justify')) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>
                </button>
            </div>

            <div class="sep"></div>

            <div class="tool-group">
                <button type="button" class="tool-btn" id="btn-ul" title="<?= e(t('bullet_list')) ?>">&#8226; <?= t('bullet_list') ?></button>
                <button type="button" class="tool-btn" id="btn-ol" title="<?= e(t('numbered_list')) ?>">1. <?= t('numbered_list') ?></button>
                <button type="button" class="tool-btn" id="btn-outdent" title="<?= e(t('outdent')) ?> (Shift+Tab)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="7 8 3 12 7 16"/><line x1="21" y1="6" x2="11" y2="6"/><line x1="21" y1="12" x2="11" y2="12"/><line x1="21" y1="18" x2="11" y2="18"/></svg>
                </button>
                <button type="button" class="tool-btn" id="btn-indent" title="<?= e(t('indent')) ?> (Tab)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 8 7 12 3 16"/><line x1="21" y1="6" x2="11" y2="6"/><line x1="21" y1="12" x2="11" y2="12"/><line x1="21" y1="18" x2="11" y2="18"/></svg>
                </button>
            </div>
        </div>

        <!-- TOOLBAR ROW 2: Colors, Inserts & Actions -->
        <div class="toolbar" role="toolbar">
            <div class="tool-group">
                <label><?= t('text_color') ?></label>
                <button type="button" class="color-swatch swatch-default"
                        data-color=""
                        data-type="text"
                        title="<?= e(t('default_color')) ?>"
                        aria-label="<?= e(t('default_color')) ?>">A</button>
                <?php foreach ($siteColors as $color): ?>
                <button type="button" class="color-swatch"
                        style="background: <?= e($color['value']) ?>"
                        data-color="<?= e($color['value']) ?>"
                        data-type="text"
                        title="<?= e($color['name']) ?>"
                        aria-label="<?= e($color['name']) ?>"></button>
                <?php endforeach; ?>
            </div>

            <div class="sep"></div>

            <div class="tool-group">
                <label><?= t('highlight_color') ?></label>
                <?php foreach ($highlightColors as $color): ?>
                <button type="button" class="color-swatch"
                        style="background: <?= e($color['value']) ?>"
                        data-color="<?= e($color['value']) ?>"
                        data-type="highlight"
                        title="<?= e($color['name']) ?>"
                        aria-label="<?= e($color['name']) ?>"></button>
                <?php endforeach; ?>
                <button type="button" class="color-swatch swatch-none"
                        data-color="transparent"
                        data-type="highlight"
                        title="<?= e(t('remove_highlight')) ?>"
                        aria-label="<?= e(t('remove_highlight')) ?>"></button>
            </div>

            <div class="sep"></div>

            <div class="tool-group">
                <button type="button" class="tool-btn" id="btn-quote" title="<?= e(t('quote_block')) ?>">&#10077;</button>
                <button type="button" class="tool-btn" id="btn-hr" title="<?= e(t('divider')) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="4" y1="12" x2="20" y2="12"/><line x1="7" y1="6" x2="17" y2="6" opacity=".35"/><line x1="7" y1="18" x2="17" y2="18" opacity=".35"/></svg>
                </button>
                <button type="button" class="tool-btn" id="btn-link" title="<?= e(t('insert_link')) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                </button>
                <button type="button" class="tool-btn" id="btn-unlink" title="<?= e(t('remove_link')) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18.84 12.25l1.72-1.71a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M5.17 11.75l-1.72 1.71a5 5 0 0 0 7.07 7.07l1.72-1.71"/><line x1="8" y1="2" x2="8" y2="5"/><line x1="2" y1="8" x2="5" y2="8"/><line x1="16" y1="19" x2="16" y2="22"/><line x1="19" y1="16" x2="22" y2="16"/></svg>
                </button>
            </div>

            <div class="sep"></div>

            <button type="button" class="tool-btn btn-bible" id="btn-verse">
                <?= t('add_verse') ?>
            </button>

            <button type="button" class="tool-btn btn-improve" id="btn-improve">
                <?= t('improve') ?>
            </button>

            <button type="button" class="tool-btn btn-translate" id="btn-translate-all">
                <?= t('translate_all') ?>
            </button>
        </div>
        </div>

        <p class="sr-only" id="editor-keys-hint"><?= e(t('editor_keys_hint')) ?></p>

        <!-- Editors for each language -->
        <div class="editor-wrap">
            <?php foreach (SUPPORTED_LANGS as $code): ?>
            <div class="editor"
                 id="editor-<?= $code ?>"
                 contenteditable="true"
                 spellcheck="true"
                 lang="<?= $code ?>"
                 role="textbox"
                 aria-multiline="true"
                 aria-label="<?= e(t('monthly_teaching') . ' - ' . LANG_NAMES[$code]) ?>"
                 data-lang="<?= $code ?>"
                 data-placeholder="<?= e($placeholders[$code]) ?>"
                 aria-describedby="editor-keys-hint"
                 <?= $code !== 'af' ? 'hidden' : '' ?>><?= $contents[$code] ?></div>
            <?php endforeach; ?>
        </div>

        <div class="actions">
            <span class="status ready" id="status" role="status" aria-live="polite">
                <span id="status-icon">&#128190;</span>
                <span id="status-text"><?= t('ready_to_save') ?></span>
            </span>
            <span class="word-count" id="word-count"></span>
            <button type="button" class="btn btn-primary" id="btn-save">
                <?= t('save_all') ?>
            </button>
        </div>
    </div>

    <!-- Shown right after a paste: switch between the two versions -->
    <div class="paste-pop" id="paste-pop" role="group" aria-label="<?= e(t('paste_options')) ?>" hidden>
        <span class="paste-pop-title" aria-hidden="true"><?= e(t('paste_options')) ?>:</span>
        <span class="sr-only" id="paste-pop-live" aria-live="polite"></span>
        <span class="seg">
            <button type="button" class="seg-btn" aria-pressed="false" data-paste-switch="rich" title="<?= e(t('paste_rich_hint')) ?>"><span class="seg-ico" aria-hidden="true">Aa</span><?= e(t('paste_rich')) ?></button>
            <button type="button" class="seg-btn" aria-pressed="false" data-paste-switch="plain" title="<?= e(t('paste_plain_hint')) ?>"><span class="seg-ico" aria-hidden="true">T</span><?= e(t('paste_plain')) ?></button>
        </span>
        <button type="button" class="paste-pop-close" id="paste-pop-close" aria-label="<?= e(t('close')) ?>">&times;</button>
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
            <span class="lang-badge" data-lang="<?= $code ?>"><?= e(LANG_NAMES[$code]) ?></span>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Verse Modal -->
    <div id="verse-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><?= t('add_bible_verse') ?></h2>
                <button type="button" class="modal-close" aria-label="<?= e(t('close')) ?>">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="v-book"><?= t('book') ?></label>
                        <select id="v-book" class="form-control">
                            <option value=""><?= t('select') ?>...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="v-chapter"><?= t('chapter') ?></label>
                        <select id="v-chapter" class="form-control"></select>
                    </div>
                    <div class="form-group">
                        <label for="v-from"><?= t('from') ?></label>
                        <select id="v-from" class="form-control"></select>
                    </div>
                    <div class="form-group">
                        <label for="v-to"><?= t('to') ?></label>
                        <select id="v-to" class="form-control"></select>
                    </div>
                </div>
                <div id="v-preview" class="verse-preview"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary modal-close"><?= t('cancel') ?></button>
                <button type="button" class="btn btn-primary" id="btn-insert"><?= t('insert') ?></button>
            </div>
        </div>
    </div>

    <script type="application/json" id="eldersData"><?= json_encode($editorData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}' ?></script>
    <script src="/admin/js/elders.js?v=<?= e($VER) ?>"></script>

    <?php require_once __DIR__ . '/../header_footer/footer.php'; ?>
</body>
</html>
