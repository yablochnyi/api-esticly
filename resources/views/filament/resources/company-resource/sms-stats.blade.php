<div class="esticly-sms-summary">
    <nav class="esticly-months" aria-label="Месяц отправки SMS">
        @foreach($monthOptions as $option)
            <a href="{{ $option['url'] }}" @class(['esticly-month', 'is-active' => $option['active']]) @if($option['active']) aria-current="date" @endif>{{ $option['label'] }}</a>
        @endforeach
    </nav>
    <div class="esticly-stats">
        @foreach([
            'Выбранный месяц' => $selectedMonthLabel,
            'Всего SMS' => $stats['month_total'],
            'Напоминания о визите' => $stats['month_reminders'],
            'Благодарности после визита' => $stats['month_thanks'],
            'За всё время' => $stats['all_time_total'],
        ] as $label => $value)
            <div><div class="esticly-detail-label">{{ $label }}</div><div class="esticly-stat-value">{{ $value }}</div></div>
        @endforeach
    </div>
    @if(($stats['month_other'] ?? 0) > 0)
        <p class="esticly-detail-label">Другие SMS за выбранный месяц: {{ $stats['month_other'] }}</p>
    @endif
</div>
