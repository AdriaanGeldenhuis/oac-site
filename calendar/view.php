<?php
declare(strict_types=1);
/**
 * Someone else's calendar (read only), for the people cal_view_rights()
 * allows. Shows their visits and appointments with title, time and place;
 * never diary entries or notes. Officers can export the period (PDF / CSV)
 * and ask for an appointment on a chosen day (Ampte → Afsprake).
 */

require_once dirname(__DIR__) . '/security/auth_gate.php';
require_once __DIR__ . '/lib/page.php';

$targetId = (int)($_GET['u'] ?? 0);

if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS, true)) {
    $_SESSION['language'] = $_GET['lang'];
    header('Location: /calendar/view.php?u=' . $targetId);
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

// Your own calendar lives on calendar.php (with everything in it)
if ($targetId <= 0 || $targetId === (int)$me['id']) {
    header('Location: /calendar/calendar.php');
    exit;
}

$target = cal_user($pdo, $targetId);
$rights = $target ? cal_view_rights($me, $target) : ['view' => false, 'export' => false, 'appoint' => false];

$T = cal_js_strings($pageLang);
$fullName = $target ? cal_name($target['name'] ?? '', $target['surname'] ?? '') : '';
$office = ($target && !empty($target['amp_id']))
    ? get_translated_office(cal_amp($target), (string)($target['gender'] ?? 'man'), $pageLang)
    : '';

$photo = (string)($target['photo'] ?? '');
if ($photo !== '' && strpos($photo, 'http') !== 0 && strpos($photo, '/') !== 0) {
    $photo = '/assets/uploads/' . $photo;
}
$parts = preg_split('/\s+/', $fullName) ?: [];
$initials = count($parts) >= 2
    ? mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[count($parts) - 1], 0, 1))
    : mb_strtoupper(mb_substr($fullName, 0, 2));

if (!$rights['view']) {
    http_response_code(403);
}

$config = [
    'mode' => 'view',
    'lang' => $pageLang,
    'csrf' => csrf_token(),
    'userId' => (int)$me['id'],
    'targetId' => $targetId,
    'targetName' => $fullName,
    'canExport' => $rights['export'],
    'canAppoint' => $rights['appoint'],
    'today' => cal_now()->format('Y-m-d'),
    't' => $T,
];
?><!doctype html>
<html lang="<?= cal_e($pageLang) ?>">
<head>
    <?php cal_render_head(__t('calendar', $pageLang) . ($rights['view'] ? ' - ' . $fullName : '')); ?>
    <?php if ($rights['view']): ?>
    <script>window.CAL = <?= cal_json_for_script($config) ?>;</script>
    <?php endif; ?>
</head>
<body class="cal-body cal-body--view">
    <?php require_once dirname(__DIR__) . '/header_footer/header.php'; ?>
    <?php cal_render_sprite(); ?>
    <?php cal_render_aura(); ?>

    <main class="cal-main" id="calMain">
    <?php if (!$rights['view']): ?>
        <section class="cal-card cal-notice" role="alert">
            <span class="cal-notice-orb" aria-hidden="true"><svg viewBox="0 0 24 24"><use href="#cal-i-calendar"/></svg></span>
            <p class="cal-notice-text"><?= cal_e(__t('cal_access_denied', $pageLang)) ?></p>
            <a class="cal-btn cal-btn--primary" href="/calendar/calendar.php"><?= cal_e(__t('cal_back_to_mine', $pageLang)) ?></a>
        </section>
    <?php else: ?>
        <section class="cal-hero cal-hero--person" aria-labelledby="calPerson">
            <div class="cal-person">
                <?php if ($photo !== ''): ?>
                    <img class="cal-avatar" src="<?= cal_e($photo) ?>" alt="" width="72" height="72" decoding="async" data-initials="<?= cal_e($initials ?: '?') ?>">
                <?php else: ?>
                    <span class="cal-avatar cal-avatar--initials" aria-hidden="true"><?= cal_e($initials ?: '?') ?></span>
                <?php endif; ?>
                <div class="cal-person-text">
                    <p class="cal-eyebrow cal-eyebrow--plain">
                        <svg class="cal-eyebrow-icon" viewBox="0 0 24 24" aria-hidden="true"><use href="#cal-i-calendar"/></svg>
                        <span><?= cal_e(__t('calendar', $pageLang)) ?></span>
                    </p>
                    <h2 class="cal-person-name" id="calPerson"><?= cal_e($fullName) ?></h2>
                    <?php if ($office !== ''): ?><p class="cal-person-office"><?= cal_e($office) ?></p><?php endif; ?>
                </div>
            </div>
            <h3 class="cal-title cal-title--sm" id="calTitle"><span>&nbsp;</span></h3>
            <p class="cal-sub" id="calSub">&nbsp;</p>
            <div class="cal-hero-actions">
                <a class="cal-btn cal-btn--glass" href="/profile/?u=<?= $targetId ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#cal-i-person"/></svg>
                    <span><?= cal_e($T['profile']) ?></span>
                </a>
                <?php if ($rights['export']): ?>
                <a class="cal-btn cal-btn--glass" id="calExportPdf" href="/calendar/api/export_pdf.php?user_id=<?= $targetId ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#cal-i-pdf"/></svg>
                    <span><?= cal_e($T['pdf']) ?></span>
                </a>
                <a class="cal-btn cal-btn--glass" id="calExportCsv" href="/calendar/api/export_excel.php?user_id=<?= $targetId ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#cal-i-sheet"/></svg>
                    <span><?= cal_e($T['excel']) ?></span>
                </a>
                <?php endif; ?>
                <span class="cal-chip cal-chip--static">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#cal-i-notes"/></svg>
                    <?= cal_e($T['cal_read_only']) ?>
                </span>
            </div>
        </section>

        <?php cal_render_board($T, false); ?>
    <?php endif; ?>
    </main>

    <div class="cal-toast" id="calToast" role="status" aria-live="polite"></div>

    <?php if ($rights['view']): ?>
    <script defer src="<?= cal_asset('/calendar/js/calendar.js') ?>"></script>
    <script defer src="<?= cal_asset('/calendar/js/view.js') ?>"></script>
    <?php endif; ?>

    <?php require_once dirname(__DIR__) . '/header_footer/footer.php'; ?>
</body>
</html>
