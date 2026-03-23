<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LaunchWaitlistSubscriptionResource\Pages;
use App\Models\LaunchWaitlistSubscription;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class LaunchWaitlistSubscriptionResource extends Resource
{
    protected static ?string $model = LaunchWaitlistSubscription::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';
    protected static ?string $navigationLabel = 'Waitlist';
    protected static ?int $navigationSort = 12;

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
            ->defaultSort('subscribed_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('locale')
                    ->label('Locale')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ip')
                    ->label('IP')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('subscribed_at')
                    ->label('Subscribed')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('locale')
                    ->options([
                        'pl' => 'PL',
                        'uk' => 'UK',
                        'en' => 'EN',
                        'it' => 'IT',
                        'fr' => 'FR',
                        'pt' => 'PT',
                        'de' => 'DE',
                        'es' => 'ES',
                        'cs' => 'CS',
                    ]),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLaunchWaitlistSubscriptions::route('/'),
        ];
    }
}
