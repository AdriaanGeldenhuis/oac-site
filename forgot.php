<?php
declare(strict_types=1);
require_once __DIR__ . '/security/headers.php';
require_once __DIR__ . '/security/config.php';
require_once __DIR__ . '/security/session.php';
require_once __DIR__ . '/security/csrf.php';
require_once __DIR__ . '/security/rate_limit.php';
require_once __DIR__ . '/includes/email.php';

$errors = [];
$success = false;

// Create password_resets table if not exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token (token),
        INDEX idx_expires (expires_at)
    )");
} catch (PDOException $e) {
    // Table might already exist, ignore
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!rate_limit_check('forgot:' . $ip, 3, 30)) {
        $errors[] = 'Too many attempts. Please try again in 30 minutes.';
    }

    if (!csrf_verify($_POST['csrf'] ?? '')) {
        $errors[] = 'Invalid session. Please try again.';
    }

    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (!$errors) {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id, name, email, status FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && $user['status'] === 'approved') {
            // Delete any existing tokens for this user
            $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$user['id']]);

            // Generate secure token
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Store token
            $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$user['id'], $token, $expiresAt]);

            // Build reset link
            $resetLink = "https://oacapp.co.za/reset.php?token={$token}";

            // Send email via SMTP
            sendPasswordResetEmail($user['email'], $user['name'], $resetLink);

            rate_limit_reset('forgot:' . $ip);
        }

        // Always show success message (don't reveal if email exists)
        $success = true;
    }
}

$csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Forgot Password</title>
<link rel="stylesheet" href="/assets/css/login.css">
</head>
<body>
<div class="login-container">
  <div class="logo-container">
    <img src="/assets/logo/bible.webp" alt="Logo">
  </div>

  <h1>Reset Password</h1>

  <?php if ($success): ?>
    <div class="success">
      <p>If an account exists with that email address, a password reset link has been sent.</p>
      <p style="margin-top: 15px; font-size: 0.9rem; color: #c0c0c0;">Please check your email inbox (and spam folder) for the reset link.</p>
      <p style="margin-top: 10px; font-size: 0.85rem; color: #ffc107;">The link will expire in 1 hour.</p>
    </div>
    <p><a href="/login.php">Back to Login</a></p>
  <?php else: ?>
    <?php if ($errors): ?>
      <div class="errors">
        <?php foreach ($errors as $e): ?>
          <p><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

      <label>Email Address<br><input type="email" name="email" required autofocus></label>

      <button type="submit">Send Reset Link</button>
    </form>

    <p><a href="/login.php">Back to Login</a> | <a href="/register.php">Create Account</a></p>
  <?php endif; ?>
</div>
</body>
</html>
