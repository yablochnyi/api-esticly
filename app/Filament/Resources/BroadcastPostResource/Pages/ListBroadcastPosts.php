<?php

namespace App\Filament\Resources\BroadcastPostResource\Pages;

use App\Filament\Resources\BroadcastPostResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Gate;

class ListBroadcastPosts extends ListRecords
{
    protected static string $resource = BroadcastPostResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return Gate::allows('access-filament-admin');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
