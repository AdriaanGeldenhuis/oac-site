<?php
declare(strict_types=1);
/**
 * Retired: no page and no app version calls this any more.
 * It deleted a member under an old rank rule (users.amp), without CSRF.
 * Live path: /profile/api/delete_user.php (the rule profile/index.php shows the button by)
 *
 * The file stays as this stub on purpose. Deploys copy files but never
 * delete them on the server, so the old version would otherwise stay
 * reachable there, with an older permission rule than the live path.
 */

http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(['success' => false, 'error' => 'gone']);
