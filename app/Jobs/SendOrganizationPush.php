<?php

namespace App\Jobs;

use App\Models\AppNotification;
use App\Models\DeviceToken;
use App\Models\User;
use App\Support\FcmV1;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendOrganizationPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $orgId,
        public string $title,
        public string $body,
        public ?int $createdByUserId = null,
    ) {}

    public function handle(): void
    {
        if (!FcmV1::isConfigured()) {
            Log::channel('push')->warning('organization_push_skipped_fcm_not_configured', [
                'org_id' => $this->orgId,
            ]);
            return;
        }

        $rawTitle = trim($this->title);
        $rawBody = trim($this->body);
        $title = Str::limit($rawTitle, 120, '…');
        $body = Str::limit($rawBody, 240, '…');

        if ($rawTitle === '' || $rawBody === '') {
            Log::channel('push')->warning('organization_push_skipped_empty_payload', [
                'org_id' => $this->orgId,
            ]);
            return;
        }

        $recipients = User::query()
            ->where('id', $this->orgId)
            ->orWhere('organization_id', $this->orgId)
            ->get(['id']);

        if ($recipients->isEmpty()) {
            Log::channel('push')->info('organization_push_skipped_no_recipients', [
                'org_id' => $this->orgId,
            ]);
            return;
        }

        $sent = 0;
        $failed = 0;

        foreach ($recipients as $recipient) {
            $tokens = DeviceToken::query()
                ->where('user_id', (int) $recipient->id)
                ->orderByDesc('last_seen_at')
                ->limit(20)
                ->pluck('token')
                ->filter()
                ->values()
                ->all();

            if (empty($tokens)) {
                $failed++;
                continue;
            }

            $notification = AppNotification::query()->create([
                'user_id' => (int) $recipient->id,
                'type' => 'organization_manual_push',
                'title' => Str::limit($rawTitle, 160, '…'),
                'body' => $rawBody,
                'data' => [
                    'org_id' => (string) $this->orgId,
                ],
            ]);

            try {
                $result = FcmV1::sendToTokens(
                    tokens: $tokens,
                    title: $title,
                    body: $body,
                    data: [
                        'type' => 'organization_manual_push',
                        'notification_id' => (string) $notification->id,
                        'org_id' => (string) $this->orgId,
                    ],
                );

                if (($result['sent'] ?? 0) > 0) {
                    $sent++;
                } else {
                    $failed++;
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::channel('push')->error('organization_push_failed', [
                    'org_id' => $this->orgId,
                    'recipient_id' => (int) $recipient->id,
                    'created_by_user_id' => $this->createdByUserId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::channel('push')->info('organization_push_finished', [
            'org_id' => $this->orgId,
            'created_by_user_id' => $this->createdByUserId,
            'recipients_total' => $recipients->count(),
            'recipients_sent' => $sent,
            'recipients_failed' => $failed,
        ]);
    }
}
