<?php

namespace App\Filament\Resources\CompanyResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class PortfolioPhotosRelationManager extends RelationManager
{
    protected static string $relationship = 'portfolioPhotos';

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
                    ->label('Photo')
                    ->square()
                    ->defaultImageUrl(asset('icon.png')),
                Tables\Columns\TextColumn::make('caption')
                    ->label('Caption')
                    ->searchable()
                    ->placeholder('—')
                    ->wrap(),
                Tables\Columns\TextColumn::make('staff.name')
                    ->label('Staff')
                    ->placeholder('Salon')
                    ->wrap(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
