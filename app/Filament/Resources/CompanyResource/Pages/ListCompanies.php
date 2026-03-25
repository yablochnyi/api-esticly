<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Resources\CompanyResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Gate;

class ListCompanies extends ListRecords
{
    protected static string $resource = CompanyResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return Gate::allows('access-filament-admin');
    }
}
