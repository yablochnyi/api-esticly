<?php

namespace App\Support;

use App\Jobs\SendSmartPush;
use App\Models\SmartPushDelivery;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;

class SmartPushNotifications
{
    private const TYPE_TODAY_PLAN = 'smart_today_plan';
    private const TYPE_DAILY_SUMMARY = 'smart_daily_summary';
    private const TYPE_TOMORROW_LOW = 'smart_tomorrow_low';

    public static function run(): void
    {
        User::query()
            ->whereNull('organization_id')
            ->whereNotNull('registered_at')
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('device_tokens')
                    ->whereColumn('device_tokens.user_id', 'users.id');
            })
            ->orderBy('id')
            ->chunkById(100, function ($orgs): void {
                foreach ($orgs as $org) {
                    self::runForOrg($org);
                }
            });
    }

    private static function runForOrg(User $org): void
    {
        $tz = self::timezone($org);
        $now = CarbonImmutable::now($tz);

        if (self::isDue($now, 8, 0)) {
            self::queueTodayPlan($org, $now, $tz);
        }

        if (self::isDue($now, 18, 30) && self::hasBookingBasics($org)) {
            self::queueTomorrowLow($org, $now, $tz);
        }

        if (self::isDue($now, 20, 30)) {
            self::queueDailySummary($org, $now, $tz);
        }
    }

    private static function queueTodayPlan(User $org, CarbonImmutable $now, string $tz): void
    {
        [$startUtc, $endUtc] = self::dayBoundsUtc($now, $tz);
        $visits = Visit::query()
            ->where('user_id', (int) $org->id)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('starts_at', [$startUtc, $endUtc])
            ->orderBy('starts_at')
            ->get(['id', 'starts_at']);

        $locale = self::locale($org);
        $title = (string) Lang::get('smart_push.today_plan_title', [], $locale);
        if ($visits->isEmpty()) {
            $body = (string) Lang::get('smart_push.today_plan_empty', [], $locale);
        } else {
            $first = CarbonImmutable::parse($visits->first()->starts_at)->utc()->setTimezone($tz)->format('H:i');
            $last = CarbonImmutable::parse($visits->last()->starts_at)->utc()->setTimezone($tz)->format('H:i');
            $body = (string) Lang::get('smart_push.today_plan_with_visits', [
                'count' => $visits->count(),
                'booking_word' => self::word($locale, 'booking', $visits->count()),
                'first' => $first,
                'last' => $last,
            ], $locale);
        }

        self::queueDelivery($org, self::TYPE_TODAY_PLAN, $now, $title, $body, [
            'screen' => 'calendar',
            'date' => $now->toDateString(),
        ]);
    }

    private static function queueDailySummary(User $org, CarbonImmutable $now, string $tz): void
    {
        [$startUtc, $endUtc] = self::dayBoundsUtc($now, $tz);
        $visits = Visit::query()
            ->where('user_id', (int) $org->id)
            ->whereBetween('starts_at', [$startUtc, $endUtc])
            ->get(['id', 'status', 'price']);

        $completed = $visits->where('status', 'completed');
        $cancelledCount = $visits->where('status', 'cancelled')->count();
        $completedCount = $completed->count();
        if ($completedCount === 0 && $cancelledCount === 0) {
            return;
        }

        $revenue = $completed->sum(fn (Visit $visit) => (float) ($visit->price ?? 0));
        $currency = trim((string) ($org->currency_code ?: ''));
        $locale = self::locale($org);
        $title = (string) Lang::get('smart_push.daily_summary_title', [], $locale);
        $body = (string) Lang::get('smart_push.daily_summary_body', [
            'visits' => $completedCount,
            'visit_word' => self::word($locale, 'visit', $completedCount),
            'cancelled' => $cancelledCount,
            'cancel_word' => self::word($locale, 'cancel', $cancelledCount),
            'revenue' => self::money($revenue, $currency),
        ], $locale);

        self::queueDelivery($org, self::TYPE_DAILY_SUMMARY, $now, $title, $body, [
            'screen' => 'analytics',
            'date' => $now->toDateString(),
        ]);
    }

    private static function queueTomorrowLow(User $org, CarbonImmutable $now, string $tz): void
    {
        $tomorrow = $now->addDay();
        [$startUtc, $endUtc] = self::dayBoundsUtc($tomorrow, $tz);
        $count = Visit::query()
            ->where('user_id', (int) $org->id)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('starts_at', [$startUtc, $endUtc])
            ->count();

        if ($count > 1) {
            return;
        }

        $locale = self::locale($org);
        $title = (string) Lang::get('smart_push.tomorrow_low_title', [], $locale);
        $bodyKey = $count === 0 ? 'smart_push.tomorrow_empty_body' : 'smart_push.tomorrow_one_body';
        $body = (string) Lang::get($bodyKey, [], $locale);

        self::queueDelivery($org, self::TYPE_TOMORROW_LOW, $now, $title, $body, [
            'screen' => 'calendar',
            'date' => $tomorrow->toDateString(),
            'count' => (string) $count,
        ]);
    }

    /**
     * @param array<string,string> $data
     */
    private static function queueDelivery(
        User $org,
        string $type,
        CarbonImmutable $localNow,
        string $title,
        string $body,
        array $data,
    ): void {
        try {
            $delivery = SmartPushDelivery::query()->create([
                'org_id' => (int) $org->id,
                'user_id' => (int) $org->id,
                'type' => $type,
                'local_date' => $localNow->toDateString(),
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'status' => 'pending',
            ]);
        } catch (QueryException) {
            return;
        }

        SendSmartPush::dispatch((int) $delivery->id);
    }

    private static function isDue(CarbonImmutable $now, int $hour, int $minute): bool
    {
        $target = $now->setTime($hour, $minute);
        $diff = $target->diffInMinutes($now, false);
        return $diff >= 0 && $diff < 5;
    }

    /**
     * @return array{0:CarbonImmutable,1:CarbonImmutable}
     */
    private static function dayBoundsUtc(CarbonImmutable $localDay, string $tz): array
    {
        $start = $localDay->setTimezone($tz)->startOfDay();
        $end = $localDay->setTimezone($tz)->endOfDay();

        return [$start->utc(), $end->utc()];
    }

    private static function hasBookingBasics(User $org): bool
    {
        $schedule = $org->schedule;
        if (!is_array($schedule) || count($schedule) === 0) {
            return false;
        }

        return $org->services()->exists();
    }

    private static function timezone(User $org): string
    {
        $tz = trim((string) ($org->timezone ?: ''));
        if ($tz !== '' && in_array($tz, timezone_identifiers_list(), true)) {
            return $tz;
        }

        return config('app.timezone') ?: 'Europe/Warsaw';
    }

    private static function locale(User $org): string
    {
        $supported = array_keys((array) config('site_locales.supported', []));
        if (empty($supported)) {
            $supported = ['pl', 'en', 'uk', 'it', 'fr', 'pt', 'de', 'es', 'cs'];
        }

        $lang = strtolower(trim((string) ($org->language_code ?: '')));
        if (in_array($lang, $supported, true)) {
            return $lang;
        }

        $fallback = (string) config('app.fallback_locale', 'en');
        return in_array($fallback, $supported, true) ? $fallback : 'en';
    }

    private static function money(float $amount, string $currency): string
    {
        $formatted = number_format($amount, 2, '.', ' ');
        $formatted = preg_replace('/\.00$/', '', $formatted) ?: $formatted;

        return trim($currency) === '' ? $formatted : "{$formatted} {$currency}";
    }

    private static function word(string $locale, string $type, int $count): string
    {
        $locale = strtolower($locale);

        $forms = match ($locale) {
            'uk' => [
                'booking' => ['запис', 'записи', 'записів'],
                'visit' => ['візит', 'візити', 'візитів'],
                'cancel' => ['скасування', 'скасування', 'скасувань'],
            ],
            'pl' => [
                'booking' => ['wizytę', 'wizyty', 'wizyt'],
                'visit' => ['wizyta', 'wizyty', 'wizyt'],
                'cancel' => ['anulowanie', 'anulowania', 'anulowań'],
            ],
            'cs' => [
                'booking' => ['rezervaci', 'rezervace', 'rezervací'],
                'visit' => ['návštěva', 'návštěvy', 'návštěv'],
                'cancel' => ['zrušení', 'zrušení', 'zrušení'],
            ],
            default => [],
        };

        if (isset($forms[$type])) {
            return self::slavicForm($count, $forms[$type][0], $forms[$type][1], $forms[$type][2]);
        }

        $simple = [
            'en' => [
                'booking' => ['booking', 'bookings'],
                'visit' => ['visit', 'visits'],
                'cancel' => ['cancellation', 'cancellations'],
            ],
            'de' => [
                'booking' => ['Termin', 'Termine'],
                'visit' => ['Besuch', 'Besuche'],
                'cancel' => ['Stornierung', 'Stornierungen'],
            ],
            'fr' => [
                'booking' => ['rendez-vous', 'rendez-vous'],
                'visit' => ['visite', 'visites'],
                'cancel' => ['annulation', 'annulations'],
            ],
            'it' => [
                'booking' => ['appuntamento', 'appuntamenti'],
                'visit' => ['visita', 'visite'],
                'cancel' => ['cancellazione', 'cancellazioni'],
            ],
            'es' => [
                'booking' => ['cita', 'citas'],
                'visit' => ['visita', 'visitas'],
                'cancel' => ['cancelación', 'cancelaciones'],
            ],
            'pt' => [
                'booking' => ['marcação', 'marcações'],
                'visit' => ['visita', 'visitas'],
                'cancel' => ['cancelamento', 'cancelamentos'],
            ],
        ];

        $pair = $simple[$locale][$type] ?? $simple['en'][$type];
        return $count === 1 ? $pair[0] : $pair[1];
    }

    private static function slavicForm(int $count, string $one, string $few, string $many): string
    {
        $n = abs($count);
        $lastTwo = $n % 100;
        $last = $n % 10;

        if ($lastTwo >= 11 && $lastTwo <= 14) {
            return $many;
        }

        if ($last === 1) {
            return $one;
        }

        if ($last >= 2 && $last <= 4) {
            return $few;
        }

        return $many;
    }
}
