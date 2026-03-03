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
    protected static ?string $navigationLabel = 'DSAR';
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
                Tables\Columns\TextColumn::make('org_id')->label('Org ID')->sortable(),
                Tables\Columns\TextColumn::make('org.company_name')
                    ->label('Salon')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('client_id')->label('Client ID')->toggleable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (DsarOperation $record) => match ($record->status) {
                        'done' => 'success',
                        'failed' => 'danger',
                        'running' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('target_email')->label('Target email')->wrap(),
                Tables\Columns\TextColumn::make('actor_user_id')->label('Actor user')->toggleable(),
                Tables\Columns\TextColumn::make('actor_staff_id')->label('Actor staff')->toggleable(),
                Tables\Columns\TextColumn::make('language_code')->label('Lang')->toggleable(),
                Tables\Columns\TextColumn::make('created_at')->label('Created')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('finished_at')->label('Finished')->dateTime()->toggleable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('org_id')
                    ->label('Org')
                    ->form([
                        TextInput::make('org_id')->label('Org ID')->numeric(),
                    ])
                    ->query(function ($query, array $data) {
                        $orgId = isset($data['org_id']) ? (int) $data['org_id'] : 0;
                        return $orgId > 0 ? $query->where('org_id', $orgId) : $query;
                    }),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'pending',
                        'running' => 'running',
                        'done' => 'done',
                        'failed' => 'failed',
                    ]),
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'export_client' => 'export_client',
                        'anonymize_client' => 'anonymize_client',
                        'export_all_clients' => 'export_all_clients',
                        'export_org' => 'export_org',
                    ]),
            ])
            ->actions([
                Action::make('downloadClientJson')
                    ->label('Client JSON')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (DsarOperation $record) => !empty($record->client_id))
                    ->url(fn (DsarOperation $record) => route('admin.dsar.export.client', [
                        'org_id' => $record->org_id,
                        'client_id' => $record->client_id,
                        'lang' => $record->language_code ?: 'pl',
                    ]))
                    ->openUrlInNewTab(),
                Action::make('downloadOrgJson')
                    ->label('Salon JSON')
                    ->icon('heroicon-o-building-office-2')
                    ->url(fn (DsarOperation $record) => route('admin.dsar.export.org', [
                        'org_id' => $record->org_id,
                        'lang' => $record->language_code ?: 'pl',
                    ]))
                    ->openUrlInNewTab(),
                Action::make('downloadAllClientsJson')
                    ->label('All Clients JSON')
                    ->icon('heroicon-o-document-arrow-down')
                    ->url(fn (DsarOperation $record) => route('admin.dsar.export.clients', [
                        'org_id' => $record->org_id,
                        'lang' => $record->language_code ?: 'pl',
                    ]))
                    ->openUrlInNewTab(),
            ])
            ->headerActions([
                Action::make('exportAllClients')
                    ->label('Export Clients JSON')
                    ->icon('heroicon-o-document-arrow-down')
                    ->form([
                        TextInput::make('org_id')->label('Org ID')->numeric()->required(),
                        Select::make('lang')
                            ->label('Language')
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
                    ->label('Export Salon JSON')
                    ->icon('heroicon-o-building-office-2')
                    ->form([
                        TextInput::make('org_id')->label('Org ID')->numeric()->required(),
                        Select::make('lang')
                            ->label('Language')
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
                    ->label('Export Single Client JSON')
                    ->icon('heroicon-o-user')
                    ->form([
                        TextInput::make('org_id')->label('Org ID')->numeric()->required(),
                        TextInput::make('client_id')->label('Client ID')->numeric()->required(),
                        Select::make('lang')
                            ->label('Language')
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
