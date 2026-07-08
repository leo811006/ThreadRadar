<?php

namespace App\Jobs;

use App\Contracts\SearchProviderInterface;
use App\Data\SearchQuery;
use App\Models\CrawlLog;
use App\Models\Keyword;
use App\Models\Post;
use App\Services\FilterService;
use App\Services\PostUpsertService;
use App\Support\KeywordTimeRangeResolver;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 巡檢主流程（docs/04-system-architecture.md §3）：
 * 配額檢查 → 呼叫 SearchProvider → 逐篇去重 upsert → 門檻比對 → 首次達標則 dispatch 通知 Job。
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

        $remainingQuota = $searchProvider->remainingQuota();

        if ($remainingQuota !== null && $remainingQuota <= 0) {
            $this->logCrawl($keyword, 'quota_exceeded', startedAt: $startedAt);
            Log::warning("CrawlKeywordJob skipped for keyword #{$keyword->id}: daily quota exceeded.");

            return;
        }

        $query = new SearchQuery(
            keyword: $keyword->name,
            since: $timeRangeResolver->resolveSince($keyword),
            until: $timeRangeResolver->resolveUntil($keyword),
        );

        try {
            $results = $searchProvider->search($query);
        } catch (Throwable $e) {
            $this->logCrawl($keyword, 'failed', startedAt: $startedAt, errorMessage: $e->getMessage());

            throw $e;
        }

        $postsCreated = 0;
        $postsUpdated = 0;

        foreach ($results as $postData) {
            $existedBefore = Post::where('threads_url', $postData->threadsUrl)->exists();

            $post = $postUpsertService->upsert($postData);

            $existedBefore ? $postsUpdated++ : $postsCreated++;

            if ($filterService->matchesThreshold($postData, $keyword)) {
                $match = $post->keywordMatches()->firstOrCreate(
                    ['keyword_id' => $keyword->id],
                    ['matched_at' => Date::now()]
                );

                if ($match->wasRecentlyCreated || $match->notified_at === null) {
                    SendNotificationJob::dispatch($post->id, $match->id)->onQueue('notify');
                }
            }
        }

        $keyword->update(['last_crawled_at' => Date::now()]);

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
            'api_calls_used' => $status === 'quota_exceeded' ? 0 : 1,
            'error_message' => $errorMessage,
            'started_at' => $startedAt,
            'finished_at' => Date::now(),
        ]);
    }
}
