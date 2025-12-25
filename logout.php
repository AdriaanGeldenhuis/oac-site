<?php
declare(strict_types=1);
require_once __DIR__ . '/security/config.php';
require_once __DIR__ . '/security/session.php';

// Clear DB session key
if (!empty($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("UPDATE users SET web_session_key = NULL WHERE id = ?");
        $stmt->execute([(int)$_SESSION['user_id']]);
    } catch (Throwable $e) {
        error_log('Logout DB update failed: ' . $e->getMessage());
    }
}

// Destroy session
$_SESSION = [];

// Delete session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax'
        ]
    );
}

session_destroy();

header('Location: /login.php');
exit;