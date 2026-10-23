<?php
// =====================================================================
// /includes/verse_of_day.php — Verse of the Day helper
// ---------------------------------------------------------------------
// Reads the small pre-built /welcome/data/verses.json (see
// /welcome/tools/build_verses.php) and picks one verse per calendar day.
// Everyone gets the same verse on the same (South African) day.
// =====================================================================
declare(strict_types=1);

require_once __DIR__ . '/languages.php';

const VOTD_TZ = 'Africa/Johannesburg';
const VOTD_MAX_BACK = 30; // how many days back the user may browse

function votd_today(): DateTimeImmutable {
    return new DateTimeImmutable('today', new DateTimeZone(VOTD_TZ));
}

function votd_load(): array {
    static $verses = null;
    if ($verses !== null) {
        return $verses;
    }
    $file = __DIR__ . '/../welcome/data/verses.json';
    $data = is_readable($file) ? json_decode((string)file_get_contents($file), true) : null;
    $verses = (is_array($data) && !empty($data['verses']) && is_array($data['verses'])) ? $data['verses'] : [];
    return $verses;
}

function votd_gcd(int $a, int $b): int {
    while ($b !== 0) {
        [$a, $b] = [$b, $a % $b];
    }
    return $a;
}

/**
 * Bible translation shown for a UI language. Only Afrikaans and English
 * Bibles exist; every other language reads the English (KJV) text.
 */
function votd_bible_lang(string $lang): string {
    return $lang === 'af' ? 'af' : 'en';
}

/**
 * Localised "Friday 23 October" style label using the central translations
 */
function votd_date_label(DateTimeImmutable $date, string $lang): string {
    $days = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
    $months = ['january', 'february', 'march', 'april', 'may', 'june', 'july',
               'august', 'september', 'october', 'november', 'december'];
    $day = __t($days[(int)$date->format('w')], $lang);
    $month = __t($months[(int)$date->format('n') - 1], $lang);
    return $day . ' ' . $date->format('j') . ' ' . $month;
}

/**
 * Verse for "today + $offset days" (offset <= 0).
 * Returns null when the data file is missing.
 */
function votd_for_offset(int $offset, string $lang): ?array {
    $verses = votd_load();
    $count = count($verses);
    if ($count === 0) {
        return null;
    }

    $offset = max(-VOTD_MAX_BACK, min(0, $offset));
    $date = votd_today()->modify($offset . ' days');

    // Day number since 1970 in the SA calendar, stepped through the list
    // with a multiplier so consecutive days jump between books/testaments
    $dayNo = intdiv((int)(new DateTimeImmutable($date->format('Y-m-d'), new DateTimeZone('UTC')))->getTimestamp(), 86400);
    $step = votd_gcd(53, $count) === 1 ? 53 : 1;
    $verse = $verses[($dayNo * $step) % $count];

    $primary = votd_bible_lang($lang);
    return [
        'offset'     => $offset,
        'date'       => $date->format('Y-m-d'),
        'date_label' => votd_date_label($date, $lang),
        'primary'    => $primary,
        'af'         => $verse['af'],
        'en'         => $verse['en'],
    ];
}
