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

// UI Translations for all pages
define('UI_TRANSLATIONS', [
    // Welcome page
    'welcome' => [
        'af' => 'Welkom',
        'en' => 'Welcome',
        'zu' => 'Siyakwamukela',
        'xh' => 'Wamkelekile',
        'pt' => 'Bem-vindo'
    ],
    'welcome_title' => [
        'af' => 'Welkom by Die Ou Aposteliese Kerk',
        'en' => 'Welcome to The Old Apostolic Church',
        'zu' => 'Siyakwamukela eBandleni Elidala Labapostoli',
        'xh' => 'Wamkelekile kwiCawe yamaDala amaPostile',
        'pt' => 'Bem-vindo à Igreja Apostólica Antiga'
    ],
    'teaching_of_month' => [
        'af' => 'Lering van die Maand',
        'en' => 'Teaching of the Month',
        'zu' => 'Imfundiso Yenyanga',
        'xh' => 'Imfundiso Yenyanga',
        'pt' => 'Ensino do Mês'
    ],
    'grow_faith' => [
        'af' => 'Groei in geloof en kennis',
        'en' => 'Growing in faith and knowledge',
        'zu' => 'Ukukhula ekukholweni nasekwazini',
        'xh' => 'Ukukhula elukholweni naselwazini',
        'pt' => 'Crescendo em fé e conhecimento'
    ],
    'no_content' => [
        'af' => 'Geen lering-inhoud gevind nie.',
        'en' => 'No teaching content found.',
        'zu' => 'Akukho okuqukethwe okufundisayo okutholiwe.',
        'xh' => 'Akukho mfundiso ifunyenweyo.',
        'pt' => 'Nenhum conteúdo de ensino encontrado.'
    ],
    // Navigation
    'gospel_media' => [
        'af' => 'Evangelie Media',
        'en' => 'Gospel Media',
        'zu' => 'Imidiya YeVangeli',
        'xh' => 'Imidiya yeVangeli',
        'pt' => 'Mídia do Evangelho'
    ],
    'prayers' => [
        'af' => 'Gebede',
        'en' => 'Prayers',
        'zu' => 'Imithandazo',
        'xh' => 'Imithandazo',
        'pt' => 'Orações'
    ],
    'bible' => [
        'af' => 'Bybel',
        'en' => 'Bible',
        'zu' => 'IBhayibheli',
        'xh' => 'IBhayibhile',
        'pt' => 'Bíblia'
    ],
    'calendar' => [
        'af' => 'Kalender',
        'en' => 'Calendar',
        'zu' => 'Ikhalenda',
        'xh' => 'Ikhalenda',
        'pt' => 'Calendário'
    ],
    'diary' => [
        'af' => 'Dagboek',
        'en' => 'Diary',
        'zu' => 'Idayari',
        'xh' => 'Idayari',
        'pt' => 'Diário'
    ],
    'notifications' => [
        'af' => 'Kennisgewings',
        'en' => 'Notifications',
        'zu' => 'Izaziso',
        'xh' => 'Izaziso',
        'pt' => 'Notificações'
    ],
    'admin' => [
        'af' => 'Admin',
        'en' => 'Admin',
        'zu' => 'Umlawuli',
        'xh' => 'Umlawuli',
        'pt' => 'Admin'
    ],
    'logout' => [
        'af' => 'Teken uit',
        'en' => 'Log out',
        'zu' => 'Phuma',
        'xh' => 'Phuma',
        'pt' => 'Sair'
    ],
    'navigation' => [
        'af' => 'Navigasie',
        'en' => 'Navigation',
        'zu' => 'Ukuzulazula',
        'xh' => 'Ukuhamba',
        'pt' => 'Navegação'
    ],
    'close' => [
        'af' => 'Sluit',
        'en' => 'Close',
        'zu' => 'Vala',
        'xh' => 'Vala',
        'pt' => 'Fechar'
    ],
    'open_menu' => [
        'af' => 'Open menu',
        'en' => 'Open menu',
        'zu' => 'Vula imenyu',
        'xh' => 'Vula imenyu',
        'pt' => 'Abrir menu'
    ],
    'toggle_theme' => [
        'af' => 'Wissel Tema',
        'en' => 'Toggle Theme',
        'zu' => 'Shintsha Ithimu',
        'xh' => 'Tshintsha Ithimu',
        'pt' => 'Alternar Tema'
    ],
    'toggle_view' => [
        'af' => 'Wissel Aansig',
        'en' => 'Toggle View',
        'zu' => 'Shintsha Umbono',
        'xh' => 'Tshintsha Imbonakalo',
        'pt' => 'Alternar Visualização'
    ],
    'ai_smart_bible' => [
        'af' => 'AI Slimbybel',
        'en' => 'AI Smart Bible',
        'zu' => 'IBhayibheli Elihlakaniphile le-AI',
        'xh' => 'IBhayibhile eKreleyayo ye-AI',
        'pt' => 'Bíblia Inteligente IA'
    ],
    'sing_emmanuel' => [
        'af' => 'Sing Emmanuel',
        'en' => 'Sing Emmanuel',
        'zu' => 'Cula Emmanuel',
        'xh' => 'Cula Emmanuel',
        'pt' => 'Cantar Emmanuel'
    ],
    // Page titles
    'page_title_welcome' => [
        'af' => 'Welkom - OAC APP',
        'en' => 'Welcome - OAC APP',
        'zu' => 'Siyakwamukela - OAC APP',
        'xh' => 'Wamkelekile - OAC APP',
        'pt' => 'Bem-vindo - OAC APP'
    ],
    // Scripture quotes
    'quote_matthew_18_20' => [
        'af' => 'Want waar twee of drie in My Naam vergader is, daar is Ek in hulle midde.',
        'en' => 'For where two or three gather in my name, there am I with them.',
        'zu' => 'Ngoba lapho ababili noma abathathu behlangene egameni lami, ngikhona phakathi kwabo.',
        'xh' => 'Kuba apho kubandakanya ababini okanye abathathu egameni lam, ndilapho phakathi kwabo.',
        'pt' => 'Pois onde dois ou três estão reunidos em meu nome, ali estou eu no meio deles.'
    ],
    'quote_matthew_18_20_ref' => [
        'af' => 'Matthéüs 18:20',
        'en' => 'Matthew 18:20',
        'zu' => 'UMathewu 18:20',
        'xh' => 'UMateyu 18:20',
        'pt' => 'Mateus 18:20'
    ]
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

/**
 * Get a translated UI string
 * @param string $key The translation key
 * @param string $lang The language code
 * @return string The translated string or the English fallback
 */
function __t(string $key, string $lang): string {
    if (!isset(UI_TRANSLATIONS[$key])) {
        return $key;
    }
    return UI_TRANSLATIONS[$key][$lang] ?? UI_TRANSLATIONS[$key]['en'] ?? $key;
}
