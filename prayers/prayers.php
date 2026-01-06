<?php

require_once __DIR__ . '/../security/auth_gate.php';
require_once __DIR__ . '/../includes/languages.php';

global $pdo;
if (!isset($pdo)) {
  die('Database connection not available');
}

$pageLang = $_SESSION['language'] ?? 'af';
if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS, true)) {
  $_SESSION['language'] = $_GET['lang'];
  $pageLang = $_GET['lang'];
  header('Location: /prayers/prayers.php');
  exit;
}

// T() for backwards compat: AF gets Afrikaans, all others get English
function T(string $af, string $en): string {
  global $pageLang;
  return $pageLang === 'af' ? $af : $en;
}

$currentUserId = $_SESSION['user_id'] ?? 0;

function esc($s) { 
  return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); 
}

$stmt = $pdo->prepare("SELECT name, surname, photo FROM users WHERE id = ?");
$stmt->execute([$currentUserId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$currentUser = [
  'username' => trim(($user['name'] ?? '') . ' ' . ($user['surname'] ?? '')) ?: 'User',
  'profile_picture' => $user['photo'] ?? '/assets/default-avatar.png'
];

?><!doctype html>
<html lang="<?= $pageLang === 'af' ? 'af' : 'en' ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= esc(T('Gebede & Getuienisse', 'Prayers & Testimonies')) ?></title>
  
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
  
  <link rel="stylesheet" href="/prayers/css/prayers.css?v=<?= time() ?>">
</head>
<body class="prayers-body">
  <?php require_once __DIR__ . '/../header_footer/header.php'; ?>

  <div class="pr-hero">
    <div class="pr-hero-glow"></div>
    <div class="pr-hero-content">
      <h1 class="pr-hero-title"><?= esc(T('Gebede & Getuienisse', 'Prayers & Testimonies')) ?></h1>
      <p class="pr-hero-subtitle"><?= esc(T('Deel jou hart, bid saam, vier saam', 'Share your heart, pray together, celebrate together')) ?></p>
    </div>
    <div class="pr-sparkles">
      <span class="pr-sparkle" style="--x: 20%; --y: 30%; --delay: 0s;"></span>
      <span class="pr-sparkle" style="--x: 80%; --y: 20%; --delay: 1s;"></span>
      <span class="pr-sparkle" style="--x: 50%; --y: 70%; --delay: 2s;"></span>
      <span class="pr-sparkle" style="--x: 10%; --y: 80%; --delay: 1.5s;"></span>
      <span class="pr-sparkle" style="--x: 90%; --y: 60%; --delay: 0.5s;"></span>
    </div>
  </div>

  <main class="pr-main">
    <section class="pr-section pr-create-section">
      <div class="pr-section-header">
        <div class="pr-icon-wrapper">
          <svg class="pr-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </div>
        <h2 class="pr-section-title"><?= esc(T('Deel jou gebedsnood of getuienis', 'Share your prayer need or testimony')) ?></h2>
      </div>

      <form id="createPostForm" class="pr-form" enctype="multipart/form-data">
        <div class="pr-tabs">
          <button type="button" class="pr-tab active" data-kind="prayer">
            <svg class="pr-tab-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-13h2v6h-2zm0 8h2v2h-2z" fill="currentColor" opacity="0.3"/>
            </svg>
            <span><?= esc(T('Gebed', 'Prayer')) ?></span>
          </button>
          <button type="button" class="pr-tab" data-kind="testimony">
            <svg class="pr-tab-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" fill="currentColor" opacity="0.3"/>
            </svg>
            <span><?= esc(T('Getuienis', 'Testimony')) ?></span>
          </button>
        </div>

        <textarea 
          id="postText" 
          name="text" 
          class="pr-textarea" 
          placeholder="<?= esc(T('Deel jou hart hier...', 'Share your heart here...')) ?>" 
          rows="4" 
          required
        ></textarea>

        <div class="pr-form-row">
          <div class="pr-file-input-wrapper">
            <input type="file" id="photoInput" name="photo" accept="image/*" class="pr-file-input">
            <label for="photoInput" class="pr-file-label">
              <svg class="pr-file-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z" fill="currentColor" opacity="0.3"/>
              </svg>
              <span id="fileLabel"><?= esc(T('Kies foto (opsioneel)', 'Choose photo (optional)')) ?></span>
            </label>
          </div>
          <button type="submit" class="pr-btn pr-btn-primary" id="submitBtn">
            <span class="pr-btn-shine"></span>
            <svg class="pr-btn-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span class="pr-btn-text"><?= esc(T('Deel', 'Share')) ?></span>
          </button>
        </div>
      </form>
    </section>

    <!-- FILTER SECTION REMOVED -->

    <section class="pr-section pr-feed-section">
      <div id="postsContainer" class="pr-posts-grid">
        <div class="pr-loading">
          <div class="pr-spinner"></div>
          <p><?= esc(T('Laai gebede...', 'Loading prayers...')) ?></p>
        </div>
      </div>
    </section>
  </main>

  <div id="commentsModal" class="pr-modal" hidden>
    <div class="pr-modal-overlay"></div>
    <div class="pr-modal-content">
      <div class="pr-modal-header">
        <h3 class="pr-modal-title"><?= esc(T('Kommentaar', 'Comments')) ?></h3>
        <button class="pr-modal-close" id="closeModal" type="button">
          <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M18 6L6 18M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </button>
      </div>
      <div class="pr-modal-body">
        <div id="commentsContainer" class="pr-comments-list"></div>
        <form id="addCommentForm" class="pr-comment-form">
          <input type="hidden" id="commentPostId" name="post_id">
          <textarea 
            id="commentText" 
            name="text" 
            class="pr-comment-input" 
            placeholder="<?= esc(T('Skryf \'n bemoedigende woord...', 'Write an encouraging word...')) ?>" 
            rows="2" 
            required
          ></textarea>
          <button type="submit" class="pr-btn pr-btn-sm pr-btn-primary">
            <span class="pr-btn-shine"></span>
            <span class="pr-btn-text"><?= esc(T('Stuur', 'Send')) ?></span>
          </button>
        </form>
      </div>
    </div>
  </div>

  <script>
    window.PRAYERS_LANG = <?= json_encode($pageLang) ?>;
    window.CURRENT_USER_ID = <?= (int)$currentUserId ?>;
    window.CURRENT_USER_NAME = <?= json_encode($currentUser['username']) ?>;
    window.CURRENT_USER_PIC = <?= json_encode($currentUser['profile_picture']) ?>;
  </script>
  <script src="/prayers/js/prayers.js?v=<?= time() ?>"></script>

  <?php require_once __DIR__ . '/../header_footer/footer.php'; ?>
</body>
</html>