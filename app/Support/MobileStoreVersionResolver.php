<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class MobileStoreVersionResolver
{
    /**
     * @return array{latest_version:string,latest_build:int|null,store_url:string}
     */
    public static function resolve(string $platform): array
    {
        return Cache::remember(
            'mobile_store_version.'.$platform,
            now()->addHour(),
            fn (): array => match ($platform) {
                'ios' => self::resolveIos(),
                'android' => self::resolveAndroid(),
                default => throw new \InvalidArgumentException('unsupported_mobile_platform'),
            },
        );
    }

    /**
     * @return array{latest_version:string,latest_build:null,store_url:string}
     */
    private static function resolveIos(): array
    {
        $appStoreId = trim((string) config('mobile_app.ios.app_store_id'));
        $bundleId = trim((string) config('mobile_app.ios.bundle_id'));
        $query = $appStoreId !== '' ? ['id' => $appStoreId] : ['bundleId' => $bundleId];
        $response = Http::acceptJson()
            ->timeout(5)
            ->retry(1, 150)
            ->get('https://itunes.apple.com/lookup', $query);

        if (! $response->successful()) {
            throw new \RuntimeException('app_store_lookup_failed');
        }

        $result = $response->json('results.0');
        $version = is_array($result) ? trim((string) ($result['version'] ?? '')) : '';
        if ($version === '') {
            throw new \RuntimeException('app_store_version_missing');
        }

        return [
            'latest_version' => $version,
            'latest_build' => null,
            'store_url' => trim((string) ($result['trackViewUrl'] ?? config('mobile_app.ios.store_url'))),
        ];
    }

    /**
     * @return array{latest_version:string,latest_build:int,store_url:string}
     */
    private static function resolveAndroid(): array
    {
        $packageName = trim((string) config('mobile_app.android.package_name'));
        if ($packageName === '') {
            throw new \RuntimeException('google_play_package_missing');
        }

        $token = GoogleServiceAccount::accessToken(GooglePlaySubscriptions::SCOPE);
        $baseUrl = sprintf(
            'https://androidpublisher.googleapis.com/androidpublisher/v3/applications/%s/edits',
            rawurlencode($packageName),
        );
        $editResponse = Http::withToken($token)->acceptJson()->timeout(8)->post($baseUrl, []);
        if (! $editResponse->successful()) {
            throw new \RuntimeException('google_play_edit_create_failed');
        }

        $editId = trim((string) $editResponse->json('id'));
        if ($editId === '') {
            throw new \RuntimeException('google_play_edit_id_missing');
        }

        try {
            $tracksResponse = Http::withToken($token)
                ->acceptJson()
                ->timeout(8)
                ->get($baseUrl.'/'.rawurlencode($editId).'/tracks');
            if (! $tracksResponse->successful()) {
                throw new \RuntimeException('google_play_tracks_fetch_failed');
            }

            $latestBuild = self::latestPublishedBuild((array) $tracksResponse->json('tracks', []));
            if ($latestBuild === null) {
                throw new \RuntimeException('google_play_published_build_missing');
            }

            return [
                'latest_version' => '0.0.0',
                'latest_build' => $latestBuild,
                'store_url' => (string) config('mobile_app.android.store_url'),
            ];
        } finally {
            try {
                Http::withToken($token)
                    ->timeout(5)
                    ->delete($baseUrl.'/'.rawurlencode($editId));
            } catch (\Throwable) {
                // The temporary edit expires automatically; cleanup is best effort.
            }
        }
    }

    private static function latestPublishedBuild(array $tracks): ?int
    {
        $latest = null;
        foreach ($tracks as $track) {
            if (! is_array($track)) {
                continue;
            }
            foreach ((array) ($track['releases'] ?? []) as $release) {
                if (! is_array($release) || ! in_array($release['status'] ?? null, ['completed', 'inProgress'], true)) {
                    continue;
                }
                foreach ((array) ($release['versionCodes'] ?? []) as $versionCode) {
                    if (is_numeric($versionCode)) {
                        $latest = max($latest ?? 0, (int) $versionCode);
                    }
                }
            }
        }

        return $latest;
    }
}
