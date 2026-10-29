<?php
/**
 * GET /offline/api/manifest.php
 * What the app's service worker (/sw.js) keeps on the phone for this
 * member: exact asset URLs, the page shells, the member's own pages and -
 * for offices 0-8 - the organ music. See includes/offline.php.
 *
 * Not logged in: auth_gate answers with a redirect to /login.php, which the
 * service worker recognises and then keeps nothing.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../security/auth_gate.php';
require_once __DIR__ . '/../../includes/offline.php';

$userId = (int)$_SESSION['user_id'];
$lang = (string)($_SESSION['language'] ?? '');
session_write_close();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['success' => false]);
    exit;
}

try {
    $manifest = oac_offline_manifest($pdo, $userId, $lang);
    echo json_encode(['success' => true] + $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Offline manifest failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false]);
}
