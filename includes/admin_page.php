<?php
declare(strict_types=1);
/**
 * Small helpers the admin pages share (index.php, account.php and the tabs).
 */

require_once __DIR__ . '/languages.php';

/** HTML-escape for output */
function admin_e($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** "First Last" from a users row (or any array with name / surname) */
function admin_full_name(?array $u, string $nameKey = 'name', string $surnameKey = 'surname'): string {
    return trim((string)($u[$nameKey] ?? '') . ' ' . (string)($u[$surnameKey] ?? ''));
}

/** The office in the member's language ("Oudste", "Suster"), or '' for a member without one */
function admin_office($ampId, $gender, string $lang): string {
    $ampId = (int)$ampId;
    if (!isset(AMP_TRANSLATION_KEYS[$ampId])) {
        return '';
    }
    return get_translated_office($ampId, (string)$gender, $lang);
}

/** An office as a group's name: "Oudste", but "Broer / Suster" for the members (amp 10), whose group holds both */
function admin_office_group(int $ampId, string $lang): string {
    $male = admin_office($ampId, 'man', $lang);
    $female = admin_office($ampId, 'vrou', $lang);
    return ($ampId === 10 && $female !== '' && $female !== $male) ? $male . ' / ' . $female : $male;
}

/**
 * How the admin names a person: always the office first, then name and
 * surname ("Oudste Kobus Nel"). $p is the prefix of aliased columns, so
 * admin_person($row, $lang, 'r_') reads r_amp_id, r_gender, r_name, r_surname.
 */
function admin_person(?array $row, string $lang, string $p = ''): string {
    $office = admin_office($row[$p . 'amp_id'] ?? 0, $row[$p . 'gender'] ?? '', $lang);
    return trim($office . ' ' . admin_full_name($row, $p . 'name', $p . 'surname'));
}

/** A translated line with {placeholders}; the line is escaped, the values are HTML the caller made safe */
function admin_fill_html(string $line, array $html): string {
    $out = admin_e($line);
    foreach ($html as $k => $v) {
        $out = str_replace('{' . $k . '}', (string)$v, $out);
    }
    return $out;
}

/**
 * When something happened, as a <time>: "vandag", "gister", "3 dae gelede"
 * for two weeks, after that the date (admin.js writes it out in the
 * member's language). '' without a usable date.
 */
function admin_ago_html(?string $when, string $lang): string {
    $when = trim((string)$when);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $when) || strpos($when, '0000-00-00') === 0) {
        return '';
    }
    $tz = new DateTimeZone('Africa/Johannesburg');
    $day = DateTimeImmutable::createFromFormat('!Y-m-d', substr($when, 0, 10), $tz);
    if (!$day) {
        return '';
    }
    $iso = $day->format('Y-m-d');
    $days = max(0, (int)$day->diff(new DateTimeImmutable('today', $tz))->format('%r%a'));

    if ($days === 0) {
        $text = __t('adm_ago_today', $lang);
    } elseif ($days === 1) {
        $text = __t('adm_ago_yesterday', $lang);
    } elseif ($days < 14) {
        $text = str_replace('{n}', (string)$days, __t('adm_ago_days', $lang));
    } else {
        return '<time datetime="' . admin_e($iso) . '" data-date="' . admin_e($iso) . '">' . admin_e($iso) . '</time>';
    }
    return '<time datetime="' . admin_e($iso) . '">' . admin_e($text) . '</time>';
}

/**
 * The admin's line icons (24x24, stroked by CSS). One set, so every tab
 * draws the same thing the same way.
 */
function admin_icon(string $name, string $class = 'adm-ico'): string {
    static $paths = [
        'home' => '<path d="M3 10.5L12 3l9 7.5"/><path d="M5 9.5V20h5v-6h4v6h5V9.5"/>',
        'approvals' => '<path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M3 10h18M8 2v4M16 2v4"/>',
        'teaching' => '<path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/>',
        'thought' => '<path d="M9 21h6M10 17h4M12 2a6 6 0 00-3.5 10.9c.6.5 1 1.2 1 2V15h5v-.1c0-.8.4-1.5 1-2A6 6 0 0012 2z"/>',
        'user' => '<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'users' => '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/>',
        'heart' => '<path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>',
        'check' => '<path d="M20 6L9 17l-5-5"/>',
        'x' => '<path d="M18 6L6 18M6 6l12 12"/>',
        'unlink' => '<path d="M18.84 12.25l1.72-1.71a5 5 0 00-7.07-7.07l-1.72 1.71M5.17 11.75l-1.72 1.71a5 5 0 007.07 7.07l1.72-1.71M8 2v3M2 8h3M16 19v3M19 16h3"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'pin' => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/>',
        'chevron' => '<path d="M9 18l6-6-6-6"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/>',
        'lock' => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0v4"/>',
        'edit' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 013 3L7 19l-4 1 1-4z"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'mail' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 6l-10 7L2 6"/>',
        'phone' => '<path d="M22 16.92v3a2 2 0 01-2.18 2 19.8 19.8 0 01-8.63-3.07 19.5 19.5 0 01-6-6A19.8 19.8 0 012.12 4.18 2 2 0 014.11 2h3a2 2 0 012 1.72c.13.96.36 1.9.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0122 16.92z"/>',
        'church' => '<path d="M12 2v4M10 4h4"/><path d="M6 22V11l6-5 6 5v11"/><path d="M10 22v-5h4v5M3 22h18"/>',
        'inbox' => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11L2 12v6a2 2 0 002 2h16a2 2 0 002-2v-6l-3.45-6.89A2 2 0 0016.76 4H7.24a2 2 0 00-1.79 1.11z"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'globe' => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/>',
        'eye' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off' => '<path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19M14.12 14.12a3 3 0 11-4.24-4.24M1 1l22 22"/>',
        'send' => '<path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/>',
        'external' => '<path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6M15 3h6v6M10 14L21 3"/>',
    ];
    return '<svg class="' . admin_e($class) . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . ($paths[$name] ?? '') . '</svg>';
}

/** A round picture of a person: their photo, else their initials */
function admin_avatar(?string $photo, string $name, string $class = 'adm-avatar'): string {
    $url = admin_photo_url($photo);
    if ($url !== '') {
        return '<img class="' . admin_e($class) . '" src="' . admin_e($url) . '" alt="" width="44" height="44" loading="lazy" decoding="async">';
    }
    return '<span class="' . admin_e($class) . ' adm-avatar--initials" aria-hidden="true">' . admin_e(admin_initials($name)) . '</span>';
}

/**
 * Which languages of a town's teaching have words in them (the editor's
 * rule: a file with only tags or an old placeholder is empty), how many,
 * and when the newest one was saved (a Unix time, 0 for never).
 */
function admin_teaching_status(string $province, string $town): array {
    require_once __DIR__ . '/teaching.php';
    $dir = teaching_dir($province, $town);
    $langs = [];
    $updated = 0;
    foreach (SUPPORTED_LANGS as $code) {
        $files = [teaching_file($dir, $code)];
        if ($code === 'en') {
            $files[] = $dir . '/' . TEACHING_LEGACY_EN_FILE;
        }
        $langs[$code] = false;
        foreach ($files as $file) {
            if (!is_file($file) || (int)@filesize($file) === 0) {
                continue;
            }
            if (teaching_has_content((string)@file_get_contents($file))) {
                $langs[$code] = true;
                $updated = max($updated, (int)@filemtime($file));
                break;
            }
        }
    }
    return ['langs' => $langs, 'done' => count(array_filter($langs)), 'total' => count($langs), 'updated' => $updated];
}

/** "/admin/css/admin.css" -> "/admin/css/admin.css?v=<file time>": cached until the file changes */
function admin_asset(string $path): string {
    $file = dirname(__DIR__) . $path;
    $v = is_file($file) ? (string)filemtime($file) : '0';
    return $path . '?v=' . $v;
}

/** "Jan van der Merwe" -> "JM" */
function admin_initials(string $name): string {
    $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (count($parts) >= 2) {
        return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[count($parts) - 1], 0, 1));
    }
    return $parts ? mb_strtoupper(mb_substr($parts[0], 0, 2)) : '';
}

/**
 * A profile photo's URL, or '' when there is none. Photos keep one file name
 * (profile_<id>.webp), so the file time goes on as ?v= and a new photo shows
 * at once instead of the browser's old copy.
 */
function admin_photo_url(?string $photo): string {
    $photo = trim((string)$photo);
    if ($photo === '') {
        return '';
    }
    if ($photo[0] === '/' && strpos($photo, '//') !== 0) {
        $file = dirname(__DIR__) . strtok($photo, '?');
        if (is_file($file)) {
            return strtok($photo, '?') . '?v=' . filemtime($file);
        }
    }
    return $photo;
}

/**
 * Store a member's profile photo: any JPEG / PNG / WebP up to 12 MB becomes
 * a 600x600 centre crop, saved as profile_<id>.webp (or .jpg without WebP
 * support). The old photo is only removed once the new one is safely on disk,
 * so a bad upload can never leave the member without their picture.
 * Returns the site-relative path, or null when the image was not usable.
 */
function admin_store_profile_photo(int $userId, string $binary): ?string {
    if ($userId <= 0 || $binary === '' || strlen($binary) > 12 * 1024 * 1024 || !function_exists('imagecreatefromstring')) {
        return null;
    }
    $info = @getimagesizefromstring($binary);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) || $info[0] > 8000 || $info[1] > 8000) {
        return null;
    }
    $src = @imagecreatefromstring($binary);
    if (!$src) {
        return null;
    }

    $w = imagesx($src);
    $h = imagesy($src);
    $side = min($w, $h);
    $canvas = imagecreatetruecolor(600, 600);
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    imagecopyresampled($canvas, $src, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), 600, 600, $side, $side);
    imagedestroy($src);

    $relDir = '/assets/uploads/' . $userId . '/profile';
    $dir = dirname(__DIR__) . $relDir;
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        imagedestroy($canvas);
        return null;
    }

    $webp = function_exists('imagewebp');
    $ext = $webp ? 'webp' : 'jpg';
    $tmp = $dir . '/.upload_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $ok = $webp ? @imagewebp($canvas, $tmp, 82) : @imagejpeg($canvas, $tmp, 85);
    imagedestroy($canvas);
    if (!$ok) {
        @unlink($tmp);
        return null;
    }

    // Only now does the old photo go
    foreach (glob($dir . '/*') ?: [] as $old) {
        if (is_file($old) && $old !== $tmp) {
            @unlink($old);
        }
    }
    $final = $dir . '/profile_' . $userId . '.' . $ext;
    if (!@rename($tmp, $final)) {
        @unlink($tmp);
        return null;
    }
    @chmod($final, 0644);

    return $relDir . '/profile_' . $userId . '.' . $ext;
}
