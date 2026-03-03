<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupportThreadResource\Pages;
use App\Filament\Resources\SupportThreadResource\RelationManagers\MessagesRelationManager;
use App\Models\SupportThread;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class SupportThreadResource extends Resource
{
    protected static ?string $model = SupportThread::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'Support';
    protected static ?int $navigationSort = 10;

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
        return static::canAccess();
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_message_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('org_id')
                    ->label('Org ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('subject')
                    ->label('Subject')
                    ->wrap()
                    ->limit(40),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (SupportThread $record) => ($record->status ?? 'open') === 'closed' ? 'gray' : 'success')
                    ->sortable(),
                Tables\Columns\TextColumn::make('unread_for_support')
                    ->label('Unread')
                    ->badge()
                    ->color(fn (SupportThread $record) => $record->unread_for_support > 0 ? 'danger' : 'gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_message_preview')
                    ->label('Last message')
                    ->limit(80)
                    ->wrap(),
                Tables\Columns\TextColumn::make('last_message_at')
                    ->label('Last at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-o-eye')
                    ->url(fn (SupportThread $record) => static::getUrl('view', ['record' => $record])),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('org_id')->disabled(),
            TextInput::make('subject')->disabled(),
            TextInput::make('status')->disabled(),
            TextInput::make('unread_for_support')->disabled(),
            TextInput::make('unread_for_user')->disabled(),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            MessagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupportThreads::route('/'),
            'view' => Pages\ViewSupportThread::route('/{record}'),
        ];
    }
}
