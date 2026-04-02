<?php

namespace App\Jobs;

use App\Models\DeviceToken;
use App\Models\PersonalNote;
use App\Support\FcmV1;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Lang;

class SendPersonalNoteReminderPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $noteId) {}

    public function handle(): void
    {
        /** @var PersonalNote|null $note */
        $note = PersonalNote::query()->with('user:id,language_code')->find($this->noteId);
        if (!$note || !$note->user || $note->remind_at === null) {
            return;
        }

        if ($note->reminder_sent_at !== null) {
            return;
        }

        if (!FcmV1::isConfigured()) {
            $note->reminder_error = 'fcm_v1_not_configured';
            $note->save();
            return;
        }

        $tokens = DeviceToken::query()
            ->where('user_id', (int) $note->user_id)
            ->orderByDesc('last_seen_at')
            ->limit(20)
            ->pluck('token')
            ->filter()
            ->values()
            ->all();

        if (empty($tokens)) {
            $note->reminder_error = 'no_device_tokens';
            $note->save();
            return;
        }

        $locale = $this->resolveLocale((string) ($note->user->language_code ?? ''));
        $title = (string) Lang::get('personal_note_reminder.title', [], $locale);
        $body = (string) Lang::get('personal_note_reminder.body', [
            'text' => mb_strimwidth(trim((string) $note->text), 0, 120, '…'),
        ], $locale);

        try {
            $result = FcmV1::sendToTokens(
                tokens: $tokens,
                title: $title,
                body: $body,
                data: [
                    'type' => 'personal_note_reminder',
                    'note_id' => (string) $note->id,
                ],
            );

            if (($result['sent'] ?? 0) > 0) {
                $note->reminder_sent_at = Carbon::now('UTC');
                $note->reminder_error = null;
            } else {
                $note->reminder_error = 'fcm_v1_no_deliveries';
            }
        } catch (\Throwable $e) {
            $note->reminder_error = mb_substr($e->getMessage(), 0, 255);
        }

        $note->save();
    }

    private function resolveLocale(string $lang): string
    {
        $supported = array_keys((array) config('site_locales.supported', []));
        if (empty($supported)) {
            $supported = ['pl', 'en', 'uk', 'it', 'fr', 'pt', 'de', 'es', 'cs'];
        }

        $lang = strtolower(trim($lang));
        if (in_array($lang, $supported, true)) {
            return $lang;
        }

        $fallback = (string) config('app.fallback_locale', 'en');
        return in_array($fallback, $supported, true) ? $fallback : 'en';
    }
}
