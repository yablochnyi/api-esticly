<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\User;
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
            Log::warning('app_store_notification_forbidden', [
                'token_configured' => $expectedToken !== '',
                'token_provided' => $providedToken !== '',
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'forbidden'], 403);
        }

        $signedPayload = trim((string) ($request->json('signedPayload') ?? ''));
        if ($signedPayload === '') {
            Log::warning('app_store_notification_missing_signed_payload');

            return response()->json(['message' => 'bad_request'], 400);
        }

        $payload = AppStoreSubscriptions::decodeNotification($signedPayload);
        if (!is_array($payload)) {
            Log::warning('app_store_notification_invalid_signed_payload');

            return response()->json(['message' => 'bad_request'], 400);
        }

        $notificationType = (string) ($payload['notificationType'] ?? '');
        $subtype = (string) ($payload['subtype'] ?? '');
        $environment = (string) ($payload['data']['environment'] ?? '');
        if ($notificationType === 'TEST') {
            Log::info('app_store_notification_test', [
                'notification_type' => $notificationType,
                'subtype' => $subtype,
                'environment' => $environment,
            ]);

            return response()->json(['ok' => true]);
        }

        $data = $payload['data'] ?? null;
        if (!is_array($data)) {
            Log::info('app_store_notification_without_data', [
                'notification_type' => $notificationType,
                'subtype' => $subtype,
                'environment' => $environment,
            ]);

            return response()->json(['ok' => true]);
        }

        $signedTransactionInfo = trim((string) ($data['signedTransactionInfo'] ?? ''));
        $transaction = $signedTransactionInfo !== '' ? AppStoreSubscriptions::decodeNotification($signedTransactionInfo) : null;
        if (!is_array($transaction)) {
            Log::warning('app_store_notification_invalid_transaction', [
                'notification_type' => $notificationType,
                'subtype' => $subtype,
                'environment' => $environment,
                'signed_transaction_present' => $signedTransactionInfo !== '',
            ]);

            return response()->json(['ok' => true]);
        }

        $bundleId = trim((string) ($transaction['bundleId'] ?? ''));
        if ($bundleId !== '' && $bundleId !== AppStoreSubscriptions::bundleId()) {
            Log::warning('app_store_notification_bundle_mismatch', [
                'notification_type' => $notificationType,
                'subtype' => $subtype,
                'environment' => $environment,
                'bundle_id' => $bundleId,
                'expected_bundle_id' => AppStoreSubscriptions::bundleId(),
            ]);

            return response()->json(['ok' => true]);
        }

        $originalTransactionId = trim((string) ($transaction['originalTransactionId'] ?? ''));
        $transactionId = trim((string) ($transaction['transactionId'] ?? ''));
        $productId = trim((string) ($transaction['productId'] ?? ''));
        $lookupId = $originalTransactionId !== '' ? $originalTransactionId : $transactionId;

        if ($lookupId === '' || $productId === '') {
            Log::warning('app_store_notification_missing_transaction_fields', [
                'notification_type' => $notificationType,
                'subtype' => $subtype,
                'environment' => $environment,
                'lookup_id_present' => $lookupId !== '',
                'product_id_present' => $productId !== '',
            ]);

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
            $appAccountToken = trim((string) ($transaction['appAccountToken'] ?? ''));
            $orgId = AppStoreSubscriptions::orgIdForAppAccountToken($appAccountToken);
            $org = $orgId ? User::query()->find($orgId) : null;

            if ($org) {
                try {
                    $synced = AppStoreSubscriptions::syncTransaction(
                        $org,
                        $productId,
                        $lookupId,
                    );

                    Log::info('app_store_notification_processed_from_app_account_token', [
                        'notification_type' => $notificationType,
                        'subtype' => $subtype,
                        'environment' => $environment,
                        'subscription_id' => $synced->id,
                        'user_id' => $org->id,
                        'lookup_id' => $lookupId,
                    ]);

                    return response()->json(['ok' => true]);
                } catch (\Throwable $e) {
                    Log::warning('app_store_notification_app_account_token_sync_failed', [
                        'notification_type' => $notificationType,
                        'subtype' => $subtype,
                        'environment' => $environment,
                        'user_id' => $org->id,
                        'lookup_id' => $lookupId,
                        'product_id' => $productId,
                        'error' => $e->getMessage(),
                    ]);

                    return response()->json(['message' => 'sync_failed'], 500);
                }
            }

            Log::warning('app_store_notification_subscription_not_found', [
                'notification_type' => $notificationType,
                'subtype' => $subtype,
                'environment' => $environment,
                'lookup_id' => $lookupId,
                'product_id' => $productId,
                'app_account_token_present' => $appAccountToken !== '',
                'app_account_token_org_id' => $orgId,
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
                'environment' => $environment,
                'subscription_id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'lookup_id' => $lookupId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('app_store_notification_sync_failed', [
                'notification_type' => $notificationType,
                'subtype' => $subtype,
                'environment' => $environment,
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
