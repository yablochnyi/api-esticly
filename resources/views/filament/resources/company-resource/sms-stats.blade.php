<div class="space-y-4">
    <div class="flex flex-wrap gap-2">
        @foreach($monthOptions as $option)
            <a
                href="{{ $option['url'] }}"
                @class([
                    'inline-flex items-center rounded-full px-3 py-1 text-sm font-medium transition',
                    'bg-primary-600 text-white' => $option['active'],
                    'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700' => ! $option['active'],
                ])
            >
                {{ $option['label'] }}
            </a>
        @endforeach
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Selected month</div>
            <div class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">{{ $selectedMonthLabel }}</div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Total SMS</div>
            <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $stats['month_total'] }}</div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Visit reminders</div>
            <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $stats['month_reminders'] }}</div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Thanks after visit</div>
            <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $stats['month_thanks'] }}</div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">All time total</div>
            <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $stats['all_time_total'] }}</div>
        </div>
    </div>

    @if(($stats['month_other'] ?? 0) > 0)
        <div class="text-sm text-gray-600 dark:text-gray-300">
            Other SMS automations in selected month: <span class="font-semibold">{{ $stats['month_other'] }}</span>
        </div>
    @endif
</div>
