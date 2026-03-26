<?php

namespace App\Support;

use App\Models\Subscription;
use App\Models\SubscriptionTransaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GooglePlaySubscriptions
{
    public const PROVIDER = 'google_play';
    public const SCOPE = 'https://www.googleapis.com/auth/androidpublisher';

    public static function packageName(): string
    {
        return trim((string) env('GOOGLE_PLAY_PACKAGE_NAME', ''));
    }

    public static function isConfigured(): bool
    {
        return GoogleServiceAccount::credentials() !== null && self::packageName() !== '';
    }

    public static function planCodeForProductId(string $productId): ?string
    {
        return match (trim($productId)) {
            'esticly_basic_monthly' => OrgSubscription::PLAN_BASIC,
            'esticly_pro_monthly' => OrgSubscription::PLAN_PRO,
            default => null,
        };
    }

    public static function shouldSyncOrg(User $org): bool
    {
        if (($org->subscription_provider ?? null) !== self::PROVIDER) {
            return false;
        }

        $latest = Subscription::query()
            ->where('user_id', $org->id)
            ->where('provider', self::PROVIDER)
            ->orderByDesc('ends_at')
            ->first();

        if (!$latest) {
            return false;
        }

        if (!$latest->last_verified_at) {
            return true;
        }

        return $latest->last_verified_at->lt(now()->subHours(6))
            || ($latest->ends_at && $latest->ends_at->lt(now()->addDay()));
    }

    public static function syncLatestForOrg(User $org): ?Subscription
    {
        $latest = Subscription::query()
            ->where('user_id', $org->id)
            ->where('provider', self::PROVIDER)
            ->orderByDesc('ends_at')
            ->first();

        if (!$latest) {
            return null;
        }

        return self::syncPurchase($org, (string) $latest->product_id, (string) $latest->purchase_token);
    }

    public static function syncPurchase(User $org, string $productId, string $purchaseToken): Subscription
    {
        if (!self::isConfigured()) {
            throw new \RuntimeException('google_play_not_configured');
        }

        $planCode = self::planCodeForProductId($productId);
        if ($planCode === null) {
            throw new \RuntimeException('google_play_unknown_product');
        }

        $body = self::fetchSubscription($purchaseToken);
        $lineItem = self::resolveLineItem($body, $productId);
        $endsAt = self::parseTs($lineItem['expiryTime'] ?? null);
        $startedAt = self::parseTs($body['startTime'] ?? null);
        $latestOrderId = trim((string) ($lineItem['latestSuccessfulOrderId'] ?? $body['latestOrderId'] ?? ''));
        $state = (string) ($body['subscriptionState'] ?? 'SUBSCRIPTION_STATE_UNSPECIFIED');
        $status = self::mapStatus($state, $endsAt);
        $cancelledAt = $status === 'cancelled' ? now() : null;

        $subscription = Subscription::query()->updateOrCreate(
            [
                'provider' => self::PROVIDER,
                'purchase_token' => $purchaseToken,
            ],
            [
                'user_id' => $org->id,
                'product_id' => $productId,
                'plan_code' => $planCode,
                'status' => $status,
                'provider_subscription_id' => $latestOrderId !== '' ? $latestOrderId : null,
                'started_at' => $startedAt,
                'renews_at' => $endsAt,
                'ends_at' => $endsAt,
                'cancelled_at' => $cancelledAt,
                'last_verified_at' => now(),
                'meta' => $body,
            ],
        );

        $isPaidActive = $endsAt?->isFuture() === true && in_array($status, ['active', 'grace_period', 'on_hold', 'cancelled'], true);
        $org->subscription_plan = $planCode;
        $org->subscription_ends_at = $endsAt;
        $org->subscription_provider = self::PROVIDER;
        if (!$isPaidActive && $endsAt && $endsAt->isPast()) {
            $org->subscription_ends_at = $endsAt;
        }
        $org->save();

        self::recordTransaction(
            org: $org,
            subscription: $subscription,
            eventType: self::eventTypeForStatus($status),
            orderId: $latestOrderId !== '' ? $latestOrderId : null,
            payload: $body,
            periodEnd: $endsAt,
            occurredAt: $startedAt ?? now(),
        );

        try {
            self::acknowledgeIfNeeded($productId, $purchaseToken, $body);
        } catch (\Throwable $e) {
            Log::warning('google_play_ack_failed', [
                'user_id' => $org->id,
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);
        }

        return $subscription;
    }

    public static function fetchSubscriptionData(string $purchaseToken): array
    {
        if (!self::isConfigured()) {
            throw new \RuntimeException('google_play_not_configured');
        }

        return self::fetchSubscription($purchaseToken);
    }

    public static function linkedPurchaseToken(array $body): ?string
    {
        $linked = trim((string) ($body['linkedPurchaseToken'] ?? ''));

        return $linked !== '' ? $linked : null;
    }

    public static function resolveProductId(array $body): ?string
    {
        $lineItems = $body['lineItems'] ?? null;
        if (!is_array($lineItems) || $lineItems === []) {
            return null;
        }

        foreach ($lineItems as $item) {
            if (!is_array($item)) {
                continue;
            }

            $productId = trim((string) ($item['productId'] ?? ''));
            if ($productId !== '') {
                return $productId;
            }
        }

        return null;
    }

    private static function fetchSubscription(string $purchaseToken): array
    {
        $token = GoogleServiceAccount::accessToken(self::SCOPE);
        $packageName = self::packageName();
        $url = sprintf(
            'https://androidpublisher.googleapis.com/androidpublisher/v3/applications/%s/purchases/subscriptionsv2/tokens/%s',
            rawurlencode($packageName),
            rawurlencode($purchaseToken),
        );

        $response = Http::withToken($token)->acceptJson()->get($url);
        if (!$response->successful()) {
            throw new \RuntimeException('google_play_fetch_failed');
        }

        $body = $response->json();
        if (!is_array($body)) {
            throw new \RuntimeException('google_play_invalid_response');
        }

        return $body;
    }

    private static function acknowledgeIfNeeded(string $productId, string $purchaseToken, array $body): void
    {
        $ackState = (string) ($body['acknowledgementState'] ?? '');
        if ($ackState === 'ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED') {
            return;
        }

        $token = GoogleServiceAccount::accessToken(self::SCOPE);
        $packageName = self::packageName();
        $url = sprintf(
            'https://androidpublisher.googleapis.com/androidpublisher/v3/applications/%s/purchases/subscriptions/%s/tokens/%s:acknowledge',
            rawurlencode($packageName),
            rawurlencode($productId),
            rawurlencode($purchaseToken),
        );

        $response = Http::withToken($token)->acceptJson()->post($url, []);
        if (!$response->successful()) {
            throw new \RuntimeException('google_play_acknowledge_failed');
        }
    }

    private static function resolveLineItem(array $body, string $productId): array
    {
        $lineItems = $body['lineItems'] ?? null;
        if (!is_array($lineItems) || $lineItems === []) {
            return [];
        }

        foreach ($lineItems as $item) {
            if (is_array($item) && (string) ($item['productId'] ?? '') === $productId) {
                return $item;
            }
        }

        return is_array($lineItems[0] ?? null) ? $lineItems[0] : [];
    }

    private static function parseTs(mixed $value): ?CarbonImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function mapStatus(string $state, ?CarbonImmutable $endsAt): string
    {
        if ($endsAt && $endsAt->isPast()) {
            return 'expired';
        }

        return match ($state) {
            'SUBSCRIPTION_STATE_ACTIVE' => 'active',
            'SUBSCRIPTION_STATE_IN_GRACE_PERIOD' => 'grace_period',
            'SUBSCRIPTION_STATE_ON_HOLD' => 'on_hold',
            'SUBSCRIPTION_STATE_CANCELED' => 'cancelled',
            'SUBSCRIPTION_STATE_EXPIRED' => 'expired',
            default => 'pending',
        };
    }

    private static function eventTypeForStatus(string $status): string
    {
        return match ($status) {
            'active' => 'verified',
            'grace_period' => 'grace_period',
            'on_hold' => 'on_hold',
            'cancelled' => 'cancelled',
            'expired' => 'expired',
            default => 'pending',
        };
    }

    private static function recordTransaction(
        User $org,
        Subscription $subscription,
        string $eventType,
        ?string $orderId,
        array $payload,
        ?CarbonImmutable $periodEnd,
        CarbonImmutable|\DateTimeInterface|string|null $occurredAt,
    ): void {
        $exists = SubscriptionTransaction::query()
            ->where('subscription_id', $subscription->id)
            ->where('event_type', $eventType)
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
            'provider_transaction_id' => $subscription->provider_subscription_id,
            'provider_order_id' => $orderId,
            'purchase_token' => $subscription->purchase_token,
            'period_start_at' => $subscription->started_at,
            'period_end_at' => $periodEnd,
            'occurred_at' => $occurredAt,
            'payload' => $payload,
        ]);
    }
}
