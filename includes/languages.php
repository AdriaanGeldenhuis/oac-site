<?php
/**
 * Language Configuration - Single Source of Truth
 * All supported languages for the OAC App
 */
declare(strict_types=1);

// Supported language codes
define('SUPPORTED_LANGS', ['af', 'en', 'zu', 'xh', 'pt']);

// Display names for each language
define('LANG_NAMES', [
    'af' => 'Afrikaans',
    'en' => 'English',
    'zu' => 'isiZulu',
    'xh' => 'isiXhosa',
    'pt' => 'Português'
]);

// Bible file mapping per language
define('BIBLE_FILES', [
    'af' => 'af_1933_53.json',
    'en' => 'en_kjv1611.json',
    'zu' => 'zu_dummy.json',
    'xh' => 'xh_dummy.json',
    'pt' => 'pt_dummy.json'
]);

/**
 * Validate and return a supported language code
 * Falls back to 'en' if invalid
 */
function validate_language(string $lang): string {
    $lang = strtolower(trim($lang));
    return in_array($lang, SUPPORTED_LANGS, true) ? $lang : 'en';
}

/**
 * Get the display name for a language code
 */
function get_lang_name(string $lang): string {
    return LANG_NAMES[$lang] ?? LANG_NAMES['en'];
}

/**
 * Get the Bible filename for a language
 */
function get_bible_file(string $lang): string {
    return BIBLE_FILES[$lang] ?? BIBLE_FILES['en'];
}
