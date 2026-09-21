<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class BillingDashboardReport
{
    public const TIMEZONE = 'Europe/Warsaw';

    public const PROVIDERS = ['app_store' => 'iOS / App Store', 'google_play' => 'Android / Google Play'];

    public const STATES = [
        'paid' => 'Оплачено', 'refunded' => 'Возврат', 'partially_refunded' => 'Частичный возврат',
        'pending_refund' => 'Возврат в обработке', 'pending' => 'Ожидает оплаты',
        'cancelled' => 'Отменено', 'unknown' => 'Не подтверждено',
    ];

    public function build(array $filters = []): array
    {
        $now = CarbonImmutable::now(self::TIMEZONE);
        $provider = in_array($filters['provider'] ?? null, array_keys(self::PROVIDERS), true) ? $filters['provider'] : null;
        $plan = in_array($filters['plan'] ?? null, ['basic', 'pro'], true) ? $filters['plan'] : null;
        $period = is_string($filters['period'] ?? null) ? $filters['period'] : '12months';
        [$from, $until] = $this->bounds($period, $now);
        $payments = SubscriptionPayment::query()
            ->when($provider, fn ($q) => $q->where('provider', $provider))
            ->when($plan, fn ($q) => $q->where('plan_code', $plan))
            ->orderByDesc('paid_at')->orderByDesc('id')->get();
        $production = $payments->where('environment', 'production');
        $charged = $production->filter(fn ($p) => $p->paid_at && $p->currency && $p->amount_micros > 0
            && in_array($p->state, ['paid', 'refunded', 'partially_refunded', 'pending_refund'], true));
        $inPeriod = fn ($p) => $p->paid_at && (! $from || $p->paid_at->gte($from)) && $p->paid_at->lt($until);
        $selected = $charged->filter($inPeriod);

        $subscriptions = Subscription::with('user:id,company_name,email')->get();
        $productionSubscriptions = $subscriptions->filter(fn ($s) => BillingPaymentImporter::environment($s) === 'production');
        $matching = $productionSubscriptions->filter(fn ($s) => (! $provider || $s->provider === $provider)
            && (! $plan || $s->plan_code === $plan));
        $active = $matching->filter(fn ($s) => $s->ends_at?->isFuture()
            && ($s->status === 'active' || ($s->provider === 'google_play' && $s->status === 'cancelled')));
        $coveredIds = $production->filter(fn ($p) => in_array($p->state, ['paid', 'partially_refunded', 'pending_refund'], true)
            && $p->paid_at?->lte(now()) && $p->amount_micros > 0 && $p->period_ends_at?->isFuture())
            ->pluck('subscription_id');
        $paying = $active->whereIn('id', $coveredIds)->sortByDesc('ends_at')->unique('user_id');
        $activeUsers = $active->sortByDesc('ends_at')->unique('user_id');
        $devices = DeviceToken::select('user_id', 'platform', 'last_seen_at')->get()->groupBy('user_id');
        $paymentsByUser = $charged->groupBy('user_id');
        $selectedByUser = $selected->groupBy('user_id');
        $firstByUser = $charged->groupBy('user_id')->map(fn ($rows) => $rows->sortBy('paid_at')->first());
        $customerState = in_array($filters['customers'] ?? null, ['active', 'inactive'], true) ? $filters['customers'] : 'all';
        $search = is_string($filters['search'] ?? null) ? mb_strtolower(mb_substr(trim($filters['search']), 0, 100)) : '';
        $customers = $matching->groupBy('user_id')->map(function ($rows, $userId) use ($activeUsers, $paying, $devices, $paymentsByUser, $selectedByUser) {
            $current = $activeUsers->firstWhere('user_id', $userId) ?? $rows->sortByDesc('ends_at')->first();
            $userPayments = $paymentsByUser->get($userId, collect());
            $userDevices = $devices->get($userId, collect());

            return [
                'id' => $userId, 'name' => $current->user?->company_name ?: 'Аккаунт #'.$userId,
                'email' => $current->user?->email, 'plan' => $current->plan_code,
                'provider' => $current->provider, 'ends_at' => $current->ends_at,
                'verified_at' => $current->last_verified_at,
                'active' => $activeUsers->contains('user_id', $userId),
                'paying' => $paying->contains('user_id', $userId),
                'status' => $current->ends_at?->isPast() ? 'expired' : $current->status,
                'devices' => $userDevices->pluck('platform')->unique()->sort()->implode(', '),
                'last_seen_at' => $userDevices->max('last_seen_at'),
                'last_paid_at' => $userPayments->first()?->paid_at,
                'payments' => $selectedByUser->get($userId, collect())->count(),
                'totals' => $this->totals($userPayments),
            ];
        })->filter(fn ($row) => ($customerState === 'all' || ($customerState === 'active') === $row['active'])
            && ($search === '' || str_contains(mb_strtolower($row['id'].' '.$row['name'].' '.$row['email']), $search)))
            ->sortByDesc(fn ($row) => ($row['active'] ? '1' : '0').($row['ends_at']?->format('YmdHis') ?? ''))->values();

        $monthStart = $from?->setTimezone(self::TIMEZONE)->startOfMonth()
            ?? ($charged->min('paid_at')?->setTimezone(self::TIMEZONE)->startOfMonth() ?? $now->startOfMonth());
        $lastMonth = $until->setTimezone(self::TIMEZONE)->subSecond()->startOfMonth()->min($now->startOfMonth());
        $months = collect();
        for ($month = $monthStart; $month->lte($lastMonth); $month = $month->addMonth()) {
            $key = $month->format('Y-m');
            $rows = $selected->filter(fn ($p) => $p->paid_at->setTimezone(self::TIMEZONE)->format('Y-m') === $key);
            $months->push([
                'month' => $key, 'count' => $rows->count(), 'customers' => $rows->pluck('user_id')->unique()->count(),
                'new' => $firstByUser->filter(fn ($p) => $p->paid_at->setTimezone(self::TIMEZONE)->format('Y-m') === $key)->count(),
                'ios' => $rows->where('provider', 'app_store')->count(),
                'android' => $rows->where('provider', 'google_play')->count(),
                'totals' => $this->totals($rows),
            ]);
        }
        $ownerQuery = User::where(fn ($q) => $q->whereNull('staff_id')->orWhereColumn('organization_id', 'id'));

        return [
            'paying' => $paying->count(), 'active' => $activeUsers->count(),
            'basic' => $paying->where('plan_code', 'basic')->count(), 'pro' => $paying->where('plan_code', 'pro')->count(),
            'ios' => $paying->where('provider', 'app_store')->count(),
            'android' => $paying->where('provider', 'google_play')->count(),
            'grace' => $matching->whereIn('status', ['grace_period', 'on_hold'])->pluck('user_id')->unique()->count(),
            'expiring' => $paying->filter(fn ($s) => $s->ends_at->lte(now()->addDays(7)))->count(),
            'owners' => $ownerQuery->count(),
            'ever' => $charged->pluck('user_id')->unique()->count(),
            'period_customers' => $selected->pluck('user_id')->unique()->count(),
            'period_payments' => $selected->count(), 'total_payments' => $charged->count(),
            'totals' => $this->totals($selected), 'all_totals' => $this->totals($charged),
            'refunds' => $production->filter($inPeriod)->whereIn('state', ['refunded', 'partially_refunded', 'pending_refund'])->count(),
            'test_payments' => $payments->where('environment', 'test')->count(),
            'unknown' => $production->filter(fn ($p) => $p->amount_micros === null || ! $p->paid_at || ! $p->currency)->count(),
            'errors' => $production->whereNotNull('lookup_error')->count(),
            'unclassified' => $payments->where('environment', 'unknown')->count(),
            'checked_at' => $payments->max('checked_at'),
            'months' => $months->reverse()->values(), 'customers' => $customers,
            'payments' => $production->filter(fn ($p) => $period === 'all' || $inPeriod($p))->values(),
        ];
    }

    public function totals(Collection $payments): array
    {
        return $payments->whereNotNull('currency')->whereNotNull('amount_micros')->groupBy('currency')
            ->map(fn ($rows) => $rows->sum('amount_micros'))->sortKeys()->all();
    }

    public static function money(?int $micros, ?string $currency): string
    {
        return $micros === null || ! $currency ? 'Нет суммы' : number_format($micros / 1000000, 2, ',', ' ').' '.$currency;
    }

    private function bounds(string $period, CarbonImmutable $now): array
    {
        if ($period === 'all') {
            return [null, $now->addSecond()->utc()];
        }
        if (preg_match('/^20[0-9]{2}-(0[1-9]|1[0-2])$/D', $period)) {
            $start = CarbonImmutable::parse($period.'-01', self::TIMEZONE);
            if ($start->lte($now) && $start->year >= 2020) {
                return [$start->utc(), $start->addMonth()->utc()];
            }
        }

        return [$now->startOfMonth()->subMonths(11)->utc(), $now->addSecond()->utc()];
    }
}
