<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPromoCode;
use App\Models\SubscriptionPromoCodeRedemption;
use App\Support\MediaUrl;
use App\Support\OrgSubscription;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    public function store(Request $request)
    {
        // schedule приходит строкой JSON из multipart -> делаем массив
        if (is_string($request->input('schedule'))) {
            $decoded = json_decode($request->input('schedule'), true);
            if (is_array($decoded)) {
                $request->merge(['schedule' => $decoded]);
            }
        }

        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:1000'],
            'address' => ['nullable', 'string', 'max:5000'],
            'description' => ['nullable', 'string', 'max:2000'],
            'currency_code' => ['required', 'string', 'size:3', Rule::exists('currencies', 'code')],
            'language_code' => ['nullable', 'string', 'max:8', Rule::in($this->supportedLanguageCodes())],
            'timezone' => ['nullable', 'string', 'max:64'],
            'promo_code' => ['nullable', 'string', 'max:40'],
            'schedule' => ['required', 'array'],

            // ВАЖНО: файл логотипа
            'logo' => ['nullable', 'image', 'max:4096'],
        ]);

        $user = $request->user();
        $promoCode = $this->normalizePromoCode($data['promo_code'] ?? null);

        DB::transaction(function () use ($request, $user, $data, $promoCode) {
            $user->company_name = $data['company_name'];
            $user->address = $data['address'] ?? null;
            $user->description = $data['description'] ?? null;
            $user->currency_code = strtoupper($data['currency_code']);
            if (!empty($data['language_code'])) {
                $user->language_code = strtolower(trim($data['language_code']));
            }
            if (!empty($data['timezone'])) {
                $user->timezone = $data['timezone'];
            }
            $user->schedule = $data['schedule'];
            if (empty($user->registered_at)) {
                $user->registered_at = Carbon::now();
            }

            if ($promoCode !== null) {
                $this->redeemSubscriptionPromoCode($user, $promoCode);
            }

            if ($request->hasFile('logo')) {
                $path = $request->file('logo')->store('logos', 'public'); // storage/app/public/logos
                $user->logo_path = $path;
            }

            $user->save();
        });

        return response()->json([
            'ok' => true,
            'user' => $user->only([
                'id',
                'phone',
                'company_name',
                'currency_code',
                'language_code',
                'logo_path',
                'subscription_plan',
                'subscription_ends_at',
            ]),
            'logo_url' => MediaUrl::publicFile($user->logo_path),
        ]);
    }

    private function normalizePromoCode(?string $code): ?string
    {
        $value = strtoupper(trim((string) $code));

        return $value === '' ? null : $value;
    }

    private function redeemSubscriptionPromoCode($user, string $code): void
    {
        $promo = SubscriptionPromoCode::query()
            ->where('code', $code)
            ->lockForUpdate()
            ->first();

        if (! $promo || ! $promo->active) {
            throw ValidationException::withMessages([
                'promo_code' => ['Promocode is not active.'],
            ]);
        }

        if ($promo->expires_at && Carbon::parse($promo->expires_at)->isPast()) {
            throw ValidationException::withMessages([
                'promo_code' => ['Promocode is not active.'],
            ]);
        }

        if ($promo->max_uses !== null && (int) $promo->used_count >= (int) $promo->max_uses) {
            throw ValidationException::withMessages([
                'promo_code' => ['Promocode is not active.'],
            ]);
        }

        $alreadyRedeemed = SubscriptionPromoCodeRedemption::query()
            ->where('subscription_promo_code_id', $promo->id)
            ->where('org_id', $user->id)
            ->exists();

        if ($alreadyRedeemed) {
            throw ValidationException::withMessages([
                'promo_code' => ['Promocode is not active.'],
            ]);
        }

        $accessUntil = Carbon::now()->addMonthsNoOverflow((int) $promo->duration_months);

        $user->subscription_plan = OrgSubscription::PLAN_PRO;
        $user->subscription_ends_at = $accessUntil;
        $user->subscription_provider = 'promo';

        SubscriptionPromoCodeRedemption::query()->create([
            'subscription_promo_code_id' => $promo->id,
            'org_id' => $user->id,
            'user_id' => $user->id,
            'access_until' => $accessUntil,
        ]);

        $promo->increment('used_count');
    }

    private function supportedLanguageCodes(): array
    {
        $codes = array_keys((array) config('site_locales.supported', []));

        return $codes ?: ['en', 'uk', 'pl', 'cs', 'de', 'fr', 'it', 'es', 'pt'];
    }
}
