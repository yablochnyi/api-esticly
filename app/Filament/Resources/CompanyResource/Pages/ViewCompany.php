<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Resources\CompanyResource;
use App\Jobs\SendOrganizationPush;
use App\Models\MarketingDelivery;
use App\Models\User;
use App\Support\MediaUrl;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ImageEntry;
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
                            ->state(fn (User $record): ?string => MediaUrl::publicFile($record->logo_path))
                            ->defaultImageUrl(asset('icon.png'))
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
}
