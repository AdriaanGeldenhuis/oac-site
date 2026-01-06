<?php
/**
 * Bible Helper - Server-side verse loading
 * Loads verses directly from Bible JSON files
 */
declare(strict_types=1);

require_once __DIR__ . '/../../includes/languages.php';

// Cache for loaded Bible data (per request)
$_BIBLE_CACHE = [];

/**
 * Get the full path to a Bible file for a language
 */
function bible_file_for_lang(string $lang): string {
    $bibleFile = get_bible_file($lang);
    return __DIR__ . '/../bibles/' . $bibleFile;
}

/**
 * Load Bible data for a language (cached per request)
 */
function bible_load(string $lang): ?array {
    global $_BIBLE_CACHE;

    $lang = validate_language($lang);

    if (isset($_BIBLE_CACHE[$lang])) {
        return $_BIBLE_CACHE[$lang];
    }

    $filePath = bible_file_for_lang($lang);

    if (!is_readable($filePath)) {
        $_BIBLE_CACHE[$lang] = null;
        return null;
    }

    $content = file_get_contents($filePath);
    if ($content === false) {
        $_BIBLE_CACHE[$lang] = null;
        return null;
    }

    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $_BIBLE_CACHE[$lang] = null;
        return null;
    }

    $_BIBLE_CACHE[$lang] = $data;
    return $data;
}

/**
 * Get a specific verse from the Bible
 *
 * @param string $lang Language code (af, en, zu, xh, pt)
 * @param string $book Book name (e.g., "Matthew", "Genesis")
 * @param int $chapter Chapter number
 * @param int $verse Verse number (1-based)
 * @return string|null The verse text, or null if not found
 */
function bible_get_verse(string $lang, string $book, int $chapter, int $verse): ?string {
    $bible = bible_load($lang);

    if (!$bible) {
        return null;
    }

    $chapterStr = (string)$chapter;

    // Check if book and chapter exist
    if (!isset($bible[$book]) || !isset($bible[$book][$chapterStr])) {
        return null;
    }

    $chapterData = $bible[$book][$chapterStr];

    // Verse index is 0-based, but verse numbers are 1-based
    $verseIndex = $verse - 1;

    // Count actual verses (skip headings)
    $verseCount = 0;
    foreach ($chapterData as $item) {
        if (isset($item['v'])) {
            $verseCount++;
            if ($verseCount === $verse) {
                return $item['v'];
            }
        }
    }

    return null;
}

/**
 * Check if a Bible is available for a language
 */
function bible_is_available(string $lang): bool {
    $bible = bible_load($lang);
    return $bible !== null && !empty($bible);
}

/**
 * Get list of books available in a Bible
 */
function bible_get_books(string $lang): array {
    $bible = bible_load($lang);

    if (!$bible) {
        return [];
    }

    return array_keys($bible);
}
