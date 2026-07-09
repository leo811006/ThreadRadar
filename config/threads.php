<?php

return [
    'app_id' => env('THREADS_APP_ID'),
    'app_secret' => env('THREADS_APP_SECRET'),
    'access_token' => env('THREADS_ACCESS_TOKEN'),
    'daily_quota' => env('THREADS_API_DAILY_QUOTA', 2200),

    /*
     * 非官方 headless browser 爬蟲設定（見 App\Providers\SearchProviders\ThreadsScraperSearchProvider）。
     * 個人非商業用途暫時替代方案，官方 threads_keyword_search 權限審核通過後應切回官方 provider。
     */
    'scraper' => [
        'node_binary' => env('THREADS_SCRAPER_NODE_BINARY', 'node'),
        'script_path' => env('THREADS_SCRAPER_SCRIPT_PATH', base_path('scripts/threads-scraper.mjs')),
        'timeout_seconds' => env('THREADS_SCRAPER_TIMEOUT_SECONDS', 60),

        // 全域併發鎖的 TTL 秒數，獨立於 timeout_seconds 設定，避免調整爬蟲逾時秒數時
        // 意外連帶改變鎖的行為（見 ThreadsScraperSearchProvider 的鎖競態說明）。
        // 應大於 timeout_seconds，留足夠緩衝覆蓋排隊等鎖與 Node 啟動開銷。
        'lock_ttl_seconds' => env('THREADS_SCRAPER_LOCK_TTL_SECONDS', 180),
    ],
];
