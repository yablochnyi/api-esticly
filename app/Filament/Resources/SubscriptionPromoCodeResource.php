<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionPromoCodeResource\Pages;
use App\Models\SubscriptionPromoCode;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class SubscriptionPromoCodeResource extends Resource
{
    protected static ?string $model = SubscriptionPromoCode::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $navigationLabel = 'Subscription promo codes';
    protected static ?int $navigationSort = 10;

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
        return static::canAccess();
    }

    public static function canEdit($record): bool
    {
        return static::canAccess();
    }

    public static function canDelete($record): bool
    {
        return static::canAccess();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->copyable()
                    ->sortable()
                    ->badge(),
                Tables\Columns\TextColumn::make('duration_months')
                    ->label('Months')
                    ->sortable(),
                Tables\Columns\TextColumn::make('used_count')
                    ->label('Used')
                    ->sortable(),
                Tables\Columns\TextColumn::make('max_uses')
                    ->label('Max uses')
                    ->placeholder('∞')
                    ->sortable(),
                Tables\Columns\IconColumn::make('active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('blogger_name')
                    ->label('Blogger')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Code valid until')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
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
            TextInput::make('code')
                ->label('Code')
                ->required()
                ->maxLength(40),
            TextInput::make('duration_months')
                ->label('Access duration, months')
                ->required()
                ->numeric()
                ->minValue(1)
                ->maxValue(36),
            TextInput::make('max_uses')
                ->label('Max uses')
                ->numeric()
                ->minValue(1),
            Toggle::make('active')
                ->label('Active')
                ->default(true),
            TextInput::make('blogger_name')
                ->label('Blogger')
                ->maxLength(120),
            DateTimePicker::make('expires_at')
                ->label('Code valid until'),
            Textarea::make('note')
                ->label('Note')
                ->rows(3),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptionPromoCodes::route('/'),
            'create' => Pages\CreateSubscriptionPromoCode::route('/create'),
            'edit' => Pages\EditSubscriptionPromoCode::route('/{record}/edit'),
        ];
    }
}
