<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Resources\CompanyResource;
use App\Models\MarketingAutomation;
use App\Models\User;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;

class ManageCompanySettings extends ViewRecord
{
    protected static string $resource = CompanyResource::class;
    protected static ?string $navigationLabel = 'Settings';
    protected static ?string $title = 'Company settings';

    public static function canAccess(array $parameters = []): bool
    {
        return Gate::allows('access-filament-admin');
    }

    public function infolist(Schema $schema): Schema
    {
        /** @var User $record */
        $record = $this->getRecord();

        return $schema
            ->columns(12)
            ->components([
                Section::make('Settings')
                    ->description('Read-only overview of reminders, online booking, and marketing setup.')
                    ->columnSpanFull()
                    ->schema([
                        ViewEntry::make('company_settings')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->view('filament.resources.company-resource.company-settings')
                            ->viewData([
                                'reminders' => $this->remindersData($record),
                                'onlineBooking' => $this->onlineBookingData($record),
                                'marketing' => $this->marketingData($record),
                            ]),
                    ]),
            ]);
    }

    private function remindersData(User $record): array
    {
        $offsets = $this->normalizeOffsets($record->reminder_offsets_min);

        return [
            'configured' => !empty($offsets),
            'offsets' => array_map(fn (int $minutes): string => $this->formatMinutes($minutes), $offsets),
        ];
    }

    private function onlineBookingData(User $record): array
    {
        $specialties = collect(is_array($record->booking_specialties) ? $record->booking_specialties : [])
            ->map(fn ($item) => is_array($item) ? trim((string) ($item['name'] ?? '')) : '')
            ->filter()
            ->values()
            ->all();

        $socials = [
            'Instagram' => $record->booking_instagram,
            'TikTok' => $record->booking_tiktok,
            'Telegram' => $record->booking_telegram,
            'WhatsApp' => $record->booking_whatsapp,
            'Viber' => $record->booking_viber,
        ];

        $filledSocials = collect($socials)
            ->filter(fn ($value) => filled($value))
            ->map(fn ($value, string $label) => ['label' => $label, 'value' => (string) $value])
            ->values()
            ->all();

        return [
            'completion' => $this->onlineBookingCompletion($record),
            'enabled' => (bool) ($record->online_booking_enabled ?? false),
            'auto_confirm' => (bool) ($record->online_booking_auto_confirm ?? false),
            'whitelist_only' => (bool) ($record->online_booking_whitelist_only ?? false),
            'period_days' => (int) ($record->online_booking_period_days ?? 90),
            'slug' => (string) ($record->booking_slug ?? ''),
            'url' => $this->bookingUrl($record),
            'address' => (string) ($record->address ?? ''),
            'phone' => (string) ($record->booking_phone ?? ''),
            'bio' => (string) ($record->booking_bio ?? ''),
            'specialties' => $specialties,
            'socials' => $filledSocials,
        ];
    }

    private function marketingData(User $record): array
    {
        $labels = [
            'visit_reminder_sms' => 'Visit reminder SMS',
            'thanks_after_visit' => 'Thanks after visit',
        ];

        $rows = MarketingAutomation::query()
            ->where('user_id', $record->id)
            ->orderBy('key')
            ->get(['key', 'enabled', 'delay_min', 'template', 'updated_at'])
            ->map(function (MarketingAutomation $automation) use ($labels): array {
                $template = trim((string) ($automation->template ?? ''));

                return [
                    'key' => (string) $automation->key,
                    'label' => $labels[(string) $automation->key] ?? (string) $automation->key,
                    'enabled' => (bool) $automation->enabled,
                    'delay' => $this->formatMinutes((int) ($automation->delay_min ?? 0)),
                    'template' => $template !== '' ? $template : null,
                    'updated_at' => $automation->updated_at?->format('Y-m-d H:i') ?: null,
                ];
            })
            ->values()
            ->all();

        return [
            'configured' => !empty($rows),
            'enabled_count' => collect($rows)->where('enabled', true)->count(),
            'items' => $rows,
        ];
    }

    private function normalizeOffsets($raw): array
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $item) {
            if (!is_numeric($item)) {
                continue;
            }

            $value = (int) $item;
            if ($value <= 0 || $value > 60 * 24 * 7) {
                continue;
            }

            $out[] = $value;
        }

        $out = array_values(array_unique($out));
        sort($out);

        return $out;
    }

    private function onlineBookingCompletion(User $record): int
    {
        $completed = 0;

        if (filled($record->booking_slug)) {
            $completed++;
        }
        if (filled($record->address)) {
            $completed++;
        }
        if (filled($record->booking_phone)) {
            $completed++;
        }
        if (filled($record->booking_bio)) {
            $completed++;
        }

        $specialties = is_array($record->booking_specialties) ? $record->booking_specialties : [];
        if (collect($specialties)->contains(fn ($item) => is_array($item) && filled($item['name'] ?? null))) {
            $completed++;
        }

        if (collect([
            $record->booking_instagram,
            $record->booking_tiktok,
            $record->booking_telegram,
            $record->booking_whatsapp,
            $record->booking_viber,
        ])->contains(fn ($value) => filled($value))) {
            $completed++;
        }

        return (int) round(($completed / 6) * 100);
    }

    private function bookingUrl(User $record): ?string
    {
        $slug = trim((string) ($record->booking_slug ?? ''));
        if ($slug === '') {
            return null;
        }

        $baseDomain = trim((string) env('BOOKING_BASE_DOMAIN', ''));
        if ($baseDomain !== '') {
            $scheme = env('BOOKING_SCHEME', 'https');

            return rtrim($scheme, ':/') . '://' . $slug . '.' . ltrim($baseDomain, '.');
        }

        $base = rtrim((string) config('app.url', ''), '/');
        if ($base === '') {
            $base = 'http://127.0.0.1:8000';
        }

        return $base . '/@' . $slug;
    }

    private function formatMinutes(int $minutes): string
    {
        if ($minutes <= 0) {
            return 'Immediately';
        }

        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $mins = $minutes % 60;

        $parts = [];
        if ($days > 0) {
            $parts[] = $days . ' d';
        }
        if ($hours > 0) {
            $parts[] = $hours . ' h';
        }
        if ($mins > 0) {
            $parts[] = $mins . ' min';
        }

        return implode(' ', $parts);
    }
}
