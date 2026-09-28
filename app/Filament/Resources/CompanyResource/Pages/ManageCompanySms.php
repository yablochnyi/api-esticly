<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Resources\CompanyResource;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class ManageCompanySms extends ManageRelatedRecords
{
    protected static string $resource = CompanyResource::class;

    protected static string $relationship = 'marketingDeliveries';

    protected static ?string $navigationLabel = 'SMS';

    protected static ?string $title = 'SMS салона';

    public static function canAccess(array $parameters = []): bool
    {
        return Gate::allows('access-filament-admin');
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('sent_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('automation_key')->label('Автоматизация')->formatStateUsing(fn ($state) => \App\Support\AdminLabels::state($state))->label('Автоматизация')->badge()->sortable(),
                Tables\Columns\TextColumn::make('to_phone')->label('Телефон')->copyable()->placeholder('—'),
                Tables\Columns\TextColumn::make('status')->label('Статус')->formatStateUsing(fn ($state) => \App\Support\AdminLabels::state($state))->label('Статус')->badge()->sortable(),
                Tables\Columns\TextColumn::make('sent_at')->label('Дата отправки')->dateTime('d.m.Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('error')
                    ->label('Ошибка')
                    ->limit(80)
                    ->tooltip(fn ($record): ?string => $record->error ?: null)
                    ->placeholder('—')
                    ->wrap(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        'pending' => 'Ожидает',
                        'sent' => 'Отправлено',
                        'failed' => 'Ошибка',
                        'skipped' => 'Пропущено',
                    ]),
                Tables\Filters\SelectFilter::make('automation_key')
                    ->label('Автоматизация')
                    ->options([
                        'visit_reminder_sms' => 'Напоминание о визите',
                        'thanks_after_visit' => 'Благодарность после визита',
                    ]),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
