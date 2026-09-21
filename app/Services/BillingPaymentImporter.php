<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Support\GooglePlaySubscriptions;
use App\Support\GoogleServiceAccount;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

/** Read-only store lookups; this projection never changes subscription entitlements. */
class BillingPaymentImporter
{
    public function refresh(bool $force = false): array
    {
        $errors = 0;
        Subscription::with('transactions')->chunkById(100, function ($subscriptions) use (&$errors) {
            foreach ($subscriptions as $subscription) {
                $snapshots = $subscription->transactions->pluck('payload')->push($subscription->meta ?? []);
                foreach ($snapshots as $snapshot) {
                    if (! is_array($snapshot)) {
                        continue;
                    }
                    if ($subscription->provider === 'app_store') {
                        $this->importAppleSnapshot($subscription, $snapshot);
                    } elseif ($subscription->provider === 'google_play') {
                        $this->discoverGoogleOrder($subscription, $snapshot);
                    }
                }
            }
        });

        SubscriptionPayment::where('provider', 'google_play')
            ->when(! $force, fn ($query) => $query->where(fn ($query) => $query
                ->whereNull('checked_at')->orWhere('checked_at', '<', now()->subDay())))
            ->chunkById(100, function ($payments) use (&$errors) {
                foreach ($payments as $payment) {
                    try {
                        $order = $this->fetchGoogleOrder($payment->external_id);
                        $this->importGoogleOrder($payment, $order);
                    } catch (\Throwable $exception) {
                        // Never persist HTTP exception text: it can contain credentials or purchase tokens.
                        $payment->update(['checked_at' => now(), 'lookup_error' => 'google_order_lookup_failed']);
                        $errors++;
                    }
                }
            });

        return ['payments' => SubscriptionPayment::count(), 'lookup_errors' => $errors];
    }

    public function fetchGoogleOrder(string $orderId): array
    {
        return Http::withToken(GoogleServiceAccount::accessToken('https://www.googleapis.com/auth/androidpublisher'))
            ->connectTimeout(5)->timeout(20)
            ->get('https://androidpublisher.googleapis.com/androidpublisher/v3/applications/'
                .rawurlencode(GooglePlaySubscriptions::packageName()).'/orders/'.rawurlencode($orderId))
            ->throw()->json();
    }

    public static function environment(Subscription $subscription): string
    {
        if ($subscription->provider === 'app_store') {
            return match (strtolower((string) data_get($subscription->meta, 'environment'))) {
                'production' => 'production',
                'sandbox' => 'test',
                default => 'unknown',
            };
        }

        return $subscription->provider === 'google_play'
            ? (array_key_exists('testPurchase', $subscription->meta ?? []) ? 'test' : 'production')
            : 'unknown';
    }

    public function importAppleSnapshot(Subscription $subscription, array $snapshot): void
    {
        $transactions = [];
        foreach (data_get($snapshot, 'history.signedTransactions', []) as $signed) {
            // Only decode snapshots previously fetched by our authenticated App Store integration.
            $parts = is_string($signed) ? explode('.', $signed) : [];
            $decoded = count($parts) === 3 ? base64_decode(strtr($parts[1], '-_', '+/'), true) : false;
            $transaction = $decoded === false ? null : json_decode($decoded, true);
            if (is_array($transaction)) {
                $transactions[] = $transaction;
            }
        }
        $transactions[] = $snapshot['latest_transaction'] ?? $snapshot['transaction'] ?? [];
        foreach ($transactions as $transaction) {
            if (empty($transaction['transactionId'])
                || (string) ($transaction['originalTransactionId'] ?? '') !== $subscription->purchase_token) {
                continue;
            }
            $product = (string) ($transaction['productId'] ?? '');
            $plan = match ($product) {
                'esticly_basic_monthly' => 'basic',
                'esticly_pro_monthly' => 'pro',
                default => null,
            };
            if ($plan === null) {
                continue;
            }
            $environment = match (strtolower((string) ($transaction['environment'] ?? $snapshot['environment'] ?? ''))) {
                'production' => 'production',
                'sandbox' => 'test',
                default => 'unknown',
            };
            $attributes = [
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'plan_code' => $plan,
                'environment' => $environment,
                'state' => isset($transaction['revocationDate']) ? 'refunded' : 'paid',
                'amount_micros' => isset($transaction['price']) ? (int) $transaction['price'] * 1000 : null,
                'currency' => $transaction['currency'] ?? null,
                'paid_at' => $this->milliseconds($transaction['purchaseDate'] ?? null),
                'period_ends_at' => $this->milliseconds($transaction['expiresDate'] ?? null),
                'checked_at' => now(),
                'lookup_error' => null,
            ];
            $payment = SubscriptionPayment::firstOrNew([
                'provider' => 'app_store', 'external_id' => (string) $transaction['transactionId'],
            ]);
            // An older verification snapshot must not erase a later refund or known price.
            if ($payment->state === 'refunded') {
                $attributes['state'] = 'refunded';
            }
            foreach (['amount_micros', 'currency', 'paid_at', 'period_ends_at'] as $field) {
                $attributes[$field] ??= $payment->{$field};
            }
            $payment->fill($attributes)->save();
        }
    }

    public function discoverGoogleOrder(Subscription $subscription, array $snapshot): void
    {
        $lines = $snapshot['lineItems'] ?? [];
        foreach ($lines as $line) {
            $id = $line['latestSuccessfulOrderId'] ?? $snapshot['latestOrderId'] ?? null;
            if (! is_string($id) || $id === '') {
                continue;
            }
            $payment = SubscriptionPayment::firstOrNew(['provider' => 'google_play', 'external_id' => $id]);
            if (! $payment->exists) {
                $payment->fill([
                    'subscription_id' => $subscription->id, 'user_id' => $subscription->user_id,
                    'plan_code' => GooglePlaySubscriptions::planCodeForProductId((string) ($line['productId'] ?? '')) ?? $subscription->plan_code,
                    'environment' => array_key_exists('testPurchase', $snapshot) ? 'test' : 'production',
                    'state' => 'unknown',
                    'period_ends_at' => $this->date($line['expiryTime'] ?? null),
                ])->save();
            }
        }
    }

    public function importGoogleOrder(SubscriptionPayment $payment, array $order): void
    {
        if (($order['orderId'] ?? '') !== $payment->external_id) {
            throw new \RuntimeException('order_id_mismatch');
        }
        $money = $order['total'] ?? [];
        $amount = isset($money['currencyCode'])
            ? ((int) ($money['units'] ?? 0) * 1000000 + intdiv((int) ($money['nanos'] ?? 0), 1000)) : null;
        $payment->update([
            'plan_code' => GooglePlaySubscriptions::planCodeForProductId((string) data_get($order, 'lineItems.0.productId')) ?? $payment->plan_code,
            'state' => match ($order['state'] ?? '') {
                'PROCESSED' => 'paid',
                'REFUNDED' => 'refunded',
                'PARTIALLY_REFUNDED' => 'partially_refunded',
                'PENDING_REFUND' => 'pending_refund',
                'PENDING' => 'pending',
                'CANCELED' => 'cancelled',
                default => 'unknown',
            },
            'amount_micros' => $amount,
            'currency' => $money['currencyCode'] ?? null,
            'paid_at' => $this->date(data_get($order, 'orderHistory.processedEvent.eventTime')),
            'period_ends_at' => $this->date(data_get($order, 'lineItems.0.subscriptionDetails.servicePeriodEndTime'))
                ?? $payment->period_ends_at,
            'checked_at' => now(), 'lookup_error' => null,
        ]);
    }

    private function milliseconds(mixed $value): ?CarbonImmutable
    {
        return is_numeric($value) && $value > 0 ? CarbonImmutable::createFromTimestampMs((int) $value, 'UTC') : null;
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value)->utc() : null;
    }
}
