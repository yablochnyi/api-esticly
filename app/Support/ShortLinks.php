<?php

namespace App\Support;

use App\Models\ShortLink;
use App\Models\User;
use Illuminate\Support\Str;

class ShortLinks
{
    private static function baseUrl(): string
    {
        $short = rtrim((string)env('SHORT_LINK_BASE_URL', ''), '/');
        if ($short !== '') {
            return $short;
        }
        $base = rtrim((string)config('app.url', ''), '/');
        if ($base === '') $base = 'http://127.0.0.1:8000';
        return $base;
    }

    private static function ensureSlug(User $org): string
    {
        if (!empty($org->booking_slug)) {
            return (string)$org->booking_slug;
        }

        do {
            $slug = Str::lower(Str::random(8));
            $exists = User::query()->where('booking_slug', $slug)->exists();
        } while ($exists);

        $org->booking_slug = $slug;
        $org->save();

        return $slug;
    }

    private static function code(): string
    {
        // 7 chars is short, still ~3.5T combos with base62.
        // Use Str::random (A-Za-z0-9) -> ok for URL.
        return Str::random(7);
    }

    public static function getOrCreate(User $org, string $key, string $targetPath): ShortLink
    {
        $key = trim($key);
        abort_if($key === '' || strlen($key) > 80, 500);

        $targetPath = '/' . ltrim(trim($targetPath), '/');
        if (strlen($targetPath) > 255) {
            $targetPath = substr($targetPath, 0, 255);
        }

        $existing = ShortLink::query()
            ->where('user_id', $org->id)
            ->where('key', $key)
            ->first();

        if ($existing && $existing->target_path === $targetPath && $existing->code) {
            return $existing;
        }

        $link = $existing ?: new ShortLink();
        $link->user_id = (int)$org->id;
        $link->key = $key;
        $link->target_path = $targetPath;

        // Ensure unique code.
        do {
            $code = self::code();
            $taken = ShortLink::query()->where('code', $code)->exists();
        } while ($taken);

        $link->code = $code;
        $link->save();

        return $link;
    }

    public static function reviewShortUrl(User $org): string
    {
        $slug = self::ensureSlug($org);
        $link = self::getOrCreate($org, 'review', '/r/' . $slug);
        return self::baseUrl() . '/s/' . $link->code;
    }
}

