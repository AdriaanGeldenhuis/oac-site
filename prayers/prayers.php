<?php
declare(strict_types=1);
/**
 * Gebede — "Light Through Glass", amethyst edition.
 * The welcome page's atmosphere lit in amethyst, the colour the prayers
 * carry on the welcome tiles. One prayer wall for the church: share a
 * need, pray along, send a heart, write an encouraging word.
 *
 * The feed, the composer, the comments and "who prays along" are drawn by
 * prayers.js; every write goes through api/ with the session CSRF token.
 * Links: #post-ID shows a prayer, #post-ID-c opens its comments.
 */

require_once dirname(__DIR__) . '/security/auth_gate.php';
require_once __DIR__ . '/lib/prayers.php';

if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS, true)) {
    $_SESSION['language'] = $_GET['lang'];
    // The prayer asked for lives in the #hash, which the browser keeps across this redirect
    header('Location: /prayers/prayers.php');
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    die('Database connection failed');
}

$lang = pr_lang();
$me = pr_me($pdo);
$myName = pr_full_name($me);
$myPhoto = pr_photo_url($me['photo'] ?? null);
$myInitials = pr_initials($myName);
$myTown = trim((string)($me['town_name'] ?? ''));

$config = [
    'lang' => $lang,
    'csrf' => csrf_token(),
    'userId' => pr_user_id(),
    'me' => ['name' => $myName, 'initials' => $myInitials, 'photo' => $myPhoto],
    'town' => $myTown,
    'limits' => ['text' => PR_TEXT_MAX, 'comment' => PR_COMMENT_MAX],
    't' => pr_js_strings($lang),
];

$icons = [
    'prayer' => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>',
    'photo' => '<path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>',
    'pin' => '<path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>',
    'person' => '<path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>',
    'globe' => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>',
];
?><!doctype html>
<html lang="<?= pr_e($lang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= pr_e(__t('prayers', $lang)) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500;1,600&family=Parisienne&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= pr_asset('/prayers/css/prayers.css') ?>">
    <script>window.PRAYERS = <?= pr_json_for_script($config) ?>;</script>
</head>
<body class="pr-body">
    <?php require_once dirname(__DIR__) . '/header_footer/header.php'; ?>

    <svg class="pr-sprite" width="0" height="0" aria-hidden="true" focusable="false"><defs>
        <?php foreach ($icons as $id => $path): ?>
        <symbol id="pr-i-<?= $id ?>" viewBox="0 0 24 24" fill="currentColor"><?= $path ?></symbol>
        <?php endforeach; ?>
    </defs></svg>

    <div class="pr-aura" aria-hidden="true"><div class="pr-rays"></div><div class="pr-halo"></div></div>

    <main class="pr-main" id="prMain">
        <section class="pr-hero" aria-labelledby="prTitle">
            <p class="pr-eyebrow">
                <svg class="pr-eyebrow-icon" viewBox="0 0 24 24" aria-hidden="true"><use href="#pr-i-prayer"/></svg>
                <span><?= pr_e(__t('pr_eyebrow', $lang)) ?></span>
            </p>
            <h2 class="pr-title" id="prTitle"><span><?= pr_e(__t('prayers', $lang)) ?></span></h2>
            <p class="pr-sub"><?= pr_e(__t('prayers_subtitle', $lang)) ?></p>
        </section>

        <!-- "What can we pray about together?" opens the composer -->
        <div class="pr-card pr-prompt">
            <button class="pr-prompt-main" type="button" id="prPrompt">
                <?php if ($myPhoto !== null): ?>
                    <img class="pr-avatar pr-prompt-avatar" src="<?= pr_e($myPhoto) ?>" alt="" width="44" height="44" decoding="async">
                <?php else: ?>
                    <span class="pr-avatar pr-prompt-avatar pr-initials" aria-hidden="true"><?= pr_e($myInitials) ?></span>
                <?php endif; ?>
                <span class="pr-prompt-text"><?= pr_e(__t('pr_prompt', $lang)) ?></span>
            </button>
            <button class="pr-prompt-tool" type="button" id="prPromptPhoto" aria-label="<?= pr_e(__t('pr_add_photo', $lang)) ?>" title="<?= pr_e(__t('pr_add_photo', $lang)) ?>">
                <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#pr-i-photo"/></svg>
            </button>
        </div>

        <nav class="pr-filters" id="prFilters" aria-label="<?= pr_e(__t('filter', $lang)) ?>">
            <button class="pr-chip" type="button" data-filter="all" aria-pressed="true">
                <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#pr-i-globe"/></svg>
                <span><?= pr_e(__t('all', $lang)) ?></span>
            </button>
            <?php if ($myTown !== ''): ?>
            <button class="pr-chip" type="button" data-filter="town" aria-pressed="false">
                <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#pr-i-pin"/></svg>
                <span><?= pr_e($myTown) ?></span>
            </button>
            <?php endif; ?>
            <button class="pr-chip" type="button" data-filter="mine" aria-pressed="false">
                <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#pr-i-person"/></svg>
                <span><?= pr_e(__t('pr_filter_mine', $lang)) ?></span>
            </button>
        </nav>

        <section class="pr-feed" id="prFeed" aria-busy="true" aria-label="<?= pr_e(__t('prayers', $lang)) ?>">
            <div class="pr-skel" aria-hidden="true"><i></i><i></i></div>
            <p class="pr-sr"><?= pr_e(__t('loading_prayers', $lang)) ?></p>
        </section>

        <div class="pr-more" id="prMoreWrap" hidden>
            <button class="pr-btn pr-btn--glass" id="prMore" type="button"><span><?= pr_e(__t('pr_load_more', $lang)) ?></span></button>
        </div>

        <!-- Closing verse, by candlelight -->
        <section class="pr-bene">
            <div class="pr-candle" aria-hidden="true"><i class="pr-flame"></i><i class="pr-wick"></i><i class="pr-wax"></i></div>
            <blockquote class="pr-quote">
                <p class="pr-quote-text"><?= pr_e(__t('pr_verse', $lang)) ?></p>
                <cite class="pr-quote-source"><?= pr_e(__t('pr_verse_ref', $lang)) ?></cite>
            </blockquote>
        </section>
    </main>

    <!-- New prayer on phones: the same glass circle and slot as Gospel Media's new-post button -->
    <button class="global-back-btn global-post-btn pr-fab" id="prFab" type="button" aria-label="<?= pr_e(__t('share_prayer_testimony', $lang)) ?>">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M12 5v14M19 12H5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </button>

    <div class="pr-toast" id="prToast" role="status" aria-live="polite"></div>

    <script defer src="<?= pr_asset('/prayers/js/prayers.js') ?>"></script>

    <?php require_once dirname(__DIR__) . '/header_footer/footer.php'; ?>
</body>
</html>
