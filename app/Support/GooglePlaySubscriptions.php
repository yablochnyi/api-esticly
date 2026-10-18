<?php

namespace App\Support;

use App\Models\Subscription;
use App\Models\SubscriptionTransaction;
use App\Models\User;
use App\Services\BillingPaymentImporter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
            'esticly_basic_monthly', 'esticly_basic_6months', 'esticly_basic_yearly' => OrgSubscription::PLAN_BASIC,
            'esticly_pro_monthly', 'esticly_pro_6months', 'esticly_pro_yearly' => OrgSubscription::PLAN_PRO,
            default => null,
        };
    }

    public static function shouldSyncOrg(User $org): bool
    {
        return self::reconcilableForOrg($org)
            ->where(fn ($query) => $query->whereNull('last_verified_at')
                ->orWhere('last_verified_at', '<=', now()->subMinutes(15)))
            ->exists();
    }

    public static function syncLatestForOrg(User $org): ?Subscription
    {
        $last = null;
        $failure = null;
        // A stale/expired row may have renewed while another plan was on hold.
        foreach (self::reconcilableForOrg($org)->orderBy('id')->get() as $subscription) {
            try {
                $last = self::syncPurchase($org, $subscription->product_id, $subscription->purchase_token);
            } catch (\Throwable $e) {
                $failure = $e;
                Log::warning('google_play_reconcile_failed', [
                    'user_id' => $org->id, 'subscription_id' => $subscription->id,
                    'exception_type' => $e::class,
                ]);
            }
        }
        if ($failure) {
            throw new \RuntimeException('google_play_reconcile_incomplete', 0, $failure);
        }

        return self::effectiveForOrg($org) ?? $last;
    }

    private static function reconcilableForOrg(User $org)
    {
        // Play tokens stop being queryable 60 days after expiration.
        return Subscription::where('user_id', $org->id)->where('provider', self::PROVIDER)
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()->subDays(60)));
    }

    public static function effectiveForOrg(User $org): ?Subscription
    {
        $subscriptions = Subscription::where('user_id', $org->id)->where('provider', self::PROVIDER)->get();
        $replacedTokens = $subscriptions
            ->filter(fn ($s) => in_array($s->status, ['active', 'grace_period', 'cancelled', 'expired', 'on_hold'], true))
            ->map(fn ($s) => self::linkedPurchaseToken($s->meta ?? []))->filter()->all();

        return $subscriptions->filter(fn ($s) => $s->ends_at?->isFuture()
            && in_array($s->status, ['active', 'grace_period', 'cancelled'], true)
            && self::planCodeForProductId($s->product_id) !== null
            && ! in_array($s->purchase_token, $replacedTokens, true))
            ->sortByDesc(fn ($s) => [
                $s->plan_code === OrgSubscription::PLAN_PRO ? 1 : 0,
                $s->ends_at->timestamp, $s->id,
            ])->first();
    }

    public static function requiresExistingPurchase(User $org): bool
    {
        return self::reconcilableForOrg($org)->get()->contains(function ($s) {
            $state = $s->meta['subscriptionState'] ?? '';

            return in_array($state, [
                'SUBSCRIPTION_STATE_ACTIVE', 'SUBSCRIPTION_STATE_IN_GRACE_PERIOD',
                'SUBSCRIPTION_STATE_ON_HOLD', 'SUBSCRIPTION_STATE_PAUSED', 'SUBSCRIPTION_STATE_PENDING',
            ], true) || ($state === 'SUBSCRIPTION_STATE_CANCELED' && $s->ends_at?->isFuture());
        });
    }

    public static function syncPurchase(User $org, string $productId, string $purchaseToken): Subscription
    {
        // Serialize fetching and applying snapshots so a slower request cannot undo a newer one.
        $subscription = Cache::lock('google-play-sync:purchase:'.hash('sha256', $purchaseToken), 120)->block(5,
            fn () => Cache::lock('google-play-sync:org:'.$org->id, 120)->block(5,
                fn () => self::syncPurchaseLocked($org, $productId, $purchaseToken)));

        try {
            app(BillingPaymentImporter::class)->refreshGoogleSubscription($subscription);
        } catch (\Throwable $e) {
            Log::warning('google_play_payment_import_failed', ['subscription_id' => $subscription->id, 'exception_type' => $e::class]);
        }

        return $subscription;
    }

    private static function syncPurchaseLocked(User $org, string $productId, string $purchaseToken): Subscription
    {
        if (! self::isConfigured()) {
            throw new \RuntimeException('google_play_not_configured');
        }

        $planCode = self::planCodeForProductId($productId);
        if ($planCode === null) {
            throw new \RuntimeException('google_play_unknown_product');
        }

        $body = self::fetchSubscription($purchaseToken);
        $accountUserId = self::accountUserId($body);
        if ($accountUserId !== null && $accountUserId !== (int) $org->id) {
            throw new \RuntimeException('google_play_purchase_owned_by_another_account');
        }
        $lineItem = self::resolveLineItem($body);
        // Deferred plan changes can still return the old product until its period ends.
        $productId = (string) ($lineItem['productId'] ?? '');
        $planCode = self::planCodeForProductId($productId);
        if ($planCode === null) {
            throw new \RuntimeException('google_play_product_mismatch');
        }
        $endsAt = self::parseTs($lineItem['expiryTime'] ?? null);
        $startedAt = self::parseTs($body['startTime'] ?? null);
        $latestOrderId = trim((string) ($lineItem['latestSuccessfulOrderId'] ?? $body['latestOrderId'] ?? ''));
        $state = (string) ($body['subscriptionState'] ?? 'SUBSCRIPTION_STATE_UNSPECIFIED');
        $status = self::mapStatus($state, $endsAt);
        $cancelledAt = $status === 'cancelled' ? now() : null;

        $subscription = DB::transaction(function () use ($org, $productId, $purchaseToken, $planCode, $status, $latestOrderId, $startedAt, $endsAt, $cancelledAt, $body) {
            $lockedOrg = User::whereKey($org->id)->lockForUpdate()->firstOrFail();
            $tokens = array_filter([$purchaseToken, self::linkedPurchaseToken($body)]);
            if (Subscription::where('provider', self::PROVIDER)->whereIn('purchase_token', $tokens)
                ->where('user_id', '!=', $org->id)->exists()) {
                throw new \RuntimeException('google_play_purchase_owned_by_another_account');
            }
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

            $effective = self::effectiveForOrg($lockedOrg);
            // Do not let a Google callback revoke an active Apple/manual entitlement.
            if (! $lockedOrg->subscription_provider || $lockedOrg->subscription_provider === self::PROVIDER
                || ! $lockedOrg->subscription_ends_at?->isFuture()) {
                $lockedOrg->subscription_plan = $effective?->plan_code;
                $lockedOrg->subscription_ends_at = $effective?->ends_at;
                $lockedOrg->subscription_provider = self::PROVIDER;
                $lockedOrg->save();
            }

            self::recordTransaction(
                org: $org,
                subscription: $subscription,
                eventType: self::eventTypeForStatus($status),
                orderId: $latestOrderId !== '' ? $latestOrderId : null,
                payload: $body,
                periodEnd: $endsAt,
                occurredAt: $startedAt ?? now(),
            );

            return $subscription;
        });
        $org->refresh();

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
        if (! self::isConfigured()) {
            throw new \RuntimeException('google_play_not_configured');
        }

        return self::fetchSubscription($purchaseToken);
    }

    public static function linkedPurchaseToken(array $body): ?string
    {
        $linked = trim((string) ($body['linkedPurchaseToken'] ?? ''));

        return $linked !== '' ? $linked : null;
    }

    public static function accountUserId(array $body): ?int
    {
        $accountId = (string) data_get($body, 'externalAccountIdentifiers.obfuscatedExternalAccountId', '');

        return preg_match('/^00000000-6573-4000-8000-([0-9a-f]{12})$/i', $accountId, $match)
            ? (int) hexdec($match[1]) : null;
    }

    public static function resolveProductId(array $body): ?string
    {
        $productId = trim((string) (self::resolveLineItem($body)['productId'] ?? ''));
        return $productId !== '' ? $productId : null;
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

        $response = Http::withToken($token)->acceptJson()->connectTimeout(5)->timeout(20)->get($url);
        if (! $response->successful()) {
            throw new \RuntimeException('google_play_fetch_failed');
        }

        $body = $response->json();
        if (! is_array($body)) {
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
        if (! in_array($body['subscriptionState'] ?? '', ['SUBSCRIPTION_STATE_ACTIVE', 'SUBSCRIPTION_STATE_IN_GRACE_PERIOD', 'SUBSCRIPTION_STATE_CANCELED'], true)) {
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

        $response = Http::withToken($token)->acceptJson()->connectTimeout(5)->timeout(20)->post($url, []);
        if (! $response->successful()) {
            throw new \RuntimeException('google_play_acknowledge_failed');
        }
    }

    private static function resolveLineItem(array $body): array
    {
        $lineItems = $body['lineItems'] ?? null;
        if (! is_array($lineItems) || $lineItems === []) {
            return [];
        }

        // Deferred replacements contain both products. Before the switch the
        // future product has no expiry; afterwards the old product is expired.
        // Never let the requested SKU or array order select a stale entitlement.
        $selected = [];
        $latestExpiry = null;
        foreach ($lineItems as $item) {
            if (! is_array($item) || empty($item['productId'])) {
                continue;
            }
            $expiry = self::parseTs($item['expiryTime'] ?? null);
            if ($selected === [] || ($expiry !== null && ($latestExpiry === null || $expiry->gt($latestExpiry)))) {
                $selected = $item;
                $latestExpiry = $expiry;
            }
        }

        return $selected;
    }

    private static function parseTs(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
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
