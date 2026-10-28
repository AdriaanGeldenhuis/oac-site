<?php
declare(strict_types=1);
/**
 * Gedagtes — "Light Through Glass", amethyst edition.
 * The welcome page's atmosphere lit in amethyst, the colour the thoughts
 * carry on the welcome tiles. Today's thought is a pane of leaded glass,
 * every earlier thought stays in the archive below (search, show more).
 *
 * Today's thought is drawn here (it is there at once, also offline from
 * the app's cache); the archive comes from api/list.php via gedagtes.js.
 */

header('Cache-Control: no-cache, must-revalidate');
require_once dirname(__DIR__) . '/security/auth_gate.php';
require_once __DIR__ . '/lib/gedagtes.php';

if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS, true)) {
    $_SESSION['language'] = $_GET['lang'];
    header('Location: /gedagtes/gedagtes.php');
    exit;
}

$lang = gd_lang();
$now = gd_now();
$todayDate = $now->format('Y-m-d');

$today = ['thought' => null, 'pending' => null];
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $today = gd_today($pdo, $lang);
    }
} catch (Throwable $e) {
    error_log('Gedagtes today error: ' . $e->getMessage());
}
$thought = $today['thought'];

$config = [
    'lang' => $lang,
    'today' => $todayDate,
    'pending' => $today['pending'],
    't' => gd_js_strings($lang),
];

$icons = [
    'bulb' => '<path d="M9 21c0 .55.45 1 1 1h4c.55 0 1-.45 1-1v-1H9v1zm3-19C8.14 2 5 5.14 5 9c0 2.38 1.19 4.47 3 5.74V17c0 .55.45 1 1 1h6c.55 0 1-.45 1-1v-2.26c1.81-1.27 3-3.36 3-5.74 0-3.86-3.14-7-7-7z"/>',
    'clock' => '<path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/>',
    'archive' => '<path d="M13 3a9 9 0 00-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42A8.954 8.954 0 0013 21a9 9 0 000-18zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/>',
];

$shareIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12v7a2 2 0 002 2h12a2 2 0 002-2v-7M16 6l-4-4-4 4M12 2v13"/></svg>';
$copyIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 4h10a2 2 0 012 2v12M16 8H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2V10a2 2 0 00-2-2z"/></svg>';
?><!doctype html>
<html lang="<?= gd_e($lang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= gd_e(__t('thought_history', $lang)) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500;1,600&family=Parisienne&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= gd_asset('/gedagtes/css/gedagtes.css') ?>">
    <script>window.GEDAGTES = <?= gd_json_for_script($config) ?>;</script>
</head>
<body class="gd-body">
    <?php require_once dirname(__DIR__) . '/header_footer/header.php'; ?>

    <svg class="gd-sprite" width="0" height="0" aria-hidden="true" focusable="false"><defs>
        <?php foreach ($icons as $id => $path): ?>
        <symbol id="gd-i-<?= $id ?>" viewBox="0 0 24 24" fill="currentColor"><?= $path ?></symbol>
        <?php endforeach; ?>
    </defs></svg>

    <div class="gd-aura" aria-hidden="true"><div class="gd-rays"></div><div class="gd-halo"></div></div>

    <main class="gd-main" id="gdMain">
        <section class="gd-hero" aria-labelledby="gdTitle">
            <p class="gd-eyebrow">
                <svg class="gd-eyebrow-icon" viewBox="0 0 24 24" aria-hidden="true"><use href="#gd-i-bulb"/></svg>
                <span><?= gd_e(__t('thought_of_day', $lang)) ?></span>
            </p>
            <h2 class="gd-title" id="gdTitle"><span><?= gd_e(__t('thought_history', $lang)) ?></span></h2>
            <p class="gd-sub"><?= gd_e(gd_long_date($todayDate, $lang)) ?></p>
            <p class="gd-tagline"><?= gd_e(__t('gd_tagline', $lang)) ?></p>
        </section>

        <!-- Today: a pane of leaded glass -->
        <section class="gd-today" aria-labelledby="gdTodayLabel">
            <h3 class="gd-label" id="gdTodayLabel"><?= gd_e(__t('today', $lang)) ?></h3>
            <?php if ($thought !== null): ?>
            <figure class="gd-card gd-pane" id="gdToday" data-date="<?= gd_e($thought['date']) ?>">
                <blockquote class="gd-pane-text" id="gdTodayText" lang="<?= gd_e($thought['lang']) ?>"><?= gd_e($thought['text']) ?></blockquote>
                <?php if ($thought['author'] !== ''): ?>
                <figcaption class="gd-pane-author" id="gdTodayAuthor"><?= gd_e($thought['author']) ?></figcaption>
                <?php endif; ?>
                <div class="gd-pane-actions">
                    <button class="gd-btn gd-btn--primary" id="gdTodayShare" type="button">
                        <?= $shareIcon ?>
                        <span><?= gd_e(__t('share', $lang)) ?></span>
                    </button>
                    <button class="gd-btn gd-btn--glass" id="gdTodayCopy" type="button">
                        <?= $copyIcon ?>
                        <span><?= gd_e(__t('gd_copy', $lang)) ?></span>
                    </button>
                </div>
            </figure>
            <?php else: ?>
            <div class="gd-card gd-wait">
                <span class="gd-wait-orb" aria-hidden="true"><svg viewBox="0 0 24 24"><use href="#gd-i-<?= $today['pending'] ? 'clock' : 'bulb' ?>"/></svg></span>
                <p class="gd-wait-text">
                    <?= gd_e($today['pending']
                        ? str_replace('{time}', $today['pending'], __t('gd_pending', $lang))
                        : __t('gd_none_today', $lang)) ?>
                </p>
                <p class="gd-wait-hint"><?= gd_e(__t('gd_none_today_hint', $lang)) ?></p>
            </div>
            <?php endif; ?>
        </section>

        <!-- Every earlier thought -->
        <section class="gd-archive" aria-labelledby="gdArchiveLabel">
            <h3 class="gd-label" id="gdArchiveLabel"><?= gd_e(__t('gd_earlier', $lang)) ?></h3>

            <div class="gd-card gd-toolbar" role="search">
                <div class="gd-search">
                    <svg class="gd-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                    <input class="gd-search-input" id="gdSearch" type="search" enterkeyhint="search" autocomplete="off" maxlength="100"
                           placeholder="<?= gd_e(__t('gd_search_ph', $lang)) ?>" aria-label="<?= gd_e(__t('gd_search_ph', $lang)) ?>">
                    <button class="gd-search-clear" id="gdSearchClear" type="button" aria-label="<?= gd_e(__t('gd_clear_search', $lang)) ?>" hidden>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </div>
            </div>

            <p class="gd-count" id="gdCount" aria-live="polite"></p>

            <div class="gd-list" id="gdList" aria-busy="true">
                <div class="gd-skel" aria-hidden="true"><i></i><i></i><i></i></div>
                <p class="gd-sr"><?= gd_e(__t('loading', $lang)) ?></p>
            </div>

            <div class="gd-more" id="gdMoreWrap" hidden>
                <button class="gd-btn gd-btn--glass" id="gdMore" type="button"><span><?= gd_e(__t('gd_load_more', $lang)) ?></span></button>
            </div>
        </section>
    </main>

    <div class="gd-toast" id="gdToast" role="status" aria-live="polite"></div>

    <script defer src="<?= gd_asset('/gedagtes/js/gedagtes.js') ?>"></script>

    <?php require_once dirname(__DIR__) . '/header_footer/footer.php'; ?>
</body>
</html>
