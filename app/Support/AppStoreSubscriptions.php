<?php

namespace App\Support;

use App\Models\Subscription;
use App\Models\SubscriptionTransaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

class AppStoreSubscriptions
{
    public const PROVIDER = 'app_store';
    private const PROD_BASE = 'https://api.storekit.itunes.apple.com';
    private const SANDBOX_BASE = 'https://api.storekit-sandbox.itunes.apple.com';
    private const AUDIENCE = 'appstoreconnect-v1';

    public static function bundleId(): string
    {
        return trim((string) env('APPLE_IAP_BUNDLE_ID', ''));
    }

    public static function isConfigured(): bool
    {
        return self::bundleId() !== ''
            && trim((string) env('APPLE_IAP_ISSUER_ID', '')) !== ''
            && trim((string) env('APPLE_IAP_KEY_ID', '')) !== ''
            && self::privateKeyPem() !== null;
    }

    public static function planCodeForProductId(string $productId): ?string
    {
        return match (trim($productId)) {
            'esticly_basic_monthly' => OrgSubscription::PLAN_BASIC,
            'esticly_pro_monthly' => OrgSubscription::PLAN_PRO,
            default => null,
        };
    }

    public static function syncTransaction(User $org, string $productId, string $transactionId): Subscription
    {
        if (!self::isConfigured()) {
            throw new \RuntimeException('app_store_not_configured');
        }

        $planCode = self::planCodeForProductId($productId);
        if ($planCode === null) {
            throw new \RuntimeException('app_store_unknown_product');
        }

        [$body, $environment] = self::fetchHistory($transactionId);
        $signedTransactions = $body['signedTransactions'] ?? null;
        if (!is_array($signedTransactions) || $signedTransactions === []) {
            throw new \RuntimeException('app_store_empty_history');
        }

        $payloads = [];
        foreach ($signedTransactions as $signedTransaction) {
            if (!is_string($signedTransaction) || $signedTransaction === '') {
                continue;
            }
            $payload = self::decodeSignedPayload($signedTransaction);
            if ($payload !== null) {
                $payloads[] = $payload;
            }
        }

        if ($payloads === []) {
            throw new \RuntimeException('app_store_invalid_history');
        }

        $match = null;
        foreach ($payloads as $payload) {
            if ((string) ($payload['productId'] ?? '') === $productId) {
                $match = $payload;
                break;
            }
        }
        $match ??= $payloads[0];

        $bundleId = (string) ($match['bundleId'] ?? '');
        if ($bundleId !== '' && $bundleId !== self::bundleId()) {
            throw new \RuntimeException('app_store_bundle_mismatch');
        }

        $expiresAt = self::parseMillis($match['expiresDate'] ?? null);
        $startedAt = self::parseMillis($match['purchaseDate'] ?? null);
        $cancelledAt = self::parseMillis($match['revocationDate'] ?? null);
        $originalTransactionId = trim((string) ($match['originalTransactionId'] ?? $transactionId));
        $currentTransactionId = trim((string) ($match['transactionId'] ?? $transactionId));
        $webOrderLineItemId = trim((string) ($match['webOrderLineItemId'] ?? ''));
        $status = self::mapStatus($expiresAt, $cancelledAt);

        $subscription = Subscription::query()->updateOrCreate(
            [
                'provider' => self::PROVIDER,
                'purchase_token' => $originalTransactionId,
            ],
            [
                'user_id' => $org->id,
                'product_id' => $productId,
                'plan_code' => $planCode,
                'status' => $status,
                'provider_subscription_id' => $originalTransactionId,
                'started_at' => $startedAt,
                'renews_at' => $expiresAt,
                'ends_at' => $expiresAt,
                'cancelled_at' => $cancelledAt,
                'last_verified_at' => now(),
                'meta' => [
                    'environment' => $environment,
                    'history' => $body,
                    'latest_transaction' => $match,
                ],
            ],
        );

        $org->subscription_plan = $planCode;
        $org->subscription_ends_at = $expiresAt;
        $org->subscription_provider = self::PROVIDER;
        $org->save();

        self::recordTransaction(
            org: $org,
            subscription: $subscription,
            eventType: self::eventTypeForStatus($status),
            transactionId: $currentTransactionId !== '' ? $currentTransactionId : null,
            orderId: $webOrderLineItemId !== '' ? $webOrderLineItemId : null,
            payload: [
                'environment' => $environment,
                'history' => $body,
                'latest_transaction' => $match,
            ],
            periodEnd: $expiresAt,
            occurredAt: $startedAt ?? now(),
        );

        return $subscription;
    }

    public static function decodeNotification(string $signedPayload): ?array
    {
        return self::decodeSignedPayload($signedPayload);
    }

    private static function fetchHistory(string $transactionId): array
    {
        $response = self::requestHistory(self::PROD_BASE, $transactionId);
        if ($response->successful()) {
            return [self::jsonBody($response), 'Production'];
        }

        $sandbox = self::requestHistory(self::SANDBOX_BASE, $transactionId);
        if ($sandbox->successful()) {
            return [self::jsonBody($sandbox), 'Sandbox'];
        }

        throw new \RuntimeException('app_store_fetch_failed');
    }

    private static function requestHistory(string $baseUrl, string $transactionId)
    {
        $url = sprintf('%s/inApps/v1/history/%s', $baseUrl, rawurlencode($transactionId));

        return Http::withToken(self::apiToken())
            ->acceptJson()
            ->get($url, [
                'sort' => 'DESCENDING',
                'productType' => 'AUTO_RENEWABLE',
                'revoked' => 'false',
            ]);
    }

    private static function jsonBody($response): array
    {
        $body = $response->json();
        if (!is_array($body)) {
            throw new \RuntimeException('app_store_invalid_response');
        }

        return $body;
    }

    private static function apiToken(): string
    {
        $header = self::b64UrlEncode(json_encode([
            'alg' => 'ES256',
            'kid' => trim((string) env('APPLE_IAP_KEY_ID', '')),
            'typ' => 'JWT',
        ], JSON_UNESCAPED_SLASHES));

        $now = time();
        $claims = self::b64UrlEncode(json_encode([
            'iss' => trim((string) env('APPLE_IAP_ISSUER_ID', '')),
            'iat' => $now,
            'exp' => $now + 3600,
            'aud' => self::AUDIENCE,
            'bid' => self::bundleId(),
        ], JSON_UNESCAPED_SLASHES));

        $signingInput = $header.'.'.$claims;
        $privateKey = openssl_pkey_get_private(self::privateKeyPem() ?? '');
        if ($privateKey === false) {
            throw new \RuntimeException('app_store_invalid_private_key');
        }

        $signature = '';
        $ok = openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        openssl_free_key($privateKey);

        if (!$ok) {
            throw new \RuntimeException('app_store_sign_failed');
        }

        return $signingInput.'.'.self::b64UrlEncode(self::derToJose($signature, 64));
    }

    private static function privateKeyPem(): ?string
    {
        $inline = trim((string) env('APPLE_IAP_PRIVATE_KEY', ''));
        if ($inline !== '') {
            return str_replace('\\n', "\n", $inline);
        }

        $path = trim((string) env('APPLE_IAP_PRIVATE_KEY_PATH', ''));
        if ($path === '' || !is_file($path)) {
            return null;
        }

        $pem = @file_get_contents($path);
        return is_string($pem) && trim($pem) !== '' ? $pem : null;
    }

    private static function parseMillis(mixed $value): ?CarbonImmutable
    {
        if (is_numeric($value)) {
            try {
                return CarbonImmutable::createFromTimestampMs((int) $value);
            } catch (\Throwable) {
                return null;
            }
        }

        if (is_string($value) && trim($value) !== '' && ctype_digit($value)) {
            try {
                return CarbonImmutable::createFromTimestampMs((int) $value);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private static function mapStatus(?CarbonImmutable $expiresAt, ?CarbonImmutable $cancelledAt): string
    {
        if ($cancelledAt !== null) {
            return 'cancelled';
        }

        if ($expiresAt === null) {
            return 'pending';
        }

        return $expiresAt->isFuture() ? 'active' : 'expired';
    }

    private static function eventTypeForStatus(string $status): string
    {
        return match ($status) {
            'active' => 'verified',
            'cancelled' => 'cancelled',
            'expired' => 'expired',
            default => 'pending',
        };
    }

    private static function recordTransaction(
        User $org,
        Subscription $subscription,
        string $eventType,
        ?string $transactionId,
        ?string $orderId,
        array $payload,
        ?CarbonImmutable $periodEnd,
        CarbonImmutable|\DateTimeInterface|string|null $occurredAt,
    ): void {
        $exists = SubscriptionTransaction::query()
            ->where('subscription_id', $subscription->id)
            ->where('event_type', $eventType)
            ->when($transactionId, fn ($q) => $q->where('provider_transaction_id', $transactionId))
            ->when($orderId, fn ($q) => $q->where('provider_order_id', $orderId))
            ->when($periodEnd, fn ($q) => $q->where('period_end_at', $periodEnd))
            ->exists();

        if ($exists) {
            return;
        }

        SubscriptionTransaction::query()->create([
            'subscription_id' => $subscription->id,
            'user_id' => $org->id,
            'provider' => self::PROVIDER,
            'event_type' => $eventType,
            'product_id' => $subscription->product_id,
            'provider_transaction_id' => $transactionId,
            'provider_order_id' => $orderId,
            'purchase_token' => $subscription->purchase_token,
            'period_start_at' => $subscription->started_at,
            'period_end_at' => $periodEnd,
            'occurred_at' => $occurredAt,
            'payload' => $payload,
        ]);
    }

    private static function decodeSignedPayload(string $signedPayload): ?array
    {
        $parts = explode('.', $signedPayload);
        if (count($parts) < 2) {
            return null;
        }

        $json = self::b64UrlDecode($parts[1]);
        if ($json === null) {
            return null;
        }

        $payload = json_decode($json, true);
        return is_array($payload) ? $payload : null;
    }

    private static function b64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64UrlDecode(string $data): ?string
    {
        $padded = str_pad(strtr($data, '-_', '+/'), strlen($data) + ((4 - strlen($data) % 4) % 4), '=', STR_PAD_RIGHT);
        $decoded = base64_decode($padded, true);
        return is_string($decoded) ? $decoded : null;
    }

    private static function derToJose(string $der, int $partLength): string
    {
        $offset = 0;
        if (ord($der[$offset++]) !== 0x30) {
            throw new \RuntimeException('app_store_invalid_der_signature');
        }

        self::readDerLength($der, $offset);

        if (ord($der[$offset++]) !== 0x02) {
            throw new \RuntimeException('app_store_invalid_der_signature');
        }
        $rLength = self::readDerLength($der, $offset);
        $r = substr($der, $offset, $rLength);
        $offset += $rLength;

        if (ord($der[$offset++]) !== 0x02) {
            throw new \RuntimeException('app_store_invalid_der_signature');
        }
        $sLength = self::readDerLength($der, $offset);
        $s = substr($der, $offset, $sLength);

        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");

        return str_pad($r, $partLength / 2, "\x00", STR_PAD_LEFT)
            .str_pad($s, $partLength / 2, "\x00", STR_PAD_LEFT);
    }

    private static function readDerLength(string $der, int &$offset): int
    {
        $length = ord($der[$offset++]);
        if (($length & 0x80) === 0) {
            return $length;
        }

        $numOctets = $length & 0x7f;
        $length = 0;
        for ($i = 0; $i < $numOctets; $i++) {
            $length = ($length << 8) | ord($der[$offset++]);
        }

        return $length;
    }
}
