<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Resources\CompanyResource;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class ManageCompanyPortfolio extends ManageRelatedRecords
{
    protected static string $resource = CompanyResource::class;
    protected static string $relationship = 'portfolioPhotos';
    protected static ?string $navigationLabel = 'Portfolio';
    protected static ?string $title = 'Company portfolio';

    public static function canAccess(array $parameters = []): bool
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
                Tables\Columns\TextColumn::make('caption')->label('Caption')->searchable()->placeholder('—')->wrap(),
                Tables\Columns\TextColumn::make('staff.name')->label('Staff')->placeholder('Salon')->wrap(),
                Tables\Columns\TextColumn::make('created_at')->label('Created')->dateTime()->sortable(),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
