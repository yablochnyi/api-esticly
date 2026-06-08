<?php

namespace App\Filament\Resources\SubscriptionPromoCodeResource\Pages;

use App\Filament\Resources\SubscriptionPromoCodeResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Gate;

class EditSubscriptionPromoCode extends EditRecord
{
    protected static string $resource = SubscriptionPromoCodeResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return Gate::allows('access-filament-admin');
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
