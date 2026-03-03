<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ShortLinks;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OnlineBookingController extends Controller
{
    private function org(Request $request): User
    {
        $u = $request->user();
        return $u->organization_id ? User::query()->findOrFail($u->organization_id) : $u;
    }

    private function forbidStaffUser(Request $request): void
    {
        if ($request->user()?->staff_id) {
            abort(403, 'access_denied');
        }
    }

    private function ensureSlug(User $org): string
    {
        if (!empty($org->booking_slug)) {
            return $org->booking_slug;
        }

        do {
            $slug = Str::lower(Str::random(8));
            $exists = User::query()->where('booking_slug', $slug)->exists();
        } while ($exists);

        $org->booking_slug = $slug;
        $org->save();

        return $slug;
    }

    private function bookingUrl(User $org): string
    {
        $slug = $this->ensureSlug($org);
        $baseDomain = trim((string)env('BOOKING_BASE_DOMAIN', ''));
        if ($baseDomain !== '') {
            $scheme = env('BOOKING_SCHEME', 'https');
            return rtrim($scheme, ':/') . '://' . $slug . '.' . ltrim($baseDomain, '.');
        }

        $base = rtrim((string)config('app.url', ''), '/');
        if ($base === '') $base = 'http://127.0.0.1:8000';
        return $base . '/b/' . $slug;
    }

    private function reviewUrl(User $org): string
    {
        $slug = $this->ensureSlug($org);
        $base = rtrim((string)config('app.url', ''), '/');
        if ($base === '') $base = 'http://127.0.0.1:8000';
        return $base . '/r/' . $slug;
    }

    public function show(Request $request)
    {
        $org = $this->org($request);

        return response()->json([
            'booking_slug' => $this->ensureSlug($org),
            'booking_url' => $this->bookingUrl($org),
            'review_url' => $this->reviewUrl($org),
            'review_short_url' => ShortLinks::reviewShortUrl($org),
            'online_booking_enabled' => (bool)($org->online_booking_enabled ?? false),
            'online_booking_whitelist_only' => (bool)($org->online_booking_whitelist_only ?? false),
            'online_booking_period_days' => (int)($org->online_booking_period_days ?? 90),
            'online_booking_auto_confirm' => (bool)($org->online_booking_auto_confirm ?? false),
        ]);
    }

    public function update(Request $request)
    {
        $this->forbidStaffUser($request);
        $org = $this->org($request);

        $data = $request->validate([
            'online_booking_enabled' => ['nullable', 'boolean'],
            'online_booking_whitelist_only' => ['nullable', 'boolean'],
            'online_booking_period_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'online_booking_auto_confirm' => ['nullable', 'boolean'],
        ]);

        if (array_key_exists('online_booking_enabled', $data)) {
            $org->online_booking_enabled = (bool)$data['online_booking_enabled'];
        }
        if (array_key_exists('online_booking_whitelist_only', $data)) {
            $org->online_booking_whitelist_only = (bool)$data['online_booking_whitelist_only'];
        }
        if (array_key_exists('online_booking_period_days', $data)) {
            $org->online_booking_period_days = (int)$data['online_booking_period_days'];
        }
        if (array_key_exists('online_booking_auto_confirm', $data)) {
            $org->online_booking_auto_confirm = (bool)$data['online_booking_auto_confirm'];
        }

        $org->save();

        return $this->show($request);
    }
}


