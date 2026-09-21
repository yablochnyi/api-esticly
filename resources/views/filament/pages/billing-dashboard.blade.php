@php
    use App\Services\BillingDashboardReport as Billing;
    use App\Filament\Resources\CompanyResource;
    $report = $this->report;
    $date = fn ($value) => $value ? \Carbon\CarbonImmutable::parse($value)->setTimezone(Billing::TIMEZONE)->format('d.m.Y H:i') : 'Нет данных';
    $moneyList = fn ($totals) => collect($totals)->map(fn ($amount, $currency) => Billing::money($amount, $currency))->implode(' · ') ?: 'Нет оплат';
    $customers = $this->rows($report['customers'], 'customersPage');
    $payments = $this->rows($report['payments'], 'paymentsPage');
    $currencies = collect(array_keys($report['all_totals']))->merge(array_keys($report['totals']))->unique()->sort();
    $maxMonth = max(1, $report['months']->max('count') ?? 0);
@endphp
<div class="billing-dashboard" wire:loading.class="billing-loading">
    <style>
        .billing-dashboard { --billing-border: #e5e7eb; --billing-muted: #64748b; --billing-accent: #047857; color: #111827; display: grid; gap: 28px; min-width: 0; letter-spacing: 0; }
        .dark .billing-dashboard { --billing-border: #374151; --billing-muted: #9ca3af; --billing-accent: #6ee7b7; color: #f3f4f6; }
        .billing-dashboard.billing-loading { opacity: .6; }
        .billing-dashboard .billing-stats { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 24px; border-bottom: 1px solid var(--billing-border); padding-bottom: 24px; }
        .billing-dashboard .billing-stat-title, .billing-dashboard .billing-muted { color: var(--billing-muted); font-size: 13px; }
        .billing-dashboard .billing-value { font-size: 30px; line-height: 1.4; font-weight: 650; font-variant-numeric: tabular-nums; }
        .billing-dashboard .billing-stat-title { display: flex; align-items: center; gap: 8px; }
        .billing-dashboard .billing-stat-title svg { width: 18px; height: 18px; flex-shrink: 0; }
        .billing-dashboard h2 { font-size: 18px; line-height: 1.5; font-weight: 600; margin: 0; }
        .billing-dashboard > section { min-width: 0; }
        .billing-dashboard .billing-heading { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
        .billing-dashboard .billing-scroll { overflow-x: auto; max-width: 100%; border-top: 1px solid var(--billing-border); border-bottom: 1px solid var(--billing-border); }
        .billing-dashboard table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; }
        .billing-dashboard th { color: var(--billing-muted); font-weight: 500; white-space: nowrap; }
        .billing-dashboard th, .billing-dashboard td { padding: 12px 14px; border-bottom: 1px solid var(--billing-border); vertical-align: top; }
        .billing-dashboard tbody tr:last-child td { border-bottom: none; }
        .billing-dashboard .billing-num { font-variant-numeric: tabular-nums; white-space: nowrap; }
        .billing-dashboard .billing-link { color: var(--billing-accent); text-decoration: underline; text-underline-offset: 3px; }
        .billing-dashboard .billing-good { color: var(--billing-accent); }
        .billing-dashboard .billing-warning { color: #b45309; }
        .dark .billing-dashboard .billing-warning { color: #fbbf24; }
        .billing-dashboard .billing-customer { min-width: 180px; max-width: 280px; overflow-wrap: anywhere; }
        .billing-dashboard .billing-empty { padding: 24px; text-align: center; color: var(--billing-muted); }
        .billing-dashboard .billing-quality { border-left: 3px solid #d97706; padding-left: 14px; font-size: 13px; }
        .billing-dashboard .billing-bar { display: inline-block; width: 72px; height: 6px; background: var(--billing-border); margin-left: 10px; vertical-align: middle; }
        .billing-dashboard .billing-bar span { display: block; background: #0d9488; height: 100%; }
        .billing-dashboard .billing-pagination { padding-top: 14px; }
        .billing-dashboard .billing-months { min-width: 760px; }
        .billing-dashboard .billing-customers { min-width: 1250px; }
        .billing-dashboard .billing-payments { min-width: 700px; }
        @media(max-width: 640px) { .billing-dashboard .billing-stats { grid-template-columns: repeat(2,minmax(0,1fr)); gap: 20px 12px; } .billing-dashboard th, .billing-dashboard td { padding: 10px; } }
    </style>

    <div class="billing-heading">
        <span class="billing-muted">Данные на {{ $date($report['checked_at']) }} · Europe/Warsaw</span>
        <x-filament::icon-button icon="heroicon-o-arrow-path" label="Обновить показатели" wire:click="$refresh" />
    </div>

    <div class="billing-stats">
        <div>
            <div class="billing-stat-title"><x-filament::icon icon="heroicon-o-users" />Платящих сейчас</div>
            <div class="billing-value">{{ $report['paying'] }}</div>
            <div class="billing-muted">Basic: {{ $report['basic'] }} · Pro: {{ $report['pro'] }}</div>
        </div>
        <div>
            <div class="billing-stat-title"><x-filament::icon icon="heroicon-o-device-phone-mobile" />Магазины активных подписчиков</div>
            <div class="billing-value">{{ $report['ios'] }} / {{ $report['android'] }}</div>
            <div class="billing-muted">iOS / Android · по текущей подписке</div>
        </div>
        <div>
            <div class="billing-stat-title"><x-filament::icon icon="heroicon-o-credit-card" />Оплат в выбранном периоде</div>
            <div class="billing-value">{{ $report['period_payments'] }}</div>
            <div class="billing-muted">{{ $report['period_customers'] }} уникальных аккаунтов</div>
        </div>
        <div>
            <div class="billing-stat-title"><x-filament::icon icon="heroicon-o-user-group" />Платили за всё время</div>
            <div class="billing-value">{{ $report['ever'] }}</div>
            <div class="billing-muted">{{ $report['total_payments'] }} оплат · {{ $report['owners'] }} аккаунтов владельцев всего</div>
        </div>
        <div>
            <div class="billing-stat-title"><x-filament::icon icon="heroicon-o-clock" />Оплаченный срок истекает за 7 дней</div>
            <div class="billing-value">{{ $report['expiring'] }}</div>
            <div class="billing-muted">Продление ещё не учтено</div>
        </div>
        <div>
            <div class="billing-stat-title"><x-filament::icon icon="heroicon-o-exclamation-circle" />Льготный период / удержание</div>
            <div class="billing-value">{{ $report['grace'] }}</div>
            <div class="billing-muted">Активных без подтверждённой суммы: {{ $report['active'] - $report['paying'] }}</div>
        </div>
    </div>

    <div class="billing-quality">
        История сохранённых заказов; более ранние отсутствующие заказы не включены. Бесплатный доступ и тестовые покупки не считаются оплатами.
        Суммы до возвратов, налоговых удержаний и комиссий магазинов, не выплаты на банковский счёт.
        <div class="billing-muted">Тестовых заказов: {{ $report['test_payments'] }} · Неполных: {{ $report['unknown'] }} · Не определена среда: {{ $report['unclassified'] }} · Ошибок загрузки: {{ $report['errors'] }} · Заказов периода с возвратом: {{ $report['refunds'] }}</div>
    </div>

    <section>
        <div class="billing-heading"><h2>Оплаты по валютам</h2><span class="billing-muted">Без пересчёта курсов</span></div>
        <div class="billing-scroll"><table>
            <thead><tr><th>Валюта</th><th>Выбранный период</th><th>За всё время</th></tr></thead>
            <tbody>@forelse ($currencies as $currency)
                <tr><td>{{ $currency }}</td><td class="billing-num">{{ Billing::money($report['totals'][$currency] ?? 0, $currency) }}</td><td class="billing-num">{{ Billing::money($report['all_totals'][$currency] ?? 0, $currency) }}</td></tr>
            @empty<tr><td colspan="3" class="billing-empty">Подтверждённых сумм пока нет</td></tr>@endforelse</tbody>
        </table></div>
    </section>

    <section>
        <div class="billing-heading"><h2>По месяцам</h2><span class="billing-muted">Новые: первая оплата в доступной истории</span></div>
        <div class="billing-scroll"><table class="billing-months">
            <thead><tr><th>Месяц</th><th>Оплат</th><th>Аккаунтов</th><th>Новых</th><th>iOS</th><th>Android</th><th>Суммы</th></tr></thead>
            <tbody>@forelse ($report['months'] as $row)
                <tr><td class="billing-num">{{ $row['month'] }}</td><td class="billing-num">{{ $row['count'] }}<span class="billing-bar" aria-hidden="true"><span style="width: {{ round(100 * $row['count'] / $maxMonth) }}%"></span></span></td><td>{{ $row['customers'] }}</td><td>{{ $row['new'] }}</td><td>{{ $row['ios'] }}</td><td>{{ $row['android'] }}</td><td>{{ $moneyList($row['totals']) }}</td></tr>
            @empty<tr><td colspan="7" class="billing-empty">Нет данных за выбранный период</td></tr>@endforelse</tbody>
        </table></div>
    </section>

    <section>
        <div class="billing-heading"><h2>Подписчики <span class="billing-muted">{{ $customers->total() }}</span></h2><span class="billing-muted">Статус сейчас · оплаты по выбранному периоду · итоги за всё время</span></div>
        <div class="billing-scroll"><table class="billing-customers">
            <thead><tr><th>Аккаунт</th><th>Подписка</th><th>Статус</th><th>Оплачено до</th><th>Последняя оплата</th><th>Устройства / активность</th><th>Оплат за период</th><th>За всё время</th></tr></thead>
            <tbody>@forelse ($customers as $row)
                <tr>
                    <td class="billing-customer"><a class="billing-link" href="{{ CompanyResource::getUrl('view', ['record' => $row['id']]) }}">#{{ $row['id'] }} · {{ $row['name'] }}</a><div class="billing-muted">{{ $row['email'] }}</div></td>
                    <td>{{ ucfirst($row['plan'] ?? '') }}<div class="billing-muted">{{ Billing::PROVIDERS[$row['provider']] ?? $row['provider'] }}</div></td>
                    <td class="{{ $row['paying'] ? 'billing-good' : 'billing-muted' }}">{{ $row['paying'] ? 'Оплачена' : ($row['active'] ? 'Активна, сумма не подтверждена' : match($row['status']) {'expired' => 'Истекла', 'grace_period' => 'Льготный период', 'on_hold' => 'Удержание', 'cancelled' => 'Отменена', default => 'Неактивна'}) }}</td>
                    <td class="billing-num">{{ $date($row['ends_at']) }}<div class="billing-muted">Проверка: {{ $date($row['verified_at']) }}</div></td>
                    <td class="billing-num">{{ $date($row['last_paid_at']) }}</td>
                    <td>{{ $row['devices'] ?: 'Нет push-регистрации' }}<div class="billing-muted">{{ $date($row['last_seen_at']) }}</div></td>
                    <td>{{ $row['payments'] }}</td><td>{{ $moneyList($row['totals']) }}</td>
                </tr>
            @empty<tr><td colspan="8" class="billing-empty">Подписчики не найдены</td></tr>@endforelse</tbody>
        </table></div>
        <div class="billing-pagination" wire:key="billing-customers-pagination"><x-filament::pagination :paginator="$customers" /></div>
    </section>

    <section>
        <div class="billing-heading"><h2>История заказов <span class="billing-muted">{{ $payments->total() }}</span></h2><span class="billing-muted">Обновление из базы каждый час, Google Orders не реже раза в сутки</span></div>
        <div class="billing-scroll"><table class="billing-payments">
            <thead><tr><th>Дата оплаты</th><th>Аккаунт</th><th>Магазин</th><th>Тариф</th><th>Сумма</th><th>Статус</th></tr></thead>
            <tbody>@forelse ($payments as $payment)
                <tr><td class="billing-num">{{ $date($payment->paid_at) }}</td><td><a class="billing-link" href="{{ CompanyResource::getUrl('view', ['record' => $payment->user_id]) }}">#{{ $payment->user_id }}</a></td><td>{{ Billing::PROVIDERS[$payment->provider] }}</td><td>{{ ucfirst($payment->plan_code ?? '') }}</td><td class="billing-num">{{ Billing::money($payment->amount_micros, $payment->currency) }}</td><td>{{ Billing::STATES[$payment->state] ?? 'Не подтверждено' }}@if($payment->lookup_error)<div class="billing-warning">Ошибка обновления</div>@endif</td></tr>
            @empty<tr><td colspan="6" class="billing-empty">Заказы не найдены</td></tr>@endforelse</tbody>
        </table></div>
        <div class="billing-pagination" wire:key="billing-payments-pagination"><x-filament::pagination :paginator="$payments" /></div>
    </section>
</div>
