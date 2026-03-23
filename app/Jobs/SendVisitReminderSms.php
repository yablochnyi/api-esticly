<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\MarketingAutomation;
use App\Models\MarketingDelivery;
use App\Models\User;
use App\Models\Visit;
use App\Support\TwilioSms;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Lang;
use Throwable;

class SendVisitReminderSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $visitId,
        public int $offsetMin,
    ) {}

    public function handle(): void
    {
        $visit = Visit::query()
            ->with(['service:id,name'])
            ->find($this->visitId);

        if (!$visit || (string) $visit->status === 'cancelled') {
            return;
        }

        $orgId = (int) $visit->user_id;

        $automation = MarketingAutomation::query()
            ->where('user_id', $orgId)
            ->where('key', 'visit_reminder_sms')
            ->first();

        if (!$automation || !$automation->enabled) {
            return;
        }

        if ((int) ($automation->delay_min ?? 0) !== (int) $this->offsetMin) {
            return;
        }

        $delivery = MarketingDelivery::query()->firstOrCreate(
            [
                'user_id' => $orgId,
                'visit_id' => (int) $visit->id,
                'automation_key' => 'visit_reminder_sms',
            ],
            [
                'status' => 'pending',
            ],
        );

        if ($delivery->sent_at) {
            return;
        }

        $clientPhone = trim((string) ($visit->client_phone ?? ''));
        if ($clientPhone === '' && $visit->client_id) {
            $client = Client::query()
                ->where('user_id', $orgId)
                ->where('id', (int) $visit->client_id)
                ->first(['phone']);
            $clientPhone = trim((string) ($client?->phone ?? ''));
        }

        $to = $this->normalizeE164($clientPhone);
        $delivery->to_phone = $to !== '' ? $to : null;

        if ($to === '') {
            $delivery->status = 'skipped';
            $delivery->error = 'client_phone_empty';
            $delivery->save();
            return;
        }

        $org = User::query()->find($orgId, ['id', 'language_code', 'timezone']);
        $locale = $this->languageByPhoneCountryCode($to)
            ?? $this->normalizeLanguageCode((string) ($org?->language_code ?? ''));

        $serviceName = trim((string) ($visit->service?->name ?? 'Visit'));
        $tz = (string) ($org?->timezone ?: config('app.timezone', 'UTC'));
        $visitAtLocal = Carbon::parse($visit->starts_at)->utc()->setTimezone($tz)->format('H:i');

        $body = $this->buildBody(
            minutesBefore: (int) $this->offsetMin,
            serviceName: $serviceName,
            visitAtLocal: $visitAtLocal,
            locale: $locale,
        );

        try {
            $ok = TwilioSms::send($to, $body);
            if (!$ok) {
                $delivery->status = 'failed';
                $delivery->error = 'twilio_not_configured';
                $delivery->save();
                return;
            }

            $delivery->status = 'sent';
            $delivery->sent_at = now();
            $delivery->error = null;
            $delivery->save();
        } catch (Throwable $e) {
            report($e);
            $delivery->status = 'failed';
            $delivery->error = mb_substr($e->getMessage(), 0, 255);
            $delivery->save();
        }
    }

    private function normalizeLanguageCode(string $code): string
    {
        $v = strtolower(trim($code));
        return match ($v) {
            'uk', 'pl', 'en', 'it', 'fr', 'pt', 'de', 'es', 'cs' => $v,
            default => 'en',
        };
    }

    private function languageByPhoneCountryCode(string $e164): ?string
    {
        $map = [
            '+420' => 'cs',
            '+380' => 'uk',
            '+351' => 'pt',
            '+353' => 'en',
            '+358' => 'en',
            '+357' => 'en',
            '+356' => 'en',
            '+352' => 'fr',
            '+49' => 'de',
            '+48' => 'pl',
            '+44' => 'en',
            '+39' => 'it',
            '+34' => 'es',
            '+33' => 'fr',
            '+32' => 'fr',
            '+31' => 'en',
            '+30' => 'en',
            '+1' => 'en',
        ];

        foreach ($map as $prefix => $locale) {
            if (str_starts_with($e164, $prefix)) {
                return $locale;
            }
        }

        return null;
    }

    private function normalizeE164(?string $phone): string
    {
        $raw = trim((string) $phone);
        if ($raw === '') return '';
        if (str_starts_with($raw, '+')) return $raw;

        $digits = (string) preg_replace('/\D+/', '', $raw);
        if ($digits === '') return '';

        return '+' . $digits;
    }

    private function buildBody(int $minutesBefore, string $serviceName, string $visitAtLocal, string $locale): string
    {
        $hours = intdiv($minutesBefore, 60);
        $minutes = $minutesBefore % 60;

        if ($hours > 0 && $minutes > 0) {
            return (string) Lang::get('visit_reminder.sms_in_hours_minutes', [
                'hours' => $hours,
                'minutes' => $minutes,
                'service' => $serviceName,
                'time' => $visitAtLocal,
            ], $locale);
        }

        if ($hours > 0) {
            return (string) Lang::get('visit_reminder.sms_in_hours', [
                'hours' => $hours,
                'service' => $serviceName,
                'time' => $visitAtLocal,
            ], $locale);
        }

        return (string) Lang::get('visit_reminder.sms_in_minutes', [
            'minutes' => $minutesBefore,
            'service' => $serviceName,
            'time' => $visitAtLocal,
        ], $locale);
    }
}
