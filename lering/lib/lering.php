<?php
declare(strict_types=1);
/**
 * Lering — shared server code for /lering/lering.php.
 *
 * Two things live on the page, one tab each:
 *   - the Teaching of the Month: the elder's text for the reader's town
 *     (includes/teaching.php, unchanged storage under /welcome/...)
 *   - the Year theme card: background, theme, text and objectives, the
 *     same for the whole church, from lering/data/jaartema.json
 *
 * Both are drawn into the page by PHP, so the app's offline copy of the
 * page (sw.js) holds them both: switching tabs never needs the internet.
 */

require_once dirname(__DIR__, 2) . '/includes/languages.php';
require_once dirname(__DIR__, 2) . '/includes/teaching.php';

const LR_YEAR_FILE = __DIR__ . '/../data/jaartema.json';
const LR_YEAR_WEB_PATH = '/lering/data/jaartema.json';   // for oac_data_version()

function lr_e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function lr_str($v): string {
    return is_string($v) ? trim($v) : '';
}

/** "3 November 2026" in the page language */
function lr_date(int $ts, string $lang): string {
    $d = (new DateTimeImmutable('@' . $ts))->setTimezone(new DateTimeZone('Africa/Johannesburg'));
    $months = ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'];
    return (int)$d->format('j') . ' ' . __t($months[(int)$d->format('n') - 1], $lang) . ' ' . $d->format('Y');
}

/**
 * Bible page link: /bible/bible.php?ref=1%20Corinthians-1-5 (bible.js opens
 * there; the app's offline copy of the Bible page answers it too)
 */
function lr_bible_href(string $bookEN, int $chapter, int $verse): string {
    return '/bible/bible.php?ref=' . rawurlencode($bookEN . '-' . max(1, $chapter) . '-' . max(1, $verse));
}

/**
 * The year theme card in the reader's language, else English, else the
 * first language the file has. null when the file is missing or broken.
 *
 * @return array{year:string, lang:string, fallback:bool, card:array, bible:?array}|null
 */
function lr_year_theme(string $lang): ?array {
    $raw = @file_get_contents(LR_YEAR_FILE);
    if ($raw === false) {
        error_log('Lering: year theme file missing: ' . LR_YEAR_FILE);
        return null;
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['langs']) || !is_array($data['langs']) || !$data['langs']) {
        error_log('Lering: year theme file is not valid JSON');
        return null;
    }

    $langs = $data['langs'];
    $use = isset($langs[$lang]) ? $lang : (isset($langs['en']) ? 'en' : (string)array_key_first($langs));
    $src = is_array($langs[$use] ?? null) ? $langs[$use] : [];

    // Only well-formed parts reach the page
    $background = [];
    foreach ((array)($src['background'] ?? []) as $p) {
        if (lr_str($p) !== '') {
            $background[] = lr_str($p);
        }
    }

    $objectives = [];
    foreach ((array)($src['objectives'] ?? []) as $o) {
        if (!is_array($o) || lr_str($o['text'] ?? null) === '') {
            continue;
        }
        $v = is_array($o['v'] ?? null) ? array_values($o['v']) : [];
        $objectives[] = [
            'text' => lr_str($o['text']),
            'ref'  => lr_str($o['ref'] ?? null),
            'from' => isset($v[0]) && is_int($v[0]) ? $v[0] : 0,
            'end'  => is_string($o['end'] ?? null) ? $o['end'] : '',
        ];
    }

    $bible = null;
    $b = $data['bible'] ?? null;
    if (is_array($b) && lr_str($b['book'] ?? null) !== '' && is_int($b['chapter'] ?? null)) {
        $bible = [
            'book'    => lr_str($b['book']),
            'chapter' => (int)$b['chapter'],
            'from'    => is_int($b['from'] ?? null) ? (int)$b['from'] : 1,
        ];
    }

    return [
        'year'     => lr_str($data['year'] ?? null),
        'lang'     => $use,
        'fallback' => $use !== $lang,
        'bible'    => $bible,
        'card'     => [
            'title'            => lr_str($src['title'] ?? null),
            'background_label' => lr_str($src['background_label'] ?? null),
            'background'       => $background,
            'theme_label'      => lr_str($src['theme_label'] ?? null),
            'theme'            => lr_str($src['theme'] ?? null),
            'text_label'       => lr_str($src['text_label'] ?? null),
            'text'             => lr_str($src['text'] ?? null),
            'objectives_label' => lr_str($src['objectives_label'] ?? null),
            'objectives_intro' => lr_str($src['objectives_intro'] ?? null),
            'objectives'       => $objectives,
        ],
    ];
}

/** a, b, c ... for the objectives, like the printed card */
function lr_letter(int $i): string {
    return $i < 26 ? chr(97 + $i) : (string)($i + 1);
}
