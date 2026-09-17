<?php

namespace App\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class FirebaseAppCheck
{
    public const JWKS_URL = 'https://firebaseappcheck.googleapis.com/v1/jwks';

    public function verifyAndConsume(string $token): string
    {
        if ($token === '' || strlen($token) > 16384) {
            throw new AppCheckRejected('missing_or_invalid');
        }
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                throw new AppCheckRejected('malformed');
            }
            $header = JWT::jsonDecode(JWT::urlsafeB64Decode($parts[0]));
            if (! is_object($header) || ($header->alg ?? null) !== 'RS256'
                || ($header->typ ?? null) !== 'JWT' || ! is_string($header->kid ?? null)
                || $header->kid === '' || strlen($header->kid) > 256) {
                throw new AppCheckRejected('invalid_header');
            }
        } catch (AppCheckRejected $e) {
            throw $e;
        } catch (Throwable) {
            throw new AppCheckRejected('malformed');
        }

        $project = (string) config('app_check.project_number');
        $appIds = config('app_check.app_ids');
        if (! ctype_digit($project) || ! is_array($appIds) || $appIds === []) {
            throw new RuntimeException('App Check configuration missing');
        }
        $cache = $this->cache();
        $keys = $this->keys($cache, $header->kid);
        if (! isset($keys[$header->kid])) {
            throw new AppCheckRejected('unknown_key');
        }
        try {
            $claims = JWT::decode($token, $keys);
        } catch (Throwable) {
            throw new AppCheckRejected('invalid_token');
        }
        $now = time();
        if (($claims->iss ?? null) !== 'https://firebaseappcheck.googleapis.com/'.$project
            || ! is_array($claims->aud ?? null)
            || ! in_array('projects/'.$project, $claims->aud, true)
            || ! is_string($claims->sub ?? null) || ! in_array($claims->sub, $appIds, true)
            || ! is_string($claims->jti ?? null) || $claims->jti === '' || strlen($claims->jti) > 500
            || ! is_int($claims->exp ?? null) || $claims->exp <= $now
            || ! is_int($claims->iat ?? null) || $claims->iat > $now
            || $claims->exp <= $claims->iat || $claims->exp - $claims->iat > 7 * 86400) {
            throw new AppCheckRejected('invalid_claims');
        }

        // Local replay protection, not Firebase's Node-only consume API. Never release
        // this reservation after a provider failure: that could send an SMS twice.
        $identity = $claims->iss.'|'.$claims->sub.'|'.$claims->jti;
        if (! $cache->add('app-check:used:'.hash('sha256', $identity), true, $claims->exp - $now + 60)) {
            throw new AppCheckRejected('replayed');
        }

        return $claims->sub;
    }

    private function cache(): Repository
    {
        $store = (string) config('app_check.cache_store');
        $driver = config('cache.stores.'.$store.'.driver');
        if (! in_array($driver, ['database', 'redis'], true)
            && ! (app()->runningUnitTests() && in_array($driver, ['array', 'file'], true))) {
            throw new RuntimeException('App Check requires a shared persistent cache');
        }

        return Cache::store($store);
    }

    private function keys(Repository $cache, string $kid): array
    {
        $snapshot = $cache->get('app-check:jwks');
        $keys = is_array($snapshot) ? JWK::parseKeySet($snapshot['jwks'], 'RS256') : [];
        if (isset($keys[$kid])) {
            return $keys;
        }

        // Bound refreshes for unknown key IDs so arbitrary tokens cannot amplify
        // outbound requests. Re-read under the lock when another worker refreshed.
        return $cache->lock('app-check:jwks-lock', 15)->block(2, function () use ($cache, $kid) {
            $snapshot = $cache->get('app-check:jwks');
            $keys = is_array($snapshot) ? JWK::parseKeySet($snapshot['jwks'], 'RS256') : [];
            if (isset($keys[$kid]) || (is_array($snapshot) && $snapshot['fetched_at'] > time() - 60)) {
                return $keys;
            }
            if (! $cache->add('app-check:jwks-fetch', true, 60)) {
                throw new RuntimeException('App Check key refresh unavailable');
            }
            $jwks = Http::acceptJson()->connectTimeout(3)->timeout(5)->get(self::JWKS_URL)->throw()->json();
            if (! is_array($jwks) || ! isset($jwks['keys']) || ! is_array($jwks['keys'])) {
                throw new RuntimeException('Invalid App Check key response');
            }
            $keys = JWK::parseKeySet($jwks, 'RS256');
            if ($keys === []) {
                throw new RuntimeException('Empty App Check keys');
            }
            if (! $cache->put('app-check:jwks', ['jwks' => $jwks, 'fetched_at' => time()], 6 * 3600)) {
                throw new RuntimeException('App Check key cache unavailable');
            }

            return $keys;
        });
    }
}
