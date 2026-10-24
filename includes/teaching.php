<?php
/**
 * Teaching of the Month - shared helpers
 * ======================================
 * Single source of truth for WHERE the monthly teaching lives, WHICH file
 * belongs to which language, HOW the welcome page falls back when a
 * translation is missing, and HOW stored HTML is made safe.
 *
 * Used by:
 *   - /admin/elders.php                    (editor - loads every language)
 *   - /admin/api/elders/save_teaching.php  (writes / removes the files)
 *   - /welcome.php                         (shows the teaching to members)
 *
 * Storage layout (relative to the web root):
 *   /welcome/south_africa/<province_slug>/<town_slug>/lering_content.html        (af)
 *   /welcome/south_africa/<province_slug>/<town_slug>/teaching_content.<xx>.html (other)
 *   /welcome/...                                                                 (no town)
 *
 * These files are user content: they are NOT tracked in git and the deploy
 * excludes them, otherwise every deploy would put old copies back.
 */
declare(strict_types=1);

require_once __DIR__ . '/languages.php';

// Base directory of all teaching files
define('TEACHING_BASE_DIR', dirname(__DIR__) . '/welcome');

// File name per language - MUST cover every entry in SUPPORTED_LANGS
define('TEACHING_FILES', [
    'af' => 'lering_content.html',
    'en' => 'teaching_content.en.html',
    'zu' => 'teaching_content.zu.html',
    'xh' => 'teaching_content.xh.html',
    'pt' => 'teaching_content.pt.html',
    'st' => 'teaching_content.st.html'
]);

// Pre-2025 English file. Shown in the editor's English tab until the first
// save writes teaching_content.en.html, then removed.
define('TEACHING_LEGACY_EN_FILE', 'teaching_content.html');

// Max stored size per language (a long teaching is ~30 KB)
define('TEACHING_MAX_BYTES', 600000);

// Placeholder texts the old editor saved as if they were content for every
// empty language. Files holding only one of these count as empty.
define('TEACHING_OLD_PLACEHOLDERS', [
    'Begin tik hier...',
    'Start typing here...',
    'Qala ukuthayipha lapha...',
    'Qala ukuchwetheza apha...',
    'Comece a digitar aqui...',
    'Qala ho thaepa mona...'
]);

/**
 * Folder-safe slug. Keep identical to the historical logic, existing
 * folders on the server depend on it.
 */
function teaching_slug(string $text): string {
    $text = strtolower(trim($text));
    $text = (string)preg_replace('/[^a-z0-9]+/', '_', $text);
    return trim($text, '_');
}

/**
 * Folder that holds the teaching for a town. Without a known province AND
 * town the shared /welcome folder is used.
 */
function teaching_dir(string $province, string $town): string {
    $p = teaching_slug($province);
    $t = teaching_slug($town);
    if ($p === '' || $t === '' || $p === 'unknown' || $t === 'unknown') {
        return TEACHING_BASE_DIR;
    }
    return TEACHING_BASE_DIR . '/south_africa/' . $p . '/' . $t;
}

function teaching_file(string $dir, string $lang): string {
    return $dir . '/' . (TEACHING_FILES[$lang] ?? TEACHING_FILES['en']);
}

/**
 * Exactly what the editor shows per language tab (no cross-language
 * fallback, so every tab is "what you see is what members get").
 * @return array<string,string> lang => html ('' when nothing is stored)
 */
function teaching_load_all(string $dir): array {
    $out = [];
    foreach (SUPPORTED_LANGS as $code) {
        // English: the legacy file stands in until a real .en file has content
        $files = [teaching_file($dir, $code)];
        if ($code === 'en') {
            $files[] = $dir . '/' . TEACHING_LEGACY_EN_FILE;
        }
        $out[$code] = '';
        foreach ($files as $file) {
            $html = is_file($file) ? teaching_sanitize_html((string)@file_get_contents($file)) : '';
            if (teaching_has_content($html)) {
                $out[$code] = $html;
                break;
            }
        }
    }
    return $out;
}

/**
 * The teaching HTML (sanitized) the welcome page shows for a reader, or ''.
 * Order:
 *   town:   own language -> English -> legacy English -> Afrikaans
 *   shared: own language -> English -> legacy English -> Afrikaans
 * A town's own teaching in another language beats the shared fallback, and
 * files without real content (e.g. an old placeholder) are skipped.
 */
function teaching_reader_html(string $townDir, string $lang): string {
    $dirs = [];
    if ($townDir !== '' && $townDir !== TEACHING_BASE_DIR) {
        $dirs[] = $townDir;
    }
    $dirs[] = TEACHING_BASE_DIR;

    $order = array_values(array_unique([$lang, 'en', '__legacy', 'af']));

    foreach ($dirs as $dir) {
        foreach ($order as $code) {
            $file = $code === '__legacy'
                ? $dir . '/' . TEACHING_LEGACY_EN_FILE
                : teaching_file($dir, $code);
            if (!is_file($file) || !is_readable($file) || filesize($file) === 0) {
                continue;
            }
            $html = teaching_sanitize_html((string)@file_get_contents($file));
            if (teaching_has_content($html)) {
                return $html;
            }
        }
    }
    return '';
}

/**
 * Does the HTML contain anything a reader would see?
 */
function teaching_has_content(string $html): bool {
    if ($html === '') {
        return false;
    }
    if (preg_match('/<(img|hr|table)\b/i', $html)) {
        return true;
    }
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $plain = trim((string)preg_replace('/[\s\x{00A0}\x{200B}\x{FEFF}]+/u', ' ', $text));
    return $plain !== '' && !in_array($plain, TEACHING_OLD_PLACEHOLDERS, true);
}

// =============================================================================
// SANITIZER - allow-list based, DOM driven
// =============================================================================

// Removed together with everything inside them
const TEACHING_DROP_TAGS = [
    'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet',
    'form', 'input', 'button', 'textarea', 'select', 'option', 'optgroup', 'datalist',
    'noscript', 'template', 'link', 'meta', 'base', 'head', 'title', 'svg', 'math',
    'canvas', 'audio', 'video', 'source', 'track', 'param', 'xml', 'dialog', 'map'
];

// Allowed tags => allowed attributes (style/class are handled for all)
const TEACHING_ALLOWED_TAGS = [
    'p' => [], 'div' => [], 'br' => [], 'hr' => [],
    'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
    'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [],
    'strike' => [], 'del' => [], 'ins' => [], 'sub' => [], 'sup' => [],
    'span' => [], 'mark' => [], 'small' => [], 'font' => ['color', 'size'],
    'blockquote' => [], 'pre' => [], 'code' => [],
    'ul' => [], 'ol' => ['start', 'type'], 'li' => [],
    'a' => ['href', 'target', 'rel', 'title'],
    'img' => ['src', 'alt', 'title', 'width', 'height'],
    'table' => [], 'thead' => [], 'tbody' => [], 'tfoot' => [], 'tr' => [],
    'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'], 'caption' => []
];

// Classes with meaning on the welcome page (Bible verse blocks)
const TEACHING_ALLOWED_CLASSES = ['vref', 'vtxt', 'verse-ref', 'verse-text'];

const TEACHING_ALLOWED_CSS = [
    'color', 'background-color', 'font-size', 'font-weight', 'font-style',
    'text-decoration', 'text-decoration-line', 'text-align', 'line-height',
    'margin-left', 'padding-left', 'vertical-align'
];

function teaching_sanitize_html(string $html): string {
    $html = trim($html);
    if ($html === '') {
        return '';
    }
    if (!class_exists('DOMDocument')) {
        return teaching_sanitize_html_fallback($html);
    }

    $doc = new DOMDocument('1.0', 'UTF-8');
    $prev = libxml_use_internal_errors(true);
    $ok = $doc->loadHTML(
        '<?xml encoding="UTF-8"?><!DOCTYPE html><html><head><meta charset="utf-8"></head><body><div id="__teach_root">'
        . $html . '</div></body></html>',
        LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
    );
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    $root = $ok ? $doc->getElementById('__teach_root') : null;
    if (!$root) {
        return teaching_sanitize_html_fallback($html);
    }

    teaching_clean_children($root);

    $out = '';
    foreach (iterator_to_array($root->childNodes) as $child) {
        $out .= $doc->saveHTML($child);
    }
    return trim($out);
}

function teaching_clean_children(DOMNode $parent): void {
    foreach (iterator_to_array($parent->childNodes) as $node) {
        if ($node instanceof DOMText) {
            continue; // includes CDATA
        }
        if (!($node instanceof DOMElement)) {
            $parent->removeChild($node); // comments, processing instructions
            continue;
        }

        $tag = strtolower($node->nodeName);

        // Namespaced Office tags (w:*, v:*, m:*) carry no text worth keeping;
        // o:p is Word's paragraph marker and is simply unwrapped below
        if (in_array($tag, TEACHING_DROP_TAGS, true) || (strpos($tag, ':') !== false && $tag !== 'o:p')) {
            $parent->removeChild($node);
            continue;
        }

        teaching_clean_children($node);

        // The browser's old "indent" button made
        // <blockquote style="margin: 0 0 0 40px; border: none; padding: 0px">,
        // which the welcome page shows as a quote box. Keep it as an indent.
        if ($tag === 'blockquote' && preg_match('/border\s*:\s*none/i', $node->getAttribute('style'))) {
            $raw = $node->getAttribute('style');
            $px = 40;
            if (preg_match('/margin\s*:\s*[\d.]+(?:px)?\s+[\d.]+(?:px)?\s+[\d.]+(?:px)?\s+([\d.]+)px/i', $raw, $m)
                || preg_match('/margin-left\s*:\s*([\d.]+)px/i', $raw, $m)) {
                $px = (int)round((float)$m[1]);
            }
            $div = $node->ownerDocument->createElement('div');
            while ($node->firstChild) {
                $div->appendChild($node->firstChild);
            }
            if ($px > 0) {
                $div->setAttribute('style', 'margin-left: ' . min($px, 200) . 'px');
            }
            $parent->replaceChild($div, $node);
            continue;
        }

        if (!array_key_exists($tag, TEACHING_ALLOWED_TAGS)) {
            // Unknown wrapper (section, center, o:p, ...): keep its content
            while ($node->firstChild) {
                $parent->insertBefore($node->firstChild, $node);
            }
            $parent->removeChild($node);
            continue;
        }

        teaching_clean_attributes($node, $tag);

        if ($tag === 'img' && !$node->hasAttribute('src')) {
            $parent->removeChild($node);
        }
    }
}

function teaching_clean_attributes(DOMElement $el, string $tag): void {
    $allowed = TEACHING_ALLOWED_TAGS[$tag];

    foreach (iterator_to_array($el->attributes) as $attr) {
        $name = strtolower($attr->nodeName);
        $value = trim((string)$attr->nodeValue);
        $keep = null;

        if ($name === 'style') {
            $keep = teaching_sanitize_style($value);
        } elseif ($name === 'class') {
            $classes = array_intersect(preg_split('/\s+/', $value) ?: [], TEACHING_ALLOWED_CLASSES);
            $keep = implode(' ', $classes);
        } elseif (in_array($name, $allowed, true)) {
            switch ($name) {
                case 'href':
                    $keep = teaching_safe_url($value, false);
                    break;
                case 'src':
                    $keep = teaching_safe_url($value, true);
                    break;
                case 'target':
                    $keep = $value === '_blank' ? '_blank' : '';
                    break;
                case 'rel':
                    $keep = 'noopener noreferrer';
                    break;
                case 'start':
                case 'colspan':
                case 'rowspan':
                case 'width':
                case 'height':
                    $keep = preg_match('/^\d{1,4}$/', $value) ? $value : '';
                    break;
                case 'type':
                    $keep = preg_match('/^[1aAiI]$/', $value) ? $value : '';
                    break;
                case 'size':
                    $keep = preg_match('/^[1-7]$/', $value) ? $value : '';
                    break;
                case 'color':
                    $keep = teaching_safe_css_color($value) ? $value : '';
                    break;
                default: // alt, title
                    $keep = mb_substr($value, 0, 300);
            }
        }

        if ($keep === null || $keep === '') {
            $el->removeAttribute($attr->nodeName);
        } elseif ($keep !== $attr->nodeValue) {
            $el->setAttribute($name, $keep);
        }
    }

    if ($tag === 'a' && $el->getAttribute('target') === '_blank') {
        $el->setAttribute('rel', 'noopener noreferrer');
    }
}

function teaching_sanitize_style(string $style): string {
    $out = [];
    foreach (explode(';', $style) as $decl) {
        $pos = strpos($decl, ':');
        if ($pos === false) {
            continue;
        }
        $prop = strtolower(trim(substr($decl, 0, $pos)));
        $val = trim(str_ireplace('!important', '', substr($decl, $pos + 1)));
        if ($val === '' || strlen($val) > 80) {
            continue;
        }
        // Never anything that can load or run something
        if (preg_match('/url\s*\(|expression|javascript|behavior|binding|@import|[\\\\<>"\'{}]/i', $val)) {
            continue;
        }
        // Background shorthand that is just a colour (Word highlights)
        if ($prop === 'background' && teaching_safe_css_color($val)) {
            $prop = 'background-color';
        }
        if (!in_array($prop, TEACHING_ALLOWED_CSS, true)) {
            continue;
        }

        $valid = false;
        switch ($prop) {
            case 'color':
            case 'background-color':
                $valid = teaching_safe_css_color($val);
                break;
            case 'font-size':
                $valid = (bool)preg_match('/^(\d{1,3}(\.\d+)?(px|pt|em|rem|%)|xx-small|x-small|small|medium|large|x-large|xx-large|smaller|larger)$/i', $val);
                break;
            case 'line-height':
                $valid = (bool)preg_match('/^(normal|\d{1,2}(\.\d+)?(px|em|%)?)$/i', $val);
                break;
            case 'margin-left':
            case 'padding-left':
                $valid = (bool)preg_match('/^\d{1,3}(\.\d+)?(px|pt|em|rem|%)?$/i', $val);
                break;
            case 'font-weight':
                $valid = (bool)preg_match('/^(normal|bold|bolder|lighter|[1-9]00)$/i', $val);
                break;
            case 'font-style':
                $valid = (bool)preg_match('/^(normal|italic|oblique)$/i', $val);
                break;
            case 'text-decoration':
            case 'text-decoration-line':
                $valid = (bool)preg_match('/^((none|underline|line-through|overline)\s*)+$/i', $val);
                break;
            case 'text-align':
                $valid = (bool)preg_match('/^(left|right|center|justify|start|end)$/i', $val);
                break;
            case 'vertical-align':
                $valid = (bool)preg_match('/^(baseline|super|sub|top|middle|bottom|text-top|text-bottom)$/i', $val);
                break;
        }
        if ($valid) {
            $out[$prop] = $prop . ': ' . $val;
        }
    }
    return implode('; ', $out);
}

function teaching_safe_css_color(string $val): bool {
    return (bool)preg_match(
        '/^(#[0-9a-f]{3,8}|rgba?\(\s*[\d.]+%?\s*,\s*[\d.]+%?\s*,\s*[\d.]+%?\s*(,\s*[\d.]+%?\s*)?\)|hsla?\(\s*[\d.]+(deg)?\s*,\s*[\d.]+%\s*,\s*[\d.]+%\s*(,\s*[\d.]+%?\s*)?\)|[a-z]{3,20})$/i',
        trim($val)
    );
}

/**
 * Only http(s), mailto, tel, site-relative and #anchors. Images: https or
 * site-relative only (no data:, no file:, no blob:).
 */
function teaching_safe_url(string $url, bool $isImage): string {
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    $probe = strtolower((string)preg_replace('/[\x00-\x20]+/', '', $url));

    if ($isImage) {
        if (strpos($probe, 'https://') === 0 || (strpos($probe, '/') === 0 && strpos($probe, '//') !== 0)) {
            return $url;
        }
        return '';
    }

    if (preg_match('#^(https?://|mailto:|tel:|/|\#)#', $probe)) {
        return $url;
    }
    // Relative link without a scheme ("page.php", "?a=1")
    if (!preg_match('/^[a-z][a-z0-9+.\-]*:/', $probe)) {
        return $url;
    }
    return '';
}

/**
 * Regex fallback for servers without ext-dom (should never be needed)
 */
function teaching_sanitize_html_fallback(string $html): string {
    foreach (TEACHING_DROP_TAGS as $tag) {
        $html = (string)preg_replace('/<' . $tag . '\b[^>]*>.*?<\/' . $tag . '\s*>/is', '', $html);
        $html = (string)preg_replace('/<\/?' . $tag . '\b[^>]*>/i', '', $html);
    }
    $html = (string)preg_replace('/<!--.*?-->/s', '', $html);
    $html = strip_tags($html, '<' . implode('><', array_keys(TEACHING_ALLOWED_TAGS)) . '>');
    $html = (string)preg_replace('/[\s\/]+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', ' ', $html);
    $html = (string)preg_replace('/(href|src)\s*=\s*("|\')?\s*(javascript|vbscript|data|file):[^"\'>\s]*("|\')?/i', '', $html);
    $html = (string)preg_replace('/expression\s*\(|url\s*\(/i', '', $html);
    return trim($html);
}
