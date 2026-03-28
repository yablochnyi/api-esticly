<?php

namespace App\Filament\Resources\BroadcastPostResource\Pages;

use App\Filament\Resources\BroadcastPostResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Gate;

class ViewBroadcastPost extends ViewRecord
{
    protected static string $resource = BroadcastPostResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return Gate::allows('access-filament-admin');
    }
}
