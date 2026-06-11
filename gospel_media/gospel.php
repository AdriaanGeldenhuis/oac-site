<?php
declare(strict_types=1);
/**
 * Gospel Media - Glossy Black with Rose Gold & Peach Theme
 * Completely rebuilt with AI Slimbybel styling
 */

require_once dirname(__DIR__) . '/security/auth_gate.php';
require_once dirname(__DIR__) . '/includes/languages.php';

// Language
$pageLang = $_SESSION['language'] ?? 'af';
if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS, true)) {
    $_SESSION['language'] = $pageLang = $_GET['lang'];
    header('Location: /gospel_media/gospel.php?room_id=' . (int)($_GET['room_id'] ?? 0));
    exit;
}
// Translation helper using central 5-language system
function t(string $key): string {
    global $pageLang;
    return __t($key, $pageLang);
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    die('Database connection failed');
}

$userId = (int)$_SESSION['user_id'];
$roomId = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;

// Get user info
$userStmt = $pdo->prepare("SELECT amp_id, town_id, congregation_id FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);
$ampId = (int)($userInfo['amp_id'] ?? 999);
$userTown = (int)($userInfo['town_id'] ?? 0);
$userCong = (int)($userInfo['congregation_id'] ?? 0);

// Default to first available room if none specified
if ($roomId <= 0) {
    // Try to get user's own gemeente first
    if ($userCong > 0) {
        $q = $pdo->prepare("SELECT id FROM rooms WHERE type = 'gemeente' AND gemeente_id = ? LIMIT 1");
        $q->execute([$userCong]);
        $row = $q->fetch();
        if ($row) {
            $roomId = (int)$row['id'];
        }
    }
    
    // If still no room, get any accessible room
    if ($roomId <= 0 && $userTown > 0) {
        $q = $pdo->prepare("SELECT id FROM rooms WHERE town_id = ? AND type != 'gemeenskap' ORDER BY 
                           FIELD(type, 'gemeente', 'opsienerskap', 'jeug', 'sondagskool') LIMIT 1");
        $q->execute([$userTown]);
        $row = $q->fetch();
        if ($row) {
            $roomId = (int)$row['id'];
        }
    }
    
    if ($roomId > 0) {
        header('Location: /gospel_media/gospel.php?room_id=' . $roomId);
        exit;
    } else {
        die(t('no_rooms_available'));
    }
}

// Get room info with translation support
function gm_label($type, $name, $lang) {
    $t = strtolower((string)$type);
    $n = (string)$name;
    // Strip existing Afrikaans type prefix from room name to avoid duplication
    $afPrefixes = ['gemeente' => 'Gemeente ', 'opsienerskap' => 'Opsienerskap ', 'jeug' => 'Jeug ', 'sondagskool' => 'Sondagskool '];
    if (isset($afPrefixes[$t]) && stripos($n, $afPrefixes[$t]) === 0) {
        $n = substr($n, strlen($afPrefixes[$t]));
    }
    if ($t === 'gemeente') return __t('congregation', $lang) . ' ' . $n;
    if ($t === 'opsienerskap') return __t('oversight', $lang) . ' ' . $n;
    if ($t === 'jeug') return __t('youth', $lang) . ' ' . $n;
    if ($t === 'sondagskool') return __t('sunday_school', $lang) . ' ' . $n;
    return $n ?: __t('room', $lang);
}

$roomTitle = __t('room', $pageLang);
$roomType = '';
$roomObj = null;

if ($roomId > 0) {
    $rs = $pdo->prepare("SELECT * FROM rooms WHERE id=?");
    $rs->execute([$roomId]);
    $roomObj = $rs->fetch(PDO::FETCH_ASSOC);
    if ($roomObj) {
        $roomTitle = gm_label($roomObj['type'] ?? '', $roomObj['name'] ?? '', $pageLang);
        $roomType = strtolower((string)($roomObj['type'] ?? ''));
    }
}

// Check access
require_once __DIR__ . '/lib/permissions.php';
$hasAccess = $roomObj ? user_has_access_to_room($pdo, $userId, $roomObj) : false;
if (!$hasAccess) {
    die(t('no_room_access'));
}

// Check posting rights
$allowPost = $roomObj ? user_can_post_in_room($pdo, $userId, $roomObj) : false;

$VER = '2.2.0';
?><!doctype html>
<html lang="<?= $pageLang ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($roomTitle) ?> - Gospel Media</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <style>
        @font-face {
            font-family: 'Parisienne';
            src: url('/assets/fonts/Parisienne-Regular.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }
    </style>
    
    <link rel="stylesheet" href="/gospel_media/css/gospel.css?v=<?= $VER ?>">
    
    <script>
        window.PAGE_LANG = <?= json_encode($pageLang) ?>;
        window.CURRENT_ROOM_ID = <?= $roomId ?>;
        window.CURRENT_ROOM_TYPE = <?= json_encode($roomType) ?>;
        window.CAN_POST = <?= $allowPost ? 'true' : 'false' ?>;
        window.USER_ID = <?= $userId ?>;
        window.USER_AMP_ID = <?= $ampId ?>;
        window.JS_T = {
            edit: <?= json_encode(__t('js_edit', $pageLang)) ?>,
            delete: <?= json_encode(__t('js_delete', $pageLang)) ?>,
            cancel: <?= json_encode(__t('js_cancel', $pageLang)) ?>,
            save: <?= json_encode(__t('js_save', $pageLang)) ?>,
            saving: <?= json_encode(__t('js_saving', $pageLang)) ?>,
            back: <?= json_encode(__t('js_back', $pageLang)) ?>,
            error: <?= json_encode(__t('js_error', $pageLang)) ?>,
            loading: <?= json_encode(__t('js_loading', $pageLang)) ?>,
            post_actions: <?= json_encode(__t('js_post_actions', $pageLang)) ?>,
            edit_post: <?= json_encode(__t('js_edit_post', $pageLang)) ?>,
            confirm_delete: <?= json_encode(__t('js_confirm_delete', $pageLang)) ?>,
            cannot_undo: <?= json_encode(__t('js_cannot_undo', $pageLang)) ?>,
            deleting: <?= json_encode(__t('js_deleting', $pageLang)) ?>,
            post: <?= json_encode(__t('js_post', $pageLang)) ?>,
            posting: <?= json_encode(__t('js_posting', $pageLang)) ?>,
            type_message: <?= json_encode(__t('js_type_message', $pageLang)) ?>,
            error_posting: <?= json_encode(__t('js_error_posting', $pageLang)) ?>,
            loading_posts: <?= json_encode(__t('js_loading_posts', $pageLang)) ?>,
            no_posts: <?= json_encode(__t('js_no_posts', $pageLang)) ?>,
            comments: <?= json_encode(__t('js_comments', $pageLang)) ?>,
            write_comment: <?= json_encode(__t('js_write_comment', $pageLang)) ?>,
            no_comments: <?= json_encode(__t('js_no_comments', $pageLang)) ?>,
            delete_comment: <?= json_encode(__t('js_delete_comment', $pageLang)) ?>,
            edit_comment: <?= json_encode(__t('js_edit_comment', $pageLang)) ?>,
            add_photo: <?= json_encode(__t('js_add_photo', $pageLang)) ?>,
            remove_photo: <?= json_encode(__t('js_remove_photo', $pageLang)) ?>,
            delete_photo: <?= json_encode(__t('js_delete_photo', $pageLang)) ?>,
            datetime: <?= json_encode(__t('js_datetime', $pageLang)) ?>,
            place: <?= json_encode(__t('js_place', $pageLang)) ?>,
            remove: <?= json_encode(__t('js_remove', $pageLang)) ?>,
            // Rooms menu translations
            rooms: <?= json_encode(__t('js_rooms', $pageLang)) ?>,
            manage_rooms: <?= json_encode(__t('js_manage_rooms', $pageLang)) ?>,
            loading_rooms: <?= json_encode(__t('js_loading_rooms', $pageLang)) ?>,
            no_rooms: <?= json_encode(__t('js_no_rooms', $pageLang)) ?>,
            automatic: <?= json_encode(__t('js_automatic', $pageLang)) ?>,
            auto: <?= json_encode(__t('js_auto', $pageLang)) ?>,
            joined_section: <?= json_encode(__t('js_joined_section', $pageLang)) ?>,
            joined_badge: <?= json_encode(__t('js_joined_badge', $pageLang)) ?>
        };
    </script>
</head>
<body class="gospel-body">
    <?php require_once dirname(__DIR__) . '/header_footer/header.php'; ?>
    
    <!-- Hero Section -->
    <div class="gm-hero">
        <div class="gm-hero-glow"></div>
        <div class="gm-hero-content">
            <h1 class="gm-hero-title"><?= htmlspecialchars($roomTitle) ?></h1>
            <div class="gm-hero-actions">
                <?php if ($allowPost): ?>
                <button id="open-composer" class="gm-rooms-btn" type="button">
                    <svg class="gm-btn-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 4v16m8-8H4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <span><?= t('new_post') ?></span>
                </button>
                <?php endif; ?>
                <button id="open-rooms-menu" class="gm-rooms-btn" type="button">
                    <svg class="gm-btn-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3 12h18M3 6h18M3 18h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <span><?= t('rooms') ?></span>
                </button>
            </div>
        </div>
    </div>
    
    <main class="gm-main">
        <!-- Feed Section -->
        <section class="gm-section gm-feed-section">
            <div class="gm-section-header">
                <div class="gm-icon-wrapper">
                    <svg class="gm-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="3" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="1.5"/>
                        <rect x="14" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="1.5"/>
                        <rect x="3" y="14" width="7" height="7" rx="1" stroke="currentColor" stroke-width="1.5"/>
                        <rect x="14" y="14" width="7" height="7" rx="1" stroke="currentColor" stroke-width="1.5"/>
                    </svg>
                </div>
                <h2 class="gm-section-title"><?= t('posts') ?></h2>
                <div class="gm-loading" id="loadingIndicator" hidden>
                    <div class="sb-spinner"></div>
                </div>
            </div>
            
            <div id="feed" class="gm-feed">
                <div class="gm-placeholder">
                    <svg class="gm-placeholder-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" fill="currentColor" opacity="0.3"/>
                    </svg>
                    <p><?= t('loading_posts') ?></p>
                </div>
            </div>
        </section>
    </main>
    
    <script defer src="/gospel_media/js/roommenu.js?v=<?= $VER ?>"></script>
    <script defer src="/gospel_media/js/gospel.js?v=<?= $VER ?>"></script>
    
    <?php require_once dirname(__DIR__) . '/header_footer/footer.php'; ?>
</body>
</html>