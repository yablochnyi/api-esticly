<?php

namespace App\Support;

use Illuminate\Http\Request;

class PublicLocale
{
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

        $explicit = strtolower(trim((string)$request->query('lang', $request->input('lang', ''))));
        if ($explicit !== '' && in_array($explicit, $supported, true)) {
            return $explicit;
        }

        $preferred = $request->getPreferredLanguage($supported);
        if (is_string($preferred) && in_array($preferred, $supported, true)) {
            return $preferred;
        }

        $orgLang = strtolower(trim((string)$orgLanguageCode));
        if ($orgLang !== '' && in_array($orgLang, $supported, true)) {
            return $orgLang;
        }

        $default = strtolower(trim((string)config('site_locales.default', 'en')));
        if (in_array($default, $supported, true)) {
            return $default;
        }

        return 'en';
    }
}

