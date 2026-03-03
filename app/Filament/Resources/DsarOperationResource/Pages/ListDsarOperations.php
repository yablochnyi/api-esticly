<?php

namespace App\Filament\Resources\DsarOperationResource\Pages;

use App\Filament\Resources\DsarOperationResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Gate;

class ListDsarOperations extends ListRecords
{
    protected static string $resource = DsarOperationResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return Gate::allows('access-filament-admin');
    }
}
