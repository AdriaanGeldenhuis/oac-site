<?php
/**
 * File: /api/admin/users/manage.php
 * Purpose: Single endpoint to (a) change roles, and (b) ban users, respecting hierarchy & jurisdiction.
 * Input (POST, JSON or form-encoded):
 *   action: 'role_change' | 'ban'
 *   target_user_id: int
 *   new_amp_id: int (required when action='role_change')
 *   reason: string (optional, used for ban)
 * Response: JSON { ok: bool, message: string }
 *
 * Security: Requires authenticated session. No output before headers.
 */

declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../auth_gate.php'; // must start session and enforce login
// Ensure a DB handle is available ($db as mysqli). If not, include your DB bootstrap here.
if (!isset($db) || !($db instanceof mysqli)) {
  // Fallback: try project-level db include if exists
  $db_inc1 = __DIR__ . '/../../db.php';
  $db_inc2 = __DIR__ . '/../../lib/db.php';
  if (file_exists($db_inc1)) require_once $db_inc1;
  if (file_exists($db_inc2) && (!isset($db) || !($db instanceof mysqli))) require_once $db_inc2;
}
if (!isset($db) || !($db instanceof mysqli)) {
  http_response_code(500);
  echo json_encode(['ok'=>false, 'message'=>'DB connection not available']);
  exit;
}

require_once __DIR__ . '/../../lib/roles.php';

$raw = file_get_contents('php://input');
$input = [];
if ($raw) {
  $tmp = json_decode($raw, true);
  if (is_array($tmp)) $input = $tmp;
}
$action = $_POST['action'] ?? ($input['action'] ?? null);
$target_user_id = (int)($_POST['target_user_id'] ?? ($input['target_user_id'] ?? 0));
$new_amp_id = isset($_POST['new_amp_id']) ? (int)$_POST['new_amp_id'] : (int)($input['new_amp_id'] ?? 0);
$reason = trim((string)($_POST['reason'] ?? ($input['reason'] ?? '')));

if (!$action || !$target_user_id) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'message'=>'Missing action or target_user_id']);
  exit;
}
$actor_id = (int)($_SESSION['user_id'] ?? 0);
if ($actor_id <= 0) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'message'=>'Unauthorized']);
  exit;
}

$actor = fetch_user_min($db, $actor_id);
$target = fetch_user_min($db, $target_user_id);
if (!$actor || !$target) {
  http_response_code(404);
  echo json_encode(['ok'=>false,'message'=>'User not found']);
  exit;
}

if ($action === 'role_change') {
  if (!$new_amp_id) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'message'=>'new_amp_id required']);
    exit;
  }

  list($allowed, $reason_msg) = can_change_role($actor, $target, $new_amp_id);
  if (!$allowed) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'message'=>$reason_msg ?: 'Not allowed']);
    exit;
  }

  // Direct role update (simple case). If your policy requires additional approvals,
  // insert into `approvals` with action='role_change' for higher review instead.
  $stmt = $db->prepare("UPDATE users SET amp_id=? WHERE id=?");
  $stmt->bind_param("ii", $new_amp_id, $target_user_id);
  $ok = $stmt->execute();
  $stmt->close();

  if (!$ok) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>'Database error during role change']);
    exit;
  }
  echo json_encode(['ok'=>true,'message'=>'Rol opgedateer']);
  exit;
}

if ($action === 'ban') {
  list($allowed, $reason_msg) = can_ban_user($actor, $target);
  if (!$allowed) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'message'=>$reason_msg ?: 'Not allowed']);
    exit;
  }
  // Set status to rejected + optional reason
  $stmt = $db->prepare("UPDATE users SET status='rejected', status_reason=? WHERE id=?");
  $stmt->bind_param("si", $reason, $target_user_id);
  $ok = $stmt->execute();
  $stmt->close();

  if (!$ok) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>'Database error during ban']);
    exit;
  }
  echo json_encode(['ok'=>true,'message'=>'Gebruiker verban/verwerp']);
  exit;
}

http_response_code(400);
echo json_encode(['ok'=>false,'message'=>'Unknown action']);