<?php

namespace App\Support\Parsers;

use App\Data\PostData;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * 將 threads-scraper.mjs 從 threads.com/search 頁面 DOM 解析出的原始資料正規化為 PostData。
 *
 * 已知能力限制（非 bug，是資料來源本身的限制，短期無計畫修復）：
 * - views/quotes 一律回傳 null：views 僅出現在貼文詳情頁（搜尋結果頁 DOM 沒有），
 *   quotes 在 Threads 前端本身沒有獨立統計欄位可顯示（見 threads-scraper.mjs 說明）。
 * - likes/replies/reposts 由 threads-scraper.mjs 從動作列 svg 擷取後回傳整數（已可用）。
 * - images/videos 一律回傳空陣列：搜尋結果頁 DOM 未解析媒體附件連結。
 *   PostUpsertService 僅在貼文首次建立時寫入這兩個欄位，故經由本 parser
 *   建立的貼文會永久沒有媒體資料，即使該貼文實際上有圖片/影片。
 */
class ThreadsScraperPostParser
{
    /**
     * @param  array<string, mixed>  $raw  單筆貼文的爬蟲原始輸出
     */
    public function parse(array $raw): PostData
    {
        if (empty($raw['permalink'])) {
            throw new RuntimeException('threads-scraper.mjs 回傳資料缺少 permalink 欄位。');
        }

        return new PostData(
            threadsUrl: $raw['permalink'],
            authorName: $raw['username'] ?? '',
            authorUsername: $raw['username'] ?? '',
            postedAt: $this->parseTimestamp($raw['timestamp'] ?? null),
            content: $raw['text'] ?? '',
            images: [],
            videos: [],
            viewsCount: null,
            likesCount: $raw['likes'] ?? null,
            repliesCount: $raw['replies'] ?? null,
            repostsCount: $raw['reposts'] ?? null,
            quotesCount: null,
            isVerifiedAuthor: false,
        );
    }

    /**
     * DOM 缺少 <time> 元素時 timestamp 為空字串；此時不可 fallback 為 now()——
     * 那會讓實際上是舊文的貼文被誤標記為剛發布，進而通過巡檢的時間區間篩選觸發誤報。
     * 寧可拋出例外交由呼叫端（ThreadsScraperSearchProvider）記錄並略過該筆。
     */
    private function parseTimestamp(?string $timestamp): CarbonImmutable
    {
        if (empty($timestamp)) {
            throw new RuntimeException('threads-scraper.mjs 回傳資料缺少 timestamp 欄位。');
        }

        return CarbonImmutable::parse($timestamp);
    }
}
