<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class MediaUrl
{
    public static function publicFile(?string $path): ?string
    {
        $private = filter_var(env('FILES_PRIVATE_SIGNED_URLS', false), FILTER_VALIDATE_BOOLEAN);
        $raw = trim((string) $path);
        if ($raw === '') return null;

        if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
            if (!$private) {
                return $raw;
            }

            $parsedPath = parse_url($raw, PHP_URL_PATH);
            $parsedPath = is_string($parsedPath) ? ltrim($parsedPath, '/') : '';
            if ($parsedPath === '') {
                return null;
            }

            $bucket = trim((string) env('AWS_BUCKET', ''), '/');
            if ($bucket !== '' && str_starts_with($parsedPath, $bucket.'/')) {
                $raw = substr($parsedPath, strlen($bucket) + 1);
            } else {
                $raw = $parsedPath;
            }
        }

        // Heal legacy local absolute paths accidentally saved in DB.
        $needle = 'storage/app/public/';
        if (str_contains($raw, $needle)) {
            $parts = explode($needle, $raw, 2);
            $raw = $parts[1] ?? $raw;
        }

        $path = ltrim($raw, '/');
        $disk = Storage::disk('public');
        if (!$private) {
            return $disk->url($path);
        }

        $ttlMin = (int) env('FILES_SIGNED_URL_TTL_MIN', 30);
        if ($ttlMin <= 0) $ttlMin = 30;

        try {
            return $disk->temporaryUrl($path, now()->addMinutes($ttlMin));
        } catch (\Throwable $e) {
            return null;
        }
    }
}
