<?php
declare(strict_types=1);
/**
 * Diary — "Light Through Glass", amber edition.
 * A private place to write: only the owner ever sees an entry (also in
 * their own calendar).
 *
 * Built to stand still: the search and period bar stays pinned under the
 * header while the list changes below it, nothing slides in when results
 * change, and the reader and the writer are full pages with their actions
 * at the top (never behind the phone's keyboard). See diary.js.
 *
 * Links: ?entry=ID opens an entry, ?date=YYYY-MM-DD[&time=HH:MM] starts one.
 */

require_once dirname(__DIR__) . '/security/auth_gate.php';
require_once __DIR__ . '/lib/diary.php';

if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS, true)) {
    $_SESSION['language'] = $_GET['lang'];
    // The open entry lives in the #hash, which the browser keeps across this redirect
    header('Location: /diary/diary.php');
    exit;
}

$lang = dy_lang();
$today = dy_now()->format('Y-m-d');
$T = dy_js_strings($lang);
$config = [
    'lang' => $lang,
    'csrf' => csrf_token(),
    'userId' => dy_user_id(),
    'today' => $today,
    'moods' => DY_MOODS,
    'weathers' => DY_WEATHERS,
    'limits' => ['title' => DY_TITLE_MAX, 'body' => DY_BODY_MAX, 'tag' => DY_TAG_MAX, 'tags' => DY_TAGS_MAX],
    't' => $T,
];

$icons = [
    'diary' => '<path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>',
    'calendar' => '<path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM9 10H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2z"/>',
    'book' => '<path d="M18 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM6 4h5v8l-2.5-1.5L6 12V4z"/>',
    'history' => '<path d="M13 3a9 9 0 00-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42A8.954 8.954 0 0013 21a9 9 0 000-18zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/>',
    'pdf' => '<path d="M20 2H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-8.5 7.5c0 .83-.67 1.5-1.5 1.5H9v2H7.5V7H10c.83 0 1.5.67 1.5 1.5v1zm5 2c0 .83-.67 1.5-1.5 1.5h-2.5V7H15c.83 0 1.5.67 1.5 1.5v3zm4-3H19v1h1.5V11H19v2h-1.5V7h3v1.5zM9 9.5h1v-1H9v1zM4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm10 5.5h1v-3h-1v3z"/>',
    'notes' => '<path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>',
    'spark' => '<path d="M12 2l2.4 6.6L21 11l-6.6 2.4L12 20l-2.4-6.6L3 11l6.6-2.4L12 2zM19 15l1 2.6 2.6 1-2.6 1-1 2.6-1-2.6-2.6-1 2.6-1 1-2.6z"/>',
    'lock' => '<path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zM9 8V6c0-1.66 1.34-3 3-3s3 1.34 3 3v2H9z"/>',
];
?><!doctype html>
<html lang="<?= dy_e($lang) ?>">
<head>
    <meta charset="utf-8">
    <!-- interactive-widget: the phone's keyboard makes the page smaller instead of sliding it up -->
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
    <title><?= dy_e(__t('my_diary', $lang)) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500;1,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= dy_asset('/diary/css/diary.css') ?>">
    <script>window.DIARY = <?= dy_json_for_script($config) ?>;</script>
</head>
<body class="dy-body">
    <?php require_once dirname(__DIR__) . '/header_footer/header.php'; ?>

    <svg class="dy-sprite" width="0" height="0" aria-hidden="true" focusable="false"><defs>
        <?php foreach ($icons as $id => $path): ?>
        <symbol id="dy-i-<?= $id ?>" viewBox="0 0 24 24" fill="currentColor"><?= $path ?></symbol>
        <?php endforeach; ?>
    </defs></svg>

    <div class="dy-aura" aria-hidden="true"><div class="dy-rays"></div><div class="dy-halo"></div></div>

    <main class="dy-main" id="dyMain">
        <section class="dy-hero" aria-labelledby="dyTitle">
            <p class="dy-private">
                <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#dy-i-lock"/></svg>
                <span><?= dy_e(__t('dy_private', $lang)) ?></span>
            </p>
            <h2 class="dy-title" id="dyTitle"><span><?= dy_e(__t('my_diary', $lang)) ?></span></h2>
            <p class="dy-sub"><?= dy_e(dy_long_date($today, $lang)) ?></p>
            <button class="dy-btn dy-btn--primary dy-write-btn" id="dyWrite" type="button">
                <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#dy-i-diary"/></svg>
                <span><?= dy_e(__t('dy_write_today', $lang)) ?></span>
            </button>
        </section>

        <!-- Pinned under the header: it never moves while the list changes below it -->
        <div class="dy-bar" id="dyBar">
            <div class="dy-bar-inner">
                <div class="dy-search" role="search">
                    <svg class="dy-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                    <input class="dy-search-input" id="dySearch" type="search" enterkeyhint="search" autocomplete="off" maxlength="100"
                           placeholder="<?= dy_e(__t('search_entries', $lang)) ?>" aria-label="<?= dy_e(__t('dy_search_label', $lang)) ?>">
                    <button class="dy-search-clear" id="dySearchClear" type="button" aria-label="<?= dy_e(__t('dy_clear_search', $lang)) ?>" hidden>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </div>
                <div class="dy-seg" id="dyFilters" role="group" aria-label="<?= dy_e(__t('dy_period', $lang)) ?>">
                    <?php foreach (['' => 'all', 'week' => 'this_week', 'month' => 'this_month', 'year' => 'this_year'] as $value => $key): ?>
                    <button class="dy-seg-btn" type="button" data-period="<?= $value ?>" aria-pressed="<?= $value === '' ? 'true' : 'false' ?>"><span><?= dy_e(__t($key, $lang)) ?></span></button>
                    <?php endforeach; ?>
                </div>
                <button class="dy-tool" id="dyExport" type="button" aria-haspopup="dialog" aria-label="<?= dy_e(__t('dy_export', $lang)) ?>" title="<?= dy_e(__t('dy_export', $lang)) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12M7 10l5 5 5-5M5 21h14"/></svg>
                    <span class="dy-tool-label"><?= dy_e(__t('dy_export', $lang)) ?></span>
                </button>
            </div>
            <span class="dy-progress" aria-hidden="true"></span>
        </div>

        <!-- One line of fixed height: the count, the tag being shown and the order -->
        <div class="dy-status">
            <div class="dy-status-main">
                <p class="dy-count" id="dyCount" aria-live="polite"></p>
                <span class="dy-tag-slot" id="dyTagSlot"></span>
            </div>
            <button class="dy-sort" id="dySort" type="button">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 4v16M3 16l4 4 4-4M17 20V4M13 8l4-4 4 4"/></svg>
                <span class="dy-sr"><?= dy_e(__t('dy_sort_label', $lang)) ?>: </span>
                <span id="dySortLabel"><?= dy_e(__t('newest_first', $lang)) ?></span>
            </button>
        </div>

        <section class="dy-list" id="dyList" aria-busy="true" aria-label="<?= dy_e(__t('my_diary', $lang)) ?>">
            <div class="dy-skel" aria-hidden="true"><i></i><i></i><i></i></div>
            <p class="dy-sr"><?= dy_e(__t('loading_entries', $lang)) ?></p>
        </section>

        <div class="dy-more" id="dyMoreWrap" hidden>
            <button class="dy-btn dy-btn--glass" id="dyMore" type="button"><span><?= dy_e(__t('dy_load_more', $lang)) ?></span></button>
        </div>
    </main>

    <!-- New entry on phones: the same glass circle and slot as Gospel Media's new-post button -->
    <button class="global-back-btn global-post-btn dy-fab" id="dyFab" type="button" aria-label="<?= dy_e(__t('new_entry', $lang)) ?>">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M12 5v14M19 12H5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </button>

    <div class="dy-toast" id="dyToast" role="status" aria-live="polite"><span class="dy-toast-text"></span></div>

    <script defer src="<?= dy_asset('/diary/js/diary.js') ?>"></script>

    <?php require_once dirname(__DIR__) . '/header_footer/footer.php'; ?>
</body>
</html>
