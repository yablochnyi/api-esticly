<?php

namespace App\Support;

class PhoneIndex
{
    public static function normalize(?string $phone): ?string
    {
        $raw = trim((string) $phone);
        if ($raw === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw);
        if (is_string($digits) && $digits !== '') {
            return '+'.$digits;
        }

        return $raw;
    }

    public static function hash(?string $phone): ?string
    {
        $normalized = self::normalize($phone);
        if ($normalized === null) {
            return null;
        }
        return hash('sha256', $normalized);
    }
}

