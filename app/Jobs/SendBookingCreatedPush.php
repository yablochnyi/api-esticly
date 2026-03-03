<?php

namespace App\Jobs;

use App\Models\DeviceToken;
use App\Models\User;
use App\Models\Visit;
use App\Support\FcmV1;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;

class SendBookingCreatedPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $orgId,
        public int $visitId,
    ) {}

    public function handle(): void
    {
        if (!FcmV1::isConfigured()) {
            Log::channel('push')->warning('booking_push_skipped_fcm_not_configured', ['org_id' => $this->orgId, 'visit_id' => $this->visitId]);
            return;
        }

        $org = User::query()->find($this->orgId);
        if (!$org) {
            return;
        }

        $visit = Visit::query()
            ->where('id', $this->visitId)
            ->where('user_id', $this->orgId)
            ->with(['service:id,name', 'staff:id,name'])
            ->first();

        if (!$visit) {
            return;
        }

        $recipientIds = [$this->orgId];
        if (!empty($visit->staff_id)) {
            $staffUserId = User::query()
                ->where('organization_id', $this->orgId)
                ->where('staff_id', (int) $visit->staff_id)
                ->value('id');
            if ($staffUserId) {
                $recipientIds[] = (int) $staffUserId;
            }
        }
        $recipientIds = array_values(array_unique($recipientIds));

        $recipients = User::query()
            ->whereIn('id', $recipientIds)
            ->get(['id', 'language_code']);

        $tz = $org->timezone ?: (config('app.timezone') ?: 'UTC');
        $startsLocal = Carbon::parse($visit->starts_at)->utc()->setTimezone($tz);
        $startsAtLabel = $startsLocal->format('d.m H:i');
        $serviceName = trim((string) ($visit->service?->name ?? 'Visit'));
        $clientName = trim((string) ($visit->client_name ?? 'Client'));
        $staffName = trim((string) ($visit->staff?->name ?? ''));

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
                continue;
            }

            $locale = $this->resolveLocale((string) ($recipient->language_code ?? ''));
            $title = (string) Lang::get('booking_push.title', [], $locale);

            $body = (string) Lang::get('booking_push.body', [
                'service' => $serviceName,
                'client' => $clientName,
                'time' => $startsAtLabel,
            ], $locale);

            if ($staffName !== '' && (int) $recipient->id === $this->orgId) {
                $body .= ' • '.(string) Lang::get('booking_push.staff_suffix', ['staff' => $staffName], $locale);
            }

            try {
                FcmV1::sendToTokens(
                    tokens: $tokens,
                    title: $title,
                    body: $body,
                    data: [
                        'type' => 'online_booking_created',
                        'org_id' => (string) $this->orgId,
                        'visit_id' => (string) $visit->id,
                    ],
                );
            } catch (\Throwable $e) {
                Log::channel('push')->error('booking_push_failed', [
                    'org_id' => $this->orgId,
                    'visit_id' => $this->visitId,
                    'recipient_id' => (int) $recipient->id,
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

