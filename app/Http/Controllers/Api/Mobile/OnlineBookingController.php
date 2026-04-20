<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ShortLinks;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OnlineBookingController extends Controller
{
    private const COMPLETION_STEPS = 6;

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

    private function normalizeSlug(?string $slug): ?string
    {
        $value = trim((string) $slug);
        if ($value === '') {
            return null;
        }

        $value = ltrim($value, '@/');
        $value = Str::lower($value);
        $value = preg_replace('/[^a-z0-9._-]+/', '', $value) ?: '';

        return $value !== '' ? $value : null;
    }

    private function normalizePublicText(?string $value): ?string
    {
        $trimmed = trim((string) $value);
        return $trimmed !== '' ? $trimmed : null;
    }

    private function normalizeSocial(?string $value): ?string
    {
        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return null;
        }

        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            return $trimmed;
        }

        return ltrim($trimmed, '@');
    }

    private function normalizePhone(?string $value): ?string
    {
        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return null;
        }

        return preg_replace('/\s+/', ' ', $trimmed) ?: null;
    }

    private function normalizeSpecialties(array $rawSpecialties): array
    {
        $specialties = [];
        foreach ($rawSpecialties as $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = $this->normalizePublicText(Arr::get($item, 'name'));
            if ($name === null) {
                continue;
            }

            $specialties[] = [
                'name' => $name,
            ];
        }

        return $specialties;
    }

    private function completionPercent(User $org): int
    {
        $completed = 0;

        if (!empty($org->booking_slug)) {
            $completed++;
        }
        if (!empty($org->address)) {
            $completed++;
        }
        if (!empty($org->booking_phone)) {
            $completed++;
        }
        if (!empty($org->booking_bio)) {
            $completed++;
        }

        $specialties = is_array($org->booking_specialties) ? $org->booking_specialties : [];
        $hasSpecialties = collect($specialties)->contains(function ($item) {
            return is_array($item)
                && !empty($item['name']);
        });
        if ($hasSpecialties) {
            $completed++;
        }

        $hasSocial = collect([
            $org->booking_instagram,
            $org->booking_tiktok,
            $org->booking_telegram,
            $org->booking_whatsapp,
            $org->booking_viber,
        ])->contains(fn ($value) => !empty($value));
        if ($hasSocial) {
            $completed++;
        }

        return (int) round(($completed / self::COMPLETION_STEPS) * 100);
    }

    private function ensureSlug(User $org): string
    {
        $current = $this->normalizeSlug($org->booking_slug);
        if (!empty($current)) {
            if ($current !== $org->booking_slug) {
                $org->booking_slug = $current;
                $org->save();
            }

            return $current;
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
        return $base . '/@' . $slug;
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
        $this->forbidStaffUser($request);
        $org = $this->org($request);

        return response()->json([
            'booking_slug' => $this->ensureSlug($org),
            'booking_url' => $this->bookingUrl($org),
            'booking_handle' => '@' . $this->ensureSlug($org),
            'review_url' => $this->reviewUrl($org),
            'review_short_url' => ShortLinks::reviewShortUrl($org),
            'address' => (string) ($org->address ?? ''),
            'booking_phone' => (string) ($org->booking_phone ?? ''),
            'booking_instagram' => (string) ($org->booking_instagram ?? ''),
            'booking_tiktok' => (string) ($org->booking_tiktok ?? ''),
            'booking_telegram' => (string) ($org->booking_telegram ?? ''),
            'booking_whatsapp' => (string) ($org->booking_whatsapp ?? ''),
            'booking_viber' => (string) ($org->booking_viber ?? ''),
            'booking_bio' => (string) ($org->booking_bio ?? ''),
            'booking_specialties' => array_values(is_array($org->booking_specialties) ? $org->booking_specialties : []),
            'completion_percent' => $this->completionPercent($org),
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
            'booking_slug' => [
                'nullable',
                'string',
                'min:3',
                'max:32',
                'regex:/^[A-Za-z0-9@._-]+$/',
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'booking_phone' => ['nullable', 'string', 'max:50'],
            'booking_instagram' => ['nullable', 'string', 'max:255'],
            'booking_tiktok' => ['nullable', 'string', 'max:255'],
            'booking_telegram' => ['nullable', 'string', 'max:255'],
            'booking_whatsapp' => ['nullable', 'string', 'max:255'],
            'booking_viber' => ['nullable', 'string', 'max:255'],
            'booking_bio' => ['nullable', 'string', 'max:2000'],
            'booking_specialties' => ['nullable', 'array'],
            'booking_specialties.*.name' => ['nullable', 'string', 'max:100'],
            'online_booking_enabled' => ['nullable', 'boolean'],
            'online_booking_whitelist_only' => ['nullable', 'boolean'],
            'online_booking_period_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'online_booking_auto_confirm' => ['nullable', 'boolean'],
        ]);

        if (array_key_exists('booking_slug', $data)) {
            $normalizedSlug = $this->normalizeSlug($data['booking_slug']);
            if ($normalizedSlug === null) {
                throw ValidationException::withMessages([
                    'booking_slug' => ['The booking slug is invalid.'],
                ]);
            }
            $exists = User::query()
                ->where('booking_slug', $normalizedSlug)
                ->where('id', '!=', $org->id)
                ->exists();
            if ($exists) {
                throw ValidationException::withMessages([
                    'booking_slug' => ['The booking slug has already been taken.'],
                ]);
            }

            $org->booking_slug = $normalizedSlug;
        }
        if (array_key_exists('address', $data)) {
            $org->address = $this->normalizePublicText($data['address']);
        }
        if (array_key_exists('booking_phone', $data)) {
            $org->booking_phone = $this->normalizePhone($data['booking_phone']);
        }
        if (array_key_exists('booking_instagram', $data)) {
            $org->booking_instagram = $this->normalizeSocial($data['booking_instagram']);
        }
        if (array_key_exists('booking_tiktok', $data)) {
            $org->booking_tiktok = $this->normalizeSocial($data['booking_tiktok']);
        }
        if (array_key_exists('booking_telegram', $data)) {
            $org->booking_telegram = $this->normalizeSocial($data['booking_telegram']);
        }
        if (array_key_exists('booking_whatsapp', $data)) {
            $org->booking_whatsapp = $this->normalizePhone($data['booking_whatsapp']) ?? $this->normalizeSocial($data['booking_whatsapp']);
        }
        if (array_key_exists('booking_viber', $data)) {
            $org->booking_viber = $this->normalizePhone($data['booking_viber']) ?? $this->normalizeSocial($data['booking_viber']);
        }
        if (array_key_exists('booking_bio', $data)) {
            $org->booking_bio = $this->normalizePublicText($data['booking_bio']);
        }
        if (array_key_exists('booking_specialties', $data)) {
            $org->booking_specialties = $this->normalizeSpecialties($data['booking_specialties'] ?? []);
        }

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
