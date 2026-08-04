<?php

namespace App\Support;

use Illuminate\Support\Str;

class RagSourceFormatter
{
    private const LIGATURES = [
        'ﬀ' => 'ff',
        'ﬁ' => 'fi',
        'ﬂ' => 'fl',
        'ﬃ' => 'ffi',
        'ﬄ' => 'ffl',
    ];

    public static function html(?string $value): string
    {
        $markdown = self::markdown($value);

        if ($markdown === '') {
            return '';
        }

        return Str::markdown(e($markdown));
    }

    public static function plain(?string $value): string
    {
        $markdown = self::markdown($value);

        if ($markdown === '') {
            return '';
        }

        return preg_replace('/\*\*(.*?)\*\*/', '$1', $markdown) ?? $markdown;
    }

    public static function cleanAnswer(?string $value): string
    {
        $text = strtr((string) $value, self::LIGATURES);
        $text = preg_replace('/(?<=[A-Za-z])(?=\d)/', ' ', $text) ?? $text;
        $text = preg_replace('/(?<=\d)(?=[A-Za-z])/', ' ', $text) ?? $text;
        $text = preg_replace('/^\s*\*{1,3}(?=\S)/m', '- ', $text) ?? $text;
        $text = preg_replace('/\*{3,}/', '', $text) ?? $text;

        return trim($text);
    }

    public static function markdown(?string $value): string
    {
        $text = trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($text === '') {
            return '';
        }

        $text = strtr($text, self::LIGATURES);
        $text = str_replace(["\r\n", "\r", "\t"], ["\n", "\n", ' '], $text);
        $text = preg_replace('/(?<=[A-Za-z])(?=\d)/', ' ', $text) ?? $text;
        $text = preg_replace('/(?<=\d)(?=[A-Za-z])/', ' ', $text) ?? $text;
        $text = preg_replace('/(?<=[a-z])(?=[A-Z][a-z])/', ' ', $text) ?? $text;
        $text = preg_replace('/[ ]{2,}/', ' ', $text) ?? $text;
        $text = preg_replace('/\*{2,}(?=\s|$)/', '', $text) ?? $text;
        $text = preg_replace('/(?<!\*)\*{1,3}(?=[A-Z])/', "\n* ", $text) ?? $text;
        $text = preg_replace('/\s+(?=(?:[•*]\s*|-\s+|\d{1,2}[.)]\s+|[IVXLCDM]+\.\s+[A-Z]|(?:MODULE|SECTION|CHAPTER|ANNEX)\s+\d+))/u', "\n", $text) ?? $text;

        $lines = collect(preg_split('/\n+/', $text) ?: [])
            ->map(fn (string $line): string => self::formatLine($line))
            ->filter()
            ->values()
            ->all();

        return implode("\n", $lines);
    }

    private static function formatLine(string $line): string
    {
        $line = trim(preg_replace('/\s+/', ' ', $line) ?? $line);
        $line = rtrim($line, " \t\n\r\0\x0B*");

        if ($line === '') {
            return '';
        }

        if (preg_match('/^[•*]\s*(.+)$/u', $line, $matches)) {
            return '- '.trim($matches[1]);
        }

        if (preg_match('/^-\s*(.+)$/u', $line, $matches)) {
            return '- '.trim($matches[1]);
        }

        if (preg_match('/^\d{1,2}[.)]\s+/', $line)) {
            return $line;
        }

        if (self::looksLikeHeading($line)) {
            return '**'.self::titleCaseHeading($line).'**';
        }

        return $line;
    }

    private static function looksLikeHeading(string $line): bool
    {
        if (mb_strlen($line) > 140 || str_ends_with($line, '.')) {
            return false;
        }

        $letters = preg_replace('/[^A-Za-z]/', '', $line) ?? '';

        return mb_strlen($letters) >= 6 && mb_strtoupper($letters) === $letters;
    }

    private static function titleCaseHeading(string $line): string
    {
        $heading = Str::of(Str::lower($line))->headline()->toString();

        $acronyms = [
            'Aop', 'Bls', 'Cpap', 'Dka', 'Enc', 'Ipc', 'Ifcdc', 'Kmc', 'Moh',
            'Ngt', 'Ogt', 'O2', 'Tb',
        ];

        foreach ($acronyms as $acronym) {
            $heading = preg_replace('/\b'.$acronym.'\b/', mb_strtoupper($acronym), $heading) ?? $heading;
        }

        return $heading;
    }
}
