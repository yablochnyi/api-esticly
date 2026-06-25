<?php

namespace App\Filament\Resources\MobileAppVersionResource\Pages;

use App\Filament\Resources\MobileAppVersionResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Gate;

class ListMobileAppVersions extends ListRecords
{
    protected static string $resource = MobileAppVersionResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return Gate::allows('access-filament-admin');
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
