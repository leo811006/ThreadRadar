<?php

use App\Jobs\CrawlKeywordJob;
use App\Models\Keyword;
use Illuminate\Support\Facades\Queue;

it('dispatches crawl job for a keyword that has never been crawled', function () {
    Queue::fake([CrawlKeywordJob::class]);

    $keyword = Keyword::factory()->create(['is_active' => true, 'last_crawled_at' => null]);

    $this->artisan('app:dispatch-due-crawls')->assertSuccessful();

    Queue::assertPushed(CrawlKeywordJob::class, fn ($job) => $job->keywordId === $keyword->id);
});

it('dispatches crawl job for a keyword whose interval has elapsed', function () {
    Queue::fake([CrawlKeywordJob::class]);

    $keyword = Keyword::factory()->create([
        'is_active' => true,
        'crawl_interval_min' => 10,
        'last_crawled_at' => now()->subMinutes(11),
    ]);

    $this->artisan('app:dispatch-due-crawls')->assertSuccessful();

    Queue::assertPushed(CrawlKeywordJob::class, fn ($job) => $job->keywordId === $keyword->id);
});

it('does not dispatch crawl job for a keyword whose interval has not elapsed', function () {
    Queue::fake([CrawlKeywordJob::class]);

    Keyword::factory()->create([
        'is_active' => true,
        'crawl_interval_min' => 10,
        'last_crawled_at' => now()->subMinutes(5),
    ]);

    $this->artisan('app:dispatch-due-crawls')->assertSuccessful();

    Queue::assertNotPushed(CrawlKeywordJob::class);
});

it('does not dispatch crawl job for an inactive keyword even if due', function () {
    Queue::fake([CrawlKeywordJob::class]);

    Keyword::factory()->create([
        'is_active' => false,
        'crawl_interval_min' => 10,
        'last_crawled_at' => now()->subMinutes(20),
    ]);

    $this->artisan('app:dispatch-due-crawls')->assertSuccessful();

    Queue::assertNotPushed(CrawlKeywordJob::class);
});
