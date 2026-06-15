<?php

namespace App\Http\Controllers;

use App\Models\PublicMarketingEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PublicMarketingEventController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event' => ['required', 'string', Rule::in(['landing_view', 'app_store_click', 'google_play_click'])],
            'locale' => ['nullable', 'string', 'max:10'],
            'path' => ['nullable', 'string', 'max:255'],
            'page_url' => ['nullable', 'string', 'max:1000'],
            'referrer' => ['nullable', 'string', 'max:1000'],
            'utm_source' => ['nullable', 'string', 'max:120'],
            'utm_medium' => ['nullable', 'string', 'max:120'],
            'utm_campaign' => ['nullable', 'string', 'max:190'],
            'utm_content' => ['nullable', 'string', 'max:190'],
            'utm_term' => ['nullable', 'string', 'max:190'],
            'fbclid' => ['nullable', 'string', 'max:500'],
            'gclid' => ['nullable', 'string', 'max:500'],
            'visitor_id' => ['nullable', 'string', 'max:120'],
            'metadata' => ['nullable', 'array'],
        ]);

        try {
            PublicMarketingEvent::query()->create([
                'event' => $validated['event'],
                'locale' => $validated['locale'] ?? null,
                'path' => $validated['path'] ?? null,
                'page_url' => $validated['page_url'] ?? null,
                'referrer' => $validated['referrer'] ?? null,
                'utm_source' => $validated['utm_source'] ?? null,
                'utm_medium' => $validated['utm_medium'] ?? null,
                'utm_campaign' => $validated['utm_campaign'] ?? null,
                'utm_content' => $validated['utm_content'] ?? null,
                'utm_term' => $validated['utm_term'] ?? null,
                'fbclid_hash' => $this->hashValue($validated['fbclid'] ?? null),
                'gclid_hash' => $this->hashValue($validated['gclid'] ?? null),
                'visitor_id_hash' => $this->hashValue($validated['visitor_id'] ?? null),
                'ip_hash' => $this->hashValue($request->ip()),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                'metadata' => $validated['metadata'] ?? null,
                'occurred_at' => Carbon::now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('public_marketing_event_store_failed', [
                'event' => $validated['event'] ?? null,
                'message' => $e->getMessage(),
            ]);

            return response()->json(['ok' => false], 202);
        }

        return response()->json(['ok' => true], 201);
    }

    private function hashValue(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return hash_hmac('sha256', $value, (string) config('app.key'));
    }
}
