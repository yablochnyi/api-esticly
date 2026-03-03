<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\MarketingAutomation;
use App\Models\MarketingDelivery;
use App\Models\User;
use App\Models\Visit;
use App\Support\ShortLinks;
use App\Support\TwilioSms;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendMarketingAutomationSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $orgId,
        public int $visitId,
        public string $automationKey,
    ) {}

    private function phoneDigits(?string $phone): string
    {
        $v = trim((string)$phone);
        if ($v === '') return '';
        return (string)preg_replace('/\D+/', '', $v);
    }

    private function normalizeE164(?string $phone): string
    {
        $raw = trim((string)$phone);
        if ($raw === '') return '';
        if (str_starts_with($raw, '+')) return $raw;
        $digits = $this->phoneDigits($raw);
        if ($digits === '') return '';
        return '+' . $digits;
    }

    private function renderTemplate(string $tpl, array $vars): string
    {
        $out = $tpl;
        foreach ($vars as $k => $v) {
            $out = str_replace('{' . $k . '}', (string)$v, $out);
        }
        return $out;
    }

    private function normalizeLanguageCode(?string $code): string
    {
        $v = strtolower(trim((string)$code));
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

    private function defaultThanksTemplate(string $language): string
    {
        return match ($language) {
            'uk' => 'Дякуємо за візит! Залиште відгук: {review_url}',
            'pl' => 'Dziekujemy za wizyte. Zostaw opinie: {review_url}',
            'it' => 'Grazie per la visita. Lascia una recensione: {review_url}',
            'fr' => 'Merci pour votre visite. Laissez un avis: {review_url}',
            'pt' => 'Obrigado pela visita. Deixe sua avaliacao: {review_url}',
            'de' => 'Danke fur Ihren Besuch. Bitte bewerten Sie uns: {review_url}',
            'es' => 'Gracias por tu visita. Deja una resena: {review_url}',
            'cs' => 'Dekujeme za navstevu. Zanechte recenzi: {review_url}',
            default => 'Thanks for your visit. Please leave a review: {review_url}',
        };
    }

    private function smsSinglePartLimit(string $text): int
    {
        // Conservative one-SMS detection:
        // - ASCII only => assume GSM-7 => 160
        // - any non-ASCII (Cyrillic, emoji, etc) => Unicode => 70
        if (preg_match('/[^\x00-\x7F]/u', $text)) {
            return 70;
        }
        return 160;
    }

    private function ensureSlug(User $org): string
    {
        if (!empty($org->booking_slug)) {
            return (string)$org->booking_slug;
        }

        do {
            $slug = strtolower(\Illuminate\Support\Str::random(8));
            $exists = User::query()->where('booking_slug', $slug)->exists();
        } while ($exists);

        $org->booking_slug = $slug;
        $org->save();

        return $slug;
    }

    private function reviewUrl(?User $org): string
    {
        if (!$org) return '';
        return ShortLinks::reviewShortUrl($org);
    }

    public function handle(): void
    {
        $automation = MarketingAutomation::query()
            ->where('user_id', $this->orgId)
            ->where('key', $this->automationKey)
            ->first();

        if (!$automation || !$automation->enabled) {
            return;
        }

        $visit = Visit::query()
            ->where('user_id', $this->orgId)
            ->where('id', $this->visitId)
            ->first();

        if (!$visit || $visit->status !== 'completed') {
            return;
        }

        $delivery = MarketingDelivery::query()->firstOrCreate(
            [
                'user_id' => $this->orgId,
                'visit_id' => $this->visitId,
                'automation_key' => $this->automationKey,
            ],
            [
                'status' => 'pending',
            ],
        );

        if ($delivery->sent_at) {
            return;
        }

        $client = null;
        if ($visit->client_id) {
            $client = Client::query()
                ->where('user_id', $this->orgId)
                ->where('id', (int)$visit->client_id)
                ->first();
        }

        $to = $this->normalizeE164($client?->phone);
        $delivery->to_phone = $to !== '' ? $to : null;

        if ($to === '') {
            $delivery->status = 'skipped';
            $delivery->error = 'client_phone_empty';
            $delivery->save();
            return;
        }

        $org = User::query()->find($this->orgId);
        $lang = $this->languageByPhoneCountryCode($to)
            ?? $this->normalizeLanguageCode($org?->language_code);

        $tpl = $this->defaultThanksTemplate($lang);

        $body = $this->renderTemplate($tpl, [
            'client_name' => $client?->name ?? '',
            'company_name' => $org?->company_name ?? '',
            'review_url' => $this->reviewUrl($org),
        ]);

        if ($this->automationKey === 'thanks_after_visit') {
            $limit = $this->smsSinglePartLimit($body);
            if (mb_strlen($body, 'UTF-8') > $limit) {
                $delivery->status = 'failed';
                $delivery->error = 'sms_too_long';
                $delivery->save();
                return;
            }
        }

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
            $delivery->error = $e->getMessage();
            $delivery->save();
        }
    }
}
