<?php

namespace App\Jobs;

use App\Models\DeviceToken;
use App\Models\User;
use App\Support\FcmV1;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendSupportReplyPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $orgId,
        public int $threadId,
        public string $body,
    ) {}

    public function handle(): void
    {
        if (!FcmV1::isConfigured()) {
            Log::channel('push')->warning('support_push_skipped_fcm_not_configured', ['org_id' => $this->orgId]);
            return;
        }

        $recipients = User::query()
            ->where('id', $this->orgId)
            ->orWhere('organization_id', $this->orgId)
            ->get(['id', 'language_code']);

        if ($recipients->isEmpty()) return;

        $messageBody = Str::limit(trim($this->body), 120, '…');

        foreach ($recipients as $recipient) {
            $tokens = DeviceToken::query()
                ->where('user_id', (int)$recipient->id)
                ->orderByDesc('last_seen_at')
                ->limit(20)
                ->pluck('token')
                ->filter()
                ->values()
                ->all();

            if (empty($tokens)) {
                continue;
            }

            $locale = $this->resolveLocale((string)($recipient->language_code ?? ''));
            $title = (string) Lang::get('support_push.title', [], $locale);
            $body = (string) Lang::get('support_push.body', ['message' => $messageBody], $locale);

            try {
                FcmV1::sendToTokens(
                    tokens: $tokens,
                    title: $title,
                    body: $body,
                    data: [
                        'type' => 'support_message',
                        'org_id' => (string) $this->orgId,
                        'thread_id' => (string) $this->threadId,
                    ],
                );
            } catch (\Throwable $e) {
                Log::channel('push')->error('support_push_failed', [
                    'org_id' => $this->orgId,
                    'recipient_id' => (int)$recipient->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
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
