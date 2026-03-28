<?php

namespace App\Models;

use App\Support\Auditable;
use Illuminate\Database\Eloquent\Model;

class BroadcastPost extends Model
{
    use Auditable;

    protected $guarded = false;

    protected function casts(): array
    {
        return [
            'title_translations' => 'array',
            'body_translations' => 'array',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function titleFor(string $locale): string
    {
        return $this->translationFor('title_translations', $locale);
    }

    public function bodyFor(string $locale): string
    {
        return $this->translationFor('body_translations', $locale);
    }

    private function translationFor(string $field, string $locale): string
    {
        $translations = (array) ($this->{$field} ?? []);
        $locale = self::resolveLocale($locale);
        $fallback = (string) config('site_locales.default', config('app.fallback_locale', 'pl'));

        return trim((string) (
            $translations[$locale]
            ?? $translations[$fallback]
            ?? reset($translations)
            ?? ''
        ));
    }

    public static function resolveLocale(?string $locale): string
    {
        $supported = array_keys((array) config('site_locales.supported', []));
        if (empty($supported)) {
            $supported = ['pl', 'en', 'uk', 'it', 'fr', 'pt', 'de', 'es', 'cs'];
        }

        $locale = strtolower(trim((string) $locale));
        if (in_array($locale, $supported, true)) {
            return $locale;
        }

        $fallback = (string) config('site_locales.default', config('app.fallback_locale', 'pl'));
        return in_array($fallback, $supported, true) ? $fallback : 'pl';
    }
}
