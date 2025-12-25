<?php
// =====================================================================
// /diary/diary.php — AI-Enhanced Premium Diary
// =====================================================================

require_once __DIR__ . '/../security/auth_gate.php';

// -------------------------- Taal --------------------------------------
$pageLang = $_SESSION['language'] ?? 'af';
if (isset($_GET['lang']) && ($_GET['lang'] === 'af' || $_GET['lang'] === 'en')) {
  $_SESSION['language'] = $_GET['lang'];
  $pageLang = $_GET['lang'];
  header('Location: /diary/diary.php');
  exit;
}
function T(string $af, string $en): string { 
  global $pageLang; 
  return $pageLang === 'en' ? $en : $af; 
}

function esc($s) { 
  return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); 
}

$userId = (int)($_SESSION['user_id'] ?? 0);

// Get user info
$user = null;
try {
  $stmt = $pdo->prepare("SELECT name, surname FROM users WHERE id = ?");
  $stmt->execute([$userId]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  error_log("Diary user fetch error: " . $e->getMessage());
}

$userName = $user ? trim($user['name'] . ' ' . $user['surname']) : 'User';

?><!doctype html>
<html lang="<?= $pageLang === 'af' ? 'af' : 'en' ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= esc(T('AI Dagboek', 'AI Diary')) ?></title>
  
  <!-- Fonts -->
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
  
  <link rel="stylesheet" href="/diary/css/diary.css?v=<?= time() ?>">
</head>
<body class="diary-body">
  <?php require_once __DIR__ . '/../header_footer/header.php'; ?>

  <!-- Hero Section -->
  <div class="diary-hero">
    <div class="diary-hero-glow"></div>
    <div class="diary-hero-content">
      <h1 class="diary-hero-title"><?= esc(T('My Dagboek', 'My Diary')) ?></h1>
      <p class="diary-hero-subtitle"><?= esc(T('Bewaar jou gedagtes, drome en gebede', 'Preserve your thoughts, dreams and prayers')) ?></p>
    </div>
    <div class="diary-sparkles">
      <span class="diary-sparkle" style="--delay: 0s; --x: 15%; --y: 25%;"></span>
      <span class="diary-sparkle" style="--delay: 0.7s; --x: 80%; --y: 35%;"></span>
      <span class="diary-sparkle" style="--delay: 1.3s; --x: 45%; --y: 65%;"></span>
      <span class="diary-sparkle" style="--delay: 1.9s; --x: 25%; --y: 75%;"></span>
      <span class="diary-sparkle" style="--delay: 2.5s; --x: 85%; --y: 80%;"></span>
    </div>
  </div>

  <main class="diary-main">
    
    <!-- Quick Stats -->
    <section class="diary-stats">
      <div class="stat-card" data-stat="total">
        <div class="stat-icon">📝</div>
        <div class="stat-info">
          <div class="stat-value" id="statTotal">0</div>
          <div class="stat-label"><?= esc(T('Totale Inskrywings', 'Total Entries')) ?></div>
        </div>
      </div>
      <div class="stat-card" data-stat="month">
        <div class="stat-icon">📅</div>
        <div class="stat-info">
          <div class="stat-value" id="statMonth">0</div>
          <div class="stat-label"><?= esc(T('Hierdie Maand', 'This Month')) ?></div>
        </div>
      </div>
      <div class="stat-card" data-stat="streak">
        <div class="stat-icon">🔥</div>
        <div class="stat-info">
          <div class="stat-value" id="statStreak">0</div>
          <div class="stat-label"><?= esc(T('Dag Reeks', 'Day Streak')) ?></div>
        </div>
      </div>
      <div class="stat-card" data-stat="words">
        <div class="stat-icon">✍️</div>
        <div class="stat-info">
          <div class="stat-value" id="statWords">0</div>
          <div class="stat-label"><?= esc(T('Totale Woorde', 'Total Words')) ?></div>
        </div>
      </div>
    </section>

    <!-- View Toggle -->
    <section class="diary-view-toggle">
      <button class="view-btn active" data-view="timeline" title="<?= esc(T('Tydlyn', 'Timeline')) ?>">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M3 10h18M3 14h18M8 6h13M8 18h13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span><?= esc(T('Tydlyn', 'Timeline')) ?></span>
      </button>
      <button class="view-btn" data-view="gallery" title="<?= esc(T('Galery', 'Gallery')) ?>">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect x="3" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
          <rect x="14" y="3" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
          <rect x="3" y="14" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
          <rect x="14" y="14" width="7" height="7" rx="1" stroke="currentColor" stroke-width="2"/>
        </svg>
        <span><?= esc(T('Galery', 'Gallery')) ?></span>
      </button>
      <button class="view-btn" data-view="search" title="<?= esc(T('Soek', 'Search')) ?>">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
          <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <span><?= esc(T('Soek', 'Search')) ?></span>
      </button>
    </section>

    <!-- Quick Actions -->
    <section class="diary-actions">
      <button class="action-btn action-primary" id="newEntryBtn">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span><?= esc(T('Nuwe Inskrywing', 'New Entry')) ?></span>
      </button>
    </section>

    <!-- Timeline View -->
    <section class="diary-view diary-timeline" id="timelineView">
      <div class="timeline-filters">
        <input type="text" class="filter-input" id="timelineSearch" placeholder="<?= esc(T('Soek inskrywings...', 'Search entries...')) ?>">
        <select class="filter-select" id="timelineSort">
          <option value="newest"><?= esc(T('Nuutste Eerste', 'Newest First')) ?></option>
          <option value="oldest"><?= esc(T('Oudste Eerste', 'Oldest First')) ?></option>
          <option value="title"><?= esc(T('Titel A-Z', 'Title A-Z')) ?></option>
        </select>
        <select class="filter-select" id="timelineFilter">
          <option value="all"><?= esc(T('Alle', 'All')) ?></option>
          <option value="today"><?= esc(T('Vandag', 'Today')) ?></option>
          <option value="week"><?= esc(T('Hierdie Week', 'This Week')) ?></option>
          <option value="month"><?= esc(T('Hierdie Maand', 'This Month')) ?></option>
          <option value="year"><?= esc(T('Hierdie Jaar', 'This Year')) ?></option>
        </select>
      </div>
      <div class="timeline-container" id="timelineContainer">
        <div class="timeline-loading">
          <div class="loading-spinner"></div>
          <p><?= esc(T('Laai inskrywings...', 'Loading entries...')) ?></p>
        </div>
      </div>
    </section>

    <!-- Gallery View -->
    <section class="diary-view diary-gallery" id="galleryView" style="display: none;">
      <div class="gallery-grid" id="galleryGrid">
        <div class="gallery-loading">
          <div class="loading-spinner"></div>
          <p><?= esc(T('Laai galery...', 'Loading gallery...')) ?></p>
        </div>
      </div>
    </section>

    <!-- Search View -->
    <section class="diary-view diary-search" id="searchView" style="display: none;">
      <div class="search-panel">
        <div class="search-header">
          <input type="text" class="search-input" id="searchInput" placeholder="<?= esc(T('Soek deur al jou inskrywings...', 'Search through all your entries...')) ?>">
          <button class="search-btn" id="searchBtn">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
              <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </button>
        </div>
        <div class="search-filters">
          <label class="search-filter">
            <input type="checkbox" id="searchTitle" checked>
            <span><?= esc(T('Titels', 'Titles')) ?></span>
          </label>
          <label class="search-filter">
            <input type="checkbox" id="searchBody" checked>
            <span><?= esc(T('Inhoud', 'Content')) ?></span>
          </label>
          <label class="search-filter">
            <input type="checkbox" id="searchTags" checked>
            <span><?= esc(T('Etikette', 'Tags')) ?></span>
          </label>
        </div>
        <div class="search-results" id="searchResults">
          <div class="search-placeholder">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="2"/>
              <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <p><?= esc(T('Begin tik om te soek...', 'Start typing to search...')) ?></p>
          </div>
        </div>
      </div>
    </section>

  </main>

  <!-- Entry Modal -->
  <div class="modal" id="entryModal">
    <div class="modal-overlay" id="modalOverlay"></div>
    <div class="modal-container">
      <div class="modal-header">
        <h2 class="modal-title" id="modalTitle"><?= esc(T('Nuwe Inskrywing', 'New Entry')) ?></h2>
        <button class="modal-close" id="modalClose">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </button>
      </div>
      <div class="modal-body">
        <form id="entryForm">
          <input type="hidden" id="entryId" value="">
          
          <div class="form-group">
            <label class="form-label"><?= esc(T('Datum & Tyd', 'Date & Time')) ?></label>
            <div class="form-row">
              <input type="date" class="form-input" id="entryDate" required>
              <input type="time" class="form-input" id="entryTime" value="00:00">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="entryTitle"><?= esc(T('Titel', 'Title')) ?></label>
            <input type="text" class="form-input" id="entryTitle" placeholder="<?= esc(T('My gedagtes vir vandag...', 'My thoughts for today...')) ?>">
          </div>

          <div class="form-group">
            <label class="form-label" for="entryBody"><?= esc(T('Inhoud', 'Content')) ?></label>
            <div class="editor-toolbar">
              <button type="button" class="editor-btn" data-action="bold" title="Bold">
                <strong>B</strong>
              </button>
              <button type="button" class="editor-btn" data-action="italic" title="Italic">
                <em>I</em>
              </button>
              <button type="button" class="editor-btn" data-action="underline" title="Underline">
                <u>U</u>
              </button>
              <button type="button" class="editor-btn" data-action="strikethrough" title="Strikethrough">
                <s>S</s>
              </button>
              <span class="editor-divider"></span>
              <button type="button" class="editor-btn" data-action="heading" title="Heading">
                <span>H1</span>
              </button>
              <button type="button" class="editor-btn" data-action="bulletList" title="Bullet List">
                <span>•</span>
              </button>
              <button type="button" class="editor-btn" data-action="numberedList" title="Numbered List">
                <span>1.</span>
              </button>
              <span class="editor-divider"></span>
              <button type="button" class="editor-btn" data-action="quote" title="Quote">
                <span>"</span>
              </button>
              <button type="button" class="editor-btn" data-action="code" title="Code">
                <span>&lt;/&gt;</span>
              </button>
            </div>
            <textarea class="form-textarea" id="entryBody" rows="10" placeholder="<?= esc(T('Skryf jou gedagtes hier...', 'Write your thoughts here...')) ?>"></textarea>
            <div class="word-count" id="wordCount">0 <?= esc(T('woorde', 'words')) ?></div>
          </div>

          <div class="form-group">
            <label class="form-label"><?= esc(T('Etikette', 'Tags')) ?></label>
            <div class="tags-container" id="tagsContainer"></div>
            <input type="text" class="form-input" id="tagInput" placeholder="<?= esc(T('Tik etiket en druk Enter...', 'Type tag and press Enter...')) ?>">
          </div>

          <div class="form-group">
            <label class="form-label"><?= esc(T('Gemoed', 'Mood')) ?></label>
            <div class="mood-selector">
              <button type="button" class="mood-btn" data-mood="happy">😊</button>
              <button type="button" class="mood-btn" data-mood="sad">😢</button>
              <button type="button" class="mood-btn" data-mood="excited">🤗</button>
              <button type="button" class="mood-btn" data-mood="calm">😌</button>
              <button type="button" class="mood-btn" data-mood="thoughtful">🤔</button>
              <button type="button" class="mood-btn" data-mood="grateful">🙏</button>
              <button type="button" class="mood-btn" data-mood="inspired">✨</button>
              <button type="button" class="mood-btn" data-mood="tired">😴</button>
            </div>
            <input type="hidden" id="entryMood">
          </div>

          <div class="form-group">
            <label class="form-label"><?= esc(T('Weer', 'Weather')) ?></label>
            <div class="weather-selector">
              <button type="button" class="weather-btn" data-weather="sunny">☀️</button>
              <button type="button" class="weather-btn" data-weather="cloudy">☁️</button>
              <button type="button" class="weather-btn" data-weather="rainy">🌧️</button>
              <button type="button" class="weather-btn" data-weather="stormy">⛈️</button>
              <button type="button" class="weather-btn" data-weather="snowy">❄️</button>
              <button type="button" class="weather-btn" data-weather="windy">🌬️</button>
            </div>
            <input type="hidden" id="entryWeather">
          </div>

          <div class="form-group">
            <label class="form-label"><?= esc(T('Herinnering', 'Reminder')) ?></label>
            <select class="form-select" id="reminderMinutes">
              <option value="0"><?= esc(T('Geen herinnering', 'No reminder')) ?></option>
              <option value="15">15 <?= esc(T('minute voor', 'minutes before')) ?></option>
              <option value="30">30 <?= esc(T('minute voor', 'minutes before')) ?></option>
              <option value="60" selected>1 <?= esc(T('uur voor', 'hour before')) ?></option>
              <option value="120">2 <?= esc(T('ure voor', 'hours before')) ?></option>
              <option value="1440">1 <?= esc(T('dag voor', 'day before')) ?></option>
            </select>
          </div>

          <div class="form-group">
            <label class="custom-checkbox">
              <input type="checkbox" id="addToCalendar" checked>
              <span class="checkmark"></span>
              <span><?= esc(T('Voeg by kalender', 'Add to calendar')) ?> 📅</span>
            </label>
          </div>

          <div class="form-actions">
            <button type="button" class="btn btn-secondary" id="cancelBtn">
              <?= esc(T('Kanselleer', 'Cancel')) ?>
            </button>
            <button type="button" class="btn btn-ai" id="aiAssistBtn">
              <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" stroke="currentColor" stroke-width="2" fill="none"/>
              </svg>
              <?= esc(T('AI Hulp', 'AI Assist')) ?>
            </button>
            <button type="submit" class="btn btn-primary" id="saveBtn">
              <?= esc(T('Stoor', 'Save')) ?>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Share Modal -->
  <div class="modal" id="shareModal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-container modal-sm">
      <div class="modal-header">
        <h2 class="modal-title"><?= esc(T('Deel Inskrywing', 'Share Entry')) ?></h2>
        <button class="modal-close" onclick="closeShareModal()">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </button>
      </div>
      <div class="modal-body">
        <div class="share-options">
          <button class="share-option" onclick="shareAs('friend')">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <span><?= esc(T('Deel met Vriend', 'Share with Friend')) ?></span>
          </button>
          <button class="share-option" onclick="shareAs('link')">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <span><?= esc(T('Kopieer Skakel', 'Copy Link')) ?></span>
          </button>
          <button class="share-option" onclick="shareAs('export')">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5m0 0L7 8m5-5v12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <span><?= esc(T('Voer Uit as PDF', 'Export as PDF')) ?></span>
          </button>
        </div>
      </div>
    </div>
  </div>

  <script>
    window.DIARY_LANG = '<?= $pageLang ?>';
    window.USER_ID = <?= $userId ?>;
  </script>
  <script src="/diary/js/diary.js?v=<?= time() ?>"></script>

  <?php require_once __DIR__ . '/../header_footer/footer.php'; ?>

</body>
</html>