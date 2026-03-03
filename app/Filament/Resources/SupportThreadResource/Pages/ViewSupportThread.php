<?php

namespace App\Filament\Resources\SupportThreadResource\Pages;

use App\Filament\Resources\SupportThreadResource;
use App\Models\SupportMessage;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ViewSupportThread extends ViewRecord
{
    protected static string $resource = SupportThreadResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return Gate::allows('access-filament-admin');
    }

    protected function afterFill(): void
    {
        // Mark all user messages as read by support when opening the thread.
        $thread = $this->record;
        if (!$thread) return;

        DB::transaction(function () use ($thread) {
            SupportMessage::query()
                ->where('thread_id', $thread->id)
                ->where('sender_type', 'user')
                ->whereNull('read_at_support')
                ->update(['read_at_support' => now()]);

            $thread->unread_for_support = 0;
            $thread->save();
        });
    }
}
