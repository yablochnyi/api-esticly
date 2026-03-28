<?php

namespace App\Jobs;

use App\Models\BroadcastPost;
use App\Models\User;
use App\Support\FcmV1;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendBroadcastPostPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $broadcastPostId) {}

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
            Log::channel('push')->warning('broadcast_post_skipped_fcm_not_configured', [
                'broadcast_post_id' => $this->broadcastPostId,
            ]);
            return;
        }

        $post->forceFill([
            'status' => 'sending',
            'queued_at' => $post->queued_at ?? now(),
            'recipients_total' => 0,
            'recipients_sent' => 0,
            'recipients_failed' => 0,
            'last_error' => null,
        ])->save();

        $query = User::query()
            ->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('device_tokens')
                    ->whereColumn('device_tokens.user_id', 'users.id');
            })
            ->select('id')
            ->orderBy('id');

        $total = (clone $query)->count();

        if ($total === 0) {
            $post->forceFill([
                'status' => 'sent',
                'recipients_total' => 0,
                'sent_at' => now(),
            ])->save();
            return;
        }

        $post->forceFill([
            'recipients_total' => $total,
        ])->save();

        $query->chunkById(200, function ($users) {
            $ids = $users->pluck('id')->map(fn ($id) => (int) $id)->all();
            if (!empty($ids)) {
                SendBroadcastPostPushChunk::dispatch($this->broadcastPostId, $ids);
            }
        });
    }
}
