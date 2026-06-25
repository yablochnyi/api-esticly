<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MobileAppVersionResource\Pages;
use App\Models\MobileAppVersion;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class MobileAppVersionResource extends Resource
{
    protected static ?string $model = MobileAppVersion::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-circle';
    protected static ?string $navigationLabel = 'App versions';
    protected static ?int $navigationSort = 11;
    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return Gate::allows('access-filament-admin');
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return static::canAccess();
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('platform')
                    ->label('Platform')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => strtoupper($state)),
                Tables\Columns\TextColumn::make('latest_version')
                    ->label('Latest version'),
                Tables\Columns\TextColumn::make('minimum_version')
                    ->label('Minimum version'),
                Tables\Columns\IconColumn::make('enabled')
                    ->label('Enabled')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('platform')
                ->label('Platform')
                ->disabled()
                ->dehydrated(),
            TextInput::make('minimum_version')
                ->label('Minimum version')
                ->helperText('Versions below this value must update before continuing.')
                ->required()
                ->regex('/^\d+(\.\d+){1,3}$/')
                ->maxLength(32),
            TextInput::make('store_url')
                ->label('Store URL')
                ->url()
                ->required()
                ->maxLength(500),
            Toggle::make('enabled')
                ->label('Enable version checks')
                ->default(true),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMobileAppVersions::route('/'),
            'edit' => Pages\EditMobileAppVersion::route('/{record}/edit'),
        ];
    }
}
