<?php

namespace App\Filament\Resources\CompanyResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class PortfolioPhotosRelationManager extends RelationManager
{
    protected static string $relationship = 'portfolioPhotos';

    protected static ?string $title = 'Портфолио';

    public static function canViewForRecord($ownerRecord, string $pageClass): bool
    {
        return Gate::allows('access-filament-admin');
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\ImageColumn::make('url')
                    ->label('Фото')
                    ->square()
                    ->defaultImageUrl(asset('icon.png')),
                Tables\Columns\TextColumn::make('caption')
                    ->label('Подпись')
                    ->searchable()
                    ->placeholder('—')
                    ->wrap(),
                Tables\Columns\TextColumn::make('staff.name')
                    ->label('Команда')
                    ->placeholder('Салон')
                    ->wrap(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Создано')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
