<?php
// =====================================================================
// /policy/index.php — Public Privacy Policy (no auth required)
// =====================================================================
declare(strict_types=1);

require_once __DIR__ . '/../security/headers.php';
require_once __DIR__ . '/../includes/languages.php';

// Language handling — session-free, uses query param or defaults to English
$pageLang = 'en';
if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS, true)) {
    $pageLang = $_GET['lang'];
} elseif (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['language'])
    && in_array($_SESSION['language'], SUPPORTED_LANGS, true)) {
    $pageLang = $_SESSION['language'];
}

function t(string $key): string {
    global $pageLang;
    return __t($key, $pageLang);
}
?>
<!doctype html>
<html lang="<?= $pageLang ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars(t('privacy_policy'), ENT_QUOTES, 'UTF-8') ?> — Old Apostolic Church</title>
<?php require_once __DIR__ . '/../header_footer/header.php'; ?>
<style>
.policy-wrap {
  max-width: 800px;
  margin: 30px auto;
  padding: 25px 20px;
  color: var(--ghf-text);
  font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
  line-height: 1.7;
}
.policy-wrap h1 {
  font-family: 'Parisienne', cursive;
  color: var(--ghf-text-rose);
  text-shadow: 0 0 15px rgba(183, 110, 121, 0.4);
  font-size: clamp(1.8rem, 5vw, 2.5rem);
  text-align: center;
  margin-bottom: 30px;
}
.policy-wrap h2 {
  color: var(--ghf-text-rose);
  font-size: 1.15rem;
  margin-top: 28px;
  margin-bottom: 8px;
  letter-spacing: 0.5px;
}
.policy-wrap p,
.policy-wrap ul {
  font-size: 0.95rem;
  margin: 8px 0;
}
.policy-wrap ul {
  padding-left: 22px;
}
.policy-wrap a {
  color: var(--ghf-text-rose);
}
.policy-date {
  text-align: center;
  font-size: 0.85rem;
  color: var(--ghf-silver-dark);
  margin-bottom: 20px;
}
</style>
</head>
<body>
<div class="policy-wrap">
  <h1><?= htmlspecialchars(t('privacy_policy'), ENT_QUOTES, 'UTF-8') ?></h1>
  <p class="policy-date"><?= htmlspecialchars(t('policy_effective_date'), ENT_QUOTES, 'UTF-8') ?>: 2025-01-01</p>

  <h2>1. <?= htmlspecialchars(t('policy_info_collect'), ENT_QUOTES, 'UTF-8') ?></h2>
  <p><?= htmlspecialchars(t('policy_info_collect_desc'), ENT_QUOTES, 'UTF-8') ?></p>
  <ul>
    <li><?= htmlspecialchars(t('policy_info_name_email'), ENT_QUOTES, 'UTF-8') ?></li>
    <li><?= htmlspecialchars(t('policy_info_congregation'), ENT_QUOTES, 'UTF-8') ?></li>
    <li><?= htmlspecialchars(t('policy_info_usage'), ENT_QUOTES, 'UTF-8') ?></li>
  </ul>

  <h2>2. <?= htmlspecialchars(t('policy_how_use'), ENT_QUOTES, 'UTF-8') ?></h2>
  <p><?= htmlspecialchars(t('policy_how_use_desc'), ENT_QUOTES, 'UTF-8') ?></p>
  <ul>
    <li><?= htmlspecialchars(t('policy_use_account'), ENT_QUOTES, 'UTF-8') ?></li>
    <li><?= htmlspecialchars(t('policy_use_experience'), ENT_QUOTES, 'UTF-8') ?></li>
    <li><?= htmlspecialchars(t('policy_use_notify'), ENT_QUOTES, 'UTF-8') ?></li>
  </ul>

  <h2>3. <?= htmlspecialchars(t('policy_data_storage'), ENT_QUOTES, 'UTF-8') ?></h2>
  <p><?= htmlspecialchars(t('policy_data_storage_desc'), ENT_QUOTES, 'UTF-8') ?></p>

  <h2>4. <?= htmlspecialchars(t('policy_sharing'), ENT_QUOTES, 'UTF-8') ?></h2>
  <p><?= htmlspecialchars(t('policy_sharing_desc'), ENT_QUOTES, 'UTF-8') ?></p>

  <h2>5. <?= htmlspecialchars(t('policy_cookies'), ENT_QUOTES, 'UTF-8') ?></h2>
  <p><?= htmlspecialchars(t('policy_cookies_desc'), ENT_QUOTES, 'UTF-8') ?></p>

  <h2>6. <?= htmlspecialchars(t('policy_rights'), ENT_QUOTES, 'UTF-8') ?></h2>
  <p><?= htmlspecialchars(t('policy_rights_desc'), ENT_QUOTES, 'UTF-8') ?></p>
  <ul>
    <li><?= htmlspecialchars(t('policy_rights_access'), ENT_QUOTES, 'UTF-8') ?></li>
    <li><?= htmlspecialchars(t('policy_rights_correct'), ENT_QUOTES, 'UTF-8') ?></li>
    <li><?= htmlspecialchars(t('policy_rights_delete'), ENT_QUOTES, 'UTF-8') ?></li>
  </ul>

  <h2>7. <?= htmlspecialchars(t('policy_contact'), ENT_QUOTES, 'UTF-8') ?></h2>
  <p><?= htmlspecialchars(t('policy_contact_desc'), ENT_QUOTES, 'UTF-8') ?></p>
</div>
<?php require_once __DIR__ . '/../header_footer/footer.php'; ?>
</body>
</html>
