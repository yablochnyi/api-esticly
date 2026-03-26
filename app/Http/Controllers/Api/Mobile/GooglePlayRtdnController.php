<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Support\GooglePlaySubscriptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GooglePlayRtdnController extends Controller
{
    public function __invoke(Request $request)
    {
        $expectedToken = trim((string) env('GOOGLE_PLAY_RTDN_TOKEN', ''));
        $providedToken = trim((string) ($request->query('token') ?? $request->header('X-Rtdn-Token') ?? ''));

        if ($expectedToken === '' || !hash_equals($expectedToken, $providedToken)) {
            return response()->json(['message' => 'forbidden'], 403);
        }

        $payload = $request->json()->all();
        if (!is_array($payload)) {
            return response()->json(['message' => 'bad_request'], 400);
        }

        $message = $payload['message'] ?? null;
        if (!is_array($message)) {
            return response()->json(['ok' => true]);
        }

        $messageId = (string) ($message['messageId'] ?? '');
        $dataB64 = (string) ($message['data'] ?? '');
        if ($dataB64 === '') {
            return response()->json(['ok' => true]);
        }

        $decoded = base64_decode(strtr($dataB64, '-_', '+/'), true);
        if (!is_string($decoded) || $decoded === '') {
            return response()->json(['message' => 'bad_request'], 400);
        }

        $notification = json_decode($decoded, true);
        if (!is_array($notification)) {
            return response()->json(['message' => 'bad_request'], 400);
        }

        if (isset($notification['testNotification'])) {
            Log::info('google_play_rtdn_test_notification', [
                'message_id' => $messageId,
                'payload' => $notification,
            ]);

            return response()->json(['ok' => true]);
        }

        $packageName = (string) ($notification['packageName'] ?? '');
        if ($packageName !== '' && $packageName !== GooglePlaySubscriptions::packageName()) {
            return response()->json(['ok' => true]);
        }

        $subscriptionNotification = $notification['subscriptionNotification'] ?? null;
        if (!is_array($subscriptionNotification)) {
            return response()->json(['ok' => true]);
        }

        $purchaseToken = trim((string) ($subscriptionNotification['purchaseToken'] ?? ''));
        $notificationType = (int) ($subscriptionNotification['notificationType'] ?? 0);
        if ($purchaseToken === '') {
            return response()->json(['ok' => true]);
        }

        $subscription = Subscription::query()
            ->with('user')
            ->where('provider', GooglePlaySubscriptions::PROVIDER)
            ->where('purchase_token', $purchaseToken)
            ->first();

        $resolvedProductId = null;
        $linkedPurchaseToken = null;

        if (!$subscription) {
            try {
                $remoteSubscription = GooglePlaySubscriptions::fetchSubscriptionData($purchaseToken);
                $linkedPurchaseToken = GooglePlaySubscriptions::linkedPurchaseToken($remoteSubscription);
                $resolvedProductId = GooglePlaySubscriptions::resolveProductId($remoteSubscription);

                if ($linkedPurchaseToken) {
                    $subscription = Subscription::query()
                        ->with('user')
                        ->where('provider', GooglePlaySubscriptions::PROVIDER)
                        ->where('purchase_token', $linkedPurchaseToken)
                        ->first();
                }
            } catch (\Throwable $e) {
                Log::warning('google_play_rtdn_lookup_failed', [
                    'message_id' => $messageId,
                    'purchase_token' => $purchaseToken,
                    'notification_type' => $notificationType,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (!$subscription || !$subscription->user) {
            Log::warning('google_play_rtdn_subscription_not_found', [
                'message_id' => $messageId,
                'purchase_token' => $purchaseToken,
                'notification_type' => $notificationType,
                'linked_purchase_token' => $linkedPurchaseToken,
                'product_id' => $resolvedProductId,
            ]);

            return response()->json(['ok' => true]);
        }

        try {
            GooglePlaySubscriptions::syncPurchase(
                $subscription->user,
                $resolvedProductId ?: (string) $subscription->product_id,
                $purchaseToken,
            );

            Log::info('google_play_rtdn_processed', [
                'message_id' => $messageId,
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'notification_type' => $notificationType,
                'linked_purchase_token' => $linkedPurchaseToken,
            ]);
        } catch (\Throwable $e) {
            Log::warning('google_play_rtdn_sync_failed', [
                'message_id' => $messageId,
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'notification_type' => $notificationType,
                'linked_purchase_token' => $linkedPurchaseToken,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'sync_failed'], 500);
        }

        return response()->json(['ok' => true]);
    }
}
