<?php

namespace App\Jobs;

use App\Models\DeviceToken;
use App\Models\VisitReminderDelivery;
use App\Support\FcmV1;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Lang;

class SendVisitReminderPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $deliveryId) {}

    public function handle(): void
    {
        /** @var VisitReminderDelivery|null $delivery */
        $delivery = VisitReminderDelivery::query()
            ->with([
                'visit:id,service_id,client_name,starts_at,status',
                'visit.service:id,name',
                'user:id,language_code',
            ])
            ->find($this->deliveryId);

        if (!$delivery) {
            return;
        }

        if ($delivery->status === 'sent' && $delivery->sent_at !== null) {
            return;
        }

        $visit = $delivery->visit;
        $recipient = $delivery->user;
        if (!$visit || !$recipient || (string) $visit->status === 'cancelled') {
            $delivery->status = 'failed';
            $delivery->error = 'visit_or_recipient_missing_or_cancelled';
            $delivery->sent_at = null;
            $delivery->save();
            return;
        }

        if (!FcmV1::isConfigured()) {
            $delivery->status = 'failed';
            $delivery->error = 'fcm_v1_not_configured';
            $delivery->sent_at = null;
            $delivery->save();
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
            $delivery->status = 'failed';
            $delivery->error = 'no_device_tokens';
            $delivery->sent_at = null;
            $delivery->save();
            return;
        }

        $locale = $this->resolveLocale((string) ($recipient->language_code ?? ''));
        $serviceName = trim((string) ($visit->service?->name ?? 'Visit'));
        $clientName = trim((string) ($visit->client_name ?? ''));
        $m = (int) $delivery->offset_min;

        $title = (string) Lang::get('visit_reminder.title', [], $locale);
        $body = $this->buildBody(
            minutesBefore: $m,
            serviceName: $serviceName,
            clientName: $clientName,
            locale: $locale,
        );

        try {
            $result = FcmV1::sendToTokens(
                tokens: $tokens,
                title: $title,
                body: $body,
                data: [
                    'type' => 'visit_reminder',
                    'visit_id' => (string) $visit->id,
                    'offset_min' => (string) $m,
                ],
            );

            if (($result['sent'] ?? 0) > 0) {
                $delivery->status = 'sent';
                $delivery->error = null;
                $delivery->sent_at = Carbon::now('UTC');
            } else {
                $delivery->status = 'failed';
                $delivery->error = 'fcm_v1_no_deliveries';
                $delivery->sent_at = null;
            }
        } catch (\Throwable $e) {
            $delivery->status = 'failed';
            $delivery->error = mb_substr($e->getMessage(), 0, 255);
            $delivery->sent_at = null;
        }

        $delivery->save();
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

    private function buildBody(int $minutesBefore, string $serviceName, string $clientName, string $locale): string
    {
        $hours = intdiv($minutesBefore, 60);
        $minutes = $minutesBefore % 60;

        if ($hours > 0 && $minutes > 0) {
            $base = (string) Lang::get('visit_reminder.in_hours_minutes', [
                'hours' => $hours,
                'minutes' => $minutes,
                'service' => $serviceName,
            ], $locale);
        } elseif ($hours > 0) {
            $base = (string) Lang::get('visit_reminder.in_hours', [
                'hours' => $hours,
                'service' => $serviceName,
            ], $locale);
        } else {
            $base = (string) Lang::get('visit_reminder.in_minutes', [
                'minutes' => $minutesBefore,
                'service' => $serviceName,
            ], $locale);
        }

        if ($clientName === '') {
            return $base;
        }

        return (string) Lang::get('visit_reminder.with_client', [
            'base' => $base,
            'client' => $clientName,
        ], $locale);
    }
}

