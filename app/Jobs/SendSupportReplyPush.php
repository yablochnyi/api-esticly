<?php

namespace App\Jobs;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
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
        $serverKey = trim((string) env('FCM_SERVER_KEY', ''));
        if ($serverKey === '') {
            Log::channel('push')->warning('support_push_skipped_fcm_not_configured', ['org_id' => $this->orgId]);
            return;
        }

        // Notify owner + staff users belonging to this organization.
        $userIds = User::query()
            ->where('id', $this->orgId)
            ->orWhere('organization_id', $this->orgId)
            ->pluck('id')
            ->map(fn ($x) => (int)$x)
            ->values()
            ->all();

        if (empty($userIds)) return;

        $tokens = DeviceToken::query()
            ->whereIn('user_id', $userIds)
            ->orderByDesc('last_seen_at')
            ->limit(50)
            ->pluck('token')
            ->filter()
            ->values()
            ->all();

        if (empty($tokens)) return;

        $title = 'Support';
        $body = Str::limit(trim($this->body), 120, '…');

        try {
            Http::withHeaders([
                'Authorization' => 'key=' . $serverKey,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'registration_ids' => $tokens,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => [
                    'type' => 'support_message',
                    'org_id' => (string) $this->orgId,
                    'thread_id' => (string) $this->threadId,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::channel('push')->error('support_push_failed', [
                'org_id' => $this->orgId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

