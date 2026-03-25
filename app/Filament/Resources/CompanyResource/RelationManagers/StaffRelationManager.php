<?php

namespace App\Filament\Resources\CompanyResource\RelationManagers;

use App\Models\Staff;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class StaffRelationManager extends RelationManager
{
    protected static string $relationship = 'staff';

    public static function canViewForRecord($ownerRecord, string $pageClass): bool
    {
        return Gate::allows('access-filament-admin');
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->label('Deleted')
                    ->dateTime()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
