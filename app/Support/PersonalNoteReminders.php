<?php

namespace App\Support;

use App\Jobs\SendPersonalNoteReminderPush;
use App\Models\PersonalNote;

class PersonalNoteReminders
{
    public static function run(): void
    {
        if (!FcmV1::isConfigured()) {
            return;
        }

        $now = now()->utc();
        $windowEnd = (clone $now)->addSeconds(30);

        PersonalNote::query()
            ->whereNotNull('remind_at')
            ->whereNull('reminder_queued_at')
            ->whereNull('deleted_at')
            ->where('remind_at', '<=', $windowEnd)
            ->orderBy('remind_at')
            ->limit(200)
            ->get(['id'])
            ->each(function (PersonalNote $note) use ($now) {
                $updated = PersonalNote::query()
                    ->whereKey($note->id)
                    ->whereNull('reminder_queued_at')
                    ->update([
                        'reminder_queued_at' => $now,
                        'reminder_error' => null,
                    ]);

                if ($updated > 0) {
                    SendPersonalNoteReminderPush::dispatch((int) $note->id);
                }
            });
    }
}
