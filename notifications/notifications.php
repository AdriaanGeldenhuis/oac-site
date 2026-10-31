<?php
declare(strict_types=1);
/**
 * Kennisgewings — "Light Through Glass", sapphire edition.
 * The welcome page's atmosphere lit in sapphire, the colour the bell
 * carries on the welcome tiles and on every page. Each notification wears
 * the jewel of the place it comes from (prayers amethyst, Gospel Media
 * ruby, calendar sapphire ...), grouped by day, newest first.
 *
 * The list is drawn by notifications.js from api/list.php. Tapping a
 * notification takes it down: open.php deletes it and goes on to its link
 * (the push on the phone goes the same way). "Mark all as read", deleting
 * (with undo) and clearing go through api/mark_read.php with the session
 * CSRF token.
 */

require_once dirname(__DIR__) . '/security/auth_gate.php';
require_once __DIR__ . '/lib/notifications.php';

if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS, true)) {
    $_SESSION['language'] = $_GET['lang'];
    header('Location: /notifications/notifications.php');
    exit;
}

$lang = nt_lang();

// Officers (amp 1-6) keep their link to the push diagnostics
$ampId = 999;
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $st = $pdo->prepare('SELECT amp_id FROM users WHERE id = ? LIMIT 1');
        $st->execute([nt_user_id()]);
        $ampId = (int)($st->fetchColumn() ?: 999);
    }
} catch (Throwable $e) {
    error_log('Notifications amp lookup: ' . $e->getMessage());
}

$config = [
    'lang' => $lang,
    'csrf' => csrf_token(),
    't' => nt_js_strings($lang),
];

// Filter chips: [filter, label key]
$filters = [
    ['all', 'all'], ['unread', 'unread'], ['prayer', 'nt_group_prayer'], ['gospel', 'nt_group_gospel'],
    ['calendar', 'nt_group_calendar'], ['thought', 'nt_group_thought'], ['other', 'nt_group_other'],
];

// Icons shared by the page and notifications.js (<use href="#nt-i-...">)
$icons = [
    'bell' => '<path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/>',
    'prayer' => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>',
    'gospel' => '<path d="M21 3H3c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h5v2h8v-2h5c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 14H3V5h18v12zM10 15l5.5-4.5L10 6v9z"/>',
    'calendar' => '<path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM9 10H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2z"/>',
    'thought' => '<path d="M9 21c0 .55.45 1 1 1h4c.55 0 1-.45 1-1v-1H9v1zm3-19C8.14 2 5 5.14 5 9c0 2.38 1.19 4.47 3 5.74V17c0 .55.45 1 1 1h6c.55 0 1-.45 1-1v-2.26c1.81-1.27 3-3.36 3-5.74 0-3.86-3.14-7-7-7z"/>',
    'cake' => '<path d="M12 6c1.11 0 2-.9 2-2 0-.38-.1-.73-.29-1.03L12 0l-1.71 2.97c-.19.3-.29.65-.29 1.03 0 1.1.9 2 2 2zm4.6 9.99l-1.07-1.07-1.08 1.07c-1.3 1.3-3.58 1.31-4.89 0l-1.07-1.07-1.09 1.07C6.75 16.64 5.88 17 4.96 17c-.73 0-1.4-.23-1.96-.61V21c0 .55.45 1 1 1h16c.55 0 1-.45 1-1v-4.61c-.56.38-1.23.61-1.96.61-.92 0-1.79-.36-2.44-1.01zM18 9h-5V7h-2v2H6c-1.66 0-3 1.34-3 3v1.54c0 1.08.88 1.96 1.96 1.96.52 0 1.02-.2 1.38-.57l2.14-2.13 2.13 2.13c.74.74 2.03.74 2.77 0l2.14-2.13 2.13 2.13c.37.37.86.57 1.38.57 1.08 0 1.96-.88 1.96-1.96V12C21 10.34 19.66 9 18 9z"/>',
    'heart' => '<path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>',
    'star' => '<path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/>',
    'person' => '<path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>',
    'check' => '<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>',
    'alert' => '<path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/>',
];
?><!doctype html>
<html lang="<?= nt_e($lang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= nt_e(__t('notifications', $lang)) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500;1,600&family=Parisienne&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= nt_asset('/notifications/css/notifications.css') ?>">
    <script>window.NOTIFS = <?= nt_json_for_script($config) ?>;</script>
</head>
<body class="nt-body">
    <?php require_once dirname(__DIR__) . '/header_footer/header.php'; ?>

    <svg class="nt-sprite" width="0" height="0" aria-hidden="true" focusable="false"><defs>
        <?php foreach ($icons as $id => $path): ?>
        <symbol id="nt-i-<?= $id ?>" viewBox="0 0 24 24" fill="currentColor"><?= $path ?></symbol>
        <?php endforeach; ?>
    </defs></svg>

    <div class="nt-aura" aria-hidden="true"><div class="nt-rays"></div><div class="nt-halo"></div></div>

    <main class="nt-main" id="ntMain">
        <section class="nt-hero" aria-labelledby="ntTitle">
            <p class="nt-eyebrow">
                <svg class="nt-eyebrow-icon" viewBox="0 0 24 24" aria-hidden="true"><use href="#nt-i-bell"/></svg>
                <span><?= nt_e(__t('your_notifications', $lang)) ?></span>
            </p>
            <h2 class="nt-title" id="ntTitle"><span><?= nt_e(__t('notifications', $lang)) ?></span></h2>
            <p class="nt-sub" id="ntSub" aria-live="polite">&nbsp;</p>
            <div class="nt-hero-actions">
                <button class="nt-btn nt-btn--primary" id="ntReadAll" type="button" hidden>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12.5l4.5 4.5L15 8.5M10.5 15.5L12 17l10-10"/></svg>
                    <span><?= nt_e(__t('mark_all_read', $lang)) ?></span>
                </button>
            </div>
        </section>

        <nav class="nt-filters" id="ntFilters" aria-label="<?= nt_e(__t('filter', $lang)) ?>">
            <?php foreach ($filters as [$value, $key]): ?>
            <button class="nt-chip nt-chip--<?= $value ?>" type="button" data-filter="<?= $value ?>" aria-pressed="<?= $value === 'all' ? 'true' : 'false' ?>"<?= in_array($value, ['all', 'unread'], true) ? '' : ' hidden' ?>>
                <span><?= nt_e(__t($key, $lang)) ?></span>
                <b class="nt-chip-count" data-count="<?= $value ?>"></b>
            </button>
            <?php endforeach; ?>
        </nav>

        <section class="nt-list" id="ntList" aria-busy="true" aria-label="<?= nt_e(__t('notifications', $lang)) ?>">
            <div class="nt-skel" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
            <p class="nt-sr"><?= nt_e(__t('loading', $lang)) ?></p>
        </section>

        <div class="nt-more" id="ntMoreWrap" hidden>
            <button class="nt-btn nt-btn--glass" id="ntMore" type="button"><span><?= nt_e(__t('nt_load_more', $lang)) ?></span></button>
        </div>

        <!-- Rarely needed, so at the end of the list -->
        <div class="nt-tools">
            <button class="nt-btn nt-btn--quiet" id="ntClear" type="button" hidden>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6M10 11v6M14 11v6"/></svg>
                <span><?= nt_e(__t('nt_clear_read', $lang)) ?></span>
            </button>
        </div>

        <?php if ($ampId <= 6): ?>
        <p class="nt-officer">
            <a class="nt-link" href="/admin/fcm-debug.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg>
                <span>FCM Debug</span>
            </a>
        </p>
        <?php endif; ?>
    </main>

    <div class="nt-toast" id="ntToast" role="status" aria-live="polite"><span class="nt-toast-text"></span></div>

    <script defer src="<?= nt_asset('/notifications/js/notifications.js') ?>"></script>

    <?php require_once dirname(__DIR__) . '/header_footer/footer.php'; ?>
</body>
</html>
