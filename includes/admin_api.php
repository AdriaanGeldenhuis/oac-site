<?php
declare(strict_types=1);
/**
 * Shared guard for the /admin/api/ endpoints that change something.
 *
 *   require_once __DIR__ . '/../../includes/admin_api.php';
 *   admin_api_require_post();          // POST only + the session CSRF token
 *
 * The token travels in the X-CSRF-Token header (OACUI.post() in
 * /admin/js/ui.js adds it) or, for plain form posts, in a csrf_token field.
 * Errors answer {success:false, error:"<message in the member's language>"}
 * with a fitting HTTP status, and never carry database details.
 *
 * Needs security/auth_gate.php (session + csrf.php) to be loaded first.
 */

require_once __DIR__ . '/languages.php';
require_once __DIR__ . '/admin_page.php';

/** Answer JSON and stop. */
function admin_api_json(array $data, int $status = 200): void {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/** The member's language (session first, English as the last resort). */
function admin_api_lang(): string {
    return validate_language((string)($_SESSION['language'] ?? 'en'));
}

/**
 * Stop with a failure. $key is a UI_TRANSLATIONS key (adm_err_*), so the
 * message the toast shows is in the member's own language.
 */
function admin_api_fail(string $key, int $status = 400): void {
    admin_api_json(['success' => false, 'error' => __t($key, admin_api_lang())], $status);
}

/** A write must be a POST that carries the session's CSRF token. */
function admin_api_require_post(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        header('Allow: POST');
        admin_api_fail('adm_err_method', 405);
    }
    $token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''));
    if ($token === '' || !csrf_verify($token)) {
        admin_api_fail('adm_err_session', 403);
    }
}

/** The signed-in member's id, or a 401. */
function admin_api_user_id(): int {
    $id = (int)($_SESSION['user_id'] ?? 0);
    if ($id <= 0) {
        admin_api_fail('adm_err_session', 401);
    }
    return $id;
}

/** Log the real reason, answer with the generic one. */
function admin_api_server_error(Throwable $e, string $where): void {
    error_log('Admin API ' . $where . ': ' . $e->getMessage());
    admin_api_fail('adm_err_server', 500);
}

/** Same as admin_api_require_post(), for endpoints whose body is JSON (the native app bridge). */
function admin_api_require_json_post(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        header('Allow: POST');
        admin_api_fail('adm_err_method', 405);
    }
    // A cross-site <form> cannot send this content type, and a cross-site
    // fetch() with it needs a pre-flight this server never answers.
    $type = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    if (strpos($type, 'application/json') !== 0) {
        admin_api_fail('adm_err_invalid', 415);
    }
}

// ---------------------------------------------------------------------
// Who may approve whom. One place for the index badge, the Approvals tab
// and approve_user.php / reject_user.php, so they can never disagree.
//   Priester (6):        Onderdiaken … Broer/Suster (7–10), own congregation
//   Oudste (5):          Oudstes and Priesters (5, 6), own town
//   Opsiener/Evangelis/Profeet (2–4): the Vierfout and Oudstes (2–5), own town
//   Apostel (1):         Apostels (1), everywhere
// An office bearer without a congregation / town to scope by sees nobody.
// ---------------------------------------------------------------------

/** ['roles' => int[], 'col' => 'congregation_id'|'town_id'|null, 'val' => int] or null for no jurisdiction */
function admin_approval_scope(array $me): ?array {
    $amp = (int)($me['amp_id'] ?? 0);
    $town = (int)($me['town_id'] ?? 0);
    $cong = (int)($me['congregation_id'] ?? 0);

    if ($amp === 6) {
        return $cong > 0 ? ['roles' => [7, 8, 9, 10], 'col' => 'congregation_id', 'val' => $cong] : null;
    }
    if ($amp === 5) {
        return $town > 0 ? ['roles' => [5, 6], 'col' => 'town_id', 'val' => $town] : null;
    }
    if ($amp >= 2 && $amp <= 4) {
        return $town > 0 ? ['roles' => [2, 3, 4, 5], 'col' => 'town_id', 'val' => $town] : null;
    }
    if ($amp === 1) {
        return ['roles' => [1], 'col' => null, 'val' => 0];
    }
    return null;
}

/** SQL for "pending users this scope covers" (prepared; role ids are ints from the list above) */
function admin_approval_where(array $scope, string $alias = 'u'): array {
    $sql = "{$alias}.status = 'pending' AND {$alias}.amp_id IN (" . implode(',', array_map('intval', $scope['roles'])) . ')';
    $params = [];
    if ($scope['col'] !== null) {
        $sql .= " AND {$alias}.{$scope['col']} = :scope_val";
        $params[':scope_val'] = $scope['val'];
    }
    return [$sql, $params];
}

/**
 * Spouse requests an office bearer (amp 1–6) may handle: a priest sees their
 * congregation, everyone else the town; nobody without either.
 * Returns [sql using :scope_a / :scope_b, params] or null.
 */
function admin_spouse_scope(array $me): ?array {
    $amp = (int)($me['amp_id'] ?? 0);
    if ($amp < 1 || $amp > 6) {
        return null;
    }
    $cong = (int)($me['congregation_id'] ?? 0);
    $town = (int)($me['town_id'] ?? 0);
    if ($cong > 0) {
        return ['(u1.congregation_id = :scope_a OR u2.congregation_id = :scope_b)', [':scope_a' => $cong, ':scope_b' => $cong]];
    }
    if ($town > 0) {
        return ['(u1.town_id = :scope_a OR u2.town_id = :scope_b)', [':scope_a' => $town, ':scope_b' => $town]];
    }
    return null;
}

/** The PHP-side twin of admin_spouse_scope(): is either of these two members inside the office bearer's scope? */
function admin_spouse_in_scope(array $me, array $a, array $b): bool {
    $amp = (int)($me['amp_id'] ?? 0);
    if ($amp < 1 || $amp > 6) {
        return false;
    }
    $cong = (int)($me['congregation_id'] ?? 0);
    $town = (int)($me['town_id'] ?? 0);
    if ($cong > 0) {
        return (int)($a['congregation_id'] ?? 0) === $cong || (int)($b['congregation_id'] ?? 0) === $cong;
    }
    if ($town > 0) {
        return (int)($a['town_id'] ?? 0) === $town || (int)($b['town_id'] ?? 0) === $town;
    }
    return false;
}

/**
 * What waits for this member, counted by the same rules as the lists that
 * show it (so a badge never promises more than the tab has):
 *   users         members to approve           (admin_approval_scope)
 *   spouse        spouse requests to approve   (admin_spouse_scope, office bearers)
 *   appointments  appointment requests made to me, still to answer
 *   mine          a spouse request made to me, to accept or turn down
 *   approvals     users + spouse               (the Approvals tab)
 *   total         everything above             (the menu)
 * $me is a users row (id, amp_id, town_id, congregation_id). Read-only;
 * a failing count is logged and counts as 0, it never breaks the page.
 */
function admin_pending_counts(PDO $pdo, array $me): array {
    $c = ['users' => 0, 'spouse' => 0, 'appointments' => 0, 'mine' => 0];
    $id = (int)($me['id'] ?? 0);

    $scope = admin_approval_scope($me);
    if ($scope !== null) {
        try {
            [$where, $params] = admin_approval_where($scope, 'u');
            $st = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE {$where}");
            $st->execute($params);
            $c['users'] = (int)$st->fetchColumn();
        } catch (Throwable $e) {
            error_log('Admin count users: ' . $e->getMessage());
        }
    }

    $spouseScope = admin_spouse_scope($me);
    if ($spouseScope !== null) {
        try {
            [$spouseSql, $spouseParams] = $spouseScope;
            $st = $pdo->prepare("SELECT COUNT(*) FROM spouse_requests sr
                JOIN users u1 ON u1.id = sr.requester_id
                JOIN users u2 ON u2.id = sr.receiver_id
                WHERE sr.status = 'pending'
                AND {$spouseSql}
                AND u1.spouse_user_id IS NULL
                AND u2.spouse_user_id IS NULL");
            $st->execute($spouseParams);
            $c['spouse'] = (int)$st->fetchColumn();
        } catch (Throwable $e) {
            error_log('Admin count spouse: ' . $e->getMessage());
        }
    }

    if ($id > 0) {
        try {
            $st = $pdo->prepare("SELECT COUNT(*) FROM ampte_appointments
                WHERE target_user_id = ? AND status = 'pending' AND appointment_date >= CURDATE()");
            $st->execute([$id]);
            $c['appointments'] = (int)$st->fetchColumn();
        } catch (Throwable $e) {
            error_log('Admin count appointments: ' . $e->getMessage());
        }

        try {
            $st = $pdo->prepare("SELECT COUNT(*) FROM spouse_requests WHERE receiver_id = ? AND status = 'pending'");
            $st->execute([$id]);
            $c['mine'] = (int)$st->fetchColumn();
        } catch (Throwable $e) {
            error_log('Admin count own spouse request: ' . $e->getMessage());
        }
    }

    $c['approvals'] = $c['users'] + $c['spouse'];
    $c['total'] = $c['users'] + $c['spouse'] + $c['appointments'] + $c['mine'];
    return $c;
}
