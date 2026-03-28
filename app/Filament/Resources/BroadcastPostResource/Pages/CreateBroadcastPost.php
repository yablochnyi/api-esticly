<?php

namespace App\Filament\Resources\BroadcastPostResource\Pages;

use App\Filament\Resources\BroadcastPostResource;
use App\Jobs\SendBroadcastPostPush;
use App\Models\BroadcastPost;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class CreateBroadcastPost extends CreateRecord
{
    protected static string $resource = BroadcastPostResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return Gate::allows('access-filament-admin');
    }

    protected function handleRecordCreation(array $data): Model
    {
        /** @var BroadcastPost $record */
        $record = static::getModel()::query()->create([
            'created_by_user_id' => auth()->id(),
            'title_translations' => $data['title_translations'] ?? [],
            'body_translations' => $data['body_translations'] ?? [],
            'status' => 'queued',
            'queued_at' => now(),
        ]);

        SendBroadcastPostPush::dispatch((int) $record->id);

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
