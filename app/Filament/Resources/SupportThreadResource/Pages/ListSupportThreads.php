<?php

namespace App\Filament\Resources\SupportThreadResource\Pages;

use App\Filament\Resources\SupportThreadResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Gate;

class ListSupportThreads extends ListRecords
{
    protected static string $resource = SupportThreadResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return Gate::allows('access-filament-admin');
    }
}
