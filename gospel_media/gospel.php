<?php
declare(strict_types=1);
/**
 * Gospel Media — "Light Through Glass", ruby edition.
 * Same atmosphere as the welcome page (rays, halo, embers, stained-glass
 * cards), lit in the ruby that Gospel Media carries on the welcome tiles.
 * Who may see / post / moderate is decided in lib/permissions.php only.
 */

require_once dirname(__DIR__) . '/security/auth_gate.php';
require_once dirname(__DIR__) . '/includes/languages.php';
require_once __DIR__ . '/lib/permissions.php';

// Language
$pageLang = $_SESSION['language'] ?? 'af';
if (!in_array($pageLang, SUPPORTED_LANGS, true)) {
    $pageLang = 'af';
}
if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS, true)) {
    $_SESSION['language'] = $_GET['lang'];
    $back = (int)($_GET['room_id'] ?? 0);
    header('Location: /gospel_media/gospel.php' . ($back > 0 ? '?room_id=' . $back : ''));
    exit;
}
// Translation helper using central 5-language system
function t(string $key): string {
    global $pageLang;
    return __t($key, $pageLang);
}

function gm_e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// Cache-bust by file time, so browsers keep the assets until they really change
function gm_asset(string $path): string {
    $mtime = @filemtime(dirname(__DIR__) . $path);
    return $path . '?v=' . ($mtime ?: '1');
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    die('Database connection failed');
}

$userId = (int)$_SESSION['user_id'];
$roomId = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;

// Get user info
$userStmt = $pdo->prepare("SELECT name, surname, photo, amp_id, town_id, congregation_id FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$userInfo = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$ampId = (int)($userInfo['amp_id'] ?? 999);
$userTown = (int)($userInfo['town_id'] ?? 0);
$userCong = (int)($userInfo['congregation_id'] ?? 0);

// What the page shows instead of a feed when there is nothing to open
$notice = null;

// No room asked for: open the room the user was last in, else their own
// gemeente, else the first room of their town / their memberships that the
// permission rules actually let them into (an Apostel, for instance, has no
// gemeente room of their own and would otherwise always land on "no access").
if ($roomId <= 0) {
    $candidates = [];
    $lastRoom = (int)($_SESSION['gm_last_room'] ?? 0);
    if ($lastRoom > 0) {
        $candidates[] = $lastRoom;
    }

    try {
        if ($userCong > 0) {
            $q = $pdo->prepare("SELECT id FROM rooms WHERE type = 'gemeente' AND gemeente_id = ?");
            $q->execute([$userCong]);
            $candidates = array_merge($candidates, $q->fetchAll(PDO::FETCH_COLUMN));
        }

        if ($userTown > 0) {
            $q = $pdo->prepare("SELECT id FROM rooms WHERE town_id = ? AND type != 'gemeenskap' ORDER BY
                               FIELD(type, 'gemeente', 'opsienerskap', 'jeug', 'sondagskool')");
            $q->execute([$userTown]);
            $candidates = array_merge($candidates, $q->fetchAll(PDO::FETCH_COLUMN));
        }

        $q = $pdo->prepare("SELECT room_id FROM room_memberships WHERE user_id = ? ORDER BY room_id");
        $q->execute([$userId]);
        $candidates = array_merge($candidates, $q->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $e) {
        error_log('Gospel default room lookup: ' . $e->getMessage());
    }

    $roomLookup = $pdo->prepare("SELECT * FROM rooms WHERE id = ? LIMIT 1");
    foreach (array_unique(array_map('intval', $candidates)) as $candidate) {
        if ($candidate <= 0) continue;
        $roomLookup->execute([$candidate]);
        $candidateRoom = $roomLookup->fetch(PDO::FETCH_ASSOC);
        if ($candidateRoom && user_has_access_to_room($pdo, $userId, $candidateRoom)) {
            header('Location: /gospel_media/gospel.php?room_id=' . $candidate);
            exit;
        }
    }

    $notice = 'no_rooms_available';
}

// Room labels: "Gemeente" + "Pretoria", translated, without doubling an
// Afrikaans prefix that is already part of the stored name
function gm_type_label(string $type, string $lang): string {
    $keys = ['gemeente' => 'congregation', 'opsienerskap' => 'oversight', 'jeug' => 'youth', 'sondagskool' => 'sunday_school'];
    return __t($keys[$type] ?? 'room', $lang);
}

function gm_short_name(string $type, string $name): string {
    $afPrefixes = ['gemeente' => 'Gemeente ', 'opsienerskap' => 'Opsienerskap ', 'jeug' => 'Jeug ', 'sondagskool' => 'Sondagskool '];
    if (isset($afPrefixes[$type]) && stripos($name, $afPrefixes[$type]) === 0) {
        $name = substr($name, strlen($afPrefixes[$type]));
    }
    return trim($name);
}

function gm_label($type, $name, $lang) {
    $t = strtolower((string)$type);
    $n = gm_short_name($t, (string)$name);
    if (in_array($t, ['gemeente', 'opsienerskap', 'jeug', 'sondagskool'], true)) {
        return gm_type_label($t, $lang) . ' ' . $n;
    }
    return $n ?: __t('room', $lang);
}

$roomTitle = __t('room', $pageLang);
$roomType = '';
$roomObj = null;
$allowPost = false;

if ($notice === null) {
    $rs = $pdo->prepare("SELECT * FROM rooms WHERE id=?");
    $rs->execute([$roomId]);
    $roomObj = $rs->fetch(PDO::FETCH_ASSOC) ?: null;

    // Check access
    $hasAccess = $roomObj ? user_has_access_to_room($pdo, $userId, $roomObj) : false;
    if (!$hasAccess) {
        // Say nothing about the room itself: not even its name.
        // (Status stays 200, as before, so the app shell shows this page.)
        $notice = 'no_room_access';
        $roomObj = null;
        $roomId = 0;
        if ((int)($_SESSION['gm_last_room'] ?? 0) === (int)($_GET['room_id'] ?? 0)) {
            unset($_SESSION['gm_last_room']);
        }
    } else {
        $roomTitle = gm_label($roomObj['type'] ?? '', $roomObj['name'] ?? '', $pageLang);
        $roomType = strtolower((string)($roomObj['type'] ?? ''));
        $_SESSION['gm_last_room'] = $roomId;

        // Check posting rights
        $allowPost = user_can_post_in_room($pdo, $userId, $roomObj);
    }
}

// Known room types get their own stained-glass colour (see gospel.css)
$knownTypes = ['gemeente', 'opsienerskap', 'jeug', 'sondagskool', 'gemeenskap'];
$typeClass = in_array($roomType, $knownTypes, true) ? $roomType : ($roomObj ? 'other' : 'none');
$typeIcon = [
    'gemeente' => 'church', 'opsienerskap' => 'shield', 'jeug' => 'spark',
    'sondagskool' => 'school', 'gemeenskap' => 'globe', 'other' => 'globe', 'none' => 'gospel'
][$typeClass];

// Hero: the type as the eyebrow, the place name in script
$heroEyebrow = 'Gospel Media';
$heroName = $notice === null ? $roomTitle : t('rooms');
if ($roomObj && in_array($roomType, ['gemeente', 'opsienerskap', 'jeug', 'sondagskool'], true)) {
    $short = gm_short_name($roomType, (string)($roomObj['name'] ?? ''));
    if ($short !== '') {
        $heroEyebrow = gm_type_label($roomType, $pageLang);
        $heroName = $short;
    }
}
$nameLen = function_exists('mb_strlen') ? mb_strlen($heroName, 'UTF-8') : strlen($heroName);
$nameSize = $nameLen > 20 ? ' gm-title--sm' : ($nameLen > 11 ? ' gm-title--md' : '');

// Composer prompt avatar: photo, else initials
$myPhoto = (string)($userInfo['photo'] ?? '');
if ($myPhoto !== '' && strpos($myPhoto, 'http') !== 0 && strpos($myPhoto, '/') !== 0) {
    $myPhoto = '/assets/uploads/' . $myPhoto;
}
$myName = trim((string)($userInfo['name'] ?? '') . ' ' . (string)($userInfo['surname'] ?? ''));
$nameParts = preg_split('/\s+/', $myName) ?: [];
if (count($nameParts) >= 2) {
    $myInitials = mb_strtoupper(mb_substr($nameParts[0], 0, 1) . mb_substr($nameParts[count($nameParts) - 1], 0, 1));
} else {
    $myInitials = $myName !== '' ? mb_strtoupper(mb_substr($myName, 0, 2)) : '';
}

// Icons shared by the page and the scripts (<use href="#gm-i-...">)
$icons = [
    'gospel' => '<path d="M21 3H3c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h5v2h8v-2h5c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 14H3V5h18v12zM10 15l5.5-4.5L10 6v9z"/>',
    'church' => '<path d="M18 12.22V9l-5-2.5V5h2V3h-2V1h-2v2H9v2h2v1.5L6 9v3.22L2 14v8h8v-3c0-1.1.9-2 2-2s2 .9 2 2v3h8v-8l-4-1.78zm-6 1.28c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>',
    'shield' => '<path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z"/>',
    'spark'  => '<path d="M7 2v11h3v9l7-12h-4l4-8z"/>',
    'school' => '<path d="M5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82zM12 3L1 9l11 6 9-4.91V17h2V9L12 3z"/>',
    'globe'  => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>',
    'photo'  => '<path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>',
    'event'  => '<path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM9 10H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2z"/>',
    'eye'    => '<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>',
    'pin'    => '<path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>',
];

$jsT = [
    'edit' => __t('js_edit', $pageLang),
    'delete' => __t('js_delete', $pageLang),
    'cancel' => __t('js_cancel', $pageLang),
    'save' => __t('js_save', $pageLang),
    'saving' => __t('js_saving', $pageLang),
    'back' => __t('js_back', $pageLang),
    'error' => __t('js_error', $pageLang),
    'loading' => __t('js_loading', $pageLang),
    'post_actions' => __t('js_post_actions', $pageLang),
    'edit_post' => __t('js_edit_post', $pageLang),
    'confirm_delete' => __t('js_confirm_delete', $pageLang),
    'cannot_undo' => __t('js_cannot_undo', $pageLang),
    'deleting' => __t('js_deleting', $pageLang),
    'post' => __t('js_post', $pageLang),
    'posting' => __t('js_posting', $pageLang),
    'type_message' => __t('js_type_message', $pageLang),
    'error_posting' => __t('js_error_posting', $pageLang),
    'loading_posts' => __t('js_loading_posts', $pageLang),
    'no_posts' => __t('js_no_posts', $pageLang),
    'comments' => __t('js_comments', $pageLang),
    'write_comment' => __t('js_write_comment', $pageLang),
    'no_comments' => __t('js_no_comments', $pageLang),
    'delete_comment' => __t('js_delete_comment', $pageLang),
    'edit_comment' => __t('js_edit_comment', $pageLang),
    'add_photo' => __t('js_add_photo', $pageLang),
    'remove_photo' => __t('js_remove_photo', $pageLang),
    'delete_photo' => __t('js_delete_photo', $pageLang),
    'datetime' => __t('js_datetime', $pageLang),
    'place' => __t('js_place', $pageLang),
    'remove' => __t('js_remove', $pageLang),
    'reactions' => __t('js_reactions', $pageLang),
    'no_reactions' => __t('js_no_reactions', $pageLang),
    'all' => __t('js_all', $pageLang),
    'new_posts' => __t('js_new_posts', $pageLang),
    'add_to_calendar' => __t('js_add_to_calendar', $pageLang),
    'read_more' => __t('read_more', $pageLang),
    'read_less' => __t('read_less', $pageLang),
    'what_share' => __t('what_share', $pageLang),
    'close' => __t('close', $pageLang),
    'new_post' => __t('new_post', $pageLang),
    // Rooms menu translations
    'rooms' => __t('js_rooms', $pageLang),
    'manage_rooms' => __t('js_manage_rooms', $pageLang),
    'loading_rooms' => __t('js_loading_rooms', $pageLang),
    'no_rooms' => __t('js_no_rooms', $pageLang),
    'automatic' => __t('js_automatic', $pageLang),
    'auto' => __t('js_auto', $pageLang),
    'joined_section' => __t('js_joined_section', $pageLang),
    'joined_badge' => __t('js_joined_badge', $pageLang),
    'search' => __t('search', $pageLang),
    'unread' => __t('unread', $pageLang),
    'you_are_here' => __t('gm_you_are_here', $pageLang),
    'type_gemeente' => gm_type_label('gemeente', $pageLang),
    'type_opsienerskap' => gm_type_label('opsienerskap', $pageLang),
    'type_jeug' => gm_type_label('jeug', $pageLang),
    'type_sondagskool' => gm_type_label('sondagskool', $pageLang),
    'type_room' => gm_type_label('', $pageLang),
];
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?><!doctype html>
<html lang="<?= gm_e($pageLang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= gm_e($notice === null ? $roomTitle : t('gospel_media')) ?> - Gospel Media</title>

    <!-- Reveal-on-scroll only hides content once we know JS is running -->
    <script>document.documentElement.classList.add('gm-js');</script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500;1,600&family=Parisienne&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= gm_asset('/gospel_media/css/gospel.css') ?>">

    <script>
        window.PAGE_LANG = <?= json_encode($pageLang) ?>;
        window.CURRENT_ROOM_ID = <?= (int)$roomId ?>;
        window.CURRENT_ROOM_TYPE = <?= json_encode($roomType) ?>;
        window.CAN_POST = <?= $allowPost ? 'true' : 'false' ?>;
        window.USER_ID = <?= $userId ?>;
        window.USER_AMP_ID = <?= $ampId ?>;
        window.JS_T = <?= json_encode($jsT, $jsonFlags) ?: '{}' ?>;
    </script>
</head>
<body class="gospel-body gm-type-<?= $typeClass ?>">
    <?php require_once dirname(__DIR__) . '/header_footer/header.php'; ?>

    <!-- Icon sprite (used by the page and by gospel.js / roommenu.js) -->
    <svg class="gm-sprite" width="0" height="0" aria-hidden="true" focusable="false">
        <defs>
            <?php foreach ($icons as $id => $path): ?>
                <symbol id="gm-i-<?= $id ?>" viewBox="0 0 24 24" fill="currentColor"><?= $path ?></symbol>
            <?php endforeach; ?>
        </defs>
    </svg>

    <!-- Atmosphere: ruby light falling through glass (purely decorative) -->
    <div class="gm-aura" id="gmAura" aria-hidden="true">
        <div class="gm-rays"></div>
        <div class="gm-halo"></div>
        <div class="gm-embers" id="gmEmbers"></div>
    </div>

    <main class="gm-main" id="gmMain">

        <!-- Hero -->
        <section class="gm-hero" aria-labelledby="gmTitle">
            <p class="gm-eyebrow">
                <svg class="gm-eyebrow-icon" viewBox="0 0 24 24" aria-hidden="true"><use href="#gm-i-<?= $notice === null ? $typeIcon : 'gospel' ?>"/></svg>
                <span><?= gm_e($heroEyebrow) ?></span>
            </p>
            <h2 class="gm-title<?= $nameSize ?>" id="gmTitle"><span><?= gm_e($heroName) ?></span></h2>

            <?php if ($notice === null): ?>
            <?php if (!$allowPost): ?>
            <div class="gm-meta">
                <span class="gm-chip" title="<?= gm_e(t('no_posting_rights')) ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#gm-i-eye"/></svg>
                    <?= gm_e(t('gm_read_only')) ?>
                </span>
            </div>
            <?php endif; ?>

            <div class="gm-hero-actions">
                <?php if ($allowPost): ?>
                <button id="open-composer" class="gm-btn gm-btn--primary" type="button">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    <span><?= gm_e(t('new_post')) ?></span>
                </button>
                <?php endif; ?>
                <button id="open-rooms-menu" class="gm-btn gm-btn--glass" type="button" aria-haspopup="dialog">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true"><rect x="3.5" y="3.5" width="7" height="7" rx="2"/><rect x="13.5" y="3.5" width="7" height="7" rx="2"/><rect x="3.5" y="13.5" width="7" height="7" rx="2"/><rect x="13.5" y="13.5" width="7" height="7" rx="2"/></svg>
                    <span><?= gm_e(t('rooms')) ?></span>
                    <span class="gm-btn-badge" data-rooms-unread hidden></span>
                </button>
            </div>
            <?php endif; ?>
        </section>

        <?php if ($notice !== null): ?>

        <!-- Nothing to open: say why, and offer a way on -->
        <section class="gm-card gm-notice" role="alert">
            <span class="gm-notice-orb" aria-hidden="true">
                <svg viewBox="0 0 24 24"><use href="#gm-i-<?= $notice === 'no_room_access' ? 'shield' : 'church' ?>"/></svg>
            </span>
            <p class="gm-notice-text"><?= gm_e(t($notice)) ?></p>
            <div class="gm-notice-actions">
                <a class="gm-btn gm-btn--primary" href="/gospel_media/rooms.php"><span><?= gm_e(t('js_manage_rooms')) ?></span></a>
                <?php if ($notice === 'no_room_access'): ?>
                <a class="gm-btn gm-btn--glass" href="/gospel_media/gospel.php"><span><?= gm_e(t('back_to_media')) ?></span></a>
                <?php else: ?>
                <a class="gm-btn gm-btn--glass" href="/welcome.php"><span><?= gm_e(t('welcome')) ?></span></a>
                <?php endif; ?>
            </div>
        </section>

        <?php else: ?>

        <!-- Rooms: always in view, one tap to change -->
        <nav class="gm-rooms gm-reveal" aria-labelledby="gmRoomsLabel">
            <div class="gm-rooms-head">
                <h3 class="gm-label" id="gmRoomsLabel"><?= gm_e(t('rooms')) ?></h3>
                <a class="gm-rooms-manage" href="/gospel_media/rooms.php">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    <span><?= gm_e(t('js_manage_rooms')) ?></span>
                </a>
            </div>
            <div class="gm-rail-wrap">
                <div class="gm-rail" id="gmRail" aria-busy="true">
                    <!-- The current room is known now; the rest arrive from user_rooms.php -->
                    <a class="gm-room gm-room--<?= $typeClass ?> is-current" href="/gospel_media/gospel.php?room_id=<?= (int)$roomId ?>" aria-current="page" data-room-id="<?= (int)$roomId ?>">
                        <span class="gm-room-orb"><svg viewBox="0 0 24 24" aria-hidden="true"><use href="#gm-i-<?= $typeIcon ?>"/></svg></span>
                        <span class="gm-room-text">
                            <b><?= gm_e($heroName) ?></b>
                            <i><?= gm_e($heroEyebrow === 'Gospel Media' ? gm_type_label('', $pageLang) : $heroEyebrow) ?></i>
                        </span>
                    </a>
                    <span class="gm-room gm-room--skel" aria-hidden="true"></span>
                    <span class="gm-room gm-room--skel" aria-hidden="true"></span>
                </div>
            </div>
        </nav>

        <!-- Feed -->
        <section class="gm-feed-section" aria-labelledby="gmFeedLabel">
            <?php if ($allowPost): ?>
            <div class="gm-prompt gm-card gm-reveal">
                <button class="gm-prompt-main" type="button" data-composer-open="post">
                    <?php if ($myPhoto !== ''): ?>
                        <img class="gm-prompt-avatar" src="<?= gm_e($myPhoto) ?>" alt="" width="44" height="44" loading="lazy" decoding="async">
                    <?php else: ?>
                        <span class="gm-prompt-avatar gm-avatar-initials" aria-hidden="true"><?= gm_e($myInitials ?: '?') ?></span>
                    <?php endif; ?>
                    <span class="gm-prompt-text"><?= gm_e(t('what_share')) ?></span>
                </button>
                <button class="gm-prompt-tool gm-prompt-tool--photo" type="button" data-composer-open="photo" aria-label="<?= gm_e(t('js_add_photo')) ?>" title="<?= gm_e(t('js_add_photo')) ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#gm-i-photo"/></svg>
                </button>
                <?php if ($roomType !== 'gemeenskap'): ?>
                <button class="gm-prompt-tool gm-prompt-tool--event" type="button" data-composer-open="event" aria-label="<?= gm_e(t('js_datetime')) ?>" title="<?= gm_e(t('js_datetime')) ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><use href="#gm-i-event"/></svg>
                </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="gm-feed-head">
                <h3 class="gm-label" id="gmFeedLabel"><?= gm_e(t('posts')) ?></h3>
                <div class="gm-loading" id="loadingIndicator" hidden><span class="gm-spinner"></span></div>
            </div>

            <div id="feed" class="gm-feed" aria-busy="true">
                <div class="gm-skel-card" aria-hidden="true"><i></i><i></i><i></i></div>
                <div class="gm-skel-card" aria-hidden="true"><i></i><i></i><i></i></div>
                <p class="gm-sr-only"><?= gm_e(t('loading_posts')) ?></p>
            </div>
        </section>

        <?php endif; ?>
    </main>

    <div class="gm-flare" id="gmFlare" aria-hidden="true"></div>
    <div class="gm-toast" id="gmToast" role="status" aria-live="polite"></div>

    <?php if ($notice === null): ?>
    <script defer src="<?= gm_asset('/gospel_media/js/roommenu.js') ?>"></script>
    <script defer src="<?= gm_asset('/gospel_media/js/gospel.js') ?>"></script>
    <?php endif; ?>

    <?php require_once dirname(__DIR__) . '/header_footer/footer.php'; ?>
</body>
</html>
