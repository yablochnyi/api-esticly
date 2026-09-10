<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class OtpSendGuard
{
    public function phoneFingerprint(string $phone): string
    {
        return hash_hmac('sha256', $phone, (string) config('app.key'));
    }

    public function reserve(string $ip): ?array
    {
        try {
            $cache = $this->cache();
            // Canonicalize IPv6 so equivalent spellings share the same allowance.
            $ipKey = hash('sha256', inet_pton($ip) ?: $ip);
            $key = 'otp-send:ip-hour:'.$ipKey;

            // Reserve before the paid call, atomically across workers. Failures also count.
            return $cache->lock($key.':lock', 10)->block(2, function () use ($cache, $key) {
                $now = now()->timestamp;
                $attempts = array_values(array_filter(
                    $cache->get($key, []),
                    fn ($timestamp) => $timestamp > $now - 3600,
                ));
                $limit = max(1, (int) config('otp.ip_hourly_limit', 5));
                if (count($attempts) >= $limit) {
                    // If the limit was reduced, enough old reservations must expire first.
                    $retryAfter = $attempts[count($attempts) - $limit] + 3600 - $now;

                    return $this->denied('otp_rate_limited', 429, $retryAfter);
                }
                $attempts[] = $now;
                if (!$cache->put($key, $attempts, 3600)) {
                    throw new \RuntimeException('OTP reservation could not be persisted');
                }

                return null;
            });
        } catch (Throwable $e) {
            // Never send unmetered SMS when the shared limiter cannot be reached.
            Log::error('otp_guard_unavailable', ['exception_type' => $e::class]);

            return $this->denied('otp_unavailable', 503, 60);
        }
    }

    private function cache()
    {
        $store = (string) config('otp.cache_store');
        $driver = config('cache.stores.'.$store.'.driver');
        if (!in_array($driver, ['redis', 'database', 'file'], true) && !app()->runningUnitTests()) {
            throw new \RuntimeException('OTP requires a persistent shared cache');
        }

        return Cache::store($store);
    }

    private function denied(string $code, int $status, int $retryAfter): array
    {
        return ['code' => $code, 'status' => $status, 'retry_after' => max(1, $retryAfter)];
    }
}
