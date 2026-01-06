<?php
declare(strict_types=1);
/**
 * AI Slimbybel Calendar - Complete Rewrite
 * Gospel-style UI with full permissions
 */

require_once __DIR__ . '/../security/auth_gate.php';
require_once __DIR__ . '/../includes/languages.php';

$pageLang = $_SESSION['language'] ?? 'af';
if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS, true)) {
    $_SESSION['language'] = $pageLang = $_GET['lang'];
    header('Location: /calendar/calendar.php');
    exit;
}

// Translation helper using central 5-language system
function t(string $key): string {
    global $pageLang;
    return __t($key, $pageLang);
}

function esc($s) { 
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); 
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    die('Database connection failed');
}

$userId = (int)$_SESSION['user_id'];

// Get user info
$userStmt = $pdo->prepare("SELECT amp_id, town_id, congregation_id, name, surname FROM users WHERE id = ?");
$userStmt->execute([$userId]);
$userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);

if (!$userInfo) {
    die('User not found');
}

$ampId = (int)($userInfo['amp_id'] ?? 10);
$userTown = (int)($userInfo['town_id'] ?? 0);
$userCong = (int)($userInfo['congregation_id'] ?? 0);
$userName = trim($userInfo['name'] . ' ' . $userInfo['surname']);

$VER = time();
?><!doctype html>
<html lang="<?= $pageLang ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc(t('ai_calendar')) ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <style>
        @font-face {
            font-family: 'Parisienne';
            src: url('/assets/fonts/Parisienne-Regular.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }
    </style>
    
    <link rel="stylesheet" href="/calendar/css/calendar.css?v=<?= $VER ?>">
</head>
<body class="calendar-body">
    <?php require_once __DIR__ . '/../header_footer/header.php'; ?>

    <main class="cal-main">
        <!-- View Switcher & Navigation -->
        <section class="cal-controls">
            <div class="cal-view-switcher">
                <button class="cal-view-btn" data-view="day">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    <span><?= esc(t('day')) ?></span>
                </button>
                
                <button class="cal-view-btn active" data-view="week">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
                        <line x1="10" y1="10" x2="10" y2="22" stroke="currentColor"/>
                        <line x1="17" y1="10" x2="17" y2="22" stroke="currentColor"/>
                    </svg>
                    <span><?= esc(t('week')) ?></span>
                </button>
                
                <button class="cal-view-btn" data-view="month">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
                        <line x1="3" y1="10" x2="21" y2="10" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    <span><?= esc(t('month')) ?></span>
                </button>
            </div>
            
            <div class="cal-nav">
                <button class="cal-nav-btn" id="prevBtn" type="button">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2"/>
                    </svg>
                </button>
                <button class="cal-nav-btn" id="todayBtn" type="button"><?= esc(t('today')) ?></button>
                <button class="cal-nav-btn" id="nextBtn" type="button">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M9 18l6-6-6-6" stroke="currentColor" stroke-width="2"/>
                    </svg>
                </button>
            </div>
        </section>

        <!-- Calendar Section -->
        <section class="cal-section">
            <div class="cal-section-header">
                <h2 class="cal-section-title" id="calTitle"><?= esc(t('calendar')) ?></h2>
            </div>
            
            <div class="cal-views">
                <!-- Day View -->
                <div class="cal-view-container" id="dayView" style="display:none;">
                    <div class="cal-day-grid" id="dayGrid"></div>
                </div>

                <!-- Week View -->
                <div class="cal-view-container" id="weekView">
                    <div class="cal-week-grid" id="weekGrid"></div>
                </div>

                <!-- Month View -->
                <div class="cal-view-container" id="monthView" style="display:none;">
                    <div class="cal-month-weekdays">
                        <div><?= esc(t('sun')) ?></div>
                        <div><?= esc(t('mon')) ?></div>
                        <div><?= esc(t('tue')) ?></div>
                        <div><?= esc(t('wed')) ?></div>
                        <div><?= esc(t('thu')) ?></div>
                        <div><?= esc(t('fri')) ?></div>
                        <div><?= esc(t('sat')) ?></div>
                    </div>
                    <div class="cal-month-grid" id="monthGrid"></div>
                </div>
            </div>
        </section>
    </main>

    <!-- Quick Action Menu -->
    <div class="cal-quick-menu" id="quickMenu" style="display:none;">
        <div class="cal-quick-overlay" id="quickOverlay"></div>
        <div class="cal-quick-panel">
            <h3 class="cal-quick-title"><?= esc(t('create_new')) ?></h3>
            <button class="cal-quick-btn" data-action="gebeurtenis" type="button">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="2"/>
                </svg>
                <span><?= esc(t('event')) ?></span>
            </button>
            <button class="cal-quick-btn" data-action="dagboek" type="button">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 20h9M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z" stroke="currentColor" stroke-width="2"/>
                </svg>
                <span><?= esc(t('diary')) ?></span>
            </button>
            <button class="cal-quick-btn" data-action="afspraak" type="button">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8z" stroke="currentColor" stroke-width="2"/>
                </svg>
                <span><?= esc(t('appointment')) ?></span>
            </button>
        </div>
    </div>

    <!-- Event Modal -->
    <div class="cal-modal" id="eventModal" style="display:none;">
        <div class="cal-modal-overlay" id="modalOverlay"></div>
        <div class="cal-modal-content">
            <div class="cal-modal-header">
                <h3 class="cal-modal-title" id="modalTitle"><?= esc(t('event')) ?></h3>
                <button class="cal-modal-close" id="closeModal" type="button">&times;</button>
            </div>
            
            <div class="cal-modal-body">
                <form id="eventForm">
                    <input type="hidden" id="eventId" name="id">
                    <input type="hidden" id="eventType" name="type" value="event">
                    
                    <div class="cal-form-group">
                        <label class="cal-form-label"><?= esc(t('title')) ?></label>
                        <input type="text" id="eventTitle" name="title" class="cal-form-input" required>
                    </div>

                    <div class="cal-form-row">
                        <div class="cal-form-group">
                            <label class="cal-form-label"><?= esc(t('date')) ?></label>
                            <input type="date" id="eventDate" name="date" class="cal-form-input" required>
                        </div>
                        <div class="cal-form-group">
                            <label class="cal-form-label"><?= esc(t('time')) ?></label>
                            <input type="time" id="eventTime" name="time" class="cal-form-input">
                        </div>
                    </div>

                    <!-- Room selector (gebeurtenis) -->
                    <div class="cal-form-group" id="roomGroup" style="display:none;">
                        <label class="cal-form-label"><?= esc(t('room')) ?></label>
                        <select id="eventRoom" name="room_id" class="cal-form-select">
                            <option value=""><?= esc(t('select_room')) ?></option>
                        </select>
                    </div>

                    <!-- User selector (afspraak) -->
                    <div class="cal-form-group" id="userGroup" style="display:none;">
                        <label class="cal-form-label"><?= esc(t('with_whom')) ?></label>
                        
                        <div class="cal-person-toggle">
                            <button type="button" class="cal-toggle-btn active" data-mode="user">
                                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                <?= esc(t('user')) ?>
                            </button>
                            <button type="button" class="cal-toggle-btn" data-mode="text">
                                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 20h9M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z" stroke="currentColor" stroke-width="2"/>
                                </svg>
                                <?= esc(t('name')) ?>
                            </button>
                        </div>

                        <div id="userSelect">
                            <select id="linkedUser" name="linked_user_id" class="cal-form-select">
                                <option value=""><?= esc(t('select_user')) ?></option>
                            </select>
                        </div>

                        <div id="userTextInput" style="display:none;">
                            <input type="text" id="personName" name="person_name" class="cal-form-input" placeholder="<?= esc(t('type_name')) ?>">
                        </div>
                    </div>

                    <div class="cal-form-group" id="locationGroup" style="display:none;">
                        <label class="cal-form-label"><?= esc(t('location')) ?></label>
                        <input type="text" id="eventLocation" name="location" class="cal-form-input">
                    </div>

                    <div class="cal-form-group">
                        <label class="cal-form-label"><?= esc(t('notes')) ?></label>
                        <textarea id="eventNotes" name="notes" class="cal-form-textarea" rows="4"></textarea>
                    </div>

                    <div class="cal-form-group" id="shareGroup">
                        <label class="cal-checkbox-label">
                            <input type="checkbox" id="shareWithSpouse" name="share_with_spouse" value="1">
                            <span><?= esc(t('share_with_spouse')) ?></span>
                        </label>
                    </div>

                    <div class="cal-modal-actions">
                        <button type="button" class="cal-btn cal-btn-secondary" id="cancelBtn"><?= esc(t('cancel')) ?></button>
                        <button type="button" class="cal-btn cal-btn-danger" id="deleteBtn" style="display:none;"><?= esc(t('delete')) ?></button>
                        <button type="submit" class="cal-btn cal-btn-primary">
                            <span class="cal-btn-shine"></span>
                            <?= esc(t('save')) ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="cal-toast-container" id="toastContainer"></div>

    <script>
        window.LANG = '<?= $pageLang ?>';
        window.USER_ID = <?= $userId ?>;
        window.USER_AMP_ID = <?= $ampId ?>;
        window.USER_TOWN = <?= $userTown ?>;
        window.USER_CONG = <?= $userCong ?>;
        window.T = {
            day: '<?= esc(t('day')) ?>',
            week: '<?= esc(t('week')) ?>',
            month: '<?= esc(t('month')) ?>',
            today: '<?= esc(t('today')) ?>',
            noEvents: '<?= esc(t('no_events')) ?>',
            gebeurtenis: '<?= esc(t('event')) ?>',
            dagboek: '<?= esc(t('diary')) ?>',
            afspraak: '<?= esc(t('appointment')) ?>',
            edit: '<?= esc(t('edit')) ?>',
            delete: '<?= esc(t('delete')) ?>',
            confirmDelete: '<?= esc(t('confirm_delete')) ?>',
            saved: '<?= esc(t('saved_success')) ?>',
            deleted: '<?= esc(t('deleted_success')) ?>',
            error: '<?= esc(t('error')) ?>',
            dayNames: <?= $pageLang === 'af' 
                ? '["Sondag","Maandag","Dinsdag","Woensdag","Donderdag","Vrydag","Saterdag"]'
                : '["Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"]'
            ?>,
            dayNamesShort: <?= $pageLang === 'af' 
                ? '["So","Ma","Di","Wo","Do","Vr","Sa"]'
                : '["Sun","Mon","Tue","Wed","Thu","Fri","Sat"]'
            ?>,
            monthNames: <?= $pageLang === 'af' 
                ? '["Januarie","Februarie","Maart","April","Mei","Junie","Julie","Augustus","September","Oktober","November","Desember"]'
                : '["January","February","March","April","May","June","July","August","September","October","November","December"]'
            ?>
        };
    </script>
    <script src="/calendar/js/calendar.js?v=<?= $VER ?>"></script>

    <?php require_once __DIR__ . '/../header_footer/footer.php'; ?>
</body>
</html>