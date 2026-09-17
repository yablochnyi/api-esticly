<?php

return [
    'enforce' => env('APP_CHECK_ENFORCE', true),
    'project_number' => env('FIREBASE_PROJECT_NUMBER', '725624745298'),
    'app_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'FIREBASE_APP_CHECK_APP_IDS',
        '1:725624745298:android:1e5c5292ccde1bb3c2de8b,1:725624745298:ios:63d7fc410b69b9c1c2de8b'
    ))))),
    // Must be shared by all workers and survive deployments.
    'cache_store' => env('APP_CHECK_CACHE_STORE', 'database'),
];
