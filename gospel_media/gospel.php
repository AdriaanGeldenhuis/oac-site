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
    header('Location: /gospel_media/gospel.php?room_id=' . ($_GET['room_id'] ?? ''));
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

// Get room info
function gm_label($type, $name) {
    $t = strtolower((string)$type);
    $n = (string)$name;
    if ($t === 'gemeente') return stripos($n, 'gemeente') === 0 ? $n : 'Gemeente ' . $n;
    if ($t === 'opsienerskap') return stripos($n, 'opsienerskap') === 0 ? $n : 'Opsienerskap ' . $n;
    if ($t === 'jeug') return stripos($n, 'jeug') === 0 ? $n : 'Jeug ' . $n;
    if ($t === 'sondagskool') return stripos($n, 'sondagskool') === 0 ? $n : 'Sondagskool ' . $n;
    return $n ?: 'Kamer';
}

$roomTitle = 'Kamer';
$roomType = '';
$roomObj = null;

if ($roomId > 0) {
    $rs = $pdo->prepare("SELECT * FROM rooms WHERE id=?");
    $rs->execute([$roomId]);
    $roomObj = $rs->fetch(PDO::FETCH_ASSOC);
    if ($roomObj) {
        $roomTitle = gm_label($roomObj['type'] ?? '', $roomObj['name'] ?? '');
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

$VER = time();
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
    </script>
</head>
<body class="gospel-body">
    <?php require_once dirname(__DIR__) . '/header_footer/header.php'; ?>
    
    <!-- Hero Section -->
    <div class="gm-hero">
        <div class="gm-hero-glow"></div>
        <div class="gm-hero-content">
            <h1 class="gm-hero-title"><?= htmlspecialchars($roomTitle) ?></h1>
            <button id="open-rooms-menu" class="gm-rooms-btn" type="button">
                <svg class="gm-btn-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M3 12h18M3 6h18M3 18h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
                <span><?= t('rooms') ?></span>
            </button>
        </div>
    </div>
    
    <main class="gm-main">
        <!-- Composer Section -->
        <?php if ($allowPost): ?>
        <section class="gm-section gm-composer-section">
            <div class="gm-section-header">
                <div class="gm-icon-wrapper">
                    <svg class="gm-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 4v16m8-8H4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </div>
                <h2 class="gm-section-title"><?= t('new_post') ?></h2>
            </div>
            
            <div class="composer-tabs">
                <button id="composer-type-post" class="composer-tab active" type="button">
                    <?= t('post') ?>
                </button>
                <button id="composer-type-event" class="composer-tab" type="button">
                    <?= t('event') ?>
                </button>
            </div>
            
            <div class="composer-body">
                <textarea id="composer-text" class="composer-textarea" 
                          placeholder="<?= t('what_share') ?>"></textarea>
                
                <div id="composer-event-fields" class="composer-event-fields hide">
                    <input id="composer-event-at" type="datetime-local" 
                           class="composer-input" 
                           placeholder="<?= t('date_time_short') ?>" />
                    <input id="composer-event-place" type="text" 
                           class="composer-input" 
                           placeholder="<?= t('place') ?>" />
                </div>
                
                <input id="composer-image" type="file" accept="image/*" multiple style="display:none;" />
                <div id="composer-preview" class="composer-preview"></div>
                
                <div class="composer-actions">
                    <button id="btn-choose-image" type="button" class="composer-attach">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48" 
                                  stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span><?= t('photo') ?></span>
                    </button>
                    <button id="composer-submit" type="button" class="composer-submit">
                        <span class="sb-btn-shine"></span>
                        <svg class="icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span><?= t('post') ?></span>
                    </button>
                </div>
            </div>
        </section>
        <?php else: ?>
        <section class="gm-section gm-info-section">
            <div class="gm-info-content">
                <svg class="gm-info-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.5"/>
                    <path d="M12 16v.01M12 8v5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
                <p><?= t('no_posting_rights') ?></p>
            </div>
        </section>
        <?php endif; ?>
        
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