<?php

namespace App\Jobs;

use App\Models\BroadcastPost;
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

class SendBroadcastPostPushChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param list<int> $userIds
     */
    public function __construct(
        public int $broadcastPostId,
        public array $userIds,
    ) {}

    public function handle(): void
    {
        $post = BroadcastPost::query()->find($this->broadcastPostId);
        if (!$post) {
            return;
        }

        if (!FcmV1::isConfigured()) {
            $post->forceFill([
                'status' => 'failed',
                'last_error' => 'fcm_not_configured',
            ])->save();
            return;
        }

        $sent = 0;
        $failed = 0;

        $users = User::query()
            ->whereIn('id', $this->userIds)
            ->get(['id', 'language_code']);

        foreach ($users as $user) {
            $tokens = DeviceToken::query()
                ->where('user_id', (int) $user->id)
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

            $locale = BroadcastPost::resolveLocale((string) ($user->language_code ?? ''));
            $title = Str::limit($post->titleFor($locale), 120, '…');
            $body = Str::limit($post->bodyFor($locale), 240, '…');

            try {
                $result = FcmV1::sendToTokens(
                    tokens: $tokens,
                    title: $title,
                    body: $body,
                    data: [
                        'type' => 'broadcast_post',
                        'broadcast_post_id' => (string) $post->id,
                    ],
                );

                if (($result['sent'] ?? 0) > 0) {
                    $sent++;
                } else {
                    $failed++;
                }
            } catch (\Throwable $e) {
                $failed++;
                Log::channel('push')->error('broadcast_post_push_failed', [
                    'broadcast_post_id' => $post->id,
                    'recipient_id' => (int) $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $post->increment('recipients_sent', $sent);
        $post->increment('recipients_failed', $failed);

        $post->refresh();
        if (($post->recipients_sent + $post->recipients_failed) >= $post->recipients_total) {
            $post->forceFill([
                'status' => 'sent',
                'sent_at' => now(),
            ])->save();
        }
    }
}
