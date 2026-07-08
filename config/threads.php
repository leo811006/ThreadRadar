<?php

return [
    'app_id' => env('THREADS_APP_ID'),
    'app_secret' => env('THREADS_APP_SECRET'),
    'access_token' => env('THREADS_ACCESS_TOKEN'),
    'daily_quota' => env('THREADS_API_DAILY_QUOTA', 2200),
];
