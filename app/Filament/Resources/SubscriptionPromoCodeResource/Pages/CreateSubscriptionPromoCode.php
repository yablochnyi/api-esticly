<?php

namespace App\Filament\Resources\SubscriptionPromoCodeResource\Pages;

use App\Filament\Resources\SubscriptionPromoCodeResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class CreateSubscriptionPromoCode extends CreateRecord
{
    protected static string $resource = SubscriptionPromoCodeResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return Gate::allows('access-filament-admin');
    }

    protected function handleRecordCreation(array $data): Model
    {
        $data['created_by_user_id'] = auth()->id();

        return static::getModel()::query()->create($data);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
