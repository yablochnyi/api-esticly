<?php

return [
    'captcha' => [
        'enabled' => env('OTP_CAPTCHA_ENABLED', false),
        'site_key' => env('TURNSTILE_SITE_KEY', ''),
        'secret_key' => env('TURNSTILE_SECRET_KEY', ''),
    ],
    'sending_enabled' => env('OTP_SENDING_ENABLED', true),
    'registered_only' => env('OTP_REGISTERED_ONLY', false),
    'paused_allowed_phone_hashes' => array_values(array_filter(array_map('trim', explode(',', (string) env('OTP_PAUSED_ALLOWED_PHONE_HASHES', ''))))),
    'cache_store' => env('OTP_CACHE_STORE', env('CACHE_STORE', 'database')),
    'ip_hourly_limit' => (int) env('OTP_IP_HOURLY_LIMIT', 2),
    'phone_hourly_limit' => (int) env('OTP_PHONE_HOURLY_LIMIT', 2),
    'global_daily_limit' => (int) env('OTP_GLOBAL_DAILY_LIMIT', 20),
    'daily_timezone' => env('OTP_DAILY_TIMEZONE', 'Europe/Warsaw'),
];
