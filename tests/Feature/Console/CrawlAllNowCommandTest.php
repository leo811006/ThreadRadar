<?php

use App\Contracts\SearchProviderInterface;
use App\Data\PostData;
use App\Jobs\AnalyzePostJob;
use App\Jobs\CrawlKeywordJob;
use App\Jobs\SendNotificationJob;
use App\Models\Keyword;
use App\Models\Post;
use App\Providers\SearchProviders\FakeSearchProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;

function bindFakeProviderForCrawlAllNow(array $results = []): FakeSearchProvider
{
    $fake = new FakeSearchProvider();
    $fake->setResults($results);
    $fake->setRemainingQuota(2200);

    app()->instance(SearchProviderInterface::class, $fake);

    return $fake;
}

it('dispatches crawl job for an active keyword regardless of last_crawled_at', function () {
    Queue::fake([CrawlKeywordJob::class]);

    // 最近才巡檢過、遠未到下次間隔，一般排程不會 dispatch，但此指令應忽略排程強制觸發。
    $keyword = Keyword::factory()->create([
        'is_active' => true,
        'crawl_interval_min' => 60,
        'last_crawled_at' => now()->subMinutes(1),
    ]);

    $this->artisan('app:crawl-all-now')->assertSuccessful();

    Queue::assertPushed(CrawlKeywordJob::class, fn ($job) => $job->keywordId === $keyword->id);
});

it('does not dispatch crawl job for an inactive keyword', function () {
    Queue::fake([CrawlKeywordJob::class]);

    Keyword::factory()->create(['is_active' => false]);

    $this->artisan('app:crawl-all-now')->assertSuccessful();

    Queue::assertNotPushed(CrawlKeywordJob::class);
});

it('dispatches one job per active keyword when multiple exist', function () {
    Queue::fake([CrawlKeywordJob::class]);

    Keyword::factory()->count(3)->create(['is_active' => true]);

    $this->artisan('app:crawl-all-now')->assertSuccessful();

    Queue::assertPushed(CrawlKeywordJob::class, 3);
});

it('with --sync runs the crawl immediately without needing a queue worker', function () {
    // dispatchSync 會讓 CrawlKeywordJob 內部 dispatch 的 AnalyzePostJob/SendNotificationJob
    // 也走 sync queue 真正執行；fake 掉避免測試環境真的呼叫 Gemini/通知外部服務。
    Queue::fake([AnalyzePostJob::class, SendNotificationJob::class]);

    $keyword = Keyword::factory()->create(['is_active' => true, 'name' => 'iPhone']);

    bindFakeProviderForCrawlAllNow([
        new PostData(
            threadsUrl: 'https://www.threads.net/@a/post/sync-test',
            authorName: 'Fake Author',
            authorUsername: 'fakeauthor',
            postedAt: CarbonImmutable::now(),
            content: 'Fake content',
            images: [],
            videos: [],
            viewsCount: 100,
            likesCount: 0,
            repliesCount: 0,
            repostsCount: 0,
            quotesCount: 0,
            isVerifiedAuthor: false,
        ),
    ]);

    $this->artisan('app:crawl-all-now --sync')->assertSuccessful();

    // 同步執行完成後，文章應已直接寫入資料庫，不需要額外啟動 queue worker。
    expect(Post::where('threads_url', 'https://www.threads.net/@a/post/sync-test')->exists())->toBeTrue()
        ->and($keyword->fresh()->last_crawled_at)->not->toBeNull();
});
