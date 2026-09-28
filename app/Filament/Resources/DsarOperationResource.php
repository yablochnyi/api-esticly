<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DsarOperationResource\Pages;
use App\Models\DsarOperation;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class DsarOperationResource extends Resource
{
    protected static ?string $model = DsarOperation::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Персональные данные';

    protected static ?string $modelLabel = 'запрос данных';

    protected static ?string $pluralModelLabel = 'Персональные данные';

    protected static string|\UnitEnum|null $navigationGroup = 'Система';

    protected static ?int $navigationSort = 11;

    public static function canAccess(): bool
    {
        return Gate::allows('access-filament-admin');
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canView($record): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('org_id')->label('ID салона')->sortable(),
                Tables\Columns\TextColumn::make('org.company_name')
                    ->label('Салон')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('client_id')->label('ID клиента')->toggleable(),
                Tables\Columns\TextColumn::make('type')->formatStateUsing(fn ($state) => \App\Support\AdminLabels::state($state))
                    ->label('Тип')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')->formatStateUsing(fn ($state) => \App\Support\AdminLabels::state($state))
                    ->label('Статус')
                    ->badge()
                    ->color(fn (DsarOperation $record) => match ($record->status) {
                        'done' => 'success',
                        'failed' => 'danger',
                        'running' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('target_email')->label('Почта получателя')->wrap(),
                Tables\Columns\TextColumn::make('actor_user_id')->label('Пользователь')->toggleable(),
                Tables\Columns\TextColumn::make('actor_staff_id')->label('Сотрудник')->toggleable(),
                Tables\Columns\TextColumn::make('language_code')->label('Язык')->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->label('Создано')->dateTime('d.m.Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('finished_at')->label('Завершено')->dateTime('d.m.Y H:i')->toggleable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('org_id')
                    ->label('Салон')
                    ->form([
                        TextInput::make('org_id')->label('ID салона')->numeric(),
                    ])
                    ->query(function ($query, array $data) {
                        $orgId = isset($data['org_id']) ? (int) $data['org_id'] : 0;

                        return $orgId > 0 ? $query->where('org_id', $orgId) : $query;
                    }),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Статус')
                    ->options([
                        'pending' => 'Ожидает',
                        'running' => 'Выполняется',
                        'done' => 'Готово',
                        'failed' => 'Ошибка',
                    ]),
                Tables\Filters\SelectFilter::make('type')
                    ->label('Тип запроса')
                    ->options([
                        'export_client' => 'Экспорт клиента',
                        'anonymize_client' => 'Обезличивание клиента',
                        'export_all_clients' => 'Экспорт всех клиентов',
                        'export_org' => 'Экспорт салона',
                    ]),
            ])
            ->actions([
                Action::make('downloadClientJson')
                    ->label('Клиент: JSON')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (DsarOperation $record) => ! empty($record->client_id))
                    ->url(fn (DsarOperation $record) => route('admin.dsar.export.client', [
                        'org_id' => $record->org_id,
                        'client_id' => $record->client_id,
                        'lang' => $record->language_code ?: 'pl',
                    ]))
                    ->openUrlInNewTab(),
                Action::make('downloadOrgJson')
                    ->label('Салон: JSON')
                    ->icon('heroicon-o-building-office-2')
                    ->url(fn (DsarOperation $record) => route('admin.dsar.export.org', [
                        'org_id' => $record->org_id,
                        'lang' => $record->language_code ?: 'pl',
                    ]))
                    ->openUrlInNewTab(),
                Action::make('downloadAllClientsJson')
                    ->label('Все клиенты: JSON')
                    ->icon('heroicon-o-document-arrow-down')
                    ->url(fn (DsarOperation $record) => route('admin.dsar.export.clients', [
                        'org_id' => $record->org_id,
                        'lang' => $record->language_code ?: 'pl',
                    ]))
                    ->openUrlInNewTab(),
            ])
            ->headerActions([
                Action::make('exportAllClients')
                    ->label('Экспорт всех клиентов: JSON')
                    ->icon('heroicon-o-document-arrow-down')
                    ->form([
                        TextInput::make('org_id')->label('ID салона')->numeric()->required(),
                        Select::make('lang')
                            ->label('Язык')
                            ->options(['pl' => 'PL', 'uk' => 'UK', 'en' => 'EN'])
                            ->default('pl')
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        return redirect()->away(route('admin.dsar.export.clients', [
                            'org_id' => (int) $data['org_id'],
                            'lang' => (string) $data['lang'],
                        ]));
                    }),
                Action::make('exportOrg')
                    ->label('Экспорт салона: JSON')
                    ->icon('heroicon-o-building-office-2')
                    ->form([
                        TextInput::make('org_id')->label('ID салона')->numeric()->required(),
                        Select::make('lang')
                            ->label('Язык')
                            ->options(['pl' => 'PL', 'uk' => 'UK', 'en' => 'EN'])
                            ->default('pl')
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        return redirect()->away(route('admin.dsar.export.org', [
                            'org_id' => (int) $data['org_id'],
                            'lang' => (string) $data['lang'],
                        ]));
                    }),
                Action::make('exportClient')
                    ->label('Экспорт клиента: JSON')
                    ->icon('heroicon-o-user')
                    ->form([
                        TextInput::make('org_id')->label('ID салона')->numeric()->required(),
                        TextInput::make('client_id')->label('ID клиента')->numeric()->required(),
                        Select::make('lang')
                            ->label('Язык')
                            ->options(['pl' => 'PL', 'uk' => 'UK', 'en' => 'EN'])
                            ->default('pl')
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        return redirect()->away(route('admin.dsar.export.client', [
                            'org_id' => (int) $data['org_id'],
                            'client_id' => (int) $data['client_id'],
                            'lang' => (string) $data['lang'],
                        ]));
                    }),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDsarOperations::route('/'),
        ];
    }
}
