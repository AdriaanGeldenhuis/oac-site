<?php
declare(strict_types=1);
/**
 * A very small PDF writer for the calendar export: A4 pages, Helvetica
 * text (WinAnsi, which covers Afrikaans and Portuguese accents), filled
 * rectangles and lines. No library needed on the server, and the result is
 * a real PDF that phones, the app and printers all open.
 */

final class CalPdf
{
    public const W = 595.28;
    public const H = 841.89;
    public const MARGIN = 40.0;

    /** Helvetica glyph widths (1/1000 em) for ASCII 32–126 */
    private const WIDTHS = [
        278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278,
        556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
        1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778,
        667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
        333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556,
        556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584,
    ];

    /** @var string[] */
    private array $pages = [];
    private string $current = '';

    public function addPage(): void
    {
        if ($this->current !== '') {
            $this->pages[] = $this->current;
        }
        $this->current = ' ';
    }

    /** UTF-8 → WinAnsi (Windows-1252); characters it lacks become "?" */
    public static function encode(string $s): string
    {
        $s = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s) ?? '';
        $out = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s);
        if ($out === false) {
            $out = function_exists('mb_convert_encoding') ? mb_convert_encoding($s, 'Windows-1252', 'UTF-8') : $s;
        }
        return $out;
    }

    private static function escape(string $winAnsi): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $winAnsi);
    }

    private static function num(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }

    /** Width in points of a UTF-8 string */
    public function width(string $s, float $size, bool $bold = false): float
    {
        $enc = self::encode($s);
        $w = 0;
        $len = strlen($enc);
        for ($i = 0; $i < $len; $i++) {
            $c = ord($enc[$i]);
            $w += ($c >= 32 && $c <= 126) ? self::WIDTHS[$c - 32] : 556;
        }
        return $w * $size / 1000 * ($bold ? 1.06 : 1.0);
    }

    /** Break text into lines no wider than $max (long words are cut) */
    public function wrap(string $s, float $max, float $size, bool $bold = false, int $maxLines = 6): array
    {
        $lines = [];
        foreach (preg_split('/\n/', str_replace(["\r\n", "\r"], "\n", $s)) ?: [] as $para) {
            $words = preg_split('/\s+/u', trim($para), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $line = '';
            foreach ($words as $word) {
                $try = $line === '' ? $word : $line . ' ' . $word;
                if ($this->width($try, $size, $bold) <= $max) {
                    $line = $try;
                    continue;
                }
                if ($line !== '') {
                    $lines[] = $line;
                }
                // A single word wider than the column: cut it
                while ($this->width($word, $size, $bold) > $max && mb_strlen($word) > 1) {
                    $cut = mb_strlen($word);
                    while ($cut > 1 && $this->width(mb_substr($word, 0, $cut), $size, $bold) > $max) {
                        $cut--;
                    }
                    $lines[] = mb_substr($word, 0, $cut);
                    $word = mb_substr($word, $cut);
                }
                $line = $word;
            }
            if ($line !== '') {
                $lines[] = $line;
            }
        }
        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lines[$maxLines - 1] = rtrim($lines[$maxLines - 1]) . '…';
        }
        return $lines ?: [''];
    }

    /** Text with its baseline at $y (from the top of the page) */
    public function text(float $x, float $y, string $s, float $size = 9.0, bool $bold = false, array $rgb = [0.12, 0.14, 0.18]): void
    {
        $this->current .= sprintf(
            "BT %s %s %s rg /%s %s Tf %s %s Td (%s) Tj ET\n",
            self::num($rgb[0]), self::num($rgb[1]), self::num($rgb[2]),
            $bold ? 'F2' : 'F1', self::num($size),
            self::num($x), self::num(self::H - $y),
            self::escape(self::encode($s))
        );
    }

    /** Filled rectangle; $y is the top edge (from the top of the page) */
    public function rect(float $x, float $y, float $w, float $h, array $rgb): void
    {
        $this->current .= sprintf(
            "%s %s %s rg %s %s %s %s re f\n",
            self::num($rgb[0]), self::num($rgb[1]), self::num($rgb[2]),
            self::num($x), self::num(self::H - $y - $h), self::num($w), self::num($h)
        );
    }

    public function line(float $x1, float $y1, float $x2, float $y2, array $rgb, float $width = 0.5): void
    {
        $this->current .= sprintf(
            "%s %s %s RG %s w %s %s m %s %s l S\n",
            self::num($rgb[0]), self::num($rgb[1]), self::num($rgb[2]), self::num($width),
            self::num($x1), self::num(self::H - $y1), self::num($x2), self::num(self::H - $y2)
        );
    }

    public function pageCount(): int
    {
        return count($this->pages) + ($this->current !== '' ? 1 : 0);
    }

    /** UTF-16BE text string for the document info (keeps every character) */
    private static function infoString(string $s): string
    {
        $utf16 = function_exists('mb_convert_encoding') ? mb_convert_encoding($s, 'UTF-16BE', 'UTF-8') : $s;
        return '<FEFF' . strtoupper(bin2hex($utf16)) . '>';
    }

    public function output(string $title): string
    {
        $pages = $this->pages;
        if ($this->current !== '') {
            $pages[] = $this->current;
        }
        if (!$pages) {
            $pages[] = ' ';
        }

        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $objects[5] = '<< /Title ' . self::infoString($title) . ' /Producer (OAC Kalender) /CreationDate (D:' . gmdate('YmdHis') . "Z) >>";

        $kids = [];
        $next = 6;
        foreach ($pages as $content) {
            $pageId = $next++;
            $contentId = $next++;
            $kids[] = $pageId . ' 0 R';
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::num(self::W) . ' ' . self::num(self::H) . ']'
                . ' /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $contentId . ' 0 R >>';
            $objects[$contentId] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "\nendstream";
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $max = max(array_keys($objects));
        $pdf .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $max; $i++) {
            $pdf .= isset($offsets[$i]) ? sprintf("%010d 00000 n \n", $offsets[$i]) : "0000000000 65535 f \n";
        }
        $pdf .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R /Info 5 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
        return $pdf;
    }
}
