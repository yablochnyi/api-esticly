<?php

namespace App\Support;

use Illuminate\Http\Request;

class PublicLocale
{
    private static function normalizeCandidate(?string $locale, array $supported): ?string
    {
        $value = strtolower(trim((string) $locale));
        if ($value === '') {
            return null;
        }

        if (in_array($value, $supported, true)) {
            return $value;
        }

        $base = explode('-', $value)[0] ?? $value;
        $base = strtolower(trim((string) $base));
        if ($base !== '' && in_array($base, $supported, true)) {
            return $base;
        }

        return match ($base) {
            'ru', 'be' => in_array('uk', $supported, true) ? 'uk' : null,
            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function supported(): array
    {
        $cfg = config('site_locales.supported', []);
        $supported = array_keys(is_array($cfg) ? $cfg : []);
        if (count($supported) > 0) {
            return $supported;
        }

        return ['uk', 'pl', 'en', 'it', 'fr', 'pt', 'de', 'es', 'cs'];
    }

    public static function resolve(Request $request, ?string $orgLanguageCode = null): string
    {
        $supported = self::supported();

        $explicit = self::normalizeCandidate($request->query('lang', $request->input('lang', '')), $supported);
        if ($explicit !== null) {
            return $explicit;
        }

        foreach ($request->getLanguages() as $candidate) {
            $preferred = self::normalizeCandidate($candidate, $supported);
            if ($preferred !== null) {
                return $preferred;
            }
        }

        $orgLang = self::normalizeCandidate($orgLanguageCode, $supported);
        if ($orgLang !== null) {
            return $orgLang;
        }

        $default = self::normalizeCandidate(config('site_locales.default', 'en'), $supported);
        if ($default !== null) {
            return $default;
        }

        return 'en';
    }
}
