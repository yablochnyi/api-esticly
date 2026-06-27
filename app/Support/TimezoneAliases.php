<?php

namespace App\Support;

class TimezoneAliases
{
    private const ALIASES = [
        'Europe/Kiev' => 'Europe/Kyiv',
    ];

    public static function normalize(?string $timezone): ?string
    {
        $value = trim((string) $timezone);

        if ($value === '') {
            return null;
        }

        return self::ALIASES[$value] ?? $value;
    }
}
