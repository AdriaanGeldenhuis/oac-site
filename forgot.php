<?php
declare(strict_types=1);
require_once __DIR__ . '/security/headers.php';
require_once __DIR__ . '/security/config.php';
require_once __DIR__ . '/security/session.php';
require_once __DIR__ . '/security/csrf.php';
require_once __DIR__ . '/security/rate_limit.php';

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

/**
 * Send password reset email
 */
function sendResetEmail(string $toEmail, string $userName, string $resetLink): bool {
    $subject = "OAC - Password Reset Request";

    $htmlBody = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 10px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .header { text-align: center; margin-bottom: 30px; }
            .header h1 { color: #b76e79; margin: 0; font-family: Georgia, serif; }
            .content { color: #333; line-height: 1.6; }
            .button { display: inline-block; background: #b76e79; color: #fff; padding: 12px 30px; text-decoration: none; border-radius: 8px; margin: 20px 0; }
            .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #888; text-align: center; }
            .warning { color: #e67e22; font-size: 13px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>OAC App</h1>
            </div>
            <div class='content'>
                <p>Dear {$userName},</p>
                <p>We received a request to reset your password. Click the button below to create a new password:</p>
                <p style='text-align: center;'>
                    <a href='{$resetLink}' class='button'>Reset Password</a>
                </p>
                <p>Or copy and paste this link into your browser:</p>
                <p style='word-break: break-all; font-size: 13px; color: #666;'>{$resetLink}</p>
                <p class='warning'>This link will expire in 1 hour.</p>
                <p>If you did not request a password reset, please ignore this email. Your password will remain unchanged.</p>
            </div>
            <div class='footer'>
                <p>This is an automated message from OAC App. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>";

    $plainBody = "Dear {$userName},\n\n";
    $plainBody .= "We received a request to reset your password.\n\n";
    $plainBody .= "Click the link below to reset your password:\n";
    $plainBody .= "{$resetLink}\n\n";
    $plainBody .= "This link will expire in 1 hour.\n\n";
    $plainBody .= "If you did not request a password reset, please ignore this email.\n\n";
    $plainBody .= "- OAC App";

    // Email headers
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: OAC App <noreply@oacapp.co.za>',
        'Reply-To: noreply@oacapp.co.za',
        'X-Mailer: PHP/' . phpversion()
    ];

    // Try to send email
    $sent = @mail($toEmail, $subject, $htmlBody, implode("\r\n", $headers));

    // Log for debugging
    if (!$sent) {
        error_log("Failed to send password reset email to: {$toEmail}");
    } else {
        error_log("Password reset email sent to: {$toEmail}");
    }

    return $sent;
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

            // Send email
            sendResetEmail($user['email'], $user['name'], $resetLink);

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
