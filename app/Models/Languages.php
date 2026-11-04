<?php

namespace App\Models;

/**
 * Supported document languages.
 *
 * Single source of truth for validation, prompts, headings and the UI. Adding a
 * language means adding an entry here plus its heading set in
 * DocumentTemplateRenderer — nothing else should hardcode a language.
 */
class Languages
{
    /** @var array<string, string> code => human label */
    public const ALL = [
        'id' => 'Bahasa Indonesia',
        'en' => 'English',
        'ms' => 'Bahasa Melayu',
    ];

    /** @var list<string> */
    public const CODES = ['id', 'en', 'ms'];

    /** @return list<string> */
    public static function codes(): array
    {
        return self::CODES;
    }

    public static function label(string $code): string
    {
        return self::ALL[$code] ?? $code;
    }

    /**
     * English name used inside AI prompts.
     */
    public static function promptName(string $code): string
    {
        return match ($code) {
            'id' => 'Bahasa Indonesia',
            'en' => 'English',
            'ms' => 'Bahasa Melayu',
            default => 'English',
        };
    }

    public static function isSupported(string $code): bool
    {
        return array_key_exists($code, self::ALL);
    }
}
