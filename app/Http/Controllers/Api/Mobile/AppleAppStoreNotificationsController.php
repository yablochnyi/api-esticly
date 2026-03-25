<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Support\AppStoreSubscriptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AppleAppStoreNotificationsController extends Controller
{
    public function __invoke(Request $request)
    {
        $expectedToken = trim((string) env('APPLE_IAP_NOTIFICATION_TOKEN', ''));
        $providedToken = trim((string) ($request->query('token') ?? $request->header('X-Apple-Iap-Token') ?? ''));

        if ($expectedToken === '' || !hash_equals($expectedToken, $providedToken)) {
            return response()->json(['message' => 'forbidden'], 403);
        }

        $signedPayload = trim((string) ($request->json('signedPayload') ?? ''));
        if ($signedPayload === '') {
            return response()->json(['message' => 'bad_request'], 400);
        }

        $payload = AppStoreSubscriptions::decodeNotification($signedPayload);
        if (!is_array($payload)) {
            return response()->json(['message' => 'bad_request'], 400);
        }

        $notificationType = (string) ($payload['notificationType'] ?? '');
        $subtype = (string) ($payload['subtype'] ?? '');
        if ($notificationType === 'TEST') {
            Log::info('app_store_notification_test', [
                'notification_type' => $notificationType,
                'subtype' => $subtype,
            ]);

            return response()->json(['ok' => true]);
        }

        $data = $payload['data'] ?? null;
        if (!is_array($data)) {
            return response()->json(['ok' => true]);
        }

        $signedTransactionInfo = trim((string) ($data['signedTransactionInfo'] ?? ''));
        $transaction = $signedTransactionInfo !== '' ? AppStoreSubscriptions::decodeNotification($signedTransactionInfo) : null;
        if (!is_array($transaction)) {
            return response()->json(['ok' => true]);
        }

        $bundleId = trim((string) ($transaction['bundleId'] ?? ''));
        if ($bundleId !== '' && $bundleId !== AppStoreSubscriptions::bundleId()) {
            return response()->json(['ok' => true]);
        }

        $originalTransactionId = trim((string) ($transaction['originalTransactionId'] ?? ''));
        $transactionId = trim((string) ($transaction['transactionId'] ?? ''));
        $productId = trim((string) ($transaction['productId'] ?? ''));
        $lookupId = $originalTransactionId !== '' ? $originalTransactionId : $transactionId;

        if ($lookupId === '' || $productId === '') {
            return response()->json(['ok' => true]);
        }

        $subscription = Subscription::query()
            ->with('user')
            ->where('provider', AppStoreSubscriptions::PROVIDER)
            ->where(function ($q) use ($lookupId) {
                $q->where('purchase_token', $lookupId)
                    ->orWhere('provider_subscription_id', $lookupId);
            })
            ->first();

        if (!$subscription || !$subscription->user) {
            Log::warning('app_store_notification_subscription_not_found', [
                'notification_type' => $notificationType,
                'subtype' => $subtype,
                'lookup_id' => $lookupId,
                'product_id' => $productId,
            ]);

            return response()->json(['ok' => true]);
        }

        try {
            AppStoreSubscriptions::syncTransaction(
                $subscription->user,
                $productId,
                $lookupId,
            );

            Log::info('app_store_notification_processed', [
                'notification_type' => $notificationType,
                'subtype' => $subtype,
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'lookup_id' => $lookupId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('app_store_notification_sync_failed', [
                'notification_type' => $notificationType,
                'subtype' => $subtype,
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'lookup_id' => $lookupId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'sync_failed'], 500);
        }

        return response()->json(['ok' => true]);
    }
}
