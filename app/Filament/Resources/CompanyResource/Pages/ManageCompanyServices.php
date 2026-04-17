<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Resources\CompanyResource;
use App\Models\Service;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class ManageCompanyServices extends ManageRelatedRecords
{
    protected static string $resource = CompanyResource::class;
    protected static string $relationship = 'services';
    protected static ?string $navigationLabel = 'Services';
    protected static ?string $title = 'Company services';

    public static function canAccess(array $parameters = []): bool
    {
        return Gate::allows('access-filament-admin');
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Service')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('price_summary')
                    ->label('Price')
                    ->state(fn (Service $record): string => $this->formatServicePrice($record)),
                Tables\Columns\TextColumn::make('duration_summary')
                    ->label('Duration')
                    ->state(fn (Service $record): string => $this->formatServiceDuration($record)),
                Tables\Columns\TextColumn::make('staff_count')->label('Staff')->counts('staff')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('Created')->dateTime()->sortable(),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }

    protected function formatServicePrice(Service $service): string
    {
        $currency = strtoupper((string) ($this->getOwnerRecord()->currency_code ?: ''));

        if (($service->price_type ?? 'fixed') === 'range') {
            $from = $service->price_from !== null ? rtrim(rtrim((string) $service->price_from, '0'), '.') : '0';
            $to = $service->price_to !== null ? rtrim(rtrim((string) $service->price_to, '0'), '.') : '0';

            return trim("{$from} - {$to} {$currency}");
        }

        $fixed = $service->price_fixed !== null ? rtrim(rtrim((string) $service->price_fixed, '0'), '.') : '0';

        return trim("{$fixed} {$currency}");
    }

    protected function formatServiceDuration(Service $service): string
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
