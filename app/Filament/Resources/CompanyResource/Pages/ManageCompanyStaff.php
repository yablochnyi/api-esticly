<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Resources\CompanyResource;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class ManageCompanyStaff extends ManageRelatedRecords
{
    protected static string $resource = CompanyResource::class;
    protected static string $relationship = 'staff';
    protected static ?string $navigationLabel = 'Staff';
    protected static ?string $title = 'Company staff';

    public static function canAccess(array $parameters = []): bool
    {
        return Gate::allows('access-filament-admin');
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('Name')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('phone')->label('Phone')->searchable()->copyable(),
                Tables\Columns\IconColumn::make('is_active')->label('Active')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->label('Created')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('deleted_at')->label('Deleted')->dateTime()->toggleable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
