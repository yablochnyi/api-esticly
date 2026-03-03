<?php

namespace App\Http\Controllers;

use App\Models\LaunchWaitlistSubscription;
use App\Support\PublicLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PublicLaunchWaitlistController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $supported = PublicLocale::supported();
        $defaultLocale = (string) config('site_locales.default', 'en');

        $validated = $request->validate([
            'email' => ['required', 'string', 'email:rfc,dns', 'max:190'],
            'phone' => ['required', 'string', 'min:6', 'max:40'],
            'locale' => ['nullable', 'string', 'in:' . implode(',', $supported)],
        ]);

        $locale = strtolower((string) ($validated['locale'] ?? $defaultLocale));
        if (!in_array($locale, $supported, true)) {
            $locale = $defaultLocale;
        }

        $phone = $this->normalizePhone((string) $validated['phone']);
        $email = mb_strtolower(trim((string) $validated['email']));

        LaunchWaitlistSubscription::query()->updateOrCreate(
            [
                'email' => $email,
                'phone' => $phone,
            ],
            [
                'locale' => $locale,
                'ip' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                'subscribed_at' => Carbon::now(),
            ]
        );

        return redirect()
            ->route('marketing.localized', ['locale' => $locale])
            ->with('waitlist_success', __('landing.waitlist.success', [], $locale))
            ->withFragment('store-waitlist');
    }

    private function normalizePhone(string $value): string
    {
        $trimmed = trim($value);
        $digits = preg_replace('/\D+/', '', $trimmed);
        if (!is_string($digits) || $digits === '') {
            return $trimmed;
        }

        return '+' . $digits;
    }
}

