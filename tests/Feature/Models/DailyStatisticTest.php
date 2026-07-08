<?php

use App\Models\DailyStatistic;
use Illuminate\Support\Facades\Date;

it('stores the date as a plain Y-m-d string so exact-match queries work', function () {
    DailyStatistic::create([
        'date' => '2026-07-07',
        'search_count' => 1,
        'new_posts_count' => 1,
        'updated_posts_count' => 1,
        'notification_count' => 1,
    ]);

    expect(DailyStatistic::where('date', '2026-07-07')->exists())->toBeTrue();
});

it('returns the date attribute as a Carbon instance', function () {
    $stat = DailyStatistic::create([
        'date' => '2026-07-07',
        'search_count' => 1,
        'new_posts_count' => 1,
        'updated_posts_count' => 1,
        'notification_count' => 1,
    ]);

    expect($stat->date)->toBeInstanceOf(Carbon\Carbon::class)
        ->and($stat->date->toDateString())->toBe('2026-07-07');
});

it('upserts by date without throwing a unique constraint violation on repeated calls', function () {
    $date = Date::parse('2026-07-07');

    DailyStatistic::upsert(
        [['date' => $date->toDateString(), 'search_count' => 1, 'new_posts_count' => 1, 'updated_posts_count' => 0, 'notification_count' => 0]],
        uniqueBy: ['date'],
        update: ['search_count', 'new_posts_count', 'updated_posts_count', 'notification_count'],
    );

    DailyStatistic::upsert(
        [['date' => $date->toDateString(), 'search_count' => 2, 'new_posts_count' => 2, 'updated_posts_count' => 0, 'notification_count' => 0]],
        uniqueBy: ['date'],
        update: ['search_count', 'new_posts_count', 'updated_posts_count', 'notification_count'],
    );

    expect(DailyStatistic::count())->toBe(1)
        ->and(DailyStatistic::first()->search_count)->toBe(2);
});
