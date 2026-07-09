<?php

use App\Models\Post;
use App\Services\DashboardService;

it('returns zero attempted and zero failure rate when no post has been analyzed', function () {
    Post::factory()->count(3)->create(['ai_summary' => null, 'ai_analysis_failed_at' => null]);

    $stats = app(DashboardService::class)->aiAnalysisFailureStats();

    expect($stats->attempted)->toBe(0)
        ->and($stats->failed)->toBe(0)
        ->and($stats->failure_rate)->toBe(0.0);
});

it('計算 attempted 只計入已成功或已永久失敗的文章，未分析的不計入分母', function () {
    Post::factory()->count(2)->create(['ai_summary' => '摘要', 'ai_analysis_failed_at' => null]);
    Post::factory()->count(1)->create(['ai_summary' => null, 'ai_analysis_failed_at' => now(), 'ai_analysis_failure_reason' => 'blocked']);
    Post::factory()->count(5)->create(['ai_summary' => null, 'ai_analysis_failed_at' => null]);

    $stats = app(DashboardService::class)->aiAnalysisFailureStats();

    expect($stats->attempted)->toBe(3)
        ->and($stats->failed)->toBe(1)
        ->and($stats->failure_rate)->toBe(33.3);
});
