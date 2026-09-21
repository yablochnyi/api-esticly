<?php

namespace App\Services;

use App\Models\GoogleCalendarConnection;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class GoogleCalendarClient
{
    public const SCOPE = 'https://www.googleapis.com/auth/calendar.app.created';

    public function configured(): bool
    {
        return filled(config('google_calendar.client_id'))
            && filled(config('google_calendar.client_secret'))
            && str_starts_with((string) config('google_calendar.redirect_uri'), 'https://');
    }

    public function authorizationUrl(string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('google_calendar.client_id'),
            'redirect_uri' => config('google_calendar.redirect_uri'),
            'response_type' => 'code',
            'scope' => 'openid email '.self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent select_account',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function exchange(string $code): array
    {
        $response = $this->tokenRequest([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => config('google_calendar.redirect_uri'),
        ]);
        $body = $response->json();
        if (! $response->successful() || ! is_array($body)
            || empty($body['access_token']) || empty($body['refresh_token'])
            || ! in_array(self::SCOPE, explode(' ', $body['scope'] ?? ''), true)) {
            throw new GoogleCalendarFailure('authorization_failed');
        }

        return $body;
    }

    public function identity(string $token): array
    {
        $response = Http::withToken($token)->acceptJson()->connectTimeout(5)->timeout(10)
            ->get('https://openidconnect.googleapis.com/v1/userinfo');
        $body = $response->json();
        if (! $response->successful() || empty($body['sub']) || empty($body['email'])
            || ($body['email_verified'] ?? false) !== true) {
            throw new GoogleCalendarFailure('authorization_failed');
        }

        return $body;
    }

    private function tokenRequest(array $params): Response
    {
        return Http::asForm()->connectTimeout(5)->timeout(10)->post('https://oauth2.googleapis.com/token', [
            ...$params,
            'client_id' => config('google_calendar.client_id'),
            'client_secret' => config('google_calendar.client_secret'),
        ]);
    }

    private function refresh(GoogleCalendarConnection $connection): void
    {
        if (! $connection->refresh_token) {
            throw new GoogleCalendarFailure('reconnect_required');
        }
        $response = $this->tokenRequest([
            'grant_type' => 'refresh_token',
            'refresh_token' => $connection->refresh_token,
        ]);
        if ($response->json('error') === 'invalid_grant') {
            throw new GoogleCalendarFailure('reconnect_required');
        }
        if (! $response->successful() || ! $response->json('access_token')) {
            throw new GoogleCalendarFailure('provider_unavailable');
        }
        $connection->update([
            'access_token' => $response->json('access_token'),
            'token_expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600)),
        ]);
    }

    public function request(GoogleCalendarConnection $connection, string $method, string $path, ?array $data = null): Response
    {
        if (! $connection->token_expires_at || $connection->token_expires_at->lte(now()->addMinute())) {
            $this->refresh($connection);
        }
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $response = Http::withToken($connection->access_token)->acceptJson()->connectTimeout(5)->timeout(10)
                ->send($method, 'https://www.googleapis.com/calendar/v3/'.$path,
                    $data === null ? [] : ['json' => $data]);
            if ($response->status() !== 401) {
                if ($response->status() === 429 || $response->serverError()) {
                    throw new GoogleCalendarFailure('provider_unavailable');
                }
                if ($response->status() === 403) {
                    $reason = $response->json('error.errors.0.reason');
                    throw new GoogleCalendarFailure(in_array($reason, ['rateLimitExceeded', 'userRateLimitExceeded'], true)
                        ? 'provider_unavailable' : 'reconnect_required');
                }

                return $response;
            }
            if ($attempt === 0) {
                $this->refresh($connection);
            }
        }
        throw new GoogleCalendarFailure('reconnect_required');
    }
}
