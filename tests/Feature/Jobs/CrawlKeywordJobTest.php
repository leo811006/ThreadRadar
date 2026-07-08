<?php

use App\Contracts\SearchProviderInterface;
use App\Data\PostData;
use App\Jobs\CrawlKeywordJob;
use App\Jobs\SendNotificationJob;
use App\Models\CrawlLog;
use App\Models\Keyword;
use App\Models\Post;
use App\Models\PostKeywordMatch;
use App\Providers\SearchProviders\FakeSearchProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;

function makeFakePost(array $overrides = []): PostData
{
    return new PostData(
        threadsUrl: $overrides['threadsUrl'] ?? 'https://www.threads.net/@user/post/' . uniqid(),
        authorName: $overrides['authorName'] ?? 'Fake Author',
        authorUsername: $overrides['authorUsername'] ?? 'fakeauthor',
        postedAt: CarbonImmutable::now(),
        content: $overrides['content'] ?? 'Fake content',
        images: [],
        videos: [],
        viewsCount: $overrides['viewsCount'] ?? 0,
        likesCount: $overrides['likesCount'] ?? 0,
        repliesCount: $overrides['repliesCount'] ?? 0,
        repostsCount: $overrides['repostsCount'] ?? 0,
        quotesCount: $overrides['quotesCount'] ?? 0,
        isVerifiedAuthor: $overrides['isVerifiedAuthor'] ?? false,
    );
}

function bindFakeProvider(array $results, ?int $quota = 2200): FakeSearchProvider
{
    $fake = new FakeSearchProvider();
    $fake->setResults($results);
    $fake->setRemainingQuota($quota);

    app()->instance(SearchProviderInterface::class, $fake);

    return $fake;
}

it('creates posts and dispatches notification job when threshold is met', function () {
    Queue::fake([SendNotificationJob::class]);

    $keyword = Keyword::factory()->create(['name' => 'iPhone']);
    $keyword->thresholds()->create(['metric' => 'views', 'operator' => '>=', 'value' => 1000]);

    bindFakeProvider([
        makeFakePost(['threadsUrl' => 'https://www.threads.net/@a/post/1', 'viewsCount' => 5000]),
    ]);

    (new CrawlKeywordJob($keyword->id))->handle(
        app(SearchProviderInterface::class),
        app(App\Support\KeywordTimeRangeResolver::class),
        app(App\Services\PostUpsertService::class),
        app(App\Services\FilterService::class),
    );

    expect(Post::count())->toBe(1)
        ->and(PostKeywordMatch::count())->toBe(1);

    Queue::assertPushed(SendNotificationJob::class);
});

it('does not dispatch notification job when threshold is not met', function () {
    Queue::fake([SendNotificationJob::class]);

    $keyword = Keyword::factory()->create(['name' => 'iPhone']);
    $keyword->thresholds()->create(['metric' => 'views', 'operator' => '>=', 'value' => 10000]);

    bindFakeProvider([
        makeFakePost(['threadsUrl' => 'https://www.threads.net/@a/post/2', 'viewsCount' => 100]),
    ]);

    (new CrawlKeywordJob($keyword->id))->handle(
        app(SearchProviderInterface::class),
        app(App\Support\KeywordTimeRangeResolver::class),
        app(App\Services\PostUpsertService::class),
        app(App\Services\FilterService::class),
    );

    expect(Post::count())->toBe(1)
        ->and(PostKeywordMatch::count())->toBe(0);

    Queue::assertNotPushed(SendNotificationJob::class);
});

it('does not dispatch notification job again for an already-notified match', function () {
    Queue::fake([SendNotificationJob::class]);

    $keyword = Keyword::factory()->create(['name' => 'iPhone']);
    $keyword->thresholds()->create(['metric' => 'views', 'operator' => '>=', 'value' => 1000]);

    $url = 'https://www.threads.net/@a/post/repeat';

    bindFakeProvider([
        makeFakePost(['threadsUrl' => $url, 'viewsCount' => 5000]),
    ]);

    // First crawl: creates match and dispatches notification.
    (new CrawlKeywordJob($keyword->id))->handle(
        app(SearchProviderInterface::class),
        app(App\Support\KeywordTimeRangeResolver::class),
        app(App\Services\PostUpsertService::class),
        app(App\Services\FilterService::class),
    );

    // Simulate that the notification job already ran and marked notified_at.
    PostKeywordMatch::first()->update(['notified_at' => now()]);

    Queue::fake([SendNotificationJob::class]);

    bindFakeProvider([
        makeFakePost(['threadsUrl' => $url, 'viewsCount' => 6000]),
    ]);

    // Second crawl: same post still matches, but should not notify again.
    (new CrawlKeywordJob($keyword->id))->handle(
        app(SearchProviderInterface::class),
        app(App\Support\KeywordTimeRangeResolver::class),
        app(App\Services\PostUpsertService::class),
        app(App\Services\FilterService::class),
    );

    expect(PostKeywordMatch::count())->toBe(1);

    Queue::assertNotPushed(SendNotificationJob::class);
});

it('skips crawling and logs quota_exceeded when daily quota is depleted', function () {
    Queue::fake([SendNotificationJob::class]);

    $keyword = Keyword::factory()->create(['name' => 'iPhone']);

    bindFakeProvider([], quota: 0);

    (new CrawlKeywordJob($keyword->id))->handle(
        app(SearchProviderInterface::class),
        app(App\Support\KeywordTimeRangeResolver::class),
        app(App\Services\PostUpsertService::class),
        app(App\Services\FilterService::class),
    );

    expect(Post::count())->toBe(0)
        ->and(CrawlLog::where('status', 'quota_exceeded')->count())->toBe(1);

    Queue::assertNotPushed(SendNotificationJob::class);
});

it('records a crawl log on successful crawl', function () {
    $keyword = Keyword::factory()->create(['name' => 'iPhone']);

    bindFakeProvider([
        makeFakePost(['threadsUrl' => 'https://www.threads.net/@a/post/log-test']),
    ]);

    (new CrawlKeywordJob($keyword->id))->handle(
        app(SearchProviderInterface::class),
        app(App\Support\KeywordTimeRangeResolver::class),
        app(App\Services\PostUpsertService::class),
        app(App\Services\FilterService::class),
    );

    $log = CrawlLog::first();

    expect($log->status)->toBe('success')
        ->and($log->posts_found)->toBe(1)
        ->and($log->posts_created)->toBe(1)
        ->and($keyword->fresh()->last_crawled_at)->not->toBeNull();
});
