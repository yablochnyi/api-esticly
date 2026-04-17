<?php

namespace App\Filament\Resources\CompanyResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class SmsDeliveriesRelationManager extends RelationManager
{
    protected static string $relationship = 'marketingDeliveries';

    public static function canViewForRecord($ownerRecord, string $pageClass): bool
    {
        return Gate::allows('access-filament-admin');
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('sent_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('automation_key')
                    ->label('Automation')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('to_phone')
                    ->label('Phone')
                    ->copyable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Sent at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('error')
                    ->label('Error')
                    ->limit(80)
                    ->tooltip(fn ($record): ?string => $record->error ?: null)
                    ->placeholder('—')
                    ->wrap(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'sent' => 'Sent',
                        'failed' => 'Failed',
                        'skipped' => 'Skipped',
                    ]),
                Tables\Filters\SelectFilter::make('automation_key')
                    ->label('Automation')
                    ->options([
                        'visit_reminder_sms' => 'Visit reminder',
                        'thanks_after_visit' => 'Thanks after visit',
                    ]),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
