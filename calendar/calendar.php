<?php
declare(strict_types=1);
/**
 * Calendar — "Light Through Glass", sapphire edition.
 * The welcome page's atmosphere lit in sapphire, the colour the calendar
 * carries on the welcome tiles. Each kind of item keeps its own jewel:
 * room events sapphire, visits emerald, appointments amethyst, diary amber,
 * birthdays ruby.
 *
 * Room events are Gospel Media posts: the feed's rules decide who sees,
 * posts and edits them (gospel_media/lib/permissions.php). Diary entries
 * are only ever shown to their owner.
 */

require_once dirname(__DIR__) . '/security/auth_gate.php';
require_once __DIR__ . '/lib/page.php';

if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS, true)) {
    $_SESSION['language'] = $_GET['lang'];
    // The view and day live in the #hash, which the browser keeps across this redirect
    header('Location: /calendar/calendar.php');
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    die('Database connection failed');
}

$pageLang = cal_lang();
$me = cal_user($pdo, (int)$_SESSION['user_id']);
if (!$me) {
    header('Location: /logout.php');
    exit;
}

$T = cal_js_strings($pageLang);
$config = [
    'mode' => 'own',
    'lang' => $pageLang,
    'csrf' => csrf_token(),
    'userId' => (int)$me['id'],
    'today' => cal_now()->format('Y-m-d'),
    't' => $T,
];
?><!doctype html>
<html lang="<?= cal_e($pageLang) ?>">
<head>
    <?php cal_render_head(__t('calendar', $pageLang)); ?>
    <script>window.CAL = <?= cal_json_for_script($config) ?>;</script>
</head>
<body class="cal-body">
    <?php require_once dirname(__DIR__) . '/header_footer/header.php'; ?>
    <?php cal_render_sprite(); ?>
    <?php cal_render_aura(); ?>

    <main class="cal-main" id="calMain">
        <section class="cal-hero" aria-labelledby="calTitle">
            <p class="cal-eyebrow">
                <svg class="cal-eyebrow-icon" viewBox="0 0 24 24" aria-hidden="true"><use href="#cal-i-calendar"/></svg>
                <span><?= cal_e(__t('calendar', $pageLang)) ?></span>
            </p>
            <h2 class="cal-title" id="calTitle"><span>&nbsp;</span></h2>
            <p class="cal-sub" id="calSub">&nbsp;</p>
        </section>

        <div class="cal-banner" id="calBanner" hidden>
            <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#cal-i-share"/></svg>
            <span><?= cal_e($T['cal_spouse_pending']) ?></span>
            <a class="cal-btn cal-btn--glass cal-btn--sm" href="/admin/account.php"><?= cal_e($T['cal_spouse_view']) ?></a>
        </div>

        <?php cal_render_board($T, true); ?>
    </main>

    <!-- New item: same glass circle and slot as Gospel Media's new-post button -->
    <button class="global-back-btn global-post-btn cal-fab" id="calFab" type="button" aria-label="<?= cal_e($T['cal_new']) ?>" aria-haspopup="dialog">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M12 5v14M19 12H5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </button>

    <div class="cal-toast" id="calToast" role="status" aria-live="polite"></div>

    <script defer src="<?= cal_asset('/calendar/js/calendar.js') ?>"></script>

    <?php require_once dirname(__DIR__) . '/header_footer/footer.php'; ?>
</body>
</html>
