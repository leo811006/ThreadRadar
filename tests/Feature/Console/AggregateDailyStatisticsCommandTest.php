<?php

use App\Models\CrawlLog;
use App\Models\DailyStatistic;
use App\Models\Keyword;
use App\Models\NotificationLog;
use App\Models\Post;

it('aggregates yesterday statistics by default', function () {
    $yesterday = now()->subDay();
    $keyword = Keyword::factory()->create();

    CrawlLog::create([
        'keyword_id' => $keyword->id,
        'status' => 'success',
        'started_at' => $yesterday,
        'finished_at' => $yesterday,
    ]);

    $post = Post::factory()->create([
        'first_seen_at' => $yesterday,
        'last_seen_at' => $yesterday->copy()->addHour(),
    ]);
    $match = $post->keywordMatches()->create([
        'keyword_id' => $keyword->id,
        'matched_at' => $yesterday,
        'notified_at' => $yesterday,
    ]);

    NotificationLog::create([
        'post_keyword_match_id' => $match->id,
        'channel_type' => 'discord',
        'status' => 'sent',
        'payload' => [],
        'sent_at' => $yesterday,
    ]);

    // 今天發生的活動不應計入昨天的結算
    CrawlLog::create([
        'keyword_id' => $keyword->id,
        'status' => 'success',
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    $this->artisan('app:aggregate-daily-statistics')->assertSuccessful();

    $stat = DailyStatistic::whereDate('date', $yesterday->toDateString())->first();

    expect($stat)->not->toBeNull()
        ->and($stat->search_count)->toBe(1)
        ->and($stat->new_posts_count)->toBe(1)
        ->and($stat->updated_posts_count)->toBe(1)
        ->and($stat->notification_count)->toBe(1);
});

it('aggregates an explicit date passed via --date', function () {
    $targetDate = now()->subDays(5);
    $keyword = Keyword::factory()->create();

    Post::factory()->create([
        'first_seen_at' => $targetDate,
        'last_seen_at' => $targetDate,
    ]);

    $this->artisan('app:aggregate-daily-statistics', ['--date' => $targetDate->toDateString()])
        ->assertSuccessful();

    $stat = DailyStatistic::whereDate('date', $targetDate->toDateString())->first();

    expect($stat)->not->toBeNull()
        ->and($stat->new_posts_count)->toBe(1);
});

it('is idempotent when run twice for the same date and recomputes the values on rerun', function () {
    $yesterday = now()->subDay();
    Post::factory()->create(['first_seen_at' => $yesterday, 'last_seen_at' => $yesterday]);

    $this->artisan('app:aggregate-daily-statistics')->assertSuccessful();

    $firstRun = DailyStatistic::whereDate('date', $yesterday->toDateString())->first();
    expect($firstRun->new_posts_count)->toBe(1);

    // 第二次執行前補上一篇同一天的新文章，驗證 upsert 的 update 分支
    // 真的重新計算了數值，而非僅僅是 no-op 略過既有紀錄。
    Post::factory()->create(['first_seen_at' => $yesterday, 'last_seen_at' => $yesterday]);

    $this->artisan('app:aggregate-daily-statistics')->assertSuccessful();

    expect(DailyStatistic::whereDate('date', $yesterday->toDateString())->count())->toBe(1);

    $secondRun = DailyStatistic::whereDate('date', $yesterday->toDateString())->first();
    expect($secondRun->id)->toBe($firstRun->id)
        ->and($secondRun->new_posts_count)->toBe(2);
});
