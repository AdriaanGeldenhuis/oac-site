<?php
declare(strict_types=1);

require_once __DIR__ . '/headers.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/session_guard.php';

auth_require();
session_guard_enforce();