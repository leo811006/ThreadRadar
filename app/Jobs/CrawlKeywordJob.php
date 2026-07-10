<?php

namespace App\Jobs;

use App\Contracts\SearchProviderInterface;
use App\Data\SearchQuery;
use App\Exceptions\QuotaExceededException;
use App\Exceptions\ScraperBlockedException;
use App\Models\CrawlLog;
use App\Models\Keyword;
use App\Services\FilterService;
use App\Services\PostUpsertService;
use App\Support\KeywordTimeRangeResolver;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

/**
 * 巡檢主流程（docs/04-system-architecture.md §3）：
 * 呼叫 SearchProvider（配額檢查與保留在其內部以原子操作完成）→ 逐篇去重 upsert →
 * 門檻比對 → 首次達標則 dispatch 通知 Job。
 */
class CrawlKeywordJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(
        public readonly int $keywordId,
    ) {
        $this->onQueue('crawl');
    }

    public function handle(
        SearchProviderInterface $searchProvider,
        KeywordTimeRangeResolver $timeRangeResolver,
        PostUpsertService $postUpsertService,
        FilterService $filterService,
    ): void {
        $keyword = Keyword::with('thresholds')->findOrFail($this->keywordId);
        $startedAt = Date::now();

        // 在實際呼叫外部 API 前先寫入 last_crawled_at：search() 可能因外部服務延遲而
        // 耗時超過 crawl_interval_min，若等到 handle() 結尾才更新，Scheduler 每分鐘的
        // 到期判斷會在這段等待期間誤判「仍然到期」而重複 dispatch 同一關鍵字。
        $keyword->update(['last_crawled_at' => $startedAt]);

        try {
            $query = new SearchQuery(
                keyword: $keyword->name,
                since: $timeRangeResolver->resolveSince($keyword),
                until: $timeRangeResolver->resolveUntil($keyword),
            );

            $results = $searchProvider->search($query);
        } catch (QuotaExceededException $e) {
            // 配額用盡不是暫時性錯誤，不重試（重試在同一天內必然再次失敗），
            // 交由下一輪排程等配額於 UTC 午夜重置後自然恢復。
            $this->logCrawl($keyword, 'quota_exceeded', startedAt: $startedAt, errorMessage: $e->getMessage());
            Log::warning("CrawlKeywordJob skipped for keyword #{$keyword->id}: daily quota exceeded.");

            return;
        } catch (ScraperBlockedException $e) {
            // 疑似被封鎖/選擇器失效不是暫時性錯誤，短時間內重試大機率仍會失敗、
            // 甚至加劇封鎖，故比照配額用盡不重試，交由下一輪排程再嘗試。
            $this->logCrawl($keyword, 'blocked', startedAt: $startedAt, errorMessage: $e->getMessage());
            Log::warning("CrawlKeywordJob skipped for keyword #{$keyword->id}: scraper appears blocked.");

            return;
        } catch (Throwable $e) {
            $this->logCrawl($keyword, 'failed', startedAt: $startedAt, errorMessage: $e->getMessage());

            throw $e;
        }

        $postsCreated = 0;
        $postsUpdated = 0;
        $postIdsForAiAnalysis = [];

        foreach ($results as $postData) {
            try {
                $post = $postUpsertService->upsert($postData);
            } catch (LogicException $e) {
                // 單篇貼文的互動數欄位不一致（如爬蟲 DOM 擷取邏輯出錯導致部分缺值）
                // 是資料品質問題而非暫時性錯誤，重試整個 job 只會用同樣的來源資料
                // 重現同一個例外——略過這一篇、記錄下來，讓其餘篇正常寫入與比對，
                // 而非讓一篇壞資料拖垮整批（見 2026-07-10 事故：改動前曾因未捕捉
                // 此例外，讓單篇失敗中斷同一批次剩餘貼文的處理）。
                Log::warning("CrawlKeywordJob: 略過一篇互動數欄位不一致的貼文（關鍵字 #{$keyword->id}）", [
                    'threads_url' => $postData->threadsUrl,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            $post->wasRecentlyCreated ? $postsCreated++ : $postsUpdated++;

            if ($filterService->matchesThreshold($postData, $keyword)) {
                $match = $post->keywordMatches()->firstOrCreate(
                    ['keyword_id' => $keyword->id],
                    ['matched_at' => Date::now()]
                );

                if ($match->wasRecentlyCreated || $match->notified_at === null) {
                    SendNotificationJob::dispatch($post->id, $match->id)->onQueue('notify');
                }

                if ($match->wasRecentlyCreated && $post->ai_summary === null && $post->ai_analysis_failed_at === null) {
                    $postIdsForAiAnalysis[] = $post->id;
                }
            }
        }

        // 本次巡檢達標的文章一次打包成批次交給 AnalyzePostJob，而非逐篇個別
        // dispatch，藉此以單次（或少數幾次）Gemini API 呼叫取代 N 次呼叫。
        // 批次大小是 AI provider 的調校參數（見 config/gemini.php 說明），非巡檢邏輯本身。
        $batchSize = (int) config('gemini.analysis_batch_size');

        foreach (array_chunk($postIdsForAiAnalysis, $batchSize) as $batch) {
            AnalyzePostJob::dispatch($batch)->onQueue('ai-analysis');
        }

        $this->logCrawl(
            $keyword,
            'success',
            startedAt: $startedAt,
            postsFound: count($results),
            postsCreated: $postsCreated,
            postsUpdated: $postsUpdated,
        );
    }

    private function logCrawl(
        Keyword $keyword,
        string $status,
        CarbonInterface $startedAt,
        int $postsFound = 0,
        int $postsCreated = 0,
        int $postsUpdated = 0,
        ?string $errorMessage = null,
    ): void {
        CrawlLog::create([
            'keyword_id' => $keyword->id,
            'status' => $status,
            'posts_found' => $postsFound,
            'posts_created' => $postsCreated,
            'posts_updated' => $postsUpdated,
            'api_calls_used' => in_array($status, ['quota_exceeded', 'blocked'], true) ? 0 : 1,
            'error_message' => $errorMessage,
            'started_at' => $startedAt,
            'finished_at' => Date::now(),
        ]);
    }
}
