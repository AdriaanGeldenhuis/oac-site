<?php
// =====================================================================
// /welcome/tools/build_verses.php — Build the Verse of the Day data
// ---------------------------------------------------------------------
// CLI only:  php welcome/tools/build_verses.php
//
// Reads the curated reference list below, pulls the verse text straight
// out of the Bible JSON files (AF 1933/53 + KJV) and writes a small
// /welcome/data/verses.json. The welcome page and /welcome/api/verse.php
// read that small file, so the 5 MB Bibles are never parsed per request.
//
// Format per line:  'English Book ch:v'  or  'English Book ch:v|ch:v'
// The part after "|" is the Afrikaans chapter:verse when the 1933/53
// numbering differs (mostly Psalms, where the heading is verse 1).
// =====================================================================
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__, 2);

$REFS = [
    // ----- Old Testament -----
    'Genesis 1:1',
    'Genesis 28:15',
    'Exodus 14:14',
    'Exodus 15:2',
    'Numbers 6:24',
    'Deuteronomy 31:6',
    'Deuteronomy 31:8',
    'Joshua 1:9',
    'Ruth 1:16',
    '1 Samuel 16:7',
    '1 Chronicles 16:34',
    '2 Chronicles 7:14',
    'Job 19:25',
    'Psalms 4:8|4:9',
    'Psalms 16:11',
    'Psalms 18:2|18:3',
    'Psalms 19:14|19:15',
    'Psalms 30:5|30:6',
    'Psalms 37:4',
    'Psalms 46:1|46:2',
    'Psalms 46:10|46:11',
    'Psalms 51:10|51:12',
    'Psalms 55:22|55:23',
    'Psalms 56:3|56:4',
    'Psalms 73:26',
    'Psalms 84:11|84:12',
    'Psalms 90:12',
    'Psalms 91:1',
    'Psalms 91:11',
    'Psalms 103:2',
    'Psalms 118:24',
    'Psalms 119:105',
    'Psalms 121:2',
    'Psalms 136:1',
    'Psalms 139:14',
    'Psalms 143:8',
    'Psalms 147:3',
    'Proverbs 3:5',
    'Proverbs 3:6',
    'Proverbs 4:23',
    'Proverbs 16:3',
    'Proverbs 18:10',
    'Ecclesiastes 3:1',
    'Isaiah 9:6|9:5',
    'Isaiah 26:3',
    'Isaiah 40:8',
    'Isaiah 40:31',
    'Isaiah 41:10',
    'Isaiah 43:2',
    'Isaiah 53:5',
    'Isaiah 54:10',
    'Isaiah 55:8',
    'Jeremiah 29:11',
    'Jeremiah 33:3',
    'Lamentations 3:23',
    'Ezekiel 36:26',
    'Micah 6:8',
    'Nahum 1:7',
    'Zephaniah 3:17',
    'Zechariah 4:6',
    // ----- New Testament -----
    'Matthew 5:9',
    'Matthew 5:14',
    'Matthew 5:16',
    'Matthew 6:33',
    'Matthew 6:34',
    'Matthew 7:7',
    'Matthew 11:28',
    'Matthew 28:20',
    'Mark 9:23',
    'Mark 10:27',
    'Mark 11:24',
    'Luke 1:37',
    'Luke 6:31',
    'John 1:5',
    'John 3:16',
    'John 8:12',
    'John 10:10',
    'John 11:25',
    'John 14:6',
    'John 14:27',
    'John 15:5',
    'John 15:13',
    'John 16:33',
    'Acts 1:8',
    'Acts 16:31',
    'Romans 5:8',
    'Romans 8:1',
    'Romans 8:18',
    'Romans 8:28',
    'Romans 8:31',
    'Romans 10:9',
    'Romans 12:2',
    'Romans 12:12',
    'Romans 15:13',
    '1 Corinthians 10:13',
    '1 Corinthians 13:4',
    '1 Corinthians 13:13',
    '1 Corinthians 16:14',
    '2 Corinthians 5:7',
    '2 Corinthians 5:17',
    '2 Corinthians 12:9',
    'Galatians 2:20',
    'Galatians 5:22',
    'Galatians 6:9',
    'Ephesians 2:8',
    'Ephesians 3:20',
    'Ephesians 4:32',
    'Ephesians 6:10',
    'Philippians 1:6',
    'Philippians 4:6',
    'Philippians 4:7',
    'Philippians 4:13',
    'Philippians 4:19',
    'Colossians 3:23',
    '1 Thessalonians 5:18',
    '2 Thessalonians 3:3',
    '1 Timothy 4:12',
    '2 Timothy 1:7',
    '2 Timothy 3:16',
    'Hebrews 4:16',
    'Hebrews 11:1',
    'Hebrews 12:2',
    'Hebrews 13:5',
    'Hebrews 13:8',
    'James 1:5',
    'James 1:17',
    'James 4:8',
    '1 Peter 5:7',
    '2 Peter 3:9',
    '1 John 1:9',
    '1 John 4:8',
    '1 John 4:18',
    '1 John 4:19',
    'Jude 1:24',
    'Revelation 3:20',
    'Revelation 21:4',
    'Revelation 22:13',
];

function load_bible(string $path): array {
    $data = json_decode((string)file_get_contents($path), true);
    if (!is_array($data)) {
        fwrite(STDERR, "Cannot read $path\n");
        exit(1);
    }
    return $data;
}

// Verse numbers count only {"v": ...} items; {"h": ...} items are headings
function verse_text(array $bible, string $book, int $chapter, int $verse): ?string {
    $n = 0;
    foreach ($bible[$book][(string)$chapter] ?? [] as $item) {
        if (isset($item['v'])) {
            $n++;
            if ($n === $verse) {
                return trim($item['v']);
            }
        }
    }
    return null;
}

// Chapter/section openings are printed in capitals ("IN die begin").
// Soften that first word for display, but leave the divine names alone.
function soften_opening(string $text): string {
    if (!preg_match('/^(\p{Lu}{2,})(\b.*)$/us', $text, $m)) {
        return $text;
    }
    if (in_array($m[1], ['LORD', 'GOD', 'HERE', 'HEER', 'JEHOVAH'], true)) {
        return $text;
    }
    $word = mb_strtolower($m[1], 'UTF-8');
    return mb_strtoupper(mb_substr($word, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($word, 1, null, 'UTF-8') . $m[2];
}

$af = load_bible($root . '/bible/bibles/af_1933_53.json');
$en = load_bible($root . '/bible/bibles/en_kjv1611.json');
$enBooks = array_keys($en);
$afBooks = array_keys($af);

$out = [];
foreach ($REFS as $line) {
    [$main, $afOverride] = array_pad(explode('|', $line, 2), 2, null);
    if (!preg_match('/^(.+) (\d+):(\d+)$/', $main, $m)) {
        fwrite(STDERR, "Bad reference: $line\n");
        exit(1);
    }
    $bookEn = $m[1];
    $idx = array_search($bookEn, $enBooks, true);
    if ($idx === false) {
        fwrite(STDERR, "Unknown book: $bookEn\n");
        exit(1);
    }
    $bookAf = $afBooks[$idx];
    $chEn = (int)$m[2];
    $vEn = (int)$m[3];
    [$chAf, $vAf] = $afOverride ? array_map('intval', explode(':', $afOverride)) : [$chEn, $vEn];

    $textEn = verse_text($en, $bookEn, $chEn, $vEn);
    $textAf = verse_text($af, $bookAf, $chAf, $vAf);
    if ($textEn === null || $textAf === null) {
        fwrite(STDERR, "Verse not found: $line\n");
        exit(1);
    }

    // Refs use the English book name — the format /bible/bible.php?ref= expects
    $out[] = [
        'en' => [
            'ref'   => "$bookEn-$chEn-$vEn",
            'label' => "$bookEn $chEn:$vEn",
            'text'  => soften_opening($textEn),
        ],
        'af' => [
            'ref'   => "$bookEn-$chAf-$vAf",
            'label' => "$bookAf $chAf:$vAf",
            'text'  => soften_opening($textAf),
        ],
    ];
}

$json = json_encode(
    ['count' => count($out), 'verses' => $out],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
);
$target = $root . '/welcome/data/verses.json';
if (!is_dir(dirname($target))) {
    mkdir(dirname($target), 0775, true);
}
file_put_contents($target, $json . "\n");
echo 'Wrote ' . count($out) . " verses to $target\n";
