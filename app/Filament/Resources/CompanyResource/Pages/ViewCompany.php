<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Resources\CompanyResource;
use App\Jobs\SendOrganizationPush;
use App\Models\MarketingDelivery;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class ViewCompany extends ViewRecord
{
    protected static string $resource = CompanyResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return Gate::allows('access-filament-admin');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendPush')
                ->label('Send push')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->modalHeading('Send push to this company')
                ->modalDescription('This notification will be queued and sent only to the selected company: owner account and its staff accounts.')
                ->form([
                    TextInput::make('title')
                        ->label('Title')
                        ->required()
                        ->maxLength(120),
                    Textarea::make('body')
                        ->label('Message')
                        ->required()
                        ->rows(5)
                        ->maxLength(240),
                ])
                ->action(function (array $data): void {
                    /** @var User $record */
                    $record = $this->getRecord();

                    SendOrganizationPush::dispatch(
                        orgId: (int) $record->id,
                        title: (string) $data['title'],
                        body: (string) $data['body'],
                        createdByUserId: auth()->id(),
                    );

                    Notification::make()
                        ->title('Push queued')
                        ->body('The notification was queued for this company.')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        /** @var User $record */
        $record = $this->getRecord();

        return $schema
            ->columns(12)
            ->components([
                Section::make('Company overview')
                    ->columns(12)
                    ->columnSpanFull()
                    ->schema([
                        ImageEntry::make('logo_url')
                            ->label('Avatar')
                            ->state(fn (User $record): ?string => $record->logo_url)
                            ->defaultImageUrl('https://placehold.co/160x160?text=No+Logo')
                            ->circular()
                            ->imageSize(96)
                            ->columnSpan(2),

                        TextEntry::make('company_name')
                            ->label('Company')
                            ->state(fn (User $record): string => (string) ($record->company_name ?: '—'))
                            ->size('lg')
                            ->weight('bold')
                            ->columnSpan(4),

                        TextEntry::make('phone')
                            ->label('Phone')
                            ->state(fn (User $record): string => (string) ($record->phone ?: '—'))
                            ->columnSpan(3),

                        TextEntry::make('email')
                            ->label('Email')
                            ->state(fn (User $record): string => (string) ($record->email ?: '—'))
                            ->columnSpan(3),

                        TextEntry::make('language_code')
                            ->label('Language')
                            ->badge()
                            ->state(fn (User $record): string => strtoupper((string) ($record->language_code ?: '—')))
                            ->columnSpan(2),

                        TextEntry::make('currency_code')
                            ->label('Currency')
                            ->badge()
                            ->state(fn (User $record): string => strtoupper((string) ($record->currency_code ?: '—')))
                            ->columnSpan(2),

                        TextEntry::make('timezone')
                            ->label('Timezone')
                            ->state(fn (User $record): string => (string) ($record->timezone ?: '—'))
                            ->columnSpan(4),

                        TextEntry::make('registered_at')
                            ->label('Registered')
                            ->state(fn (User $record): string => $record->registered_at?->format('Y-m-d H:i') ?: '—')
                            ->columnSpan(2),

                        TextEntry::make('address')
                            ->label('Address')
                            ->state(fn (User $record): string => (string) ($record->address ?: '—'))
                            ->columnSpan(8),
                    ]),

                Section::make('Business stats')
                    ->description('Current totals for this company.')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('services_total')
                            ->label('Services')
                            ->state(fn (User $record): string => (string) $record->services()->count())
                            ->badge(),
                        TextEntry::make('visits_total')
                            ->label('Visits')
                            ->state(fn (User $record): string => (string) $record->visits()->count())
                            ->badge(),
                        TextEntry::make('clients_total')
                            ->label('Clients')
                            ->state(fn (User $record): string => (string) $record->clients()->count())
                            ->badge(),
                        TextEntry::make('portfolio_total')
                            ->label('Portfolio')
                            ->state(fn (User $record): string => (string) $record->portfolioPhotos()->count())
                            ->badge(),
                    ]),

                Section::make('SMS stats')
                    ->description('Client SMS sent from this company. Filter by month.')
                    ->columnSpanFull()
                    ->schema([
                        ViewEntry::make('sms_stats')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->view('filament.resources.company-resource.sms-stats')
                            ->viewData([
                                'stats' => $this->getSmsStats($record),
                                'selectedMonth' => $this->getSelectedSmsMonth(),
                                'selectedMonthLabel' => $this->getSelectedSmsMonthLabel(),
                                'monthOptions' => $this->getSmsMonthOptions($record),
                            ]),
                    ]),

                Section::make('Created services')
                    ->description('Latest services created by the company.')
                    ->columnSpan(6)
                    ->schema([
                        RepeatableEntry::make('services_preview')
                            ->hiddenLabel()
                            ->contained(false)
                            ->state(fn (User $record) => $record->services()
                                ->latest('id')
                                ->limit(5)
                                ->get()
                                ->map(fn ($service) => [
                                    'name' => (string) $service->name,
                                    'price' => $this->formatServicePrice($service),
                                    'duration' => $this->formatServiceDuration($service),
                                ])
                                ->all())
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Service')
                                    ->weight('bold'),
                                TextEntry::make('price')
                                    ->label('Price'),
                                TextEntry::make('duration')
                                    ->label('Duration'),
                            ])
                            ->placeholder('No services yet.'),
                    ]),

                Section::make('Latest visits')
                    ->description('Latest visits for this company.')
                    ->columnSpan(6)
                    ->schema([
                        RepeatableEntry::make('visits_preview')
                            ->hiddenLabel()
                            ->contained(false)
                            ->state(fn (User $record) => $record->visits()
                                ->with(['service:id,name'])
                                ->latest('starts_at')
                                ->limit(5)
                                ->get()
                                ->map(fn ($visit) => [
                                    'service' => (string) ($visit->service?->name ?: '—'),
                                    'client' => (string) ($visit->client_name ?: '—'),
                                    'starts_at' => $visit->starts_at?->format('Y-m-d H:i') ?: '—',
                                    'status' => (string) ($visit->status ?: '—'),
                                ])
                                ->all())
                            ->schema([
                                TextEntry::make('service')
                                    ->label('Service')
                                    ->weight('bold'),
                                TextEntry::make('client')
                                    ->label('Client'),
                                TextEntry::make('starts_at')
                                    ->label('Starts at'),
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge(),
                            ])
                            ->placeholder('No visits yet.'),
                    ]),

                Section::make('Latest clients')
                    ->description('Latest clients added to this company.')
                    ->columnSpan(6)
                    ->schema([
                        RepeatableEntry::make('clients_preview')
                            ->hiddenLabel()
                            ->contained(false)
                            ->state(fn (User $record) => $record->clients()
                                ->latest('id')
                                ->limit(5)
                                ->get()
                                ->map(fn ($client) => [
                                    'name' => (string) ($client->name ?: '—'),
                                    'phone' => (string) ($client->phone ?: '—'),
                                    'created_at' => $client->created_at?->format('Y-m-d H:i') ?: '—',
                                ])
                                ->all())
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Client')
                                    ->weight('bold'),
                                TextEntry::make('phone')
                                    ->label('Phone'),
                                TextEntry::make('created_at')
                                    ->label('Created'),
                            ])
                            ->placeholder('No clients yet.'),
                    ]),

                Section::make('Portfolio')
                    ->description('Latest portfolio photos.')
                    ->columnSpan(6)
                    ->schema([
                        RepeatableEntry::make('portfolio_preview')
                            ->hiddenLabel()
                            ->contained(false)
                            ->grid(2)
                            ->state(fn (User $record) => $record->portfolioPhotos()
                                ->latest('id')
                                ->limit(6)
                                ->get()
                                ->map(fn ($photo) => [
                                    'url' => $photo->url,
                                    'caption' => (string) ($photo->caption ?: 'No caption'),
                                    'created_at' => $photo->created_at?->format('Y-m-d') ?: '—',
                                ])
                                ->all())
                            ->schema([
                                ImageEntry::make('url')
                                    ->hiddenLabel()
                                    ->defaultImageUrl('https://placehold.co/600x400?text=No+Image')
                                    ->imageHeight(120)
                                    ->imageWidth('100%'),
                                TextEntry::make('caption')
                                    ->label('Caption')
                                    ->limit(80),
                                TextEntry::make('created_at')
                                    ->label('Created'),
                            ])
                            ->placeholder('No portfolio photos yet.'),
                    ]),
            ]);
    }

    protected function getSelectedSmsMonth(): string
    {
        $month = trim((string) request()->query('sms_month'));

        if (preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            return $month;
        }

        return now()->format('Y-m');
    }

    protected function getSelectedSmsMonthLabel(): string
    {
        return Carbon::createFromFormat('Y-m', $this->getSelectedSmsMonth())
            ->startOfMonth()
            ->translatedFormat('F Y');
    }

    protected function getSmsMonthOptions(User $record): array
    {
        $baseUrl = static::getResource()::getUrl('view', ['record' => $record]);
        $current = $this->getSelectedSmsMonth();

        return collect(range(0, 11))
            ->map(function (int $offset) use ($baseUrl, $current) {
                $month = now()->startOfMonth()->subMonths($offset);
                $value = $month->format('Y-m');

                return [
                    'value' => $value,
                    'label' => $month->translatedFormat('M Y'),
                    'url' => "{$baseUrl}?sms_month={$value}",
                    'active' => $value === $current,
                ];
            })
            ->all();
    }

    protected function getSmsStats(User $record): array
    {
        $month = Carbon::createFromFormat('Y-m', $this->getSelectedSmsMonth())->startOfMonth();
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $baseQuery = MarketingDelivery::query()
            ->where('user_id', $record->id)
            ->where('status', 'sent')
            ->whereNotNull('sent_at')
            ->whereBetween('sent_at', [$start, $end]);

        $allTimeQuery = MarketingDelivery::query()
            ->where('user_id', $record->id)
            ->where('status', 'sent')
            ->whereNotNull('sent_at');

        return [
            'month_total' => (clone $baseQuery)->count(),
            'month_reminders' => (clone $baseQuery)->where('automation_key', 'visit_reminder_sms')->count(),
            'month_thanks' => (clone $baseQuery)->where('automation_key', 'thanks_after_visit')->count(),
            'month_other' => (clone $baseQuery)->whereNotIn('automation_key', ['visit_reminder_sms', 'thanks_after_visit'])->count(),
            'all_time_total' => (clone $allTimeQuery)->count(),
        ];
    }

    protected function formatServicePrice(object $service): string
    {
        $currency = strtoupper((string) ($this->getRecord()->currency_code ?: ''));

        if (($service->price_type ?? 'fixed') === 'range') {
            $from = $service->price_from !== null ? rtrim(rtrim((string) $service->price_from, '0'), '.') : '0';
            $to = $service->price_to !== null ? rtrim(rtrim((string) $service->price_to, '0'), '.') : '0';

            return trim("{$from} - {$to} {$currency}");
        }

        $fixed = $service->price_fixed !== null ? rtrim(rtrim((string) $service->price_fixed, '0'), '.') : '0';

        return trim("{$fixed} {$currency}");
    }

    protected function formatServiceDuration(object $service): string
    {
        $from = (int) ($service->duration_from_min ?? 0);
        $to = (int) ($service->duration_to_min ?? 0);

        if ($from > 0 && $to > 0 && $from !== $to) {
            return "{$from}-{$to} min";
        }

        $value = max($from, $to);

        return $value > 0 ? "{$value} min" : '—';
    }
}
