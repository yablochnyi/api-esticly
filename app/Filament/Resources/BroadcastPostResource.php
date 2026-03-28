<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BroadcastPostResource\Pages;
use App\Models\BroadcastPost;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class BroadcastPostResource extends Resource
{
    protected static ?string $model = BroadcastPost::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';
    protected static ?string $navigationLabel = 'Broadcasts';
    protected static ?int $navigationSort = 8;

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
        return static::canAccess();
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
        $defaultLocale = (string) config('site_locales.default', 'pl');

        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make("title_translations.$defaultLocale")
                    ->label('Title')
                    ->wrap(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'sent' => 'success',
                        'sending' => 'warning',
                        'failed' => 'danger',
                        'queued' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('recipients_total')
                    ->label('Recipients')
                    ->sortable(),
                Tables\Columns\TextColumn::make('recipients_sent')
                    ->label('Sent')
                    ->sortable(),
                Tables\Columns\TextColumn::make('recipients_failed')
                    ->label('Failed')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_by_user_id')
                    ->label('Created by')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sent_at')
                    ->label('Sent at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function form(Schema $schema): Schema
    {
        $components = [];
        foreach ((array) config('site_locales.supported', []) as $code => $meta) {
            $name = (string) ($meta['native'] ?? strtoupper((string) $code));
            $components[] = TextInput::make("title_translations.$code")
                ->label("Title ($name)")
                ->required()
                ->maxLength(120);
            $components[] = Textarea::make("body_translations.$code")
                ->label("Message ($name)")
                ->required()
                ->rows(4)
                ->maxLength(500);
        }

        return $schema->components($components);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBroadcastPosts::route('/'),
            'create' => Pages\CreateBroadcastPost::route('/create'),
            'view' => Pages\ViewBroadcastPost::route('/{record}'),
        ];
    }
}
