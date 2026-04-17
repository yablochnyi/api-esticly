<?php

namespace App\Filament\Resources\CompanyResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class VisitsRelationManager extends RelationManager
{
    protected static string $relationship = 'visits';

    public static function canViewForRecord($ownerRecord, string $pageClass): bool
    {
        return Gate::allows('access-filament-admin');
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('service.name')
                    ->label('Service')
                    ->searchable()
                    ->placeholder('—')
                    ->wrap(),
                Tables\Columns\TextColumn::make('client_name')
                    ->label('Client')
                    ->searchable()
                    ->placeholder('—')
                    ->wrap(),
                Tables\Columns\TextColumn::make('staff.name')
                    ->label('Staff')
                    ->placeholder('Salon')
                    ->wrap(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Price')
                    ->formatStateUsing(function ($state): string {
                        $value = $state !== null ? rtrim(rtrim((string) $state, '0'), '.') : '0';
                        $currency = strtoupper((string) ($this->getOwnerRecord()->currency_code ?: ''));

                        return trim("{$value} {$currency}");
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Starts at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                        'confirmed' => 'Confirmed',
                        'no_show' => 'No show',
                    ]),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
