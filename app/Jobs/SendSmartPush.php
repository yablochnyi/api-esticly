<?php

namespace App\Jobs;

use App\Models\AppNotification;
use App\Models\DeviceToken;
use App\Models\SmartPushDelivery;
use App\Support\FcmV1;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendSmartPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $deliveryId) {}

    public function handle(): void
    {
        /** @var SmartPushDelivery|null $delivery */
        $delivery = SmartPushDelivery::query()->find($this->deliveryId);
        if (!$delivery || $delivery->status === 'sent') {
            return;
        }

        if (!FcmV1::isConfigured()) {
            $delivery->update([
                'status' => 'failed',
                'sent_at' => null,
                'error' => 'fcm_v1_not_configured',
            ]);
            return;
        }

        $tokens = DeviceToken::query()
            ->where('user_id', (int) $delivery->user_id)
            ->orderByDesc('last_seen_at')
            ->limit(20)
            ->pluck('token')
            ->filter()
            ->values()
            ->all();

        if (empty($tokens)) {
            $delivery->update([
                'status' => 'skipped',
                'sent_at' => null,
                'error' => 'no_device_tokens',
            ]);
            return;
        }

        $title = Str::limit(trim((string) $delivery->title), 120, '…');
        $body = Str::limit(trim((string) $delivery->body), 240, '…');
        if ($title === '' || $body === '') {
            $delivery->update([
                'status' => 'failed',
                'sent_at' => null,
                'error' => 'empty_payload',
            ]);
            return;
        }

        $notification = AppNotification::query()->create([
            'user_id' => (int) $delivery->user_id,
            'type' => (string) $delivery->type,
            'title' => $title,
            'body' => $body,
            'data' => [
                'org_id' => (string) $delivery->org_id,
                'delivery_id' => (string) $delivery->id,
                'local_date' => $delivery->local_date?->toDateString(),
                ...((array) ($delivery->data ?? [])),
            ],
        ]);

        try {
            $result = FcmV1::sendToTokens(
                tokens: $tokens,
                title: $title,
                body: $body,
                data: [
                    'type' => (string) $delivery->type,
                    'notification_id' => (string) $notification->id,
                    'org_id' => (string) $delivery->org_id,
                    'delivery_id' => (string) $delivery->id,
                    'local_date' => $delivery->local_date?->toDateString() ?? '',
                    ...array_map('strval', (array) ($delivery->data ?? [])),
                ],
            );

            $delivery->update([
                'status' => (($result['sent'] ?? 0) > 0) ? 'sent' : 'failed',
                'sent_at' => (($result['sent'] ?? 0) > 0) ? Carbon::now('UTC') : null,
                'error' => (($result['sent'] ?? 0) > 0) ? null : 'fcm_v1_no_deliveries',
            ]);
        } catch (\Throwable $e) {
            $delivery->update([
                'status' => 'failed',
                'sent_at' => null,
                'error' => mb_substr($e->getMessage(), 0, 255),
            ]);

            Log::channel('push')->error('smart_push_failed', [
                'delivery_id' => (int) $delivery->id,
                'org_id' => (int) $delivery->org_id,
                'user_id' => (int) $delivery->user_id,
                'type' => (string) $delivery->type,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
