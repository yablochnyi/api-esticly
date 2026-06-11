<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramAdminNotifier
{
    public static function newRegistration(array $data): void
    {
        self::send(self::lines([
            'New registration',
            self::field('Org ID', $data['org_id'] ?? null),
            self::field('Company', $data['company_name'] ?? null),
            self::field('Phone', $data['phone'] ?? null),
            self::field('Language', $data['language_code'] ?? null),
            self::field('Currency', $data['currency_code'] ?? null),
            self::field('Timezone', $data['timezone'] ?? null),
            self::field('Registered at', $data['registered_at'] ?? null),
        ]), 'registration');
    }

    public static function supportMessage(array $data): void
    {
        self::send(self::lines([
            'New support message',
            self::field('Thread ID', $data['thread_id'] ?? null),
            self::field('Org ID', $data['org_id'] ?? null),
            self::field('Company', $data['company_name'] ?? null),
            self::field('Phone', $data['phone'] ?? null),
            self::field('User ID', $data['user_id'] ?? null),
            self::field('Message', $data['message'] ?? null),
        ]), 'support');
    }

    private static function send(string $text, string $event): void
    {
        $token = trim((string) env('TELEGRAM_BOT_TOKEN', ''));
        $chatId = trim((string) env('TELEGRAM_ADMIN_CHAT_ID', ''));

        if ($token === '' || $chatId === '') {
            return;
        }

        try {
            $response = Http::timeout(5)->post(
                sprintf('https://api.telegram.org/bot%s/sendMessage', $token),
                [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'disable_web_page_preview' => true,
                ],
            );

            if (!$response->successful()) {
                Log::warning('telegram_admin_notification_failed', [
                    'event' => $event,
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 1000),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('telegram_admin_notification_exception', [
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private static function field(string $label, mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : "{$label}: {$value}";
    }

    private static function lines(array $lines): string
    {
        return implode("\n", array_values(array_filter(
            $lines,
            fn ($line) => is_string($line) && trim($line) !== '',
        )));
    }
}
