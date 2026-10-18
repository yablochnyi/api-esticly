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

    public function reserve(string $ip, string $normalizedPhone, bool $registered = false): ?array
    {
        if (config('otp.registered_only', false) && ! $registered) {
            return $this->denied('otp_unavailable', 503, 3600);
        }

        // Review numbers are handled separately, without contacting the SMS provider.
        if (! config('otp.sending_enabled', true)
            && ! in_array(hash('sha256', $normalizedPhone), config('otp.paused_allowed_phone_hashes', []), true)) {
            return $this->denied('otp_unavailable', 503, 3600);
        }

        try {
            $cache = $this->cache();
            $phoneKey = 'otp-send:phone-cooldown:'.$this->phoneFingerprint($normalizedPhone);
            $phoneHourKey = 'otp-send:phone-hour:'.$this->phoneFingerprint($normalizedPhone);
            // Canonicalize IPv6 so equivalent spellings share the same allowance.
            $ipKey = hash('sha256', inet_pton($ip) ?: $ip);
            $key = 'otp-send:ip-hour:'.$ipKey;

            // Reserve before the paid call, atomically across workers. Failures also count.
            return $cache->lock($phoneKey.':lock', 10)->block(2, function () use ($cache, $key, $phoneKey, $phoneHourKey, $registered) {
                $remainingMs = (int) $cache->get($phoneKey, 0) - now()->getTimestampMs();
                if ($remainingMs > 0) {
                    return $this->denied('otp_rate_limited', 429, (int) ceil($remainingMs / 1000));
                }

                return $cache->lock($key.':lock', 10)->block(2, function () use ($cache, $key, $phoneKey, $phoneHourKey, $registered) {
                    $now = now()->timestamp;
                    $phoneAttempts = array_values(array_filter(
                        $cache->get($phoneHourKey, []),
                        fn ($timestamp) => $timestamp > $now - 3600,
                    ));
                    $phoneLimit = max(1, (int) config('otp.phone_hourly_limit', 2));
                    if (count($phoneAttempts) >= $phoneLimit) {
                        $retryAfter = $phoneAttempts[count($phoneAttempts) - $phoneLimit] + 3600 - $now;

                        return $this->denied('otp_rate_limited', 429, $retryAfter);
                    }
                    $attempts = array_values(array_filter(
                        $cache->get($key, []),
                        fn ($timestamp) => $timestamp > $now - 3600,
                    ));
                    $limit = max(1, (int) config('otp.ip_hourly_limit', 2));
                    if (count($attempts) >= $limit) {
                        // If the limit was reduced, enough old reservations must expire first.
                        $retryAfter = $attempts[count($attempts) - $limit] + 3600 - $now;

                        return $this->denied('otp_rate_limited', 429, $retryAfter);
                    }
                    $reserve = function () use ($cache, $phoneAttempts, $attempts, $now, $phoneHourKey, $key, $phoneKey) {
                        $attempts[] = $now;
                        $phoneAttempts[] = $now;
                        if (! $cache->put($phoneHourKey, $phoneAttempts, 3600)) {
                            throw new \RuntimeException('OTP phone allowance could not be persisted');
                        }
                        if (! $cache->put($key, $attempts, 3600)) {
                            throw new \RuntimeException('OTP reservation could not be persisted');
                        }
                        // Keep reservations after provider failures: an uncertain send may still be billed.
                        if (! $cache->put($phoneKey, now()->getTimestampMs() + 60_000, 61)) {
                            throw new \RuntimeException('OTP phone cooldown could not be persisted');
                        }

                        return null;
                    };

                    // Existing accounts retain login when the new-number budget is exhausted.
                    return $registered ? $reserve() : $this->reserveDaily($cache, $reserve);
                });
            });
        } catch (Throwable $e) {
            // Never send unmetered SMS when the shared limiter cannot be reached.
            Log::error('otp_guard_unavailable', ['exception_type' => $e::class]);

            return $this->denied('otp_unavailable', 503, 60);
        }
    }

    private function reserveDaily($cache, callable $reserve): ?array
    {
        // A single cross-worker lock covers every phone/IP, including the midnight boundary.
        return $cache->lock('otp-send:global-day:lock', 10)->block(2, function () use ($cache, $reserve) {
            $day = now()->setTimezone((string) config('otp.daily_timezone', 'Europe/Warsaw'));
            $reset = $day->copy()->addDay()->startOfDay();
            $key = 'otp-send:global-day:'.$day->format('Y-m-d');
            $used = $cache->get($key, 0);
            if (! is_int($used) || $used < 0) {
                throw new \RuntimeException('Invalid OTP daily allowance');
            }
            $limit = max(0, (int) config('otp.global_daily_limit', 20));
            if ($used >= $limit) {
                return $this->denied('otp_rate_limited', 429, $reset->timestamp - $day->timestamp);
            }
            // Persist the global reservation first; partial failures must never allow an unmetered send.
            if (! $cache->put($key, $used + 1, $reset->timestamp - $day->timestamp + 3600)) {
                throw new \RuntimeException('OTP daily allowance could not be persisted');
            }

            return $reserve();
        });
    }

    private function cache()
    {
        $store = (string) config('otp.cache_store');
        $driver = config('cache.stores.'.$store.'.driver');
        if (! in_array($driver, ['redis', 'database', 'file'], true) && ! app()->runningUnitTests()) {
            throw new \RuntimeException('OTP requires a persistent shared cache');
        }

        return Cache::store($store);
    }

    private function denied(string $code, int $status, int $retryAfter): array
    {
        return ['code' => $code, 'status' => $status, 'retry_after' => max(1, $retryAfter)];
    }
}
