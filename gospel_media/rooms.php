<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/security/auth_gate.php';
require_once dirname(__DIR__) . '/includes/languages.php';

$pageLang = $_SESSION['language'] ?? 'af';
if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS, true)) {
    $_SESSION['language'] = $pageLang = $_GET['lang'];
}
// Translation helper using central 5-language system
function t(string $key): string {
    global $pageLang;
    return __t($key, $pageLang);
}

// Helper to format room display name with translation
function formatRoomDisplayName(string $type, string $name): string {
    global $pageLang;
    $t = strtolower($type);
    if ($t === 'gemeente') return __t('congregation', $pageLang) . ' ' . $name;
    if ($t === 'opsienerskap') return __t('oversight', $pageLang) . ' ' . $name;
    if ($t === 'jeug') return __t('youth', $pageLang) . ' ' . $name;
    if ($t === 'sondagskool') return __t('sunday_school', $pageLang) . ' ' . $name;
    return $name ?: __t('room', $pageLang);
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    die('Database connection failed');
}

$userId = (int)$_SESSION['user_id'];

// Get user info
$me = $pdo->prepare("SELECT amp_id, town_id, congregation_id, name, surname, birthdate, marital_status FROM users WHERE id=?");
$me->execute([$userId]);
$u = $me->fetch(PDO::FETCH_ASSOC);

if (!$u) {
    die('User not found');
}

$ampId = isset($u['amp_id']) ? (int)$u['amp_id'] : 999;
$townId = (int)($u['town_id'] ?? 0);
$congId = (int)($u['congregation_id'] ?? 0);
$userName = htmlspecialchars($u['name'] . ' ' . $u['surname']);
$birthdate = $u['birthdate'] ?? null;
$maritalStatus = $u['marital_status'] ?? '';

// Calculate age
$age = 0;
if ($birthdate) {
    $dob = new DateTime($birthdate);
    $now = new DateTime();
    $age = $now->diff($dob)->y;
}

if ($townId <= 0) {
    die(t('profile_no_town'));
}

// Determine user capabilities
$isApostel = ($ampId === 1);
$isPrivileged = ($ampId >= 2 && $ampId <= 6);
$isRestricted = ($ampId >= 8);

// Get town/congregation names
$townName = '';
$congName = '';
try {
    $tq = $pdo->prepare("SELECT name FROM towns WHERE id=?");
    $tq->execute([$townId]);
    $townName = $tq->fetchColumn() ?: 'Onbekend';
    
    if ($congId > 0) {
        $cq = $pdo->prepare("SELECT name FROM congregations WHERE id=?");
        $cq->execute([$congId]);
        $congName = $cq->fetchColumn() ?: '';
    }
} catch (Throwable $e) {
    $townName = 'Onbekend';
}

// Get auto-membership rooms (always member)
$autoRooms = [];

// Get all available rooms based on amp level
$availableRooms = [];
$joinedRooms = [];

try {
    if ($isApostel) {
        $sql = "SELECT r.*, 
                       t.name AS town_name,
                       CONCAT('Opsienerskap ', t.name) AS display_name,
                       EXISTS(SELECT 1 FROM room_memberships rm WHERE rm.room_id = r.id AND rm.user_id = ?) AS is_member
                FROM rooms r
                LEFT JOIN towns t ON t.id = r.town_id
                WHERE r.type = 'opsienerskap'
                ORDER BY t.name";
        $st = $pdo->prepare($sql);
        $st->execute([$userId]);
        $rooms = $st->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($rooms as $room) {
            if ((int)$room['is_member'] === 1) {
                $joinedRooms[] = $room;
            } else {
                $availableRooms[] = $room;
            }
        }
        
    } elseif ($isRestricted) {
        if ($congId > 0) {
            $sql = "SELECT r.*, c.name AS congregation_name, c.name AS display_name
                    FROM rooms r
                    LEFT JOIN congregations c ON c.id = r.gemeente_id
                    WHERE r.type = 'gemeente' AND r.gemeente_id = ?";
            $st = $pdo->prepare($sql);
            $st->execute([$congId]);
            $autoRooms = array_merge($autoRooms, $st->fetchAll(PDO::FETCH_ASSOC));
        }
        
        $sql = "SELECT r.*, t.name AS town_name, CONCAT('Opsienerskap ', t.name) AS display_name
                FROM rooms r
                LEFT JOIN towns t ON t.id = r.town_id
                WHERE r.type = 'opsienerskap' AND r.town_id = ?";
        $st = $pdo->prepare($sql);
        $st->execute([$townId]);
        $autoRooms = array_merge($autoRooms, $st->fetchAll(PDO::FETCH_ASSOC));
        
        if ($age > 0 && $age < 16) {
            $sql = "SELECT r.*, t.name AS town_name, CONCAT('Sondagskool ', t.name) AS display_name
                    FROM rooms r
                    LEFT JOIN towns t ON t.id = r.town_id
                    WHERE r.type = 'sondagskool' AND r.town_id = ?";
            $st = $pdo->prepare($sql);
            $st->execute([$townId]);
            $autoRooms = array_merge($autoRooms, $st->fetchAll(PDO::FETCH_ASSOC));
        }
        
        if ($age >= 16 && $age <= 25 && $maritalStatus === 'ongetroud') {
            $sql = "SELECT r.*, t.name AS town_name, CONCAT('Jeug ', t.name) AS display_name
                    FROM rooms r
                    LEFT JOIN towns t ON t.id = r.town_id
                    WHERE r.type = 'jeug' AND r.town_id = ?";
            $st = $pdo->prepare($sql);
            $st->execute([$townId]);
            $autoRooms = array_merge($autoRooms, $st->fetchAll(PDO::FETCH_ASSOC));
        }
        
        $sql = "SELECT r.*, 
                       t.name AS town_name,
                       CASE 
                           WHEN r.type = 'jeug' THEN CONCAT('Jeug ', t.name)
                           WHEN r.type = 'sondagskool' THEN CONCAT('Sondagskool ', t.name)
                           ELSE r.name
                       END AS display_name
                FROM room_memberships rm
                JOIN rooms r ON r.id = rm.room_id
                LEFT JOIN towns t ON t.id = r.town_id
                WHERE rm.user_id = ? 
                  AND r.type IN ('jeug', 'sondagskool')
                  AND r.town_id = ?
                ORDER BY r.type";
        $st = $pdo->prepare($sql);
        $st->execute([$userId, $townId]);
        $joinedRooms = array_merge($joinedRooms, $st->fetchAll(PDO::FETCH_ASSOC));
        
    } elseif ($isPrivileged) {
        if ($congId > 0) {
            $sql = "SELECT r.*, c.name AS congregation_name, c.name AS display_name
                    FROM rooms r
                    LEFT JOIN congregations c ON c.id = r.gemeente_id
                    WHERE r.type = 'gemeente' AND r.gemeente_id = ?";
            $st = $pdo->prepare($sql);
            $st->execute([$congId]);
            $autoRooms = array_merge($autoRooms, $st->fetchAll(PDO::FETCH_ASSOC));
        }
        
        $sql = "SELECT r.*, t.name AS town_name, CONCAT('Opsienerskap ', t.name) AS display_name
                FROM rooms r
                LEFT JOIN towns t ON t.id = r.town_id
                WHERE r.type = 'opsienerskap' AND r.town_id = ?";
        $st = $pdo->prepare($sql);
        $st->execute([$townId]);
        $autoRooms = array_merge($autoRooms, $st->fetchAll(PDO::FETCH_ASSOC));
        
        if ($age > 0 && $age < 16) {
            $sql = "SELECT r.*, t.name AS town_name, CONCAT('Sondagskool ', t.name) AS display_name
                    FROM rooms r
                    LEFT JOIN towns t ON t.id = r.town_id
                    WHERE r.type = 'sondagskool' AND r.town_id = ?";
            $st = $pdo->prepare($sql);
            $st->execute([$townId]);
            $autoRooms = array_merge($autoRooms, $st->fetchAll(PDO::FETCH_ASSOC));
        }
        
        if ($age >= 16 && $age <= 25 && $maritalStatus === 'ongetroud') {
            $sql = "SELECT r.*, t.name AS town_name, CONCAT('Jeug ', t.name) AS display_name
                    FROM rooms r
                    LEFT JOIN towns t ON t.id = r.town_id
                    WHERE r.type = 'jeug' AND r.town_id = ?";
            $st = $pdo->prepare($sql);
            $st->execute([$townId]);
            $autoRooms = array_merge($autoRooms, $st->fetchAll(PDO::FETCH_ASSOC));
        }
        
        $sql = "SELECT r.*, 
                       c.name AS congregation_name,
                       c.name AS display_name,
                       t.name AS town_name,
                       EXISTS(SELECT 1 FROM room_memberships rm WHERE rm.room_id = r.id AND rm.user_id = ?) AS is_member
                FROM rooms r
                LEFT JOIN congregations c ON c.id = r.gemeente_id
                LEFT JOIN towns t ON t.id = (SELECT town_id FROM congregations WHERE id = r.gemeente_id)
                WHERE r.type = 'gemeente' 
                  AND r.gemeente_id != ?
                  AND (SELECT town_id FROM congregations WHERE id = r.gemeente_id) = ?
                ORDER BY c.name";
        $st = $pdo->prepare($sql);
        $st->execute([$userId, $congId, $townId]);
        $gemeentes = $st->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($gemeentes as $room) {
            if ((int)$room['is_member'] === 1) {
                $joinedRooms[] = $room;
            } else {
                $availableRooms[] = $room;
            }
        }
        
        $jeugTypes = [];
        if (!($age > 0 && $age < 16)) $jeugTypes[] = 'sondagskool';
        if (!($age >= 16 && $age <= 25 && $maritalStatus === 'ongetroud')) $jeugTypes[] = 'jeug';
        
        if ($jeugTypes) {
            $placeholders = implode(',', array_fill(0, count($jeugTypes), '?'));
            $sql = "SELECT r.*, 
                           t.name AS town_name,
                           CASE 
                               WHEN r.type = 'jeug' THEN CONCAT('Jeug ', t.name)
                               WHEN r.type = 'sondagskool' THEN CONCAT('Sondagskool ', t.name)
                               ELSE r.name
                           END AS display_name,
                           EXISTS(SELECT 1 FROM room_memberships rm WHERE rm.room_id = r.id AND rm.user_id = ?) AS is_member
                    FROM rooms r
                    LEFT JOIN towns t ON t.id = r.town_id
                    WHERE r.type IN ($placeholders) AND r.town_id = ?
                    ORDER BY r.type";
            $st = $pdo->prepare($sql);
            $st->execute(array_merge([$userId], $jeugTypes, [$townId]));
            $youth = $st->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($youth as $room) {
                if ((int)$room['is_member'] === 1) {
                    $joinedRooms[] = $room;
                } else {
                    $availableRooms[] = $room;
                }
            }
        }
        
    } else {
        if ($congId > 0) {
            $sql = "SELECT r.*, c.name AS congregation_name, c.name AS display_name
                    FROM rooms r
                    LEFT JOIN congregations c ON c.id = r.gemeente_id
                    WHERE r.type = 'gemeente' AND r.gemeente_id = ?";
            $st = $pdo->prepare($sql);
            $st->execute([$congId]);
            $autoRooms = array_merge($autoRooms, $st->fetchAll(PDO::FETCH_ASSOC));
        }
        
        $sql = "SELECT r.*, t.name AS town_name, CONCAT('Opsienerskap ', t.name) AS display_name
                FROM rooms r
                LEFT JOIN towns t ON t.id = r.town_id
                WHERE r.type = 'opsienerskap' AND r.town_id = ?";
        $st = $pdo->prepare($sql);
        $st->execute([$townId]);
        $autoRooms = array_merge($autoRooms, $st->fetchAll(PDO::FETCH_ASSOC));
        
        if ($age > 0 && $age < 16) {
            $sql = "SELECT r.*, t.name AS town_name, CONCAT('Sondagskool ', t.name) AS display_name
                    FROM rooms r
                    LEFT JOIN towns t ON t.id = r.town_id
                    WHERE r.type = 'sondagskool' AND r.town_id = ?";
            $st = $pdo->prepare($sql);
            $st->execute([$townId]);
            $autoRooms = array_merge($autoRooms, $st->fetchAll(PDO::FETCH_ASSOC));
        }
        
        if ($age >= 16 && $age <= 25 && $maritalStatus === 'ongetroud') {
            $sql = "SELECT r.*, t.name AS town_name, CONCAT('Jeug ', t.name) AS display_name
                    FROM rooms r
                    LEFT JOIN towns t ON t.id = r.town_id
                    WHERE r.type = 'jeug' AND r.town_id = ?";
            $st = $pdo->prepare($sql);
            $st->execute([$townId]);
            $autoRooms = array_merge($autoRooms, $st->fetchAll(PDO::FETCH_ASSOC));
        }
        
        $jeugTypes = [];
        if (!($age > 0 && $age < 16)) $jeugTypes[] = 'sondagskool';
        if (!($age >= 16 && $age <= 25 && $maritalStatus === 'ongetroud')) $jeugTypes[] = 'jeug';
        
        if ($jeugTypes) {
            $placeholders = implode(',', array_fill(0, count($jeugTypes), '?'));
            $sql = "SELECT r.*, 
                           t.name AS town_name,
                           CASE 
                               WHEN r.type = 'jeug' THEN CONCAT('Jeug ', t.name)
                               WHEN r.type = 'sondagskool' THEN CONCAT('Sondagskool ', t.name)
                               ELSE r.name
                           END AS display_name,
                           EXISTS(SELECT 1 FROM room_memberships rm WHERE rm.room_id = r.id AND rm.user_id = ?) AS is_member
                    FROM rooms r
                    LEFT JOIN towns t ON t.id = r.town_id
                    WHERE r.type IN ($placeholders) AND r.town_id = ?
                    ORDER BY r.type";
            $st = $pdo->prepare($sql);
            $st->execute(array_merge([$userId], $jeugTypes, [$townId]));
            $youth = $st->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($youth as $room) {
                if ((int)$room['is_member'] === 1) {
                    $joinedRooms[] = $room;
                } else {
                    $availableRooms[] = $room;
                }
            }
        }
    }

    // Post-process: apply translations to display_name
    $allRoomArrays = [&$autoRooms, &$joinedRooms, &$availableRooms];
    foreach ($allRoomArrays as &$roomArray) {
        foreach ($roomArray as &$room) {
            $type = $room['type'] ?? '';
            $name = $room['town_name'] ?? $room['congregation_name'] ?? $room['name'] ?? '';
            $room['display_name'] = formatRoomDisplayName($type, $name);
        }
        unset($room);
    }
    unset($roomArray);

} catch (Throwable $e) {
    error_log("Rooms query failed: " . $e->getMessage());
}

$VER = time();
?><!doctype html>
<html lang="<?= $pageLang ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= t('rooms') ?> - <?= htmlspecialchars($townName) ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <style>
    @font-face {
      font-family: 'Parisienne';
      src: url('/assets/fonts/Parisienne-Regular.ttf') format('truetype');
      font-weight: normal;
      font-style: normal;
      font-display: swap;
    }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@200;400;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="/gospel_media/css/rooms.css?v=<?= $VER ?>">
</head>
<body class="rooms-body">
    <?php require_once dirname(__DIR__) . '/header_footer/header.php'; ?>
    
    <main class="rm-main">
        <div class="rm-header">
            <h1 class="rm-title"><?= t('your_rooms') ?></h1>
            <p class="rm-subtitle"><?= htmlspecialchars($townName) ?></p>
        </div>
        
        <div class="rm-info">
            <p><strong><?= t('welcome') ?>:</strong> <?= $userName ?></p>
            <p><strong><?= t('town') ?>:</strong> <?= htmlspecialchars($townName) ?></p>
            <?php if ($congName): ?>
                <p><strong><?= t('congregation') ?>:</strong> <?= htmlspecialchars($congName) ?></p>
            <?php endif; ?>
            <?php if ($age > 0): ?>
                <p><strong><?= t('age') ?>:</strong> <?= $age ?> <?= t('years') ?></p>
            <?php endif; ?>
            <p><strong><?= t('amp_level') ?>:</strong> 
                <span class="highlight">
                    <?php
                    if ($isApostel) echo t('apostle_desc');
                    elseif ($isPrivileged) echo t('amp_2_6_desc');
                    elseif ($isRestricted) echo t('amp_8_desc');
                    else echo t('amp_7_desc');
                    ?>
                </span>
            </p>
        </div>
        
        <?php if ($autoRooms): ?>
        <section class="rm-section">
            <div class="rm-section-header">
                <h2>
                    <span><?= t('automatic') ?></span>
                    <span class="rm-count"><?= count($autoRooms) ?></span>
                </h2>
            </div>
            <div class="rm-section-body">
                <ul class="rm-list">
                    <?php foreach ($autoRooms as $r): 
                        $roomType = strtolower($r['type'] ?? '');
                        $roomId = (int)$r['id'];
                        
                        $canLeaveRoom = false;
                        if ($roomType === 'gemeente' && $isPrivileged) {
                            $canLeaveRoom = true;
                        }
                    ?>
                        <li class="rm-item auto">
                            <div>
                                <a class="rm-link" href="/gospel_media/gospel.php?room_id=<?= $roomId ?>">
                                    <?= htmlspecialchars($r['display_name']) ?>
                                </a>
                                <div class="rm-meta">
                                    <?= t('automatic_membership') ?>
                                    <?php if ($canLeaveRoom): ?>
                                        • <?= t('officer_can_choose') ?>
                                    <?php elseif ($roomType === 'sondagskool' && $age > 0 && $age < 16): ?>
                                        • <?= t('auto_under_16') ?>
                                    <?php elseif ($roomType === 'jeug' && $age >= 16 && $age <= 25): ?>
                                        • <?= t('auto_16_25') ?>
                                    <?php elseif ($r['description']): ?>
                                        • <?= htmlspecialchars(mb_substr($r['description'], 0, 50)) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="rm-actions">
                                <span class="rm-badge auto">
                                    <?= t('auto_short') ?>
                                </span>
                                <?php if ($canLeaveRoom): ?>
                                    <button class="rm-btn rm-btn-leave" 
                                            data-action="leave" 
                                            data-room-id="<?= $roomId ?>"
                                            data-room-name="<?= htmlspecialchars($r['display_name']) ?>">
                                        <?= t('leave') ?>
                                    </button>
                                <?php else: ?>
                                    <button class="rm-btn rm-btn-view" onclick="location.href='/gospel_media/gospel.php?room_id=<?= $roomId ?>'">
                                        <?= t('view') ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
        <?php endif; ?>
        
        <?php if ($joinedRooms): ?>
        <section class="rm-section">
            <div class="rm-section-header">
                <h2>
                    <span><?= t('joined') ?></span>
                    <span class="rm-count"><?= count($joinedRooms) ?></span>
                </h2>
            </div>
            <div class="rm-section-body">
                <ul class="rm-list">
                    <?php foreach ($joinedRooms as $r): 
                        $roomType = strtolower($r['type'] ?? '');
                        $roomId = (int)$r['id'];
                    ?>
                        <li class="rm-item">
                            <div>
                                <a class="rm-link" href="/gospel_media/gospel.php?room_id=<?= $roomId ?>">
                                    <?= htmlspecialchars($r['display_name']) ?>
                                </a>
                                <div class="rm-meta">
                                    <?php if (isset($r['town_name'])): ?>
                                        <?= htmlspecialchars($r['town_name']) ?>
                                    <?php endif; ?>
                                    <?php if ($r['description']): ?>
                                        • <?= htmlspecialchars(mb_substr($r['description'], 0, 50)) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="rm-actions">
                                <span class="rm-badge <?= $roomType ?>">
                                    <?= ucfirst($roomType) ?>
                                </span>
                                <button class="rm-btn rm-btn-leave"
                                        data-action="leave"
                                        data-room-id="<?= $roomId ?>"
                                        data-room-name="<?= htmlspecialchars($r['display_name']) ?>">
                                    <?= t('leave') ?>
                                </button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($availableRooms): ?>
        <section class="rm-section">
            <div class="rm-section-header">
                <h2>
                    <span><?= t('available') ?></span>
                    <span class="rm-count"><?= count($availableRooms) ?></span>
                </h2>
            </div>
            <div class="rm-section-body">
                <ul class="rm-list">
                    <?php foreach ($availableRooms as $r): 
                        $roomType = strtolower($r['type'] ?? '');
                        $roomId = (int)$r['id'];
                    ?>
                        <li class="rm-item">
                            <div>
                                <div class="rm-link">
                                    <?= htmlspecialchars($r['display_name']) ?>
                                </div>
                                <div class="rm-meta">
                                    <?php if (isset($r['town_name'])): ?>
                                        <?= htmlspecialchars($r['town_name']) ?>
                                    <?php endif; ?>
                                    <?php if ($r['description']): ?>
                                        • <?= htmlspecialchars(mb_substr($r['description'], 0, 50)) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="rm-actions">
                                <span class="rm-badge <?= $roomType ?>">
                                    <?= ucfirst($roomType) ?>
                                </span>
                                <button class="rm-btn rm-btn-join"
                                        data-action="join"
                                        data-room-id="<?= $roomId ?>"
                                        data-room-name="<?= htmlspecialchars($r['display_name']) ?>">
                                    <?= t('join') ?>
                                </button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
        <?php endif; ?>
        
        <?php if (!$autoRooms && !$joinedRooms && !$availableRooms): ?>
        <section class="rm-section">
            <div class="rm-empty">
                <div class="rm-empty-icon">📭</div>
                <p class="rm-empty-text">
                    <?= t('no_rooms') ?>
                </p>
            </div>
        </section>
        <?php endif; ?>
        
        <p class="rm-back">
            <a href="/gospel_media/gospel.php">← <?= t('back_to_media') ?></a>
        </p>
    </main>
    
    <div id="toast" class="toast"></div>
    
    <script>
    function showToast(message, isError = false) {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.className = 'toast' + (isError ? ' error' : '') + ' show';
        setTimeout(() => toast.classList.remove('show'), 3000);
    }
    
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;
        
        const action = btn.dataset.action;
        const roomId = btn.dataset.roomId;
        const roomName = btn.dataset.roomName;
        
        if (action === 'leave') {
            const confirmMsg = roomName.toLowerCase().includes('gemeente')
                ? `<?= t('confirm_leave_congregation') ?>`
                : `<?= t('confirm_leave_room') ?> "${roomName}"?`;

            if (!confirm(confirmMsg)) {
                return;
            }
        }
        
        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = '...';
        
        try {
            const res = await fetch('/gospel_media/api/rooms/' + action + '.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'room_id=' + roomId
            });
            
            const data = await res.json();
            
            if (data.ok || data.success) {
                showToast(action === 'join'
                    ? '<?= t('successfully_joined') ?>'
                    : '<?= t('successfully_left') ?>');
                setTimeout(() => location.reload(), 1000);
            } else {
                throw new Error(data.error || 'Unknown error');
            }
        } catch (err) {
            console.error(err);
            let errorMsg = err.message || 'Unknown error';
            
            if (errorMsg.includes('cannot_leave_opsienerskap')) {
                errorMsg = '<?= t('cannot_leave_oversight') ?>';
            } else if (errorMsg.includes('cannot_leave_own_gemeente')) {
                errorMsg = '<?= t('cannot_leave_congregation') ?>';
            }

            showToast('<?= t('error') ?>: ' + errorMsg, true);
            btn.disabled = false;
            btn.textContent = originalText;
        }
    });
    </script>
    
    <?php require_once dirname(__DIR__) . '/header_footer/footer.php'; ?>
</body>
</html>