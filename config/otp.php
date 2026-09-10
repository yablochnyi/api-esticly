<?php

return [
    'cache_store' => env('OTP_CACHE_STORE', env('CACHE_STORE', 'database')),
    'ip_hourly_limit' => (int) env('OTP_IP_HOURLY_LIMIT', 5),
];
