<?php

return [
    'api_key' => env('GEMINI_API_KEY'),
    'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),

    /*
     * 一次 AnalyzePostJob 最多打包幾篇文章送給 Gemini：免費層級 TPM（每分鐘
     * token 數）上限 100 萬，20 篇文章的內容+prompt 開銷遠低於此上限，
     * 同時仍能大幅減少 API 請求次數（相較於逐篇個別呼叫）以節省 RPM/RPD 額度。
     * 屬於 Gemini provider 的調校參數，不屬於 CrawlKeywordJob 的巡檢邏輯，
     * 故放在此處而非硬編在呼叫端，未來更換 AI provider 或調整批次大小時
     * 只需改這裡。
     */
    'analysis_batch_size' => env('GEMINI_ANALYSIS_BATCH_SIZE', 20),
];
