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

// Translation helper using central 5-language system
function t(string $key): string {
  global $pageLang;
  return __t($key, $pageLang);
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
<html lang="<?= $pageLang ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= esc(t('prayers_testimonies')) ?></title>
  
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
  
<?php $VER = '2.1.0'; ?>
  <link rel="stylesheet" href="/prayers/css/prayers.css?v=<?= $VER ?>">
</head>
<body class="prayers-body">
  <?php require_once __DIR__ . '/../header_footer/header.php'; ?>

  <div class="pr-hero">
    <div class="pr-hero-glow"></div>
    <div class="pr-hero-content">
      <h1 class="pr-hero-title"><?= esc(t('prayers_testimonies')) ?></h1>
      <p class="pr-hero-subtitle"><?= esc(t('prayers_subtitle')) ?></p>
      <div class="pr-hero-actions">
        <button id="open-composer" class="pr-hero-btn" type="button">
          <svg class="pr-btn-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 4v16m8-8H4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          <span><?= esc(t('share_prayer_testimony')) ?></span>
        </button>
      </div>
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
    <!-- FILTER SECTION REMOVED -->

    <section class="pr-section pr-feed-section">
      <div id="postsContainer" class="pr-posts-grid">
        <div class="pr-loading">
          <div class="pr-spinner"></div>
          <p><?= esc(t('loading_prayers')) ?></p>
        </div>
      </div>
    </section>
  </main>

  <div id="commentsModal" class="pr-modal" hidden>
    <div class="pr-modal-overlay"></div>
    <div class="pr-modal-content">
      <div class="pr-modal-header">
        <h3 class="pr-modal-title"><?= esc(t('comments')) ?></h3>
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
            placeholder="<?= esc(t('write_encouraging_word')) ?>" 
            rows="2" 
            required
          ></textarea>
          <button type="submit" class="pr-btn pr-btn-sm pr-btn-primary">
            <span class="pr-btn-shine"></span>
            <span class="pr-btn-text"><?= esc(t('send')) ?></span>
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
    window.JS_T = {
      edit: <?= json_encode(__t('js_edit', $pageLang)) ?>,
      delete: <?= json_encode(__t('js_delete', $pageLang)) ?>,
      cancel: <?= json_encode(__t('js_cancel', $pageLang)) ?>,
      error: <?= json_encode(__t('js_error', $pageLang)) ?>,
      share: <?= json_encode(__t('js_share', $pageLang)) ?>,
      update: <?= json_encode(__t('js_update', $pageLang)) ?>,
      posting: <?= json_encode(__t('js_posting', $pageLang)) ?>,
      choose_photo: <?= json_encode(__t('js_choose_photo', $pageLang)) ?>,
      prayer_shared: <?= json_encode(__t('js_prayer_shared', $pageLang)) ?>,
      could_not_post: <?= json_encode(__t('js_could_not_post', $pageLang)) ?>,
      post_updated: <?= json_encode(__t('js_post_updated', $pageLang)) ?>,
      could_not_update: <?= json_encode(__t('js_could_not_update', $pageLang)) ?>,
      remove_photo: <?= json_encode(__t('js_remove_photo', $pageLang)) ?>,
      delete_photo: <?= json_encode(__t('js_delete_photo', $pageLang)) ?>,
      photo_removed: <?= json_encode(__t('js_photo_removed', $pageLang)) ?>,
      could_not_remove: <?= json_encode(__t('js_could_not_remove', $pageLang)) ?>,
      delete_post: <?= json_encode(__t('js_delete_post', $pageLang)) ?>,
      post_deleted: <?= json_encode(__t('js_post_deleted', $pageLang)) ?>,
      could_not_delete: <?= json_encode(__t('js_could_not_delete', $pageLang)) ?>,
      loading_prayers: <?= json_encode(__t('js_loading_prayers', $pageLang)) ?>,
      no_prayers: <?= json_encode(__t('js_no_prayers', $pageLang)) ?>,
      could_not_load: <?= json_encode(__t('js_could_not_load', $pageLang)) ?>,
      prayer: <?= json_encode(__t('js_prayer', $pageLang)) ?>,
      testimony: <?= json_encode(__t('js_testimony', $pageLang)) ?>,
      comment_posted: <?= json_encode(__t('js_comment_posted', $pageLang)) ?>,
      comment_updated: <?= json_encode(__t('js_comment_updated', $pageLang)) ?>,
      comment_deleted: <?= json_encode(__t('js_comment_deleted', $pageLang)) ?>,
      delete_comment: <?= json_encode(__t('js_delete_comment', $pageLang)) ?>,
      no_comments: <?= json_encode(__t('js_no_comments', $pageLang)) ?>
    };
  </script>
  <script src="/prayers/js/prayers.js?v=<?= $VER ?>"></script>

  <?php require_once __DIR__ . '/../header_footer/footer.php'; ?>
</body>
</html>