<?php
declare(strict_types=1);
require_once __DIR__ . '/../security/auth_gate.php';
require_once __DIR__ . '/../includes/languages.php';

$pageLang = $_SESSION['language'] ?? 'af';
if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS, true)) {
    $_SESSION['language'] = $pageLang = $_GET['lang'];
}

// Translation helper using central 5-language system
function t(string $key): string {
    global $pageLang;
    return __t($key, $pageLang);
}

function esc($s) { 
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); 
}

$meId = (int)($_SESSION['user_id'] ?? 0);
$targetId = isset($_GET['u']) && is_numeric($_GET['u']) ? (int)$_GET['u'] : $meId;

if ($targetId <= 0) {
    header('Location: /calendar/calendar.php');
    exit;
}

// Load target user
$stmt = $pdo->prepare("SELECT u.id, u.name, u.surname, u.amp_id, u.town_id, u.congregation_id, u.photo, u.gender,
    CASE WHEN u.gender='vrou' THEN a.female_name ELSE a.male_name END AS amp_name
    FROM users u
    LEFT JOIN amptes a ON a.id = u.amp_id
    WHERE u.id = ? LIMIT 1");
$stmt->execute([$targetId]);
$targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$targetUser) {
    header('Location: /calendar/calendar.php');
    exit;
}

// Load my permissions
$stmt = $pdo->prepare("SELECT amp_id, town_id, congregation_id FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$meId]);
$me = $stmt->fetch(PDO::FETCH_ASSOC);

$myAmpId = (int)($me['amp_id'] ?? 10);
$myTownId = (int)($me['town_id'] ?? 0);
$myCongId = (int)($me['congregation_id'] ?? 0);

$targetAmpId = (int)($targetUser['amp_id'] ?? 10);
$targetTownId = (int)($targetUser['town_id'] ?? 0);
$targetCongId = (int)($targetUser['congregation_id'] ?? 0);

// Check permission
$canView = false;
$canExport = false;

if ($meId === $targetId) {
    $canView = true;
    $canExport = ($myAmpId >= 1 && $myAmpId <= 9);
} elseif ($myAmpId < $targetAmpId) {
    // Higher amp viewing lower
    if ($myAmpId >= 6) {
        $canView = ($myCongId === $targetCongId);
    } elseif ($myAmpId >= 2) {
        $canView = ($myTownId === $targetTownId);
    } else {
        $canView = true;
    }
    $canExport = ($myAmpId >= 1 && $myAmpId <= 9);
} elseif ($myAmpId <= 5 && $myTownId === $targetTownId) {
    // Same town
    $canView = true;
    $canExport = true;
}

if (!$canView) {
    http_response_code(403);
    die('Access denied / Toegang geweier');
}

$fullName = trim($targetUser['name'] . ' ' . $targetUser['surname']);
$avatar = !empty($targetUser['photo']) ? $targetUser['photo'] : '/assets/img/avatar-default.png';

$VER = time();
?><!doctype html>
<html lang="<?= $pageLang ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc(t('calendar')) ?> - <?= esc($fullName) ?></title>
    
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
    
    <style>
    /* Scoped user header styles */
    .calendar-body .cal-user-header {
      display: flex;
      align-items: center;
      gap: 25px;
      padding: 25px 30px;
      background: linear-gradient(135deg, rgba(26, 26, 26, 0.95) 0%, rgba(42, 42, 42, 0.85) 100%);
      border: 1px solid rgba(183, 110, 121, 0.3);
      border-radius: 16px;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.8), 0 0 20px rgba(183, 110, 121, 0.4);
      margin-bottom: 30px;
      position: relative;
      overflow: hidden;
      z-index: 10;
    }

    .calendar-body .cal-user-header::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent 0%, rgba(192, 192, 192, 0.1) 50%, transparent 100%);
      animation: header-shine 3s ease-in-out infinite;
      pointer-events: none;
    }

    @keyframes header-shine {
      0% { left: -100%; }
      50%, 100% { left: 100%; }
    }

    .calendar-body .cal-user-avatar {
      flex-shrink: 0;
      width: 100px;
      height: 100px;
      border-radius: 50%;
      border: 3px solid #b76e79;
      overflow: hidden;
      box-shadow: 0 4px 16px rgba(0, 0, 0, 0.7), 0 0 20px rgba(183, 110, 121, 0.4);
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
      z-index: 2;
    }

    .calendar-body .cal-user-avatar:hover {
      transform: scale(1.05);
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.8), 0 0 30px rgba(183, 110, 121, 0.6);
    }

    .calendar-body .cal-user-avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
      border-radius: 50%;
    }

    .calendar-body .cal-user-info {
      flex: 1;
      position: relative;
      z-index: 2;
    }

    .calendar-body .cal-user-name {
      font-family: 'Parisienne', cursive;
      font-size: clamp(1.8rem, 4vw, 2.5rem);
      color: #b76e79;
      text-shadow: 0 0 20px rgba(183, 110, 121, 0.4);
      margin: 0 0 8px 0;
      letter-spacing: 1px;
      line-height: 1.2;
    }

    .calendar-body .cal-user-amp {
      font-size: 1.1rem;
      color: #f3c3b1;
      opacity: 0.9;
      font-weight: 300;
      margin: 0;
    }

    .calendar-body .cal-user-actions {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      position: relative;
      z-index: 2;
    }

    .calendar-body .cal-user-actions .cal-btn {
      padding: 12px 20px;
      background: linear-gradient(135deg, #2a2a2a 0%, #1a1a1a 100%);
      border: 1px solid #8a8a8a;
      border-radius: 12px;
      color: #f3c3b1;
      font-size: 14px;
      font-weight: 300;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      position: relative;
      overflow: hidden;
    }

    .calendar-body .cal-user-actions .cal-btn::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent 0%, rgba(192, 192, 192, 0.2) 50%, transparent 100%);
      transition: left 0.6s;
      pointer-events: none;
    }

    .calendar-body .cal-user-actions .cal-btn:hover::before {
      left: 100%;
    }

    .calendar-body .cal-user-actions .cal-btn:hover {
      border-color: #b76e79;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.8), 0 0 20px rgba(183, 110, 121, 0.4);
      transform: translateY(-2px);
    }

    .calendar-body .cal-user-actions .cal-btn svg {
      width: 18px;
      height: 18px;
      stroke: currentColor;
      flex-shrink: 0;
    }

    .calendar-body .cal-btn-export {
      background: linear-gradient(135deg, rgba(76, 175, 80, 0.3) 0%, rgba(76, 175, 80, 0.5) 100%);
      border-color: #4caf50;
      color: #4caf50;
    }

    .calendar-body .cal-btn-export:hover {
      background: linear-gradient(135deg, rgba(76, 175, 80, 0.5) 0%, rgba(76, 175, 80, 0.7) 100%);
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.8), 0 0 20px rgba(76, 175, 80, 0.6);
      border-color: #66bb6a;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .calendar-body .cal-user-header {
        flex-direction: column;
        text-align: center;
        padding: 20px;
      }

      .calendar-body .cal-user-avatar {
        width: 80px;
        height: 80px;
      }

      .calendar-body .cal-user-name {
        font-size: 1.8rem;
      }

      .calendar-body .cal-user-amp {
        font-size: 1rem;
      }

      .calendar-body .cal-user-actions {
        width: 100%;
        flex-direction: column;
      }

      .calendar-body .cal-user-actions .cal-btn {
        width: 100%;
        justify-content: center;
      }
    }

    @media (max-width: 480px) {
      .calendar-body .cal-user-header {
        padding: 15px;
      }

      .calendar-body .cal-user-avatar {
        width: 70px;
        height: 70px;
      }

      .calendar-body .cal-user-name {
        font-size: 1.5rem;
      }

      .calendar-body .cal-user-amp {
        font-size: 0.95rem;
      }
    }

    /* Toast notification */
    .cal-toast {
      position: fixed;
      bottom: 30px;
      right: 30px;
      padding: 15px 25px;
      background: linear-gradient(135deg, #1a1a1a 0%, #2a2a2a 100%);
      border: 1px solid #8a8a8a;
      border-radius: 12px;
      color: #f3c3b1;
      font-size: 15px;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.8);
      z-index: 11000;
      opacity: 0;
      transform: translateX(100px);
      transition: all 0.3s ease;
      pointer-events: none;
    }

    .cal-toast.show {
      opacity: 1;
      transform: translateX(0);
    }

    .cal-toast-success {
      border-color: #4caf50;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.8), 0 0 20px rgba(76, 175, 80, 0.4);
    }

    .cal-toast-error {
      border-color: #e76f51;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.8), 0 0 20px rgba(231, 111, 81, 0.4);
    }

    .cal-toast-info {
      border-color: #2196f3;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.8), 0 0 20px rgba(33, 150, 243, 0.4);
    }
    </style>
</head>
<body class="calendar-body">
    <?php require_once __DIR__ . '/../header_footer/header.php'; ?>

    <main class="cal-main">
        <!-- User Header -->
        <section class="cal-user-header">
            <div class="cal-user-avatar">
                <img src="<?= esc($avatar) ?>" alt="<?= esc($fullName) ?>">
            </div>
            <div class="cal-user-info">
                <h1 class="cal-user-name"><?= esc($fullName) ?></h1>
                <?php if (!empty($targetUser['amp_name'])): ?>
                <p class="cal-user-amp"><?= esc($targetUser['amp_name']) ?></p>
                <?php endif; ?>
            </div>
            <div class="cal-user-actions">
                <a href="/profile/?u=<?= $targetId ?>" class="cal-btn">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 11a4 4 0 100-8 4 4 0 000 8z" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    <?= esc(t('profile')) ?>
                </a>
                
                <?php if ($canExport): ?>
                <button type="button" class="cal-btn cal-btn-export" onclick="exportToPDF()">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" stroke="currentColor" stroke-width="2"/>
                        <path d="M14 2v6h6M16 13H8M16 17H8M10 9H8" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    <?= esc(t('pdf')) ?>
                </button>
                
                <button type="button" class="cal-btn cal-btn-export" onclick="exportToExcel()">
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z" stroke="currentColor" stroke-width="2"/>
                        <path d="M14 2v6h6M8 13h2l2 4 2-4h2" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    <?= esc(t('excel')) ?>
                </button>
                <?php endif; ?>
            </div>
        </section>

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

    <script>
        window.LANG = '<?= $pageLang ?>';
        window.TARGET_USER_ID = <?= $targetId ?>;
        window.USER_ID = <?= $meId ?>;
        window.VIEW_ONLY = <?= $meId === $targetId ? 'false' : 'true' ?>;
        window.T = {
            day: '<?= esc(t('day')) ?>',
            week: '<?= esc(t('week')) ?>',
            month: '<?= esc(t('month')) ?>',
            today: '<?= esc(t('today')) ?>',
            noEvents: '<?= esc(t('no_events')) ?>',
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
    <script src="/calendar/js/view.js?v=<?= $VER ?>"></script>

    <?php require_once __DIR__ . '/../header_footer/footer.php'; ?>
</body>
</html>