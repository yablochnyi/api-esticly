<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

class FcmV1
{
    private static ?array $cachedCreds = null;
    private static ?array $cachedAccessToken = null;

    public static function isConfigured(): bool
    {
        return self::credentials() !== null;
    }

    /**
     * @param list<string> $tokens
     * @param array<string,string> $data
     * @return array{sent:int,failed:int}
     */
    public static function sendToTokens(array $tokens, string $title, string $body, array $data = []): array
    {
        $creds = self::credentials();
        if ($creds === null) {
            throw new \RuntimeException('fcm_v1_not_configured');
        }

        $token = self::accessToken($creds);
        $projectId = $creds['project_id'];
        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $sent = 0;
        $failed = 0;

        foreach ($tokens as $deviceToken) {
            $deviceToken = trim((string) $deviceToken);
            if ($deviceToken === '') {
                continue;
            }

            $response = Http::withToken($token)
                ->acceptJson()
                ->post($url, [
                    'message' => [
                        'token' => $deviceToken,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'data' => $data,
                    ],
                ]);

            if ($response->successful()) {
                $sent++;
            } else {
                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * @return array{client_email:string,private_key:string,project_id:string}|null
     */
    private static function credentials(): ?array
    {
        if (self::$cachedCreds !== null) {
            return self::$cachedCreds;
        }

        $path = trim((string) env('GOOGLE_APPLICATION_CREDENTIALS', ''));
        if ($path === '' || !is_file($path)) {
            return null;
        }

        $json = @file_get_contents($path);
        if (!is_string($json) || $json === '') {
            return null;
        }

        $parsed = json_decode($json, true);
        if (!is_array($parsed)) {
            return null;
        }

        $clientEmail = trim((string) ($parsed['client_email'] ?? ''));
        $privateKey = (string) ($parsed['private_key'] ?? '');
        $projectId = trim((string) (env('FCM_PROJECT_ID', '') ?: ($parsed['project_id'] ?? '')));

        if ($clientEmail === '' || $privateKey === '' || $projectId === '') {
            return null;
        }

        self::$cachedCreds = [
            'client_email' => $clientEmail,
            'private_key' => $privateKey,
            'project_id' => $projectId,
        ];

        return self::$cachedCreds;
    }

    /**
     * @param array{client_email:string,private_key:string,project_id:string} $creds
     */
    private static function accessToken(array $creds): string
    {
        $now = time();
        $cached = self::$cachedAccessToken;
        if (is_array($cached) && ($cached['expires_at'] ?? 0) > ($now + 60)) {
            return (string) $cached['token'];
        }

        $header = self::base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = self::base64UrlEncode(json_encode([
            'iss' => $creds['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $signingInput = $header.'.'.$claims;
        $signature = '';
        $ok = openssl_sign($signingInput, $signature, $creds['private_key'], OPENSSL_ALGO_SHA256);
        if (!$ok) {
            throw new \RuntimeException('fcm_v1_jwt_sign_failed');
        }

        $jwt = $signingInput.'.'.self::base64UrlEncode($signature);

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('fcm_v1_oauth_failed');
        }

        $body = $response->json();
        $token = is_array($body) ? (string) ($body['access_token'] ?? '') : '';
        $expiresIn = is_array($body) ? (int) ($body['expires_in'] ?? 3600) : 3600;
        if ($token === '') {
            throw new \RuntimeException('fcm_v1_access_token_missing');
        }

        self::$cachedAccessToken = [
            'token' => $token,
            'expires_at' => $now + max(60, $expiresIn),
        ];

        return $token;
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

