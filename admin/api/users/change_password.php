<?php
declare(strict_types=1);
/**
 * POST /admin/api/users/change_password.php   (X-CSRF-Token)
 * The Settings tab: change your own password.
 *  - the current password must be right (5 wrong tries block it for 15 minutes)
 *  - the new one is at least 8 characters (as on the reset page) and different
 * Fields: current_password, new_password   Answer: {success} or {success:false, error}
 */

require_once __DIR__ . '/../../../security/auth_gate.php';
require_once __DIR__ . '/../../../security/rate_limit.php';
require_once __DIR__ . '/../../../includes/admin_api.php';

admin_api_require_post();
$userId = admin_api_user_id();

$current = (string)($_POST['current_password'] ?? '');
$new = (string)($_POST['new_password'] ?? '');

if ($current === '' || $new === '') {
    admin_api_fail('adm_err_invalid');
}
if (mb_strlen($new) < 8) {
    admin_api_fail('adm_pw_min');
}
if (strlen($new) > 72) { // bcrypt only reads the first 72 bytes
    admin_api_fail('adm_pw_max');
}
if ($new === $current) {
    admin_api_fail('adm_pw_same');
}

try {
    $key = 'pwchange:' . $userId;
    if (!rate_limit_check($key, 5, 15)) {
        admin_api_fail('adm_err_rate', 429);
    }

    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $hash = (string)($stmt->fetchColumn() ?: '');

    if ($hash === '' || !password_verify($current, $hash)) {
        admin_api_fail('adm_pw_wrong', 403);
    }

    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
        ->execute([password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]), $userId]);

    rate_limit_reset($key);

    admin_api_json(['success' => true]);
} catch (Throwable $e) {
    admin_api_server_error($e, 'users/change_password');
}
