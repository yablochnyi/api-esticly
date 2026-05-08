<div class="grid gap-4 xl:grid-cols-3">
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="flex items-start justify-between gap-3">
            <div>
                <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Master / salon reminders</div>
                <div class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">
                    {{ $reminders['configured'] ? 'Configured' : 'Not configured' }}
                </div>
            </div>

            <span @class([
                'rounded-full px-2.5 py-1 text-xs font-semibold',
                'bg-success-100 text-success-700 dark:bg-success-500/10 dark:text-success-400' => $reminders['configured'],
                'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' => ! $reminders['configured'],
            ])>
                {{ $reminders['configured'] ? 'ON' : 'OFF' }}
            </span>
        </div>

        <div class="mt-4">
            @if($reminders['configured'])
                <div class="flex flex-wrap gap-2">
                    @foreach($reminders['offsets'] as $offset)
                        <span class="rounded-full bg-primary-50 px-3 py-1 text-sm font-medium text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">
                            {{ $offset }} before visit
                        </span>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    The company has not selected any push reminder offsets.
                </p>
            @endif
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 xl:col-span-2">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Online booking</div>
                <div class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">
                    {{ $onlineBooking['completion'] }}% completed
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <span @class([
                    'rounded-full px-2.5 py-1 text-xs font-semibold',
                    'bg-success-100 text-success-700 dark:bg-success-500/10 dark:text-success-400' => $onlineBooking['enabled'],
                    'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' => ! $onlineBooking['enabled'],
                ])>
                    {{ $onlineBooking['enabled'] ? 'Enabled' : 'Disabled' }}
                </span>
                <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                    {{ $onlineBooking['auto_confirm'] ? 'Auto-confirm' : 'Manual confirm' }}
                </span>
                @if($onlineBooking['whitelist_only'])
                    <span class="rounded-full bg-warning-100 px-2.5 py-1 text-xs font-semibold text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">
                        Whitelist only
                    </span>
                @endif
            </div>
        </div>

        <div class="mt-4 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
            <div
                class="h-full rounded-full bg-primary-600"
                style="width: {{ max(0, min(100, (int) $onlineBooking['completion'])) }}%"
            ></div>
        </div>

        <dl class="mt-4 grid gap-3 md:grid-cols-2">
            <div>
                <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Booking URL</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                    @if($onlineBooking['url'])
                        <a href="{{ $onlineBooking['url'] }}" target="_blank" class="text-primary-600 underline dark:text-primary-400">
                            {{ $onlineBooking['url'] }}
                        </a>
                    @else
                        <span class="text-gray-500 dark:text-gray-400">Not set</span>
                    @endif
                </dd>
            </div>

            <div>
                <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Booking period</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $onlineBooking['period_days'] }} days</dd>
            </div>

            <div>
                <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Address</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $onlineBooking['address'] !== '' ? $onlineBooking['address'] : 'Not set' }}</dd>
            </div>

            <div>
                <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Booking phone</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $onlineBooking['phone'] !== '' ? $onlineBooking['phone'] : 'Not set' }}</dd>
            </div>
        </dl>

        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div>
                <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Specialties</div>
                @if(!empty($onlineBooking['specialties']))
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach($onlineBooking['specialties'] as $specialty)
                            <span class="rounded-full bg-primary-50 px-3 py-1 text-sm font-medium text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">
                                {{ $specialty }}
                            </span>
                        @endforeach
                    </div>
                @else
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Not set</p>
                @endif
            </div>

            <div>
                <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Socials</div>
                @if(!empty($onlineBooking['socials']))
                    <div class="mt-2 space-y-1 text-sm text-gray-900 dark:text-white">
                        @foreach($onlineBooking['socials'] as $social)
                            <div><span class="font-medium">{{ $social['label'] }}:</span> {{ $social['value'] }}</div>
                        @endforeach
                    </div>
                @else
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Not set</p>
                @endif
            </div>
        </div>

        <div class="mt-4">
            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">About / bio</div>
            <p class="mt-2 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">
                {{ $onlineBooking['bio'] !== '' ? $onlineBooking['bio'] : 'Not set' }}
            </p>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900 xl:col-span-3">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Marketing automations</div>
                <div class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">
                    {{ $marketing['enabled_count'] }} enabled
                </div>
            </div>

            <span @class([
                'rounded-full px-2.5 py-1 text-xs font-semibold',
                'bg-success-100 text-success-700 dark:bg-success-500/10 dark:text-success-400' => $marketing['enabled_count'] > 0,
                'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' => $marketing['enabled_count'] === 0,
            ])>
                {{ $marketing['configured'] ? 'Configured' : 'No rows' }}
            </span>
        </div>

        @if(!empty($marketing['items']))
            <div class="mt-4 overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-950">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Automation</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Status</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Delay</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Template</th>
                            <th class="px-4 py-3 text-left font-semibold text-gray-700 dark:text-gray-200">Updated</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @foreach($marketing['items'] as $item)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $item['label'] }}</td>
                                <td class="px-4 py-3">
                                    <span @class([
                                        'rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-success-100 text-success-700 dark:bg-success-500/10 dark:text-success-400' => $item['enabled'],
                                        'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' => ! $item['enabled'],
                                    ])>
                                        {{ $item['enabled'] ? 'Enabled' : 'Disabled' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $item['delay'] }}</td>
                                <td class="max-w-xl px-4 py-3 text-gray-700 dark:text-gray-300">
                                    @if($item['template'])
                                        <span class="line-clamp-3 whitespace-pre-line">{{ $item['template'] }}</span>
                                    @else
                                        <span class="text-gray-500 dark:text-gray-400">Default template</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $item['updated_at'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                The company has not configured marketing automations yet.
            </p>
        @endif
    </div>
</div>
