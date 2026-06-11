<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AppStoreSubscriptions;
use App\Support\GooglePlaySubscriptions;
use App\Support\OrgSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BillingController extends Controller
{
    public function verifyGooglePlay(Request $request)
    {
        $u = $request->user();
        if ($u->staff_id) {
            return response()->json(['message' => 'access_denied'], 403);
        }

        $data = $request->validate([
            'product_id' => ['required', 'string', 'max:128'],
            'purchase_token' => ['required', 'string', 'max:255'],
        ]);

        $org = $u->organization_id ? User::query()->findOrFail($u->organization_id) : $u;

        if (!GooglePlaySubscriptions::isConfigured()) {
            return response()->json(['message' => 'google_play_not_configured'], 503);
        }

        $subscription = GooglePlaySubscriptions::syncPurchase(
            $org,
            (string) $data['product_id'],
            (string) $data['purchase_token'],
        );

        $org->refresh();

        return response()->json([
            'ok' => true,
            'subscription' => [
                'id' => $subscription->id,
                'provider' => $subscription->provider,
                'product_id' => $subscription->product_id,
                'plan_code' => $subscription->plan_code,
                'status' => $subscription->status,
                'ends_at' => $subscription->ends_at?->toIso8601String(),
                'last_verified_at' => $subscription->last_verified_at?->toIso8601String(),
            ],
            ...OrgSubscription::status($org),
        ]);
    }

    public function syncGooglePlay(Request $request)
    {
        $u = $request->user();
        if ($u->staff_id) {
            return response()->json(['message' => 'access_denied'], 403);
        }

        $org = $u->organization_id ? User::query()->findOrFail($u->organization_id) : $u;

        if (!GooglePlaySubscriptions::isConfigured()) {
            return response()->json(['message' => 'google_play_not_configured'], 503);
        }

        $subscription = GooglePlaySubscriptions::syncLatestForOrg($org);
        $org->refresh();

        return response()->json([
            'ok' => true,
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'provider' => $subscription->provider,
                'product_id' => $subscription->product_id,
                'plan_code' => $subscription->plan_code,
                'status' => $subscription->status,
                'ends_at' => $subscription->ends_at?->toIso8601String(),
                'last_verified_at' => $subscription->last_verified_at?->toIso8601String(),
            ] : null,
            ...OrgSubscription::status($org),
        ]);
    }

    public function verifyAppleAppStore(Request $request)
    {
        $u = $request->user();
        if ($u->staff_id) {
            return response()->json(['message' => 'access_denied'], 403);
        }

        $data = $request->validate([
            'product_id' => ['required', 'string', 'max:128'],
            'transaction_id' => ['required', 'string', 'max:255'],
            'receipt_data' => ['nullable', 'string'],
        ]);

        $org = $u->organization_id ? User::query()->findOrFail($u->organization_id) : $u;

        if (!AppStoreSubscriptions::isConfigured()) {
            return response()->json(['message' => 'app_store_not_configured'], 503);
        }

        try {
            $subscription = AppStoreSubscriptions::syncTransaction(
                $org,
                (string) $data['product_id'],
                (string) $data['transaction_id'],
            );
        } catch (\Throwable $e) {
            Log::warning('app_store_verify_failed', [
                'user_id' => $u->id,
                'org_id' => $org->id,
                'product_id' => (string) $data['product_id'],
                'transaction_id' => (string) $data['transaction_id'],
                'receipt_data_present' => !empty($data['receipt_data']),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $org->refresh();

        return response()->json([
            'ok' => true,
            'subscription' => [
                'id' => $subscription->id,
                'provider' => $subscription->provider,
                'product_id' => $subscription->product_id,
                'plan_code' => $subscription->plan_code,
                'status' => $subscription->status,
                'ends_at' => $subscription->ends_at?->toIso8601String(),
                'last_verified_at' => $subscription->last_verified_at?->toIso8601String(),
            ],
            ...OrgSubscription::status($org),
        ]);
    }
}
