<?php
declare(strict_types=1);
/**
 * Small helpers the admin pages share (index.php, account.php and the tabs).
 */

/** HTML-escape for output */
function admin_e($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** "First Last" from a users row (or any array with name / surname) */
function admin_full_name(?array $u, string $nameKey = 'name', string $surnameKey = 'surname'): string {
    return trim((string)($u[$nameKey] ?? '') . ' ' . (string)($u[$surnameKey] ?? ''));
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
