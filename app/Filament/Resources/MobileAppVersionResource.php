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

    protected static ?string $navigationLabel = 'Версии приложения';

    protected static ?string $modelLabel = 'версию приложения';

    protected static ?string $pluralModelLabel = 'Версии приложения';

    protected static string|\UnitEnum|null $navigationGroup = 'Система';

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
                    ->label('Платформа')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => strtoupper($state)),
                Tables\Columns\TextColumn::make('latest_version')
                    ->label('Последняя версия'),
                Tables\Columns\TextColumn::make('minimum_version')
                    ->label('Минимальная версия'),
                Tables\Columns\IconColumn::make('enabled')
                    ->label('Включено')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Обновлено')
                    ->dateTime('d.m.Y H:i')
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
                ->label('Платформа')
                ->disabled()
                ->dehydrated(),
            TextInput::make('minimum_version')
                ->label('Минимальная версия')
                ->helperText('Для более ранних версий обновление будет обязательным.')
                ->required()
                ->regex('/^\d+(\.\d+){1,3}$/')
                ->maxLength(32),
            TextInput::make('store_url')
                ->label('Ссылка на магазин')
                ->url()
                ->required()
                ->maxLength(500),
            Toggle::make('enabled')
                ->label('Проверять версию приложения')
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
