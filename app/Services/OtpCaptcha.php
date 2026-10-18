<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class OtpCaptcha
{
    public const ACTION = 'otp_send';

    public function check(string $phone, string $ip, ?string $id, ?string $token): ?array
    {
        if (! $this->configured()) {
            return $this->unavailable();
        }

        try {
            $store = Cache::store(config('otp.cache_store'));
            if (! $id || ! $token) {
                $id = bin2hex(random_bytes(32));
                $store->put($this->key($id), app(OtpSendGuard::class)->phoneFingerprint($phone), 300);

                return ['status' => 403, 'code' => 'captcha_required', 'captcha' => [
                    'id' => $id,
                    'url' => rtrim(config('app.url'), '/').'/auth/otp-challenge/'.$id,
                ]];
            }

            if (! preg_match('/^[a-f0-9]{64}$/D', $id)) {
                return $this->invalid();
            }

            // Consume under a shared lock before Siteverify: concurrent requests cannot reuse a challenge.
            return $store->lock($this->key($id).':lock', 20)->block(2, function () use ($store, $phone, $ip, $id, $token) {
                $expected = $store->get($this->key($id));
                if (! is_string($expected) || ! hash_equals($expected, app(OtpSendGuard::class)->phoneFingerprint($phone))) {
                    return $this->invalid();
                }
                $store->forget($this->key($id));
                $response = Http::asForm()->connectTimeout(3)->timeout(8)->post(
                    'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                    ['secret' => config('otp.captcha.secret_key'), 'response' => $token, 'remoteip' => $ip],
                );
                if (! $response->successful()) {
                    return $this->unavailable();
                }
                $result = $response->json();
                if (! is_array($result) || ($result['success'] ?? false) !== true
                    || ($result['action'] ?? '') !== self::ACTION
                    || ($result['cdata'] ?? '') !== $id
                    || ($result['hostname'] ?? '') !== parse_url(config('app.url'), PHP_URL_HOST)) {
                    return $this->invalid();
                }

                return null;
            });
        } catch (Throwable $e) {
            logger()->warning('otp_captcha_unavailable', ['exception_type' => get_class($e)]);

            return $this->unavailable();
        }
    }

    public function configured(): bool
    {
        return trim((string) config('otp.captcha.site_key')) !== ''
            && trim((string) config('otp.captcha.secret_key')) !== '';
    }

    public function exists(string $id): bool
    {
        return Cache::store(config('otp.cache_store'))->has($this->key($id));
    }

    private function key(string $id): string
    {
        return 'otp-captcha:'.$id;
    }

    private function invalid(): array
    {
        return ['status' => 403, 'code' => 'captcha_invalid'];
    }

    private function unavailable(): array
    {
        return ['status' => 503, 'code' => 'captcha_unavailable'];
    }
}
