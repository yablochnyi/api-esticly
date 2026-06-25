<?php

return [
    'ios' => [
        'app_store_id' => env('IOS_APP_STORE_ID', '6761251722'),
        'bundle_id' => env('IOS_BUNDLE_ID', 'com.esticly.app'),
        'store_url' => env(
            'IOS_APP_STORE_URL',
            'https://apps.apple.com/app/id6761251722',
        ),
    ],
    'android' => [
        'package_name' => env('GOOGLE_PLAY_PACKAGE_NAME', 'com.esticly.app'),
        'store_url' => env(
            'GOOGLE_PLAY_STORE_URL',
            'https://play.google.com/store/apps/details?id=com.esticly.app',
        ),
    ],
];
