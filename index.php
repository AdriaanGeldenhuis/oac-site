<?php
require_once __DIR__ . '/security/headers.php';
require_once __DIR__ . '/security/config.php';
require_once __DIR__ . '/security/session.php';
require_once __DIR__ . '/security/auth.php';

if (auth_logged_in()) {
    header('Location: /welcome.php');
    exit;
}
header('Location: /login.php');
exit;