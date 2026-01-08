<?php
declare(strict_types=1);
require_once __DIR__ . '/security/headers.php';
require_once __DIR__ . '/security/config.php';
require_once __DIR__ . '/security/session.php';
require_once __DIR__ . '/security/csrf.php';
require_once __DIR__ . '/security/rate_limit.php';

$errors = [];
$success = false;
$validToken = false;
$token = $_GET['token'] ?? $_POST['token'] ?? '';

// Validate token
if (!empty($token)) {
    $stmt = $pdo->prepare("
        SELECT pr.*, u.email, u.name
        FROM password_resets pr
        JOIN users u ON u.id = pr.user_id
        WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $resetData = $stmt->fetch();

    if ($resetData) {
        $validToken = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!rate_limit_check('reset:' . $ip, 5, 15)) {
        $errors[] = 'Too many attempts. Please try again in 15 minutes.';
    }

    if (!csrf_verify($_POST['csrf'] ?? '')) {
        $errors[] = 'Invalid session. Please try again.';
    }

    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    } elseif ($password !== $password2) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        // Update password
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$hash, $resetData['user_id']]);

        // Mark token as used
        $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
        $stmt->execute([$resetData['id']]);

        // Clear all sessions for this user (force re-login)
        $stmt = $pdo->prepare("UPDATE users SET web_session_key = NULL WHERE id = ?");
        $stmt->execute([$resetData['user_id']]);

        $success = true;
        rate_limit_reset('reset:' . $ip);
    }
}

$csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reset Password</title>
<link rel="stylesheet" href="/assets/css/login.css">
</head>
<body>
<div class="login-container">
  <div class="logo-container">
    <img src="/assets/logo/bible.webp" alt="Logo">
  </div>

  <h1>New Password</h1>

  <?php if ($success): ?>
    <div class="success">
      <p>Your password has been reset successfully!</p>
      <p>You can now log in with your new password.</p>
    </div>
    <p style="text-align: center; margin-top: 20px;"><a href="/login.php">Go to Login</a></p>

  <?php elseif (!$validToken): ?>
    <div class="errors">
      <p>This password reset link is invalid or has expired.</p>
      <p>Please request a new reset link.</p>
    </div>
    <p style="text-align: center; margin-top: 20px;"><a href="/forgot.php">Request New Link</a> | <a href="/login.php">Back to Login</a></p>

  <?php else: ?>
    <?php if ($errors): ?>
      <div class="errors">
        <?php foreach ($errors as $e): ?>
          <p><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <p style="text-align: center; color: var(--color-text-muted); margin-bottom: 20px;">
      Enter a new password for <strong><?= htmlspecialchars($resetData['email']) ?></strong>
    </p>

    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">

      <div class="password-wrapper">
        <label>
          New Password
          <span class="toggle-pw" onclick="togglePw('pw')">&#128065;</span>
        </label>
        <input type="password" id="pw" name="password" required minlength="8">
      </div>

      <div class="password-wrapper">
        <label>
          Confirm Password
          <span class="toggle-pw" onclick="togglePw('pw2')">&#128065;</span>
        </label>
        <input type="password" id="pw2" name="password2" required minlength="8">
      </div>

      <button type="submit">Reset Password</button>
    </form>

    <p><a href="/login.php">Back to Login</a></p>
  <?php endif; ?>
</div>

<script>
function togglePw(id) {
  const pw = document.getElementById(id);
  pw.type = pw.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
