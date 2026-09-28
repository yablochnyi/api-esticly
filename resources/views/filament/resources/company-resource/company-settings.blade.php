<div class="esticly-settings">
    <section class="esticly-settings-section">
        <div class="esticly-detail-heading"><h3>Напоминания мастеру и салону</h3><span @class(['esticly-status', 'is-enabled' => $reminders['configured']])>{{ $reminders['configured'] ? 'Настроено' : 'Не настроено' }}</span></div>
        @if($reminders['configured'])
            <div class="esticly-tags">@foreach($reminders['offsets'] as $offset)<span>{{ $offset }} до визита</span>@endforeach</div>
        @else
            <p class="esticly-detail-label">Время напоминаний пока не выбрано.</p>
        @endif
    </section>
    <section class="esticly-settings-section">
        <div class="esticly-detail-heading"><h3>Онлайн-запись</h3><span @class(['esticly-status', 'is-enabled' => $onlineBooking['enabled']])>{{ $onlineBooking['enabled'] ? 'Включено' : 'Выключено' }}</span></div>
        <div class="esticly-tags"><span>{{ $onlineBooking['auto_confirm'] ? 'Автоподтверждение' : 'Ручное подтверждение' }}</span>@if($onlineBooking['whitelist_only'])<span>Только разрешённые клиенты</span>@endif</div>
        <div class="esticly-completion"><progress max="100" value="{{ max(0, min(100, (int) $onlineBooking['completion'])) }}" aria-label="Заполнение профиля онлайн-записи"></progress><span>{{ $onlineBooking['completion'] }}% заполнено</span></div>
        <dl class="esticly-details">
            <div><dt>Ссылка на запись</dt><dd>@if($onlineBooking['url'])<a href="{{ $onlineBooking['url'] }}" target="_blank" rel="noopener noreferrer">{{ $onlineBooking['url'] }}</a>@else Не указано @endif</dd></div>
            <div><dt>Период записи</dt><dd>{{ $onlineBooking['period_days'] }} дн.</dd></div>
            <div><dt>Адрес</dt><dd>{{ $onlineBooking['address'] ?: 'Не указано' }}</dd></div>
            <div><dt>Телефон для записи</dt><dd>{{ $onlineBooking['phone'] ?: 'Не указано' }}</dd></div>
            <div><dt>Специализации</dt><dd>{{ implode(', ', $onlineBooking['specialties']) ?: 'Не указано' }}</dd></div>
            <div><dt>Соцсети</dt><dd>@forelse($onlineBooking['socials'] as $social)<div>{{ $social['label'] }}: {{ $social['value'] }}</div>@empty Не указано @endforelse</dd></div>
            <div class="esticly-detail-full"><dt>О салоне</dt><dd class="esticly-preserve-lines">{{ $onlineBooking['bio'] ?: 'Не указано' }}</dd></div>
        </dl>
    </section>
    <section class="esticly-settings-section">
        <div class="esticly-detail-heading"><h3>Автоматические рассылки</h3><span class="esticly-detail-label">Включено: {{ $marketing['enabled_count'] }}</span></div>
        @if(!empty($marketing['items']))
            <div class="esticly-detail-scroll"><table class="esticly-detail-table">
                <thead><tr><th>Автоматизация</th><th>Статус</th><th>Задержка</th><th>Шаблон</th><th>Обновлено</th></tr></thead>
                <tbody>@foreach($marketing['items'] as $item)<tr>
                    <td>{{ $item['label'] }}</td><td><span @class(['esticly-status', 'is-enabled' => $item['enabled']])>{{ $item['enabled'] ? 'Включено' : 'Выключено' }}</span></td>
                    <td>{{ $item['delay'] }}</td><td class="esticly-preserve-lines">{{ $item['template'] ?: 'Стандартный шаблон' }}</td><td>{{ $item['updated_at'] ?? '—' }}</td>
                </tr>@endforeach</tbody>
            </table></div>
        @else
            <p class="esticly-detail-label">Автоматические рассылки пока не настроены.</p>
        @endif
    </section>
</div>
