<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\MobileAppVersion;
use App\Support\MobileStoreVersionResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AppVersionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'platform' => ['required', 'string', 'in:ios,android'],
        ]);

        $settings = MobileAppVersion::query()
            ->where('platform', $data['platform'])
            ->first();

        try {
            $store = MobileStoreVersionResolver::resolve($data['platform']);
        } catch (\Throwable $e) {
            Log::warning('mobile_store_version_lookup_failed', [
                'platform' => $data['platform'],
                'error' => $e->getMessage(),
            ]);
            $store = [
                'latest_version' => (string) ($settings?->latest_version ?? '0.0.0'),
                'latest_build' => null,
                'store_url' => (string) ($settings?->store_url ?? config('mobile_app.'.$data['platform'].'.store_url', '')),
            ];
        }

        return response()->json([
            'platform' => $data['platform'],
            'enabled' => $settings?->enabled ?? false,
            'latest_version' => $store['latest_version'],
            'latest_build' => $store['latest_build'],
            'minimum_version' => (string) ($settings?->minimum_version ?? '0.0.0'),
            'store_url' => $store['store_url'],
        ]);
    }
}
