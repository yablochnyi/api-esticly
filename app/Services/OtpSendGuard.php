<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\IpUtils;
use Throwable;

class OtpSendGuard
{
    public function phoneFingerprint(string $phone): string
    {
        return hash_hmac('sha256', $phone, (string) config('app.key'));
    }

    public function checkEligibility(string $normalizedPhone, bool $registered = false): ?array
    {
        if (config('otp.registered_only', false) && ! $registered) {
            return $this->denied('otp_unavailable', 503, 3600, 'registered_only');
        }

        // Review numbers are handled separately, without contacting the SMS provider.
        if (! config('otp.sending_enabled', true)
            && ! in_array(hash('sha256', $normalizedPhone), config('otp.paused_allowed_phone_hashes', []), true)) {
            return $this->denied('otp_unavailable', 503, 3600, 'sending_paused');
        }

        return null;
    }

    public function reserve(string $ip, string $normalizedPhone, bool $registered = false): ?array
    {
        if ($denied = $this->checkEligibility($normalizedPhone, $registered)) {
            return $denied;
        }

        try {
            $packedIp = inet_pton($ip);
            if ($packedIp === false) {
                throw new \RuntimeException('Invalid OTP client IP');
            }
            // IPv4-mapped IPv6 must share the native IPv4 address and network budgets.
            if (strlen($packedIp) === 16 && substr($packedIp, 0, 12) === str_repeat("\0", 10)."\xff\xff") {
                $packedIp = substr($packedIp, 12);
            }
            $networkKey = 'otp-send:new-network-hour:'.hash('sha256', inet_pton(IpUtils::anonymize(inet_ntop($packedIp))));
            $cache = $this->cache();
            $phoneKey = 'otp-send:phone-cooldown:'.$this->phoneFingerprint($normalizedPhone);
            $phoneHourKey = 'otp-send:phone-hour:'.$this->phoneFingerprint($normalizedPhone);
            // Canonicalize IPv6 so equivalent spellings share the same allowance.
            $ipKey = hash('sha256', $packedIp);
            $key = 'otp-send:ip-hour:'.$ipKey;

            // Reserve before the paid call, atomically across workers. Failures also count.
            return $cache->lock($phoneKey.':lock', 10)->block(2, function () use ($cache, $key, $phoneKey, $phoneHourKey, $registered, $networkKey) {
                $remainingMs = (int) $cache->get($phoneKey, 0) - now()->getTimestampMs();
                if ($remainingMs > 0) {
                    return $this->denied('otp_rate_limited', 429, (int) ceil($remainingMs / 1000), 'phone_cooldown');
                }

                return $cache->lock($key.':lock', 10)->block(2, function () use ($cache, $key, $phoneKey, $phoneHourKey, $registered, $networkKey) {
                    $now = now()->timestamp;
                    $phoneAttempts = array_values(array_filter(
                        $cache->get($phoneHourKey, []),
                        fn ($timestamp) => $timestamp > $now - 3600,
                    ));
                    $phoneLimit = max(1, (int) config('otp.phone_hourly_limit', 2));
                    if (count($phoneAttempts) >= $phoneLimit) {
                        $retryAfter = $phoneAttempts[count($phoneAttempts) - $phoneLimit] + 3600 - $now;

                        return $this->denied('otp_rate_limited', 429, $retryAfter, 'phone_hourly_limit');
                    }
                    $attempts = array_values(array_filter(
                        $cache->get($key, []),
                        fn ($timestamp) => $timestamp > $now - 3600,
                    ));
                    $limit = max(1, (int) config('otp.ip_hourly_limit', 2));
                    if (count($attempts) >= $limit) {
                        // If the limit was reduced, enough old reservations must expire first.
                        $retryAfter = $attempts[count($attempts) - $limit] + 3600 - $now;

                        return $this->denied('otp_rate_limited', 429, $retryAfter, 'ip_hourly_limit');
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
                    return $registered ? $reserve() : $this->reserveNewNumber($cache, $networkKey, $reserve);
                });
            });
        } catch (Throwable $e) {
            // Never send unmetered SMS when the shared limiter cannot be reached.
            Log::error('otp_guard_unavailable', ['exception_type' => $e::class]);

            return $this->denied('otp_unavailable', 503, 60, 'guard_unavailable');
        }
    }

    private function reserveNewNumber($cache, string $networkKey, callable $reserve): ?array
    {
        // A single cross-worker lock covers every phone/IP, including the midnight boundary.
        return $cache->lock('otp-send:global-day:lock', 10)->block(2, function () use ($cache, $networkKey, $reserve) {
            $day = now()->setTimezone((string) config('otp.daily_timezone', 'Europe/Warsaw'));
            $reset = $day->copy()->addDay()->startOfDay();
            $key = 'otp-send:global-day:'.$day->format('Y-m-d');
            $used = $cache->get($key, 0);
            if (! is_int($used) || $used < 0) {
                throw new \RuntimeException('Invalid OTP daily allowance');
            }
            $limit = max(0, (int) config('otp.global_daily_limit', 20));
            if ($used >= $limit) {
                return $this->denied('otp_rate_limited', 429, $reset->timestamp - $day->timestamp, 'new_daily_limit');
            }
            $now = now()->timestamp;
            $buckets = [
                'otp-send:new-global-hour' => ['limit' => (int) config('otp.new_global_hourly_limit', 5), 'reason' => 'new_global_hourly_limit'],
                $networkKey => ['limit' => (int) config('otp.new_network_hourly_limit', 2), 'reason' => 'new_network_hourly_limit'],
            ];
            $reservations = [];
            foreach ($buckets as $bucketKey => $bucket) {
                $attempts = $cache->get($bucketKey, []);
                if (! is_array($attempts) || array_filter($attempts, fn ($time) => ! is_int($time) || $time > $now || $time < 0)) {
                    throw new \RuntimeException('Invalid OTP new-number allowance');
                }
                $attempts = array_values(array_filter($attempts, fn ($time) => $time > $now - 3600));
                sort($attempts, SORT_NUMERIC);
                $limit = max(0, $bucket['limit']);
                if ($limit === 0 || count($attempts) >= $limit) {
                    $retry = $limit === 0 ? 3600 : $attempts[count($attempts) - $limit] + 3600 - $now;

                    return $this->denied('otp_rate_limited', 429, $retry, $bucket['reason']);
                }
                $reservations[$bucketKey] = [...$attempts, $now];
            }
            // Persist the global reservation first; partial failures must never allow an unmetered send.
            if (! $cache->put($key, $used + 1, $reset->timestamp - $day->timestamp + 3600)) {
                throw new \RuntimeException('OTP daily allowance could not be persisted');
            }
            foreach ($reservations as $bucketKey => $attempts) {
                if (! $cache->put($bucketKey, $attempts, 3600)) {
                    throw new \RuntimeException('OTP new-number allowance could not be persisted');
                }
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

    private function denied(string $code, int $status, int $retryAfter, string $policyReason): array
    {
        return ['code' => $code, 'status' => $status, 'retry_after' => max(1, $retryAfter), 'policy_reason' => $policyReason];
    }
}
