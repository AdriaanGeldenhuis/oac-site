<?php
/**
 * Offline in the app - shared helpers
 * ====================================
 * The app keeps Welcome, the Bible and Sing Emmanuel working without
 * internet (see /sw.js). These helpers give every page the same asset URLs
 * and tell the service worker what to keep.
 *
 *   oac_asset('/welcome/css/welcome.css')        -> '/welcome/css/welcome.css?v=<mtime>'
 *   oac_data_url('/bible/bibles/af_1933_53.json') -> '...json?v=<md5, 12 chars>'
 *   oac_offline_manifest($pdo, $userId)           -> what /offline/api/manifest.php answers
 *
 * Big data files (Bible, hymns) are versioned by their CONTENT, not their
 * file time: every deploy gives every file a new mtime, and that used to
 * re-download ~10 MB of Bible. The Android app carries the same files and
 * compares this hash to know its own copy is current (App Files/app/
 * build.gradle.kts computes it the same way: md5, first 12 hex chars).
 */
declare(strict_types=1);

define('OAC_WEB_ROOT', dirname(__DIR__));

// Hash cache: data path => size:mtime + hash. storage/ is never deployed over
// and not reachable from the web (storage/.htaccess).
define('OAC_DATA_VERSION_CACHE', OAC_WEB_ROOT . '/storage/cache/data_versions.json');

// Bump when the manifest's shape or the service worker's needs change, so
// every phone fetches its offline copy again
define('OAC_OFFLINE_SCHEMA', '1');

/** Cache-bust by file time (same format as bible_asset() / se_asset()) */
function oac_asset(string $webPath): string {
    $mtime = @filemtime(OAC_WEB_ROOT . $webPath);
    return $webPath . '?v=' . ($mtime ?: '1');
}

/** Content version of a data file: md5, first 12 hex chars, cached per size + mtime */
function oac_data_version(string $webPath): string {
    static $cache = null;

    $abs = OAC_WEB_ROOT . $webPath;
    $size = @filesize($abs);
    $mtime = @filemtime($abs);
    if ($size === false || $mtime === false) {
        return '1';
    }
    $stamp = $size . ':' . $mtime;

    if ($cache === null) {
        $cache = [];
        $raw = @file_get_contents(OAC_DATA_VERSION_CACHE);
        if ($raw !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $cache = $decoded;
            }
        }
    }
    if (isset($cache[$webPath]['s'], $cache[$webPath]['v']) && $cache[$webPath]['s'] === $stamp) {
        return (string)$cache[$webPath]['v'];
    }

    $hash = @md5_file($abs);
    if ($hash === false) {
        return (string)$mtime;
    }
    $version = substr($hash, 0, 12);
    $cache[$webPath] = ['s' => $stamp, 'v' => $version];

    // Best effort: without a writable storage/cache the hash is simply
    // computed again next time (~15 ms for the Bible)
    $dir = dirname(OAC_DATA_VERSION_CACHE);
    if (is_dir($dir) || @mkdir($dir, 0750, true)) {
        $tmp = OAC_DATA_VERSION_CACHE . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, json_encode($cache, JSON_UNESCAPED_SLASHES)) !== false) {
            if (!@rename($tmp, OAC_DATA_VERSION_CACHE)) {
                @unlink($tmp);
            }
        }
    }
    return $version;
}

/** URL of a data file with its content version */
function oac_data_url(string $webPath): string {
    return $webPath . '?v=' . oac_data_version($webPath);
}

/** Organ player: offices 0-8 only (same rule as singemmanuel.php and api/midi.php) */
function oac_can_play_organ(PDO $pdo, int $userId): bool {
    $stmt = $pdo->prepare("SELECT amp_id FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $ampId = $stmt->fetchColumn();
    return $ampId !== false && $ampId !== null && (int)$ampId >= 0 && (int)$ampId <= 8;
}

/**
 * Which hymns have organ music: ['1' => 1, '2' => 2, ...]
 * 1 = one verse (NNN.mid), 2 = also two verses (NNNR.mid)
 */
function oac_midi_map(): array {
    $map = [];
    foreach (glob(OAC_WEB_ROOT . '/singemmanuel/media/*.mid') ?: [] as $file) {
        if (!preg_match('/^(\d{3})(R?)\.mid$/', basename($file), $m)) {
            continue;
        }
        $n = (string)(int)$m[1];
        if ($n === '0') {
            continue;
        }
        if ($m[2] === 'R') {
            $map[$n] = 2;
        } elseif (!isset($map[$n])) {
            $map[$n] = 1;
        }
    }
    // A two-verse file without its one-verse file cannot be played (the
    // player offers "1 verse" first and singemmanuel.php needs NNN.mid)
    foreach ($map as $n => $kind) {
        if ($kind === 2 && !is_file(OAC_WEB_ROOT . '/singemmanuel/media/' . sprintf('%03d', (int)$n) . '.mid')) {
            unset($map[$n]);
        }
    }
    uksort($map, fn($a, $b) => (int)$a <=> (int)$b);
    return $map;
}

/**
 * Everything the service worker keeps for this member, as exact URLs (the
 * same ones the pages ask for, so a cached copy is always found).
 *
 * The Bible JSON is left out on purpose: the app carries it, and bible.js
 * keeps its own copy in IndexedDB - 10 MB need not be stored a third time.
 */
function oac_offline_manifest(PDO $pdo, int $userId): array {
    $static = [
        oac_asset('/header_footer/css/header.css'),
        oac_asset('/header_footer/js/header.js'),
        oac_asset('/assets/js/fcm-bridge.js'),
        oac_asset('/offline/css/offline.css'),
        oac_asset('/offline/js/offline.js'),
        oac_asset('/welcome/css/welcome.css'),
        oac_asset('/welcome/js/welcome.js'),
        oac_asset('/bible/css/bible.css'),
        oac_asset('/bible/js/bible.js'),
        oac_asset('/singemmanuel/css/singemmanuel.css'),
        oac_asset('/singemmanuel/js/singemmanuel.js'),
        oac_asset('/singemmanuel/js/se-player.js'),
        oac_data_url('/singemmanuel/songs/sing_af.json'),
        oac_data_url('/singemmanuel/songs/sing_en.json'),
        // Asked for without a version by the pages / header.css
        '/assets/img/wood-cross.svg',
        '/assets/fonts/Parisienne-Regular.ttf',
        '/offline/index.html',
    ];

    // Exactly as the pages link them (welcome + Sing Emmanuel, and the Bible)
    $fonts = [
        'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500;1,600&family=Parisienne&display=swap',
        'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;1,500&family=Parisienne&display=swap',
    ];

    // Page shells (network-first in the service worker; no personal data, so
    // they stay after logout) and the member's own pages and data, which
    // logout removes from the phone
    $pages = [
        '/bible/bible.php',
        '/singemmanuel/singemmanuel.php',
    ];
    $user = [
        '/welcome.php',
        '/bible/api/load_all.php',
    ];

    // Organ music only for those who may play it; api/midi.php checks again
    $canPlay = oac_can_play_organ($pdo, $userId);
    $midi = [];
    if ($canPlay) {
        foreach (oac_midi_map() as $n => $kind) {
            $midi[] = '/singemmanuel/api/midi.php?song=' . $n . '&verses=1';
            if ($kind === 2) {
                $midi[] = '/singemmanuel/api/midi.php?song=' . $n . '&verses=2';
            }
        }
    }

    $version = sha1(json_encode([OAC_OFFLINE_SCHEMA, $userId, $canPlay, $static, $fonts, $pages, $user, $midi]));

    return [
        'version' => $version,
        'static'  => $static,
        'fonts'   => $fonts,
        'pages'   => $pages,
        'user'    => $user,
        'midi'    => $canPlay ? $midi : null,
    ];
}
