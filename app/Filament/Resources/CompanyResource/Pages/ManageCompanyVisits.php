<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Resources\CompanyResource;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class ManageCompanyVisits extends ManageRelatedRecords
{
    protected static string $resource = CompanyResource::class;

    protected static string $relationship = 'visits';

    protected static ?string $navigationLabel = 'Записи';

    protected static ?string $title = 'Записи салона';

    public static function canAccess(array $parameters = []): bool
    {
        return Gate::allows('access-filament-admin');
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('service.name')->label('Услуга')->searchable()->placeholder('—')->wrap(),
                Tables\Columns\TextColumn::make('client_name')->label('Клиент')->searchable()->placeholder('—')->wrap(),
                Tables\Columns\TextColumn::make('staff.name')->label('Команда')->placeholder('Салон')->wrap(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Цена')
                    ->formatStateUsing(function ($state): string {
                        $value = $state !== null ? rtrim(rtrim((string) $state, '0'), '.') : '0';
                        $currency = strtoupper((string) ($this->getOwnerRecord()->currency_code ?: ''));

                        return trim("{$value} {$currency}");
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')->label('Статус')->formatStateUsing(fn ($state) => \App\Support\AdminLabels::state($state))->label('Статус')->badge()->sortable(),
                Tables\Columns\TextColumn::make('starts_at')->label('Начало')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        'pending' => 'Ожидает',
                        'completed' => 'Завершена',
                        'cancelled' => 'Отменена',
                        'confirmed' => 'Подтверждена',
                        'no_show' => 'Неявка',
                    ]),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
