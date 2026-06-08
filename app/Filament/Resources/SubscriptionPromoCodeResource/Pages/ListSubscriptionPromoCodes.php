<?php

namespace App\Filament\Resources\SubscriptionPromoCodeResource\Pages;

use App\Filament\Resources\SubscriptionPromoCodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Gate;

class ListSubscriptionPromoCodes extends ListRecords
{
    protected static string $resource = SubscriptionPromoCodeResource::class;

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
