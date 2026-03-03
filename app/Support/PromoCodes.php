<?php

namespace App\Support;

use App\Models\PromoCode;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;

class PromoCodes
{
    public static function norm(?string $code): string
    {
        $c = strtoupper(trim((string)$code));
        // keep only A-Z0-9 and underscore/dash (safe for users)
        $c = (string)preg_replace('/[^A-Z0-9_-]+/', '', $c);
        return substr($c, 0, 40);
    }

    public static function findActive(User $org, string $code): ?PromoCode
    {
        $code = self::norm($code);
        if ($code === '') return null;
        return PromoCode::query()
            ->where('user_id', $org->id)
            ->where('code', $code)
            ->where('active', true)
            ->first();
    }

    /**
     * @return array{ok:bool, message:string, promo:?PromoCode, discount:float, final:float}
     */
    public static function apply(User $org, ?PromoCode $promo, ?Service $service, float $basePrice, ?string $localDateYmd): array
    {
        $basePrice = max(0.0, $basePrice);
        if (!$promo) {
            return ['ok' => false, 'message' => 'promo_invalid', 'promo' => null, 'discount' => 0.0, 'final' => $basePrice];
        }

        // optional service restriction
        if ($promo->service_id && $service && (int)$promo->service_id !== (int)$service->id) {
            return ['ok' => false, 'message' => 'promo_not_for_service', 'promo' => $promo, 'discount' => 0.0, 'final' => $basePrice];
        }

        // validity date check in org tz (compare by local date)
        if ($promo->valid_until) {
            $d = $localDateYmd ?: Carbon::now($org->timezone ?: 'Europe/Warsaw')->toDateString();
            $until = Carbon::parse($promo->valid_until)->toDateString();
            if ($d > $until) {
                return ['ok' => false, 'message' => 'promo_expired', 'promo' => $promo, 'discount' => 0.0, 'final' => $basePrice];
            }
        }

        $type = (string)($promo->type ?? '');
        $amount = (float)($promo->amount ?? 0);
        $discount = 0.0;

        if ($type === 'percent') {
            $discount = $basePrice * max(0.0, min(100.0, $amount)) / 100.0;
        } elseif ($type === 'fixed') {
            $discount = max(0.0, $amount);
        } else {
            return ['ok' => false, 'message' => 'promo_invalid', 'promo' => $promo, 'discount' => 0.0, 'final' => $basePrice];
        }

        $final = max(0.0, $basePrice - $discount);

        // Normalize decimals to 2 dp.
        $discount = round($discount, 2);
        $final = round($final, 2);

        return ['ok' => true, 'message' => 'promo_applied', 'promo' => $promo, 'discount' => $discount, 'final' => $final];
    }
}

